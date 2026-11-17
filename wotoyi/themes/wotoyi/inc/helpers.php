<?php
/**
 * Small helpers: view detection, escaping wrappers, icons, URLs, money.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which public view is being rendered: home | search | vehicle | dealer | blog | page | 404.
 * The marketplace plugin may override with the `wotoyi_theme_view` filter (virtual routes).
 */
function wotoyi_view(): string {
	static $cache = array();
	$key = (int) get_queried_object_id() . '|' . (string) get_query_var( 'wotoyi_view' );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$qv = (string) get_query_var( 'wotoyi_view' );
	if ( in_array( $qv, array( 'search', 'vehicle', 'dealer' ), true ) ) {
		$view = $qv;
	} elseif ( is_404() ) {
		$view = '404';
	} elseif ( is_front_page() ) {
		$view = 'home';
	} elseif ( is_singular( 'wotoyi_vehicle' ) ) {
		$view = 'vehicle';
	} elseif ( is_singular( 'wotoyi_dealer' ) ) {
		$view = 'dealer';
	} elseif ( is_post_type_archive( 'wotoyi_vehicle' ) || is_search() ) {
		$view = 'search';
	} elseif ( is_home() || is_archive() || is_singular( 'post' ) ) {
		$view = 'blog';
	} else {
		$view = 'page';
	}
	$view          = (string) apply_filters( 'wotoyi_theme_view', $view );
	$cache[ $key ] = $view;
	return $view;
}

/** Public URLs used by header, footer and sections. Filterable so the plugin can point to its real routes. */
function wotoyi_urls(): array {
	$archive = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( 'wotoyi_vehicle' ) : false;
	$search  = $archive ? $archive : home_url( '/vehicules/' );
	$urls    = array(
		'home'       => home_url( '/' ),
		'search'     => $search,
		'rent'       => add_query_arg( 'offer_type', 'rental', $search ),
		'services'   => home_url( '/#services' ),
		'dealers'    => home_url( '/#dealers' ),
		'inspection' => home_url( '/#inspection' ),
		'dealer_cta' => home_url( '/#dealer-cta' ),
		'pro'        => '',
		'account'    => '',
		'favourites' => '',
		'safety'     => '',
		'terms'      => '',
		'privacy'    => '',
		'notices'    => '',
		'contact'    => '',
	);
	return (array) apply_filters( 'wotoyi_theme_urls', $urls );
}

function wotoyi_url( string $key ): string {
	$u = wotoyi_urls();
	return isset( $u[ $key ] ) ? (string) $u[ $key ] : '';
}

/** Inline SVG icon from the sprite printed by the chrome block. */
function wotoyi_icon( string $name, string $class = '' ): string {
	return '<svg class="wy-i ' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"><use href="#i-' . esc_attr( $name ) . '"/></svg>';
}

/** Price with Western digits and a narrow no-break space as thousands separator (design decision: Western digits in AR UI). */
function wotoyi_price( $amount, ?string $currency = 'XOF' ): string {
	$n   = number_format( (float) $amount, 0, '', "\u{202F}" );
	$cur = strtoupper( (string) $currency );
	if ( '' === $cur || 'XOF' === $cur || 'XAF' === $cur ) {
		return $n . "\u{00A0}FCFA";
	}
	return $n . "\u{00A0}" . $cur;
}

function wotoyi_number( $n ): string {
	return number_format( (float) $n, 0, '', "\u{202F}" );
}

/** Render a template file from inc/views/ and return its HTML. Variables are passed in $vars. */
function wotoyi_view_html( string $name, array $vars = array() ): string {
	$file = WOTOYI_THEME_DIR . '/inc/views/' . $name . '.php';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- local template scope only.
	extract( $vars, EXTR_SKIP );
	ob_start();
	include $file;
	return (string) ob_get_clean();
}

/** True when the marketplace plugin is active (constant defined by wotoyi-marketplace). */
function wotoyi_marketplace_active(): bool {
	return (bool) apply_filters( 'wotoyi_theme_marketplace_active', defined( 'WOTOYI_MP_VERSION' ) );
}

/** Safe initials for dealer logo fallbacks. */
function wotoyi_initials( string $name ): string {
	$parts = preg_split( '/\s+/u', trim( $name ) ) ?: array();
	$out   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$out .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
	}
	return '' === $out ? 'W' : $out;
}

/** Section heading with eyebrow (shared by dynamic section blocks). */
function wotoyi_section_head( string $id, string $eyebrow, string $title, string $link_label = '', string $link_url = '', string $level = 'h2' ): string {
	$h  = '<header class="wy-sec-head"><div>';
	$h .= '' !== $eyebrow ? '<p class="wy-overline">' . esc_html( $eyebrow ) . '</p>' : '';
	$h .= '<' . $level . ' id="' . esc_attr( $id ) . '" class="wy-display-l" data-motion="split">' . wotoyi_split_words( esc_html( $title ), true ) . '</' . $level . '></div>';
	if ( '' !== $link_label && '' !== $link_url ) {
		$h .= '<a class="wy-link wy-link--arrow" href="' . esc_url( $link_url ) . '">' . esc_html( $link_label ) . ' ' . wotoyi_icon( 'arrow-right', 'wy-flip' ) . '</a>';
	}
	return $h . '</header>';
}
