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

		return $out;
	}

	/** Email that receives contact requests (falls back to the site admin email). */
	public static function contact_email() {
		$email = self::get( 'contact_email' );
		return $email ? $email : get_option( 'admin_email' );
	}
}
