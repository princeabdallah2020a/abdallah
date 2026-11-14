<?php
/**
 * Bootstrap.
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'textdomain' ), 1 );
		if ( ! class_exists( 'ADC\\Plugin' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_crm' ) );
			return;
		}
		Database::maybe_migrate();
		Sync::init();
		Webhook_Receiver::init();
		Ai_Endpoint::init();
		Crm_Screen::init();
	}

	public static function textdomain() {
		load_plugin_textdomain( 'automotive-marketplace-connector', false, dirname( plugin_basename( ACX_FILE ) ) . '/languages' );
	}

	public static function missing_crm() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'AutoBuzy Marketplace Connector needs the AutoBuzy Dealer CRM plugin to be active.', 'automotive-marketplace-connector' ) . '</p></div>';
	}

	public static function activate() {
		Database::migrate();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( Sync::CRON );
		wp_clear_scheduled_hook( Sync::HEARTBEAT );
	}
}
