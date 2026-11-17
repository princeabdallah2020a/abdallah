<?php
/**
 * Plugin Name:       Wotoyi Demo (DONNEES FICTIVES)
 * Description:       DEMO ONLY. Loads/removes clearly labelled fictional dealers, vehicles and services through the Wotoyi Marketplace API, and adds a read-only Dealers list. Not for production data.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Wotoyi
 * License:           GPL-2.0-or-later
 * Text Domain:       wotoyi-demo
 *
 * @package Wotoyi\Demo
 */

defined( 'ABSPATH' ) || exit;

define( 'WOTOYI_DEMO_VERSION', '0.1.0' );
define( 'WOTOYI_DEMO_DIR', plugin_dir_path( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Wotoyi Demo requiert PHP 8.1 ou plus.', 'wotoyi-demo' ) . '</p></div>';
		}
	);
	return;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Wotoyi\\Demo\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = WOTOYI_DEMO_DIR . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);

\Wotoyi\Demo\Admin::boot();
