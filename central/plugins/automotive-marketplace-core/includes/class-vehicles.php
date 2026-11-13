<?php
/**
 * Vehicle listings repository: every read of the public catalog goes through
 * this class so visibility rules (dealer status, listing status, freshness)
 * are enforced in exactly one place.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Vehicles {

	const CACHE_GROUP = 'amc_vehicles';

	/**
	 * Normalise untrusted filter input (query string or REST params).
	 *
	 * @param array $raw Raw values.
	 * @return array Clean filters; absent keys mean "no filter".
	 */
	public static function sanitize_filters( $raw ) {
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$f   = array();

		foreach ( array( 'brand', 'model', 'city', 'dealer' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && '' !== $raw[ $key ] ) {
				$f[ $key ] = sanitize_title( (string) $raw[ $key ] );
			}
		}

		$enums = array(
			'fuel'         => array_keys( Catalog::fuel_types() ),
			'transmission' => array_keys( Catalog::transmissions() ),
			'body'         => array_keys( Catalog::body_types() ),
			'condition'    => array_keys( Catalog::conditions() ),
			'type'         => array_keys( Catalog::listing_types() ),
			'availability' => array( 'available', 'reserved' ),
			'sort'         => array_keys( Catalog::sort_options() ),
		);
		foreach ( $enums as $key => $allowed ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && in_array( (string) $raw[ $key ], $allowed, true ) ) {
				$f[ $key ] = (string) $raw[ $key ];
			}
		}

		foreach ( array( 'price_min', 'price_max', 'year_min', 'year_max', 'mileage_max' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && 0 !== strpos( trim( (string) $raw[ $key ] ), '-' ) ) {
				$value = absint( preg_replace( '/\D+/', '', (string) $raw[ $key ] ) );
				if ( $value > 0 ) {
					$f[ $key ] = $value;
				}
			}
		}

		if ( isset( $raw['q'] ) && is_scalar( $raw['q'] ) ) {
			$q = trim( mb_substr( sanitize_text_field( (string) $raw['q'] ), 0, 80 ) );
			if ( '' !== $q ) {
				$f['q'] = $q;
			}
		}
		if ( ! empty( $raw['featured'] ) ) {
			$f['featured'] = 1;
		}

		return $f;
	}

	/**
	 * Search the public catalog.
	 *
	 * @param array $filters  Output of sanitize_filters().
	 * @param int   $page     1-based page.
	 * @param int   $per_page Page size.
	 * @return array { items: object[], total: int, pages: int, page: int, per_page: int }
	 */
	public static function search( array $filters = array(), $page = 1, $per_page = 0 ) {
		global $wpdb;

		$per_page = $per_page > 0 ? min( 48, (int) $per_page ) : (int) Settings::get( 'per_page' );
		$page     = max( 1, (int) $page );

		list( $where, $params ) = self::build_where( $filters );
		$v = Database::table( 'vehicles' );
		$d = Database::table( 'dealers' );

		$from = "FROM $v v INNER JOIN $d d ON d.id = v.dealer_id WHERE " . implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) $from";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$order = self::order_by( isset( $filters['sort'] ) ? $filters['sort'] : 'latest' );
		$sql   = "SELECT v.*, d.business_name AS dealer_name, d.slug AS dealer_slug, d.is_verified AS dealer_verified, d.sync_mode AS dealer_sync_mode
			$from ORDER BY $order LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ) );
		// phpcs:enable

		return array(
			'items'    => array_map( array( __CLASS__, 'hydrate' ), $items ? $items : array() ),
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * WHERE clauses that define what the public may see, plus user filters.
	 *
	 * @param array $filters Clean filters.
	 * @return array [ string[] $where, array $params ]
	 */
	private static function build_where( array $filters ) {
		global $wpdb;
		$where  = array( "d.status = 'active'" );
		$params = array();

		if ( isset( $filters['availability'] ) && 'available' === $filters['availability'] ) {
			$where[] = "v.status = 'active'";
		} elseif ( isset( $filters['availability'] ) && 'reserved' === $filters['availability'] ) {
			$where[] = "v.status = 'reserved'";
		} else {
			$where[] = "v.status IN ('active','reserved')";
		}

		// Freshness policy: listings from API-connected dealers disappear when
		// the dealer website has not confirmed them for too long.
		$where[]  = "(d.sync_mode <> 'api' OR v.last_synced_at >= %s)";
		$params[] = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * (int) Settings::get( 'hide_after_hours' ) );

		$equals = array(
			'brand'        => 'v.brand_slug',
			'model'        => 'v.model_slug',
			'city'         => 'v.city_slug',
			'dealer'       => 'd.slug',
			'fuel'         => 'v.fuel_type',
			'transmission' => 'v.transmission',
			'body'         => 'v.body_type',
			'condition'    => 'v.vehicle_condition',
			'type'         => 'v.listing_type',
		);
		foreach ( $equals as $key => $column ) {
			if ( isset( $filters[ $key ] ) ) {
				$where[]  = "$column = %s";
				$params[] = $filters[ $key ];
			}
		}

		$ranges = array(
			'price_min'   => 'v.price_amount >= %d',
			'price_max'   => 'v.price_amount <= %d',
			'year_min'    => 'v.year >= %d',
			'year_max'    => 'v.year <= %d',
			'mileage_max' => 'v.mileage_km <= %d',
		);
		foreach ( $ranges as $key => $clause ) {
			if ( isset( $filters[ $key ] ) ) {
				$where[]  = $clause;
				$params[] = $filters[ $key ];
			}
		}

		if ( ! empty( $filters['featured'] ) ) {
			$where[]  = '(v.is_featured = 1 AND (v.featured_until IS NULL OR v.featured_until >= %s))';
			$params[] = gmdate( 'Y-m-d H:i:s' );
		}

		if ( isset( $filters['q'] ) ) {
			// Every word must match the title, brand, model or city.
			foreach ( array_slice( preg_split( '/\s+/', $filters['q'] ), 0, 5 ) as $word ) {
				$like    = '%' . $wpdb->esc_like( $word ) . '%';
				$where[] = '(v.title LIKE %s OR v.brand LIKE %s OR v.model LIKE %s OR v.city LIKE %s)';
				array_push( $params, $like, $like, $like, $like );
			}
		}

		return array( $where, $params );
	}

	private static function order_by( $sort ) {
		switch ( $sort ) {
			case 'price_asc':
				return 'v.price_amount ASC, v.id DESC';
			case 'price_desc':
				return 'v.price_amount DESC, v.id DESC';
			case 'mileage_asc':
				return 'v.mileage_km ASC, v.id DESC';
			case 'featured':
				return 'v.is_featured DESC, v.published_at DESC, v.id DESC';
			default:
				return 'v.published_at DESC, v.id DESC';
		}
	}

	/**
	 * A single listing by slug, with dealer public fields and media.
	 * Returns sold / rented listings too (the page shows them as unavailable),
	 * but never unpublished ones or listings of non-active dealers.
	 *
	 * @param string $slug Listing slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		return self::get_one( 'v.slug = %s', sanitize_title( $slug ) );
	}

	public static function get_by_id( $id ) {
		return self::get_one( 'v.id = %d', absint( $id ) );
	}

	private static function get_one( $clause, $value ) {
		global $wpdb;
		$v = Database::table( 'vehicles' );
		$d = Database::table( 'dealers' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT v.*, d.business_name AS dealer_name, d.slug AS dealer_slug, d.is_verified AS dealer_verified, d.sync_mode AS dealer_sync_mode
				FROM $v v INNER JOIN $d d ON d.id = v.dealer_id
				WHERE $clause AND d.status = 'active' AND v.status <> 'unpublished'
				AND (d.sync_mode <> 'api' OR v.last_synced_at >= %s)",
				$value,
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * (int) Settings::get( 'hide_after_hours' ) )
			)
		);
		// phpcs:enable
		if ( ! $row ) {
			return null;
		}
		$vehicle        = self::hydrate( $row );
		$vehicle->media = self::media( $vehicle->id );
		return $vehicle;
	}

	/**
	 * Gallery images of a listing, in display order.
	 *
	 * @param int $vehicle_id Listing id.
	 */
	public static function media( $vehicle_id ) {
		global $wpdb;
		$table = Database::table( 'vehicle_media' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, url, alt, width, height, position FROM $table WHERE vehicle_id = %d ORDER BY position ASC, id ASC", absint( $vehicle_id ) ) );
		return $rows ? $rows : array();
	}

	/**
	 * Listings similar to the given one: same listing type, same body type or
	 * brand, closest price first.
	 *
	 * @param object $vehicle Hydrated listing.
	 * @param int    $limit   Max results.
	 */
	public static function similar( $vehicle, $limit = 4 ) {
		global $wpdb;
		$v = Database::table( 'vehicles' );
		$d = Database::table( 'dealers' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.*, d.business_name AS dealer_name, d.slug AS dealer_slug, d.is_verified AS dealer_verified, d.sync_mode AS dealer_sync_mode
				FROM $v v INNER JOIN $d d ON d.id = v.dealer_id
				WHERE d.status = 'active' AND v.status = 'active' AND v.id <> %d AND v.listing_type = %s
				AND (v.body_type = %s OR v.brand_slug = %s)
				AND (d.sync_mode <> 'api' OR v.last_synced_at >= %s)
				ORDER BY (CASE WHEN v.brand_slug = %s THEN 0 ELSE 1 END) ASC, ABS(CAST(v.price_amount AS SIGNED) - %d) ASC
				LIMIT %d",
				$vehicle->id,
				$vehicle->listing_type,
				$vehicle->body_type,
				$vehicle->brand_slug,
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * (int) Settings::get( 'hide_after_hours' ) ),
				$vehicle->brand_slug,
				$vehicle->price_amount,
				absint( $limit )
			)
		);
		// phpcs:enable
		return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
	}

	/**
	 * Filter options computed from what is actually in the catalog:
	 * brands (with their models), cities and dealers.
	 *
	 * @return array { brands: [slug => [name, models: [slug => name]]], cities: [slug => name], dealers: [slug => name], years: [min, max] }
	 */
	public static function facets() {
		$cached = wp_cache_get( 'facets', self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$v      = Database::table( 'vehicles' );
		$d      = Database::table( 'dealers' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * (int) Settings::get( 'hide_after_hours' ) );
		$base   = "FROM $v v INNER JOIN $d d ON d.id = v.dealer_id
			WHERE d.status = 'active' AND v.status IN ('active','reserved') AND (d.sync_mode <> 'api' OR v.last_synced_at >= %s)";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$facets = array(
			'brands'  => array(),
			'cities'  => array(),
			'dealers' => array(),
			'years'   => array( 0, 0 ),
		);

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT v.brand, v.brand_slug, v.model, v.model_slug $base ORDER BY v.brand ASC, v.model ASC", $cutoff ) );
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $facets['brands'][ $row->brand_slug ] ) ) {
				$facets['brands'][ $row->brand_slug ] = array(
					'name'   => $row->brand,
					'models' => array(),
				);
			}
			$facets['brands'][ $row->brand_slug ]['models'][ $row->model_slug ] = $row->model;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT v.city, v.city_slug $base AND v.city_slug <> '' ORDER BY v.city ASC", $cutoff ) );
		foreach ( (array) $rows as $row ) {
			$facets['cities'][ $row->city_slug ] = $row->city;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT d.slug, d.business_name $base ORDER BY d.business_name ASC", $cutoff ) );
		foreach ( (array) $rows as $row ) {
			$facets['dealers'][ $row->slug ] = $row->business_name;
		}

		$years = $wpdb->get_row( $wpdb->prepare( "SELECT MIN(v.year) AS min_year, MAX(v.year) AS max_year $base AND v.year > 0", $cutoff ) );
		if ( $years && $years->max_year ) {
			$facets['years'] = array( (int) $years->min_year, (int) $years->max_year );
		}
		// phpcs:enable

		wp_cache_set( 'facets', $facets, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		return $facets;
	}

	/** Must be called after any write to the vehicles or dealers tables. */
	public static function flush_cache() {
		wp_cache_delete( 'facets', self::CACHE_GROUP );
	}

	/**
	 * Cast columns and add computed fields.
	 *
	 * @param object $row Database row.
	 */
	public static function hydrate( $row ) {
		foreach ( array( 'id', 'dealer_id', 'year', 'mileage_km', 'price_amount', 'is_featured', 'is_demo' ) as $int ) {
			$row->$int = (int) $row->$int;
		}
		$row->dealer_verified = isset( $row->dealer_verified ) ? (bool) (int) $row->dealer_verified : false;

		$features      = json_decode( (string) $row->features, true );
		$row->features = is_array( $features ) ? array_values( array_filter( array_map( 'strval', $features ) ) ) : array();

		if ( $row->is_featured && $row->featured_until && strtotime( $row->featured_until . ' UTC' ) < time() ) {
			$row->is_featured = 0;
		}

		// Stale = the dealer website has not confirmed this listing recently.
		$row->is_stale = false;
		if ( isset( $row->dealer_sync_mode ) && 'api' === $row->dealer_sync_mode ) {
			$synced        = $row->last_synced_at ? strtotime( $row->last_synced_at . ' UTC' ) : 0;
			$row->is_stale = $synced < time() - HOUR_IN_SECONDS * (int) Settings::get( 'stale_after_hours' );
		}
		return $row;
	}

	/**
	 * Build a unique listing slug, e.g. "toyota-corolla-2019-dakar-3f9a".
	 *
	 * @param array $data      Listing fields (brand, model, year, city).
	 * @param int   $dealer_id Dealer id.
	 */
	public static function unique_slug( array $data, $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		$base  = sanitize_title( implode( ' ', array_filter( array( $data['brand'], $data['model'], $data['year'], $data['city'] ) ) ) );
		$base  = substr( $base ? $base : 'vehicle', 0, 170 );
		$seed  = $dealer_id . '|' . $data['external_id'];

		for ( $i = 0; $i < 10; $i++ ) {
			$slug = $base . '-' . substr( md5( $seed . '|' . $i ), 0, 4 + $i );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) ) ) {
				return $slug;
			}
		}
		return $base . '-' . wp_generate_password( 12, false );
	}

	/**
	 * Create or update a listing, keyed by (dealer_id, external_id) so the same
	 * dealer vehicle can never produce two marketplace listings.
	 *
	 * The caller is responsible for authorisation and for resolving $dealer_id
	 * server-side. Used by the demo importer today and by the sync API in Phase 4.
	 *
	 * @param int   $dealer_id Dealer id.
	 * @param array $input     Listing data (see docs/03-api-contracts.md).
	 * @return int|\WP_Error Listing id.
	 */
	public static function upsert( $dealer_id, array $input ) {
		global $wpdb;
		$table     = Database::table( 'vehicles' );
		$dealer_id = absint( $dealer_id );

		$external_id = isset( $input['external_id'] ) ? substr( sanitize_text_field( (string) $input['external_id'] ), 0, 64 ) : '';
		$brand       = isset( $input['brand'] ) ? sanitize_text_field( $input['brand'] ) : '';
		$model       = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : '';
		if ( ! $dealer_id || '' === $external_id || '' === $brand || '' === $model ) {
			return new \WP_Error( 'amc_invalid_vehicle', __( 'A listing needs a dealer, an external ID, a brand and a model.', 'automotive-marketplace-core' ), array( 'status' => 400 ) );
		}

		$pick = static function ( $key, array $allowed, $default ) use ( $input ) {
			return isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ? $input[ $key ] : $default;
		};

		$year     = isset( $input['year'] ) ? absint( $input['year'] ) : 0;
		$city     = isset( $input['city'] ) ? sanitize_text_field( $input['city'] ) : '';
		$features = array();
		if ( isset( $input['features'] ) && is_array( $input['features'] ) ) {
			$features = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', $input['features'] ) ) ), 0, 40 );
		}
		$now = current_time( 'mysql', true );

		$data = array(
			'dealer_id'         => $dealer_id,
			'external_id'       => $external_id,
			'title'             => isset( $input['title'] ) && '' !== $input['title'] ? sanitize_text_field( $input['title'] ) : trim( "$brand $model " . ( $year ? $year : '' ) ),
			'brand'             => $brand,
			'brand_slug'        => sanitize_title( $brand ),
			'model'             => $model,
			'model_slug'        => sanitize_title( $model ),
			'year'              => ( $year >= 1900 && $year <= (int) gmdate( 'Y' ) + 1 ) ? $year : 0,
			'mileage_km'        => isset( $input['mileage_km'] ) ? absint( $input['mileage_km'] ) : 0,
			'fuel_type'         => $pick( 'fuel_type', array_keys( Catalog::fuel_types() ), '' ),
			'transmission'      => $pick( 'transmission', array_keys( Catalog::transmissions() ), '' ),
			'body_type'         => $pick( 'body_type', array_keys( Catalog::body_types() ), '' ),
			'vehicle_condition' => $pick( 'vehicle_condition', array_keys( Catalog::conditions() ), '' ),
			'color'             => isset( $input['color'] ) ? sanitize_text_field( $input['color'] ) : '',
			'listing_type'      => $pick( 'listing_type', array_keys( Catalog::listing_types() ), 'sale' ),
			'price_amount'      => isset( $input['price_amount'] ) ? absint( $input['price_amount'] ) : 0,
			'price_period'      => $pick( 'price_period', array_keys( Catalog::price_periods() ), 'total' ),
			'currency'          => $pick( 'currency', array_keys( Catalog::currencies() ), Settings::get( 'default_currency' ) ),
			'city'              => $city,
			'city_slug'         => sanitize_title( $city ),
			'country_code'      => isset( $input['country_code'] ) ? strtoupper( substr( sanitize_key( $input['country_code'] ), 0, 2 ) ) : Settings::get( 'default_country' ),
			'description'       => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
			'features'          => wp_json_encode( $features ),
			'thumbnail_url'     => isset( $input['thumbnail_url'] ) ? esc_url_raw( $input['thumbnail_url'] ) : '',
			'source_url'        => isset( $input['source_url'] ) ? esc_url_raw( $input['source_url'], array( 'http', 'https' ) ) : '',
			'status'            => $pick( 'status', array_keys( Catalog::statuses() ), 'active' ),
			'is_featured'       => empty( $input['is_featured'] ) ? 0 : 1,
			'is_demo'           => empty( $input['is_demo'] ) ? 0 : 1,
			'source_updated_at' => $now,
			'last_synced_at'    => $now,
			'updated_at'        => $now,
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE dealer_id = %d AND external_id = %s", $dealer_id, $external_id ) );

		if ( $existing ) {
			// The slug is permanent once created: URLs must not change on update.
			$ok = $wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			$id = (int) $existing;
		} else {
			$data['slug']         = self::unique_slug( $data, $dealer_id );
			$data['published_at'] = isset( $input['published_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $input['published_at'] ) ) : $now;
			$data['created_at']   = $now;
			$ok                   = $wpdb->insert( $table, $data );
			$id                   = (int) $wpdb->insert_id;
		}
		if ( false === $ok || ! $id ) {
			return new \WP_Error( 'amc_db_error', __( 'The listing could not be saved.', 'automotive-marketplace-core' ), array( 'status' => 500 ) );
		}

		if ( isset( $input['media'] ) && is_array( $input['media'] ) ) {
			self::replace_media( $id, $input['media'] );
		}

		self::flush_cache();
		return $id;
	}

	/**
	 * Replace the gallery of a listing.
	 *
	 * @param int   $vehicle_id Listing id.
	 * @param array $media      List of [ url, alt, width, height ].
	 */
	public static function replace_media( $vehicle_id, array $media ) {
		global $wpdb;
		$table = Database::table( 'vehicle_media' );
		$wpdb->delete( $table, array( 'vehicle_id' => absint( $vehicle_id ) ), array( '%d' ) );

		$position = 0;
		foreach ( array_slice( $media, 0, 30 ) as $item ) {
			$url = isset( $item['url'] ) ? esc_url_raw( $item['url'], array( 'http', 'https' ) ) : '';
			if ( '' === $url ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'vehicle_id'  => absint( $vehicle_id ),
					'url'         => $url,
					'alt'         => isset( $item['alt'] ) ? sanitize_text_field( $item['alt'] ) : '',
					'width'       => isset( $item['width'] ) ? absint( $item['width'] ) : 0,
					'height'      => isset( $item['height'] ) ? absint( $item['height'] ) : 0,
					'position'    => $position++,
					'source_hash' => hash( 'sha256', $url ),
					'created_at'  => current_time( 'mysql', true ),
				)
			);
		}
	}
}
