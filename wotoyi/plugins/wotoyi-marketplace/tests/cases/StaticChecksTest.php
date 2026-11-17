<?php

/**
 * Source-level guardrails (WordPress coding rules of PROJECT.md §14.3) checked on the real code.
 */
final class StaticChecksTest extends Wotoyi_Test_Case {

	/** @return list<string> */
	private function php_files( bool $include_main = true ): array {
		$out = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WOTOYI_MP_DIR . 'includes' ) ) as $f ) {
			if ( str_ends_with( (string) $f, '.php' ) ) {
				$out[] = (string) $f;
			}
		}
		if ( $include_main ) {
			$out[] = WOTOYI_MP_DIR . 'wotoyi-marketplace.php';
			$out[] = WOTOYI_MP_DIR . 'uninstall.php';
		}
		sort( $out );
		return $out;
	}

	public function test_php_lint_all_plugin_files(): void {
		$n = 0;
		foreach ( $this->php_files() as $file ) {
			exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
			$this->assertSame( 0, $code, "php -l $file: " . implode( ' ', $out ) );
			$out = array();
			++$n;
		}
		$this->assertGreaterThanOrEqual( 30, $n );
	}

	public function test_every_file_has_direct_access_guard_and_strict_types(): void {
		foreach ( $this->php_files() as $file ) {
			$src = file_get_contents( $file );
			$this->assertTrue( str_starts_with( $src, '<?php' ), "$file starts with <?php" );
			$this->assertTrue( str_contains( $src, "defined( 'ABSPATH' )" ) || str_contains( $src, "defined( 'WP_UNINSTALL_PLUGIN' )" ), "$file ABSPATH guard" );
			if ( str_contains( $file, '/includes/' ) && ! str_ends_with( $file, 'autoload.php' ) && ! str_ends_with( $file, 'api.php' ) ) {
				$this->assertStringContains( 'declare(strict_types=1);', $src, "$file strict_types" );
			}
		}
	}

	public function test_forbidden_constructs_are_absent(): void {
		$forbidden = array(
			'__return_true'  => '/__return_true/',
			'superglobals'   => '/\$_(GET|POST|REQUEST|COOKIE|FILES)\b/',
			'eval'           => '/\beval\s*\(/',
			'extract'        => '/\bextract\s*\(/',
			'unserialize'    => '/\bunserialize\s*\(/',
			'shell'          => '/\b(shell_exec|exec|system|passthru|proc_open|popen)\s*\(/',
			'create_function' => '/create_function\s*\(/',
			'raw mysqli'     => '/\bmysqli_|new\s+mysqli/',
			'var_dump'       => '/\b(var_dump|print_r|var_export)\s*\(/',
			'$wpdb->prepare free-form' => '/\$wpdb->prepare|\$db->prepare/',
		);
		foreach ( $this->php_files() as $file ) {
			$src = file_get_contents( $file );
			foreach ( $forbidden as $name => $re ) {
				if ( '$wpdb->prepare free-form' === $name ) {
					continue; // Db::prepare() is the single choke point, checked below.
				}
				$this->assertSame( 0, preg_match( $re, $src ), "$name in $file" );
			}
		}
	}

	public function test_superglobal_server_access_is_confined_to_ip_helper(): void {
		foreach ( $this->php_files() as $file ) {
			if ( preg_match( '/\$_SERVER/', file_get_contents( $file ) ) ) {
				$this->assertTrue( str_ends_with( $file, 'Support/Ip.php' ), "$file reads \$_SERVER" );
			}
		}
	}

	public function test_all_sql_goes_through_prepared_choke_point(): void {
		// Every wpdb read/query call must either take a Db::prepare() result inline, a variable assigned from it,
		// or be a constant statement (transaction control / DDL helpers) explicitly annotated.
		foreach ( $this->php_files() as $file ) {
			$lines = file( $file );
			foreach ( $lines as $i => $line ) {
				if ( ! preg_match( '/->(get_results|get_row|get_var|get_col|query)\(/', $line ) ) {
					continue;
				}
				if ( str_contains( $line, 'Db::prepare(' ) ) {
					continue;
				}
				if ( preg_match( "/->(get_results|get_row|get_var|query)\( '(START TRANSACTION|COMMIT|ROLLBACK)' \)/", $line ) ) {
					continue;
				}
				if ( preg_match( '/->(get_results|get_row|get_var|query)\( \$sql\s*[,)]/', $line ) ) {
					// $sql must have been assigned from Db::prepare shortly above (statements can span several lines).
					$ctx = implode( '', array_slice( $lines, max( 0, $i - 15 ), 16 ) );
					$this->assertTrue( 1 === preg_match( '/\$sql\s*=\s*Db::prepare\(/', $ctx ), "$file:" . ( $i + 1 ) . ' $sql not assigned from Db::prepare' );
					continue;
				}
				if ( str_contains( $line, 'DROP TABLE IF EXISTS' ) && str_ends_with( $file, 'Installer.php' ) ) {
					continue; // Table names come from the fixed Schema::names() list.
				}
				$this->fail( "$file:" . ( $i + 1 ) . ' SQL call not using Db::prepare: ' . trim( $line ) );
			}
		}
		$this->assertTrue( true );
	}

	public function test_every_registered_route_has_a_real_permission_callback(): void {
		$routes = $GLOBALS['wotoyi_routes'];
		$this->assertGreaterThanOrEqual( 30, count( $routes ) );
		$write = 0;
		foreach ( $routes as $r ) {
			$perm = $r['args']['permission_callback'] ?? null;
			$this->assertTrue( is_callable( $perm ), $r['pattern'] . ' has a callable permission_callback' );
			$this->assertTrue( '__return_true' !== $perm, $r['pattern'] . ' must not be __return_true' );
			if ( 'GET' !== $r['args']['methods'] ) {
				++$write;
				if ( is_array( $perm ) ) {
					$this->assertTrue( 'public_read' !== $perm[1], $r['pattern'] . ' write route with read-only gate' );
				}
			}
		}
		$this->assertGreaterThanOrEqual( 15, $write );
	}

	public function test_sync_routes_all_use_the_hmac_authenticator_except_pairing(): void {
		foreach ( $GLOBALS['wotoyi_routes'] as $r ) {
			if ( ! str_starts_with( $r['pattern'], '/wotoyi/v1/sync/' ) || '/wotoyi/v1/sync/pair' === $r['pattern'] ) {
				continue;
			}
			$this->assertTrue( $r['args']['permission_callback'] instanceof Closure, $r['pattern'] . ' signed' );
			// Behavioural proof: unsigned requests are refused for every method on that route.
			$method = explode( ',', $r['args']['methods'] )[0];
			$path   = preg_replace( '/\(\?P<source_id>[^)]*\)/', 'x1', str_replace( '\\', '', $r['pattern'] ) );
			$res    = rest_do_request( new WP_REST_Request( trim( $method ), $path ) );
			$this->assertStatus( 401, $res, $r['pattern'] );
		}
	}

	public function test_admin_and_account_routes_refuse_anonymous_requests(): void {
		$samples = array(
			array( 'POST', '/wotoyi/v1/admin/dealers', 401 ),
			array( 'POST', '/wotoyi/v1/admin/dealers/00000000-0000-7000-8000-000000000000/pairing-code', 401 ),
			array( 'POST', '/wotoyi/v1/admin/dealers/00000000-0000-7000-8000-000000000000/status', 401 ),
			array( 'GET', '/wotoyi/v1/admin/dealers/00000000-0000-7000-8000-000000000000/sync-log', 401 ),
			array( 'GET', '/wotoyi/v1/me/favourites', 401 ),
			array( 'POST', '/wotoyi/v1/me/favourites', 401 ),
			array( 'DELETE', '/wotoyi/v1/me/favourites/vehicle/abc', 401 ),
			array( 'GET', '/wotoyi/v1/me/saved-searches', 401 ),
			array( 'POST', '/wotoyi/v1/me/saved-searches', 401 ),
			array( 'DELETE', '/wotoyi/v1/me/saved-searches/1', 401 ),
		);
		foreach ( $samples as [ $m, $path, $code ] ) {
			$this->assertStatus( $code, rest_do_request( new WP_REST_Request( $m, $path ) ), "$m $path" );
		}
	}
}
