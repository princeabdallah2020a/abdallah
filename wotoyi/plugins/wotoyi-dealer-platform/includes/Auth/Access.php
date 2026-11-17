<?php
/**
 * Resolves "who is calling, for which tenant, with which role" for a REST request, and checks capabilities.
 *
 * - The tenant is NEVER taken from the request body or URL ids. It comes from the caller's own active
 *   memberships; with several memberships the header X-Wotoyi-Tenant (a tenant uuid) selects one of THEM.
 * - A WordPress administrator has no automatic access to tenant data (tenant isolation applies to the operator too).
 * - Writes need a REST nonce (cookie auth) unless the request was authenticated by an application password.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Auth;

use Wotoyi\DealerPlatform\Data\Directory;

defined( 'ABSPATH' ) || exit;

final class Access {

	private static ?\WeakMap $contexts = null;

	/**
	 * Permission callback body.
	 *
	 * @return true|\WP_Error
	 */
	public static function authorize( \WP_REST_Request $request, string $cap, bool $write ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'wotoyi_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
		}
		$ctx = self::resolve( $request );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		if ( ! Roles::can( $ctx['member']['role'], $cap ) ) {
			return new \WP_Error( 'wotoyi_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}
		if ( $write ) {
			$nonce = self::check_nonce( $request );
			if ( is_wp_error( $nonce ) ) {
				return $nonce;
			}
		}
		self::$contexts ??= new \WeakMap();
		self::$contexts[ $request ] = $ctx;
		return true;
	}

	/** Platform operator routes (create tenants): WordPress capability + nonce on writes. */
	public static function authorize_platform( \WP_REST_Request $request, bool $write ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'wotoyi_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
		}
		if ( ! current_user_can( Roles::PLATFORM_CAP ) ) {
			return new \WP_Error( 'wotoyi_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}
		return $write ? self::check_nonce( $request ) : true;
	}

	/** Any logged-in user with at least one membership (profile endpoint). */
	public static function authorize_member( \WP_REST_Request $request ): bool|\WP_Error {
		return self::authorize( $request, 'dashboard.read', false ) ;
	}

	/** @return array{tenant:array<string,mixed>,member:array<string,mixed>}|null Context stored by authorize(). */
	public static function context( \WP_REST_Request $request ): ?array {
		return self::$contexts[ $request ] ?? null;
	}

	/** @return array{tenant:array<string,mixed>,member:array<string,mixed>}|\WP_Error */
	private static function resolve( \WP_REST_Request $request ): array|\WP_Error {
		$memberships = Directory::memberships_for_user( get_current_user_id() );
		if ( ! $memberships ) {
			return new \WP_Error( 'wotoyi_no_tenant', 'No dealer account is linked to this user.', array( 'status' => 403 ) );
		}
		$wanted = (string) $request->get_header( 'x_wotoyi_tenant' );
		if ( '' !== $wanted ) {
			foreach ( $memberships as $m ) {
				if ( hash_equals( (string) $m['tenant']['uuid'], $wanted ) ) {
					return $m;
				}
			}
			return new \WP_Error( 'wotoyi_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) ); // Same answer for unknown and foreign tenants.
		}
		if ( count( $memberships ) > 1 ) {
			return new \WP_Error( 'wotoyi_tenant_required', 'Several dealer accounts: send the X-Wotoyi-Tenant header.', array( 'status' => 400 ) );
		}
		return $memberships[0];
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
