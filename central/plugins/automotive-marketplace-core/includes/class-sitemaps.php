<?php
/**
 * Adds marketplace URLs to the WordPress core XML sitemap (/wp-sitemap.xml).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Sitemaps {

	public static function init() {
		add_action(
			'init',
			static function () {
				if ( function_exists( 'wp_register_sitemap_provider' ) ) {
					wp_register_sitemap_provider( 'amcvehicles', new Sitemap_Provider( 'amcvehicles' ) );
					wp_register_sitemap_provider( 'amcdealers', new Sitemap_Provider( 'amcdealers' ) );
				}
			}
		);
	}
}

/**
 * One provider class serving both vehicles and dealers.
 */
final class Sitemap_Provider extends \WP_Sitemaps_Provider {

	const PER_PAGE = 1000;

	/**
	 * @param string $name Provider name: amcvehicles or amcdealers (letters only, core requirement).
	 */
	public function __construct( $name ) {
		$this->name        = $name;
		$this->object_type = $name;
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		global $wpdb;
		$v      = Database::table( 'vehicles' );
		$d      = Database::table( 'dealers' );
		$offset = ( max( 1, (int) $page_num ) - 1 ) * self::PER_PAGE;
		$urls   = array();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'amcvehicles' === $this->name ) {
			if ( 1 === (int) $page_num ) {
				foreach ( array_keys( Marketplace::presets() ) as $preset ) {
					$urls[] = array( 'loc' => amc_catalog_url( $preset ) );
				}
			}
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT v.slug, v.updated_at FROM $v v INNER JOIN $d d ON d.id = v.dealer_id
					WHERE d.status = 'active' AND v.status IN ('active','reserved') AND (d.sync_mode <> 'api' OR v.last_synced_at >= %s)
					ORDER BY v.id ASC LIMIT %d OFFSET %d",
					$this->cutoff(),
					self::PER_PAGE,
					$offset
				)
			);
			foreach ( (array) $rows as $row ) {
				$urls[] = array(
					'loc'     => amc_entity_url( 'vehicle', $row->slug ),
					'lastmod' => gmdate( 'c', strtotime( $row->updated_at . ' UTC' ) ),
				);
			}
		} else {
			if ( 1 === (int) $page_num ) {
				$urls[] = array( 'loc' => amc_dealers_url() );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT slug, updated_at FROM $d WHERE status = 'active' ORDER BY id ASC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ) );
			foreach ( (array) $rows as $row ) {
				$urls[] = array(
					'loc'     => amc_dealer_url( $row->slug ),
					'lastmod' => gmdate( 'c', strtotime( $row->updated_at . ' UTC' ) ),
				);
			}
		}
		// phpcs:enable
		return $urls;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		global $wpdb;
		$v = Database::table( 'vehicles' );
		$d = Database::table( 'dealers' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'amcvehicles' === $this->name ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $v v INNER JOIN $d d ON d.id = v.dealer_id
					WHERE d.status = 'active' AND v.status IN ('active','reserved') AND (d.sync_mode <> 'api' OR v.last_synced_at >= %s)",
					$this->cutoff()
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $d WHERE status = 'active'" );
		}
		// phpcs:enable
		return max( 1, (int) ceil( $total / self::PER_PAGE ) );
	}

	private function cutoff() {
		return gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * (int) Settings::get( 'hide_after_hours' ) );
	}
}
