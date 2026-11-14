<?php
/**
 * Symmetric encryption of stored secrets (libsodium secretbox).
 *
 * The key is derived from the WordPress AUTH salt: if the salts in
 * wp-config.php change, stored API secrets can no longer be decrypted and the
 * connections must be re-issued (documented in docs/DEPLOYMENT.md).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	private static function key() {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . '|amc-credentials', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public static function encrypt( $plain ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( (string) $plain, $nonce, self::key() ) );
	}

	/** @return string '' when the value cannot be decrypted. */
	public static function decrypt( $stored ) {
		$raw = base64_decode( (string) $stored, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Request signature shared by the marketplace and the connector:
	 * hex( HMAC-SHA256( secret, timestamp \n METHOD \n route \n sha256(body) ) ).
	 */
	public static function sign( $secret, $timestamp, $method, $route, $body ) {
		return hash_hmac( 'sha256', $timestamp . "\n" . strtoupper( $method ) . "\n" . $route . "\n" . hash( 'sha256', (string) $body ), $secret );
	}
}
