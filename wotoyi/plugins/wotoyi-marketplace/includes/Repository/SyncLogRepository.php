<?php
/**
 * sync_log: one row per (dealer, event_id). Doubles as the idempotency ledger.
 * Never stores secrets, signatures or the raw payload.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Json;

defined( 'ABSPATH' ) || exit;

final class SyncLogRepository {

	public function table(): string {
		return Db::table( 'sync_log' );
	}

	/**
	 * Claim an event id for processing.
	 *
	 * @return array{state:string,row:array<string,mixed>}
	 *         state: new (caller must process) | done (final result stored) | conflict (same id, other payload)
	 */
	public function claim( int $dealer_id, string $event_id, string $payload_hash, string $event_type, string $transport, string $request_id ): array {
		$row = $this->find( $dealer_id, $event_id );
		if ( ! $row ) {
			$ok = Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				array(
					'dealer_id'    => $dealer_id,
					'event_id'     => $event_id,
					'payload_hash' => $payload_hash,
					'event_type'   => $event_type,
					'transport'    => $transport,
					'status'       => 'received',
					'request_id'   => $request_id,
					'received_at'  => Clock::mysql(),
				)
			);
			if ( false !== $ok ) {
				return array( 'state' => 'new', 'row' => $this->find( $dealer_id, $event_id ) ?? array() );
			}
			$row = $this->find( $dealer_id, $event_id ); // Lost a race with a concurrent identical request.
			if ( ! $row ) {
				throw new \RuntimeException( 'sync_log_insert_failed' );
			}
		}
		if ( ! hash_equals( (string) $row['payload_hash'], $payload_hash ) ) {
			return array( 'state' => 'conflict', 'row' => $row );
		}
		if ( in_array( $row['status'], array( 'applied', 'unchanged', 'stale', 'rejected' ), true ) ) {
			return array( 'state' => 'done', 'row' => $row );
		}
		return array( 'state' => 'new', 'row' => $row ); // received/failed: safe to (re)process.
	}

	/** @param array<string,mixed> $result */
	public function finish( int $log_id, string $status, int $http_status, array $result, ?string $entity_type, ?string $source_id, ?int $entity_id, ?int $version, int $duration_ms ): void {
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'           => $status,
				'http_status'      => $http_status,
				'result'           => Json::canonical( $result ),
				'message'          => mb_substr( (string) ( $result['message'] ?? '' ), 0, 255 ) ?: null,
				'entity_type'      => $entity_type,
				'entity_source_id' => $source_id,
				'entity_id'        => $entity_id,
				'version'          => $version,
				'processed_at'     => Clock::mysql(),
				'duration_ms'      => $duration_ms,
			),
			array( 'id' => $log_id )
		);
	}

	/** @return array<string,mixed>|null */
	public function find( int $dealer_id, string $event_id ): ?array {
		$sql = Db::prepare( "SELECT * FROM {$this->table()} WHERE dealer_id = %d AND event_id = %s LIMIT 1", array( $dealer_id, $event_id ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** @return list<array<string,mixed>> */
	public function recent( int $dealer_id, int $limit = 50, int $before_id = 0 ): array {
		$limit = max( 1, min( 200, $limit ) );
		if ( $before_id > 0 ) {
			$sql = Db::prepare( "SELECT id, event_id, event_type, entity_type, entity_source_id, version, transport, status, http_status, message, received_at, duration_ms FROM {$this->table()} WHERE dealer_id = %d AND id < %d ORDER BY id DESC LIMIT %d", array( $dealer_id, $before_id, $limit ) );
		} else {
			$sql = Db::prepare( "SELECT id, event_id, event_type, entity_type, entity_source_id, version, transport, status, http_status, message, received_at, duration_ms FROM {$this->table()} WHERE dealer_id = %d ORDER BY id DESC LIMIT %d", array( $dealer_id, $limit ) );
		}
		return (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	public function prune( int $days ): void {
		$cutoff = Clock::mysql( Clock::now() - max( 1, $days ) * DAY_IN_SECONDS );
		Db::wpdb()->query( Db::prepare( "DELETE FROM {$this->table()} WHERE received_at < %s", array( $cutoff ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
