<?php
/**
 * HMAC-SHA256 request signing shared by both sides (the marketplace verifies, the Dealer Platform signs).
 *
 * Canonical string (newline separated):
 *   WOTOYI-HMAC-SHA256 \n timestamp \n nonce \n METHOD \n route-path \n sha256-hex(body)
 * The route path (e.g. /wotoyi/v1/sync/events) is signed, so a captured signature cannot be replayed against
 * another endpoint or entity id. The query string is NOT signed, so signed routes refuse any query string
 * (Authenticator::permission(), SECURITY-REVIEW M1).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class Signer {

	public const ALGO = 'WOTOYI-HMAC-SHA256';

	public const HEADER_KEY       = 'X-Wotoyi-Key';
	public const HEADER_TIMESTAMP = 'X-Wotoyi-Timestamp';
	public const HEADER_NONCE     = 'X-Wotoyi-Nonce';
	public const HEADER_SIGNATURE = 'X-Wotoyi-Signature';

	public static function canonical( string $timestamp, string $nonce, string $method, string $path, string $body ): string {
		return implode( "\n", array( self::ALGO, $timestamp, $nonce, strtoupper( $method ), $path, hash( 'sha256', $body ) ) );
	}

	public static function signature( string $secret, string $timestamp, string $nonce, string $method, string $path, string $body ): string {
		return hash_hmac( 'sha256', self::canonical( $timestamp, $nonce, $method, $path, $body ), $secret );
	}

	/**
	 * Headers a client must send.
	 *
	 * @return array<string,string>
	 */
	public static function headers( string $key_id, string $secret, string $method, string $path, string $body, ?int $timestamp = null, ?string $nonce = null ): array {
		$ts    = (string) ( $timestamp ?? Clock::now() );
		$nonce = $nonce ?? Crypto::token( 16 );
		return array(
			self::HEADER_KEY       => $key_id,
			self::HEADER_TIMESTAMP => $ts,
			self::HEADER_NONCE     => $nonce,
			self::HEADER_SIGNATURE => self::signature( $secret, $ts, $nonce, $method, $path, $body ),
			'Content-Type'         => 'application/json',
		);
	}
}
