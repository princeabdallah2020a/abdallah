<?php
/**
 * Sales. Completing a deal marks the vehicle as sold (one transaction, one marketplace `vehicle.sold` event) and
 * moves the linked lead to `won`. Revenue figures in the dashboard come only from completed deals.
 *
 * Status machine: draft -> confirmed -> completed; draft/confirmed -> cancelled. Completed deals are final
 * (cancelling a completed sale must be an explicit, audited correction process, not a status flip).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class DealService extends EntityService {

	private const NEXT = array(
		'draft'     => array( 'confirmed', 'completed', 'cancelled' ),
		'confirmed' => array( 'completed', 'cancelled' ),
		'completed' => array(),
		'cancelled' => array(),
	);

	public function __construct( private VehicleService $vehicles, private LeadService $leads ) {
		parent::__construct( 'deals', Entities::get( 'deals' ) );
	}

	protected function defaults(): array {
		$member = TenantContext::member();
		return array(
			'status'   => 'draft',
			'currency' => TenantContext::tenant()['currency'] ?? 'XOF',
			'sold_by'  => $member['uuid'] ?? null,
		);
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		$status = (string) $merged['status'];
		$from   = $old['status'] ?? null;
		if ( 'completed' === $from ) {
			throw ApiException::conflict( 'wotoyi_deal_final', 'A completed deal cannot be modified.' );
		}
		if ( null !== $from && $from !== $status && ! in_array( $status, self::NEXT[ $from ] ?? array(), true ) ) {
			throw ApiException::conflict( 'wotoyi_invalid_transition', "Cannot change a {$from} deal to {$status}.", array( 'from' => $from, 'to' => $status ) );
		}
		if ( ! empty( $merged['discount'] ) && (int) $merged['discount'] > (int) $merged['amount'] ) {
			throw ApiException::invalid( 'Discount cannot exceed the amount.', array( 'fields' => array( 'discount' ) ) );
		}
		$enters_sale = in_array( $status, array( 'confirmed', 'completed' ), true ) && $from !== $status;
		if ( $enters_sale ) {
			$vehicle = $this->vehicles->find( (string) $merged['vehicle_uuid'] );
			if ( ! $vehicle || ! in_array( $vehicle['status'], array( 'published', 'unpublished' ), true ) ) {
				throw ApiException::conflict( 'wotoyi_vehicle_not_available', 'The vehicle is not available for sale.' );
			}
			$others = TenantDb::count( 'deals', array( 'vehicle_uuid' => $merged['vehicle_uuid'], 'status' => array( 'in', array( 'confirmed', 'completed' ) ), 'uuid' => array( '!=', (string) ( $merged['uuid'] ?? '' ) ) ) );
			if ( $others > 0 ) {
				throw ApiException::conflict( 'wotoyi_vehicle_already_sold', 'Another confirmed or completed deal exists for this vehicle.' );
			}
		}
		return null;
	}

	public function create( array $input, array $system = array() ): array {
		if ( 'completed' === ( $input['status'] ?? null ) ) {
			$system['sold_at'] = Clock::mysql();
		}
		return parent::create( $input, $system );
	}

	public function update( string $uuid, array $input, array $system = array() ): array {
		$row = $this->row( $uuid );
		if ( 'completed' === ( $input['status'] ?? null ) && 'completed' !== $row['status'] ) {
			$system['sold_at'] = Clock::mysql();
		}
		return parent::update( $uuid, $input, $system );
	}

	protected function after_create( array $row ): void {
		$this->on_completed( null, $row );
	}

	protected function after_update( array $old, array $new ): void {
		$this->on_completed( $old, $new );
	}

	/** @param array<string,mixed>|null $old @param array<string,mixed> $new */
	private function on_completed( ?array $old, array $new ): void {
		if ( 'completed' !== $new['status'] || ( $old && 'completed' === $old['status'] ) ) {
			return;
		}
		$this->vehicles->transition( (string) $new['vehicle_uuid'], 'sold', array( 'sold_price' => (int) $new['amount'] ) );
		if ( ! empty( $new['lead_uuid'] ) ) {
			$this->leads->mark_won_silently( (string) $new['lead_uuid'] );
		}
		AuditLog::record( 'deals.completed', 'deals', (string) $new['uuid'], array( 'vehicle' => $new['vehicle_uuid'] ) );
	}

	/**
	 * "Mark vehicle as sold": creates a completed deal for the vehicle (and therefore the revenue record).
	 *
	 * @param array<string,mixed> $input amount (required), customer_uuid, lead_uuid, payment_method, discount, notes
	 * @return array<string,mixed>
	 */
	public function sell_vehicle( string $vehicle_uuid, array $input ): array {
		return $this->create( array( 'vehicle_uuid' => $vehicle_uuid, 'status' => 'completed' ) + $input );
	}

	public function present( array $row ): array {
		$out = parent::present( $row );
		if ( ! $this->can_finance() ) {
			unset( $out['trade_in_value'] );
		}
		return $out;
	}
}
