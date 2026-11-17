<?php
/**
 * Client IP helpers. Only REMOTE_ADDR is trusted unless a filter says otherwise
 * (behind a proxy/CDN, hook `wotoyi_mp_client_ip`).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class Ip {

	public static function client(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'wotoyi_mp_client_ip', $ip );
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Abuse-limiting bucket of an address: IPv4 as is, IPv4-mapped IPv6 as IPv4, other IPv6 truncated to its /64
	 * (one subscriber controls a whole /64, so per-address limits are meaningless: SECURITY-REVIEW M2).
	 */
	public static function bucket( string $ip ): string {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $bin || 16 !== strlen( $bin ) ) {
			return $ip;
		}
		if ( str_starts_with( $bin, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
			return (string) inet_ntop( substr( $bin, 12 ) );
		}
		return (string) inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}

	/**
	 * Keyed hash of the IP bucket: usable for abuse limiting, not reversible to a raw address
	 * (IPv6 addresses are hashed as their /64, which is also what gets stored on leads).
	 */
	public static function hash( string $ip ): string {
		return Crypto::keyed_hash( 'ip', self::bucket( $ip ) );
	}
}
