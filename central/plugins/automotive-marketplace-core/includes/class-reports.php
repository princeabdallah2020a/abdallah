<?php
/**
 * Platform growth reports (staff): last 12 months.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Reports {

	/**
	 * Monthly series.
	 *
	 * @return array month (Y-m) => [ dealers, revenue, leads, whatsapp, listings ]
	 */
	public static function monthly() {
		global $wpdb;
		$since  = gmdate( 'Y-m-01 00:00:00', strtotime( '-11 months', strtotime( gmdate( 'Y-m-01' ) ) ) );
		$months = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$months[ gmdate( 'Y-m', strtotime( '-' . $i . ' months', strtotime( gmdate( 'Y-m-01' ) ) ) ) ] = array(
				'dealers'  => 0,
				'revenue'  => 0,
				'leads'    => 0,
				'whatsapp' => 0,
				'listings' => 0,
			);
		}
		$series = array(
			'dealers'  => 'SELECT SUBSTR(created_at, 1, 7) AS m, COUNT(*) AS n FROM ' . Database::table( 'dealers' ) . ' WHERE is_demo = 0 AND created_at >= %s GROUP BY m',
			'revenue'  => 'SELECT SUBSTR(verified_at, 1, 7) AS m, SUM(amount) AS n FROM ' . Database::table( 'payments' ) . " WHERE status = 'verified' AND verified_at >= %s GROUP BY m",
			'leads'    => 'SELECT SUBSTR(created_at, 1, 7) AS m, COUNT(*) AS n FROM ' . Database::table( 'marketplace_leads' ) . ' WHERE created_at >= %s GROUP BY m',
			'whatsapp' => 'SELECT SUBSTR(created_at, 1, 7) AS m, COUNT(*) AS n FROM ' . Database::table( 'wa_messages' ) . " WHERE direction = 'in' AND created_at >= %s GROUP BY m",
			'listings' => 'SELECT SUBSTR(created_at, 1, 7) AS m, COUNT(*) AS n FROM ' . Database::table( 'vehicles' ) . ' WHERE is_demo = 0 AND created_at >= %s GROUP BY m',
		);
		foreach ( $series as $key => $sql ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $since ) ) as $row ) {
				if ( isset( $months[ $row->m ] ) ) {
					$months[ $row->m ][ $key ] = (int) $row->n;
				}
			}
		}
		return $months;
	}

	/** Dealers ranked by marketplace requests received (90 days). */
	public static function top_dealers( $limit = 10 ) {
		global $wpdb;
		$l = Database::table( 'marketplace_leads' );
		$d = Database::table( 'dealers' );
		$v = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.business_name, COUNT(l.id) AS leads, (SELECT COUNT(*) FROM $v v WHERE v.dealer_id = d.id AND v.status IN ('active','reserved')) AS listings FROM $d d LEFT JOIN $l l ON l.dealer_id = d.id AND l.created_at >= %s WHERE d.is_demo = 0 AND d.status = 'active' GROUP BY d.id, d.business_name ORDER BY leads DESC, listings DESC LIMIT %d", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ), absint( $limit ) ) );
		return $rows ? $rows : array();
	}
}
