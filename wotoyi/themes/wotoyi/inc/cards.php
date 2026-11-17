<?php
/**
 * Card renderers (vehicle, service, dealer, badges). Input shapes = marketplace presenters (VehiclePresenter::card etc.);
 * every value is escaped here, missing values are simply not rendered.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

function wotoyi_badge( string $kind, string $label = '' ): string {
	$map = array(
		'demo'      => array( __( 'Démo', 'wotoyi' ), '' ),
		'sponsored' => array( __( 'Sponsorisé', 'wotoyi' ), '' ),
		'inspected' => array( __( 'Inspecté par Wotoyi', 'wotoyi' ), 'clipboard' ),
		'verified'  => array( __( 'Concessionnaire vérifié', 'wotoyi' ), 'shield' ),
		'venant'    => array( __( 'Venant', 'wotoyi' ), '' ),
		'cleared'   => array( __( 'Dédouané', 'wotoyi' ), 'check' ),
		'new'       => array( __( 'Neuf', 'wotoyi' ), '' ),
	);
	if ( ! isset( $map[ $kind ] ) ) {
		return '';
	}
	$text = '' !== $label ? $label : $map[ $kind ][0];
	$cls  = 'new' === $kind ? 'cleared' : $kind;
	return '<span class="wy-badge wy-badge--' . esc_attr( $cls ) . '">' . ( $map[ $kind ][1] ? wotoyi_icon( $map[ $kind ][1] ) : '' ) . esc_html( $text ) . '</span>';
}

function wotoyi_vehicle_url( array $v ): string {
	$url = isset( $v['url'] ) ? (string) $v['url'] : '';
	if ( '' === $url && ! empty( $v['slug'] ) ) {
		$url = home_url( '/vehicule/' . rawurlencode( (string) $v['slug'] ) . '/' );
	}
	return (string) apply_filters( 'wotoyi_theme_vehicle_url', $url, $v );
}

function wotoyi_vehicle_badges( array $v, bool $full = false ): string {
	$o = '';
	if ( ! empty( $v['demo'] ) ) {
		$o .= wotoyi_badge( 'demo' );
	}
	if ( ! empty( $v['featured'] ) ) {
		$o .= wotoyi_badge( 'sponsored' );
	}
	if ( ! empty( $v['wotoyi_inspected'] ) ) {
		$o .= wotoyi_badge( 'inspected' );
	}
	if ( $full && ! empty( $v['dealer']['verified'] ) ) {
		$o .= wotoyi_badge( 'verified' );
	}
	$cond = (string) ( $v['condition_type'] ?? '' );
	if ( 'imported' === $cond ) {
		$o .= wotoyi_badge( 'venant' );
	} elseif ( 'new' === $cond ) {
		$o .= wotoyi_badge( 'new' );
	} elseif ( $full && 'cleared' === (string) ( $v['customs_status'] ?? '' ) ) {
		$o .= wotoyi_badge( 'cleared' );
	}
	return $o;
}

/** Sale and/or rental price lines. */
function wotoyi_vehicle_prices( array $v, bool $big = false ): string {
	$cur = (string) ( $v['currency'] ?? 'XOF' );
	$h   = '';
	if ( ! empty( $v['price'] ) ) {
		$h .= '<span class="wy-price' . ( $big ? ' wy-price--l' : '' ) . '">' . esc_html( wotoyi_price( $v['price'], $cur ) ) . '</span>';
	}
	if ( ! empty( $v['rental_price_day'] ) ) {
		/* translators: %s: formatted price */
		$h .= '<span class="wy-price wy-price--rent">' . esc_html( sprintf( __( '%s / jour', 'wotoyi' ), wotoyi_price( $v['rental_price_day'], $cur ) ) ) . '</span>';
	}
	if ( ! empty( $v['rental_price_month'] ) && empty( $v['price'] ) ) {
		/* translators: %s: formatted price */
		$h .= '<span class="wy-price wy-price--sub">' . esc_html( sprintf( __( '%s / mois', 'wotoyi' ), wotoyi_price( $v['rental_price_month'], $cur ) ) ) . '</span>';
	}
	if ( '' === $h ) {
		$h = '<span class="wy-price wy-price--sub">' . esc_html__( 'Prix sur demande', 'wotoyi' ) . '</span>';
	}
	return $h;
}

/** @param array<string,mixed> $v Presenter card. @param array<string,mixed> $o eager(bool), rent(bool). */
function wotoyi_vehicle_card( array $v, array $o = array() ): string {
	static $vt_used = array();
	$title = trim( (string) ( $v['title'] ?? '' ) );
	if ( '' === $title ) {
		$title = trim( implode( ' ', array_filter( array( $v['make'] ?? '', $v['model'] ?? '' ) ) ) );
	}
	if ( '' === $title ) {
		return '';
	}
	$id   = (string) ( $v['id'] ?? $v['slug'] ?? md5( $title ) );
	$href = wotoyi_vehicle_url( $v );
	$vt   = 'wy-v-' . preg_replace( '/[^a-z0-9_-]/i', '', $id );
	$vts  = isset( $vt_used[ $vt ] ) ? '' : ' style="--vt:' . esc_attr( $vt ) . '"';
	$vt_used[ $vt ] = true;

	$img = '';
	if ( ! empty( $v['cover_image'] ) ) {
		$img = '<img src="' . esc_url( (string) $v['cover_image'] ) . '" width="660" height="400" alt="' . esc_attr( $title ) . '" loading="' . ( ! empty( $o['eager'] ) ? 'eager' : 'lazy' ) . '" decoding="async">';
	} else {
		$img = '<span class="wy-vcard__ph" role="img" aria-label="' . esc_attr__( 'Photo bientôt disponible', 'wotoyi' ) . '">' . wotoyi_icon( 'camera' ) . '</span>';
	}

	$meta = array();
	if ( ! empty( $v['year'] ) ) {
		$meta[] = (string) (int) $v['year'];
	}
	if ( 'new' === ( $v['condition_type'] ?? '' ) ) {
		$meta[] = wotoyi_label( 'cond', 'new' );
	} elseif ( isset( $v['mileage_km'] ) && '' !== $v['mileage_km'] ) {
		$meta[] = wotoyi_number( $v['mileage_km'] ) . "\u{00A0}km";
	}
	if ( ! empty( $v['fuel_type'] ) ) {
		$meta[] = wotoyi_label( 'fuel', (string) $v['fuel_type'] );
	}
	if ( ! empty( $v['transmission'] ) ) {
		$meta[] = wotoyi_label( 'trans', (string) $v['transmission'] );
	}
	$loc = trim( implode( ' · ', array_filter( array( (string) ( $v['city'] ?? '' ), (string) ( $v['dealer']['name'] ?? '' ) ) ) ) );

	$h  = '<article class="wy-vcard' . ( ! empty( $o['cls'] ) ? ' ' . esc_attr( $o['cls'] ) : '' ) . '" data-id="' . esc_attr( $id ) . '">';
	$h .= '<div class="wy-vcard__sheen" aria-hidden="true"></div>';
	$h .= '<div class="wy-vcard__media"' . $vts . '>' . $img . '<div class="wy-vcard__badges">' . wotoyi_vehicle_badges( $v ) . '</div></div>';
	$h .= '<button class="wy-fav" type="button" data-wy-fav="' . esc_attr( $id ) . '" aria-pressed="false" aria-label="' . esc_attr__( 'Ajouter aux favoris', 'wotoyi' ) . '">' . wotoyi_icon( 'heart' ) . '<span class="wy-fav__p" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span></button>';
	$h .= '<div class="wy-vcard__body"><h3 class="wy-vcard__title"><a href="' . esc_url( $href ) . '">' . esc_html( $title ) . '</a></h3>';
	if ( ! empty( $v['trim_level'] ) ) {
		$h .= '<p class="wy-vcard__ver"><bdi>' . esc_html( (string) $v['trim_level'] ) . '</bdi></p>';
	}
	if ( $meta ) {
		$h .= '<ul class="wy-vcard__meta"><li>' . implode( '</li><li>', array_map( static fn( $m ) => '<bdi>' . esc_html( $m ) . '</bdi>', $meta ) ) . '</li></ul>';
	}
	$h .= '<div class="wy-vcard__prices">' . wotoyi_vehicle_prices( $v ) . '</div>';
	if ( '' !== $loc ) {
		$h .= '<p class="wy-vcard__loc">' . wotoyi_icon( 'pin' ) . '<span>' . esc_html( $loc ) . '</span></p>';
	}
	return $h . '</div></article>';
}

/** @param array<string,mixed> $s title, description, city, dealer, url, icon */
function wotoyi_service_card( array $s ): string {
	$t = trim( (string) ( $s['title'] ?? '' ) );
	if ( '' === $t ) {
		return '';
	}
	$icon = in_array( (string) ( $s['icon'] ?? '' ), array( 'wrench', 'steering', 'drop', 'bolt', 'shield', 'clipboard', 'globe', 'tag' ), true ) ? (string) $s['icon'] : 'wrench';
	$h    = '<article class="wy-scard">' . wotoyi_icon( $icon );
	$h   .= '<h3 class="wy-h4">' . ( ! empty( $s['url'] ) ? '<a href="' . esc_url( (string) $s['url'] ) . '">' . esc_html( $t ) . '</a>' : esc_html( $t ) ) . '</h3>';
	if ( ! empty( $s['description'] ) ) {
		$h .= '<p>' . esc_html( wp_trim_words( (string) $s['description'], 24 ) ) . '</p>';
	}
	$by = trim( implode( ' · ', array_filter( array( (string) ( $s['dealer'] ?? '' ), (string) ( $s['city'] ?? '' ) ) ) ) );
	if ( '' !== $by ) {
		$h .= '<p class="wy-caption wy-muted">' . wotoyi_icon( 'pin' ) . esc_html( $by ) . '</p>';
	}
	return $h . '</article>';
}

/** @param array<string,mixed> $d name, slug/url, city, verified, logo_url, count */
function wotoyi_dealer_card( array $d ): string {
	$n = trim( (string) ( $d['name'] ?? '' ) );
	if ( '' === $n ) {
		return '';
	}
	$url  = (string) apply_filters( 'wotoyi_theme_dealer_url', (string) ( $d['url'] ?? '' ), $d );
	$logo = ! empty( $d['logo_url'] ) ? '<span class="wy-dcard__logo" aria-hidden="true"><img src="' . esc_url( (string) $d['logo_url'] ) . '" alt="" width="52" height="52" loading="lazy" decoding="async"></span>' : '<span class="wy-dcard__logo" aria-hidden="true">' . esc_html( wotoyi_initials( $n ) ) . '</span>';
	$h    = '<article class="wy-dcard">' . $logo . '<h3 class="wy-h4">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $n ) . '</a>' : esc_html( $n ) ) . '</h3>';
	if ( ! empty( $d['verified'] ) ) {
		$h .= '<div class="wy-badges">' . wotoyi_badge( 'verified' ) . '</div>';
	}
	if ( ! empty( $d['city'] ) ) {
		$h .= '<p class="wy-caption wy-muted">' . wotoyi_icon( 'pin' ) . esc_html( (string) $d['city'] ) . '</p>';
	}
	if ( isset( $d['count'] ) && (int) $d['count'] > 0 ) {
		/* translators: %s: number of vehicles */
		$h .= '<p class="wy-caption"><strong>' . esc_html( wotoyi_number( $d['count'] ) ) . '</strong> ' . esc_html( _n( 'véhicule', 'véhicules', (int) $d['count'], 'wotoyi' ) ) . '</p>';
	}
	return $h . '</article>';
}

/** Branded empty state (graceful no-plugin / no-data rendering). */
function wotoyi_empty_state( string $title, string $text, array $actions = array() ): string {
	$h  = '<div class="wy-empty"><svg class="wy-empty__art" viewBox="0 0 120 80" aria-hidden="true"><circle cx="60" cy="40" r="26" class="wy-empty__sun"/><path d="M8 52h104"/><path d="M26 62h68M40 72h40"/></svg>';
	$h .= '<h2 class="wy-h3">' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p>';
	if ( $actions ) {
		$h .= '<div class="wy-row wy-row--center">';
		foreach ( $actions as $a ) {
			$h .= '<a class="wy-btn ' . esc_attr( $a['class'] ?? 'wy-btn--ghost' ) . '" href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['label'] ) . '</a>';
		}
		$h .= '</div>';
	}
	return $h . '</div>';
}
