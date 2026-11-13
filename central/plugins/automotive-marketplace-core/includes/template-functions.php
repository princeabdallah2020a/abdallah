<?php
/**
 * Global helper functions available to templates and themes.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a marketplace template (theme-overridable through an "amc/" folder).
 *
 * @param string $name Template name, e.g. "parts/vehicle-card".
 * @param array  $vars Variables available in the template as $args.
 */
function amc_get_template( $name, array $vars = array() ) {
	$file = AMC\Marketplace::locate( $name );
	if ( $file ) {
		$args = $vars; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		include $file;
	}
}

/**
 * URL of a catalog view.
 *
 * @param string $preset all|sale|rent|featured.
 * @param array  $query  Extra query arguments (filters).
 */
function amc_catalog_url( $preset = 'all', array $query = array() ) {
	$presets = AMC\Marketplace::presets();
	$preset  = isset( $presets[ $preset ] ) ? $preset : 'all';
	if ( get_option( 'permalink_structure' ) ) {
		$url = home_url( user_trailingslashit( $presets[ $preset ] ) );
	} else {
		$url = add_query_arg(
			array(
				'amc_view'   => 'catalog',
				'amc_preset' => $preset,
			),
			home_url( '/' )
		);
	}
	return $query ? add_query_arg( array_map( 'rawurlencode', $query ), $url ) : $url;
}

/** URL of a marketplace entity page. */
function amc_entity_url( $view, $slug = '' ) {
	$paths = array(
		'vehicle' => 'car/' . $slug,
		'dealer'  => 'dealer/' . $slug,
		'dealers' => 'dealers',
	);
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( user_trailingslashit( $paths[ $view ] ) );
	}
	return add_query_arg(
		array_filter(
			array(
				'amc_view' => $view,
				'amc_slug' => $slug,
			)
		),
		home_url( '/' )
	);
}

function amc_vehicle_url( $vehicle ) {
	return amc_entity_url( 'vehicle', $vehicle->slug );
}

function amc_dealer_url( $dealer_slug ) {
	return amc_entity_url( 'dealer', $dealer_slug );
}

function amc_dealers_url() {
	return amc_entity_url( 'dealers' );
}

/**
 * Query arguments representing user-chosen filters (those forced by the
 * preset are omitted so URLs stay clean).
 *
 * @param array  $filters Clean filters.
 * @param string $preset  Catalog preset.
 */
function amc_filters_to_query( array $filters, $preset = 'all' ) {
	if ( 'sale' === $preset || 'rent' === $preset ) {
		unset( $filters['type'] );
	}
	if ( 'featured' === $preset ) {
		unset( $filters['featured'] );
	}
	if ( isset( $filters['sort'] ) && 'latest' === $filters['sort'] ) {
		unset( $filters['sort'] );
	}
	return $filters;
}

/**
 * Format an integer amount (minor units) with its currency, e.g. "12 500 000 FCFA".
 *
 * @param int    $amount   Amount in the currency's minor unit.
 * @param string $currency ISO 4217 code.
 */
function amc_format_money( $amount, $currency = 'XOF' ) {
	$currencies = AMC\Catalog::currencies();
	$config     = isset( $currencies[ $currency ] ) ? $currencies[ $currency ] : array(
		'symbol'   => $currency,
		'decimals' => 0,
	);
	$value      = $config['decimals'] ? $amount / pow( 10, $config['decimals'] ) : $amount;
	// Narrow no-break space as thousands separator (French typography).
	return number_format( $value, $config['decimals'], ',', "\u{202F}" ) . "\u{00A0}" . $config['symbol'];
}

/**
 * Price of a listing, including the rental period when relevant.
 *
 * @param object $vehicle Listing.
 */
function amc_vehicle_price( $vehicle ) {
	if ( $vehicle->price_amount <= 0 ) {
		return __( 'Price on request', 'automotive-marketplace-core' );
	}
	$price   = amc_format_money( $vehicle->price_amount, $vehicle->currency );
	$periods = AMC\Catalog::price_periods();
	if ( ! empty( $periods[ $vehicle->price_period ] ) ) {
		$price .= ' / ' . $periods[ $vehicle->price_period ];
	}
	return $price;
}

function amc_format_mileage( $km ) {
	return number_format( (int) $km, 0, ',', "\u{202F}" ) . "\u{00A0}km";
}

/**
 * Keep only digits and a leading "+".
 *
 * @param string $phone Raw phone number.
 */
function amc_normalize_phone( $phone ) {
	$phone = trim( (string) $phone );
	$plus  = 0 === strpos( $phone, '+' ) ? '+' : '';
	return substr( $plus . preg_replace( '/\D+/', '', $phone ), 0, 20 );
}

/**
 * WhatsApp click-to-chat link.
 *
 * @param string $number  Phone number in international format.
 * @param string $message Optional pre-filled message.
 */
function amc_whatsapp_url( $number, $message = '' ) {
	$digits = preg_replace( '/\D+/', '', (string) $number );
	if ( '' === $digits ) {
		return '';
	}
	$url = 'https://wa.me/' . $digits;
	return '' !== $message ? $url . '?text=' . rawurlencode( $message ) : $url;
}

/**
 * Human label for a vocabulary value.
 *
 * @param string $vocabulary fuel_types|transmissions|body_types|conditions|listing_types|statuses.
 * @param string $key        Stored value.
 */
function amc_label( $vocabulary, $key ) {
	return AMC\Catalog::label( $vocabulary, $key );
}

/**
 * Inline SVG icon (decorative: hidden from assistive technology).
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 */
function amc_icon( $name, $size = 18 ) {
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'gauge'    => '<path d="M12 14l4-4"/><path d="M3.3 19a10 10 0 1 1 17.4 0"/>',
		'fuel'     => '<path d="M3 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M2 21h12"/><path d="M5 9h6"/><path d="M13 11h2a2 2 0 0 1 2 2v3a2 2 0 0 0 4 0V8l-3-3"/>',
		'gearbox'  => '<circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><path d="M5 7v10M12 7v10M19 7v3a2 2 0 0 1-2 2H5"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'pin'      => '<path d="M12 21s7-6.1 7-11a7 7 0 0 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'shield'   => '<path d="M12 3l7 3v5c0 5-3.4 8.6-7 10-3.6-1.400-7-5-7-10V6l7-3z"/><path d="m9 12 2 2 4-4"/>',
		'phone'    => '<path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'chat'     => '<path d="M21 12a8 8 0 0 1-11.800 7L3 21l2-5.500A8 8 0 1 1 21 12z"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'car'      => '<path d="M5 11l1.500-4.500A2 2 0 0 1 8.400 5h7.200a2 2 0 0 1 1.900 1.500L19 11"/><rect x="3" y="11" width="18" height="6" rx="2"/><circle cx="7.500" cy="17.500" r="1.500"/><circle cx="16.500" cy="17.500" r="1.500"/>',
		'store'    => '<path d="M4 9l1.500-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M4 9a3 3 0 0 0 5.300 0 3 3 0 0 0 5.400 0A3 3 0 0 0 20 9"/><path d="M10 20v-6h4v6"/>',
		'star'     => '<path d="m12 3 2.700 5.700 6.300.800-4.600 4.300 1.200 6.200L12 17l-5.600 3 1.200-6.200L3 9.500l6.300-.800L12 3z"/>',
		'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'sync'     => '<path d="M4 12a8 8 0 0 1 14-5.300L20 9"/><path d="M20 4v5h-5"/><path d="M20 12a8 8 0 0 1-14 5.300L4 15"/><path d="M4 20v-5h5"/>',
		'users'    => '<circle cx="9" cy="8" r="3.500"/><path d="M2.500 20a6.500 6.500 0 0 1 13 0"/><path d="M16 4.700a3.500 3.500 0 0 1 0 6.600M18 14.500a6.500 6.500 0 0 1 3.500 5.500"/>',
		'chart'    => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'sparkle'  => '<path d="M12 3l1.800 4.700a2 2 0 0 0 1.200 1.200L19.500 10.500 15 12.200a2 2 0 0 0-1.200 1.200L12 18l-1.800-4.600A2 2 0 0 0 9 12.200L4.500 10.500 9 8.900a2 2 0 0 0 1.200-1.200L12 3z"/><path d="M19 15v4M17 17h4M5 3v3M3.500 4.500h3"/>',
		'send'     => '<path d="M4 12l16-8-6 16-3-6.500L4 12z"/><path d="M11 13.500 20 4"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$size = (int) $size;
	return '<svg class="amds-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Print a vehicle card.
 *
 * @param object $vehicle Hydrated listing.
 * @param array  $options { heading: string (h2|h3), eager: bool }.
 */
function amc_vehicle_card( $vehicle, array $options = array() ) {
	amc_get_template(
		'parts/vehicle-card',
		array(
			'vehicle' => $vehicle,
			'heading' => isset( $options['heading'] ) && 'h2' === $options['heading'] ? 'h2' : 'h3',
			'eager'   => ! empty( $options['eager'] ),
		)
	);
}

/**
 * Print a dealer card.
 *
 * @param object $dealer Public dealer profile.
 */
function amc_dealer_card( $dealer ) {
	amc_get_template( 'parts/dealer-card', array( 'dealer' => $dealer ) );
}

/**
 * Public helper for themes: search the catalog.
 *
 * @param array $filters  Raw filters (sanitised here).
 * @param int   $per_page Number of listings.
 * @return object[] Listings.
 */
function amc_get_vehicles( array $filters = array(), $per_page = 8 ) {
	$results = AMC\Vehicles::search( AMC\Vehicles::sanitize_filters( $filters ), 1, $per_page );
	return $results['items'];
}

/**
 * Public helper for themes: active dealers.
 *
 * @param int $limit Number of dealers.
 * @return object[] Dealers.
 */
function amc_get_dealers( $limit = 6 ) {
	$results = AMC\Dealers::search( array( 'limit' => $limit ) );
	return $results['items'];
}

/**
 * Print the vehicle search form (home hero and catalog sidebar share fields).
 *
 * @param array $options { layout: hero|sidebar, filters: array, preset: string }.
 */
function amc_search_form( array $options = array() ) {
	amc_get_template(
		'parts/filters',
		array(
			'layout'  => isset( $options['layout'] ) && 'hero' === $options['layout'] ? 'hero' : 'sidebar',
			'filters' => isset( $options['filters'] ) ? $options['filters'] : array(),
			'preset'  => isset( $options['preset'] ) ? $options['preset'] : 'all',
		)
	);
}

/** Initials used when a dealer has no logo. */
function amc_initials( $name ) {
	$words = preg_split( '/\s+/', trim( (string) $name ) );
	$out   = '';
	foreach ( array_slice( $words, 0, 2 ) as $word ) {
		$out .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}
	return $out;
}
