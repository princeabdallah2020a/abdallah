<?php
/**
 * Thin helpers around $wpdb.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class Db {

	public static function wpdb(): \wpdb {
		global $wpdb;
		return $wpdb;
	}

	/** Full table name for a logical name (vehicles, dealers, ...). */
	public static function table( string $name ): string {
		return self::wpdb()->prefix . 'wotoyi_' . $name;
	}

	/**
	 * Prepare a query only when it has arguments (wpdb::prepare() refuses queries without placeholders).
	 *
	 * @param string       $sql  SQL with %s / %d / %f placeholders only.
	 * @param array<mixed> $args Values.
	 */
	public static function prepare( string $sql, array $args = array() ): string {
		if ( array() === $args ) {
			return $sql;
		}
		return self::wpdb()->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/** Comma separated placeholders, e.g. placeholders(3,'%s') => "%s,%s,%s". */
	public static function placeholders( int $count, string $type = '%s' ): string {
		return implode( ',', array_fill( 0, max( 1, $count ), $type ) );
	}

	public static function begin(): void {
		self::wpdb()->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function commit(): void {
		self::wpdb()->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function rollback(): void {
		self::wpdb()->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
