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

	/** Keyed hash of the IP: usable for abuse limiting, not reversible to a raw address. */
	public static function hash( string $ip ): string {
		return Crypto::keyed_hash( 'ip', $ip );
	}
}
