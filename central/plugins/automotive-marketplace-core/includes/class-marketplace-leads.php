<?php
/**
 * "Message the dealer" form on marketplace vehicle pages.
 *
 * The request is stored, then delivered to the dealer's CRM as a signed
 * lead.created webhook. When the dealer website is not connected, or every
 * delivery attempt failed, the dealer receives it by email instead.
 * Personal data is purged after 180 days.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Marketplace_Leads {

	const ACTION    = 'amc_vehicle_lead';
	const RETENTION = 180;

	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'amc_webhook_delivered', array( __CLASS__, 'delivered' ) );
		add_action( 'amc_webhook_failed', array( __CLASS__, 'failed' ) );
		add_action( Subscriptions::CRON, array( __CLASS__, 'purge' ) );
	}

	public static function result() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		$result = isset( $_GET['amc_lead'] ) ? sanitize_key( wp_unslash( $_GET['amc_lead'] ) ) : '';
		return in_array( $result, array( 'sent', 'invalid', 'rate', 'error' ), true ) ? $result : '';
	}

	public static function render_form( $vehicle ) {
		amc_get_template( 'parts/lead-form', array( 'vehicle' => $vehicle ) );
	}

	private static function back( $result, $url ) {
		wp_safe_redirect( add_query_arg( 'amc_lead', $result, $url ) . '#amc-lead' );
		exit;
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified just below.
		$vehicle = Vehicles::get_by_id( isset( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : 0 );
		$back    = $vehicle ? amc_vehicle_url( $vehicle ) : home_url( '/' );

		if ( ! isset( $_POST['_amc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_amc_nonce'] ) ), self::ACTION ) ) {
			self::back( 'error', $back );
		}
		$elapsed = time() - ( isset( $_POST['amc_ts'] ) ? absint( $_POST['amc_ts'] ) : 0 );
		if ( ! empty( $_POST['website'] ) || $elapsed < 3 ) {
			self::back( 'sent', $back ); // Bot: pretend success.
		}
		if ( ! $vehicle || ! in_array( $vehicle->status, array( 'active', 'reserved' ), true ) ) {
			self::back( 'error', $back );
		}
		if ( ! Rate_Limiter::allow( 'vehicle_lead', 6, HOUR_IN_SECONDS ) ) {
			self::back( 'rate', $back );
		}
		$name    = isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 120 ) : '';
		$phone   = isset( $_POST['phone'] ) ? amc_normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$message = isset( $_POST['message'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ), 0, 3000 ) : '';
		$consent = ! empty( $_POST['consent'] );
		// phpcs:enable
		if ( '' === $name || ( '' === $phone && '' === $email ) || ! $consent ) {
			self::back( 'invalid', $back );
		}
		if ( '' === $message ) {
			/* translators: %s: vehicle title */
			$message = sprintf( __( 'Hello, I am interested in the %s. Is it still available?', 'automotive-marketplace-core' ), $vehicle->title );
		}
		$id = self::create( (int) $vehicle->dealer_id, (int) $vehicle->id, compact( 'name', 'phone', 'email', 'message' ) );
		self::back( $id ? 'sent' : 'error', $back );
	}

	/**
	 * Store and route a lead.
	 *
	 * @return int Lead id.
	 */
	public static function create( $dealer_id, $vehicle_id, array $data ) {
		global $wpdb;
		$wpdb->insert(
			Database::table( 'marketplace_leads' ),
			array(
				'dealer_id'  => absint( $dealer_id ),
				'vehicle_id' => absint( $vehicle_id ),
				'name'       => $data['name'],
				'phone'      => $data['phone'],
				'email'      => $data['email'],
				'message'    => $data['message'],
				'channel'    => 'webhook',
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return 0;
		}
		$vehicle  = Vehicles::get_by_id( $vehicle_id );
		$delivery = Webhooks::enqueue(
			$dealer_id,
			'lead.created',
			array(
				'lead_id'     => $id,
				'name'        => $data['name'],
				'phone'       => $data['phone'],
				'email'       => $data['email'],
				'message'     => $data['message'],
				'external_id' => $vehicle ? $vehicle->external_id : '',
				'vehicle'     => $vehicle ? $vehicle->title : '',
				'vehicle_url' => $vehicle ? amc_vehicle_url( $vehicle ) : '',
			)
		);
		if ( ! $delivery ) {
			self::email( $id );
		}
		return $id;
	}

	private static function set_status( $id, $status, $channel = '' ) {
		global $wpdb;
		$data = array( 'status' => $status );
		if ( $channel ) {
			$data['channel'] = $channel;
		}
		$wpdb->update( Database::table( 'marketplace_leads' ), $data, array( 'id' => absint( $id ) ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'marketplace_leads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	/** Email fallback to the dealer. */
	public static function email( $id ) {
		$lead   = self::get( $id );
		$dealer = $lead ? Memberships::get_dealer( $lead->dealer_id ) : null;
		$to     = Notifications::dealer_email( $dealer );
		if ( ! $lead || ! $to ) {
			if ( $lead ) {
				self::set_status( $id, 'undeliverable', 'email' );
			}
			return false;
		}
		$vehicle = Vehicles::get_by_id( $lead->vehicle_id );
		$lines   = array(
			/* translators: %s: vehicle title */
			sprintf( __( 'A visitor of the marketplace is interested in: %s', 'automotive-marketplace-core' ), $vehicle ? $vehicle->title : '—' ),
			__( 'Name', 'automotive-marketplace-core' ) . ': ' . $lead->name,
			__( 'Phone', 'automotive-marketplace-core' ) . ': ' . ( $lead->phone ? $lead->phone : '—' ),
			__( 'Email', 'automotive-marketplace-core' ) . ': ' . ( $lead->email ? $lead->email : '—' ),
			'',
			$lead->message,
		);
		$sent = Notifications::send( $to, __( 'New request from the marketplace', 'automotive-marketplace-core' ), $lines, $vehicle ? __( 'View the listing', 'automotive-marketplace-core' ) : '', $vehicle ? amc_vehicle_url( $vehicle ) : '' );
		self::set_status( $id, $sent ? 'emailed' : 'undeliverable', 'email' );
		return $sent;
	}

	public static function delivered( $row ) {
		if ( 'lead.created' === $row->event ) {
			$data = json_decode( (string) $row->payload, true );
			if ( ! empty( $data['lead_id'] ) ) {
				self::set_status( (int) $data['lead_id'], 'delivered' );
			}
		}
	}

	public static function failed( $row ) {
		if ( 'lead.created' === $row->event ) {
			$data = json_decode( (string) $row->payload, true );
			if ( ! empty( $data['lead_id'] ) ) {
				self::email( (int) $data['lead_id'] );
			}
		}
	}

	/** Remove personal data after the retention period. */
	public static function purge() {
		global $wpdb;
		$table = Database::table( 'marketplace_leads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - self::RETENTION * DAY_IN_SECONDS ) ) );
	}

	/** Counts per status for the last 30 days (staff overview). */
	public static function stats() {
		global $wpdb;
		$table = Database::table( 'marketplace_leads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM $table WHERE created_at >= %s GROUP BY status", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row->status ] = (int) $row->n;
		}
		return $out;
	}
}
