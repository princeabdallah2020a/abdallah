<?php
/**
 * Template helpers.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

function autodealer_has_crm() {
	return defined( 'ADC_VERSION' );
}

function autodealer_is_crm() {
	return autodealer_has_crm() && ADC\Router::is_manager();
}

function autodealer_icon( $name, $size = 18 ) {
	return function_exists( 'adc_icon' ) ? adc_icon( $name, $size ) : '';
}

/** Business identity with safe fallbacks when the plugin is missing. */
function autodealer_business() {
	if ( autodealer_has_crm() ) {
		return adc_business();
	}
	return (object) array(
		'name'      => get_bloginfo( 'name' ),
		'tagline'   => get_bloginfo( 'description' ),
		'address'   => '',
		'city'      => '',
		'country'   => '',
		'phone'     => '',
		'whatsapp'  => '',
		'email'     => '',
		'hours'     => '',
		'map_url'   => '',
		'facebook'  => '',
		'instagram' => '',
		'logo'      => '',
	);
}

/** Main navigation items: [ label, url, is_current ]. */
function autodealer_nav() {
	if ( ! autodealer_has_crm() ) {
		return array();
	}
	$view  = ADC\Router::view();
	$items = array(
		array( __( 'Home', 'autodealer' ), home_url( '/' ), is_front_page() ),
		array( __( 'Our vehicles', 'autodealer' ), adc_inventory_url(), in_array( $view, array( 'inventory', 'vehicle' ), true ) ),
	);
	if ( ADC\Settings::get( 'show_rentals' ) ) {
		$items[] = array( __( 'Rentals', 'autodealer' ), adc_page_url( 'rentals' ), 'rentals' === $view );
	}
	if ( ADC\Settings::get( 'show_financing' ) ) {
		$items[] = array( __( 'Financing', 'autodealer' ), adc_page_url( 'financing' ), 'financing' === $view );
	}
	$items[] = array( __( 'About', 'autodealer' ), adc_page_url( 'about' ), 'about' === $view );
	$items[] = array( __( 'Contact', 'autodealer' ), adc_page_url( 'contact' ), 'contact' === $view );
	return $items;
}

function autodealer_brand() {
	$b = autodealer_business();
	echo '<a class="ad-brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home">';
	if ( $b->logo ) {
		echo '<img class="ad-brand__logo" src="' . esc_url( $b->logo ) . '" alt="' . esc_attr( $b->name ) . '">';
	} else {
		echo '<span class="ad-brand__mark" aria-hidden="true">' . esc_html( function_exists( 'adc_initials' ) ? adc_initials( $b->name ) : mb_substr( $b->name, 0, 1 ) ) . '</span><span class="ad-brand__name">' . esc_html( $b->name ) . '</span>';
	}
	echo '</a>';
}
