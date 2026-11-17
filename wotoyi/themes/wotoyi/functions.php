<?php
/**
 * Wotoyi block theme bootstrap.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'WOTOYI_THEME_VERSION', '2.0.0' );
define( 'WOTOYI_THEME_DIR', get_template_directory() );
define( 'WOTOYI_THEME_URI', get_template_directory_uri() );

foreach ( array( 'helpers', 'labels', 'setup', 'assets', 'split', 'cards', 'blocks' ) as $wotoyi_inc ) {
	require_once WOTOYI_THEME_DIR . '/inc/' . $wotoyi_inc . '.php';
}
unset( $wotoyi_inc );
