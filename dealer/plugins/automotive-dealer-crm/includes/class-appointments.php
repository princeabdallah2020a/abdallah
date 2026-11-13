<?php
/**
 * Appointments (showroom visits, test drives, deliveries).
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Appointments {

	/** @return int|\WP_Error */
	public static function create( array $in ) {
		global $wpdb;
		if ( ! current_user_can( 'adc_manage_leads' ) && ! current_user_can( 'adc_manage_customers' ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		$starts = adc_utc_from_local( isset( $in['starts_at'] ) ? $in['starts_at'] : '' );
		if ( ! $starts ) {
			return new \WP_Error( 'adc_appointment', __( 'Choose a date and time.', 'automotive-dealer-crm' ) );
		}
		$lead = ! empty( $in['lead_id'] ) ? Leads::get( absint( $in['lead_id'] ) ) : null;
		$wpdb->insert(
			Database::table( 'appointments' ),
			array(
				'lead_id'     => $lead ? (int) $lead->id : 0,
				'customer_id' => $lead ? (int) $lead->customer_id : absint( isset( $in['customer_id'] ) ? $in['customer_id'] : 0 ),
				'vehicle_id'  => $lead ? (int) $lead->vehicle_id : absint( isset( $in['vehicle_id'] ) ? $in['vehicle_id'] : 0 ),
				'user_id'     => isset( $in['user_id'] ) && Team::is_member( absint( $in['user_id'] ) ) ? absint( $in['user_id'] ) : get_current_user_id(),
				'starts_at'   => $starts,
				'title'       => mb_substr( sanitize_text_field( isset( $in['title'] ) ? (string) $in['title'] : '' ), 0, 190 ),
				'notes'       => isset( $in['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $in['notes'] ), 0, 2000 ) : '',
				'status'      => 'scheduled',
				'created_at'  => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $lead ) {
			if ( in_array( $lead->status, array( 'new', 'contacted', 'interested' ), true ) ) {
				Leads::set_status( $lead->id, 'appointment' );
			}
			Leads::note( $lead->id, 'system', sprintf( /* translators: %s: date */ __( 'Appointment scheduled on %s.', 'automotive-dealer-crm' ), adc_date( $starts, true ) ) );
		}
		Activity::log( 'appointment_created', 'appointment', $id, adc_date( $starts, true ) );
		return $id;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'scheduled', 'done', 'cancelled' ), true ) ) {
			return false;
		}
		return (bool) $wpdb->update( Database::table( 'appointments' ), array( 'status' => $status ), array( 'id' => absint( $id ) ) );
	}

	/** Upcoming appointments (visible to everyone with CRM access). */
	public static function upcoming( $limit = 10, $lead_id = 0 ) {
		global $wpdb;
		$a     = Database::table( 'appointments' );
		$l     = Database::table( 'leads' );
		$v     = Database::table( 'vehicles' );
		$where = $lead_id ? $wpdb->prepare( 'a.lead_id = %d', $lead_id ) : $wpdb->prepare( "a.status = 'scheduled' AND a.starts_at >= %s", gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.*, l.name AS lead_name, l.phone AS lead_phone, v.title AS vehicle_title FROM $a a LEFT JOIN $l l ON l.id = a.lead_id LEFT JOIN $v v ON v.id = a.vehicle_id WHERE $where ORDER BY a.starts_at ASC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}
}
