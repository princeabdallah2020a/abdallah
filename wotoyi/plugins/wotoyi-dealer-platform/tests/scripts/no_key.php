<?php
// Run with WOTOYI_TEST_NO_KEY=1: proves the plugin refuses to pair / store secrets without WOTOYI_SECRET_KEY.
require __DIR__ . '/../bootstrap.php';

use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Crypto;
use Wotoyi\DealerPlatform\Sync\Pairing;

$GLOBALS['wpdb'] = new wpdb();
\Wotoyi\DealerPlatform\Installer::install_tables();
$out = array( 'key_defined' => defined( 'WOTOYI_SECRET_KEY' ), 'key_available' => Crypto::key_available() );
try {
	Crypto::encrypt( 'x' );
	$out['encrypt'] = 'allowed';
} catch ( RuntimeException $e ) {
	$out['encrypt'] = $e->getMessage();
}
Fixtures::user( 11 );
$t = Fixtures::tenant( 'Dealer', 11 );
$mp = new FakeMarketplace();
try {
	Fixtures::in( $t, fn() => Pairing::pair_http( 'WTY-ABCD-EFGH-JKLM', 'https://mp.example.com' ) );
	$out['pair'] = 'allowed';
} catch ( ApiException $e ) {
	$out['pair'] = $e->error_code() . ':' . $e->http_status();
}
$out['http_calls'] = count( $mp->requests );
$out['state_rows'] = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_wotoyi_dp_sync_state' );
echo json_encode( $out );
