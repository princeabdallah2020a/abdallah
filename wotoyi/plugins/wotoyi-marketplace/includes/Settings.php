<?php
/**
 * Plugin settings (option `wotoyi_mp_settings`) with constant overrides for security-relevant switches.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'wotoyi_mp_settings';

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'local_mode'          => false,   // Single-install demo: direct function calls, no HTTP (see LocalTransport).
			'clock_skew'          => 300,     // Seconds; HMAC timestamp tolerance.
			'key_grace'           => 600,     // Seconds the previous secret stays valid after rotation.
			'pairing_ttl'         => 900,     // Seconds a pairing code lives.
			'max_body_bytes'      => 1048576, // Signed request body limit.
			'public_rate_limit'   => 120,     // Public reads per IP per minute.
			'lead_rate_limit'     => 5,       // Lead submissions per IP per 10 minutes.
			'sync_rate_limit'     => 240,     // Signed requests per dealer key per minute.
			'log_retention_days'  => 90,
			'delete_on_uninstall' => false,
		);
	}

	public static function get( string $key ): mixed {
		if ( 'local_mode' === $key && defined( 'WOTOYI_MP_LOCAL_MODE' ) ) {
			return (bool) WOTOYI_MP_LOCAL_MODE;
		}
		$stored   = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		$value    = is_array( $stored ) && array_key_exists( $key, $stored ) ? $stored[ $key ] : ( $defaults[ $key ] ?? null );
		return apply_filters( 'wotoyi_mp_setting_' . $key, $value );
	}

	public static function int( string $key ): int {
		return (int) self::get( $key );
	}

	public static function nonce_ttl(): int {
		return self::int( 'clock_skew' ) * 2 + 60;
	}
}
