<?php
/**
 * Controlled vocabularies shared by the catalog, the API and (later) the sync layer.
 *
 * Keys are stable machine values stored in the database and exchanged through
 * the API. Labels are translatable.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Catalog {

	public static function fuel_types() {
		return array(
			'petrol'   => __( 'Petrol', 'automotive-marketplace-core' ),
			'diesel'   => __( 'Diesel', 'automotive-marketplace-core' ),
			'hybrid'   => __( 'Hybrid', 'automotive-marketplace-core' ),
			'electric' => __( 'Electric', 'automotive-marketplace-core' ),
			'lpg'      => __( 'LPG', 'automotive-marketplace-core' ),
		);
	}

	public static function transmissions() {
		return array(
			'automatic' => __( 'Automatic', 'automotive-marketplace-core' ),
			'manual'    => __( 'Manual', 'automotive-marketplace-core' ),
		);
	}

	public static function body_types() {
		return array(
			'sedan'       => __( 'Sedan', 'automotive-marketplace-core' ),
			'suv'         => __( 'SUV / 4x4', 'automotive-marketplace-core' ),
			'hatchback'   => __( 'Hatchback', 'automotive-marketplace-core' ),
			'pickup'      => __( 'Pickup', 'automotive-marketplace-core' ),
			'coupe'       => __( 'Coupe', 'automotive-marketplace-core' ),
			'wagon'       => __( 'Estate', 'automotive-marketplace-core' ),
			'van'         => __( 'Van / Minibus', 'automotive-marketplace-core' ),
			'convertible' => __( 'Convertible', 'automotive-marketplace-core' ),
		);
	}

	public static function conditions() {
		return array(
			'new'      => __( 'New', 'automotive-marketplace-core' ),
			'imported' => __( 'Imported used', 'automotive-marketplace-core' ),
			'used'     => __( 'Locally used', 'automotive-marketplace-core' ),
		);
	}

	public static function listing_types() {
		return array(
			'sale' => __( 'For sale', 'automotive-marketplace-core' ),
			'rent' => __( 'For rent', 'automotive-marketplace-core' ),
		);
	}

	/** Statuses a marketplace listing can have. Only "active" and "reserved" appear in results. */
	public static function statuses() {
		return array(
			'active'      => __( 'Available', 'automotive-marketplace-core' ),
			'reserved'    => __( 'Reserved', 'automotive-marketplace-core' ),
			'sold'        => __( 'Sold', 'automotive-marketplace-core' ),
			'rented'      => __( 'Rented', 'automotive-marketplace-core' ),
			'unpublished' => __( 'Unpublished', 'automotive-marketplace-core' ),
		);
	}

	public static function dealer_statuses() {
		return array( 'pending', 'under_review', 'approved', 'active', 'suspended', 'expired', 'cancelled' );
	}

	public static function price_periods() {
		return array(
			'total' => '',
			'hour'  => __( 'per hour', 'automotive-marketplace-core' ),
			'day'   => __( 'per day', 'automotive-marketplace-core' ),
			'month' => __( 'per month', 'automotive-marketplace-core' ),
		);
	}

	public static function sort_options() {
		return array(
			'latest'      => __( 'Latest', 'automotive-marketplace-core' ),
			'price_asc'   => __( 'Lowest price', 'automotive-marketplace-core' ),
			'price_desc'  => __( 'Highest price', 'automotive-marketplace-core' ),
			'mileage_asc' => __( 'Lowest mileage', 'automotive-marketplace-core' ),
			'featured'    => __( 'Featured', 'automotive-marketplace-core' ),
		);
	}

	/**
	 * Supported currencies: symbol and number of decimals. Amounts are always
	 * stored as integers in the currency's minor unit (XOF has none).
	 */
	public static function currencies() {
		return apply_filters(
			'amc_currencies',
			array(
				'XOF' => array( 'symbol' => 'FCFA', 'decimals' => 0 ),
				'EUR' => array( 'symbol' => '€', 'decimals' => 2 ),
				'USD' => array( 'symbol' => '$', 'decimals' => 2 ),
			)
		);
	}

	/**
	 * Translated label for a vocabulary key, or the raw key if unknown.
	 *
	 * @param string $vocabulary fuel_types|transmissions|body_types|conditions|listing_types|statuses.
	 * @param string $key        Stored value.
	 */
	public static function label( $vocabulary, $key ) {
		if ( ! method_exists( __CLASS__, $vocabulary ) ) {
			return $key;
		}
		$labels = self::$vocabulary();
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}
}
