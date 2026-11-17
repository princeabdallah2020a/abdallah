<?php
/**
 * Test bootstrap: WordPress shim + plugin autoloader. Run everything with:  php tests/run.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
set_error_handler(
	static function ( int $no, string $str, string $file, int $line ): bool {
		throw new ErrorException( $str, 0, $no, $file, $line ); // Notices/warnings/deprecations fail tests.
	}
);

require_once __DIR__ . '/shim/wp-shim.php';
require_once WOTOYI_DP_DIR . 'includes/autoload.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Fixtures.php';
require_once __DIR__ . '/FakeMarketplace.php';
