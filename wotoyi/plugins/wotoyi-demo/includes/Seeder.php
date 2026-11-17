<?php
/**
 * Loads and removes the demo data. Creation goes through the marketplace's own public PHP API (local transport →
 * same Receiver as the signed HTTP sync), so validation, versioning and indexes are the real ones.
 *
 * Demo marker ("is_demo"): the marketplace schema has no such column and we do not alter it. A row is demo when it
 * belongs to a dealer with transport = 'local' AND source_dealer_uuid starting with PREFIX. Everything else
 * (vehicles, services, equipment, leads, favourites, sync log) is reached only through those dealer ids.
 *
 * @package Wotoyi\Demo
 */

declare(strict_types=1);

namespace Wotoyi\Demo;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Cache;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Sync\LocalTransport;

defined( 'ABSPATH' ) || exit;

final class Seeder {

	public const PREFIX = 'wotoyi-demo-';
	private const LOCK  = 'wotoyi_demo_lock';

	public static function marketplace_ready(): bool {
		return class_exists( Plugin::class ) && function_exists( 'wotoyi_marketplace_local_receive' );
	}

	/**
	 * @return array{dealers:int,vehicles:int,services:int,errors:list<string>}
	 * @throws \RuntimeException Codes: production_confirmation_required, marketplace_missing, demo_busy, local_mode_locked_by_constant, local_mode_unavailable, or a marketplace error code.
	 */
	public static function load( bool $confirm_production = false ): array {
		if ( 'production' === wp_get_environment_type() && ! $confirm_production ) {
			throw new \RuntimeException( 'production_confirmation_required' );
		}
		if ( ! self::marketplace_ready() ) {
			throw new \RuntimeException( 'marketplace_missing' );
		}
		if ( get_transient( self::LOCK ) ) {
			throw new \RuntimeException( 'demo_busy' );
		}
		set_transient( self::LOCK, 1, 120 );
		$restore = self::enable_local_mode();
		$result  = array( 'dealers' => 0, 'vehicles' => 0, 'services' => 0, 'errors' => array() );
		try {
			$vehicles = Dataset::vehicles();
			$services = Dataset::services();
			foreach ( Dataset::dealers() as $d ) {
				$dealer = wotoyi_marketplace_local_register_dealer( self::PREFIX . $d['key'], $d['profile'] );
				if ( $d['verified'] ) {
					Plugin::dealers()->set_verified( (int) Plugin::dealers()->find_by_uuid( (string) $dealer['id'] )['id'], true );
				}
				++$result['dealers'];
				foreach ( array( 'vehicle' => $vehicles, 'service' => $services ) as $type => $set ) {
					foreach ( $set[ $d['key'] ] ?? array() as $item ) {
						$event = array(
							'event_id'    => 'wotoyi-demo-' . $item['source_id'] . '-v1',
							'event_type'  => $type . '.published',
							'occurred_at' => gmdate( 'c' ),
							'api_version' => 'v1',
							'data'        => array( 'source_id' => $item['source_id'], 'version' => 1 ) + $item['data'],
						);
						$res = wotoyi_marketplace_local_receive( (string) $dealer['id'], $event );
						if ( in_array( $res['status'] ?? '', array( 'applied', 'unchanged' ), true ) && empty( $res['warnings'] ) ) {
							++$result[ $type . 's' ];
						} else {
							$result['errors'][] = $item['source_id'] . ': ' . ( $res['status'] ?? '?' ) . ' ' . wp_json_encode( $res['warnings'] ?? $res['error'] ?? '' );
						}
					}
				}
			}
			update_option( 'wotoyi_demo_state', array( 'loaded_at' => gmdate( 'c' ), 'version' => WOTOYI_DEMO_VERSION ), false );
		} finally {
			$restore();
			delete_transient( self::LOCK );
		}
		return $result;
	}

	/**
	 * Switch marketplace local mode on for the seeding call only. Returns the closure that puts the previous
	 * option back exactly (including "option absent").
	 */
	private static function enable_local_mode(): \Closure {
		if ( defined( 'WOTOYI_MP_LOCAL_MODE' ) ) {
			if ( ! WOTOYI_MP_LOCAL_MODE ) {
				delete_transient( self::LOCK );
				throw new \RuntimeException( 'local_mode_locked_by_constant' );
			}
			return static function (): void {}; // Already on by configuration: nothing to restore.
		}
		$missing  = new \stdClass();
		$original = get_option( Settings::OPTION, $missing );
		$restore  = static function () use ( $original, $missing ): void {
			if ( $original === $missing ) {
				delete_option( Settings::OPTION );
			} else {
				update_option( Settings::OPTION, $original );
			}
		};
		update_option( Settings::OPTION, array_merge( is_array( $original ) ? $original : array(), array( 'local_mode' => true ) ) );
		if ( ! LocalTransport::enabled() ) { // A filter or constant overrides the option.
			$restore();
			delete_transient( self::LOCK );
			throw new \RuntimeException( 'local_mode_unavailable' );
		}
		return $restore;
	}

	/** @return list<int> Ids of the demo dealers. */
	public static function demo_dealer_ids(): array {
		$wpdb = Db::wpdb();
		$sql  = Db::prepare( 'SELECT id FROM ' . Db::table( 'dealers' ) . ' WHERE transport = %s AND source_dealer_uuid LIKE %s', array( 'local', $wpdb->esc_like( self::PREFIX ) . '%' ) );
		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/** @return array{dealers:int,vehicles:int,services:int} */
	public static function counts(): array {
		$ids = self::demo_dealer_ids();
		if ( ! $ids ) {
			return array( 'dealers' => 0, 'vehicles' => 0, 'services' => 0 );
		}
		$in  = Db::placeholders( count( $ids ), '%d' );
		$cnt = static fn( string $table ): int => (int) Db::wpdb()->get_var( Db::prepare( 'SELECT COUNT(*) FROM ' . Db::table( $table ) . " WHERE dealer_id IN ({$in}) AND status <> 'deleted'", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return array( 'dealers' => count( $ids ), 'vehicles' => $cnt( 'vehicles' ), 'services' => $cnt( 'services' ) );
	}

	/**
	 * Hard-delete every demo row (and rows that hang off demo dealers). One transaction.
	 *
	 * @return array<string,int> Rows deleted per table.
	 */
	public static function remove(): array {
		$ids = self::demo_dealer_ids();
		if ( ! $ids ) {
			delete_option( 'wotoyi_demo_state' );
			return array();
		}
		$wpdb    = Db::wpdb();
		$in      = Db::placeholders( count( $ids ), '%d' );
		$deleted = array();
		$run     = static function ( string $label, string $sql, array $args ) use ( $wpdb, &$deleted ): void {
			$n = $wpdb->query( Db::prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			if ( false === $n ) {
				throw new \RuntimeException( 'delete_failed_' . $label );
			}
			$deleted[ $label ] = (int) $n;
		};
		Db::begin();
		try {
			$vsel = 'SELECT id FROM ' . Db::table( 'vehicles' ) . " WHERE dealer_id IN ({$in})";
			$ssel = 'SELECT id FROM ' . Db::table( 'services' ) . " WHERE dealer_id IN ({$in})";
			$run( 'vehicle_equipment', 'DELETE FROM ' . Db::table( 'vehicle_equipment' ) . " WHERE vehicle_id IN ({$vsel})", $ids );
			$run( 'favourites_vehicles', 'DELETE FROM ' . Db::table( 'favourites' ) . " WHERE item_type = 'vehicle' AND item_id IN ({$vsel})", $ids );
			$run( 'favourites_services', 'DELETE FROM ' . Db::table( 'favourites' ) . " WHERE item_type = 'service' AND item_id IN ({$ssel})", $ids );
			$run( 'leads', 'DELETE FROM ' . Db::table( 'leads' ) . " WHERE dealer_id IN ({$in})", $ids );
			$run( 'vehicles', 'DELETE FROM ' . Db::table( 'vehicles' ) . " WHERE dealer_id IN ({$in})", $ids );
			$run( 'services', 'DELETE FROM ' . Db::table( 'services' ) . " WHERE dealer_id IN ({$in})", $ids );
			$run( 'sync_log', 'DELETE FROM ' . Db::table( 'sync_log' ) . " WHERE dealer_id IN ({$in})", $ids );
			$run( 'sync_nonces', 'DELETE FROM ' . Db::table( 'sync_nonces' ) . " WHERE dealer_id IN ({$in})", $ids );
			$run( 'dealers', 'DELETE FROM ' . Db::table( 'dealers' ) . " WHERE id IN ({$in})", $ids );
			Db::commit();
		} catch ( \Throwable $e ) {
			Db::rollback();
			throw $e;
		}
		Cache::bump();
		delete_option( 'wotoyi_demo_state' );
		return $deleted;
	}
}
