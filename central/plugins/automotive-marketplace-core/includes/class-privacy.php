<?php
/**
 * Personal data: WordPress privacy tools (Tools → Export / Erase Personal
 * Data) for the requests visitors send through the marketplace, and the
 * suggested privacy policy text.
 *
 * Retention (automatic): marketplace requests 180 days, WhatsApp messages and
 * call transcripts 90 days, conversation memory 30 days.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
		add_action( Subscriptions::CRON, array( __CLASS__, 'purge_inquiries' ) );
	}

	public static function exporters( $exporters ) {
		$exporters['autobuzy'] = array(
			'exporter_friendly_name' => __( 'AutoBuzy requests', 'automotive-marketplace-core' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['autobuzy'] = array(
			'eraser_friendly_name' => __( 'AutoBuzy requests', 'automotive-marketplace-core' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	private static function rows( $email ) {
		global $wpdb;
		$i = Database::table( 'inquiries' );
		$l = Database::table( 'marketplace_leads' );
		return array(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'inquiries' => (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $i WHERE email = %s LIMIT 500", $email ) ),
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'leads'     => (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $l WHERE email = %s LIMIT 500", $email ) ),
		);
	}

	public static function export( $email, $page = 1 ) {
		$items = array();
		$rows  = self::rows( sanitize_email( $email ) );
		foreach ( $rows['inquiries'] as $row ) {
			$items[] = array(
				'group_id'    => 'autobuzy-contact',
				'group_label' => __( 'Contact requests', 'automotive-marketplace-core' ),
				'item_id'     => 'inquiry-' . $row->id,
				'data'        => array(
					array( 'name' => __( 'Date', 'automotive-marketplace-core' ), 'value' => $row->created_at ),
					array( 'name' => __( 'Name', 'automotive-marketplace-core' ), 'value' => $row->name ),
					array( 'name' => __( 'Phone', 'automotive-marketplace-core' ), 'value' => $row->phone ),
					array( 'name' => __( 'Message', 'automotive-marketplace-core' ), 'value' => $row->message ),
				),
			);
		}
		foreach ( $rows['leads'] as $row ) {
			$vehicle = Vehicles::get_by_id( $row->vehicle_id );
			$items[] = array(
				'group_id'    => 'autobuzy-vehicle-requests',
				'group_label' => __( 'Messages sent to dealers', 'automotive-marketplace-core' ),
				'item_id'     => 'lead-' . $row->id,
				'data'        => array(
					array( 'name' => __( 'Date', 'automotive-marketplace-core' ), 'value' => $row->created_at ),
					array( 'name' => __( 'Vehicle', 'automotive-marketplace-core' ), 'value' => $vehicle ? $vehicle->title : '—' ),
					array( 'name' => __( 'Name', 'automotive-marketplace-core' ), 'value' => $row->name ),
					array( 'name' => __( 'Phone', 'automotive-marketplace-core' ), 'value' => $row->phone ),
					array( 'name' => __( 'Message', 'automotive-marketplace-core' ), 'value' => $row->message ),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	public static function erase( $email, $page = 1 ) {
		global $wpdb;
		$email   = sanitize_email( $email );
		$removed = 0;
		if ( $email ) {
			$removed += (int) $wpdb->delete( Database::table( 'inquiries' ), array( 'email' => $email ) );
			$removed += (int) $wpdb->delete( Database::table( 'marketplace_leads' ), array( 'email' => $email ) );
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => $removed ? array( __( 'Requests sent to dealers were deleted from AutoBuzy. Copies already delivered to a dealer CRM must be erased by that dealer.', 'automotive-marketplace-core' ) ) : array(),
			'done'           => true,
		);
	}

	/** Contact requests: kept one year. */
	public static function purge_inquiries() {
		global $wpdb;
		$table = Database::table( 'inquiries' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) );
	}

	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'AutoBuzy',
			'<p>' . esc_html__( 'When you send a message to a dealer from a vehicle page, your name, phone number, email and message are sent to that dealer so they can answer you, and kept on AutoBuzy for 180 days.', 'automotive-marketplace-core' ) . '</p>'
			. '<p>' . esc_html__( 'Contact requests sent to AutoBuzy are kept for one year.', 'automotive-marketplace-core' ) . '</p>'
			. '<p>' . esc_html__( 'If you write to the AutoBuzy assistant on WhatsApp or call it, your number and messages are processed to answer you, using an AI provider (Anthropic) and, for voice notes, a speech-to-text provider. Messages and call transcripts are kept for 90 days.', 'automotive-marketplace-core' ) . '</p>'
		);
	}
}
