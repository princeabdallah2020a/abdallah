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
			// WhatsApp AI assistant (Phase 5) — disabled until configured and verified.
			'wa_enabled'            => 0,
			'wa_phone_number_id'    => '',
			'wa_business_number'    => '',
			'wa_graph_version'      => 'v21.0',
			'wa_verify_token'       => '',
			'wa_access_token'       => '', // Encrypted (Crypto); prefer the AMC_WA_ACCESS_TOKEN constant.
			'wa_app_secret'         => '', // Encrypted; prefer AMC_WA_APP_SECRET.
			'wa_public_assistant'   => 1,  // Unknown numbers get the buyer assistant.
			// Speech-to-text for voice notes (provider-independent).
			'stt_provider'          => 'none',
			'stt_base_url'          => 'https://api.openai.com/v1',
			'stt_model'             => 'whisper-1',
			'stt_api_key'           => '', // Encrypted; prefer AMC_STT_API_KEY.
			// Telephony (Phase 6) — disabled until a provider is verified.
			'voice_provider'        => 'none',
			'voice_secret'          => '', // Encrypted shared secret of the voice gateway.
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

		// Channels (only present in the platform admin "WhatsApp & voice" form).
		if ( isset( $input['channels_form'] ) ) {
			foreach ( array( 'wa_enabled', 'wa_public_assistant' ) as $key ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
			foreach ( array( 'wa_phone_number_id' => '/[^0-9]/', 'wa_business_number' => '/[^0-9+ ]/', 'wa_verify_token' => '/[^A-Za-z0-9_\-]/', 'wa_graph_version' => '/[^a-z0-9.]/' ) as $key => $strip ) {
				if ( isset( $input[ $key ] ) ) {
					$out[ $key ] = substr( preg_replace( $strip, '', (string) $input[ $key ] ), 0, 80 );
				}
			}
			if ( isset( $input['stt_provider'] ) ) {
				$out['stt_provider'] = isset( Speech::providers()[ $input['stt_provider'] ] ) ? $input['stt_provider'] : 'none';
			}
			if ( isset( $input['stt_base_url'] ) ) {
				$out['stt_base_url'] = untrailingslashit( esc_url_raw( (string) $input['stt_base_url'], array( 'https' ) ) );
			}
			if ( isset( $input['stt_model'] ) ) {
				$out['stt_model'] = substr( preg_replace( '/[^A-Za-z0-9_.:\-\/]/', '', (string) $input['stt_model'] ), 0, 80 );
			}
			if ( isset( $input['voice_provider'] ) ) {
				$out['voice_provider'] = isset( Telephony::providers()[ $input['voice_provider'] ] ) ? $input['voice_provider'] : 'none';
			}
			// Secrets: never pre-filled; empty keeps the saved value; stored encrypted.
			foreach ( array( 'wa_access_token', 'wa_app_secret', 'stt_api_key', 'voice_secret' ) as $key ) {
				// The form merges saved values: an unchanged (already encrypted) value is kept as is.
				if ( ! empty( $input[ $key ] ) && (string) $input[ $key ] !== (string) self::get( $key ) ) {
					$out[ $key ] = Crypto::encrypt( preg_replace( '/[^\x21-\x7E]/', '', (string) $input[ $key ] ) );
				}
				if ( ! empty( $input[ $key . '_delete' ] ) ) {
					$out[ $key ] = '';
				}
			}
			if ( '' === $out['wa_verify_token'] ) {
				$out['wa_verify_token'] = wp_generate_password( 32, false );
			}
		}

		return $out;
	}

	/**
	 * A secret: wp-config.php constant first (recommended), then the encrypted setting.
	 *
	 * @param string $key      Setting key.
	 * @param string $constant Constant name.
	 */
	public static function secret( $key, $constant ) {
		if ( defined( $constant ) && constant( $constant ) ) {
			return (string) constant( $constant );
		}
		$stored = (string) self::get( $key );
		return '' === $stored ? '' : Crypto::decrypt( $stored );
	}

	/** Where a secret comes from: constant|settings|none (never the value). */
	public static function secret_source( $key, $constant ) {
		if ( defined( $constant ) && constant( $constant ) ) {
			return 'constant';
		}
		return '' === (string) self::get( $key ) ? 'none' : 'settings';
	}

	/** Email that receives contact requests (falls back to the site admin email). */
	public static function contact_email() {
		$email = self::get( 'contact_email' );
		return $email ? $email : get_option( 'admin_email' );
	}
}
