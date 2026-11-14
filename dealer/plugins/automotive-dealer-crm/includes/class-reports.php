<?php
/**
 * Business reports of the dealership (managers: adc_view_reports).
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Reports {

	public static function periods() {
		return array(
			30  => __( 'Last 30 days', 'automotive-dealer-crm' ),
			90  => __( 'Last 90 days', 'automotive-dealer-crm' ),
			365 => __( 'Last 12 months', 'automotive-dealer-crm' ),
		);
	}

	/**
	 * All figures for a period.
	 *
	 * @param int $days Period length.
	 */
	public static function build( $days ) {
		global $wpdb;
		$days  = isset( self::periods()[ $days ] ) ? (int) $days : 30;
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$l     = Database::table( 'leads' );
		$s     = Database::table( 'sales' );
		$v     = Database::table( 'vehicles' );
		$r     = Database::table( 'rentals' );

		// Leads by source with conversion.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sources = (array) $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) AS total, SUM(status = 'won') AS won, SUM(status = 'lost') AS lost FROM $l WHERE created_at >= %s GROUP BY source ORDER BY total DESC", $since ) );

		// Sales and margin.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sales = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(s.sale_price), 0) AS revenue, COALESCE(SUM(CASE WHEN v.purchase_price > 0 THEN s.sale_price - v.purchase_price ELSE 0 END), 0) AS margin, SUM(v.purchase_price > 0) AS with_cost FROM $s s LEFT JOIN $v v ON v.id = s.vehicle_id WHERE s.status = 'confirmed' AND s.sold_on >= %s", substr( $since, 0, 10 ) ) );

		// Average days between publication and sale.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sold = (array) $wpdb->get_results( $wpdb->prepare( "SELECT published_at, sold_at FROM $v WHERE sold_at >= %s AND published_at IS NOT NULL", $since ) );
		$age  = array();
		foreach ( $sold as $row ) {
			$age[] = max( 0, ( strtotime( $row->sold_at . ' UTC' ) - strtotime( $row->published_at . ' UTC' ) ) / DAY_IN_SECONDS );
		}

		// Stock waiting for more than 60 days.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$aging = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, title, price_amount, currency, published_at FROM $v WHERE status IN ('published','reserved') AND listing_type = 'sale' AND published_at < %s ORDER BY published_at ASC LIMIT 10", gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ) );

		// Rentals: revenue and fleet utilisation over the period.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rentals = (array) $wpdb->get_results( $wpdb->prepare( "SELECT starts_at, ends_at, total_amount FROM $r WHERE status IN ('confirmed','active','returned') AND ends_at > %s AND starts_at < %s", $since, gmdate( 'Y-m-d H:i:s' ) ) );
		$rented  = 0;
		$rent_in = 0;
		$from    = time() - $days * DAY_IN_SECONDS;
		foreach ( $rentals as $row ) {
			$start   = max( $from, strtotime( $row->starts_at . ' UTC' ) );
			$end     = min( time(), strtotime( $row->ends_at . ' UTC' ) );
			$rented += max( 0, $end - $start );
			if ( strtotime( $row->starts_at . ' UTC' ) >= $from ) {
				$rent_in += (int) $row->total_amount;
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fleet = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $v WHERE listing_type = 'rent' AND status IN ('published','reserved','rented')" );

		// Team: leads won per person.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$team = (array) $wpdb->get_results( $wpdb->prepare( "SELECT assigned_to, COUNT(*) AS total, SUM(status = 'won') AS won FROM $l WHERE created_at >= %s AND assigned_to > 0 GROUP BY assigned_to ORDER BY won DESC, total DESC LIMIT 10", $since ) );

		$total_leads = array_sum( wp_list_pluck( $sources, 'total' ) );
		$won_leads   = array_sum( wp_list_pluck( $sources, 'won' ) );
		return array(
			'days'        => $days,
			'leads'       => $total_leads,
			'conversion'  => $total_leads ? round( 100 * $won_leads / $total_leads, 1 ) : 0,
			'sources'     => $sources,
			'sales'       => (int) $sales->n,
			'revenue'     => (int) $sales->revenue,
			'margin'      => (int) $sales->margin,
			'with_cost'   => (int) $sales->with_cost,
			'days_to_sell' => $age ? round( array_sum( $age ) / count( $age ) ) : null,
			'aging'       => $aging,
			'rent_income' => $rent_in,
			'utilisation' => $fleet ? min( 100, round( 100 * $rented / ( $fleet * $days * DAY_IN_SECONDS ), 1 ) ) : null,
			'fleet'       => $fleet,
			'team'        => $team,
		);
	}
}
