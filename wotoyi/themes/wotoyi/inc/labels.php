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
			'electric'      => __( 'Électrique', 'wotoyi' ),
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
			'coupe'       => __( 'Coupé', 'wotoyi' ),
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
			'imported' => __( 'Importé', 'wotoyi' ),
		),
		'drive'   => array(
			'fwd' => __( 'Traction', 'wotoyi' ),
			'rwd' => __( 'Propulsion', 'wotoyi' ),
			'4x4' => '4x4',
			'awd' => 'AWD',
		),
		'colour'  => array(
			'white' => __( 'Blanc', 'wotoyi' ), 'black' => __( 'Noir', 'wotoyi' ), 'grey' => __( 'Gris', 'wotoyi' ), 'silver' => __( 'Argent', 'wotoyi' ), 'blue' => __( 'Bleu', 'wotoyi' ),
			'red' => __( 'Rouge', 'wotoyi' ), 'green' => __( 'Vert', 'wotoyi' ), 'brown' => __( 'Marron', 'wotoyi' ), 'beige' => __( 'Beige', 'wotoyi' ), 'yellow' => __( 'Jaune', 'wotoyi' ),
			'orange' => __( 'Orange', 'wotoyi' ), 'gold' => __( 'Or', 'wotoyi' ), 'purple' => __( 'Violet', 'wotoyi' ), 'other' => __( 'Autre', 'wotoyi' ),
		),
		'uph'     => array(
			'leather' => __( 'Cuir', 'wotoyi' ), 'fabric' => __( 'Tissu', 'wotoyi' ), 'alcantara' => __( 'Alcantara', 'wotoyi' ), 'vinyl' => __( 'Vinyle', 'wotoyi' ), 'mixed' => __( 'Mixte', 'wotoyi' ), 'other' => __( 'Autre', 'wotoyi' ),
		),
		'customs' => array(
			'cleared' => __( 'Dédouané', 'wotoyi' ), 'in_transit' => __( 'En transit', 'wotoyi' ), 'not_applicable' => __( 'Non applicable', 'wotoyi' ),
		),
		'equip'   => array(
			'air_conditioning' => __( 'Climatisation', 'wotoyi' ), 'climate_control' => __( 'Climatisation automatique', 'wotoyi' ), 'heated_seats' => __( 'Sièges chauffants', 'wotoyi' ), 'ventilated_seats' => __( 'Sièges ventilés', 'wotoyi' ),
			'electric_seats' => __( 'Sièges électriques', 'wotoyi' ), 'cruise_control' => __( 'Régulateur de vitesse', 'wotoyi' ), 'keyless_entry' => __( 'Accès sans clé', 'wotoyi' ), 'power_windows' => __( 'Vitres électriques', 'wotoyi' ),
			'power_tailgate' => __( 'Hayon électrique', 'wotoyi' ), 'massage_seats' => __( 'Sièges massants', 'wotoyi' ), 'airbags_front' => __( 'Airbags avant', 'wotoyi' ), 'airbags_side' => __( 'Airbags latéraux', 'wotoyi' ),
			'airbags_curtain' => __( 'Airbags rideaux', 'wotoyi' ), 'abs' => 'ABS', 'esp' => 'ESP', 'isofix' => 'Isofix', 'tpms' => __( 'Contrôle de pression des pneus', 'wotoyi' ), 'alarm' => __( 'Alarme', 'wotoyi' ),
			'immobilizer' => __( 'Antidémarrage', 'wotoyi' ), 'gps' => 'GPS', 'bluetooth' => 'Bluetooth', 'carplay_android_auto' => 'CarPlay / Android Auto', 'usb' => 'USB', 'premium_audio' => __( 'Audio premium', 'wotoyi' ),
			'rear_screens' => __( 'Écrans arrière', 'wotoyi' ), 'wireless_charging' => __( 'Charge sans fil', 'wotoyi' ), 'sunroof' => __( 'Toit ouvrant', 'wotoyi' ), 'panoramic_roof' => __( 'Toit panoramique', 'wotoyi' ),
			'alloy_wheels' => __( 'Jantes alliage', 'wotoyi' ), 'roof_rails' => __( 'Barres de toit', 'wotoyi' ), 'tow_bar' => __( 'Attelage', 'wotoyi' ), 'tinted_windows' => __( 'Vitres teintées', 'wotoyi' ),
			'running_boards' => __( 'Marchepieds', 'wotoyi' ), 'leather_seats' => __( 'Sièges cuir', 'wotoyi' ), 'seven_seats' => __( '7 places', 'wotoyi' ), 'third_row' => __( 'Troisième rangée', 'wotoyi' ),
			'folding_rear_seats' => __( 'Banquette rabattable', 'wotoyi' ), 'ambient_lighting' => __( 'Éclairage d’ambiance', 'wotoyi' ), 'sport_seats' => __( 'Sièges sport', 'wotoyi' ), 'led_headlights' => __( 'Phares LED', 'wotoyi' ),
			'xenon' => __( 'Xénon', 'wotoyi' ), 'adaptive_headlights' => __( 'Phares adaptatifs', 'wotoyi' ), 'fog_lights' => __( 'Antibrouillards', 'wotoyi' ), 'drl' => __( 'Feux de jour', 'wotoyi' ),
			'rear_camera' => __( 'Caméra de recul', 'wotoyi' ), 'camera_360' => __( 'Caméra 360°', 'wotoyi' ), 'parking_sensors_front' => __( 'Radars avant', 'wotoyi' ), 'parking_sensors_rear' => __( 'Radars arrière', 'wotoyi' ),
			'lane_assist' => __( 'Maintien de voie', 'wotoyi' ), 'blind_spot' => __( 'Angle mort', 'wotoyi' ), 'adaptive_cruise' => __( 'Régulateur adaptatif', 'wotoyi' ), 'auto_emergency_braking' => __( 'Freinage d’urgence', 'wotoyi' ),
			'auto_parking' => __( 'Stationnement automatique', 'wotoyi' ),
		),
	);
	return isset( $map[ $group ][ $code ] ) ? (string) $map[ $group ][ $code ] : (string) $code;
}
