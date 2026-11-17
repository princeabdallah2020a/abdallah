<?php
/**
 * Buyer favourites (vehicles and services). Scoped to the logged-in user id.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class FavouriteRepository {

	public const MAX_PER_USER = 500;

	public function table(): string {
		return Db::table( 'favourites' );
	}

	public function add( int $user_id, string $type, int $item_id ): bool {
		if ( ! in_array( $type, array( 'vehicle', 'service' ), true ) || $this->count( $user_id ) >= self::MAX_PER_USER ) {
			return false;
		}
		$suppress = Db::wpdb()->suppress_errors( true );
		$ok       = Db::wpdb()->insert( $this->table(), array( 'user_id' => $user_id, 'item_type' => $type, 'item_id' => $item_id, 'created_at' => Clock::mysql() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Db::wpdb()->suppress_errors( $suppress );
		return false !== $ok || $this->has( $user_id, $type, $item_id ); // Already present counts as success (idempotent).
	}

	public function remove( int $user_id, string $type, int $item_id ): bool {
		return false !== Db::wpdb()->delete( $this->table(), array( 'user_id' => $user_id, 'item_type' => $type, 'item_id' => $item_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function has( int $user_id, string $type, int $item_id ): bool {
		$sql = Db::prepare( "SELECT 1 FROM {$this->table()} WHERE user_id = %d AND item_type = %s AND item_id = %d LIMIT 1", array( $user_id, $type, $item_id ) );
		return null !== Db::wpdb()->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	public function count( int $user_id ): int {
		return (int) Db::wpdb()->get_var( Db::prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE user_id = %d", array( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Favourite vehicles still visible publicly.
	 *
	 * @return list<array<string,mixed>> Rows: favourite created_at + vehicle uuid/slug/title.
	 */
	public function list_vehicles( int $user_id ): array {
		$sql = Db::prepare(
			'SELECT f.created_at AS favourited_at, v.uuid, v.slug, v.title, v.make, v.model, v.year, v.price, v.currency, v.cover_image FROM ' . $this->table() . ' f INNER JOIN ' . Db::table( 'vehicles' ) . ' v ON v.id = f.item_id INNER JOIN ' . Db::table( 'dealers' ) . " d ON d.id = v.dealer_id WHERE f.user_id = %d AND f.item_type = %s AND v.status = %s AND d.status = %s ORDER BY f.id DESC LIMIT %d",
			array( $user_id, 'vehicle', 'published', 'active', self::MAX_PER_USER )
		);
		return (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
