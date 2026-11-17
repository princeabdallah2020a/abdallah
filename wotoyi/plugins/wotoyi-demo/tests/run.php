<?php
/** Standalone runner:  php tests/run.php [filter]  (exit 1 on failure). */

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$files  = glob( __DIR__ . '/cases/*Test.php' );
sort( $files );
$passed = 0; $failed = array(); $asserts = 0; $t0 = microtime( true );
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
printf( "\n%d passed, %d failed, %d assertions, %.2fs (PHP %s)\n", $passed, count( $failed ), $asserts, microtime( true ) - $t0, PHP_VERSION );
if ( $failed ) {
	echo "Failed:\n  " . implode( "\n  ", $failed ) . "\n";
	exit( 1 );
}
