<?php
/**
 * SQL assembly for vehicle search. Every dynamic value goes through placeholders; column names and
 * SQL snippets come only from the hard-coded spec in Filters::spec().
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Search;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class VehicleQuery {

	/** FROM clause shared by all vehicle queries. */
	public static function from(): string {
		return Db::table( 'vehicles' ) . ' v INNER JOIN ' . Db::table( 'dealers' ) . ' d ON d.id = v.dealer_id';
	}

	/**
	 * Build the WHERE clause (without the keyword).
	 *
	 * @param array<string,mixed> $f       Normalised filters.
	 * @param list<string>        $exclude Filter names to leave out (facet computation).
	 * @return array{0:string,1:list<mixed>} SQL with placeholders, args.
	 */
	public static function where( array $f, array $exclude = array() ): array {
		$sql  = array( 'v.status = %s', 'd.status = %s' );
		$args = array( 'published', 'active' );
		foreach ( $exclude as $name ) {
			unset( $f[ $name ] );
		}
		$db = Db::wpdb();

		// Keyword: every term must appear in the denormalised search text.
		foreach ( $f['q'] ?? array() as $term ) {
			$sql[]  = 'v.search_text LIKE %s';
			$args[] = '%' . $db->esc_like( $term ) . '%';
		}

		if ( isset( $f['offer_type'] ) ) {
			if ( 'both' === $f['offer_type'] ) {
				$sql[]  = 'v.offer_type = %s';
				$args[] = 'both';
			} else {
				$sql[]  = 'v.offer_type IN (%s,%s)';
				$args[] = $f['offer_type'];
				$args[] = 'both';
			}
		}

		if ( isset( $f['rental_duration'] ) ) {
			$sql[]  = '(v.rental_min_days IS NULL OR v.rental_min_days <= %d)';
			$args[] = $f['rental_duration'];
		}

		foreach ( Filters::spec() as $name => $spec ) {
			if ( ! isset( $f[ $name ] ) ) {
				continue;
			}
			$value = $f[ $name ];
			$col   = $spec[1];
			switch ( $spec[0] ) {
				case 'enum':
				case 'country':
				case 'text':
					$sql[] = "{$col} IN (" . Db::placeholders( count( $value ) ) . ')';
					array_push( $args, ...$value );
					break;
				case 'num_multi':
					$sql[] = "{$col} IN (" . Db::placeholders( count( $value ), '%d' ) . ')';
					array_push( $args, ...$value );
					break;
				case 'min':
					$sql[]  = "{$col} >= %d";
					$args[] = $value;
					break;
				case 'max':
					$sql[]  = "{$col} <= %d";
					$args[] = $value;
					break;
				case 'maxf':
					$sql[]  = "{$col} <= %f";
					$args[] = $value;
					break;
				case 'flag':
					$sql[] = $col; // Static snippet from the spec, no user data.
					break;
			}
		}

		if ( isset( $f['dealer'] ) ) {
			$sql[] = 'd.uuid IN (' . Db::placeholders( count( $f['dealer'] ) ) . ')';
			array_push( $args, ...$f['dealer'] );
		}

		foreach ( $f['equipment'] ?? array() as $code ) {
			$sql[]  = 'EXISTS (SELECT 1 FROM ' . Db::table( 'vehicle_equipment' ) . ' e WHERE e.vehicle_id = v.id AND e.code = %s)';
			$args[] = $code;
		}

		if ( isset( $f['posted_since'] ) ) {
			$sql[]  = 'v.published_at >= %s';
			$args[] = Clock::mysql( Clock::now() - (int) $f['posted_since'] * DAY_IN_SECONDS );
		}

		if ( isset( $f['lat'], $f['lng'] ) ) {
			[ $geo_sql, $geo_args ] = self::geo_where( (float) $f['lat'], (float) $f['lng'], (int) $f['radius_km'] );
			$sql[]                  = $geo_sql;
			$args                   = array_merge( $args, $geo_args );
		}

		return array( implode( ' AND ', $sql ), $args );
	}

	/** Great-circle distance in km (haversine) between (lat,lng) and the row's coordinates. */
	public static function distance_expr( string $lat_col = 'v.latitude', string $lng_col = 'v.longitude' ): string {
		return "(12742 * ASIN(SQRT(POWER(SIN(RADIANS({$lat_col} - %f) / 2), 2) + COS(RADIANS(%f)) * COS(RADIANS({$lat_col})) * POWER(SIN(RADIANS({$lng_col} - %f) / 2), 2))))";
	}

	/** @return array{0:string,1:list<float>} */
	public static function geo_where( float $lat, float $lng, int $radius_km, string $lat_col = 'v.latitude', string $lng_col = 'v.longitude' ): array {
		$dlat = $radius_km / 111.0;
		$sql  = "{$lat_col} BETWEEN %f AND %f";
		$args = array( $lat - $dlat, $lat + $dlat );
		if ( abs( $lat ) < 89.0 ) {
			$dlng = $radius_km / ( 111.32 * cos( deg2rad( $lat ) ) );
			if ( $lng - $dlng >= -180.0 && $lng + $dlng <= 180.0 ) { // Skip the box across the antimeridian; the exact test below still applies.
				$sql   .= " AND {$lng_col} BETWEEN %f AND %f";
				$args[] = $lng - $dlng;
				$args[] = $lng + $dlng;
			}
		}
		$sql   .= ' AND ' . self::distance_expr( $lat_col, $lng_col ) . ' <= %f';
		$args[] = $lat;
		$args[] = $lat;
		$args[] = $lng;
		$args[] = (float) $radius_km;
		return array( '(' . $sql . ')', $args );
	}

	/**
	 * ORDER BY clause.
	 *
	 * @return array{0:string,1:list<mixed>}
	 */
	public static function order( string $sort, array $f ): array {
		$args = array();
		switch ( $sort ) {
			case 'price_asc':
				$sql = '(v.price IS NULL) ASC, v.price ASC, v.id DESC';
				break;
			case 'price_desc':
				$sql = '(v.price IS NULL) ASC, v.price DESC, v.id DESC';
				break;
			case 'year_desc':
				$sql = '(v.year IS NULL) ASC, v.year DESC, v.id DESC';
				break;
			case 'year_asc':
				$sql = '(v.year IS NULL) ASC, v.year ASC, v.id DESC';
				break;
			case 'mileage_asc':
				$sql = '(v.mileage_km IS NULL) ASC, v.mileage_km ASC, v.id DESC';
				break;
			case 'mileage_desc':
				$sql = '(v.mileage_km IS NULL) ASC, v.mileage_km DESC, v.id DESC';
				break;
			case 'newest':
				$sql = 'v.published_at DESC, v.id DESC';
				break;
			case 'views':
				$sql = 'v.views DESC, v.id DESC';
				break;
			case 'distance':
				$sql  = self::distance_expr() . ' ASC, v.id DESC';
				$args = array( (float) $f['lat'], (float) $f['lat'], (float) $f['lng'] );
				break;
			default: // relevance: featured first, then newest.
				$sql = 'v.featured DESC, v.published_at DESC, v.id DESC';
		}
		return array( $sql, $args );
	}
}
