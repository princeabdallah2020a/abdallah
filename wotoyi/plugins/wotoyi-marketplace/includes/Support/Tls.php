<?php
/**
 * Transport security for secret-bearing endpoints (SECURITY-REVIEW M4).
 *
 * /sync/* (pairing and every signed route) must be reached over HTTPS: the pairing and rotate-key responses
 * carry the clear HMAC secret. Two explicit switches exist:
 *
 *  - Filter `wotoyi_mp_request_is_secure` (bool $is_ssl): for sites behind a TLS-terminating proxy/CDN where
 *    WordPress cannot see the TLS hop. Only return true if the proxy header is set by YOUR proxy and cannot be
 *    forged by clients.
 *  - Constant WOTOYI_MP_ALLOW_INSECURE (or filter `wotoyi_mp_allow_insecure_transport`): local development and
 *    tests ONLY. It is ignored when wp_get_environment_type() is 'production' (the WordPress default).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class Tls {

	/** True when the current request arrived over TLS (or the site owner vouched for it through the filter). */
	public static function request_is_secure(): bool {
		$ssl = function_exists( 'is_ssl' ) && is_ssl();
		return (bool) apply_filters( 'wotoyi_mp_request_is_secure', $ssl );
	}

	/** True when insecure transport may be used (never in production). */
	public static function insecure_allowed(): bool {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( 'production' === $env ) {
			return false;
		}
		$allowed = defined( 'WOTOYI_MP_ALLOW_INSECURE' ) && true === WOTOYI_MP_ALLOW_INSECURE;
		return (bool) apply_filters( 'wotoyi_mp_allow_insecure_transport', $allowed );
	}

	/** Gate for secret-bearing server endpoints. @return true|\WP_Error */
	public static function require_secure_request(): bool|\WP_Error {
		if ( self::request_is_secure() || self::insecure_allowed() ) {
			return true;
		}
		return new \WP_Error( 'wotoyi_https_required', 'HTTPS is required for this endpoint.', array( 'status' => 403 ) );
	}

	/** Client side: is this base URL acceptable? */
	public static function url_allowed( string $url ): bool {
		$scheme = 1 === preg_match( '#^([a-z][a-z0-9+.\-]*)://[^/\s]+#i', $url, $m ) ? strtolower( $m[1] ) : '';
		return 'https' === $scheme || ( 'http' === $scheme && self::insecure_allowed() );
	}
}
