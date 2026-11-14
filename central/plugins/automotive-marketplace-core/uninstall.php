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
foreach ( array( 'vehicle_media', 'vehicles', 'dealers', 'inquiries', 'audit_log', 'ai_usage', 'plans', 'applications', 'subscriptions', 'payments', 'documents', 'provisioning_jobs', 'tickets', 'ticket_messages', 'api_credentials', 'sync_events', 'webhook_deliveries', 'marketplace_leads', 'ai_contacts', 'wa_conversations', 'wa_messages', 'call_logs' ) as $amc_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'amc_' . $amc_table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
delete_option( 'amc_settings' );
delete_option( 'amc_db_version' );
delete_option( 'amc_pages' );
delete_option( 'amc_plan_durations' );
remove_role( 'amc_dealer_owner' );
remove_role( 'amc_platform_manager' );
wp_clear_scheduled_hook( 'amc_daily' );
