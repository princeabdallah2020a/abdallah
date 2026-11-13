<?php
/**
 * Subscription plans and commitment durations. Every price shown anywhere
 * comes from here: nothing is hardcoded in templates.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Plans {

	const DURATIONS_OPTION = 'amc_plan_durations';

	/** Commitment durations: months => discount percent. */
	public static function durations() {
		$saved = get_option( self::DURATIONS_OPTION, array() );
		$out   = array();
		foreach ( ( is_array( $saved ) && $saved ? $saved : array( 1 => 0, 3 => 5, 6 => 10, 12 => 15 ) ) as $months => $discount ) {
			$months = absint( $months );
			if ( $months >= 1 && $months <= 36 ) {
				$out[ $months ] = min( 90, max( 0, (float) $discount ) );
			}
		}
		ksort( $out );
		return $out ? $out : array( 1 => 0 );
	}

	public static function save_durations( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			$months = isset( $row['months'] ) ? absint( $row['months'] ) : 0;
			if ( $months >= 1 && $months <= 36 ) {
				$out[ $months ] = isset( $row['discount'] ) ? min( 90, max( 0, (float) $row['discount'] ) ) : 0;
			}
		}
		if ( ! isset( $out[1] ) ) {
			$out[1] = 0; // Monthly billing always exists.
		}
		ksort( $out );
		update_option( self::DURATIONS_OPTION, $out, false );
	}

	/**
	 * Plans, ordered for display.
	 *
	 * @param bool $active_only Only plans open for subscription.
	 */
	public static function all( $active_only = false ) {
		global $wpdb;
		$table = Database::table( 'plans' );
		$where = $active_only ? 'WHERE is_active = 1' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY sort_order ASC, price_amount ASC" );
		return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'plans' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function get_by_code( $code ) {
		global $wpdb;
		$table = Database::table( 'plans' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE code = %s", sanitize_key( $code ) ) );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function hydrate( $row ) {
		foreach ( array( 'id', 'price_amount', 'vehicle_limit', 'ai_quota', 'team_members', 'visibility_boost', 'is_highlighted', 'is_active', 'sort_order' ) as $key ) {
			$row->$key = (int) $row->$key;
		}
		$features      = json_decode( (string) $row->features, true );
		$row->features = is_array( $features ) ? $features : array();
		return $row;
	}

	/**
	 * Price of a plan for a commitment.
	 *
	 * @param object $plan   Plan.
	 * @param int    $months Duration.
	 * @return array { months, discount, subtotal, total, monthly, saving }
	 */
	public static function price( $plan, $months ) {
		$durations = self::durations();
		$months    = isset( $durations[ $months ] ) ? (int) $months : 1;
		$discount  = isset( $durations[ $months ] ) ? $durations[ $months ] : 0;
		$subtotal  = $plan->price_amount * $months;
		$total     = (int) round( $subtotal * ( 100 - $discount ) / 100 );
		return array(
			'months'   => $months,
			'discount' => $discount,
			'subtotal' => $subtotal,
			'total'    => $total,
			'monthly'  => (int) round( $total / $months ),
			'saving'   => $subtotal - $total,
		);
	}

	/**
	 * Create or update a plan.
	 *
	 * @param array $in Raw input.
	 * @param int   $id Plan id, 0 to create.
	 * @return int|\WP_Error
	 */
	public static function save( array $in, $id = 0 ) {
		global $wpdb;
		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'amc_plan', __( 'A plan needs a name.', 'automotive-marketplace-core' ) );
		}
		$features = array();
		if ( isset( $in['features'] ) ) {
			$lines    = is_array( $in['features'] ) ? $in['features'] : preg_split( '/\r\n|\r|\n/', (string) $in['features'] );
			$features = array_values( array_filter( array_map( 'sanitize_text_field', $lines ) ) );
		}
		$currency = isset( $in['currency'] ) && isset( Catalog::currencies()[ $in['currency'] ] ) ? $in['currency'] : Settings::get( 'default_currency' );
		$data     = array(
			'name'             => $name,
			'tagline'          => isset( $in['tagline'] ) ? sanitize_text_field( $in['tagline'] ) : '',
			'price_amount'     => isset( $in['price_amount'] ) ? absint( preg_replace( '/\D/', '', (string) $in['price_amount'] ) ) : 0,
			'currency'         => $currency,
			'vehicle_limit'    => isset( $in['vehicle_limit'] ) ? absint( $in['vehicle_limit'] ) : 0,
			'ai_quota'         => isset( $in['ai_quota'] ) ? absint( $in['ai_quota'] ) : 0,
			'team_members'     => isset( $in['team_members'] ) ? max( 1, absint( $in['team_members'] ) ) : 1,
			'features'         => wp_json_encode( array_slice( $features, 0, 30 ) ),
			'visibility_boost' => empty( $in['visibility_boost'] ) ? 0 : 1,
			'is_highlighted'   => empty( $in['is_highlighted'] ) ? 0 : 1,
			'is_active'        => empty( $in['is_active'] ) ? 0 : 1,
			'sort_order'       => isset( $in['sort_order'] ) ? (int) $in['sort_order'] : 0,
			'updated_at'       => current_time( 'mysql', true ),
		);
		$table = Database::table( 'plans' );
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
			return absint( $id );
		}
		$code = isset( $in['code'] ) && '' !== $in['code'] ? sanitize_key( $in['code'] ) : sanitize_key( $name );
		if ( self::get_by_code( $code ) ) {
			$code .= '-' . wp_generate_password( 4, false, false );
		}
		$data['code']       = strtolower( $code );
		$data['created_at'] = $data['updated_at'];
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	/** Initial plans (editable afterwards in Platform admin → Plans). */
	public static function seed() {
		global $wpdb;
		$table = Database::table( 'plans' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) > 0 ) {
			return;
		}
		$plans = array(
			array(
				'code'          => 'start',
				'name'          => 'Start',
				'tagline'       => 'Pour lancer votre présence en ligne',
				'price_amount'  => 15000,
				'vehicle_limit' => 10,
				'ai_quota'      => 100,
				'team_members'  => 1,
				'sort_order'    => 1,
				'features'      => array( 'Site web indépendant à votre nom', 'CRM concessionnaire', 'Jusqu’à 10 véhicules', 'Annonces sur la marketplace', 'Statistiques de base', 'Assistant IA (quota limité)' ),
			),
			array(
				'code'           => 'business',
				'name'           => 'Business',
				'tagline'        => 'Pour les concessions en croissance',
				'price_amount'   => 25000,
				'vehicle_limit'  => 40,
				'ai_quota'       => 500,
				'team_members'   => 5,
				'sort_order'     => 2,
				'is_highlighted' => 1,
				'features'       => array( 'Tout le pack Start', 'Jusqu’à 40 véhicules', 'Gestion des prospects', 'Gestion des ventes et des locations', 'Accès équipe', 'Statistiques avancées', 'Quota IA étendu' ),
			),
			array(
				'code'             => 'premium',
				'name'             => 'Premium',
				'tagline'          => 'Pour les grands comptes',
				'price_amount'     => 40000,
				'vehicle_limit'    => 100,
				'ai_quota'         => 2000,
				'team_members'     => 20,
				'sort_order'       => 3,
				'visibility_boost' => 1,
				'features'         => array( 'Tout le pack Business', 'Jusqu’à 100 véhicules', 'Gestion d’équipe avancée', 'Support prioritaire', 'Quota IA élevé', 'Visibilité premium sur la marketplace' ),
			),
		);
		foreach ( $plans as $plan ) {
			$plan['is_active'] = 1;
			$plan['currency']  = 'XOF';
			self::save( $plan );
		}
	}
}
