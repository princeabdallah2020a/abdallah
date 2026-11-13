<?php
/**
 * Theme setup and assets.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'autodealer', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		register_nav_menus( array( 'primary' => __( 'Main menu', 'autodealer' ) ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'amds', get_template_directory_uri() . '/assets/css/design-system.css', array(), AUTODEALER_VERSION );
		wp_enqueue_style( 'autodealer-fonts', get_template_directory_uri() . '/assets/css/fonts.css', array(), AUTODEALER_VERSION );
		if ( autodealer_is_crm() ) {
			return; // The CRM has its own interface.
		}
		wp_enqueue_style( 'autodealer', get_template_directory_uri() . '/assets/css/theme.css', array( 'amds' ), AUTODEALER_VERSION );
		wp_enqueue_script( 'autodealer', get_template_directory_uri() . '/assets/js/theme.js', array(), AUTODEALER_VERSION, true );
	},
	4
);

add_action(
	'wp_head',
	static function () {
		foreach ( array( 'inter-latin-var.woff2', 'sora-latin-var.woff2' ) as $font ) {
			echo '<link rel="preload" href="' . esc_url( get_template_directory_uri() . '/assets/fonts/' . $font ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	},
	1
);

add_filter(
	'body_class',
	static function ( $classes ) {
		$classes[] = 'ad-preset-' . sanitize_html_class( autodealer_has_crm() ? (string) ADC\Settings::get( 'preset' ) : 'classic' );
		return $classes;
	}
);

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

add_action(
	'admin_notices',
	static function () {
		if ( ! autodealer_has_crm() && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The AutoDealer theme requires the "AutoBuzy Dealer CRM" plugin. Please install and activate it.', 'autodealer' ) . '</p></div>';
		}
	}
);
