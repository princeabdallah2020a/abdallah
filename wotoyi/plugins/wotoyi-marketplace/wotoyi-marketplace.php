<?php
/**
 * Plugin Name:       Wotoyi Marketplace
 * Description:       Marketplace core: vehicle and service catalogue, maximum search, dealers, and the signed receiver for dealer content.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Wotoyi
 * License:           GPL-2.0-or-later
 * Text Domain:       wotoyi-marketplace
 *
 * @package Wotoyi\Marketplace
 */

defined( 'ABSPATH' ) || exit;

define( 'WOTOYI_MP_VERSION', '0.2.0' );
define( 'WOTOYI_MP_DB_VERSION', '2' );
define( 'WOTOYI_MP_FILE', __FILE__ );
define( 'WOTOYI_MP_DIR', plugin_dir_path( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Wotoyi Marketplace requires PHP 8.1 or higher.', 'wotoyi-marketplace' )
				. '</p></div>';
		}
	);
	return;
}

require_once WOTOYI_MP_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( \Wotoyi\Marketplace\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Wotoyi\Marketplace\Installer::class, 'deactivate' ) );

\Wotoyi\Marketplace\Plugin::boot();
