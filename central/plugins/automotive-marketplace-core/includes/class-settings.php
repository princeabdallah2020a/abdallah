<?php
/**
 * Platform settings (single option, typed defaults, sanitisation).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION = 'amc_settings';

	public static function defaults() {
		return array(
			'default_currency'    => 'XOF',
			'default_city'        => 'Dakar',
			'default_country'     => 'SN',
			'per_page'            => 12,
			// Freshness policy for listings synchronised from dealer websites.
			'stale_after_hours'   => 72,   // Flag listing as "availability to confirm".
			'hide_after_hours'    => 168,  // Remove from public results.
			// Canonical policy: "self" (marketplace page) or "dealer" (dealer website page).
			'canonical_policy'    => 'self',
			'contact_email'       => '',
			'company_phone'       => '',
			'company_whatsapp'    => '',
			'company_address'     => '',
			'api_rate_limit'      => 120,  // Public API requests per minute and per IP.
			'delete_on_uninstall' => 0,
			// AI assistant.
			'ai_visitor_enabled'  => 1,
			'ai_model'            => 'claude-opus-5-5',
			'ai_api_key'          => '',
			'ai_daily_cap'        => 500,  // Max AI requests per day, all users (0 = no cap).
			// Memberships.
			'registration_open'     => 1,
			'require_documents'     => 0,
			'grace_days'            => 5,
			'provisioning_provider' => 'manual',
			'pay_wave_details'      => '',
			'pay_om_details'        => '',
			'pay_bank_details'      => '',
			'pay_cash_details'      => '',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitise raw input (used as the register_setting sanitize callback).
	 *
	 * @param array $input Raw values.
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::all();

		if ( isset( $input['default_currency'] ) ) {
			$currency = strtoupper( sanitize_text_field( $input['default_currency'] ) );
			if ( isset( Catalog::currencies()[ $currency ] ) ) {
				$out['default_currency'] = $currency;
			}
		}
		foreach ( array( 'default_city', 'company_phone', 'company_whatsapp', 'company_address' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}
		if ( isset( $input['default_country'] ) ) {
			$out['default_country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $input['default_country'] ), 0, 2 ) );
		}
		if ( isset( $input['contact_email'] ) ) {
			$out['contact_email'] = sanitize_email( $input['contact_email'] );
		}
		if ( isset( $input['per_page'] ) ) {
			$out['per_page'] = min( 48, max( 6, absint( $input['per_page'] ) ) );
		}
		if ( isset( $input['stale_after_hours'] ) ) {
			$out['stale_after_hours'] = max( 1, absint( $input['stale_after_hours'] ) );
		}
		if ( isset( $input['hide_after_hours'] ) ) {
			$out['hide_after_hours'] = max( $out['stale_after_hours'], absint( $input['hide_after_hours'] ) );
		}
		if ( isset( $input['api_rate_limit'] ) ) {
			$out['api_rate_limit'] = min( 2000, max( 10, absint( $input['api_rate_limit'] ) ) );
		}
		if ( isset( $input['canonical_policy'] ) ) {
			$out['canonical_policy'] = 'dealer' === $input['canonical_policy'] ? 'dealer' : 'self';
		}
		$out['delete_on_uninstall'] = empty( $input['delete_on_uninstall'] ) ? 0 : 1;
		$out['ai_visitor_enabled']  = empty( $input['ai_visitor_enabled'] ) ? 0 : 1;
		if ( isset( $input['ai_model'] ) && isset( Assistant::models()[ $input['ai_model'] ] ) ) {
			$out['ai_model'] = $input['ai_model'];
		}
		if ( isset( $input['ai_daily_cap'] ) ) {
			$out['ai_daily_cap'] = min( 100000, absint( $input['ai_daily_cap'] ) );
		}
		// The key field is never pre-filled: empty means "keep the saved key".
		if ( ! empty( $input['ai_api_key'] ) ) {
			$out['ai_api_key'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input['ai_api_key'] );
		}
		if ( ! empty( $input['ai_api_key_delete'] ) ) {
			$out['ai_api_key'] = '';
		}
		// Membership settings (only present in the platform admin form).
		foreach ( array( 'registration_open', 'require_documents' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}
		if ( isset( $input['grace_days'] ) ) {
			$out['grace_days'] = min( 60, absint( $input['grace_days'] ) );
		}
		foreach ( array( 'pay_wave_details', 'pay_om_details', 'pay_bank_details', 'pay_cash_details' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, 300 );
			}
		}
		if ( isset( $input['provisioning_provider'] ) ) {
			$out['provisioning_provider'] = sanitize_key( $input['provisioning_provider'] );
		}

		return $out;
	}

	/** Email that receives contact requests (falls back to the site admin email). */
	public static function contact_email() {
		$email = self::get( 'contact_email' );
		return $email ? $email : get_option( 'admin_email' );
	}
}
