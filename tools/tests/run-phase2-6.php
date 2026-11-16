<?php
/**
 * QA runner for phases 2–6 (membership, dealer CRM, sync, WhatsApp/AI, reports/privacy/hardening).
 *
 * Needs TWO local WordPress sites (see docs/QA-REPORT-2026-11-16.md, "Re-run"):
 *   central site (automotive-marketplace-core + autobuzy theme), served over HTTP
 *   dealer  site (automotive-dealer-crm + automotive-marketplace-connector + autodealer), served over HTTP
 * and the QA mu-plugin tools/tests/qa-local-mu-plugin.php copied into BOTH sites' wp-content/mu-plugins/.
 *
 * WARNING: creates and modifies data. Local/staging only, never production.
 *
 * Usage:
 *   php tools/tests/run-phase2-6.php <central-dir> <central-url> <dealer-dir> <dealer-url> [phases=2,3,4,5,6] [json-out]
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
$central_dir = rtrim( $argv[1] ?? '', '/' );
$central_url = rtrim( $argv[2] ?? '', '/' );
$dealer_dir  = rtrim( $argv[3] ?? '', '/' );
$dealer_url  = rtrim( $argv[4] ?? '', '/' );
$only        = isset( $argv[5] ) ? explode( ',', $argv[5] ) : array( '2', '3', '4', '5', '6' );
$json_out    = $argv[6] ?? '';
if ( ! is_file( $central_dir . '/wp-load.php' ) || ! is_file( $dealer_dir . '/wp-load.php' ) || ! $central_url || ! $dealer_url ) {
	fwrite( STDERR, "Usage: php tools/tests/run-phase2-6.php <central-dir> <central-url> <dealer-dir> <dealer-url> [phases] [json-out]\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = parse_url( $central_url, PHP_URL_HOST ) . ':' . parse_url( $central_url, PHP_URL_PORT );
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $central_dir . '/wp-load.php';
require __DIR__ . '/lib.php';

define( 'QA_MAIL_CENTRAL', $central_dir . '/wp-content/qa-mail.log' );
define( 'QA_MAIL_DEALER', $dealer_dir . '/wp-content/qa-mail.log' );
$C = array( 'dir' => $central_dir, 'url' => $central_url );
$D = array( 'dir' => $dealer_dir, 'url' => $dealer_url );

foreach ( array( '2', '3', '4', '5', '6' ) as $n ) {
	if ( in_array( $n, $only, true ) ) {
		require __DIR__ . "/phases/phase{$n}.php";
	}
}

exit( qa_summary( $json_out ) ? 1 : 0 );
