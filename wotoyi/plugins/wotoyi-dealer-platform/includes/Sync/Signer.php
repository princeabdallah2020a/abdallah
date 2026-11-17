<?php
/**
 * HMAC-SHA256 request signing, client side. Wire format is fixed by the marketplace receiver
 * (wotoyi-marketplace README, "Sync protocol"):
 *
 *   canonical = "WOTOYI-HMAC-SHA256\n" . timestamp . "\n" . nonce . "\n" . METHOD . "\n" . route . "\n" . sha256_hex(body)
 *   route     = "/wotoyi/v1/sync/events"   (REST route: no host, no /wp-json prefix, no query string)
 *   signature = hex(HMAC-SHA256(secret, canonical))
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class Signer {

	public const ALGO = 'WOTOYI-HMAC-SHA256';

	public static function canonical( string $timestamp, string $nonce, string $method, string $path, string $body ): string {
		return implode( "\n", array( self::ALGO, $timestamp, $nonce, strtoupper( $method ), $path, hash( 'sha256', $body ) ) );
	}

	public static function signature( string $secret, string $timestamp, string $nonce, string $method, string $path, string $body ): string {
		return hash_hmac( 'sha256', self::canonical( $timestamp, $nonce, $method, $path, $body ), $secret );
	}

	/** @return array<string,string> */
	public static function headers( string $key_id, string $secret, string $method, string $path, string $body, ?int $timestamp = null, ?string $nonce = null ): array {
		$ts    = (string) ( $timestamp ?? Clock::now() );
		$nonce = $nonce ?? Crypto::token( 16 ); // 22 URL-safe chars: inside the receiver's 16-64 range.
		return array(
			'X-Wotoyi-Key'       => $key_id,
			'X-Wotoyi-Timestamp' => $ts,
			'X-Wotoyi-Nonce'     => $nonce,
			'X-Wotoyi-Signature' => self::signature( $secret, $ts, $nonce, $method, $path, $body ),
			'Content-Type'       => 'application/json',
		);
	}
}
