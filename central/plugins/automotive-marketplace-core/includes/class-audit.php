<?php
/**
 * Append-only audit log for administrative and security-relevant events.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Audit {

	/**
	 * Record an event.
	 *
	 * @param string $action      Dot-notation event name, e.g. "settings.updated".
	 * @param string $object_type Entity type.
	 * @param int    $object_id   Entity id.
	 * @param array  $context     Extra data. Never put secrets or personal data here.
	 */
	public static function log( $action, $object_type = '', $object_id = 0, array $context = array() ) {
		global $wpdb;
		$wpdb->insert(
			Database::table( 'audit_log' ),
			array(
				'actor_user_id' => get_current_user_id(),
				'action'        => substr( sanitize_text_field( $action ), 0, 80 ),
				'object_type'   => substr( sanitize_key( $object_type ), 0, 40 ),
				'object_id'     => absint( $object_id ),
				'context'       => wp_json_encode( $context ),
				'ip_hash'       => Rate_Limiter::ip_hash(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Latest events, newest first.
	 *
	 * @param int $limit Max rows.
	 */
	public static function latest( $limit = 50 ) {
		global $wpdb;
		$table = Database::table( 'audit_log' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", absint( $limit ) ) );
	}
}
