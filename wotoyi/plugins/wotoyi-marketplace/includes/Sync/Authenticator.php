<?php
/**
 * Verifies signed sync requests: format, timestamp window, key lookup, HMAC (constant time), nonce replay,
 * dealer status, body size and rate limits. Used as `permission_callback` of every sync route.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

use Wotoyi\Marketplace\Repository\DealerRepository;
use Wotoyi\Marketplace\Repository\NonceRepository;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\RateLimiter;
use Wotoyi\Marketplace\Support\Tls;

defined( 'ABSPATH' ) || exit;

final class Authenticator {

	/** Authenticated dealer per request object; not spoofable through request parameters. */
	private static ?\WeakMap $dealers = null;

	public function __construct( private DealerRepository $dealers_repo, private NonceRepository $nonces ) {}

	/** Routes that only the CURRENT secret may call (H1): they change or end the credentials themselves. */
	private const CURRENT_SECRET_ONLY = array( '/sync/rotate-key', '/sync/disconnect' );

	/** REST permission callback. @return true|\WP_Error */
	public function permission( \WP_REST_Request $request ): bool|\WP_Error {
		// M4: the signed API (and the responses that carry secrets) is HTTPS only.
		$secure = Tls::require_secure_request();
		if ( is_wp_error( $secure ) ) {
			return $secure;
		}
		// M1: the signature covers method, route and body but NOT the query string, and WordPress resolves
		// parameters JSON -> POST -> query -> URL. Any unsigned query value could shadow a signed one: refuse them.
		$allowed = (array) apply_filters( 'wotoyi_mp_sync_allowed_query_params', array( 'rest_route' ) ); // `rest_route` = plain-permalink sites.
		if ( array_diff_key( (array) $request->get_query_params(), array_flip( $allowed ) ) ) {
			return $this->error( 'wotoyi_query_not_allowed', 'Query parameters are not allowed on signed sync routes.', 400 );
		}

		$result = $this->verify(
			(string) $request->get_header( 'x_wotoyi_key' ),
			(string) $request->get_header( 'x_wotoyi_timestamp' ),
			(string) $request->get_header( 'x_wotoyi_nonce' ),
			(string) $request->get_header( 'x_wotoyi_signature' ),
			$request->get_method(),
			$request->get_route(),
			(string) $request->get_body()
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// H1: a superseded (grace-period) secret may keep syncing content, but can never rotate or revoke credentials.
		if ( 'current' !== ( $result['_auth_slot'] ?? '' ) && $this->requires_current_secret( $request->get_route() ) ) {
			return $this->error( 'wotoyi_secret_superseded', 'This secret has been rotated; use the current secret.', 401 );
		}
		self::$dealers ??= new \WeakMap();
		self::$dealers[ $request ] = $result;
		return true;
	}

	private function requires_current_secret( string $route ): bool {
		foreach ( self::CURRENT_SECRET_ONLY as $suffix ) {
			if ( str_ends_with( rtrim( $route, '/' ), $suffix ) ) {
				return true;
			}
		}
		return false;
	}

	/** Dealer authenticated for this request by permission(), or null. */
	public static function dealer( \WP_REST_Request $request ): ?array {
		return self::$dealers[ $request ] ?? null;
	}

	/**
	 * Core verification (pure: no WP_REST_Request needed).
	 *
	 * @return array<string,mixed>|\WP_Error Dealer row on success.
	 */
	public function verify( string $key_id, string $timestamp, string $nonce, string $signature, string $method, string $path, string $body ): array|\WP_Error {
		$ip_bucket = 'authfail:' . Ip::hash( Ip::client() );
		if ( RateLimiter::blocked( $ip_bucket, 30, 60 ) ) {
			return $this->error( 'wotoyi_rate_limited', 'Too many failed attempts.', 429 );
		}

		if ( '' === $key_id || '' === $timestamp || '' === $nonce || '' === $signature ) {
			return $this->fail( $ip_bucket, 'wotoyi_auth_missing', 'Missing authentication headers.', 401 );
		}
		if ( 1 !== preg_match( '/^wk_[a-f0-9]{16}$/', $key_id )
			|| 1 !== preg_match( '/^\d{10}$/', $timestamp )
			|| 1 !== preg_match( '/^[A-Za-z0-9_\-]{16,64}$/', $nonce )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return $this->fail( $ip_bucket, 'wotoyi_auth_malformed', 'Malformed authentication headers.', 401 );
		}
		if ( strlen( $body ) > Settings::int( 'max_body_bytes' ) ) {
			return $this->error( 'wotoyi_body_too_large', 'Request body too large.', 413 );
		}
		if ( abs( Clock::now() - (int) $timestamp ) > Settings::int( 'clock_skew' ) ) {
			return $this->fail( $ip_bucket, 'wotoyi_timestamp_skew', 'Timestamp outside the accepted window.', 401 );
		}
		$dealer = $this->dealers_repo->find_by_key_id( $key_id );
		$slot   = null; // Which secret matched: 'current' | 'previous'.
		if ( $dealer && 'http' === $dealer['transport'] ) {
			foreach ( $this->dealers_repo->accepted_secrets_by_slot( $dealer ) as $name => $secret ) {
				if ( hash_equals( Signer::signature( $secret, $timestamp, $nonce, $method, $path, $body ), $signature ) ) {
					$slot = $name;
					break;
				}
			}
		}
		if ( ! $dealer || null === $slot ) {
			return $this->fail( $ip_bucket, 'wotoyi_auth_failed', 'Invalid credentials.', 401 ); // Same answer for unknown key and bad signature.
		}

		// M3: the per-key limit is applied only AFTER the signature is proven, so forged traffic naming a real key id
		// can never exhaust a legitimate dealer's budget (unauthenticated callers are limited per IP above).
		if ( ! RateLimiter::allow( 'sync:' . $key_id, Settings::int( 'sync_rate_limit' ), 60 ) ) {
			return $this->error( 'wotoyi_rate_limited', 'Rate limit exceeded.', 429 );
		}

		if ( ! $this->nonces->consume( (int) $dealer['id'], $nonce ) ) {
			return $this->fail( $ip_bucket, 'wotoyi_replay', 'Nonce already used.', 401 );
		}
		if ( 'active' !== $dealer['status'] ) {
			return $this->error( 'wotoyi_dealer_inactive', 'Dealer account is not active.', 403 );
		}

		$this->dealers_repo->touch_seen( $dealer );
		$dealer['_auth_slot'] = $slot; // Not a DB column: never persist this row as is.
		return $dealer;
	}

	private function fail( string $ip_bucket, string $code, string $message, int $status ): \WP_Error {
		RateLimiter::record( $ip_bucket, 60 );
		return $this->error( $code, $message, $status );
	}

	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
