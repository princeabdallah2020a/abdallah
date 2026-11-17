<?php
/**
 * Buyer leads captured on the marketplace. The marketplace is the outbox: the dealer platform pulls
 * pending leads over the signed API and acknowledges them (no lead is lost if delivery fails).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class LeadRepository {

	public const CHANNELS = array( 'web', 'whatsapp', 'call', 'ai', 'form' );

	public function table(): string {
		return Db::table( 'leads' );
	}

	/**
	 * @param array<string,mixed> $lead Already sanitised: dealer_id, vehicle_id?, service_id?, user_id?, name?, phone?, email?, message?, channel, locale?, consent, ip_hash?
	 * @return array{id:int,uuid:string}
	 */
	public function create( array $lead ): array {
		$uuid = Uuid::v7();
		$ok   = Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'uuid'       => $uuid,
				'dealer_id'  => (int) $lead['dealer_id'],
				'vehicle_id' => $lead['vehicle_id'] ?? null,
				'service_id' => $lead['service_id'] ?? null,
				'user_id'    => $lead['user_id'] ?? null,
				'name'       => $lead['name'] ?? null,
				'phone'      => $lead['phone'] ?? null,
				'email'      => $lead['email'] ?? null,
				'message'    => $lead['message'] ?? null,
				'channel'    => $lead['channel'] ?? 'web',
				'locale'     => $lead['locale'] ?? null,
				'consent'    => empty( $lead['consent'] ) ? 0 : 1,
				'ip_hash'    => $lead['ip_hash'] ?? null,
				'created_at' => Clock::mysql(),
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'lead_insert_failed' );
		}
		return array( 'id' => (int) Db::wpdb()->insert_id, 'uuid' => $uuid );
	}

	/**
	 * Pending leads of ONE dealer (tenant scoped).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function pending_for_dealer( int $dealer_id, int $limit ): array {
		$limit = max( 1, min( 100, $limit ) );
		$sql   = Db::prepare( "SELECT l.uuid, l.name, l.phone, l.email, l.message, l.channel, l.locale, l.consent, l.created_at, v.uuid AS vehicle_uuid, v.source_id AS vehicle_source_id, s.uuid AS service_uuid, s.source_id AS service_source_id FROM {$this->table()} l LEFT JOIN " . Db::table( 'vehicles' ) . ' v ON v.id = l.vehicle_id LEFT JOIN ' . Db::table( 'services' ) . " s ON s.id = l.service_id WHERE l.dealer_id = %d AND l.delivery_status = %s ORDER BY l.id ASC LIMIT %d", array( $dealer_id, 'pending', $limit ) );
		$rows  = Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as &$r ) {
			$r['consent'] = (bool) (int) $r['consent'];
		}
		return (array) $rows;
	}

	/**
	 * Mark leads delivered. Only rows that belong to the dealer are touched.
	 *
	 * @param list<string> $uuids
	 */
	public function acknowledge( int $dealer_id, array $uuids ): int {
		$uuids = array_values( array_filter( array_slice( $uuids, 0, 100 ), static fn( $u ): bool => is_string( $u ) && Uuid::is_valid( $u ) ) );
		if ( ! $uuids ) {
			return 0;
		}
		$sql = Db::prepare(
			"UPDATE {$this->table()} SET delivery_status = %s, delivered_at = %s, delivery_attempts = delivery_attempts + 1 WHERE dealer_id = %d AND delivery_status = %s AND uuid IN (" . Db::placeholders( count( $uuids ) ) . ')',
			array_merge( array( 'delivered', Clock::mysql(), $dealer_id, 'pending' ), $uuids )
		);
		Db::wpdb()->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) Db::wpdb()->rows_affected;
	}

	public function count_for_dealer( int $dealer_id ): int {
		return (int) Db::wpdb()->get_var( Db::prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE dealer_id = %d", array( $dealer_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
