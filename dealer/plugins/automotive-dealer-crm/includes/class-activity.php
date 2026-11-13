<?php
/**
 * Activity log of the dealership (who did what, when).
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Activity {

	public static function log( $action, $object_type, $object_id, $summary = '' ) {
		global $wpdb;
		$wpdb->insert(
			Database::table( 'activity' ),
			array(
				'user_id'     => get_current_user_id(),
				'action'      => substr( sanitize_key( str_replace( '.', '_', $action ) ), 0, 60 ),
				'object_type' => substr( sanitize_key( $object_type ), 0, 30 ),
				'object_id'   => absint( $object_id ),
				'summary'     => mb_substr( sanitize_text_field( $summary ), 0, 255 ),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	public static function latest( $limit = 20 ) {
		global $wpdb;
		$table = Database::table( 'activity' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}
}
