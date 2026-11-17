<?php
/**
 * THE data-access choke point for tenant-scoped tables.
 *
 *  - Every statement gets `tenant_id = <TenantContext::id()>`; with no tenant context it throws (fail closed).
 *  - Callers cannot supply tenant_id (in a WHERE or in data): it is rejected, not trusted.
 *  - Tables and columns are validated against the schema registry; values are always bound through
 *    $wpdb->prepare(); there is no raw-SQL entry point and no way to name another tenant.
 *  - UPDATE/DELETE require at least one caller-supplied condition (no "update everything of the tenant").
 *
 * Only this class, Directory (the explicit cross-tenant gateway), Installer and uninstall.php may touch $wpdb;
 * a static test enforces it.
 *
 * Condition syntax:  'col' => scalar            (=; null => IS NULL)
 *                    'col' => ['=', 'in', '!=', '<', '<=', '>', '>=', 'like', 'null', 'notnull', 'between' ...]
 *                    '_search' => [ ['col1','col2'], 'term' ]   (LIKE on several columns, OR-ed, AND-ed with the rest)
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Data;

use Wotoyi\DealerPlatform\Schema\Schema;

defined( 'ABSPATH' ) || exit;

final class TenantDb {

	private static int $depth = 0;

	public static function wpdb(): \wpdb {
		global $wpdb;
		return $wpdb;
	}

	/** Physical table name for a logical name (no validation: used by Schema and Directory). */
	public static function table_name( string $logical ): string {
		return self::wpdb()->prefix . 'wotoyi_dp_' . $logical;
	}

	public static function charset_collate(): string {
		return self::wpdb()->get_charset_collate();
	}

	/** Tenant table or exception. */
	private static function scoped( string $logical ): string {
		if ( ! Schema::is_scoped( $logical ) ) {
			throw new \InvalidArgumentException( 'not_a_tenant_table:' . $logical );
		}
		return self::table_name( $logical );
	}

	private static function col( string $table, string $column ): string {
		if ( 1 !== preg_match( '/^[a-z_][a-z0-9_]*$/', $column ) || ! in_array( $column, Schema::columns( $table ), true ) ) {
			throw new \InvalidArgumentException( 'unknown_column:' . $table . '.' . $column );
		}
		return $column;
	}

	/** @param mixed $v */
	private static function ph( $v ): string {
		return is_int( $v ) || is_bool( $v ) ? '%d' : ( is_float( $v ) ? '%f' : '%s' );
	}

	/** @param mixed $v */
	private static function arg( $v ) {
		return is_bool( $v ) ? (int) $v : $v;
	}

	/**
	 * @param array<string,mixed> $where
	 * @return array{0:string,1:list<mixed>} SQL fragment (always starts with tenant_id) and bound args.
	 */
	private static function where( string $table, array $where ): array {
		$sql  = 'tenant_id = %d';
		$args = array( TenantContext::id() );
		foreach ( $where as $key => $cond ) {
			if ( 'tenant_id' === $key ) {
				throw new \InvalidArgumentException( 'tenant_id_not_allowed' );
			}
			if ( '_search' === $key ) {
				[ $cols, $term ] = $cond;
				$term            = mb_substr( trim( (string) $term ), 0, 100 );
				if ( '' === $term || ! $cols ) {
					continue;
				}
				$parts = array();
				foreach ( $cols as $c ) {
					$parts[] = self::col( $table, $c ) . ' LIKE %s';
					$args[]  = '%' . self::wpdb()->esc_like( $term ) . '%';
				}
				$sql .= ' AND (' . implode( ' OR ', $parts ) . ')';
				continue;
			}
			$c = self::col( $table, (string) $key );
			if ( ! is_array( $cond ) ) {
				if ( null === $cond ) {
					$sql .= " AND {$c} IS NULL";
				} else {
					$sql  .= " AND {$c} = " . self::ph( $cond );
					$args[] = self::arg( $cond );
				}
				continue;
			}
			$op = strtolower( (string) $cond[0] );
			switch ( $op ) {
				case 'null':
					$sql .= " AND {$c} IS NULL";
					break;
				case 'notnull':
					$sql .= " AND {$c} IS NOT NULL";
					break;
				case 'in':
					$vals = array_values( (array) $cond[1] );
					if ( ! $vals ) {
						$sql .= ' AND 1 = 0';
						break;
					}
					$sql .= " AND {$c} IN (" . implode( ',', array_map( static fn( $v ): string => self::ph( $v ), $vals ) ) . ')';
					foreach ( $vals as $v ) {
						$args[] = self::arg( $v );
					}
					break;
				case 'range': // ['range', min|null, max|null]
					foreach ( array( 1 => '>=', 2 => '<=' ) as $i => $cmp ) {
						if ( isset( $cond[ $i ] ) && is_numeric( $cond[ $i ] ) ) {
							$sql  .= " AND {$c} {$cmp} " . self::ph( $cond[ $i ] + 0 );
							$args[] = $cond[ $i ] + 0;
						}
					}
					break;
				case 'like':
					$sql  .= " AND {$c} LIKE %s";
					$args[] = '%' . self::wpdb()->esc_like( mb_substr( (string) $cond[1], 0, 100 ) ) . '%';
					break;
				case '=':
				case '!=':
				case '<':
				case '<=':
				case '>':
				case '>=':
					if ( null === $cond[1] ) {
						throw new \InvalidArgumentException( 'null_comparison' );
					}
					$sql  .= " AND {$c} {$op} " . self::ph( $cond[1] );
					$args[] = self::arg( $cond[1] );
					break;
				default:
					throw new \InvalidArgumentException( 'bad_operator:' . $op );
			}
		}
		return array( $sql, $args );
	}

	/** A failed read must never look like "no rows": surface it (message stays in the PHP error log, not in responses). */
	private static function check( string $what ): void {
		$err = (string) self::wpdb()->last_error;
		if ( '' !== $err ) {
			error_log( 'wotoyi-dealer-platform database error in ' . $what ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			throw new \RuntimeException( 'db_error:' . $what );
		}
	}

	private static function prep( string $sql, array $args ): string {
		return self::wpdb()->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Insert a row for the current tenant. Returns the internal id.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function insert( string $logical, array $data ): int {
		$table = self::scoped( $logical );
		if ( array_key_exists( 'tenant_id', $data ) || array_key_exists( 'id', $data ) ) {
			throw new \InvalidArgumentException( 'tenant_id_not_allowed' );
		}
		foreach ( array_keys( $data ) as $c ) {
			self::col( $logical, (string) $c );
		}
		$row = array( 'tenant_id' => TenantContext::id() ) + $data;
		if ( false === self::wpdb()->insert( $table, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'db_insert_failed:' . $logical );
		}
		return (int) self::wpdb()->insert_id;
	}

	/**
	 * @param array<string,mixed> $where
	 * @param array<string,mixed> $opts   columns, order [[col,dir]], limit, offset
	 * @return list<array<string,mixed>>
	 */
	public static function select( string $logical, array $where = array(), array $opts = array() ): array {
		$table = self::scoped( $logical );
		[ $w, $args ] = self::where( $logical, $where );
		$cols  = isset( $opts['columns'] ) ? implode( ', ', array_map( fn( $c ) => self::col( $logical, (string) $c ), $opts['columns'] ) ) : '*';
		$sql   = "SELECT {$cols} FROM {$table} WHERE {$w}";
		if ( ! empty( $opts['order'] ) ) {
			$o = array();
			foreach ( $opts['order'] as [ $c, $dir ] ) {
				$o[] = self::col( $logical, (string) $c ) . ( 'desc' === strtolower( (string) $dir ) ? ' DESC' : ' ASC' );
			}
			$sql .= ' ORDER BY ' . implode( ', ', $o );
		}
		if ( isset( $opts['limit'] ) ) {
			$sql   .= ' LIMIT %d';
			$args[] = max( 1, min( 1000, (int) $opts['limit'] ) );
			if ( ! empty( $opts['offset'] ) ) {
				$sql   .= ' OFFSET %d';
				$args[] = max( 0, (int) $opts['offset'] );
			}
		}
		$rows = (array) self::wpdb()->get_results( self::prep( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::check( 'select:' . $logical );
		return $rows;
	}

	/** @param array<string,mixed> $where @return array<string,mixed>|null */
	public static function get( string $logical, array $where ): ?array {
		$rows = self::select( $logical, $where, array( 'limit' => 1 ) );
		return $rows[0] ?? null;
	}

	public static function find_uuid( string $logical, string $uuid ): ?array {
		return self::get( $logical, array( 'uuid' => $uuid ) );
	}

	/** @param array<string,mixed> $where */
	public static function count( string $logical, array $where = array() ): int {
		$table = self::scoped( $logical );
		[ $w, $args ] = self::where( $logical, $where );
		$n = (int) self::wpdb()->get_var( self::prep( "SELECT COUNT(*) FROM {$table} WHERE {$w}", $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::check( 'count:' . $logical );
		return $n;
	}

	/** @param array<string,mixed> $where */
	public static function sum( string $logical, string $column, array $where = array() ): int|float {
		$table = self::scoped( $logical );
		$c     = self::col( $logical, $column );
		[ $w, $args ] = self::where( $logical, $where );
		$v = self::wpdb()->get_var( self::prep( "SELECT COALESCE(SUM({$c}),0) FROM {$table} WHERE {$w}", $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::check( 'sum:' . $logical );
		return false !== strpos( (string) $v, '.' ) ? (float) $v : (int) $v;
	}

	/**
	 * Row counts grouped by one column.
	 *
	 * @param array<string,mixed> $where
	 * @return array<string,int>
	 */
	public static function group_count( string $logical, string $column, array $where = array() ): array {
		$table = self::scoped( $logical );
		$c     = self::col( $logical, $column );
		[ $w, $args ] = self::where( $logical, $where );
		$rows = (array) self::wpdb()->get_results( self::prep( "SELECT {$c} AS k, COUNT(*) AS n FROM {$table} WHERE {$w} GROUP BY {$c}", $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::check( 'group:' . $logical );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (string) $r['k'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Update rows of the current tenant. A value of ['@incr' => n] increments the column atomically.
	 *
	 * @param array<string,mixed> $where Must not be empty.
	 * @param array<string,mixed> $data
	 * @return int Affected rows.
	 */
	public static function update( string $logical, array $where, array $data ): int {
		$table = self::scoped( $logical );
		if ( 'audit_log' === $logical ) {
			throw new \InvalidArgumentException( 'audit_log_is_append_only' ); // SECURITY-REVIEW M3.
		}
		if ( ! $where || ! $data ) {
			throw new \InvalidArgumentException( 'update_requires_where_and_data' );
		}
		if ( array_key_exists( 'tenant_id', $data ) || array_key_exists( 'id', $data ) ) {
			throw new \InvalidArgumentException( 'tenant_id_not_allowed' );
		}
		$set  = array();
		$args = array();
		foreach ( $data as $c => $v ) {
			$c = self::col( $logical, (string) $c );
			if ( is_array( $v ) && array_key_exists( '@incr', $v ) ) {
				$set[]  = "{$c} = {$c} + %d";
				$args[] = (int) $v['@incr'];
			} elseif ( null === $v ) {
				$set[] = "{$c} = NULL";
			} else {
				$set[]  = "{$c} = " . self::ph( $v );
				$args[] = self::arg( $v );
			}
		}
		[ $w, $wargs ] = self::where( $logical, $where );
		$res = self::wpdb()->query( self::prep( "UPDATE {$table} SET " . implode( ', ', $set ) . " WHERE {$w}", array_merge( $args, $wargs ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $res ) {
			throw new \RuntimeException( 'db_update_failed:' . $logical );
		}
		return (int) $res;
	}

	/** Hard delete: allowed only on tables that are pruned by retention (outbox). */
	public static function delete( string $logical, array $where ): int {
		$table = self::scoped( $logical );
		if ( ! in_array( $logical, array( 'outbox' ), true ) || ! $where ) {
			throw new \InvalidArgumentException( 'delete_not_allowed:' . $logical );
		}
		[ $w, $args ] = self::where( $logical, $where );
		$res = self::wpdb()->query( self::prep( "DELETE FROM {$table} WHERE {$w}", $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false === $res ? 0 : (int) $res;
	}

	/**
	 * Run a callback in one DB transaction. Re-entrant (MySQL has no nested transactions: only the outermost
	 * call issues START TRANSACTION / COMMIT). Rolls back and rethrows on any exception.
	 *
	 * @return mixed
	 */
	public static function transaction( callable $fn ): mixed {
		$outer = 0 === self::$depth;
		if ( $outer ) {
			self::wpdb()->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		++self::$depth;
		try {
			$result = $fn();
			--self::$depth;
			if ( $outer ) {
				self::wpdb()->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			return $result;
		} catch ( \Throwable $e ) {
			--self::$depth;
			if ( $outer ) {
				self::wpdb()->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			throw $e;
		}
	}

	public static function in_transaction(): bool {
		return self::$depth > 0;
	}
}
