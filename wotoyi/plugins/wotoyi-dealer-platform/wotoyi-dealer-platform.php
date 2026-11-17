<?php
/**
 * Plugin Name:       Wotoyi Dealer Platform
 * Description:       Dealer platform core: tenant-isolated CRM, vehicles master, services, sales and rentals, REST API for Wotoyi Pro, and the signed sync engine to the Wotoyi Marketplace.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Wotoyi
 * License:           GPL-2.0-or-later
 * Text Domain:       wotoyi-dealer-platform
 *
 * @package Wotoyi\DealerPlatform
 */

defined( 'ABSPATH' ) || exit;

define( 'WOTOYI_DP_VERSION', '0.1.0' );
define( 'WOTOYI_DP_DB_VERSION', '2' );
define( 'WOTOYI_DP_FILE', __FILE__ );
define( 'WOTOYI_DP_DIR', plugin_dir_path( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Wotoyi Dealer Platform requires PHP 8.1 or higher.', 'wotoyi-dealer-platform' )
				. '</p></div>';
		}
	);
	return;
}

require_once WOTOYI_DP_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( \Wotoyi\DealerPlatform\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Wotoyi\DealerPlatform\Installer::class, 'deactivate' ) );

\Wotoyi\DealerPlatform\Plugin::boot();
