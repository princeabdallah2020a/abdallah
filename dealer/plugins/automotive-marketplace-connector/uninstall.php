<?php
/**
 * Uninstall: removes the connection (including the encrypted secret) and the connector tables.
 * Vehicles, leads and customers belong to the Dealer CRM and are never touched.
 *
 * @package AutomotiveMarketplaceConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'items', 'inbox', 'log' ) as $acx_table ) {
	$acx_name = $wpdb->prefix . 'acx_' . $acx_table;
	$wpdb->query( "DROP TABLE IF EXISTS $acx_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array( 'acx_db_version', 'acx_connection', 'acx_remote' ) as $acx_option ) {
	delete_option( $acx_option );
}
delete_metadata( 'user', 0, 'acx_whatsapp', '', true );
wp_clear_scheduled_hook( 'acx_sync_run' );
wp_clear_scheduled_hook( 'acx_heartbeat' );
