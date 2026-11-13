<?php
/**
 * Controlled vocabularies (same keys as the central marketplace so data can
 * be synchronised without translation).
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Labels {

	public static function fuel_types() {
		return array(
			'petrol'   => __( 'Petrol', 'automotive-dealer-crm' ),
			'diesel'   => __( 'Diesel', 'automotive-dealer-crm' ),
			'hybrid'   => __( 'Hybrid', 'automotive-dealer-crm' ),
			'electric' => __( 'Electric', 'automotive-dealer-crm' ),
			'lpg'      => __( 'LPG', 'automotive-dealer-crm' ),
		);
	}

	public static function transmissions() {
		return array(
			'automatic' => __( 'Automatic', 'automotive-dealer-crm' ),
			'manual'    => __( 'Manual', 'automotive-dealer-crm' ),
		);
	}

	public static function body_types() {
		return array(
			'sedan'       => __( 'Sedan', 'automotive-dealer-crm' ),
			'suv'         => __( 'SUV / 4x4', 'automotive-dealer-crm' ),
			'hatchback'   => __( 'Hatchback', 'automotive-dealer-crm' ),
			'pickup'      => __( 'Pickup', 'automotive-dealer-crm' ),
			'coupe'       => __( 'Coupe', 'automotive-dealer-crm' ),
			'wagon'       => __( 'Estate', 'automotive-dealer-crm' ),
			'van'         => __( 'Van / Minibus', 'automotive-dealer-crm' ),
			'convertible' => __( 'Convertible', 'automotive-dealer-crm' ),
		);
	}

	public static function conditions() {
		return array(
			'new'      => _x( 'New', 'vehicle condition', 'automotive-dealer-crm' ),
			'imported' => __( 'Imported used', 'automotive-dealer-crm' ),
			'used'     => __( 'Locally used', 'automotive-dealer-crm' ),
		);
	}

	public static function listing_types() {
		return array(
			'sale' => __( 'For sale', 'automotive-dealer-crm' ),
			'rent' => __( 'For rent', 'automotive-dealer-crm' ),
		);
	}

	public static function price_periods() {
		return array(
			'total' => '',
			'hour'  => __( 'hour', 'automotive-dealer-crm' ),
			'day'   => __( 'day', 'automotive-dealer-crm' ),
			'month' => __( 'month', 'automotive-dealer-crm' ),
		);
	}

	public static function vehicle_statuses() {
		return array(
			'draft'          => __( 'Draft', 'automotive-dealer-crm' ),
			'pending_review' => __( 'Pending review', 'automotive-dealer-crm' ),
			'published'      => __( 'Published', 'automotive-dealer-crm' ),
			'reserved'       => __( 'Reserved', 'automotive-dealer-crm' ),
			'sold'           => __( 'Sold', 'automotive-dealer-crm' ),
			'rented'         => __( 'Rented', 'automotive-dealer-crm' ),
			'archived'       => __( 'Archived', 'automotive-dealer-crm' ),
		);
	}

	public static function lead_statuses() {
		return array(
			'new'         => __( 'New', 'automotive-dealer-crm' ),
			'contacted'   => __( 'Contacted', 'automotive-dealer-crm' ),
			'interested'  => __( 'Interested', 'automotive-dealer-crm' ),
			'appointment' => __( 'Appointment', 'automotive-dealer-crm' ),
			'negotiation' => __( 'Negotiation', 'automotive-dealer-crm' ),
			'won'         => __( 'Won', 'automotive-dealer-crm' ),
			'lost'        => __( 'Lost', 'automotive-dealer-crm' ),
		);
	}

	public static function lead_sources() {
		return array(
			'website'     => __( 'Website', 'automotive-dealer-crm' ),
			'marketplace' => __( 'Marketplace', 'automotive-dealer-crm' ),
			'whatsapp'    => 'WhatsApp',
			'phone'       => __( 'Phone call', 'automotive-dealer-crm' ),
			'walk_in'     => __( 'Visit to the showroom', 'automotive-dealer-crm' ),
			'ads'         => __( 'Advertising campaign', 'automotive-dealer-crm' ),
			'referral'    => __( 'Referral', 'automotive-dealer-crm' ),
			'manual'      => __( 'Other', 'automotive-dealer-crm' ),
		);
	}

	public static function sale_statuses() {
		return array(
			'draft'     => __( 'Draft', 'automotive-dealer-crm' ),
			'confirmed' => __( 'Confirmed', 'automotive-dealer-crm' ),
			'cancelled' => __( 'Cancelled', 'automotive-dealer-crm' ),
		);
	}

	public static function rental_statuses() {
		return array(
			'quote'     => __( 'Quote', 'automotive-dealer-crm' ),
			'confirmed' => __( 'Confirmed', 'automotive-dealer-crm' ),
			'active'    => __( 'Vehicle out', 'automotive-dealer-crm' ),
			'returned'  => __( 'Returned', 'automotive-dealer-crm' ),
			'cancelled' => __( 'Cancelled', 'automotive-dealer-crm' ),
		);
	}

	public static function payment_statuses() {
		return array(
			'unpaid'  => __( 'Unpaid', 'automotive-dealer-crm' ),
			'partial' => __( 'Partly paid', 'automotive-dealer-crm' ),
			'paid'    => __( 'Paid', 'automotive-dealer-crm' ),
		);
	}

	public static function payment_methods() {
		return array(
			'cash'          => __( 'Cash', 'automotive-dealer-crm' ),
			'wave'          => 'Wave',
			'orange_money'  => 'Orange Money',
			'bank_transfer' => __( 'Bank transfer', 'automotive-dealer-crm' ),
			'cheque'        => __( 'Cheque', 'automotive-dealer-crm' ),
			'financing'     => __( 'Financing / credit', 'automotive-dealer-crm' ),
			'trade_in'      => __( 'Trade-in + balance', 'automotive-dealer-crm' ),
		);
	}

	public static function currencies() {
		return array(
			'XOF' => array( 'symbol' => 'FCFA', 'decimals' => 0 ),
			'EUR' => array( 'symbol' => '€', 'decimals' => 2 ),
			'USD' => array( 'symbol' => '$', 'decimals' => 2 ),
		);
	}

	public static function label( $vocabulary, $key ) {
		if ( ! method_exists( __CLASS__, $vocabulary ) ) {
			return $key;
		}
		$list = self::$vocabulary();
		return isset( $list[ $key ] ) ? $list[ $key ] : $key;
	}

	/** Badge tone for any status. */
	public static function tone( $status ) {
		$map = array(
			'success' => array( 'published', 'won', 'confirmed', 'paid', 'returned', 'done' ),
			'warning' => array( 'pending_review', 'reserved', 'new', 'quote', 'partial', 'scheduled', 'appointment', 'negotiation' ),
			'info'    => array( 'rented', 'active', 'contacted', 'interested' ),
			'danger'  => array( 'lost', 'cancelled', 'unpaid' ),
			'dark'    => array( 'sold' ),
		);
		foreach ( $map as $tone => $list ) {
			if ( in_array( $status, $list, true ) ) {
				return $tone;
			}
		}
		return 'neutral';
	}
}
