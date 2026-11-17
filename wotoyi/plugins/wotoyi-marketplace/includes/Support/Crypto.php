<?php
/**
 * Secrets handling: encryption at rest for per-dealer HMAC secrets, keyed hashes, random tokens.
 *
 * Master key: constant WOTOYI_SECRET_KEY (define it in wp-config.php, >= 32 chars) or, as a fallback,
 * a key derived from the WordPress salts. Changing the key makes stored dealer secrets unreadable
 * (dealers must be re-paired).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	private static function master(): string {
		if ( defined( 'WOTOYI_SECRET_KEY' ) && is_string( WOTOYI_SECRET_KEY ) && strlen( WOTOYI_SECRET_KEY ) >= 32 ) {
			return WOTOYI_SECRET_KEY;
		}
		return wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
	}

	private static function derive( string $purpose ): string {
		return hash_hkdf( 'sha256', self::master(), 32, 'wotoyi-mp:' . $purpose );
	}

	public static function encrypt( string $plain ): string {
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::derive( 'secretbox' ) );
		return 'v1:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function decrypt( ?string $stored ): ?string {
		if ( null === $stored || ! str_starts_with( $stored, 'v1:' ) ) {
			return null;
		}
		$raw = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::derive( 'secretbox' ) );
		return false === $plain ? null : $plain;
	}

	/** Keyed hash (HMAC-SHA256, hex) for values we must compare but never store in clear (pairing codes, IPs). */
	public static function keyed_hash( string $purpose, string $value ): string {
		return hash_hmac( 'sha256', $value, self::derive( 'hmac:' . $purpose ) );
	}

	/** URL-safe random token. */
	public static function token( int $bytes = 32 ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** Human-friendly pairing code: WTY-XXXX-XXXX-XXXX (60 bits, unambiguous alphabet). */
	public static function pairing_code(): string {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 32 symbols.
		$out      = '';
		for ( $i = 0; $i < 12; $i++ ) {
			$out .= $alphabet[ random_int( 0, 31 ) ];
		}
		return 'WTY-' . substr( $out, 0, 4 ) . '-' . substr( $out, 4, 4 ) . '-' . substr( $out, 8, 4 );
	}

	public static function normalize_pairing_code( string $code ): string {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $code ) ?? '' );
		if ( 15 !== strlen( $code ) || ! str_starts_with( $code, 'WTY' ) ) {
			return '';
		}
		return 'WTY-' . substr( $code, 3, 4 ) . '-' . substr( $code, 7, 4 ) . '-' . substr( $code, 11, 4 );
	}
}
