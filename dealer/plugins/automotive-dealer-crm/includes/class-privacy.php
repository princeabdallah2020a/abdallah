<?php
/**
 * Personal data of customers and leads: WordPress privacy tools
 * (Tools → Export / Erase Personal Data) and suggested policy text.
 *
 * Erasure anonymises records instead of deleting them, so that sales and
 * rental history (accounting) stay consistent; identity documents attached
 * to the customer are deleted.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	public static function exporters( $exporters ) {
		$exporters['adc'] = array(
			'exporter_friendly_name' => __( 'Dealer CRM (customers and requests)', 'automotive-dealer-crm' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['adc'] = array(
			'eraser_friendly_name' => __( 'Dealer CRM (customers and requests)', 'automotive-dealer-crm' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$email = sanitize_email( $email );
		$items = array();
		$c     = Database::table( 'customers' );
		$l     = Database::table( 'leads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $c WHERE email = %s", $email ) ) as $row ) {
			$items[] = array(
				'group_id'    => 'adc-customer',
				'group_label' => __( 'Customer record', 'automotive-dealer-crm' ),
				'item_id'     => 'customer-' . $row->id,
				'data'        => array(
					array( 'name' => __( 'Name', 'automotive-dealer-crm' ), 'value' => $row->name ),
					array( 'name' => __( 'Phone', 'automotive-dealer-crm' ), 'value' => $row->phone ),
					array( 'name' => __( 'Address', 'automotive-dealer-crm' ), 'value' => trim( $row->address . ' ' . $row->city ) ),
					array( 'name' => __( 'ID number', 'automotive-dealer-crm' ), 'value' => $row->id_number ),
				),
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $l WHERE email = %s", $email ) ) as $row ) {
			$items[] = array(
				'group_id'    => 'adc-lead',
				'group_label' => __( 'Requests', 'automotive-dealer-crm' ),
				'item_id'     => 'lead-' . $row->id,
				'data'        => array(
					array( 'name' => __( 'Date', 'automotive-dealer-crm' ), 'value' => $row->created_at ),
					array( 'name' => __( 'Name', 'automotive-dealer-crm' ), 'value' => $row->name ),
					array( 'name' => __( 'Phone', 'automotive-dealer-crm' ), 'value' => $row->phone ),
					array( 'name' => __( 'Message', 'automotive-dealer-crm' ), 'value' => $row->message ),
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
		$email = sanitize_email( $email );
		$count = 0;
		if ( $email ) {
			$anon = __( 'Anonymised', 'automotive-dealer-crm' );
			$c    = Database::table( 'customers' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $c WHERE email = %s", $email ) ) as $id ) {
				foreach ( Files::for_customer( (int) $id ) as $file ) {
					Files::delete( (int) $file->id );
				}
				$wpdb->update( $c, array( 'name' => $anon, 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'id_number' => '', 'notes' => '' ), array( 'id' => (int) $id ) );
				++$count;
			}
			$count += (int) $wpdb->update( Database::table( 'leads' ), array( 'name' => $anon, 'phone' => '', 'email' => '', 'message' => '' ), array( 'email' => $email ) );
		}
		return array(
			'items_removed'  => $count > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public static function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Dealer CRM', 'automotive-dealer-crm' ),
				'<p>' . esc_html__( 'When you contact us or buy or rent a vehicle, we record your name, contact details and, for contracts, your identity document, to handle your request and our legal obligations. Only our team can access them.', 'automotive-dealer-crm' ) . '</p>'
			);
		}
	}
}
