<?php
/**
 * TEST SHIM ONLY. A tiny $wpdb look-alike on PDO SQLite so the plugin's real repository/search SQL runs
 * in plain `php` without MySQL or WordPress. It is NOT MySQL: see README "Known limits" for what this does
 * and does not prove.
 */

class wpdb { // phpcs:ignore

	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public int $rows_affected = 0;
	public string $last_error = '';
	public array $queries = array();
	private bool $suppress = false;
	private int $tx_depth = 0;
	public PDO $pdo;

	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
		$this->pdo->sqliteCreateFunction( 'POWER', 'pow', 2 );
		$this->pdo->sqliteCreateFunction( 'SIN', 'sin', 1 );
		$this->pdo->sqliteCreateFunction( 'COS', 'cos', 1 );
		$this->pdo->sqliteCreateFunction( 'ASIN', 'asin', 1 );
		$this->pdo->sqliteCreateFunction( 'SQRT', 'sqrt', 1 );
		$this->pdo->sqliteCreateFunction( 'RADIANS', 'deg2rad', 1 );
	}

	public function get_charset_collate(): string {
		return '';
	}

	public function suppress_errors( bool $suppress = true ): bool {
		$old            = $this->suppress;
		$this->suppress = $suppress;
		return $old;
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/** Minimal wpdb::prepare: %s %d %f %% with array or varargs. */
	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$args = array_values( $args );
		$i    = 0;
		$out  = preg_replace_callback(
			'/%[sdfF%]/',
			function ( array $m ) use ( &$i, $args ) {
				if ( '%%' === $m[0] ) {
					return '%';
				}
				if ( ! array_key_exists( $i, $args ) ) {
					throw new RuntimeException( 'prepare(): too few arguments for query: ' . $query );
				}
				$v = $args[ $i++ ];
				switch ( $m[0] ) {
					case '%d':
						return (string) (int) $v;
					case '%s':
						return $this->pdo->quote( (string) $v );
					default:
						return sprintf( '%F', (float) $v );
				}
			},
			$query
		);
		if ( $i !== count( $args ) ) {
			throw new RuntimeException( 'prepare(): wrong argument count (' . $i . ' placeholders, ' . count( $args ) . ' args) for: ' . $query );
		}
		return $out;
	}

	private function translate( string $sql ): string {
		$sql = preg_replace( '/\s+FOR UPDATE\s*$/i', '', $sql );
		$sql = preg_replace( "/LIKE\s+('(?:[^']|'')*')/i", "LIKE $1 ESCAPE '\\'", $sql );
		return $sql;
	}

	private function run( string $sql ): ?PDOStatement {
		$sql                = $this->translate( $sql );
		$this->queries[]    = $sql;
		$this->last_error   = '';
		$u                  = strtoupper( trim( $sql ) );
		if ( 'START TRANSACTION' === $u ) {
			if ( 0 === $this->tx_depth ) {
				$this->pdo->exec( 'BEGIN' );
			}
			++$this->tx_depth;
			return null;
		}
		if ( 'COMMIT' === $u || 'ROLLBACK' === $u ) {
			if ( $this->tx_depth > 0 ) {
				--$this->tx_depth;
				if ( 0 === $this->tx_depth ) {
					$this->pdo->exec( $u );
				} elseif ( 'ROLLBACK' === $u ) {
					$this->pdo->exec( 'ROLLBACK' );
					$this->tx_depth = 0;
				}
			}
			return null;
		}
		try {
			$st = $this->pdo->query( $sql );
			return $st;
		} catch ( PDOException $e ) {
			$this->last_error = $e->getMessage();
			if ( ! $this->suppress ) {
				$GLOBALS['wotoyi_db_errors'][] = $e->getMessage() . ' | ' . substr( $sql, 0, 200 );
			}
			return null;
		}
	}

	public function query( string $sql ) {
		$st = $this->run( $sql );
		if ( '' !== $this->last_error ) {
			return false;
		}
		$this->rows_affected = $st ? $st->rowCount() : 0;
		return $this->rows_affected;
	}

	private function stringify( array $row ): array {
		foreach ( $row as $k => $v ) {
			$row[ $k ] = null === $v ? null : (string) $v;
		}
		return $row;
	}

	public function get_results( string $sql, $output = 'ARRAY_A' ): array {
		$st = $this->run( $sql );
		if ( ! $st ) {
			return array();
		}
		return array_map( array( $this, 'stringify' ), $st->fetchAll() );
	}

	public function get_row( string $sql, $output = 'ARRAY_A' ) {
		$rows = $this->get_results( $sql );
		return $rows[0] ?? null;
	}

	public function get_var( string $sql ) {
		$row = $this->get_row( $sql );
		return $row ? array_values( $row )[0] : null;
	}

	private function fmt( $v ): string {
		return null === $v ? 'NULL' : $this->pdo->quote( (string) $v );
	}

	public function insert( string $table, array $data ) {
		$sql = "INSERT INTO {$table} (" . implode( ',', array_keys( $data ) ) . ') VALUES (' . implode( ',', array_map( array( $this, 'fmt' ), $data ) ) . ')';
		$st  = $this->run( $sql );
		if ( '' !== $this->last_error ) {
			return false;
		}
		$this->insert_id     = (int) $this->pdo->lastInsertId();
		$this->rows_affected = $st->rowCount();
		return $this->rows_affected;
	}

	private function where( array $where ): string {
		$parts = array();
		foreach ( $where as $k => $v ) {
			$parts[] = null === $v ? "{$k} IS NULL" : "{$k} = " . $this->fmt( $v );
		}
		return implode( ' AND ', $parts );
	}

	public function update( string $table, array $data, array $where ) {
		$set = array();
		foreach ( $data as $k => $v ) {
			$set[] = "{$k} = " . $this->fmt( $v );
		}
		$st = $this->run( "UPDATE {$table} SET " . implode( ', ', $set ) . ' WHERE ' . $this->where( $where ) );
		if ( '' !== $this->last_error ) {
			return false;
		}
		$this->rows_affected = $st->rowCount();
		return $this->rows_affected;
	}

	public function delete( string $table, array $where ) {
		$st = $this->run( "DELETE FROM {$table} WHERE " . $this->where( $where ) );
		if ( '' !== $this->last_error ) {
			return false;
		}
		$this->rows_affected = $st->rowCount();
		return $this->rows_affected;
	}

	/** dbDelta replacement: translate MySQL DDL (as generated by Schema) to SQLite. */
	public function create_from_mysql( string $sql ): void {
		if ( ! preg_match( '/CREATE TABLE (\S+) \((.*)\)\s*[^)]*;$/s', trim( $sql ), $m ) ) {
			throw new RuntimeException( 'unparseable CREATE TABLE' );
		}
		$table = $m[1];
		$cols  = array();
		$idx   = array();
		$pk    = null;
		foreach ( preg_split( '/\R/', trim( $m[2] ) ) as $line ) {
			$line = rtrim( trim( $line ), ',' );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^PRIMARY KEY\s+\((.+)\)$/', $line, $k ) ) {
				$pk = $k[1];
			} elseif ( preg_match( '/^(UNIQUE )?KEY (\S+) \((.+)\)$/', $line, $k ) ) {
				$idx[] = 'CREATE ' . ( $k[1] ? 'UNIQUE ' : '' ) . "INDEX IF NOT EXISTS {$table}_{$k[2]} ON {$table} ({$k[3]})";
			} else {
				$line = preg_replace( '/\s+UNSIGNED/i', '', $line );
				if ( preg_match( '/^(\w+) .*AUTO_INCREMENT/i', $line, $c ) ) {
					$line = "{$c[1]} INTEGER PRIMARY KEY AUTOINCREMENT";
					$pk   = null === $pk ? 'auto' : $pk;
				}
				$cols[] = $line;
			}
		}
		$def = implode( ', ', $cols );
		if ( null !== $pk && 'auto' !== $pk && ! str_contains( $def, 'AUTOINCREMENT' ) ) {
			$def .= ", PRIMARY KEY ({$pk})";
		}
		$this->pdo->exec( "CREATE TABLE IF NOT EXISTS {$table} ({$def})" );
		foreach ( $idx as $i ) {
			$this->pdo->exec( $i );
		}
	}
}
