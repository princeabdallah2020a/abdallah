<?php
/**
 * Read-only dealer list for the wp-admin screen (name, status, vehicles count).
 *
 * @package Wotoyi\Demo
 */

declare(strict_types=1);

namespace Wotoyi\Demo;

use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class DealerList {

	public const PER_PAGE = 50;

	/** @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int} */
	public static function page( int $page ): array {
		$wpdb  = Db::wpdb();
		$d     = Db::table( 'dealers' );
		$v     = Db::table( 'vehicles' );
		$count = 'SELECT COUNT(*) FROM ' . $d;
		$total = (int) $wpdb->get_var( $count ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = max( 1, min( $page, $pages ) );
		$sql   = Db::prepare(
			"SELECT d.id, d.name, d.status, d.verified, d.city, d.transport, d.source_dealer_uuid,
				(SELECT COUNT(*) FROM {$v} v WHERE v.dealer_id = d.id AND v.status = %s) AS vehicles_published,
				(SELECT COUNT(*) FROM {$v} v2 WHERE v2.dealer_id = d.id AND v2.status <> %s) AS vehicles_total
			FROM {$d} d ORDER BY d.id DESC LIMIT %d OFFSET %d",
			array( 'published', 'deleted', self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE )
		);
		$rows  = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as &$row ) {
			$row['is_demo'] = 'local' === $row['transport'] && str_starts_with( (string) $row['source_dealer_uuid'], Seeder::PREFIX );
		}
		unset( $row );
		return array( 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages );
	}
}
