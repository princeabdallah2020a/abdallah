<?php
/**
 * Global helpers for templates and for the dealer theme.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

/** Business/website setting. */
function adc_setting( $key ) {
	return ADC\Settings::get( $key );
}

function adc_normalize_phone( $phone ) {
	$phone = trim( (string) $phone );
	$plus  = 0 === strpos( $phone, '+' ) ? '+' : '';
	return substr( $plus . preg_replace( '/\D+/', '', $phone ), 0, 20 );
}

function adc_whatsapp_url( $number, $message = '' ) {
	$digits = preg_replace( '/\D+/', '', (string) $number );
	if ( '' === $digits ) {
		return '';
	}
	return 'https://wa.me/' . $digits . ( '' !== $message ? '?text=' . rawurlencode( $message ) : '' );
}

function adc_money( $amount, $currency = '' ) {
	$currency   = $currency ? $currency : ADC\Settings::get( 'currency' );
	$currencies = ADC\Labels::currencies();
	$config     = isset( $currencies[ $currency ] ) ? $currencies[ $currency ] : array(
		'symbol'   => $currency,
		'decimals' => 0,
	);
	$value      = $config['decimals'] ? $amount / pow( 10, $config['decimals'] ) : $amount;
	return number_format( (float) $value, $config['decimals'], ',', "\u{202F}" ) . "\u{00A0}" . $config['symbol'];
}

/** Price of a vehicle with its period ("30 000 FCFA / day"). */
function adc_vehicle_price( $vehicle ) {
	if ( (int) $vehicle->price_amount <= 0 ) {
		return __( 'Price on request', 'automotive-dealer-crm' );
	}
	$periods = ADC\Labels::price_periods();
	$price   = adc_money( $vehicle->price_amount, $vehicle->currency );
	return ! empty( $periods[ $vehicle->price_period ] ) ? $price . ' / ' . $periods[ $vehicle->price_period ] : $price;
}

function adc_km( $km ) {
	return number_format( (int) $km, 0, ',', "\u{202F}" ) . "\u{00A0}km";
}

/** Local date/time display of a UTC datetime. */
function adc_date( $utc, $with_time = false ) {
	if ( ! $utc ) {
		return '—';
	}
	return wp_date( get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' ), strtotime( $utc . ' UTC' ) );
}

/** "2026-11-20T09:30" (local, for datetime-local inputs) from a UTC datetime. */
function adc_input_datetime( $utc ) {
	return $utc ? wp_date( 'Y-m-d\TH:i', strtotime( $utc . ' UTC' ) ) : '';
}

/** UTC datetime from a local datetime-local value ('' if invalid). */
function adc_utc_from_local( $local ) {
	$local = str_replace( 'T', ' ', (string) $local );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $local ) ) {
		return '';
	}
	return get_gmt_from_date( substr( $local, 0, 16 ) . ':00' );
}

function adc_label( $vocabulary, $key ) {
	return ADC\Labels::label( $vocabulary, $key );
}

function adc_badge( $status, $vocabulary = '' ) {
	$label = $vocabulary ? adc_label( $vocabulary, $status ) : $status;
	return '<span class="amds-badge amds-badge--' . esc_attr( ADC\Labels::tone( $status ) ) . '">' . esc_html( $label ) . '</span>';
}

/* ---------- URLs ---------- */

function adc_url( $path ) {
	return get_option( 'permalink_structure' ) ? home_url( user_trailingslashit( $path ) ) : add_query_arg( 'adc_path', $path, home_url( '/' ) );
}

function adc_inventory_url( array $query = array(), $rentals = false ) {
	$url = adc_url( $rentals ? 'rentals' : 'inventory' );
	return $query ? add_query_arg( array_map( 'rawurlencode', $query ), $url ) : $url;
}

function adc_vehicle_url( $vehicle ) {
	return adc_url( 'vehicle/' . $vehicle->slug );
}

function adc_page_url( $page ) {
	return adc_url( $page );
}

/**
 * CRM URL.
 *
 * @param string     $section Section.
 * @param int|string $item    Id or "new".
 * @param array      $args    Query arguments.
 */
function adc_manager_url( $section = '', $item = '', array $args = array() ) {
	$path = 'manager' . ( $section ? '/' . $section : '' ) . ( $item ? '/' . $item : '' );
	$url  = adc_url( $path );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/* ---------- Business ---------- */

/** Public business identity used by the theme and SEO. */
function adc_business() {
	$s = ADC\Settings::all();
	return (object) array(
		'name'      => $s['business_name'] ? $s['business_name'] : get_bloginfo( 'name' ),
		'tagline'   => $s['tagline'],
		'address'   => $s['address'],
		'city'      => $s['city'],
		'country'   => $s['country'],
		'phone'     => $s['phone'],
		'whatsapp'  => $s['whatsapp'] ? $s['whatsapp'] : $s['phone'],
		'email'     => $s['email'],
		'hours'     => $s['hours'],
		'map_url'   => $s['map_url'],
		'facebook'  => $s['facebook'],
		'instagram' => $s['instagram'],
		'logo'      => $s['logo_id'] ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '',
	);
}

function adc_initials( $name ) {
	$out = '';
	foreach ( array_slice( preg_split( '/\s+/', trim( (string) $name ) ), 0, 2 ) as $word ) {
		$out .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}
	return $out;
}

/** Inline SVG icon (decorative). */
function adc_icon( $name, $size = 18 ) {
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'sync'     => '<path d="M20 11a8 8 0 0 0-14.5-4.5L3 9"/><path d="M3 4v5h5"/><path d="M4 13a8 8 0 0 0 14.5 4.5L21 15"/><path d="M21 20v-5h-5"/>',
		'sparkle'  => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 17l.7 1.9 1.8.6-1.8.7L19 22l-.7-1.8-1.8-.7 1.8-.6z"/>',
		'gauge'    => '<path d="M12 14l4-4"/><path d="M3.3 19a10 10 0 1 1 17.4 0"/>',
		'fuel'     => '<path d="M3 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M2 21h12"/><path d="M5 9h6"/><path d="M13 11h2a2 2 0 0 1 2 2v3a2 2 0 0 0 4 0V8l-3-3"/>',
		'gearbox'  => '<circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><path d="M5 7v10M12 7v10M19 7v3a2 2 0 0 1-2 2H5"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'pin'      => '<path d="M12 21s7-6.1 7-11a7 7 0 0 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'chat'     => '<path d="M21 12a8 8 0 0 1-11.8 7L3 21l2-5.5A8 8 0 1 1 21 12z"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'car'      => '<path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11"/><rect x="3" y="11" width="18" height="6" rx="2"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>',
		'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'chart'    => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
		'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M15 8l2 2"/>',
		'tag'      => '<path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'flag'     => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
		'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'file'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6z"/><path d="M14 3v6h6"/>',
		'shield'   => '<path d="M12 3l7 3v5c0 5-3.4 8.6-7 10-3.6-1.4-7-5-7-10V6l7-3z"/><path d="m9 12 2 2 4-4"/>',
		'star'     => '<path d="m12 3 2.7 5.7 6.3.8-4.6 4.3 1.2 6.2L12 17l-5.6 3 1.2-6.2L3 9.5l6.3-.8L12 3z"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="amds-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Render a plugin template (theme can override in "adc/").
 *
 * @param string $name Template name, e.g. "public/parts/vehicle-card".
 * @param array  $vars Variables, available as $args.
 */
function adc_template( $name, array $vars = array() ) {
	$file = ADC\Router::locate( $name );
	if ( $file ) {
		$args = $vars; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		include $file;
	}
}

/** Public vehicles for theme sections. */
function adc_get_vehicles( array $filters = array(), $limit = 6 ) {
	return ADC\Vehicles::search_public( $filters, 1, $limit )['items'];
}
