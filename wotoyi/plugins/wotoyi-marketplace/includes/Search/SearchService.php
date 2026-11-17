<?php
/**
 * Public read side: vehicle search (filters, sort, pagination, live count), facets, detail, services search.
 * Only published listings of ACTIVE dealers are ever returned.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Search;

use Wotoyi\Marketplace\Presenter\ServicePresenter;
use Wotoyi\Marketplace\Presenter\VehiclePresenter;
use Wotoyi\Marketplace\Schema\Enums;
use Wotoyi\Marketplace\Schema\ServiceFields;
use Wotoyi\Marketplace\Schema\VehicleFields;
use Wotoyi\Marketplace\Support\Cache;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class SearchService {

	private const DEALER_COLS = 'd.uuid AS dealer_uuid, d.name AS dealer_name, d.slug AS dealer_slug, d.verified AS dealer_verified, d.logo_url AS dealer_logo, d.city AS dealer_city';

	/**
	 * Facet spec: name => [column, filters excluded while computing it, requires filter].
	 * "Excluded" filters are the facet's own (and its dependants'), so users see what changing it would give.
	 */
	private const FACETS = array(
		'make'           => array( 'v.make', array( 'make', 'model', 'trim' ), null ),
		'model'          => array( 'v.model', array( 'model', 'trim' ), 'make' ),
		'trim'           => array( 'v.trim_level', array( 'trim' ), 'model' ),
		'body_type'      => array( 'v.body_type', array( 'body_type' ), null ),
		'fuel'           => array( 'v.fuel_type', array( 'fuel' ), null ),
		'transmission'   => array( 'v.transmission', array( 'transmission' ), null ),
		'drive'          => array( 'v.drive_type', array( 'drive' ), null ),
		'condition'      => array( 'v.condition_type', array( 'condition' ), null ),
		'ext_colour'     => array( 'v.ext_colour', array( 'ext_colour' ), null ),
		'city'           => array( 'v.city', array( 'city' ), null ),
		'origin_country' => array( 'v.origin_country', array( 'origin_country' ), null ),
		'customs_status' => array( 'v.customs_status', array( 'customs_status' ), null ),
		'offer_type'     => array( 'v.offer_type', array( 'offer_type' ), null ),
	);

	/** Numeric ranges: name => [column, [min filter, max filter]]. */
	private const RANGES = array(
		'price'      => array( 'v.price', array( 'price_min', 'price_max' ) ),
		'year'       => array( 'v.year', array( 'year_min', 'year_max' ) ),
		'mileage_km' => array( 'v.mileage_km', array( 'mileage_min', 'mileage_max' ) ),
	);

	/* ---------------------------- Vehicles ---------------------------- */

	/**
	 * @param array<string,mixed> $params Raw request params.
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int,filters:array<string,mixed>,sort:string}
	 */
	public function search( array $params ): array {
		$f      = Filters::parse( $params );
		$paging = Filters::paging( $params, isset( $f['lat'] ) );
		$total  = $this->count_filtered( $f );
		$items  = array();

		if ( $total > 0 ) {
			[ $where, $wargs ] = VehicleQuery::where( $f );
			[ $order, $oargs ] = VehicleQuery::order( $paging['sort'], $f );

			$cols  = array( 'v.id', 'v.uuid', 'v.slug', 'v.featured', 'v.published_at', 'v.photo_count', 'v.has_video', 'v.has_360' );
			$cols  = array_merge( $cols, array_map( static fn( string $c ): string => 'v.' . $c, array_diff( VehicleFields::CARD_FIELDS, array( 'wotoyi_inspected' ) ) ), array( 'v.wotoyi_inspected', self::DEALER_COLS ) );
			$sargs = array();
			if ( isset( $f['lat'] ) ) {
				$cols[] = VehicleQuery::distance_expr() . ' AS distance_km';
				$sargs  = array( (float) $f['lat'], (float) $f['lat'], (float) $f['lng'] );
			}

			$sql = 'SELECT ' . implode( ', ', $cols ) . ' FROM ' . VehicleQuery::from() . " WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
			$sql = Db::prepare( $sql, array_merge( $sargs, $wargs, $oargs, array( $paging['per_page'], ( $paging['page'] - 1 ) * $paging['per_page'] ) ) );
			$rows = Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$items = array_map( array( VehiclePresenter::class, 'card' ), (array) $rows );
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $paging['page'],
			'per_page' => $paging['per_page'],
			'pages'    => (int) ceil( $total / $paging['per_page'] ),
			'filters'  => $f,
			'sort'     => $paging['sort'],
		);
	}

	/** Live result count for the filter panel. */
	public function count( array $params ): int {
		return $this->count_filtered( Filters::parse( $params ) );
	}

	private function count_filtered( array $f ): int {
		$cached = Cache::get( 'count', $f );
		if ( is_int( $cached ) ) {
			return $cached;
		}
		[ $where, $args ] = VehicleQuery::where( $f );
		$sql              = Db::prepare( 'SELECT COUNT(*) FROM ' . VehicleQuery::from() . " WHERE {$where}", $args );
		$total            = (int) Db::wpdb()->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		Cache::set( 'count', $f, $total, 30 );
		return $total;
	}

	/**
	 * Facet counts and numeric ranges for the current filters.
	 *
	 * @param array<string,mixed> $params Raw request params (filters + optional `facets` list).
	 * @return array{facets:array<string,list<array{value:string,count:int}>>,ranges:array<string,array{min:?int,max:?int}>}
	 */
	public function facets( array $params ): array {
		$f         = Filters::parse( $params );
		$requested = isset( $params['facets'] ) && is_string( $params['facets'] ) ? array_filter( array_map( 'trim', explode( ',', $params['facets'] ) ) ) : array();
		$names     = $requested ? array_values( array_intersect( array_merge( array_keys( self::FACETS ), array( 'equipment' ) ), $requested ) ) : array_merge( array_keys( self::FACETS ), array( 'equipment' ) );

		$cache_key = array( $f, $names );
		$cached    = Cache::get( 'facets', $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$facets = array();
		foreach ( $names as $name ) {
			if ( 'equipment' === $name ) {
				$facets['equipment'] = $this->equipment_facet( $f );
				continue;
			}
			[ $col, $exclude, $needs ] = self::FACETS[ $name ];
			if ( null !== $needs && empty( $f[ $needs ] ) ) {
				continue; // Dependent facet (models need a make, trims need a model).
			}
			[ $where, $args ] = VehicleQuery::where( $f, $exclude );
			$sql              = Db::prepare( "SELECT {$col} AS value, COUNT(*) AS n FROM " . VehicleQuery::from() . " WHERE {$where} AND {$col} IS NOT NULL GROUP BY {$col} ORDER BY n DESC, value ASC LIMIT 100", $args );
			$rows             = (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$facets[ $name ]  = array_map( static fn( array $r ): array => array( 'value' => (string) $r['value'], 'count' => (int) $r['n'] ), $rows );
		}

		$ranges = array();
		foreach ( self::RANGES as $name => [ $col, $own ] ) {
			[ $where, $args ] = VehicleQuery::where( $f, $own );
			$sql              = Db::prepare( "SELECT MIN({$col}) AS lo, MAX({$col}) AS hi FROM " . VehicleQuery::from() . " WHERE {$where}", $args );
			$row              = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$ranges[ $name ]  = array(
				'min' => isset( $row['lo'] ) ? (int) $row['lo'] : null,
				'max' => isset( $row['hi'] ) ? (int) $row['hi'] : null,
			);
		}

		$out = array( 'facets' => $facets, 'ranges' => $ranges );
		Cache::set( 'facets', $cache_key, $out, 60 );
		return $out;
	}

	/** @return list<array{value:string,count:int}> */
	private function equipment_facet( array $f ): array {
		[ $where, $args ] = VehicleQuery::where( $f, array( 'equipment' ) );
		$sql              = Db::prepare( 'SELECT e.code AS value, COUNT(*) AS n FROM ' . VehicleQuery::from() . ' INNER JOIN ' . Db::table( 'vehicle_equipment' ) . " e ON e.vehicle_id = v.id WHERE {$where} GROUP BY e.code ORDER BY n DESC, value ASC LIMIT 100", $args );
		$rows             = (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return array_map( static fn( array $r ): array => array( 'value' => (string) $r['value'], 'count' => (int) $r['n'] ), $rows );
	}

	/**
	 * Vehicle detail by UUID or slug (published or sold, active dealer only).
	 *
	 * @return array<string,mixed>|null
	 */
	public function vehicle_detail( string $ref, string $lang = 'fr' ): ?array {
		$ref = strtolower( trim( $ref ) );
		if ( '' === $ref || strlen( $ref ) > 190 ) {
			return null;
		}
		$sql = Db::prepare(
			'SELECT v.*, ' . self::DEALER_COLS . ', d.phone AS dealer_phone, d.whatsapp AS dealer_whatsapp, d.website_url AS dealer_website, d.area AS dealer_area, d.address AS dealer_address, d.languages AS dealer_languages FROM ' . VehicleQuery::from() . ' WHERE (v.uuid = %s OR v.slug = %s) AND v.status IN (%s,%s) AND d.status = %s LIMIT 1',
			array( $ref, $ref, 'published', 'sold', 'active' )
		);
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return null;
		}
		$equipment = array();
		$eq_rows   = (array) Db::wpdb()->get_results( Db::prepare( 'SELECT category, code FROM ' . Db::table( 'vehicle_equipment' ) . ' WHERE vehicle_id = %d ORDER BY category, code', array( (int) $row['id'] ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( $eq_rows as $r ) {
			$equipment[ $r['category'] ][] = $r['code'];
		}
		$out                 = VehiclePresenter::detail( $row, $equipment, $lang );
		$out['internal_id']  = (int) $row['id']; // Stripped by the REST layer; used for view counting.
		return $out;
	}

	/** Resolve a vehicle ref to its row id (published only), for leads, favourites and view counting. */
	public function published_vehicle_ref( string $ref ): ?array {
		$ref = strtolower( trim( $ref ) );
		$sql = Db::prepare( 'SELECT v.id, v.dealer_id FROM ' . VehicleQuery::from() . ' WHERE (v.uuid = %s OR v.slug = %s) AND v.status = %s AND d.status = %s LIMIT 1', array( $ref, $ref, 'published', 'active' ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? array( 'id' => (int) $row['id'], 'dealer_id' => (int) $row['dealer_id'] ) : null;
	}

	/** @return array{id:int,dealer_id:int}|null */
	public function published_service_ref( string $ref ): ?array {
		$ref = strtolower( trim( $ref ) );
		$sql = Db::prepare( 'SELECT s.id, s.dealer_id FROM ' . Db::table( 'services' ) . ' s INNER JOIN ' . Db::table( 'dealers' ) . ' d ON d.id = s.dealer_id WHERE (s.uuid = %s OR s.slug = %s) AND s.status = %s AND d.status = %s LIMIT 1', array( $ref, $ref, 'published', 'active' ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? array( 'id' => (int) $row['id'], 'dealer_id' => (int) $row['dealer_id'] ) : null;
	}

	/* ----------------------------- Services ----------------------------- */

	/**
	 * Services search: q, category[], city, area, price_max, mobile_service, dealer, verified_dealer, lat/lng/radius_km, sort, paging.
	 *
	 * @param array<string,mixed> $params
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
	 */
	public function search_services( array $params ): array {
		$sql  = array( 's.status = %s', 'd.status = %s' );
		$args = array( 'published', 'active' );
		$db   = Db::wpdb();

		if ( isset( $params['q'] ) && is_scalar( $params['q'] ) ) {
			foreach ( array_slice( preg_split( '/\s+/', strtolower( trim( sanitize_text_field( (string) $params['q'] ) ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: array(), 0, 5 ) as $term ) {
				if ( mb_strlen( $term ) >= 2 ) {
					$sql[]  = 's.search_text LIKE %s';
					$args[] = '%' . $db->esc_like( mb_substr( $term, 0, 40 ) ) . '%';
				}
			}
		}
		$cats = array_values( array_intersect( array_map( 'strtolower', self::csv( $params['category'] ?? null ) ), Enums::SERVICE_CATEGORY ) );
		if ( $cats ) {
			$sql[] = 's.category IN (' . Db::placeholders( count( $cats ) ) . ')';
			$args  = array_merge( $args, $cats );
		}
		foreach ( array( 'city', 'area' ) as $col ) {
			$vals = array_map( static fn( string $s ): string => mb_substr( $s, 0, 80 ), self::csv( $params[ $col ] ?? null ) );
			if ( $vals ) {
				$sql[] = "s.{$col} IN (" . Db::placeholders( count( $vals ) ) . ')';
				$args  = array_merge( $args, $vals );
			}
		}
		if ( isset( $params['price_max'] ) && is_scalar( $params['price_max'] ) && ctype_digit( (string) $params['price_max'] ) ) {
			$sql[]  = 's.price_from <= %d';
			$args[] = min( 9999999999, (int) $params['price_max'] );
		}
		if ( ! empty( $params['mobile_service'] ) && in_array( (string) $params['mobile_service'], array( '1', 'true', 'yes' ), true ) ) {
			$sql[] = 's.mobile_service = 1';
		}
		if ( ! empty( $params['verified_dealer'] ) && in_array( (string) $params['verified_dealer'], array( '1', 'true', 'yes' ), true ) ) {
			$sql[] = 'd.verified = 1';
		}
		$dealers = array_values( array_filter( array_map( 'strtolower', self::csv( $params['dealer'] ?? null ) ), static fn( string $u ): bool => Uuid::is_valid( $u ) ) );
		if ( $dealers ) {
			$sql[] = 'd.uuid IN (' . Db::placeholders( count( $dealers ) ) . ')';
			$args  = array_merge( $args, $dealers );
		}

		$geo    = null;
		$lat    = isset( $params['lat'] ) && is_numeric( $params['lat'] ) ? max( -90.0, min( 90.0, (float) $params['lat'] ) ) : null;
		$lng    = isset( $params['lng'] ) && is_numeric( $params['lng'] ) ? max( -180.0, min( 180.0, (float) $params['lng'] ) ) : null;
		$select = 's.*, ' . self::DEALER_COLS;
		$sargs  = array();
		if ( null !== $lat && null !== $lng ) {
			$radius = isset( $params['radius_km'] ) && ctype_digit( (string) $params['radius_km'] ) ? max( 1, min( 500, (int) $params['radius_km'] ) ) : 50;
			[ $geo_sql, $geo_args ] = VehicleQuery::geo_where( $lat, $lng, $radius, 's.latitude', 's.longitude' );
			$sql[]  = $geo_sql;
			$args   = array_merge( $args, $geo_args );
			$geo    = array( $lat, $lng );
			$select .= ', ' . VehicleQuery::distance_expr( 's.latitude', 's.longitude' ) . ' AS distance_km';
			$sargs   = array( $lat, $lat, $lng );
		}

		$where = implode( ' AND ', $sql );
		$from  = Db::table( 'services' ) . ' s INNER JOIN ' . Db::table( 'dealers' ) . ' d ON d.id = s.dealer_id';
		$total = (int) $db->get_var( Db::prepare( "SELECT COUNT(*) FROM {$from} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery

		$per_page = Filters::paging( $params, false )['per_page'];
		$page     = Filters::paging( $params, false )['page'];
		$sort     = isset( $params['sort'] ) && is_string( $params['sort'] ) ? $params['sort'] : 'relevance';
		$oargs    = array();
		switch ( $sort ) {
			case 'price_asc':
				$order = '(s.price_from IS NULL) ASC, s.price_from ASC, s.id DESC';
				break;
			case 'views':
				$order = 's.views DESC, s.id DESC';
				break;
			case 'distance':
				if ( $geo ) {
					$order = VehicleQuery::distance_expr( 's.latitude', 's.longitude' ) . ' ASC, s.id DESC';
					$oargs = array( $geo[0], $geo[0], $geo[1] );
					break;
				}
				// no break: without coordinates fall back to default order.
			default:
				$order = 's.featured DESC, s.published_at DESC, s.id DESC';
		}

		$items = array();
		if ( $total > 0 ) {
			$rows  = (array) $db->get_results( Db::prepare( "SELECT {$select} FROM {$from} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge( $sargs, $args, $oargs, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$items = array_map( static fn( array $r ): array => ServicePresenter::item( $r ), $rows );
		}
		return array( 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per_page, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/** @return array<string,mixed>|null */
	public function service_detail( string $ref, string $lang = 'fr' ): ?array {
		$ref = strtolower( trim( $ref ) );
		$sql = Db::prepare( 'SELECT s.*, ' . self::DEALER_COLS . ' FROM ' . Db::table( 'services' ) . ' s INNER JOIN ' . Db::table( 'dealers' ) . ' d ON d.id = s.dealer_id WHERE (s.uuid = %s OR s.slug = %s) AND s.status = %s AND d.status = %s LIMIT 1', array( $ref, $ref, 'published', 'active' ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return null;
		}
		$out                = ServicePresenter::item( $row, true, $lang );
		$out['internal_id'] = (int) $row['id'];
		return $out;
	}

	/** @return list<string> */
	private static function csv( mixed $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $raw, 0, Filters::MAX_LIST ) as $item ) {
			if ( is_scalar( $item ) && '' !== trim( sanitize_text_field( (string) $item ) ) ) {
				$out[] = trim( sanitize_text_field( (string) $item ) );
			}
		}
		return $out;
	}
}
