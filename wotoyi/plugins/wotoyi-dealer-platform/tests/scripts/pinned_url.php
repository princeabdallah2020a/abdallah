<?php
// Run in its own process: a constant cannot be redefined, so the pinned-URL behaviour (SECURITY-REVIEW M1) needs a clean PHP.
define( 'WOTOYI_DP_MARKETPLACE_URL', 'https://wotoyi.example.com/' );
require __DIR__ . '/../bootstrap.php';

use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Sync\Pairing;

Pairing::set_resolver( static fn( string $host ): array => array( '93.184.216.34' ) );
$out = array( 'empty_uses_pinned' => Pairing::validate_base_url( '' ), 'same_origin' => Pairing::validate_base_url( 'https://WOTOYI.example.com/' ) );
foreach ( array( 'https://attacker.example.net', 'https://wotoyi.example.com.attacker.net', 'http://wotoyi.example.com', 'https://wotoyi.example.com:8443', 'https://other.example.com/x' ) as $u ) {
	try {
		Pairing::validate_base_url( $u );
		$out['rejected'][ $u ] = false;
	} catch ( ApiException $e ) {
		$out['rejected'][ $u ] = true;
	}
}
echo json_encode( $out );
