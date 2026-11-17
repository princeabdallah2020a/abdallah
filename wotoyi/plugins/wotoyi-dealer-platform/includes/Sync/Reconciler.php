<?php
/**
 * Nightly reconciliation (closes the open issue of the marketplace README, known limit 6): compares what the
 * marketplace holds for this dealer (/sync/manifest: source_id, version, status, tombstones included) with the
 * local master and re-queues whatever differs.
 *
 *   remote missing, local live            -> push snapshot at the local version
 *   remote older (version <)              -> push snapshot at the local version
 *   remote same/newer version, status or version differs (rollback of our DB, marketplace-side change)
 *                                         -> bump the local version to remote+1 (the dealer platform is the source of
 *                                            truth), then push; audited
 *   local deleted, remote not deleted     -> push tombstone
 *   remote has items we do not know       -> REPORTED only (never auto-deleted: a restored backup must not wipe listings)
 * Limits: the manifest has no content hash, so a content difference at identical version+status is not detected.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Service\AuditLog;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class Reconciler {

	private const PAGE      = 200;
	private const MAX_PAGES = 250; // 50,000 items per type.

	/** @return array<string,mixed> summary */
	public static function run_for_current(): array {
		$state = SyncState::get();
		if ( 'paired' !== $state['status'] ) {
			return array( 'status' => 'not_paired' );
		}
		$summary = array( 'status' => 'ok', 'run_id' => Uuid::v7(), 'types' => array() );
		try {
			$transport = TransportFactory::make( $state );
			foreach ( array( 'vehicle' => 'vehicles', 'service' => 'services' ) as $type => $table ) {
				$summary['types'][ $type ] = self::reconcile_type( $transport, $type, $table, (string) $summary['run_id'] );
			}
		} catch ( SyncException $e ) {
			$summary['status'] = $e->is_auth() ? 'auth_failed' : 'error';
			$summary['error']  = $e->error_code();
			SyncState::save( array( 'last_error' => 'reconcile: ' . $e->error_code() . ': ' . $e->getMessage() ) + ( $e->is_auth() ? array( 'status' => 'auth_failed' ) : array() ) );
			return $summary;
		}
		SyncState::save( array( 'last_reconcile_at' => Clock::mysql(), 'last_reconcile_summary' => (string) wp_json_encode( $summary ) ) );
		AuditLog::record( 'sync.reconcile', 'sync', null, array( 'types' => $summary['types'] ) );
		Outbox::after_commit();
		return $summary;
	}

	/** @return array<string,int> */
	private static function reconcile_type( Transport $transport, string $type, string $table, string $run_id ): array {
		$remote = self::fetch_manifest( $transport, $type );
		$count  = array( 'remote' => count( $remote ), 'checked' => 0, 'ok' => 0, 'missing_remote' => 0, 'outdated' => 0, 'version_bumped' => 0, 'tombstones_pushed' => 0, 'orphans' => 0 );
		$after  = 0;
		while ( true ) {
			$rows = TenantDb::select( $table, array( 'id' => array( '>', $after ), 'published_at' => array( 'notnull' ) ), array( 'order' => array( array( 'id', 'asc' ) ), 'limit' => self::PAGE ) );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$after = (int) $row['id'];
				++$count['checked'];
				$uuid = (string) $row['uuid'];
				$r    = $remote[ $uuid ] ?? null;
				unset( $remote[ $uuid ] );
				self::reconcile_row( $type, $table, $row, $r, $run_id, $count );
			}
		}
		foreach ( $remote as $r ) {
			if ( 'deleted' !== $r['status'] ) {
				++$count['orphans'];
			}
		}
		return $count;
	}

	/**
	 * @param array<string,mixed>      $row
	 * @param array<string,mixed>|null $r
	 * @param array<string,int>        $count
	 */
	private static function reconcile_row( string $type, string $table, array $row, ?array $r, string $run_id, array &$count ): void {
		$status  = (string) $row['status'];
		$version = (int) $row['version'];
		if ( null === $r ) {
			if ( 'deleted' === $status ) {
				++$count['ok']; // Never reached the marketplace and is gone: nothing to do.
				return;
			}
			++$count['missing_remote'];
			self::push( $type, $row, $run_id );
			return;
		}
		if ( $r['status'] === $status && ( $r['version'] === $version || 'deleted' === $status ) ) {
			++$count['ok'];
			return;
		}
		if ( $r['version'] >= $version ) {
			// Remote is not behind but differs: we are authoritative, move past the remote version.
			$new = max( $version, (int) $r['version'] ) + 1;
			TenantDb::update( $table, array( 'uuid' => $row['uuid'], 'version' => $version ), array( 'version' => $new, 'updated_at' => Clock::mysql() ) );
			AuditLog::record( 'sync.version_bumped', $table, (string) $row['uuid'], array( 'from' => $version, 'to' => $new, 'remote_version' => $r['version'], 'remote_status' => $r['status'] ) );
			$row['version'] = $new;
			++$count['version_bumped'];
		} else {
			++$count['outdated'];
		}
		if ( 'deleted' === $status ) {
			++$count['tombstones_pushed'];
		}
		self::push( $type, $row, $run_id );
	}

	/** @param array<string,mixed> $row */
	private static function push( string $type, array $row, string $run_id ): void {
		$uuid    = (string) $row['uuid'];
		$version = (int) $row['version'];
		if ( 'deleted' === $row['status'] ) {
			Outbox::enqueue( $type, $uuid, $type . '.deleted', $version, Payload::lifecycle( $row ), 'rc:' . $run_id );
			return;
		}
		Outbox::enqueue( $type, $uuid, $type . '.updated', $version, Payload::snapshot( $type, $row ), 'rc:' . $run_id );
	}

	/** @return array<string,array{version:int,status:string}> source_id => state */
	private static function fetch_manifest( Transport $transport, string $type ): array {
		$out    = array();
		$cursor = 0;
		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$res = $transport->manifest( $type, $cursor, self::PAGE );
			foreach ( $res['items'] as $item ) {
				if ( isset( $item['source_id'] ) ) {
					$out[ (string) $item['source_id'] ] = array( 'version' => (int) ( $item['version'] ?? 0 ), 'status' => (string) ( $item['status'] ?? '' ) );
				}
			}
			if ( empty( $res['next'] ) ) {
				break;
			}
			$cursor = (int) $res['next'];
		}
		return $out;
	}
}
