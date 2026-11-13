<?php
/**
 * Support conversations between dealers and platform staff.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Support {

	public static function categories() {
		return array(
			'account'  => __( 'Account and application', 'automotive-marketplace-core' ),
			'billing'  => __( 'Subscription and payment', 'automotive-marketplace-core' ),
			'website'  => __( 'Website and CRM', 'automotive-marketplace-core' ),
			'listings' => __( 'Marketplace listings', 'automotive-marketplace-core' ),
			'other'    => __( 'Other', 'automotive-marketplace-core' ),
		);
	}

	/** @return int|\WP_Error Ticket id. */
	public static function create( $dealer_id, $subject, $category, $body ) {
		global $wpdb;
		$subject = mb_substr( sanitize_text_field( $subject ), 0, 190 );
		$body    = mb_substr( sanitize_textarea_field( $body ), 0, 5000 );
		if ( '' === $subject || '' === $body ) {
			return new \WP_Error( 'amc_ticket', __( 'Please enter a subject and a message.', 'automotive-marketplace-core' ) );
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'tickets' ),
			array(
				'dealer_id'  => absint( $dealer_id ),
				'user_id'    => get_current_user_id(),
				'subject'    => $subject,
				'category'   => isset( self::categories()[ $category ] ) ? $category : 'other',
				'status'     => 'open',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		self::add_message( $id, $body, false );
		Notifications::ticket_reply( $id, true );
		return $id;
	}

	/** @return true|\WP_Error */
	public static function reply( $ticket_id, $body, $is_staff ) {
		global $wpdb;
		$ticket = self::get( $ticket_id );
		$body   = mb_substr( sanitize_textarea_field( $body ), 0, 5000 );
		if ( ! $ticket || '' === $body ) {
			return new \WP_Error( 'amc_ticket', __( 'Please write a message.', 'automotive-marketplace-core' ) );
		}
		self::add_message( $ticket_id, $body, $is_staff );
		$wpdb->update(
			Database::table( 'tickets' ),
			array(
				'status'     => $is_staff ? 'answered' : 'open',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $ticket_id )
		);
		Notifications::ticket_reply( $ticket_id, ! $is_staff );
		return true;
	}

	private static function add_message( $ticket_id, $body, $is_staff ) {
		global $wpdb;
		$wpdb->insert(
			Database::table( 'ticket_messages' ),
			array(
				'ticket_id'  => absint( $ticket_id ),
				'user_id'    => get_current_user_id(),
				'is_staff'   => $is_staff ? 1 : 0,
				'body'       => $body,
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	public static function set_status( $ticket_id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'open', 'answered', 'closed' ), true ) ) {
			return false;
		}
		return (bool) $wpdb->update( Database::table( 'tickets' ), array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $ticket_id ) ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$t = Database::table( 'tickets' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT t.*, d.business_name FROM $t t LEFT JOIN $d d ON d.id = t.dealer_id WHERE t.id = %d", absint( $id ) ) );
	}

	public static function messages( $ticket_id ) {
		global $wpdb;
		$table = Database::table( 'ticket_messages' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE ticket_id = %d ORDER BY id ASC", absint( $ticket_id ) ) );
		return $rows ? $rows : array();
	}

	public static function for_dealer( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'tickets' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE dealer_id = %d ORDER BY updated_at DESC", absint( $dealer_id ) ) );
		return $rows ? $rows : array();
	}

	public static function list( $status = '' ) {
		global $wpdb;
		$t     = Database::table( 'tickets' );
		$d     = Database::table( 'dealers' );
		$where = $status ? $wpdb->prepare( 'WHERE t.status = %s', $status ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT t.*, d.business_name FROM $t t LEFT JOIN $d d ON d.id = t.dealer_id $where ORDER BY CASE t.status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END, t.updated_at DESC LIMIT 200" );
		return $rows ? $rows : array();
	}

	public static function open_count() {
		global $wpdb;
		$table = Database::table( 'tickets' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'open'" );
	}
}
