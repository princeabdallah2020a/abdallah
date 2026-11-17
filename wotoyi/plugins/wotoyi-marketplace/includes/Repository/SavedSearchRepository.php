<?php
/**
 * Saved searches (normalised filter sets), scoped to the logged-in user.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Json;

defined( 'ABSPATH' ) || exit;

final class SavedSearchRepository {

	public const MAX_PER_USER = 20;

	public function table(): string {
		return Db::table( 'saved_searches' );
	}

	/**
	 * @param array<string,mixed> $filters Normalised filters (Filters::parse output).
	 * @return int|string Insert id, or error code: limit_reached | duplicate | empty_filters
	 */
	public function create( int $user_id, string $name, array $filters, bool $notify ): int|string {
		unset( $filters['page'], $filters['per_page'] );
		if ( ! $filters ) {
			return 'empty_filters';
		}
		if ( $this->count( $user_id ) >= self::MAX_PER_USER ) {
			return 'limit_reached';
		}
		$json = Json::canonical( $filters );
		$ok   = Db::wpdb()->suppress_errors( true );
		$res  = Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'user_id'      => $user_id,
				'name'         => $name,
				'filters'      => $json,
				'filters_hash' => hash( 'sha256', $json ),
				'notify'       => $notify ? 1 : 0,
				'created_at'   => Clock::mysql(),
			)
		);
		Db::wpdb()->suppress_errors( $ok );
		return false === $res ? 'duplicate' : (int) Db::wpdb()->insert_id;
	}

	public function count( int $user_id ): int {
		return (int) Db::wpdb()->get_var( Db::prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE user_id = %d", array( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/** @return list<array<string,mixed>> */
	public function list_for_user( int $user_id ): array {
		$rows = (array) Db::wpdb()->get_results( Db::prepare( "SELECT id, name, filters, notify, created_at FROM {$this->table()} WHERE user_id = %d ORDER BY id DESC", array( $user_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as &$r ) {
			$r['id']      = (int) $r['id'];
			$r['notify']  = (bool) (int) $r['notify'];
			$r['filters'] = Json::decode_array( $r['filters'] ) ?? array();
		}
		return $rows;
	}

	/** Deletes only if the search belongs to the user. */
	public function delete( int $user_id, int $id ): bool {
		Db::wpdb()->delete( $this->table(), array( 'id' => $id, 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 1 === (int) Db::wpdb()->rows_affected;
	}

	/** Erase every saved search of a user (account deletion / erasure request). */
	public function delete_all_for_user( int $user_id ): int {
		Db::wpdb()->query( Db::prepare( "DELETE FROM {$this->table()} WHERE user_id = %d", array( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) Db::wpdb()->rows_affected;
	}
}
