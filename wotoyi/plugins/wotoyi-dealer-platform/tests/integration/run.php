<?php
/**
 * Cross-plugin integration runner:  php tests/integration/run.php [filter]
 *
 * Boots BOTH plugins in one PHP process / one (SQLite) database, with the marketplace's real REST routes,
 * receiver, repositories and search. The HTTP transport of the Dealer Platform is bridged to the marketplace's
 * REST dispatcher (no FakeMarketplace here), so signatures are verified by the real receiver.
 */

define( 'WOTOYI_MP_VERSION', 'test' );
define( 'WOTOYI_MP_DB_VERSION', '2' );
define( 'WOTOYI_MP_DIR', dirname( __DIR__, 3 ) . '/wotoyi-marketplace/' );
define( 'WOTOYI_MP_FILE', WOTOYI_MP_DIR . 'wotoyi-marketplace.php' );

require_once dirname( __DIR__ ) . '/bootstrap.php';
if ( ! is_file( WOTOYI_MP_DIR . 'includes/autoload.php' ) ) {
	fwrite( STDERR, "Marketplace plugin not found at " . WOTOYI_MP_DIR . "\n" );
	exit( 2 );
}
require_once WOTOYI_MP_DIR . 'includes/autoload.php';
require_once __DIR__ . '/IntegrationCase.php';

$filter  = $argv[1] ?? '';
$files   = glob( __DIR__ . '/cases/*Test.php' );
sort( $files );
$passed  = 0;
$failed  = array();
$asserts = 0;
$started = microtime( true );
foreach ( $files as $file ) {
	$before = get_declared_classes();
	require_once $file;
	foreach ( array_diff( get_declared_classes(), $before ) as $class ) {
		if ( ! is_subclass_of( $class, 'Wotoyi_Test_Case' ) ) {
			continue;
		}
		foreach ( get_class_methods( $class ) as $method ) {
			if ( ! str_starts_with( $method, 'test_' ) || ( $filter && ! str_contains( "$class::$method", $filter ) ) ) {
				continue;
			}
			$t = new $class();
			try {
				$t->run_test( $method );
				++$passed;
				echo "  ok    {$class}::{$method}\n";
			} catch ( Throwable $e ) {
				$failed[] = "{$class}::{$method}";
				echo "  FAIL  {$class}::{$method}\n        " . get_class( $e ) . ': ' . $e->getMessage() . "\n        at " . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
			}
			$asserts += $t->assertions;
		}
	}
}
printf( "\n%d passed, %d failed, %d assertions, %.2fs (PHP %s)\n", $passed, count( $failed ), $asserts, microtime( true ) - $started, PHP_VERSION );
if ( $failed ) {
	echo "Failed:\n  " . implode( "\n  ", $failed ) . "\n";
	exit( 1 );
}
