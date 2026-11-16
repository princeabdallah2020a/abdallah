<?php
/**
 * Run a PHP snippet inside a WordPress site (CLI) — used by the QA runner to
 * drive the dealer site, which cannot be loaded in the same process.
 * Usage: php tools/tests/wp-eval.php <site-dir> <base-url> '<php code>'
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
$site = rtrim( $argv[1] ?? '', '/' );
$base = rtrim( $argv[2] ?? '', '/' );
$_SERVER['HTTP_HOST']   = parse_url( $base, PHP_URL_HOST ) . ( parse_url( $base, PHP_URL_PORT ) ? ':' . parse_url( $base, PHP_URL_PORT ) : '' );
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.9';
require $site . '/wp-load.php';
// phpcs:ignore Squiz.PHP.Eval
eval( $argv[3] );
