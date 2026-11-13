<?php
/**
 * Template helpers.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

/** True when the marketplace plugin is active. */
function autobuzy_has_marketplace() {
	return defined( 'AMC_VERSION' );
}

/**
 * URL of a company page created by the plugin ('' when unavailable).
 *
 * @param string $key Page key, e.g. "contact".
 */
function autobuzy_page_url( $key ) {
	return autobuzy_has_marketplace() ? AMC\Content::page_url( $key ) : '';
}

/**
 * Inline icon (falls back to nothing without the plugin).
 *
 * @param string $name Icon name.
 * @param int    $size Size in pixels.
 */
function autobuzy_icon( $name, $size = 18 ) {
	return function_exists( 'amc_icon' ) ? amc_icon( $name, $size ) : '';
}

/**
 * Default navigation used until a menu is assigned in Appearance → Menus.
 *
 * @return array[] [ label, url ]
 */
function autobuzy_default_nav() {
	if ( ! autobuzy_has_marketplace() ) {
		return array();
	}
	return array_filter(
		array(
			array( __( 'For sale', 'autobuzy' ), amc_catalog_url( 'sale' ) ),
			array( __( 'For rent', 'autobuzy' ), amc_catalog_url( 'rent' ) ),
			array( __( 'Dealers', 'autobuzy' ), amc_dealers_url() ),
			array( __( 'Services', 'autobuzy' ), autobuzy_page_url( 'services' ) ),
			array( __( 'How it works', 'autobuzy' ), autobuzy_page_url( 'how-it-works' ) ),
		),
		static function ( $item ) {
			return '' !== $item[1];
		}
	);
}

/** Print the primary navigation list. */
function autobuzy_primary_nav() {
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'am-nav__list',
				'depth'          => 1,
			)
		);
		return;
	}
	$current = home_url( add_query_arg( array() ) );
	echo '<ul class="am-nav__list">';
	foreach ( autobuzy_default_nav() as $item ) {
		$active = untrailingslashit( strtok( $current, '?' ) ) === untrailingslashit( $item[1] );
		echo '<li><a href="' . esc_url( $item[1] ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $item[0] ) . '</a></li>';
	}
	echo '</ul>';
}

/** Site name or custom logo. */
function autobuzy_brand() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	echo '<a class="am-brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home"><span class="am-brand__mark" aria-hidden="true"></span>' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
}

/**
 * Section heading with an optional "see all" link.
 *
 * @param string $title Title.
 * @param string $url   Link URL.
 * @param string $label Link label.
 * @param string $lead  Optional intro.
 */
function autobuzy_section_head( $title, $url = '', $label = '', $lead = '' ) {
	echo '<div class="am-section__head"><div><h2 class="am-section__title">' . esc_html( $title ) . '</h2>';
	if ( $lead ) {
		echo '<p class="am-section__lead">' . esc_html( $lead ) . '</p>';
	}
	echo '</div>';
	if ( $url ) {
		echo '<a class="amds-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . autobuzy_icon( 'arrow', 16 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>';
}
