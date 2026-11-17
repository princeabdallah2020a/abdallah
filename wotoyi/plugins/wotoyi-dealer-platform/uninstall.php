<?php
/**
 * Uninstall: keeps all dealer data unless the administrator opted in (setting delete_on_uninstall = true).
 *
 * @package Wotoyi\DealerPlatform
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

define( 'WOTOYI_DP_DB_VERSION', '1' );
require_once __DIR__ . '/includes/autoload.php';

$settings = get_option( 'wotoyi_dp_settings', array() );
if ( is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] ) ) {
	\Wotoyi\DealerPlatform\Installer::drop_all();
}
