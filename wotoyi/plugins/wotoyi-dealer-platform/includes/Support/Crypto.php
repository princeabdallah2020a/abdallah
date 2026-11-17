<?php
/**
 * Secrets handling: encryption at rest for the marketplace HMAC secret, keyed hashes, random tokens.
 *
 * Same approach as the marketplace plugin (libsodium secretbox, key derived with HKDF from WOTOYI_SECRET_KEY)
 * with ONE deliberate difference: there is NO fallback to the WordPress salts. Without WOTOYI_SECRET_KEY
 * (>= 32 chars, defined in wp-config.php) encryption is refused, so pairing and sync are disabled rather than
 * silently protected by a key that changes when salts are rotated.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Support;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	public static function key_available(): bool {
		return defined( 'WOTOYI_SECRET_KEY' ) && is_string( WOTOYI_SECRET_KEY ) && strlen( WOTOYI_SECRET_KEY ) >= 32;
	}

	private static function derive( string $purpose ): string {
		if ( ! self::key_available() ) {
			throw new \RuntimeException( 'secret_key_missing' );
		}
		return hash_hkdf( 'sha256', WOTOYI_SECRET_KEY, 32, 'wotoyi-dp:' . $purpose );
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
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::derive( 'secretbox' ) );
		return false === $plain ? null : $plain;
	}

	/** Keyed hash (HMAC-SHA256, hex): used for IPs in the audit log. Falls back to an unkeyed-but-salted hash when no key. */
	public static function keyed_hash( string $purpose, string $value ): string {
		$key = self::key_available() ? self::derive( 'hmac:' . $purpose ) : hash( 'sha256', wp_salt( 'auth' ) . $purpose );
		return hash_hmac( 'sha256', $value, $key );
	}

	/** URL-safe random token. */
	public static function token( int $bytes = 32 ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** Normalise a marketplace pairing code (WTY-XXXX-XXXX-XXXX) or return ''. */
	public static function normalize_pairing_code( string $code ): string {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $code ) ?? '' );
		if ( 15 !== strlen( $code ) || ! str_starts_with( $code, 'WTY' ) ) {
			return '';
		}
		return 'WTY-' . substr( $code, 3, 4 ) . '-' . substr( $code, 7, 4 ) . '-' . substr( $code, 11, 4 );
	}
}
