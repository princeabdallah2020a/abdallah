<?php
/**
 * Business profile and website appearance, edited by the dealer from the
 * CRM (no coding, no wp-admin). The dealer theme reads everything here.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION = 'adc_settings';

	public static function defaults() {
		return array(
			// Business.
			'business_name'  => get_bloginfo( 'name' ),
			'tagline'        => '',
			'address'        => '',
			'city'           => 'Dakar',
			'country'        => 'SN',
			'phone'          => '',
			'whatsapp'       => '',
			'email'          => get_option( 'admin_email' ),
			'hours'          => '',
			'map_url'        => '',
			'currency'       => 'XOF',
			'facebook'       => '',
			'instagram'      => '',
			'lead_email'     => get_option( 'admin_email' ),
			// Website.
			'preset'         => 'classic',
			'color_primary'  => '',
			'color_accent'   => '',
			'logo_id'        => 0,
			'hero_image_id'  => 0,
			'hero_title'     => '',
			'hero_text'      => '',
			'about_text'     => '',
			'show_rentals'   => 1,
			'show_financing' => 0,
			'financing_text' => '',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	public static function presets() {
		return array(
			'classic' => array( __( 'Classic — navy and red', 'automotive-dealer-crm' ), '#0e1726', '#cf3e1a' ),
			'bold'    => array( __( 'Bold — black and yellow', 'automotive-dealer-crm' ), '#111111', '#f2b705' ),
			'elegant' => array( __( 'Elegant — green and gold', 'automotive-dealer-crm' ), '#13322b', '#b8893b' ),
			'ocean'   => array( __( 'Ocean — blue and turquoise', 'automotive-dealer-crm' ), '#0b2545', '#0f9d9a' ),
		);
	}

	/** Effective brand colours (custom colours override the preset). */
	public static function colors() {
		$s       = self::all();
		$presets = self::presets();
		$preset  = isset( $presets[ $s['preset'] ] ) ? $presets[ $s['preset'] ] : $presets['classic'];
		return array(
			'primary' => $s['color_primary'] ? $s['color_primary'] : $preset[1],
			'accent'  => $s['color_accent'] ? $s['color_accent'] : $preset[2],
		);
	}

	/**
	 * Save a group of settings from untrusted input.
	 *
	 * @param array $in Raw values.
	 */
	public static function save( array $in ) {
		$out    = self::all();
		$before = self::colors();
		$text = array( 'business_name', 'tagline', 'address', 'city', 'phone', 'whatsapp', 'hours', 'hero_title' );
		foreach ( $text as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, 191 );
			}
		}
		foreach ( array( 'phone', 'whatsapp' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = adc_normalize_phone( $in[ $key ] );
			}
		}
		foreach ( array( 'hero_text', 'about_text', 'financing_text' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = mb_substr( sanitize_textarea_field( (string) $in[ $key ] ), 0, 5000 );
			}
		}
		foreach ( array( 'email', 'lead_email' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = sanitize_email( (string) $in[ $key ] );
			}
		}
		foreach ( array( 'map_url', 'facebook', 'instagram' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = esc_url_raw( (string) $in[ $key ], array( 'http', 'https' ) );
			}
		}
		if ( isset( $in['country'] ) ) {
			$out['country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $in['country'] ), 0, 2 ) );
		}
		if ( isset( $in['currency'] ) && isset( Labels::currencies()[ $in['currency'] ] ) ) {
			$out['currency'] = $in['currency'];
		}
		if ( isset( $in['preset'] ) && isset( self::presets()[ $in['preset'] ] ) ) {
			$out['preset'] = $in['preset'];
		}
		// A colour left as it was keeps following the chosen design; a colour
		// actually changed by the dealer becomes a custom colour.
		foreach ( array( 'color_primary' => 'primary', 'color_accent' => 'accent' ) as $key => $role ) {
			if ( ! empty( $in['reset_colors'] ) ) {
				$out[ $key ] = '';
			} elseif ( isset( $in[ $key ] ) ) {
				$color = (string) sanitize_hex_color( $in[ $key ] );
				if ( $color && strtolower( $color ) !== strtolower( $before[ $role ] ) ) {
					$out[ $key ] = $color;
				}
			}
		}
		foreach ( array( 'show_rentals', 'show_financing' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
			}
		}
		foreach ( array( 'logo_id', 'hero_image_id' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = absint( $in[ $key ] );
			}
		}
		update_option( self::OPTION, $out );
		if ( $out['business_name'] ) {
			update_option( 'blogname', $out['business_name'] );
		}
		if ( isset( $in['tagline'] ) ) {
			update_option( 'blogdescription', $out['tagline'] );
		}
		return $out;
	}
}
