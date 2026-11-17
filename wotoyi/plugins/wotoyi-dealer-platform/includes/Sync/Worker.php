<?php
/**
 * Outbox worker (Action-Scheduler style, on WP-Cron): claims due events per tenant, signs and pushes them in
 * batches, and applies per-event results. Safe to run concurrently and to crash at any point:
 *   - an event is claimed with an atomic UPDATE (lock token + lock expiry); a dead worker's claim expires;
 *   - delivery is at-least-once, de-duplicated by the marketplace on event_id, ordered by entity version;
 *   - a failed batch only reschedules its own events (exponential backoff + jitter), never blocks other tenants.
 * Entry points: kick() after a commit, tick() from cron, run_now() from the "sync now" button.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Settings;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class Worker {

	public const HOOK_TICK      = 'wotoyi_dp_sync_tick';
	public const HOOK_RECONCILE = 'wotoyi_dp_reconcile';

	/**
	 * After a commit: push immediately in local (demo) mode, otherwise ask cron to run soon.
	 */
	public static function kick(): void {
		if ( ! TenantContext::has() ) {
			return;
		}
		$state = SyncState::get();
		if ( 'paired' !== $state['status'] ) {
			return;
		}
		if ( 'local' === $state['transport'] && Settings::get( 'inline_local_push' ) ) {
			self::run_for_current( 3 );
			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && false === wp_next_scheduled( self::HOOK_TICK ) ) {
			wp_schedule_single_event( Clock::now() + 5, self::HOOK_TICK );
		}
	}

	/**
	 * Cron entry: every tenant with due work, then lead pulls. Tenants are processed one by one, each inside its
	 * own context; an exception in one tenant is logged and does not affect the others.
	 *
	 * @return array<int,array<string,mixed>> per tenant id
	 */
	public static function tick(): array {
		$out = array();
		foreach ( Directory::tenant_ids_with_due_outbox() as $tenant_id ) {
			$tenant = Directory::find_by_id( $tenant_id );
			if ( ! $tenant || 'active' !== $tenant['status'] ) {
				continue;
			}
			try {
				$out[ $tenant_id ] = TenantContext::run( $tenant, static fn(): array => self::run_for_current( 5 ) );
			} catch ( \Throwable $e ) {
				error_log( 'wotoyi-dealer-platform worker (tenant ' . $tenant_id . '): ' . get_class( $e ) . ' ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$out[ $tenant_id ] = array( 'status' => 'error' );
			}
		}
		foreach ( Directory::paired_tenant_ids() as $tenant_id ) {
			$tenant = Directory::find_by_id( $tenant_id );
			if ( ! $tenant || 'active' !== $tenant['status'] ) {
				continue;
			}
			try {
				TenantContext::run(
					$tenant,
					static function () use ( $tenant_id, &$out ): void {
						$last = SyncState::get()['last_pull_at'];
						if ( null === $last || Clock::now() - (int) strtotime( $last . ' UTC' ) >= Settings::int( 'lead_pull_interval' ) ) {
							$out[ $tenant_id ]['leads'] = LeadPuller::run_for_current();
						}
					}
				);
			} catch ( \Throwable $e ) {
				error_log( 'wotoyi-dealer-platform lead pull (tenant ' . $tenant_id . '): ' . get_class( $e ) . ' ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		return $out;
	}

	/** Nightly cron: reconcile every paired tenant. @return array<int,array<string,mixed>> */
	public static function reconcile_all(): array {
		$out = array();
		foreach ( Directory::paired_tenant_ids() as $tenant_id ) {
			$tenant = Directory::find_by_id( $tenant_id );
			if ( ! $tenant || 'active' !== $tenant['status'] ) {
				continue;
			}
			try {
				$out[ $tenant_id ] = TenantContext::run( $tenant, static fn(): array => Reconciler::run_for_current() );
			} catch ( \Throwable $e ) {
				error_log( 'wotoyi-dealer-platform reconcile (tenant ' . $tenant_id . '): ' . get_class( $e ) . ' ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$out[ $tenant_id ] = array( 'status' => 'error' );
			}
		}
		return $out;
	}

	/** Manual trigger for the current tenant: push, pull leads. @return array<string,mixed> */
	public static function run_now(): array {
		return array( 'push' => self::run_for_current( 10 ), 'leads' => LeadPuller::run_for_current() );
	}

	/**
	 * Push due events of the CURRENT tenant.
	 *
	 * @return array<string,mixed> {status, sent, retried, rejected, stale}
	 */
	public static function run_for_current( int $max_batches = 5 ): array {
		$state = SyncState::get();
		$res   = array( 'status' => 'ok', 'sent' => 0, 'retried' => 0, 'rejected' => 0, 'stale' => 0 );
		if ( 'paired' !== $state['status'] ) {
			$res['status'] = 'not_paired';
			return $res;
		}
		try {
			$transport = TransportFactory::make( $state );
		} catch ( SyncException $e ) {
			SyncState::save( array( 'status' => 'auth_failed', 'last_error' => $e->error_code() . ': ' . $e->getMessage() ) );
			$res['status'] = 'auth_failed';
			return $res;
		}

		for ( $i = 0; $i < $max_batches; $i++ ) {
			$claimed = self::claim_within_limits();
			if ( ! $claimed ) {
				break;
			}
			$events = array_map( static fn( array $r ): array => (array) json_decode( (string) $r['payload'], true ), $claimed );
			try {
				$results = $transport->send_batch( $events );
			} catch ( SyncException $e ) {
				self::fail_batch( $claimed, $e );
				$res['retried'] += $e->retryable() ? count( $claimed ) : 0;
				$res['rejected'] += $e->retryable() ? 0 : count( $claimed );
				if ( $e->is_auth() ) {
					SyncState::save( array( 'status' => 'auth_failed', 'last_error' => $e->error_code() . ': ' . $e->getMessage() ) );
					$res['status'] = 'auth_failed';
				} else {
					SyncState::save( array( 'last_error' => $e->error_code() . ': ' . $e->getMessage() ) );
					$res['status'] = 'error';
				}
				break;
			}
			self::apply( $claimed, $results, $res );
		}
		return $res;
	}

	/** Claim up to batch_size events whose payloads fit in batch_max_bytes; give the rest back. @return list<array<string,mixed>> */
	private static function claim_within_limits(): array {
		$claimed = Outbox::claim( min( 50, max( 1, Settings::int( 'batch_size' ) ) ), Settings::int( 'lock_ttl' ) );
		$bytes   = 0;
		$keep    = array();
		$release = array();
		foreach ( $claimed as $row ) {
			$bytes += strlen( (string) $row['payload'] );
			if ( $keep && $bytes > Settings::int( 'batch_max_bytes' ) ) {
				$release[] = (int) $row['id'];
			} else {
				$keep[] = $row;
			}
		}
		if ( $release ) {
			TenantDb::update( 'outbox', array( 'id' => array( 'in', $release ), 'status' => 'sending' ), array( 'status' => 'pending', 'lock_token' => null, 'locked_until' => null ) );
		}
		return $keep;
	}

	/**
	 * @param list<array<string,mixed>>        $claimed
	 * @param array<string,array<string,mixed>> $results keyed by event_id
	 * @param array<string,mixed>              $res
	 */
	private static function apply( array $claimed, array $results, array &$res ): void {
		$max   = Settings::int( 'max_attempts' );
		$ok    = false;
		$error = null;
		foreach ( $claimed as $row ) {
			$r      = $results[ $row['event_id'] ] ?? null;
			$id     = (int) $row['id'];
			$http   = (int) ( $r['http_status'] ?? 0 );
			$status = (string) ( $r['status'] ?? '' );
			if ( null === $r ) {
				Outbox::mark_retry( $row, 0, 'no_result: the marketplace returned no result for this event', $max );
				++$res['retried'];
			} elseif ( in_array( $status, array( 'applied', 'unchanged' ), true ) ) {
				Outbox::mark_sent( $id, $status, $http ?: 200 );
				++$res['sent'];
				$ok = true;
			} elseif ( 'stale' === $status ) {
				Outbox::mark_sent( $id, 'stale', $http ?: 409 ); // A newer version is already there: this event is obsolete, not failed.
				++$res['stale'];
				$ok = true;
			} elseif ( 'rejected' === $status ) {
				$error = (string) ( $r['code'] ?? 'rejected' ) . ': ' . (string) ( $r['message'] ?? '' );
				Outbox::mark_rejected( $id, $http, $error );
				++$res['rejected'];
			} else {
				$error = (string) ( $r['code'] ?? 'failed' ) . ': ' . (string) ( $r['message'] ?? '' );
				Outbox::mark_retry( $row, $http, $error, $max );
				++$res['retried'];
			}
		}
		$state = array( 'last_error' => $error );
		if ( $ok ) {
			$state['last_push_at'] = Clock::mysql();
		}
		SyncState::save( $state );
	}

	/** @param list<array<string,mixed>> $claimed */
	private static function fail_batch( array $claimed, SyncException $e ): void {
		$max = Settings::int( 'max_attempts' );
		foreach ( $claimed as $row ) {
			if ( $e->retryable() ) {
				Outbox::mark_retry( $row, $e->http_status(), $e->error_code() . ': ' . $e->getMessage(), $max );
			} elseif ( $e->is_auth() ) {
				Outbox::mark_retry( $row, $e->http_status(), $e->error_code() . ': ' . $e->getMessage(), $max, 900 );
			} else {
				Outbox::mark_rejected( (int) $row['id'], $e->http_status(), $e->error_code() . ': ' . $e->getMessage() );
			}
		}
	}
}
