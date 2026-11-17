<?php
/**
 * Pairing flow: an administrator issues a single-use, short-lived code for a dealer; the Dealer Platform
 * posts it once and receives its key id + secret (shown only in that response).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

use Wotoyi\Marketplace\Repository\DealerRepository;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class Pairing {

	private const FAIL_LIMIT_IP     = 5;   // Failed attempts per IP per 15 minutes.
	private const FAIL_LIMIT_GLOBAL = 100; // Failed attempts overall per hour (distributed guessing).

	public function __construct( private DealerRepository $dealers ) {}

	/**
	 * @param array<string,mixed> $input {code, source_dealer_uuid?, name?, ...profile}
	 * @return array<string,mixed>|\WP_Error
	 */
	public function pair( array $input, string $client_ip ): array|\WP_Error {
		$ip_bucket = 'pairfail:' . Ip::hash( $client_ip );
		if ( RateLimiter::blocked( $ip_bucket, self::FAIL_LIMIT_IP, 900 ) || RateLimiter::blocked( 'pairfail:global', self::FAIL_LIMIT_GLOBAL, 3600 ) ) {
			return new \WP_Error( 'wotoyi_rate_limited', 'Too many pairing attempts. Try again later.', array( 'status' => 429 ) );
		}

		$code   = Crypto::normalize_pairing_code( is_string( $input['code'] ?? null ) ? $input['code'] : '' );
		$dealer = '' !== $code ? $this->dealers->find_by_pairing_code( $code ) : null;
		if ( ! $dealer || 'suspended' === $dealer['status'] || 'local' === $dealer['transport'] ) {
			return $this->fail( $ip_bucket );
		}

		$source = $input['source_dealer_uuid'] ?? null;
		$source = is_string( $source ) && 1 === preg_match( '/^[A-Za-z0-9_.:\-]{8,64}$/', $source ) ? $source : null;
		if ( ! empty( $dealer['source_dealer_uuid'] ) && null !== $source && $source !== $dealer['source_dealer_uuid'] ) {
			return $this->fail( $ip_bucket ); // Code was issued for another dealer identity.
		}
		if ( null === $source && empty( $dealer['source_dealer_uuid'] ) ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'source_dealer_uuid is required.', array( 'status' => 422 ) );
		}
		if ( null !== $source && empty( $dealer['source_dealer_uuid'] ) && $this->dealers->find_by_source_uuid( $source ) ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'This dealer identity is already linked.', array( 'status' => 409 ) );
		}

		if ( ! $this->dealers->consume_pairing_code( (int) $dealer['id'], $code ) ) {
			return $this->fail( $ip_bucket ); // Lost a race: code already used.
		}

		$creds = $this->dealers->issue_credentials( (int) $dealer['id'], $source );

		$profile = array_intersect_key( $input, DealerRepository::profile_defs() );
		unset( $profile['name'] ); // The marketplace operator owns the dealer name.
		if ( $profile ) {
			$this->dealers->update_profile( (int) $dealer['id'], $profile );
		}

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

	private function fail( string $ip_bucket ): \WP_Error {
		RateLimiter::record( $ip_bucket, 900 );
		RateLimiter::record( 'pairfail:global', 3600 );
		return new \WP_Error( 'wotoyi_invalid_pairing_code', 'Invalid or expired pairing code.', array( 'status' => 403 ) );
	}
}
