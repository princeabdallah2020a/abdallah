<?php
/**
 * French labels for catalogue enum codes (source language of the theme is French; translations via wotoyi-i18n / .po files).
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

function wotoyi_label( string $group, ?string $code ): string {
	if ( null === $code || '' === $code ) {
		return '';
	}
	$map = array(
		'fuel'    => array(
			'petrol'        => __( 'Essence', 'wotoyi' ),
			'diesel'        => __( 'Diesel', 'wotoyi' ),
			'hybrid'        => __( 'Hybride', 'wotoyi' ),
			'plugin_hybrid' => __( 'Hybride rechargeable', 'wotoyi' ),
			'electric'      => __( 'Electrique', 'wotoyi' ),
			'lpg'           => __( 'GPL', 'wotoyi' ),
			'other'         => __( 'Autre', 'wotoyi' ),
		),
		'trans'   => array(
			'manual'         => __( 'Manuelle', 'wotoyi' ),
			'automatic'      => __( 'Automatique', 'wotoyi' ),
			'cvt'            => __( 'Automatique (CVT)', 'wotoyi' ),
			'semi_automatic' => __( 'Semi-automatique', 'wotoyi' ),
		),
		'body'    => array(
			'sedan'       => __( 'Berline', 'wotoyi' ),
			'suv'         => __( 'SUV', 'wotoyi' ),
			'hatchback'   => __( 'Citadine', 'wotoyi' ),
			'wagon'       => __( 'Break', 'wotoyi' ),
			'coupe'       => __( 'Coupe', 'wotoyi' ),
			'convertible' => __( 'Cabriolet', 'wotoyi' ),
			'pickup'      => __( 'Pick-up', 'wotoyi' ),
			'van'         => __( 'Utilitaire', 'wotoyi' ),
			'minivan'     => __( 'Monospace', 'wotoyi' ),
			'truck'       => __( 'Camion', 'wotoyi' ),
			'bus'         => __( 'Bus', 'wotoyi' ),
			'motorcycle'  => __( 'Moto', 'wotoyi' ),
			'other'       => __( 'Autre', 'wotoyi' ),
		),
		'cond'    => array(
			'new'      => __( 'Neuf', 'wotoyi' ),
			'used'     => __( 'Occasion', 'wotoyi' ),
			'imported' => __( 'Venant', 'wotoyi' ),
		),
		'drive'   => array(
			'fwd' => __( 'Traction', 'wotoyi' ),
			'rwd' => __( 'Propulsion', 'wotoyi' ),
			'4x4' => '4x4',
			'awd' => 'AWD',
		),
	);
	return isset( $map[ $group ][ $code ] ) ? (string) $map[ $group ][ $code ] : (string) $code;
}
