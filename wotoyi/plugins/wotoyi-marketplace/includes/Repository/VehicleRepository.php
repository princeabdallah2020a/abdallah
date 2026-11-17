<?php
/**
 * Vehicle persistence.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Schema\Enums;
use Wotoyi\Marketplace\Schema\VehicleFields;
use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class VehicleRepository extends AbstractListingRepository {

	protected function logical(): string {
		return 'vehicles';
	}

	public function defs(): array {
		return VehicleFields::defs();
	}

	protected function derive( array $clean ): array {
		$gallery = is_array( $clean['gallery'] ?? null ) ? $clean['gallery'] : array();
		$cover   = $clean['cover_image'] ?? null;
		$photos  = count( $gallery ) + ( null !== $cover && ! in_array( $cover, $gallery, true ) ? 1 : 0 );
		$text    = strtolower( implode( ' ', array_filter( array(
			$clean['title'] ?? null, $clean['make'] ?? null, $clean['model'] ?? null, $clean['trim_level'] ?? null,
			$clean['body_type'] ?? null, $clean['fuel_type'] ?? null, $clean['ext_colour'] ?? null,
			$clean['city'] ?? null, $clean['area'] ?? null, isset( $clean['year'] ) ? (string) $clean['year'] : null,
		) ) ) );

		return array(
			'photo_count' => min( 65535, $photos ),
			'has_video'   => empty( $clean['video_url'] ) ? 0 : 1,
			'has_360'     => empty( $clean['view360_url'] ) ? 0 : 1,
			'search_text' => mb_substr( $text, 0, 1000 ),
		);
	}

	protected function slug_base( array $clean ): string {
		return sanitize_title( trim( ( $clean['make'] ?? '' ) . ' ' . ( $clean['model'] ?? '' ) . ' ' . ( $clean['year'] ?? '' ) ) );
	}

	protected function after_write( int $id, array $clean ): void {
		$db = Db::wpdb();
		if ( false === $db->delete( Db::table( 'vehicle_equipment' ), array( 'vehicle_id' => $id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'equipment_delete_failed' ); // Aborts the surrounding transaction.
		}
		if ( empty( $clean['equipment'] ) || ! is_array( $clean['equipment'] ) ) {
			return;
		}
		foreach ( $clean['equipment'] as $category => $codes ) {
			foreach ( $codes as $code ) {
				$ok = $db->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					Db::table( 'vehicle_equipment' ),
					array(
						'vehicle_id' => $id,
						'code'       => $code,
						'category'   => $category,
					)
				);
				if ( false === $ok ) {
					throw new \RuntimeException( 'equipment_insert_failed' );
				}
			}
		}
	}

	/** @return array<string,list<string>> category => codes */
	public function equipment_for( int $vehicle_id ): array {
		$sql  = Db::prepare( 'SELECT category, code FROM ' . Db::table( 'vehicle_equipment' ) . ' WHERE vehicle_id = %d ORDER BY category, code', array( $vehicle_id ) );
		$rows = Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['category'] ][] = $r['code'];
		}
		return $out;
	}

	/** Marketplace-managed trust fields (inspection badge): admin-only path, never from dealer payloads. */
	public function set_inspection( int $vehicle_id, bool $inspected, ?string $date, ?string $report_url ): void {
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'wotoyi_inspected'      => $inspected ? 1 : 0,
				'inspection_date'       => $date,
				'inspection_report_url' => $report_url,
			),
			array( 'id' => $vehicle_id )
		);
		\Wotoyi\Marketplace\Support\Cache::bump();
	}

	/** Status values accepted for vehicles. */
	public static function statuses(): array {
		return Enums::VEHICLE_STATUS;
	}
}
