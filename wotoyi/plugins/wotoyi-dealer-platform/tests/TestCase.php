<?php
/**
 * Minimal PHPUnit-compatible base class (setUp/tearDown, assertSame, ...). Plain `php tests/run.php` runs it;
 * the same test classes can be run by PHPUnit if you alias this class to PHPUnit\Framework\TestCase.
 */

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\Clock;

class AssertionFailure extends Exception {}

abstract class Wotoyi_Test_Case {

	public const T0 = 1800000000; // Fixed "now" (2027-01-15) for deterministic time tests.

	public int $assertions = 0;

	protected function setUp(): void {
		$GLOBALS['wotoyi_options']    = array();
		$GLOBALS['wotoyi_transients'] = array();
		$GLOBALS['wotoyi_filters']    = array();
		$GLOBALS['wotoyi_user']       = array( 'id' => 0, 'caps' => array() );
		$GLOBALS['wotoyi_db_errors']  = array();
		$GLOBALS['wotoyi_did']        = array();
		$GLOBALS['wotoyi_actions']    = array();
		$GLOBALS['wotoyi_is_ssl']     = true;
		$GLOBALS['wotoyi_env']        = 'production';
		$GLOBALS['wotoyi_users']      = array();
		$GLOBALS['wotoyi_routes']     = array();
		$_SERVER['REMOTE_ADDR']       = '203.0.113.7';
		$GLOBALS['wpdb']              = new wpdb();
		$GLOBALS['wotoyi_wp_users']   = array();
		$GLOBALS['wotoyi_http_log']   = array();
		$GLOBALS['wotoyi_mail_log']   = array();
		method_exists( \Wotoyi\DealerPlatform\Sync\Pairing::class, 'set_resolver' ) && \Wotoyi\DealerPlatform\Sync\Pairing::set_resolver( static fn( string $host ): array => array( '93.184.216.34' ) ); // No real DNS in tests.
		$GLOBALS['wotoyi_http_handler'] = null;
		$GLOBALS['wotoyi_cron_single'] = array();
		Plugin::reset();
		TenantContext::reset();
		Clock::set( self::T0 );
		\Wotoyi\DealerPlatform\Installer::install_tables();
		\Wotoyi\DealerPlatform\Rest\Routes::register();
	}

	protected function tearDown(): void {
		Clock::set( null );
		TenantContext::reset();
	}

	public function run_test( string $method ): void {
		$this->setUp();
		try {
			$this->$method();
			if ( $GLOBALS['wotoyi_db_errors'] && ! $this->allow_db_errors ) {
				throw new AssertionFailure( 'Unexpected SQL errors: ' . implode( ' || ', $GLOBALS['wotoyi_db_errors'] ) );
			}
		} finally {
			$this->tearDown();
		}
	}

	/** Tests that deliberately provoke constraint violations set this. */
	protected bool $allow_db_errors = false;

	/* ------------------------- assertions ------------------------- */

	protected function fail( string $msg ): never {
		throw new AssertionFailure( $msg );
	}

	protected function assertTrue( $v, string $msg = '' ): void {
		++$this->assertions;
		if ( true !== $v ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected true, got ' . var_export( $v, true ) );
		}
	}

	protected function assertFalse( $v, string $msg = '' ): void {
		++$this->assertions;
		if ( false !== $v ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected false, got ' . var_export( $v, true ) );
		}
	}

	protected function assertSame( $expected, $actual, string $msg = '' ): void {
		++$this->assertions;
		if ( $expected !== $actual ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected ' . $this->dump( $expected ) . ', got ' . $this->dump( $actual ) );
		}
	}

	protected function assertEquals( $expected, $actual, string $msg = '' ): void {
		++$this->assertions;
		if ( $expected != $actual ) { // phpcs:ignore
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected ' . $this->dump( $expected ) . ', got ' . $this->dump( $actual ) );
		}
	}

	protected function assertNull( $v, string $msg = '' ): void {
		++$this->assertions;
		if ( null !== $v ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected null, got ' . $this->dump( $v ) );
		}
	}

	protected function assertNotNull( $v, string $msg = '' ): void {
		++$this->assertions;
		if ( null === $v ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected non-null' );
		}
	}

	protected function assertCount( int $n, $arr, string $msg = '' ): void {
		++$this->assertions;
		if ( ! is_countable( $arr ) || count( $arr ) !== $n ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "expected count {$n}, got " . ( is_countable( $arr ) ? count( $arr ) : 'non-countable' ) . ' ' . $this->dump( $arr ) );
		}
	}

	protected function assertGreaterThanOrEqual( $min, $actual, string $msg = '' ): void {
		++$this->assertions;
		if ( $actual < $min ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "expected >= {$min}, got {$actual}" );
		}
	}

	protected function assertArrayHasKey( $key, array $arr, string $msg = '' ): void {
		++$this->assertions;
		if ( ! array_key_exists( $key, $arr ) ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "missing key '{$key}' in " . $this->dump( array_keys( $arr ) ) );
		}
	}

	protected function assertArrayNotHasKey( $key, array $arr, string $msg = '' ): void {
		++$this->assertions;
		if ( array_key_exists( $key, $arr ) ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "unexpected key '{$key}'" );
		}
	}

	protected function assertContains( $needle, array $hay, string $msg = '' ): void {
		++$this->assertions;
		if ( ! in_array( $needle, $hay, true ) ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'missing ' . $this->dump( $needle ) . ' in ' . $this->dump( $hay ) );
		}
	}

	protected function assertNotContains( $needle, array $hay, string $msg = '' ): void {
		++$this->assertions;
		if ( in_array( $needle, $hay, true ) ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . 'unexpected ' . $this->dump( $needle ) );
		}
	}

	protected function assertStringContains( string $needle, string $hay, string $msg = '' ): void {
		++$this->assertions;
		if ( ! str_contains( $hay, $needle ) ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "'{$needle}' not found in " . substr( $hay, 0, 300 ) );
		}
	}

	protected function assertStringNotContains( string $needle, string $hay, string $msg = '' ): void {
		++$this->assertions;
		if ( str_contains( $hay, $needle ) ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "'{$needle}' unexpectedly found" );
		}
	}

	protected function assertThrows( callable $fn, string $message_part = '', string $msg = '' ): void {
		++$this->assertions;
		try {
			$fn();
		} catch ( Throwable $e ) {
			if ( '' !== $message_part && ! str_contains( $e->getMessage(), $message_part ) ) {
				$this->fail( ( $msg ? $msg . ': ' : '' ) . "threw '{$e->getMessage()}', expected to contain '{$message_part}'" );
			}
			return;
		}
		$this->fail( ( $msg ? $msg . ': ' : '' ) . 'expected an exception' );
	}

	protected function assertStatus( int $status, $res, string $msg = '' ): void {
		++$this->assertions;
		if ( $res->get_status() !== $status ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "expected HTTP {$status}, got {$res->get_status()} " . substr( (string) json_encode( $res->get_data() ), 0, 300 ) );
		}
	}

	protected function assertLessThan( $max, $actual, string $msg = '' ): void {
		++$this->assertions;
		if ( $actual >= $max ) {
			$this->fail( ( $msg ? $msg . ': ' : '' ) . "expected < {$max}, got {$actual}" );
		}
	}

	private function dump( $v ): string {
		return substr( (string) json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ), 0, 400 );
	}
}
