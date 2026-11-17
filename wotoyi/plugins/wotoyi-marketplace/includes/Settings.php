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
			'audit_retention_days' => 365,    // Audit log age limit (0 = keep forever).
			// Buyer personal data (H2). 0 disables a rule. Legal must confirm the values for Senegal law 2008-12 / GDPR.
			'lead_retention_delivered_days'    => 30, // Delivered leads: name/phone/email/message/ip_hash/user_id are erased after N days.
			'lead_retention_undelivered_days'  => 90, // Leads the dealer never pulled: deleted after N days.
			'lead_retention_disconnected_days' => 30, // Leads of a disconnected dealer: deleted N days after disconnection.
			'lead_consent_version' => 'v1',   // Version label of the consent text shown with the form (client may send its own).
			'lead_dealer_daily_cap'   => 200, // Leads per dealer per rolling day (fixed 24 h window); 0 = unlimited.
			'lead_contact_daily_cap'  => 5,   // Leads per phone/email per day across the marketplace; 0 = unlimited.
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
