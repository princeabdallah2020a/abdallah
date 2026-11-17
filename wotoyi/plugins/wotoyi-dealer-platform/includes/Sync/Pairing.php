<?php
/**
 * Dealer-side pairing (PROJECT.md §13.6): the marketplace operator issues a one-time code (WTY-XXXX-XXXX-XXXX,
 * 15 minutes, single use); the dealer enters it here together with the marketplace URL. We POST it once to
 * /sync/pair, receive key_id + secret, and store the secret ENCRYPTED (libsodium, key from WOTOYI_SECRET_KEY).
 * Pairing is refused when WOTOYI_SECRET_KEY is missing.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Service\AuditLog;
use Wotoyi\DealerPlatform\Settings;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class Pairing {

	/**
	 * Marketplace base URL: https, no credentials/query/fragment, public host (unless insecure mode is pinned by constant).
	 *
	 * @throws ApiException
	 */
	public static function validate_base_url( string $url ): string {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			throw ApiException::invalid( 'Invalid marketplace URL.', array( 'fields' => array( 'base_url' ) ) );
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			throw ApiException::invalid( 'The URL must not contain credentials, a query or a fragment.', array( 'fields' => array( 'base_url' ) ) );
		}
		$insecure = Settings::allow_insecure_marketplace();
		if ( 'https' !== $scheme && ! ( $insecure && 'http' === $scheme ) ) {
			throw ApiException::invalid( 'The marketplace URL must use https.', array( 'fields' => array( 'base_url' ) ) );
		}
		$ip = filter_var( trim( $host, '[]' ), FILTER_VALIDATE_IP );
		if ( ! $insecure && ( 'localhost' === $host || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) || ( false !== $ip && false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) ) {
			throw ApiException::invalid( 'The marketplace host must be a public address.', array( 'fields' => array( 'base_url' ) ) );
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		return $scheme . '://' . $host . $port . $path;
	}

	/**
	 * @return array<string,mixed> Safe sync state.
	 * @throws ApiException
	 */
	public static function pair_http( string $code, string $base_url ): array {
		if ( ! Crypto::key_available() ) {
			throw new ApiException( 'wotoyi_secret_key_missing', 'Define WOTOYI_SECRET_KEY (32+ random characters) in wp-config.php before pairing.', 503 );
		}
		$normalized = Crypto::normalize_pairing_code( $code );
		if ( '' === $normalized ) {
			throw ApiException::invalid( 'The pairing code must look like WTY-XXXX-XXXX-XXXX.', array( 'fields' => array( 'code' ) ) );
		}
		$base   = self::validate_base_url( $base_url );
		$tenant = TenantContext::tenant();
		$body   = (string) wp_json_encode(
			array_filter(
				array(
					'code'               => $normalized,
					'source_dealer_uuid' => $tenant['uuid'],
					'city'               => $tenant['city'],
					'country'            => $tenant['country'],
				),
				static fn( $v ): bool => null !== $v && '' !== $v
			)
		);
		$res = wp_safe_remote_request(
			$base . '/wp-json/wotoyi/v1/sync/pair',
			array(
				'method'              => 'POST',
				'timeout'             => 15,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => 65536,
				'headers'             => array( 'Content-Type' => 'application/json' ),
				'body'                => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			throw new ApiException( 'wotoyi_marketplace_unreachable', 'The marketplace could not be reached.', 502 );
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$data   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$data   = is_array( $data ) ? $data : array();
		if ( 403 === $status ) {
			throw new ApiException( 'wotoyi_pairing_failed', 'Invalid or expired pairing code.', 422 );
		}
		if ( 429 === $status ) {
			throw new ApiException( 'wotoyi_rate_limited', 'Too many pairing attempts. Try again later.', 429 );
		}
		if ( 201 !== $status && 200 !== $status ) {
			throw new ApiException( 'wotoyi_pairing_failed', 'The marketplace refused the pairing (HTTP ' . $status . ').', 502 );
		}
		$key_id = $data['key_id'] ?? null;
		$secret = $data['secret'] ?? null;
		if ( ! is_string( $key_id ) || 1 !== preg_match( '/^wk_[a-f0-9]{16}$/', $key_id ) || ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			throw new ApiException( 'wotoyi_pairing_failed', 'Unexpected response from the marketplace.', 502 );
		}
		SyncState::save(
			array(
				'status'                => 'paired',
				'transport'             => 'http',
				'base_url'              => $base,
				'key_id'                => $key_id,
				'secret_enc'            => Crypto::encrypt( $secret ),
				'marketplace_dealer_id' => isset( $data['dealer_id'] ) && is_string( $data['dealer_id'] ) ? substr( $data['dealer_id'], 0, 36 ) : null,
				'paired_at'             => Clock::mysql(),
				'last_error'            => null,
			)
		);
		AuditLog::record( 'sync.paired', 'sync', null, array( 'transport' => 'http', 'key_id' => $key_id, 'host' => (string) wp_parse_url( $base, PHP_URL_HOST ) ) );
		return SyncState::present();
	}

	/**
	 * Demo mode: register this tenant with the marketplace living in the same WordPress.
	 *
	 * @return array<string,mixed> Safe sync state.
	 * @throws ApiException
	 */
	public static function pair_local(): array {
		if ( ! Settings::get( 'local_mode' ) ) {
			throw new ApiException( 'wotoyi_local_mode_disabled', 'Local mode is off (set WOTOYI_DP_LOCAL_MODE).', 403 );
		}
		if ( ! LocalTransport::available() || ! function_exists( 'wotoyi_marketplace_local_register_dealer' ) ) {
			throw new ApiException( 'wotoyi_marketplace_missing', 'The marketplace plugin is not active on this site.', 409 );
		}
		$tenant = TenantContext::tenant();
		try {
			$dealer = \wotoyi_marketplace_local_register_dealer( (string) $tenant['uuid'], array_filter( array( 'name' => $tenant['name'], 'city' => $tenant['city'], 'country' => $tenant['country'] ) ) );
		} catch ( \Throwable $e ) {
			throw new ApiException( 'wotoyi_local_pairing_failed', 'The marketplace refused local registration: ' . $e->getMessage(), 409 );
		}
		SyncState::save(
			array(
				'status'                => 'paired',
				'transport'             => 'local',
				'base_url'              => null,
				'key_id'                => null,
				'secret_enc'            => null,
				'marketplace_dealer_id' => isset( $dealer['id'] ) ? (string) $dealer['id'] : null,
				'paired_at'             => Clock::mysql(),
				'last_error'            => null,
			)
		);
		AuditLog::record( 'sync.paired', 'sync', null, array( 'transport' => 'local' ) );
		return SyncState::present();
	}

	/** Forget the credentials locally (events stay queued). The marketplace operator can revoke the key on their side. */
	public static function disconnect(): void {
		SyncState::save( array( 'status' => 'disconnected', 'secret_enc' => null, 'key_id' => null, 'last_error' => null ) );
		AuditLog::record( 'sync.disconnected', 'sync' );
	}
}
