<?php
/**
 * Phone / SIP voice channel — provider independent, DISABLED by default.
 *
 * AutoBuzy does not run a SIP stack itself. A voice gateway (Asterisk,
 * FreeSWITCH, a CPaaS…) handles the call, speech recognition and speech
 * synthesis, and talks to this endpoint:
 *
 *   POST /wp-json/marketplace/v1/voice/events
 *   X-AMC-Timestamp: unix time (±300 s)
 *   X-AMC-Voice-Signature: hex(HMAC-SHA256(voice_secret, timestamp + "." + body))
 *   {
 *     "event": "call.started" | "utterance" | "call.ended",
 *     "call_ref": "gateway call id", "direction": "inbound",
 *     "from": "+221…", "to": "+221…",
 *     "text": "transcribed words (utterance)",
 *     "duration_seconds": 42 (call.ended)
 *   }
 *   → { "say": "text to speak back" }   (for "utterance")
 *
 * The answer comes from the same assistant as WhatsApp (same permissions,
 * same spoken yes/no confirmation for sensitive actions). Keep it disabled
 * until the gateway has been tested end to end (docs/05-ai-assistant.md).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Telephony {

	public static function providers() {
		return array(
			'none'    => __( 'Disabled', 'automotive-marketplace-core' ),
			'gateway' => __( 'Voice gateway (signed webhook)', 'automotive-marketplace-core' ),
		);
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( Subscriptions::CRON, array( __CLASS__, 'purge' ) );
	}

	public static function enabled() {
		return 'gateway' === Settings::get( 'voice_provider' ) && '' !== Settings::secret( 'voice_secret', 'AMC_VOICE_SECRET' );
	}

	public static function endpoint() {
		return rest_url( Api::NS . '/voice/events' );
	}

	public static function routes() {
		register_rest_route(
			Api::NS,
			'/voice/events',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'event' ),
				'permission_callback' => array( __CLASS__, 'authenticate' ),
			)
		);
	}

	public static function authenticate( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'amc_voice_disabled', 'Voice channel disabled.', array( 'status' => 403 ) );
		}
		$ts        = (int) $request->get_header( 'x-amc-timestamp' );
		$signature = strtolower( (string) $request->get_header( 'x-amc-voice-signature' ) );
		if ( abs( time() - $ts ) > 300 ) {
			return new \WP_Error( 'amc_voice_auth', 'Timestamp outside the allowed window.', array( 'status' => 401 ) );
		}
		$expected = hash_hmac( 'sha256', $ts . '.' . $request->get_body(), Settings::secret( 'voice_secret', 'AMC_VOICE_SECRET' ) );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new \WP_Error( 'amc_voice_auth', 'Invalid signature.', array( 'status' => 401 ) );
		}
		return true;
	}

	public static function event( \WP_REST_Request $request ) {
		global $wpdb;
		$table = Database::table( 'call_logs' );
		$event = sanitize_key( str_replace( '.', '_', (string) $request->get_param( 'event' ) ) );
		$ref   = substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $request->get_param( 'call_ref' ) ), 0, 191 );
		$from  = Ai_Contacts::normalize( (string) $request->get_param( 'from' ) );
		if ( '' === $ref ) {
			return new \WP_Error( 'amc_voice', 'call_ref is required.', array( 'status' => 400 ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$call    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE provider = 'gateway' AND call_ref = %s", $ref ) );
		$now     = current_time( 'mysql', true );
		$contact = $from ? Ai_Contacts::find( $from ) : null;
		if ( ! $call ) {
			$wpdb->insert(
				$table,
				array(
					'provider'    => 'gateway',
					'call_ref'    => $ref,
					'direction'   => 'outbound' === $request->get_param( 'direction' ) ? 'outbound' : 'inbound',
					'from_number' => $from,
					'to_number'   => substr( preg_replace( '/[^0-9+]/', '', (string) $request->get_param( 'to' ) ), 0, 32 ),
					'dealer_id'   => $contact ? (int) $contact->dealer_id : 0,
					'status'      => 'started',
					'transcript'  => '',
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$call = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $wpdb->insert_id ) );
		}

		if ( 'call_ended' === $event ) {
			$wpdb->update( $table, array( 'status' => 'ended', 'duration_seconds' => absint( $request->get_param( 'duration_seconds' ) ), 'updated_at' => $now ), array( 'id' => (int) $call->id ) );
			return rest_ensure_response( array( 'ok' => true ) );
		}
		if ( 'utterance' !== $event ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}

		$text = mb_substr( sanitize_textarea_field( (string) $request->get_param( 'text' ) ), 0, 2000 );
		if ( '' === $text || '' === $from ) {
			return rest_ensure_response( array( 'say' => __( 'Sorry, I did not hear you. Could you repeat?', 'automotive-marketplace-core' ) ) );
		}
		// Counted like WhatsApp messages (plan quota, flood protection, history).
		$wpdb->insert(
			Database::table( 'wa_messages' ),
			array(
				'wamid'      => 'call:' . $ref,
				'phone'      => $from,
				'dealer_id'  => $contact ? (int) $contact->dealer_id : 0,
				'direction'  => 'in',
				'kind'       => 'call',
				'body'       => $text,
				'status'     => 'processed',
				'created_at' => $now,
			)
		);
		$say = wp_strip_all_tags( str_replace( '*', '', Whatsapp_Agent::respond( $from, $text ) ) );
		$wpdb->update(
			$table,
			array(
				'transcript' => mb_substr( $call->transcript . "\n> " . $text . "\n" . $say, -60000 ),
				'status'     => 'in_progress',
				'updated_at' => $now,
			),
			array( 'id' => (int) $call->id )
		);
		return rest_ensure_response( array( 'say' => $say ) );
	}

	public static function recent( $limit = 30 ) {
		global $wpdb;
		$t = Database::table( 'call_logs' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.id, c.direction, c.from_number, c.status, c.duration_seconds, c.created_at, d.business_name FROM $t c LEFT JOIN $d d ON d.id = c.dealer_id ORDER BY c.id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	/** Call transcripts are personal data: kept 90 days. */
	public static function purge() {
		global $wpdb;
		$t = Database::table( 'call_logs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
	}
}
