<?php
/**
 * Uninstall: data is removed only if the administrator opted in (setting delete_on_uninstall).
 *
 * @package Wotoyi\Marketplace
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

define( 'WOTOYI_MP_DIR', plugin_dir_path( __FILE__ ) );
require_once WOTOYI_MP_DIR . 'includes/autoload.php';

if ( \Wotoyi\Marketplace\Settings::get( 'delete_on_uninstall' ) ) {
	\Wotoyi\Marketplace\Installer::drop_all();
}
