<?php
/**
 * Rentals and the booking calendar. Conflict rule: a vehicle cannot have two overlapping reserved/active rentals.
 * Days = end_date - start_date (nights), minimum 1; total defaults to days x daily_rate when not given.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;

defined( 'ABSPATH' ) || exit;

final class RentalService extends EntityService {

	private const NEXT = array(
		'reserved'  => array( 'active', 'cancelled' ),
		'active'    => array( 'returned', 'cancelled' ),
		'returned'  => array(),
		'cancelled' => array(),
	);

	public function __construct( private VehicleService $vehicles ) {
		parent::__construct( 'rentals', Entities::get( 'rentals' ) );
	}

	protected function defaults(): array {
		return array( 'status' => 'reserved', 'currency' => TenantContext::tenant()['currency'] ?? 'XOF' );
	}

	public static function days( string $start, string $end ): int {
		return max( 1, (int) round( ( strtotime( $end . ' UTC' ) - strtotime( $start . ' UTC' ) ) / DAY_IN_SECONDS ) );
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( strcmp( (string) $merged['end_date'], (string) $merged['start_date'] ) < 0 ) {
			throw ApiException::invalid( 'end_date must not be before start_date.', array( 'fields' => array( 'end_date' ) ) );
		}
		$status = (string) $merged['status'];
		$from   = $old['status'] ?? null;
		if ( null !== $from && $from !== $status && ! in_array( $status, self::NEXT[ $from ] ?? array(), true ) ) {
			throw ApiException::conflict( 'wotoyi_invalid_transition', "Cannot change a {$from} rental to {$status}.", array( 'from' => $from, 'to' => $status ) );
		}
		$vehicle = $this->vehicles->find( (string) $merged['vehicle_uuid'] );
		if ( ! $vehicle || in_array( $vehicle['status'], array( 'sold', 'deleted' ), true ) ) {
			throw ApiException::conflict( 'wotoyi_vehicle_not_available', 'The vehicle is not available for rental.' );
		}
		if ( in_array( $status, array( 'reserved', 'active' ), true ) ) {
			$clash = TenantDb::count(
				'rentals',
				array(
					'vehicle_uuid' => $merged['vehicle_uuid'],
					'status'       => array( 'in', array( 'reserved', 'active' ) ),
					'start_date'   => array( '<=', (string) $merged['end_date'] ),
					'end_date'     => array( '>=', (string) $merged['start_date'] ),
					'uuid'         => array( '!=', (string) ( $merged['uuid'] ?? '' ) ),
				)
			);
			if ( $clash > 0 ) {
				throw ApiException::conflict( 'wotoyi_rental_overlap', 'The vehicle is already booked for these dates.' );
			}
		}
		if ( null === $old && empty( $merged['total'] ) && ! empty( $merged['daily_rate'] ) ) {
			$merged['total'] = self::days( (string) $merged['start_date'], (string) $merged['end_date'] ) * (int) $merged['daily_rate'];
		}
		return $merged;
	}

	protected function derived_columns( array $merged, array $changed ): array {
		if ( ! in_array( 'total', $changed, true ) && array_intersect( $changed, array( 'start_date', 'end_date', 'daily_rate' ) ) && ! empty( $merged['daily_rate'] ) ) {
			return array( 'total' => self::days( (string) $merged['start_date'], (string) $merged['end_date'] ) * (int) $merged['daily_rate'] );
		}
		return array();
	}

	/**
	 * Calendar: rentals overlapping [from, to] (max 366 days), without personal data.
	 *
	 * @return array<string,mixed>
	 */
	public function calendar( string $from, string $to ): array {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) || strcmp( $to, $from ) < 0 ) {
			throw ApiException::invalid( 'from and to must be dates (YYYY-MM-DD), from <= to.' );
		}
		if ( RentalService::days( $from, $to ) > 366 ) {
			throw ApiException::invalid( 'Range too large (max 366 days).' );
		}
		$rows = TenantDb::select( 'rentals', array( 'start_date' => array( '<=', $to ), 'end_date' => array( '>=', $from ), 'status' => array( 'in', array( 'reserved', 'active', 'returned' ) ) ), array( 'order' => array( array( 'start_date', 'asc' ) ), 'limit' => 500 ) );
		return array(
			'from'  => $from,
			'to'    => $to,
			'items' => array_map( static fn( array $r ): array => array( 'id' => $r['uuid'], 'vehicle_id' => $r['vehicle_uuid'], 'customer_id' => $r['customer_uuid'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'], 'status' => $r['status'] ), $rows ),
		);
	}
}
