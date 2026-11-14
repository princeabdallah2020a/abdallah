<?php
/**
 * WhatsApp Business Cloud API channel (Meta).
 *
 * Webhook: {site}/wp-json/marketplace/v1/whatsapp/webhook
 *  - GET  verification (hub.mode / hub.verify_token / hub.challenge);
 *  - POST messages, authenticated with X-Hub-Signature-256 (app secret).
 * Incoming messages are stored and answered asynchronously (WP-Cron), so the
 * webhook always answers Meta within its time limit.
 *
 * Disabled by default: nothing is received or sent until staff enable it in
 * Platform admin → WhatsApp & voice, with credentials of a verified number.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Whatsapp {

	const PROCESS = 'amc_wa_process';
	const GRAPH   = 'https://graph.facebook.com/';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::PROCESS, array( Whatsapp_Agent::class, 'process' ) );
		add_action( Subscriptions::CRON, array( __CLASS__, 'purge' ) );
	}

	public static function enabled() {
		return (bool) Settings::get( 'wa_enabled' ) && Settings::get( 'wa_phone_number_id' ) && self::token() && self::app_secret();
	}

	private static function token() {
		return Settings::secret( 'wa_access_token', 'AMC_WA_ACCESS_TOKEN' );
	}

	private static function app_secret() {
		return Settings::secret( 'wa_app_secret', 'AMC_WA_APP_SECRET' );
	}

	public static function webhook_url() {
		return rest_url( Api::NS . '/whatsapp/webhook' );
	}

	public static function routes() {
		register_rest_route(
			Api::NS,
			'/whatsapp/webhook',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'verify' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'receive' ),
					'permission_callback' => '__return_true', // Authenticated by signature inside.
				),
			)
		);
	}

	/** Meta subscription check: echo the challenge as plain text. */
	public static function verify( \WP_REST_Request $request ) {
		$token = (string) Settings::get( 'wa_verify_token' );
		if ( 'subscribe' === $request->get_param( 'hub_mode' ) && '' !== $token && hash_equals( $token, (string) $request->get_param( 'hub_verify_token' ) ) ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $request->get_param( 'hub_challenge' ) ) );
			exit;
		}
		return new \WP_Error( 'amc_wa_verify', 'Verification failed.', array( 'status' => 403 ) );
	}

	public static function receive( \WP_REST_Request $request ) {
		global $wpdb;
		$secret    = self::app_secret();
		$signature = (string) $request->get_header( 'x-hub-signature-256' );
		$raw       = $request->get_body();
		if ( '' === $secret || ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw, $secret ), $signature ) ) {
			return new \WP_Error( 'amc_wa_signature', 'Invalid signature.', array( 'status' => 401 ) );
		}
		if ( ! Settings::get( 'wa_enabled' ) ) {
			return rest_ensure_response( array( 'ok' => true, 'ignored' => 'disabled' ) );
		}
		$payload = json_decode( $raw, true );
		$table   = Database::table( 'wa_messages' );
		foreach ( (array) ( $payload['entry'] ?? array() ) as $entry ) {
			foreach ( (array) ( $entry['changes'] ?? array() ) as $change ) {
				$value = (array) ( $change['value'] ?? array() );
				if ( (string) ( $value['metadata']['phone_number_id'] ?? '' ) !== (string) Settings::get( 'wa_phone_number_id' ) ) {
					continue; // Another number of the same app.
				}
				foreach ( (array) ( $value['messages'] ?? array() ) as $message ) {
					$wamid = substr( (string) ( $message['id'] ?? '' ), 0, 191 );
					$phone = Ai_Contacts::normalize( (string) ( $message['from'] ?? '' ) );
					if ( '' === $wamid || '' === $phone ) {
						continue;
					}
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE wamid = %s", $wamid ) ) ) {
						continue; // Meta retries deliveries: process once.
					}
					$type = sanitize_key( (string) ( $message['type'] ?? 'unknown' ) );
					$body = '';
					$media = '';
					if ( 'text' === $type ) {
						$body = mb_substr( (string) ( $message['text']['body'] ?? '' ), 0, 4000 );
					} elseif ( 'audio' === $type ) {
						$media = substr( (string) ( $message['audio']['id'] ?? '' ), 0, 191 );
					} elseif ( 'interactive' === $type ) {
						$body = (string) ( $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? '' );
						$type = 'text';
					} elseif ( 'button' === $type ) {
						$body = (string) ( $message['button']['text'] ?? '' );
						$type = 'text';
					}
					$contact = Ai_Contacts::find( $phone );
					$wpdb->insert(
						$table,
						array(
							'wamid'      => $wamid,
							'phone'      => $phone,
							'dealer_id'  => $contact ? (int) $contact->dealer_id : 0,
							'direction'  => 'in',
							'kind'       => substr( $type, 0, 20 ),
							'body'       => sanitize_textarea_field( $body ),
							'media_id'   => $media,
							'status'     => 'received',
							'created_at' => current_time( 'mysql', true ),
						)
					);
					wp_schedule_single_event( time(), self::PROCESS, array( (int) $wpdb->insert_id ) );
				}
			}
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ------------------------------------------------------------------ */
	/* Graph API client                                                    */
	/* ------------------------------------------------------------------ */

	private static function graph( $path ) {
		return self::GRAPH . rawurlencode( (string) Settings::get( 'wa_graph_version' ) ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Send a text message (inside the 24-hour customer service window).
	 *
	 * @return true|\WP_Error
	 */
	public static function send_text( $phone, $text ) {
		global $wpdb;
		$text     = mb_substr( trim( (string) $text ), 0, 4000 );
		$response = wp_remote_post(
			self::graph( Settings::get( 'wa_phone_number_id' ) . '/messages' ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . self::token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'to'                => ltrim( $phone, '+' ),
						'type'              => 'text',
						'text'              => array(
							'body'        => $text,
							'preview_url' => true,
						),
					)
				),
			)
		);
		$code  = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$data  = is_wp_error( $response ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$error = is_wp_error( $response ) ? $response->get_error_message() : ( $code >= 300 ? (string) ( $data['error']['message'] ?? 'HTTP ' . $code ) : '' );
		$contact = Ai_Contacts::find( $phone );
		$wpdb->insert(
			Database::table( 'wa_messages' ),
			array(
				'wamid'      => substr( (string) ( $data['messages'][0]['id'] ?? '' ), 0, 191 ),
				'phone'      => $phone,
				'dealer_id'  => $contact ? (int) $contact->dealer_id : 0,
				'direction'  => 'out',
				'kind'       => 'text',
				'body'       => $text,
				'status'     => '' === $error ? 'sent' : 'failed',
				'error'      => mb_substr( $error, 0, 255 ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return '' === $error ? true : new \WP_Error( 'amc_wa_send', $error );
	}

	/** Blue ticks: tell the sender the message was read. */
	public static function mark_read( $wamid ) {
		wp_remote_post(
			self::graph( Settings::get( 'wa_phone_number_id' ) . '/messages' ),
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => array(
					'Authorization' => 'Bearer ' . self::token(),
					'Content-Type'  => 'application/json',
				),
				'body'     => wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'status'            => 'read',
						'message_id'        => $wamid,
					)
				),
			)
		);
	}

	/**
	 * Download a media file (voice note).
	 *
	 * @return array|\WP_Error { bytes, mime }
	 */
	public static function download_media( $media_id ) {
		$auth = array( 'Authorization' => 'Bearer ' . self::token() );
		$meta = wp_remote_get( self::graph( rawurlencode( $media_id ) ), array( 'timeout' => 15, 'headers' => $auth ) );
		$info = is_wp_error( $meta ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $meta ), true );
		$url  = (string) ( $info['url'] ?? '' );
		// Media URLs are hosted by Meta: refuse anything else.
		if ( ! $url || ! preg_match( '#^https://[a-z0-9.-]+\.(fbsbx|facebook|whatsapp)\.(com|net)/#i', $url ) ) {
			return new \WP_Error( 'amc_wa_media', 'Media not available.' );
		}
		if ( (int) ( $info['file_size'] ?? 0 ) > Speech::MAX_BYTES ) {
			return new \WP_Error( 'amc_wa_media', 'Media too large.' );
		}
		$file = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => $auth, 'limit_response_size' => Speech::MAX_BYTES ) );
		if ( is_wp_error( $file ) || 200 !== (int) wp_remote_retrieve_response_code( $file ) ) {
			return new \WP_Error( 'amc_wa_media', 'Media download failed.' );
		}
		return array(
			'bytes' => (string) wp_remote_retrieve_body( $file ),
			'mime'  => (string) ( $info['mime_type'] ?? 'audio/ogg' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Admin data & retention                                              */
	/* ------------------------------------------------------------------ */

	public static function recent( $limit = 50 ) {
		global $wpdb;
		$m = Database::table( 'wa_messages' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.*, d.business_name FROM $m m LEFT JOIN $d d ON d.id = m.dealer_id ORDER BY m.id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	/** Inbound messages of a dealer this month (plan quota). */
	public static function month_count( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'wa_messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE dealer_id = %d AND direction = 'in' AND created_at >= %s", absint( $dealer_id ), gmdate( 'Y-m-01 00:00:00' ) ) );
	}

	/** Messages are kept 90 days, conversation memory 30 days. */
	public static function purge() {
		global $wpdb;
		$m = Database::table( 'wa_messages' );
		$c = Database::table( 'wa_conversations' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $m WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $c WHERE updated_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
	}
}
