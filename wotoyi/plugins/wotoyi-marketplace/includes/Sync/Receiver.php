<?php
/**
 * Receiver for dealer content (vehicles and services) and lead hand-back. Transport independent:
 * HTTP (after HMAC authentication) and local calls both end here, so behaviour is identical.
 *
 * Event envelope (PROJECT.md §13.4):
 *   { event_id, event_type, occurred_at, dealer_uuid, api_version, data }
 * data = full snapshot of the entity + { source_id, version }.
 *
 * Guarantees:
 *  - Tenant isolation: the dealer comes from the authenticated key (or local registry), never from the payload;
 *    a payload naming another dealer is rejected (403) and logged.
 *  - Idempotency: (dealer, event_id) is claimed in sync_log; replays return the stored result; same id with a
 *    different payload is a 409 conflict.
 *  - Ordering: lower versions than the stored one are rejected as stale (409); equal versions are no-ops.
 *  - Snapshot semantics: published/updated replace all dealer-controlled fields (absent = cleared).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

use Wotoyi\Marketplace\Repository\AbstractListingRepository;
use Wotoyi\Marketplace\Repository\DealerRepository;
use Wotoyi\Marketplace\Repository\LeadRepository;
use Wotoyi\Marketplace\Repository\ServiceRepository;
use Wotoyi\Marketplace\Repository\SyncLogRepository;
use Wotoyi\Marketplace\Repository\VehicleRepository;
use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Schema\ServiceFields;
use Wotoyi\Marketplace\Schema\VehicleFields;
use Wotoyi\Marketplace\Support\Json;

defined( 'ABSPATH' ) || exit;

final class Receiver {

	public const API_VERSION = 'v1';
	public const MAX_BATCH   = 50;

	public const EVENT_TYPES = array(
		'vehicle.published', 'vehicle.updated', 'vehicle.unpublished', 'vehicle.sold', 'vehicle.deleted',
		'service.published', 'service.updated', 'service.unpublished', 'service.deleted',
	);

	public function __construct(
		private DealerRepository $dealers,
		private VehicleRepository $vehicles,
		private ServiceRepository $services,
		private SyncLogRepository $log,
		private LeadRepository $leads
	) {}

	/**
	 * Process one event for an already-authenticated dealer.
	 *
	 * @param array<string,mixed> $dealer     Dealer row resolved by the transport.
	 * @param array<string,mixed> $event      Decoded envelope (untrusted).
	 * @return array<string,mixed> {status, http_status, event_id, entity?, warnings, message?, duplicate?}
	 */
	public function handle_event( array $dealer, array $event, string $transport = 'http', string $request_id = '' ): array {
		$started  = microtime( true );
		$event_id = $event['event_id'] ?? null;
		if ( ! is_string( $event_id ) || 1 !== preg_match( '/^[A-Za-z0-9_.:\-]{8,64}$/', $event_id ) ) {
			return $this->fail( 'rejected', 422, null, 'invalid_event', 'event_id must be 8-64 characters of [A-Za-z0-9_.:-].' );
		}
		$type = $event['event_type'] ?? null;
		if ( ! is_string( $type ) || ! in_array( $type, self::EVENT_TYPES, true ) ) {
			return $this->fail( 'rejected', 422, $event_id, 'unsupported_event_type', 'Unsupported event_type.' );
		}
		if ( isset( $event['api_version'] ) && self::API_VERSION !== $event['api_version'] ) {
			return $this->fail( 'rejected', 422, $event_id, 'unsupported_api_version', 'Unsupported api_version.' );
		}

		$dealer_id = (int) $dealer['id'];
		$hash      = hash( 'sha256', Json::canonical( $event ) );
		$claim     = $this->log->claim( $dealer_id, $event_id, $hash, $type, $transport, substr( $request_id, 0, 64 ) );

		if ( 'conflict' === $claim['state'] ) {
			return $this->fail( 'rejected', 409, $event_id, 'event_id_conflict', 'event_id already used with a different payload.' );
		}
		if ( 'done' === $claim['state'] ) {
			$stored                = Json::decode_array( $claim['row']['result'] ?? null ) ?? array();
			$stored['duplicate']   = true;
			$stored['http_status'] = (int) ( $claim['row']['http_status'] ?? 200 );
			unset( $stored['entity']['internal_id'] );
			return $stored;
		}

		$log_id = (int) $claim['row']['id'];
		try {
			$result = $this->process( $dealer, $type, $event );
		} catch ( \Throwable $e ) {
			error_log( 'wotoyi-marketplace receiver error: ' . get_class( $e ) . ' ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$result = $this->fail( 'failed', 500, $event_id, 'internal_error', 'Internal error while applying the event; retry later.' );
		}
		$result['event_id'] = $event_id;

		$entity = $result['entity'] ?? array();
		$this->log->finish(
			$log_id,
			(string) $result['status'],
			(int) $result['http_status'],
			$result,
			isset( $entity['type'] ) ? (string) $entity['type'] : null,
			isset( $entity['source_id'] ) ? (string) $entity['source_id'] : null,
			isset( $entity['internal_id'] ) ? (int) $entity['internal_id'] : null,
			isset( $entity['version'] ) ? (int) $entity['version'] : null,
			(int) round( ( microtime( true ) - $started ) * 1000 )
		);
		unset( $result['entity']['internal_id'] );
		return $result;
	}

	/**
	 * Process up to MAX_BATCH events independently (initial full sync, reconciliation).
	 *
	 * @param list<mixed> $events
	 * @return array{results:list<array<string,mixed>>,summary:array<string,int>}
	 */
	public function handle_batch( array $dealer, array $events, string $transport = 'http', string $request_id = '' ): array {
		$results = array();
		$summary = array();
		foreach ( array_slice( array_values( $events ), 0, self::MAX_BATCH ) as $event ) {
			$res       = is_array( $event ) ? $this->handle_event( $dealer, $event, $transport, $request_id ) : $this->fail( 'rejected', 422, null, 'invalid_event', 'Event must be an object.' );
			$results[] = $res;
			$summary[ (string) $res['status'] ] = ( $summary[ (string) $res['status'] ] ?? 0 ) + 1;
		}
		if ( count( $events ) > self::MAX_BATCH ) {
			$summary['skipped_over_limit'] = count( $events ) - self::MAX_BATCH;
		}
		return array( 'results' => $results, 'summary' => $summary );
	}

	/** @return list<array<string,mixed>> Pending leads of this dealer only. */
	public function pull_leads( array $dealer, int $limit ): array {
		return $this->leads->pending_for_dealer( (int) $dealer['id'], $limit );
	}

	/** @param list<string> $uuids */
	public function ack_leads( array $dealer, array $uuids ): int {
		return $this->leads->acknowledge( (int) $dealer['id'], $uuids );
	}

	/* --------------------------------------------------------------- */

	/** @return array<string,mixed> */
	private function process( array $dealer, string $type, array $event ): array {
		$event_id = (string) $event['event_id'];
		[ $entity, $action ] = explode( '.', $type, 2 );
		$data = $event['data'] ?? null;
		if ( ! is_array( $data ) || array_is_list( $data ) ) {
			return $this->fail( 'rejected', 422, $event_id, 'invalid_event', 'data must be an object.' );
		}

		// Tenant check: any dealer identity in the payload must be the authenticated dealer.
		$allowed = array_filter( array( $dealer['uuid'], $dealer['source_dealer_uuid'] ?? null ) );
		foreach ( array( $event['dealer_uuid'] ?? null, $data['dealer_uuid'] ?? null, $data['dealer_id'] ?? null ) as $claimed ) {
			if ( null !== $claimed && ( ! is_scalar( $claimed ) || ! in_array( (string) $claimed, $allowed, true ) ) ) {
				return $this->fail( 'rejected', 403, $event_id, 'tenant_mismatch', 'Payload refers to a different dealer.' );
			}
		}

		$source_id = $data['source_id'] ?? null;
		if ( ! is_string( $source_id ) || 1 !== preg_match( '/^[A-Za-z0-9_.:\-]{1,64}$/', $source_id ) ) {
			return $this->fail( 'rejected', 422, $event_id, 'invalid_source_id', 'data.source_id is required (1-64 chars [A-Za-z0-9_.:-]).' );
		}
		$version = null;
		if ( array_key_exists( 'version', $data ) ) {
			if ( ! is_int( $data['version'] ) && ! ( is_string( $data['version'] ) && ctype_digit( $data['version'] ) ) ) {
				return $this->fail( 'rejected', 422, $event_id, 'invalid_version', 'data.version must be a positive integer.' );
			}
			$version = (int) $data['version'];
		}

		$is_vehicle = 'vehicle' === $entity;
		$repo       = $is_vehicle ? $this->vehicles : $this->services;
		$dealer_id  = (int) $dealer['id'];

		switch ( $action ) {
			case 'published':
			case 'updated':
				if ( null === $version || $version < 1 ) {
					return $this->fail( 'rejected', 422, $event_id, 'invalid_version', 'data.version (>= 1) is required for snapshots.' );
				}
				$sanitized = Fields::sanitize( $repo->defs(), $data, $is_vehicle ? VehicleFields::ENVELOPE : ServiceFields::ENVELOPE );
				$clean     = $sanitized['clean'];
				$warnings  = $sanitized['warnings'];

				$has_identity = $is_vehicle ? ( ! empty( $clean['title'] ) || ( ! empty( $clean['make'] ) && ! empty( $clean['model'] ) ) ) : ! empty( $clean['title'] );
				if ( ! $has_identity ) {
					return $this->fail( 'rejected', 422, $event_id, 'quality_rules', $is_vehicle ? 'A vehicle needs a title or make and model.' : 'A service needs a title.', $warnings );
				}

				$existing = $repo->find_by_source( $dealer_id, $source_id );
				$status   = $this->target_status( $action, $data['status'] ?? null, $existing, $is_vehicle );

				if ( 'published' === $status ) {
					$allowed_publish = apply_filters( 'wotoyi_mp_can_publish', true, $dealer, $entity, $clean ); // Plan limits / moderation hook.
					if ( true !== $allowed_publish ) {
						$msg = is_wp_error( $allowed_publish ) ? $allowed_publish->get_error_message() : 'Publishing is not allowed for this dealer.';
						return $this->fail( 'rejected', 403, $event_id, 'publish_not_allowed', $msg, $warnings );
					}
				}

				$res = $repo->upsert( $dealer_id, $source_id, $clean, $version, $status );
				return $this->map( $res, $entity, $source_id, $event_id, $warnings );

			case 'unpublished':
			case 'sold':
			case 'deleted':
				$res = 'deleted' === $action
					? $repo->tombstone( $dealer_id, $source_id, $version )
					: $repo->set_status( $dealer_id, $source_id, 'sold' === $action ? 'sold' : 'unpublished', $version );
				return $this->map( $res, $entity, $source_id, $event_id, array() );
		}
		return $this->fail( 'rejected', 422, $event_id, 'unsupported_event_type', 'Unsupported event_type.' );
	}

	/** Status a snapshot event results in. */
	private function target_status( string $action, mixed $requested, ?array $existing, bool $is_vehicle ): string {
		if ( 'published' === $action ) {
			return 'published';
		}
		$allowed = $is_vehicle ? array( 'published', 'unpublished', 'sold', 'draft' ) : array( 'published', 'unpublished', 'draft' );
		if ( is_string( $requested ) && in_array( $requested, $allowed, true ) ) {
			return $requested;
		}
		if ( $existing && 'deleted' !== $existing['status'] ) {
			return (string) $existing['status'];
		}
		return 'published';
	}

	/**
	 * @param array{outcome:string,id:int,uuid:string,version:int,status:string} $res
	 * @param list<string> $warnings
	 * @return array<string,mixed>
	 */
	private function map( array $res, string $entity, string $source_id, string $event_id, array $warnings ): array {
		$info = array(
			'type'        => $entity,
			'id'          => $res['uuid'] ?: null,
			'source_id'   => $source_id,
			'version'     => $res['version'],
			'status'      => $res['status'],
			'internal_id' => $res['id'],
		);
		switch ( $res['outcome'] ) {
			case 'created':
			case 'updated':
				return array( 'status' => 'applied', 'http_status' => 200, 'event_id' => $event_id, 'created' => 'created' === $res['outcome'], 'entity' => $info, 'warnings' => $warnings );
			case 'unchanged':
				return array( 'status' => 'unchanged', 'http_status' => 200, 'event_id' => $event_id, 'entity' => $info, 'warnings' => $warnings );
			case 'stale':
				return array( 'status' => 'stale', 'http_status' => 409, 'event_id' => $event_id, 'code' => 'stale_version', 'message' => 'A newer version is already stored.', 'entity' => $info, 'warnings' => $warnings );
			default: // not_found for unpublish/sold: nothing to do, not an error.
				return array( 'status' => 'unchanged', 'http_status' => 200, 'event_id' => $event_id, 'entity' => array( 'type' => $entity, 'id' => null, 'source_id' => $source_id ), 'warnings' => array( 'not_found' ) );
		}
	}

	/**
	 * @param list<string> $warnings
	 * @return array<string,mixed>
	 */
	private function fail( string $status, int $http, ?string $event_id, string $code, string $message, array $warnings = array() ): array {
		return array(
			'status'      => $status,
			'http_status' => $http,
			'event_id'    => $event_id,
			'code'        => $code,
			'message'     => $message,
			'warnings'    => $warnings,
		);
	}
}
