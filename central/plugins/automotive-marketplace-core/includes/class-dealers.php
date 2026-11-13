<?php
/**
 * Dealers repository. Public methods only ever return the public profile
 * fields: private data (contact email, linked user, internal status history)
 * never leaves this class through the public catalog.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Dealers {

	/** Columns that may be shown publicly. */
	const PUBLIC_COLUMNS = 'd.id, d.slug, d.business_name, d.logo_url, d.description, d.address, d.city, d.city_slug, d.country_code, d.phone, d.whatsapp, d.website_url, d.is_verified, d.is_demo, d.created_at';

	/**
	 * Active dealers with their number of visible listings.
	 *
	 * @param array $args { city?: string, q?: string, limit?: int, offset?: int, verified_first?: bool }
	 * @return array { items: object[], total: int }
	 */
	public static function search( array $args = array() ) {
		global $wpdb;
		$d = Database::table( 'dealers' );
		$v = Database::table( 'vehicles' );

		$where  = array( "d.status = 'active'" );
		$params = array();
		if ( ! empty( $args['city'] ) ) {
			$where[]  = 'd.city_slug = %s';
			$params[] = sanitize_title( $args['city'] );
		}
		if ( ! empty( $args['q'] ) ) {
			$where[]  = 'd.business_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['q'] ) ) . '%';
		}
		$where_sql = implode( ' AND ', $where );
		$limit     = isset( $args['limit'] ) ? min( 100, max( 1, (int) $args['limit'] ) ) : 24;
		$offset    = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$cutoff    = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * (int) Settings::get( 'hide_after_hours' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM $d d WHERE $where_sql";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$sql = 'SELECT ' . self::PUBLIC_COLUMNS . ",
			(SELECT COUNT(*) FROM $v v WHERE v.dealer_id = d.id AND v.status IN ('active','reserved')
				AND (d.sync_mode <> 'api' OR v.last_synced_at >= %s)) AS vehicle_count
			FROM $d d WHERE $where_sql
			ORDER BY d.is_verified DESC, vehicle_count DESC, d.business_name ASC
			LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $cutoff ), $params, array( $limit, $offset ) ) ) );
		// phpcs:enable

		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * Public profile of an active dealer.
	 *
	 * @param string $slug Dealer slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		return self::get_public( 'd.slug = %s', sanitize_title( $slug ) );
	}

	public static function get_public_by_id( $id ) {
		return self::get_public( 'd.id = %d', absint( $id ) );
	}

	private static function get_public( $clause, $value ) {
		global $wpdb;
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT ' . self::PUBLIC_COLUMNS . " FROM $d d WHERE $clause AND d.status = 'active'", $value ) );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function hydrate( $row ) {
		$row->id          = (int) $row->id;
		$row->is_verified = (bool) (int) $row->is_verified;
		if ( isset( $row->vehicle_count ) ) {
			$row->vehicle_count = (int) $row->vehicle_count;
		}
		return $row;
	}

	/**
	 * Create a dealer record. Internal use (demo importer now, onboarding in Phase 2).
	 *
	 * @param array $input Dealer fields.
	 * @return int|\WP_Error Dealer id.
	 */
	public static function create( array $input ) {
		global $wpdb;
		$table = Database::table( 'dealers' );
		$name  = isset( $input['business_name'] ) ? sanitize_text_field( $input['business_name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'amc_invalid_dealer', __( 'A dealer needs a business name.', 'automotive-marketplace-core' ), array( 'status' => 400 ) );
		}

		$base = sanitize_title( $name );
		$slug = $base;
		$i    = 2;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) ) ) {
			$slug = $base . '-' . $i++;
		}

		$city   = isset( $input['city'] ) ? sanitize_text_field( $input['city'] ) : '';
		$status = isset( $input['status'] ) && in_array( $input['status'], Catalog::dealer_statuses(), true ) ? $input['status'] : 'pending';
		$now    = current_time( 'mysql', true );

		$ok = $wpdb->insert(
			$table,
			array(
				'slug'          => $slug,
				'business_name' => $name,
				'logo_url'      => isset( $input['logo_url'] ) ? esc_url_raw( $input['logo_url'] ) : '',
				'description'   => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
				'address'       => isset( $input['address'] ) ? sanitize_text_field( $input['address'] ) : '',
				'city'          => $city,
				'city_slug'     => sanitize_title( $city ),
				'country_code'  => isset( $input['country_code'] ) ? strtoupper( substr( sanitize_key( $input['country_code'] ), 0, 2 ) ) : Settings::get( 'default_country' ),
				'phone'         => isset( $input['phone'] ) ? amc_normalize_phone( $input['phone'] ) : '',
				'whatsapp'      => isset( $input['whatsapp'] ) ? amc_normalize_phone( $input['whatsapp'] ) : '',
				'contact_email' => isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '',
				'website_url'   => isset( $input['website_url'] ) ? esc_url_raw( $input['website_url'], array( 'http', 'https' ) ) : '',
				'is_verified'   => empty( $input['is_verified'] ) ? 0 : 1,
				'status'        => $status,
				'sync_mode'     => isset( $input['sync_mode'] ) && 'manual' === $input['sync_mode'] ? 'manual' : 'api',
				'is_demo'       => empty( $input['is_demo'] ) ? 0 : 1,
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		if ( ! $ok ) {
			return new \WP_Error( 'amc_db_error', __( 'The dealer could not be saved.', 'automotive-marketplace-core' ), array( 'status' => 500 ) );
		}
		Vehicles::flush_cache();
		return (int) $wpdb->insert_id;
	}
}
