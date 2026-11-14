<?php
/**
 * Plugin bootstrap: wires every module together.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var bool */
	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );

		// Run pending migrations when the code is updated without re-activation.
		Database::maybe_migrate();

		Content::init();
		Marketplace::init();
		Seo::init();
		Sitemaps::init();
		Api::init();
		Inquiries::init();
		Assistant_Ui::init();
		Permissions::init();
		Portal::init();
		Documents::init();
		Subscriptions::init();
		Sync_Api::init();
		Webhooks::init();
		Marketplace_Leads::init();
		Whatsapp::init();
		Telephony::init();
		Privacy::init();

		if ( is_admin() ) {
			Admin::init();
		}
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'automotive-marketplace-core', false, dirname( plugin_basename( AMC_FILE ) ) . '/languages' );
	}

	public static function activate() {
		Database::migrate();
		Content::register_post_types();
		Content::install_pages();
		Marketplace::register_rewrites();
		Portal::register_rewrites();
		Permissions::install_roles();
		flush_rewrite_rules();
		Audit::log( 'plugin.activated', 'plugin', 0, array( 'version' => AMC_VERSION ) );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'amc_daily' );
		wp_clear_scheduled_hook( Webhooks::CRON );
		flush_rewrite_rules();
	}
}
