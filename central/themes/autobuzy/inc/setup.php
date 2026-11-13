<?php
/**
 * Theme setup, assets and plugin dependency notice.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'autobuzy', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 320,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		register_nav_menus(
			array(
				'primary' => __( 'Main menu', 'autobuzy' ),
				'footer'  => __( 'Footer menu', 'autobuzy' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! wp_style_is( 'amds', 'registered' ) ) {
			wp_register_style( 'amds', get_template_directory_uri() . '/assets/css/design-system.css', array(), AUTOBUZY_VERSION );
		}
	},
	5
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'autobuzy', get_template_directory_uri() . '/assets/css/theme.css', array( 'amds' ), AUTOBUZY_VERSION );
		wp_enqueue_script( 'autobuzy', get_template_directory_uri() . '/assets/js/theme.js', array(), AUTOBUZY_VERSION, true );
	},
	20
);

// Preload the two self-hosted variable fonts (no third-party font requests).
add_action(
	'wp_head',
	static function () {
		foreach ( array( 'inter-latin-var.woff2', 'sora-latin-var.woff2' ) as $font ) {
			echo '<link rel="preload" href="' . esc_url( get_template_directory_uri() . '/assets/fonts/' . $font ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	},
	1
);

// Emoji script is not needed and costs a request.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

add_action(
	'admin_notices',
	static function () {
		if ( ! defined( 'AMC_VERSION' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The AutoBuzy theme requires the "Automotive Marketplace Core" plugin. Please install and activate it.', 'autobuzy' ) . '</p></div>';
		}
	}
);
