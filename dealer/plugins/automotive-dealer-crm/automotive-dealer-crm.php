<?php
/**
 * Plugin Name:       AutoBuzy Dealer CRM
 * Description:       Independent dealership CRM (vehicles, leads, customers, sales, rentals, team) and public inventory for the dealer's own website.
 * Version:           0.3.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            AutoBuzy
 * License:           GPL-2.0-or-later
 * Text Domain:       automotive-dealer-crm
 * Domain Path:       /languages
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

define( 'ADC_VERSION', '0.3.0' );
define( 'ADC_DB_VERSION', 1 );
define( 'ADC_FILE', __FILE__ );
define( 'ADC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'ADC\\' ) ) {
			return;
		}
		$file = ADC_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', substr( $class, 4 ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once ADC_DIR . 'includes/template-functions.php';

register_activation_hook( __FILE__, array( 'ADC\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ADC\\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'ADC\\Plugin', 'boot' ) );
