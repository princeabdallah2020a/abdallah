<?php
/**
 * Shared persistence for dealer-owned listings (vehicles, services): versioned upsert, status changes,
 * tombstones. Every method takes the dealer id explicitly: there is no way to touch another tenant's row.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Support\Cache;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Uuid;

defined( 'ABSPATH' ) || exit;

abstract class AbstractListingRepository {

	/** Logical table name (vehicles | services). */
	abstract protected function logical(): string;

	/** @return array<string,array> */
	abstract public function defs(): array;

	/**
	 * Derived columns (search text, counters) computed from sanitised values.
	 *
	 * @param array<string,mixed> $clean
	 * @return array<string,mixed>
	 */
	abstract protected function derive( array $clean ): array;

	/** @param array<string,mixed> $clean */
	abstract protected function slug_base( array $clean ): string;

	/** Hook for child tables (equipment). */
	protected function after_write( int $id, array $clean ): void {}

	public function table(): string {
		return Db::table( $this->logical() );
	}

	/** @return array<string,mixed>|null */
	public function find_by_source( int $dealer_id, string $source_id ): ?array {
		$sql = Db::prepare( "SELECT * FROM {$this->table()} WHERE dealer_id = %d AND source_id = %s LIMIT 1", array( $dealer_id, $source_id ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public function find_by_id( int $id ): ?array {
		$sql = Db::prepare( "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", array( $id ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	private function lock_row( int $dealer_id, string $source_id ): ?array {
		$sql = Db::prepare( "SELECT id, uuid, version, status, published_at FROM {$this->table()} WHERE dealer_id = %d AND source_id = %s LIMIT 1 FOR UPDATE", array( $dealer_id, $source_id ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/**
	 * Create or replace (snapshot) a listing, honouring version ordering.
	 *
	 * @param array<string,mixed> $clean Sanitised catalogue values (Fields::sanitize output).
	 * @return array{outcome:string,id:int,uuid:string,version:int,status:string}
	 *         outcome: created | updated | unchanged | stale
	 */
	public function upsert( int $dealer_id, string $source_id, array $clean, int $version, string $status ): array {
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			Db::begin();
			try {
				$existing = $this->lock_row( $dealer_id, $source_id );
				$now      = Clock::mysql();
				$data     = Fields::to_db( $this->defs(), $clean ) + $this->derive( $clean );

				if ( $existing ) {
					$current = (int) $existing['version'];
					if ( $version < $current ) {
						Db::rollback();
						return $this->outcome( 'stale', $existing, $current, (string) $existing['status'] );
					}
					if ( $version === $current && 'deleted' !== $existing['status'] ) {
						Db::commit();
						return $this->outcome( 'unchanged', $existing, $current, (string) $existing['status'] );
					}
					$data['version']    = $version;
					$data['status']     = $status;
					$data['updated_at'] = $now;
					$data['deleted_at'] = null;
					if ( 'published' === $status && ( 'published' !== $existing['status'] || empty( $existing['published_at'] ) ) ) {
						$data['published_at'] = $now;
					}
					if ( false === Db::wpdb()->update( $this->table(), $data, array( 'id' => (int) $existing['id'], 'dealer_id' => $dealer_id ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						throw new \RuntimeException( 'db_update_failed' );
					}
					$id   = (int) $existing['id'];
					$uuid = (string) $existing['uuid'];
					$out  = 'updated';
				} else {
					$uuid                 = Uuid::v7();
					$data['uuid']         = $uuid;
					$data['dealer_id']    = $dealer_id;
					$data['source_id']    = $source_id;
					$data['version']      = $version;
					$data['status']       = $status;
					$data['slug']         = $this->unique_slug( $this->slug_base( $clean ), $uuid );
					$data['created_at']   = $now;
					$data['updated_at']   = $now;
					$data['published_at'] = 'published' === $status ? $now : null;
					if ( false === Db::wpdb()->insert( $this->table(), $data ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						throw new \RuntimeException( 'db_insert_failed' ); // Possibly a concurrent insert: retry once as update.
					}
					$id  = (int) Db::wpdb()->insert_id;
					$out = 'created';
				}
				$this->after_write( $id, $clean );
				Db::commit();
				Cache::bump();
				return array(
					'outcome' => $out,
					'id'      => $id,
					'uuid'    => $uuid,
					'version' => $version,
					'status'  => $status,
				);
			} catch ( \Throwable $e ) {
				Db::rollback();
				if ( $attempt >= 1 ) {
					throw $e;
				}
			}
		}
		throw new \RuntimeException( 'upsert_failed' ); // Unreachable.
	}

	/**
	 * Change status only (unpublish, sold). No snapshot required.
	 *
	 * @return array{outcome:string,id:int,uuid:string,version:int,status:string}  outcome: updated|unchanged|stale|not_found
	 */
	public function set_status( int $dealer_id, string $source_id, string $status, ?int $version ): array {
		$row = $this->find_by_source( $dealer_id, $source_id );
		if ( ! $row ) {
			return array( 'outcome' => 'not_found', 'id' => 0, 'uuid' => '', 'version' => 0, 'status' => $status );
		}
		$current = (int) $row['version'];
		if ( null !== $version && $version < $current ) {
			return $this->outcome( 'stale', $row, $current, (string) $row['status'] );
		}
		if ( $row['status'] === $status && ( null === $version || $version <= $current ) ) {
			return $this->outcome( 'unchanged', $row, $current, $status );
		}
		$new_version = max( $current, (int) $version );
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'     => $status,
				'version'    => $new_version,
				'updated_at' => Clock::mysql(),
			),
			array( 'id' => (int) $row['id'], 'dealer_id' => $dealer_id )
		);
		Cache::bump();
		return $this->outcome( 'updated', $row, $new_version, $status );
	}

	/**
	 * Delete = tombstone: the row stays (hidden) so older out-of-order events cannot resurrect it.
	 *
	 * @return array{outcome:string,id:int,uuid:string,version:int,status:string}
	 */
	public function tombstone( int $dealer_id, string $source_id, ?int $version ): array {
		$row = $this->find_by_source( $dealer_id, $source_id );
		$now = Clock::mysql();
		if ( ! $row ) {
			$uuid = Uuid::v7();
			Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				array(
					'uuid'       => $uuid,
					'dealer_id'  => $dealer_id,
					'source_id'  => $source_id,
					'version'    => (int) $version,
					'status'     => 'deleted',
					'slug'       => 'deleted-' . str_replace( '-', '', $uuid ),
					'created_at' => $now,
					'updated_at' => $now,
					'deleted_at' => $now,
				)
			);
			Cache::bump();
			return array( 'outcome' => 'created', 'id' => (int) Db::wpdb()->insert_id, 'uuid' => $uuid, 'version' => (int) $version, 'status' => 'deleted' );
		}
		$current = (int) $row['version'];
		if ( null !== $version && $version < $current ) {
			return $this->outcome( 'stale', $row, $current, (string) $row['status'] );
		}
		if ( 'deleted' === $row['status'] ) {
			return $this->outcome( 'unchanged', $row, $current, 'deleted' );
		}
		$new_version = max( $current, (int) $version );
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'     => 'deleted',
				'version'    => $new_version,
				'featured'   => 0,
				'updated_at' => $now,
				'deleted_at' => $now,
			),
			array( 'id' => (int) $row['id'], 'dealer_id' => $dealer_id )
		);
		Cache::bump();
		return $this->outcome( 'updated', $row, $new_version, 'deleted' );
	}

	/**
	 * Reconciliation manifest of ONE dealer's listings (all statuses, including tombstones), keyset-paginated.
	 *
	 * @return array{items:list<array{source_id:string,version:int,status:string}>,next:?int}
	 */
	public function manifest( int $dealer_id, int $after_id, int $limit ): array {
		$limit = max( 1, min( 500, $limit ) );
		$sql   = Db::prepare( "SELECT id, source_id, version, status FROM {$this->table()} WHERE dealer_id = %d AND id > %d ORDER BY id ASC LIMIT %d", array( $dealer_id, max( 0, $after_id ), $limit + 1 ) );
		$rows  = (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$next  = null;
		if ( count( $rows ) > $limit ) {
			array_pop( $rows );
			$next = (int) $rows[ count( $rows ) - 1 ]['id'];
		}
		return array(
			'items' => array_map( static fn( array $r ): array => array( 'source_id' => (string) $r['source_id'], 'version' => (int) $r['version'], 'status' => (string) $r['status'] ), $rows ),
			'next'  => $next,
		);
	}

	public function increment_views( int $id ): void {
		Db::wpdb()->query( Db::prepare( "UPDATE {$this->table()} SET views = views + 1 WHERE id = %d AND status = %s", array( $id, 'published' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/** Hide or restore every listing of a dealer without losing data (used on suspend/disconnect via dealer status join). */
	public function count_by_dealer( int $dealer_id ): int {
		$sql = Db::prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE dealer_id = %d AND status <> %s", array( $dealer_id, 'deleted' ) );
		return (int) Db::wpdb()->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	private function unique_slug( string $base, string $uuid ): string {
		$base = '' === $base ? $this->logical() : $base;
		return substr( $base, 0, 170 ) . '-' . substr( str_replace( '-', '', $uuid ), -8 );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array{outcome:string,id:int,uuid:string,version:int,status:string}
	 */
	private function outcome( string $outcome, array $row, int $version, string $status ): array {
		return array(
			'outcome' => $outcome,
			'id'      => (int) $row['id'],
			'uuid'    => (string) $row['uuid'],
			'version' => $version,
			'status'  => $status,
		);
	}
}
