<?php
final class StaticChecksTest extends Wotoyi_Test_Case {

	private function php_files(): array {
		$root = dirname( __DIR__, 2 );
		$out  = array();
		$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( 'php' === $f->getExtension() ) {
				$out[] = $f->getPathname();
			}
		}
		sort( $out );
		return $out;
	}

	public function test_all_files_lint(): void {
		foreach ( $this->php_files() as $f ) {
			$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $f ) . ' 2>&1' );
			$this->assertStringContains( 'No syntax errors', (string) $out, $f );
		}
	}

	public function test_raw_sql_and_wpdb_only_in_the_data_layer(): void {
		$root    = dirname( __DIR__, 2 );
		$allowed = array( '/includes/Data/', '/includes/Installer.php', '/uninstall.php', '/tests/' );
		$checked = 0;
		foreach ( $this->php_files() as $f ) {
			$rel = str_replace( $root, '', $f );
			foreach ( $allowed as $a ) {
				if ( str_contains( $rel, $a ) ) {
					continue 2;
				}
			}
			++$checked;
			$src = (string) file_get_contents( $f );
			$this->assertFalse( (bool) preg_match( '/\$wpdb|global\s+\$wpdb|->query\(|->get_results\(|->prepare\(/', $src ), "{$rel} touches the database directly" );
			$this->assertFalse( (bool) preg_match( '/\b(SELECT\s+.+\s+FROM|INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+\S+\s+SET)\b/i', $src ), "{$rel} contains raw SQL" );
		}
		$this->assertTrue( $checked > 40, 'scanned the service, REST and sync layers' );
	}

	public function test_no_open_permission_callbacks_or_dangerous_functions(): void {
		$root = dirname( __DIR__, 2 );
		foreach ( $this->php_files() as $f ) {
			$rel = str_replace( $root, '', $f );
			if ( str_contains( $rel, '/tests/' ) ) {
				continue;
			}
			$src = (string) file_get_contents( $f );
			$this->assertFalse( str_contains( $src, '__return_true' ), "{$rel}: __return_true" );
			$this->assertFalse( (bool) preg_match( '/\b(eval|exec|shell_exec|system|passthru|unserialize)\s*\(/', $src ), "{$rel}: dangerous function" );
			$this->assertFalse( (bool) preg_match( '/\$_(GET|POST|REQUEST|COOKIE)\b/', $src ), "{$rel}: superglobal input outside WP_REST_Request" );
		}
	}

	public function test_every_route_has_a_permission_callback(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest/Routes.php' );
		$this->assertTrue( substr_count( $src, 'permission_callback' ) >= 1 );
		$this->assertFalse( str_contains( $src, "'permission_callback' => '__return" ) );
		$routes = $GLOBALS['wotoyi_routes'] ?? array();
		$this->assertTrue( count( $routes ) > 60, 'routes are registered: ' . count( $routes ) );
		foreach ( $routes as $r ) {
			$defs = isset( $r['args']['methods'] ) ? array( $r['args'] ) : $r['args'];
			foreach ( $defs as $h ) {
				$this->assertTrue( is_array( $h ) && isset( $h['permission_callback'] ) && is_callable( $h['permission_callback'] ), $r['pattern'] . ' has no permission callback' );
			}
		}
	}
}
