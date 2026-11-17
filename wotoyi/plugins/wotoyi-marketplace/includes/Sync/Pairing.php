<?php
/**
 * Pairing flow: an administrator issues a single-use, short-lived code for a dealer; the Dealer Platform
 * posts it once and receives its key id + secret (shown only in that response).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Repository\DealerRepository;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class Pairing {

	private const FAIL_LIMIT_IP     = 5;   // Failed attempts per IP per 15 minutes.
	private const FAIL_LIMIT_GLOBAL = 100; // MISSED codes overall per hour (distributed guessing); never blocks a matching code.

	public function __construct( private DealerRepository $dealers ) {}

	/**
	 * @param array<string,mixed> $input {code, source_dealer_uuid?, name?, ...profile}
	 * @return array<string,mixed>|\WP_Error
	 */
	public function pair( array $input, string $client_ip ): array|\WP_Error {
		$ip_bucket = 'pairfail:' . Ip::hash( $client_ip );
		if ( RateLimiter::blocked( $ip_bucket, self::FAIL_LIMIT_IP, 900 ) ) {
			return $this->limited();
		}

		$code   = Crypto::normalize_pairing_code( is_string( $input['code'] ?? null ) ? $input['code'] : '' );
		$dealer = '' !== $code ? $this->dealers->find_by_pairing_code( $code ) : null;
		if ( ! $dealer || 'suspended' === $dealer['status'] || 'local' === $dealer['transport'] ) {
			// M3: the global distributed-guessing guard only ever stops MISSES. A code that matches a live record is
			// never blocked by it, so an attacker cannot lock every dealer out of onboarding with junk codes.
			if ( RateLimiter::blocked( 'pairfail:global', self::FAIL_LIMIT_GLOBAL, 3600 ) ) {
				return $this->limited();
			}
			return $this->fail( $ip_bucket );
		}

		$source = $input['source_dealer_uuid'] ?? null;
		$source = is_string( $source ) && 1 === preg_match( '/^[A-Za-z0-9_.:\-]{8,64}$/', $source ) ? $source : null;
		$bound  = ! empty( $dealer['source_dealer_uuid'] );
		// L1: a dealer record bound to a Dealer Platform identity requires that exact identity. Omitting it is not a bypass.
		if ( $bound && $source !== $dealer['source_dealer_uuid'] ) {
			return $this->fail_ip( $ip_bucket ); // Code was issued for another dealer identity (or none was presented).
		}
		if ( ! $bound && null === $source ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'source_dealer_uuid is required.', array( 'status' => 422 ) );
		}
		if ( ! $bound && $this->dealers->find_by_source_uuid( (string) $source ) ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'This dealer identity is already linked.', array( 'status' => 409 ) );
		}

		if ( ! $this->dealers->consume_pairing_code( (int) $dealer['id'], $code ) ) {
			return $this->fail_ip( $ip_bucket ); // Lost a race: code already used.
		}

		$creds = $this->dealers->issue_credentials( (int) $dealer['id'], $source );

		// L1: the public profile is owned by the marketplace operator. Pairing may only FILL fields that are still empty
		// (it can no longer overwrite website/logo/phone with values from whoever intercepted the code).
		$profile = $this->dealers->sanitize_profile( array_diff_key( array_intersect_key( $input, DealerRepository::profile_defs() ), array( 'name' => 1 ) ) );
		$fill    = array();
		foreach ( $profile as $field => $value ) {
			if ( null !== $value && ( null === ( $dealer[ $field ] ?? null ) || '' === $dealer[ $field ] ) ) {
				$fill[ $field ] = $value;
			}
		}
		if ( $fill ) {
			$this->dealers->update_profile( (int) $dealer['id'], $fill );
		}
		Plugin::audit()->record( 'dealer', 0, 'pairing.success', (int) $dealer['id'], array( 'status' => array( $dealer['status'], 'active' ), 'profile_fields' => array_keys( $fill ), 'source_identity_bound' => $bound ) );

		return array(
			'dealer_id'   => (string) $dealer['uuid'],
			'key_id'      => $creds['key_id'],
			'secret'      => $creds['secret'], // Returned once, never again.
			'api_version' => Receiver::API_VERSION,
			'server_time' => Clock::now(),
			'limits'      => array(
				'max_body_bytes' => Settings::int( 'max_body_bytes' ),
				'max_batch'      => Receiver::MAX_BATCH,
				'clock_skew'     => Settings::int( 'clock_skew' ),
			),
		);
	}

	private function limited(): \WP_Error {
		return new \WP_Error( 'wotoyi_rate_limited', 'Too many pairing attempts. Try again later.', array( 'status' => 429 ) );
	}

	/** Wrong identity / lost race with a real code: count against this IP only. */
	private function fail_ip( string $ip_bucket ): \WP_Error {
		RateLimiter::record( $ip_bucket, 900 );
		return new \WP_Error( 'wotoyi_invalid_pairing_code', 'Invalid or expired pairing code.', array( 'status' => 403 ) );
	}

	private function fail( string $ip_bucket ): \WP_Error {
		RateLimiter::record( $ip_bucket, 900 );
		RateLimiter::record( 'pairfail:global', 3600 );
		return new \WP_Error( 'wotoyi_invalid_pairing_code', 'Invalid or expired pairing code.', array( 'status' => 403 ) );
	}
}
