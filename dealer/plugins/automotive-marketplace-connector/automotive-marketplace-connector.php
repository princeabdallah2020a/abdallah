<?php
/**
 * Plugin Name:       AutoBuzy Marketplace Connector
 * Description:       Connects this dealer website (AutoBuzy Dealer CRM) to the AutoBuzy marketplace: publishes vehicles through a signed API, receives marketplace requests as CRM leads, and powers the WhatsApp AI assistant.
 * Version:           0.4.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  automotive-dealer-crm
 * Author:            AutoBuzy
 * License:           GPL-2.0-or-later
 * Text Domain:       automotive-marketplace-connector
 * Domain Path:       /languages
 *
 * @package AutomotiveMarketplaceConnector
 */

defined( 'ABSPATH' ) || exit;

define( 'ACX_VERSION', '0.4.0' );
define( 'ACX_DB_VERSION', 1 );
define( 'ACX_FILE', __FILE__ );
define( 'ACX_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'ACX\\' ) ) {
			return;
		}
		$file = ACX_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', substr( $class, 4 ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'ACX\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACX\\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'ACX\\Plugin', 'boot' ), 20 );
