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

	/** Names that resolve to loopback or private space by design (wildcard DNS services and reserved suffixes). */
	private const BLOCKED_SUFFIXES = array( 'localhost', 'local', 'internal', 'localdomain', 'lan', 'intranet', 'corp', 'private', 'home.arpa', 'localtest.me', 'lvh.me', 'vcap.me', 'nip.io', 'sslip.io', 'xip.io', 'traefik.me' );

	/** @var (callable(string):list<string>)|null */
	private static $resolver = null;

	/** Test hook: replace DNS resolution (host => list of IPs). */
	public static function set_resolver( ?callable $resolver ): void {
		self::$resolver = $resolver;
	}

	/** @return list<string> */
	private static function resolve( string $host ): array {
		if ( null !== self::$resolver ) {
			return array_values( (array) ( self::$resolver )( $host ) );
		}
		$ips = array();
		foreach ( (array) @dns_get_record( $host, DNS_A | DNS_AAAA ) as $rec ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$ip = $rec['ip'] ?? $rec['ipv6'] ?? null;
			if ( is_string( $ip ) ) {
				$ips[] = $ip;
			}
		}
		return $ips;
	}

	/** True only for a globally routable IPv4/IPv6 address. IPv4-in-IPv6 forms (mapped, compatible, NAT64, 6to4) are judged by the embedded IPv4. */
	public static function ip_is_public( string $ip ): bool {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $bin ) {
			return false;
		}
		if ( 4 === strlen( $bin ) ) {
			return self::v4_is_public( ( unpack( 'N', $bin ) )[1] );
		}
		$b = array_values( unpack( 'C16', $bin ) );
		if ( 0 === array_sum( array_slice( $b, 0, 10 ) ) && ( ( 255 === $b[10] && 255 === $b[11] ) || ( 0 === $b[10] && 0 === $b[11] ) ) ) {
			return self::v4_is_public( ( unpack( 'N', substr( $bin, 12, 4 ) ) )[1] ); // ::ffff:a.b.c.d, ::a.b.c.d (and :: / ::1 as 0.0.0.x).
		}
		if ( "\x00\x64\xff\x9b" . str_repeat( "\0", 8 ) === substr( $bin, 0, 12 ) ) {
			return self::v4_is_public( ( unpack( 'N', substr( $bin, 12, 4 ) ) )[1] ); // 64:ff9b::/96 NAT64.
		}
		if ( 0x20 === $b[0] && 0x02 === $b[1] ) {
			return self::v4_is_public( ( unpack( 'N', substr( $bin, 2, 4 ) ) )[1] ); // 2002::/16 6to4.
		}
		if ( ( $b[0] & 0xe0 ) !== 0x20 ) {
			return false; // Only global unicast 2000::/3 (excludes ::1, fc00::/7, fe80::/10, ff00::/8, ...).
		}
		if ( 0x20 === $b[0] && 0x01 === $b[1] && ( 0x0d === $b[2] && 0xb8 === $b[3] || 0x00 === $b[2] && 0x00 === $b[3] ) ) {
			return false; // 2001:db8::/32 documentation, 2001::/32 Teredo.
		}
		return true;
	}

	private static function v4_is_public( int $n ): bool {
		$blocked = array(
			array( '0.0.0.0', 8 ), array( '10.0.0.0', 8 ), array( '100.64.0.0', 10 ), array( '127.0.0.0', 8 ), array( '169.254.0.0', 16 ),
			array( '172.16.0.0', 12 ), array( '192.0.0.0', 24 ), array( '192.0.2.0', 24 ), array( '192.88.99.0', 24 ), array( '192.168.0.0', 16 ),
			array( '198.18.0.0', 15 ), array( '198.51.100.0', 24 ), array( '203.0.113.0', 24 ), array( '224.0.0.0', 4 ), array( '240.0.0.0', 4 ),
		);
		foreach ( $blocked as [ $net, $bits ] ) {
			$mask = ( 0xFFFFFFFF << ( 32 - $bits ) ) & 0xFFFFFFFF;
			if ( ( $n & $mask ) === ( ip2long( $net ) & $mask ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Marketplace base URL (SECURITY-REVIEW M1): https only, port 443, no credentials/query/fragment, a real public DNS
	 * name or canonical public IP. Rejected: decimal/hex/octal/short IPv4 forms (2130706433, 0x7f000001, 127.1), IPv4-in-
	 * IPv6, single-label hosts, localhost and reserved or loopback-DNS suffixes, and any name that resolves to a private
	 * address. When WOTOYI_DP_MARKETPLACE_URL is defined the origin must equal it. DNS rebinding between this check and
	 * the request is mitigated, not eliminated, by wp_safe_remote_request(); pin the constant in production.
	 *
	 * @throws ApiException
	 */
	public static function validate_base_url( string $url ): string {
		$bad    = static fn(): ApiException => ApiException::invalid( 'The marketplace URL is not acceptable.', array( 'fields' => array( 'base_url' ) ) );
		$pinned = Settings::pinned_marketplace_url();
		$url    = trim( $url );
		if ( '' === $url && null !== $pinned ) {
			$url = $pinned;
		}
		if ( '' === $url || 1 === preg_match( '/[\x00-\x20\x7f\\]/', $url ) ) {
			throw $bad();
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			throw $bad();
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = rtrim( strtolower( (string) $parts['host'] ), '.' );
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			throw $bad();
		}
		$insecure = Settings::allow_insecure_marketplace();
		if ( 'https' !== $scheme && ! ( $insecure && 'http' === $scheme ) ) {
			throw ApiException::invalid( 'The marketplace URL must use https.', array( 'fields' => array( 'base_url' ) ) );
		}
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : null;
		if ( ! $insecure && null !== $port && 443 !== $port ) {
			throw $bad();
		}
		$path = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		if ( '' !== $path && ( 1 !== preg_match( '#^(/[A-Za-z0-9._~-]+)+$#', $path ) || 1 === preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) ) {
			throw $bad();
		}
		if ( ! $insecure ) {
			self::assert_public_host( $host, $bad );
		}
		$origin = $scheme . '://' . $host . ( null !== $port && ! ( 'https' === $scheme && 443 === $port ) ? ':' . $port : '' );
		if ( null !== $pinned ) {
			$pp = (array) wp_parse_url( $pinned );
			$po = strtolower( (string) ( $pp['scheme'] ?? '' ) ) . '://' . rtrim( strtolower( (string) ( $pp['host'] ?? '' ) ), '.' ) . ( isset( $pp['port'] ) && 443 !== (int) $pp['port'] ? ':' . (int) $pp['port'] : '' );
			if ( ! hash_equals( $po, $origin ) ) {
				throw $bad();
			}
		}
		return $origin . $path;
	}

	/** @param callable():ApiException $bad */
	private static function assert_public_host( string $host, callable $bad ): void {
		if ( 1 === preg_match( '/^\[(.+)\]$/', $host, $m ) ) { // IPv6 literal.
			if ( false === filter_var( $m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) || ! self::ip_is_public( $m[1] ) ) {
				throw $bad();
			}
			return;
		}
		if ( 1 !== preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host ) || strlen( $host ) > 253 ) {
			throw $bad();
		}
		$last = substr( $host, (int) strrpos( '.' . $host, '.' ) );
		if ( 1 === preg_match( '/^(0x[0-9a-f]*|[0-9]+)$/', $last ) ) { // Numeric last label: an IPv4 literal in some form (a TLD is never numeric).
			if ( false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || ! self::ip_is_public( $host ) ) { // Only canonical dotted quads pass.
				throw $bad();
			}
			return;
		}
		if ( false === strpos( $host, '.' ) ) {
			throw $bad(); // localhost, intranet names.
		}
		foreach ( self::BLOCKED_SUFFIXES as $suffix ) {
			if ( $host === $suffix || str_ends_with( $host, '.' . $suffix ) ) {
				throw $bad();
			}
		}
		$ips = self::resolve( $host );
		if ( ! $ips ) {
			throw $bad();
		}
		foreach ( $ips as $ip ) {
			if ( ! self::ip_is_public( $ip ) ) {
				throw $bad();
			}
		}
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
		// One generic answer for every remote failure: the upstream status, error text and timing detail would turn
		// this endpoint into a blind SSRF probe (M1). Detail stays in the server log (no secrets, no body).
		$fail = static function ( string $why ) use ( $base ): ApiException {
			error_log( 'wotoyi-dealer-platform pairing failed (' . $why . ') host=' . (string) wp_parse_url( $base, PHP_URL_HOST ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new ApiException( 'wotoyi_pairing_failed', 'Pairing failed. Check the pairing code and the marketplace address, or ask the marketplace operator.', 422 );
		};
		if ( is_wp_error( $res ) ) {
			throw $fail( 'transport' );
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$data   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$data   = is_array( $data ) ? $data : array();
		if ( 201 !== $status && 200 !== $status ) {
			throw $fail( 'http ' . $status );
		}
		$key_id = $data['key_id'] ?? null;
		$secret = $data['secret'] ?? null;
		if ( ! is_string( $key_id ) || 1 !== preg_match( '/^wk_[a-f0-9]{16}$/', $key_id ) || ! is_string( $secret ) || strlen( $secret ) < 16 ) {
			throw $fail( 'unexpected response' );
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
