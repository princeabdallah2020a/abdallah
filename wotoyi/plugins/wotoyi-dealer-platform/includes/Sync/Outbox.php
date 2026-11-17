<?php
/**
 * Transactional outbox: sync events are written in the SAME DB transaction as the change that caused them, so a
 * vehicle change and its event either both exist or neither does. Delivery is the Worker's job.
 *
 * Event ids are deterministic (tenant + entity + version + type): re-enqueueing the same change is a no-op, and a
 * retry of a failed push re-sends the identical event_id (the marketplace de-duplicates on it).
 * Statuses: pending -> sending -> sent | pending (retry with backoff) | rejected (permanent, needs a fix) | dead
 * (too many attempts) | superseded (replaced by a newer snapshot before it was sent).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class Outbox {

	public const SNAPSHOT_TYPES = array( 'vehicle.published', 'vehicle.updated', 'service.published', 'service.updated' );

	private static bool $dirty = false;

	public static function event_id( string $entity_type, string $entity_uuid, int $version, string $event_type, string $salt = '' ): string {
		return ( '' === $salt ? 'dp-' : 'rc-' ) . substr( hash( 'sha256', TenantContext::uuid() . '|' . $entity_type . '|' . $entity_uuid . '|' . $version . '|' . $event_type . '|' . $salt ), 0, 40 );
	}

	/**
	 * Queue one event. Must be called inside the entity transaction.
	 *
	 * @param array<string,mixed> $data   Event data (source_id, version, ... snapshot).
	 * @return bool False when this exact event already exists.
	 */
	public static function enqueue( string $entity_type, string $entity_uuid, string $event_type, int $version, array $data, string $salt = '' ): bool {
		$event_id = self::event_id( $entity_type, $entity_uuid, $version, $event_type, $salt );
		if ( TenantDb::get( 'outbox', array( 'event_id' => $event_id ) ) ) {
			return false;
		}
		// A newer change replaces older events that were never delivered: the latest state is what matters.
		TenantDb::update(
			'outbox',
			array( 'entity_uuid' => $entity_uuid, 'status' => 'pending', 'entity_version' => array( '<', $version ) ),
			array( 'status' => 'superseded', 'last_error' => 'superseded by version ' . $version )
		);
		$envelope = array(
			'event_id'    => $event_id,
			'event_type'  => $event_type,
			'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z', Clock::now() ),
			'dealer_uuid' => TenantContext::uuid(),
			'api_version' => 'v1',
			'data'        => $data,
		);
		TenantDb::insert(
			'outbox',
			array(
				'event_id'        => $event_id,
				'entity_type'     => $entity_type,
				'entity_uuid'     => $entity_uuid,
				'event_type'      => $event_type,
				'entity_version'  => $version,
				'payload'         => (string) wp_json_encode( $envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'status'          => 'pending',
				'attempts'        => 0,
				'next_attempt_at' => Clock::mysql(),
				'created_at'      => Clock::mysql(),
			)
		);
		self::$dirty = true;
		return true;
	}

	/** Called after the outermost commit of a service call: nudge the worker (cron single event, or inline in local mode). */
	public static function after_commit(): void {
		if ( ! self::$dirty || TenantDb::in_transaction() ) {
			return;
		}
		self::$dirty = false;
		try {
			Worker::kick();
		} catch ( \Throwable $e ) {
			error_log( 'wotoyi-dealer-platform kick failed: ' . get_class( $e ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/** Test helper. */
	public static function reset(): void {
		self::$dirty = false;
	}

	/**
	 * Atomically claim up to $limit due events (oldest first). Returns the rows claimed by THIS call only.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function claim( int $limit, int $lock_ttl ): array {
		$now = Clock::mysql();
		// Release claims of workers that died.
		TenantDb::update( 'outbox', array( 'status' => 'sending', 'locked_until' => array( '<', $now ) ), array( 'status' => 'pending', 'lock_token' => null, 'locked_until' => null ) );
		$due = TenantDb::select( 'outbox', array( 'status' => 'pending', 'next_attempt_at' => array( '<=', $now ) ), array( 'columns' => array( 'id' ), 'order' => array( array( 'id', 'asc' ) ), 'limit' => $limit ) );
		if ( ! $due ) {
			return array();
		}
		$token = bin2hex( random_bytes( 16 ) );
		TenantDb::update(
			'outbox',
			array( 'id' => array( 'in', array_map( static fn( array $r ): int => (int) $r['id'], $due ) ), 'status' => 'pending' ),
			array( 'status' => 'sending', 'lock_token' => $token, 'locked_until' => Clock::mysql( Clock::now() + $lock_ttl ) )
		);
		return TenantDb::select( 'outbox', array( 'lock_token' => $token, 'status' => 'sending' ), array( 'order' => array( array( 'id', 'asc' ) ) ) );
	}

	/** @param array<string,mixed> $result */
	public static function mark_sent( int $id, string $result_status, int $http_status ): void {
		TenantDb::update( 'outbox', array( 'id' => $id ), array( 'status' => 'sent', 'result_status' => substr( $result_status, 0, 16 ), 'last_http_status' => $http_status, 'last_error' => null, 'sent_at' => Clock::mysql(), 'lock_token' => null, 'locked_until' => null ) );
	}

	public static function mark_rejected( int $id, int $http_status, string $error ): void {
		TenantDb::update( 'outbox', array( 'id' => $id ), array( 'status' => 'rejected', 'result_status' => 'rejected', 'last_http_status' => $http_status, 'last_error' => mb_substr( $error, 0, 255 ), 'attempts' => array( '@incr' => 1 ), 'lock_token' => null, 'locked_until' => null ) );
	}

	/** Schedule a retry with backoff, or give up (`dead`) after max attempts. */
	public static function mark_retry( array $row, int $http_status, string $error, int $max_attempts, ?int $delay = null ): void {
		$attempts = (int) $row['attempts'] + 1;
		$dead     = $attempts >= $max_attempts;
		TenantDb::update(
			'outbox',
			array( 'id' => (int) $row['id'] ),
			array(
				'status'           => $dead ? 'dead' : 'pending',
				'attempts'         => $attempts,
				'next_attempt_at'  => Clock::mysql( Clock::now() + ( $delay ?? self::backoff( $attempts ) ) ),
				'last_http_status' => $http_status ?: null,
				'last_error'       => mb_substr( $error, 0, 255 ),
				'lock_token'       => null,
				'locked_until'     => null,
			)
		);
	}

	/** Exponential backoff with jitter: 30 s, 1 min, 2 min, 4 min ... capped at 1 h. */
	public static function backoff( int $attempts ): int {
		$base = (int) min( 3600, 30 * ( 2 ** max( 0, $attempts - 1 ) ) );
		return $base + random_int( 0, (int) ( $base * 0.2 ) );
	}

	/** Put rejected/dead events back in the queue (admin action after fixing the cause). @return int rows */
	public static function requeue( ?array $statuses = null ): int {
		return TenantDb::update(
			'outbox',
			array( 'status' => array( 'in', $statuses ?? array( 'rejected', 'dead' ) ) ),
			array( 'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => Clock::mysql(), 'last_error' => null, 'lock_token' => null, 'locked_until' => null )
		);
	}

	/** @return array<string,int> counts by status */
	public static function stats(): array {
		return TenantDb::group_count( 'outbox', 'status' );
	}

	/**
	 * @param array<string,mixed> $q status, page, per_page
	 * @return array<string,mixed>
	 */
	public static function list( array $q ): array {
		$where = array();
		if ( isset( $q['status'] ) && is_string( $q['status'] ) && '' !== $q['status'] ) {
			$where['status'] = $q['status'];
		}
		$per   = max( 1, min( 100, (int) ( $q['per_page'] ?? 50 ) ) );
		$page  = max( 1, (int) ( $q['page'] ?? 1 ) );
		$rows  = TenantDb::select( 'outbox', $where, array( 'columns' => array( 'event_id', 'entity_type', 'entity_uuid', 'event_type', 'entity_version', 'status', 'attempts', 'next_attempt_at', 'result_status', 'last_http_status', 'last_error', 'created_at', 'sent_at' ), 'order' => array( array( 'id', 'desc' ) ), 'limit' => $per, 'offset' => ( $page - 1 ) * $per ) );
		return array( 'items' => $rows, 'total' => TenantDb::count( 'outbox', $where ), 'page' => $page, 'per_page' => $per );
	}

	/** Delete delivered events older than the retention window. */
	public static function prune( int $days ): int {
		return TenantDb::delete( 'outbox', array( 'status' => array( 'in', array( 'sent', 'superseded' ) ), 'created_at' => array( '<', Clock::mysql( Clock::now() - max( 1, $days ) * DAY_IN_SECONDS ) ) ) );
	}

	public static function token(): string {
		return Crypto::token( 8 );
	}
}
