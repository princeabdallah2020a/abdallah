<?php
/**
 * Receives signed webhooks from the marketplace:
 * POST /wp-json/dealer/v1/webhooks/marketplace
 *
 * Events: lead.created (→ CRM lead, source "marketplace"), resync.requested,
 * dealer.updated. Each delivery id is processed once (replay protection).
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

defined( 'ABSPATH' ) || exit;

final class Webhook_Receiver {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'dealer/v1',
			'/webhooks/marketplace',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive' ),
				'permission_callback' => array( Connection::class, 'verify' ),
			)
		);
	}

	public static function receive( \WP_REST_Request $request ) {
		global $wpdb;
		$body  = (array) $request->get_json_params();
		$id    = isset( $body['id'] ) ? (string) $body['id'] : (string) $request->get_header( 'x-amc-delivery' );
		$event = isset( $body['event'] ) ? (string) $body['event'] : '';
		$data  = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		if ( ! wp_is_uuid( $id ) || '' === $event ) {
			return new \WP_Error( 'acx_payload', 'Invalid payload.', array( 'status' => 400 ) );
		}
		// Atomic dedupe: the primary key rejects a second insert of the same delivery.
		$inserted = $wpdb->insert(
			Database::table( 'inbox' ),
			array(
				'delivery_id' => $id,
				'event'       => substr( $event, 0, 40 ),
				'received_at' => current_time( 'mysql', true ),
			)
		);
		if ( ! $inserted ) {
			return rest_ensure_response( array( 'ok' => true, 'duplicate' => true ) );
		}

		switch ( $event ) {
			case 'lead.created':
				$result = self::lead( $data );
				if ( is_wp_error( $result ) ) {
					// Let the marketplace retry (and fall back to email in the end).
					$wpdb->delete( Database::table( 'inbox' ), array( 'delivery_id' => $id ) );
					return new \WP_Error( 'acx_lead', $result->get_error_message(), array( 'status' => 422 ) );
				}
				return rest_ensure_response( array( 'ok' => true, 'lead_id' => $result ) );

			case 'resync.requested':
				$count = Sync::queue_all();
				return rest_ensure_response( array( 'ok' => true, 'queued' => $count ) );

			case 'dealer.updated':
				Connection::save_remote(
					array(
						'dealer'       => array_merge( Connection::remote()['dealer'] ?? array(), array( 'status' => sanitize_key( (string) ( $data['status'] ?? '' ) ) ) ),
						'subscription' => array(
							'plan'    => sanitize_text_field( (string) ( $data['plan'] ?? '' ) ),
							'ends_at' => isset( $data['ends_at'] ) ? sanitize_text_field( (string) $data['ends_at'] ) : null,
						),
					)
				);
				if ( 'active' === ( $data['status'] ?? '' ) ) {
					Sync::retry_errors(); // Listings refused while inactive can go online now.
				}
				Database::log( __( 'Marketplace account updated.', 'automotive-marketplace-connector' ) );
				return rest_ensure_response( array( 'ok' => true ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'ignored' => $event ) );
	}

	/**
	 * Create a CRM lead from a marketplace request.
	 *
	 * @return int|\WP_Error Lead id.
	 */
	private static function lead( array $data ) {
		global $wpdb;
		$vehicle_id = 0;
		if ( ! empty( $data['external_id'] ) ) {
			$table = \ADC\Database::table( 'vehicles' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$vehicle_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE uid = %s", (string) $data['external_id'] ) );
		}
		$message = (string) ( $data['message'] ?? '' );
		if ( ! empty( $data['vehicle_url'] ) ) {
			$message .= "\n\n" . __( 'Marketplace listing:', 'automotive-marketplace-connector' ) . ' ' . esc_url_raw( (string) $data['vehicle_url'] );
		}
		$id = \ADC\Leads::save(
			array(
				'name'       => (string) ( $data['name'] ?? '' ),
				'phone'      => (string) ( $data['phone'] ?? '' ),
				'email'      => (string) ( $data['email'] ?? '' ),
				'message'    => $message,
				'vehicle_id' => $vehicle_id,
			),
			0,
			'marketplace'
		);
		if ( ! is_wp_error( $id ) ) {
			Database::log( sprintf( /* translators: %s: customer name */ __( 'New marketplace request from %s.', 'automotive-marketplace-connector' ), sanitize_text_field( (string) ( $data['name'] ?? '' ) ) ) );
		}
		return $id;
	}
}
