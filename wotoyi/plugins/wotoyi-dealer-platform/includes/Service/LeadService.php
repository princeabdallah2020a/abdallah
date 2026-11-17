<?php
/**
 * Leads and the sales pipeline. Marketplace leads are imported idempotently (external_id = marketplace lead uuid).
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

final class LeadService extends EntityService {

	public function __construct( private ActivityService $activities, private CustomerService $customers ) {
		parent::__construct( 'leads', Entities::get( 'leads' ) );
	}

	protected function defaults(): array {
		return array( 'stage' => 'new', 'source' => 'other', 'currency' => TenantContext::tenant()['currency'] ?? 'XOF' );
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( empty( $merged['name'] ) && empty( $merged['phone'] ) && empty( $merged['email'] ) && empty( $merged['customer_uuid'] ) ) {
			throw ApiException::invalid( 'A lead needs a name, phone, email or customer.', array( 'fields' => array( 'name', 'phone', 'email' ) ) );
		}
		return null;
	}

	public function update( string $uuid, array $input, array $system = array() ): array {
		$before = $this->row( $uuid );
		$res    = parent::update( $uuid, $input, $system );
		if ( isset( $input['stage'] ) && $res['stage'] !== $before['stage'] ) {
			$this->activities->log( 'lead', $uuid, 'stage_change', $before['stage'] . ' -> ' . $res['stage'] );
		}
		return $res;
	}

	/** @return array<string,mixed> */
	public function change_stage( string $uuid, string $stage, ?string $lost_reason = null ): array {
		if ( ! in_array( $stage, Entities::LEAD_STAGES, true ) ) {
			throw ApiException::invalid( 'Unknown stage.', array( 'fields' => array( 'stage' ) ) );
		}
		$input = array( 'stage' => $stage );
		if ( 'lost' === $stage && null !== $lost_reason ) {
			$input['lost_reason'] = $lost_reason;
		}
		return $this->update( $uuid, $input );
	}

	/**
	 * Kanban data: for each stage the count and the most recent leads.
	 *
	 * @return array<string,mixed>
	 */
	public function pipeline( int $per_stage = 50 ): array {
		$counts = TenantDb::group_count( 'leads', 'stage', array( 'deleted_at' => array( 'null' ) ) );
		$out    = array();
		foreach ( Entities::LEAD_STAGES as $stage ) {
			$rows           = TenantDb::select( 'leads', array( 'stage' => $stage, 'deleted_at' => array( 'null' ) ), array( 'order' => array( array( 'updated_at', 'desc' ), array( 'id', 'desc' ) ), 'limit' => $per_stage ) );
			$out[ $stage ]  = array( 'count' => $counts[ $stage ] ?? 0, 'items' => array_map( fn( array $r ): array => $this->present( $r ), $rows ) );
		}
		return array( 'stages' => $out, 'total' => array_sum( $counts ) );
	}

	/** Link the lead to a customer (existing match by phone/email, else a new one). @return array<string,mixed> */
	public function convert( string $uuid ): array {
		$res = TenantDb::transaction(
			function () use ( $uuid ): array {
				$lead = $this->row( $uuid );
				if ( ! empty( $lead['customer_uuid'] ) ) {
					return $this->present( $lead );
				}
				$customer = $this->customers->find_match( $lead['phone'] ?: null, $lead['email'] ?: null );
				if ( $customer ) {
					$cuuid = (string) $customer['uuid'];
				} else {
					$created = $this->customers->create(
						array(
							'name'    => $lead['name'] ?: ( $lead['phone'] ?: $lead['email'] ),
							'phone'   => $lead['phone'],
							'email'   => $lead['email'],
							'source'  => $lead['source'],
							'consent' => (bool) (int) $lead['consent'],
						)
					);
					$cuuid = (string) $created['id'];
				}
				$out = parent::update( $uuid, array( 'customer_uuid' => $cuuid ) );
				$this->activities->log( 'lead', $uuid, 'system', 'Linked to customer' );
				return $out;
			}
		);
		return $res;
	}

	/**
	 * Import one lead pulled from the marketplace. Idempotent: the marketplace lead uuid is the external id, so a
	 * re-delivery (ack lost) creates nothing. All remote values are untrusted and sanitised by the field engine.
	 *
	 * @param array<string,mixed> $remote Row from /sync/leads/pull.
	 * @return string 'imported' | 'duplicate' | 'invalid'
	 */
	public function import_marketplace( array $remote ): string {
		$external = $remote['uuid'] ?? null;
		if ( ! is_string( $external ) || 1 !== preg_match( '/^[0-9a-f\-]{36}$/', $external ) ) {
			return 'invalid';
		}
		if ( TenantDb::get( 'leads', array( 'external_id' => $external ) ) ) {
			return 'duplicate';
		}
		$vehicle_uuid = null;
		if ( ! empty( $remote['vehicle_source_id'] ) && is_string( $remote['vehicle_source_id'] ) && null !== TenantDb::get( 'vehicles', array( 'uuid' => $remote['vehicle_source_id'] ) ) ) {
			$vehicle_uuid = $remote['vehicle_source_id']; // Our vehicle uuid is the marketplace source_id; verified to belong to this tenant.
		}
		$service_uuid = null;
		if ( ! empty( $remote['service_source_id'] ) && is_string( $remote['service_source_id'] ) && null !== TenantDb::get( 'services', array( 'uuid' => $remote['service_source_id'] ) ) ) {
			$service_uuid = $remote['service_source_id'];
		}
		$input = array(
			'source'       => 'marketplace',
			'name'         => $remote['name'] ?? null,
			'phone'        => $remote['phone'] ?? null,
			'email'        => $remote['email'] ?? null,
			'message'      => $remote['message'] ?? null,
			'channel'      => $remote['channel'] ?? null,
			'locale'       => $remote['locale'] ?? null,
			'consent'      => ! empty( $remote['consent'] ),
			'vehicle_uuid' => $vehicle_uuid,
			'service_uuid' => $service_uuid,
		);
		try {
			TenantDb::transaction(
				function () use ( $input, $external ): void {
					$lead = parent::create( $input, array( 'external_id' => $external ) );
					$this->activities->log( 'lead', (string) $lead['id'], 'system', 'Lead received from the marketplace' );
				}
			);
		} catch ( ApiException $e ) {
			return 'invalid';
		}
		return 'imported';
	}

	public function mark_won_silently( string $uuid ): void {
		$lead = TenantDb::get( 'leads', array( 'uuid' => $uuid, 'deleted_at' => array( 'null' ) ) );
		if ( $lead && 'won' !== $lead['stage'] ) {
			TenantDb::update( 'leads', array( 'uuid' => $uuid ), array( 'stage' => 'won', 'updated_at' => Clock::mysql(), 'version' => array( '@incr' => 1 ) ) );
			$this->activities->log( 'lead', $uuid, 'stage_change', $lead['stage'] . ' -> won' );
		}
	}
}
