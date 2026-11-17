<?php
/**
 * Vehicles master (the dealer's source of truth for stock). Same ~85-field model as the marketplace catalogue plus
 * dealer-only fields (stock ref, supplier, purchase cost, internal notes) that are never synced.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\VehicleFields;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class VehicleService extends SyncedEntityService {

	/** @return array<string,mixed> Entity definition (same shape as Schema\Entities). */
	public static function definition(): array {
		return array(
			'fields'      => VehicleFields::defs(),
			'soft_delete' => true,
			'filters'     => array( 'status', 'make', 'model', 'body_type', 'fuel_type', 'transmission', 'drive_type', 'condition_type', 'offer_type', 'city', 'availability' ),
			'search'      => array( 'title', 'make', 'model', 'stock_ref', 'vin', 'plate' ),
			'sortable'    => array( 'created_at', 'updated_at', 'price', 'year', 'mileage_km', 'published_at' ),
		);
	}

	public function __construct() {
		parent::__construct( 'vehicles', self::definition() );
	}

	protected function event_entity(): string {
		return 'vehicle';
	}

	protected function sync_field_names(): array {
		return VehicleFields::sync_fields();
	}

	protected function transitions(): array {
		return array(
			'draft'       => array( 'published', 'deleted' ),
			'published'   => array( 'unpublished', 'sold', 'deleted' ),
			'unpublished' => array( 'published', 'sold', 'deleted' ),
			'sold'        => array( 'published', 'deleted' ),
			'deleted'     => array(),
		);
	}

	protected function publish_problems( array $m ): array {
		$missing = array();
		if ( empty( $m['title'] ) && ( empty( $m['make'] ) || empty( $m['model'] ) ) ) {
			$missing[] = 'title_or_make_and_model';
		}
		$offer = (string) ( $m['offer_type'] ?? 'sale' );
		if ( in_array( $offer, array( 'sale', 'both' ), true ) && empty( $m['price'] ) ) {
			$missing[] = 'price';
		}
		if ( in_array( $offer, array( 'rental', 'both' ), true ) && empty( $m['rental_price_day'] ) && empty( $m['rental_price_month'] ) ) {
			$missing[] = 'rental_price';
		}
		return $missing;
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( empty( $merged['title'] ) && ! empty( $merged['make'] ) ) {
			$merged['title'] = mb_substr( trim( $merged['make'] . ' ' . ( $merged['model'] ?? '' ) . ' ' . ( $merged['year'] ?? '' ) ), 0, 160 );
		}
		parent::finalize( $merged, $old );
		return $merged;
	}

	protected function derived_columns( array $merged, array $changed ): array {
		if ( empty( $merged['title'] ) && ! empty( $merged['make'] ) ) {
			return array( 'title' => mb_substr( trim( $merged['make'] . ' ' . ( $merged['model'] ?? '' ) . ' ' . ( $merged['year'] ?? '' ) ), 0, 160 ) );
		}
		return array();
	}

	protected function status_columns( array $old, string $target ): array {
		$cols = parent::status_columns( $old, $target );
		if ( 'sold' === $target ) {
			$cols['sold_at'] = Clock::mysql();
		} elseif ( in_array( $target, array( 'published', 'unpublished' ), true ) && ! empty( $old['sold_at'] ) ) {
			$cols['sold_at']    = null;
			$cols['sold_price'] = null;
		}
		return $cols;
	}

	protected function assert_can_delete( array $row ): void {
		$uuid = (string) $row['uuid'];
		if ( TenantDb::count( 'rentals', array( 'vehicle_uuid' => $uuid, 'status' => array( 'in', array( 'reserved', 'active' ) ) ) ) > 0
			|| TenantDb::count( 'deals', array( 'vehicle_uuid' => $uuid, 'status' => 'confirmed' ) ) > 0 ) {
			throw ApiException::conflict( 'wotoyi_vehicle_in_use', 'This vehicle has an active rental or a confirmed sale.' );
		}
	}

	protected function build_where( array $q ): array {
		$where = parent::build_where( $q );
		foreach ( array( 'price' => 'price', 'year' => 'year', 'mileage' => 'mileage_km' ) as $param => $col ) {
			$min = $q[ $param . '_min' ] ?? null;
			$max = $q[ $param . '_max' ] ?? null;
			if ( ( is_numeric( $min ) || is_numeric( $max ) ) ) {
				$where[ $col ] = array( 'range', is_numeric( $min ) ? (int) $min : null, is_numeric( $max ) ? (int) $max : null );
			}
		}
		return $where;
	}

	public function present( array $row ): array {
		$out                 = parent::present( $row );
		$out['published_at'] = $row['published_at'] ?? null;
		$out['sold_at']      = $row['sold_at'] ?? null;
		if ( $this->can_finance() ) {
			$out['sold_price'] = isset( $row['sold_price'] ) ? (int) $row['sold_price'] : null;
		}
		return $out;
	}

	/** Dashboard/other services: live (not deleted) vehicle by uuid or null. @return array<string,mixed>|null */
	public function find( string $uuid ): ?array {
		return TenantDb::get( 'vehicles', array( 'uuid' => $uuid, 'deleted_at' => array( 'null' ) ) );
	}
}
