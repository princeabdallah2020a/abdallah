<?php
/**
 * Fixed-window rate limiter backed by transients (uses the object cache when
 * one is configured, the options table otherwise).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

	/**
	 * Count a hit and tell whether the caller is still within the limit.
	 *
	 * @param string $bucket Logical bucket, e.g. "api" or "inquiry".
	 * @param int    $limit  Max hits per window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when allowed, false when the limit is exceeded.
	 */
	public static function allow( $bucket, $limit, $window ) {
		$slot = (int) floor( time() / $window );
		$key  = 'amc_rl_' . md5( $bucket . '|' . self::ip_hash() . '|' . $slot );
		$hits = (int) get_transient( $key );
		if ( $hits >= $limit ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window );
		return true;
	}

	/**
	 * Client IP. Only REMOTE_ADDR is trusted by default: forwarded headers can
	 * be spoofed. Sites behind a trusted reverse proxy can use the filter.
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'amc_client_ip', $ip );
	}

	/** Salted hash so raw IP addresses are never stored. */
	public static function ip_hash() {
		return hash_hmac( 'sha256', self::client_ip(), wp_salt( 'auth' ) );
	}
}
