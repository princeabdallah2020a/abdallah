<?php
/**
 * Permission callbacks. Every route uses one of these (no blanket-allow shortcut).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Rest;

use Wotoyi\Marketplace\Installer;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class Permissions {

	/** Public, read-only catalogue data. Rate limited per IP; no state change. */
	public static function public_read(): bool|\WP_Error {
		if ( ! RateLimiter::allow( 'read:' . Ip::hash( Ip::client() ), Settings::int( 'public_rate_limit' ), 60 ) ) {
			return new \WP_Error( 'wotoyi_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
		}
		return true;
	}

	/** Public endpoints that write (leads, view counter): tighter per-IP limit. */
	public static function public_write(): bool|\WP_Error {
		if ( ! RateLimiter::allow( 'write:' . Ip::hash( Ip::client() ), 30, 60 ) ) {
			return new \WP_Error( 'wotoyi_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
		}
		return true;
	}

	/** Pairing endpoint gate (the code check and its failure limits live in Pairing). */
	public static function pairing_gate(): bool|\WP_Error {
		if ( ! RateLimiter::allow( 'pair:' . Ip::hash( Ip::client() ), 20, 60 ) ) {
			return new \WP_Error( 'wotoyi_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
		}
		return true;
	}

	/** Logged-in buyer, read. */
	public static function account_read(): bool|\WP_Error {
		return is_user_logged_in() ? true : new \WP_Error( 'wotoyi_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
	}

	/** Logged-in buyer, write: also requires a valid REST nonce (cookie auth) unless application-password auth was used. */
	public static function account_write( \WP_REST_Request $request ): bool|\WP_Error {
		$read = self::account_read();
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		return self::check_nonce( $request );
	}

	/** Marketplace staff (custom capability), read. */
	public static function admin_read(): bool|\WP_Error {
		return current_user_can( Installer::CAP ) ? true : new \WP_Error( 'wotoyi_forbidden', 'Insufficient permissions.', array( 'status' => is_user_logged_in() ? 403 : 401 ) );
	}

	/** Marketplace staff, write (capability + nonce). */
	public static function admin_write( \WP_REST_Request $request ): bool|\WP_Error {
		$read = self::admin_read();
		if ( is_wp_error( $read ) ) {
			return $read;
		}
		return self::check_nonce( $request );
	}

	private static function check_nonce( \WP_REST_Request $request ): bool|\WP_Error {
		$nonce = (string) $request->get_header( 'x_wp_nonce' );
		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		if ( did_action( 'application_password_did_authenticate' ) ) {
			return true; // Non-cookie authentication is not CSRF-prone.
		}
		return new \WP_Error( 'rest_cookie_invalid_nonce', 'Invalid or missing REST nonce.', array( 'status' => 403 ) );
	}
}
