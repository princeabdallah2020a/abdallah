<?php
/**
 * Connector tables.
 *
 * acx_items  one row per vehicle: synchronisation state and outbox (coalesced:
 *            the payload is built at send time, so only the latest state is sent).
 * acx_inbox  webhook deliveries already processed (replay protection).
 * acx_log    short technical log shown in the CRM.
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

defined( 'ABSPATH' ) || exit;

final class Database {

	const OPTION = 'acx_db_version';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'acx_' . $name;
	}

	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) < ACX_DB_VERSION ) {
			self::migrate();
		}
	}

	public static function migrate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c     = $wpdb->get_charset_collate();
		$items = self::table( 'items' );
		$inbox = self::table( 'inbox' );
		$log   = self::table( 'log' );
		dbDelta(
			"CREATE TABLE $items (
			uid char(36) NOT NULL,
			vehicle_id bigint(20) unsigned NOT NULL DEFAULT 0,
			op varchar(10) NOT NULL DEFAULT 'put',
			state varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
			remote_url varchar(500) NOT NULL DEFAULT '',
			remote_status varchar(20) NOT NULL DEFAULT '',
			last_error varchar(255) NOT NULL DEFAULT '',
			synced_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (uid),
			KEY state_next (state,next_attempt_at),
			KEY vehicle_id (vehicle_id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE $inbox (
			delivery_id char(36) NOT NULL,
			event varchar(40) NOT NULL,
			received_at datetime NOT NULL,
			PRIMARY KEY  (delivery_id),
			KEY received_at (received_at)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE $log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(10) NOT NULL DEFAULT 'info',
			message varchar(255) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
			) $c;"
		);
		update_option( self::OPTION, ACX_DB_VERSION, false );
	}

	public static function log( $message, $level = 'info' ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'log' ),
			array(
				'level'      => 'error' === $level ? 'error' : 'info',
				'message'    => mb_substr( (string) $message, 0, 255 ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	public static function recent_log( $limit = 30 ) {
		global $wpdb;
		$table = self::table( 'log' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	/** Keep 30 days of log and inbox. */
	public static function cleanup() {
		global $wpdb;
		$cut = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		foreach ( array( 'log' => 'created_at', 'inbox' => 'received_at' ) as $name => $column ) {
			$table = self::table( $name );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE $column < %s", $cut ) );
		}
	}

	public static function drop_all() {
		global $wpdb;
		foreach ( array( 'items', 'inbox', 'log' ) as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( self::OPTION );
	}
}
