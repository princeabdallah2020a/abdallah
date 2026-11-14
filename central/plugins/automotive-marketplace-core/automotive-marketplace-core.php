<?php
/**
 * Plugin Name:       AutoBuzy Marketplace Core
 * Description:       AutoBuzy central platform: marketplace, dealer memberships and subscriptions, platform administration and AI assistant.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            AutoBuzy
 * License:           GPL-2.0-or-later
 * Text Domain:       automotive-marketplace-core
 * Domain Path:       /languages
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

define( 'AMC_VERSION', '0.2.0' );
define( 'AMC_DB_VERSION', 5 );
define( 'AMC_FILE', __FILE__ );
define( 'AMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload AMC\Some_Class from includes/class-some-class.php.
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'AMC\\' ) ) {
			return;
		}
		$name = strtolower( str_replace( '_', '-', substr( $class, 4 ) ) );
		$file = AMC_DIR . 'includes/class-' . $name . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once AMC_DIR . 'includes/template-functions.php';

register_activation_hook( __FILE__, array( 'AMC\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AMC\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'AMC\\Plugin', 'boot' ) );
