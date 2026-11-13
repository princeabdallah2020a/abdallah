<?php
/**
 * Uninstall: data is only deleted when the administrator opted in from
 * Marketplace → Settings → Data. Otherwise everything is preserved.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$amc_settings = get_option( 'amc_settings', array() );
if ( empty( $amc_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'vehicle_media', 'vehicles', 'dealers', 'inquiries', 'audit_log' ) as $amc_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'amc_' . $amc_table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
delete_option( 'amc_settings' );
delete_option( 'amc_db_version' );
delete_option( 'amc_pages' );
