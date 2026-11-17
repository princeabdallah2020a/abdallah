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

defined( 'ABSPATH' ) || exit;

final class Authenticator {

	/** Authenticated dealer per request object; not spoofable through request parameters. */
	private static ?\WeakMap $dealers = null;

	public function __construct( private DealerRepository $dealers_repo, private NonceRepository $nonces ) {}

	/** REST permission callback. @return true|\WP_Error */
	public function permission( \WP_REST_Request $request ): bool|\WP_Error {
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
		self::$dealers ??= new \WeakMap();
		self::$dealers[ $request ] = $result;
		return true;
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
		if ( ! RateLimiter::allow( 'sync:' . $key_id, Settings::int( 'sync_rate_limit' ), 60 ) ) {
			return $this->error( 'wotoyi_rate_limited', 'Rate limit exceeded.', 429 );
		}

		$dealer = $this->dealers_repo->find_by_key_id( $key_id );
		$valid  = false;
		if ( $dealer && 'http' === $dealer['transport'] ) {
			foreach ( $this->dealers_repo->accepted_secrets( $dealer ) as $secret ) {
				if ( hash_equals( Signer::signature( $secret, $timestamp, $nonce, $method, $path, $body ), $signature ) ) {
					$valid = true;
					break;
				}
			}
		}
		if ( ! $dealer || ! $valid ) {
			return $this->fail( $ip_bucket, 'wotoyi_auth_failed', 'Invalid credentials.', 401 ); // Same answer for unknown key and bad signature.
		}

		if ( ! $this->nonces->consume( (int) $dealer['id'], $nonce ) ) {
			return $this->fail( $ip_bucket, 'wotoyi_replay', 'Nonce already used.', 401 );
		}
		if ( 'active' !== $dealer['status'] ) {
			return $this->error( 'wotoyi_dealer_inactive', 'Dealer account is not active.', 403 );
		}

		$this->dealers_repo->touch_seen( $dealer );
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
