<?php
/**
 * Theme setup: supports, editor styles, block styles, pattern categories, head script (theme + motion tier).
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'wotoyi', WOTOYI_THEME_DIR . '/languages' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
		add_editor_style( array( 'assets/dist/critical-default.css', 'assets/css/blocks.css' ) );
		remove_theme_support( 'core-block-patterns' );
	}
);

add_action(
	'init',
	static function (): void {
		register_block_pattern_category( 'wotoyi-home', array( 'label' => __( 'Wotoyi - Accueil', 'wotoyi' ) ) );
		register_block_pattern_category( 'wotoyi-page', array( 'label' => __( 'Wotoyi - Pages', 'wotoyi' ) ) );

		$styles = array(
			'core/group'     => array(
				array( 'wy-sand', __( 'Sable', 'wotoyi' ) ),
				array( 'wy-night', __( 'Nuit', 'wotoyi' ) ),
				array( 'wy-card', __( 'Carte', 'wotoyi' ) ),
				array( 'wy-section', __( 'Section', 'wotoyi' ) ),
			),
			'core/paragraph' => array(
				array( 'wy-lead', __( 'Chapeau', 'wotoyi' ) ),
				array( 'wy-overline', __( 'Surtitre', 'wotoyi' ) ),
				array( 'wy-caption', __( 'Legende', 'wotoyi' ) ),
			),
			'core/heading'   => array(
				array( 'wy-display-xl', __( 'Display XL', 'wotoyi' ) ),
				array( 'wy-display-l', __( 'Display L', 'wotoyi' ) ),
				array( 'wy-display-m', __( 'Display M', 'wotoyi' ) ),
			),
			'core/button'    => array(
				array( 'wy-ghost', __( 'Contour', 'wotoyi' ) ),
				array( 'wy-secondary', __( 'Teal', 'wotoyi' ) ),
				array( 'wy-whatsapp', __( 'WhatsApp', 'wotoyi' ) ),
			),
			'core/list'      => array(
				array( 'wy-checks', __( 'Coches', 'wotoyi' ) ),
			),
		);
		foreach ( $styles as $block => $list ) {
			foreach ( $list as $s ) {
				register_block_style( $block, array( 'name' => $s[0], 'label' => $s[1] ) );
			}
		}
		// Public query var so the marketplace plugin can mark virtual routes.
		add_filter(
			'query_vars',
			static function ( array $v ): array {
				$v[] = 'wotoyi_view';
				return $v;
			}
		);
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'wy-page-' . wotoyi_view();
		return $classes;
	}
);

/**
 * <1 kB head script: theme (light/dark/auto), motion tier (full/lite/reduced) before CSS paints (no flash). DESIGN 1.6.
 * Language and direction are owned by WordPress (language_attributes) and the wotoyi-i18n plugin.
 */
add_action(
	'wp_head',
	static function (): void {
		echo '<script id="wy-head">(function(){try{var d=document.documentElement,s=localStorage,m=matchMedia,q=location.search,p=s.getItem("wy-theme")||"auto",t=p==="auto"?(m("(prefers-color-scheme: dark)").matches?"dark":"light"):p,c=navigator.connection||{},lite=(navigator.hardwareConcurrency||8)<=4||(navigator.deviceMemory||8)<=2||c.saveData||/2g/.test(c.effectiveType||""),mo=(q.match(/[?&]motion=(full|lite|reduced)/)||[])[1]||s.getItem("wy-motion")||"auto";if(mo==="auto")mo=m("(prefers-reduced-motion: reduce)").matches?"reduced":lite?"lite":"full";var th=(q.match(/[?&]theme=(light|dark)/)||[])[1];if(th){t=th;p=th}d.dataset.theme=t;d.dataset.themePref=p;d.dataset.motion=mo;d.classList.add("wy-js")}catch(e){}})();</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string.
	},
	0
);

add_action(
	'wp_head',
	static function (): void {
		echo '<meta name="color-scheme" content="light dark">' . "\n";
		echo '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 64 64\'%3E%3Crect width=\'64\' height=\'64\' rx=\'14\' fill=\'%2306080F\'/%3E%3Ccircle cx=\'32\' cy=\'32\' r=\'17\' fill=\'%23F2A93B\'/%3E%3Crect x=\'12\' y=\'36\' width=\'40\' height=\'3\' fill=\'%2306080F\'/%3E%3C/svg%3E">' . "\n";
	},
	1
);
