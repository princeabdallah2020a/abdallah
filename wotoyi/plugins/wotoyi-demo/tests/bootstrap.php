<?php
/**
 * Demo plugin tests reuse the marketplace test shim (mini WordPress + SQLite $wpdb) and add only the few
 * WordPress functions the demo plugin needs. Run:  php tests/run.php [filter]
 */

require_once dirname( __DIR__, 2 ) . '/wotoyi-marketplace/tests/bootstrap.php';

define( 'WOTOYI_DEMO_VERSION', 'test' );
define( 'WOTOYI_DEMO_DIR', dirname( __DIR__ ) . '/' );

spl_autoload_register(
	static function ( string $c ): void {
		if ( 0 === strpos( $c, 'Wotoyi\\Demo\\' ) ) {
			require_once WOTOYI_DEMO_DIR . 'includes/' . substr( $c, 12 ) . '.php';
		}
	}
);

/* Test-only stubs (the marketplace shim does not provide them). */
$GLOBALS['wotoyi_admin_menus'] = array();
function delete_transient( string $k ): bool { unset( $GLOBALS['wotoyi_transients'][ $k ] ); return true; }
function get_theme_root_uri( string $t = '' ): string { return 'https://demo.example.com/wp-content/themes'; }
function get_theme_root( string $t = '' ): string { return dirname( __DIR__, 3 ) . '/themes'; }
function sanitize_key( string $k ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function absint( $v ): int { return abs( (int) $v ); }
function esc_attr( string $s ): string { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_url( string $s ): string { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_js( string $s ): string { return addslashes( $s ); }
function admin_url( string $p = '' ): string { return 'https://demo.example.com/wp-admin/' . $p; }
function add_query_arg( array $a, string $u = '' ): string { return $u . '?' . http_build_query( $a ); }
function wp_nonce_field( string $a ): void { echo '<input type="hidden" name="_wpnonce" value="n-' . esc_attr( $a ) . '">'; }
function add_menu_page( ...$a ): void { $GLOBALS['wotoyi_admin_menus'][] = array( 'menu', $a ); }
function add_submenu_page( ...$a ): void { $GLOBALS['wotoyi_admin_menus'][] = array( 'sub', $a ); }
function add_management_page( ...$a ): void { $GLOBALS['wotoyi_admin_menus'][] = array( 'tools', $a ); }
function wp_die( $m = '', $t = '', $a = array() ): void { throw new RuntimeException( 'wp_die:' . ( $a['response'] ?? 0 ) ); }
