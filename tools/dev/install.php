<?php
/**
 * Installs WordPress in a local test site and activates the marketplace
 * plugin and theme. Local development only (weak, fixed credentials).
 *
 * Usage: php tools/dev/install.php <site-dir>
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
$site = rtrim( $argv[1] ?? '', '/' );
if ( ! is_file( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "Not a WordPress directory: $site\n" );
	exit( 1 );
}

define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']   = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_blog_installed() ) {
	wp_install( 'AutoMarket', 'admin', 'admin@example.test', true, '', 'local-dev-password' );
}
update_option( 'blogdescription', '' );
update_option( 'timezone_string', 'Africa/Dakar' );

global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );

$result = activate_plugin( 'automotive-marketplace-core/automotive-marketplace-core.php' );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'Plugin activation failed: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}
switch_theme( 'automarket' );
flush_rewrite_rules();

echo "Installed. Admin login: admin / local-dev-password\n";
