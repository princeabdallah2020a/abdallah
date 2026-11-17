<?php
/**
 * Search filter parsing: turns untrusted query parameters into a normalised, validated filter set.
 * Unknown parameters are dropped; enumerations are whitelisted; numbers are clamped; lists are capped.
 * Nothing leaves this class unvalidated, so the query builder only ever sees safe typed values.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Search;

use Wotoyi\Marketplace\Schema\Enums;
use Wotoyi\Marketplace\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class Filters {

	public const MAX_LIST     = 20;
	public const MAX_PER_PAGE = 48;
	public const MAX_OFFSET   = 10000;

	public const SORTS = array( 'relevance', 'price_asc', 'price_desc', 'year_desc', 'year_asc', 'mileage_asc', 'mileage_desc', 'newest', 'views', 'distance' );

	/**
	 * Declarative spec: param => [kind, column/sql, extra].
	 * Kinds: enum (multi, whitelist) | text (multi) | num_multi | min | max | maxf | flag
	 *
	 * @return array<string,array>
	 */
	public static function spec(): array {
		return array(
			// Multi-select enumerations.
			'body_type'            => array( 'enum', 'v.body_type', Enums::BODY_TYPE ),
			'fuel'                 => array( 'enum', 'v.fuel_type', Enums::FUEL ),
			'transmission'         => array( 'enum', 'v.transmission', Enums::TRANSMISSION ),
			'drive'                => array( 'enum', 'v.drive_type', Enums::DRIVE ),
			'ext_colour'           => array( 'enum', 'v.ext_colour', Enums::COLOUR ),
			'int_colour'           => array( 'enum', 'v.int_colour', Enums::COLOUR ),
			'upholstery'           => array( 'enum', 'v.upholstery', Enums::UPHOLSTERY ),
			'condition'            => array( 'enum', 'v.condition_type', Enums::CONDITION ),
			'customs_status'       => array( 'enum', 'v.customs_status', Enums::CUSTOMS ),
			'origin_country'       => array( 'country', 'v.origin_country' ),
			'registration_country' => array( 'country', 'v.registration_country' ),
			// Free text, exact match (case-insensitive through the MySQL collation).
			'make'                 => array( 'text', 'v.make' ),
			'model'                => array( 'text', 'v.model' ),
			'trim'                 => array( 'text', 'v.trim_level' ),
			'city'                 => array( 'text', 'v.city' ),
			'area'                 => array( 'text', 'v.area' ),
			// Numeric lists.
			'doors'                => array( 'num_multi', 'v.doors' ),
			// Ranges (rows with NULL in the column never match an active range).
			'price_min'            => array( 'min', 'v.price' ),
			'price_max'            => array( 'max', 'v.price' ),
			'monthly_price_max'    => array( 'max', 'v.rental_price_month' ),
			'year_min'             => array( 'min', 'v.year', 1900, 2100 ),
			'year_max'             => array( 'max', 'v.year', 1900, 2100 ),
			'mileage_min'          => array( 'min', 'v.mileage_km', 0, 5000000 ),
			'mileage_max'          => array( 'max', 'v.mileage_km', 0, 5000000 ),
			'engine_min'           => array( 'min', 'v.engine_cc', 0, 20000 ),
			'engine_max'           => array( 'max', 'v.engine_cc', 0, 20000 ),
			'power_min'            => array( 'min', 'v.power_hp', 0, 3000 ),
			'power_max'            => array( 'max', 'v.power_hp', 0, 3000 ),
			'range_min'            => array( 'min', 'v.electric_range_km', 0, 2000 ),
			'seats_min'            => array( 'min', 'v.seats', 0, 80 ),
			'owners_max'           => array( 'max', 'v.owners_count', 0, 50 ),
			'consumption_max'      => array( 'maxf', 'v.consumption_combined', 0, 99 ),
			// Boolean flags (only "true" is meaningful: it narrows the result).
			'accident_free'        => array( 'flag', 'v.accident_free = 1' ),
			'service_history'      => array( 'flag', "v.service_book IN ('full','partial')" ),
			'warranty'             => array( 'flag', 'v.warranty = 1' ),
			'inspected'            => array( 'flag', 'v.wotoyi_inspected = 1' ),
			'verified_dealer'      => array( 'flag', 'd.verified = 1' ),
			'delivery'             => array( 'flag', 'v.delivery_available = 1' ),
			'financing'            => array( 'flag', 'v.financing_available = 1' ),
			'trade_in'             => array( 'flag', 'v.trade_in_accepted = 1' ),
			'has_photos'           => array( 'flag', 'v.photo_count > 0' ),
			'has_video'            => array( 'flag', 'v.has_video = 1' ),
			'has_360'              => array( 'flag', 'v.has_360 = 1' ),
		);
	}

	/** Names of every supported public filter parameter (documentation and tests). */
	public static function parameter_names(): array {
		return array_merge(
			array( 'q', 'offer_type', 'rental_duration', 'dealer', 'equipment', 'posted_since', 'lat', 'lng', 'radius_km' ),
			array_keys( self::spec() )
		);
	}

	/**
	 * @param array<string,mixed> $params Raw query params.
	 * @return array<string,mixed> Normalised filters.
	 */
	public static function parse( array $params ): array {
		$out = array();

		// Keyword: up to 5 terms of 2-40 chars.
		if ( isset( $params['q'] ) && is_scalar( $params['q'] ) ) {
			$terms = preg_split( '/\s+/', strtolower( trim( sanitize_text_field( (string) $params['q'] ) ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
			$terms = array_values( array_filter( array_map( static fn( string $t ): string => mb_substr( $t, 0, 40 ), $terms ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 ) );
			if ( $terms ) {
				$out['q'] = array_slice( $terms, 0, 5 );
			}
		}

		// Offer type (single).
		if ( isset( $params['offer_type'] ) && is_string( $params['offer_type'] ) && in_array( strtolower( $params['offer_type'] ), Enums::OFFER_TYPE, true ) ) {
			$out['offer_type'] = strtolower( $params['offer_type'] );
		}

		foreach ( self::spec() as $name => $spec ) {
			if ( ! array_key_exists( $name, $params ) ) {
				continue;
			}
			$raw = $params[ $name ];
			switch ( $spec[0] ) {
				case 'enum':
					$list = array_values( array_intersect( array_map( 'strtolower', self::to_list( $raw ) ), $spec[2] ) );
					if ( $list ) {
						$out[ $name ] = array_values( array_unique( $list ) );
					}
					break;
				case 'country':
					$list = array_values( array_unique( array_filter( array_map( static fn( string $c ): string => strtoupper( $c ), self::to_list( $raw ) ), static fn( string $c ): bool => 1 === preg_match( '/^[A-Z]{2}$/', $c ) ) ) );
					if ( $list ) {
						$out[ $name ] = $list;
					}
					break;
				case 'text':
					$list = array_values( array_unique( array_map( static fn( string $s ): string => mb_substr( $s, 0, 80 ), self::to_list( $raw ) ) ) );
					if ( $list ) {
						$out[ $name ] = $list;
					}
					break;
				case 'num_multi':
					$list = array_values( array_unique( array_filter( array_map( static fn( string $s ): int => (int) $s, array_filter( self::to_list( $raw ), 'ctype_digit' ) ), static fn( int $n ): bool => $n > 0 && $n <= 99 ) ) );
					if ( $list ) {
						$out[ $name ] = $list;
					}
					break;
				case 'min':
				case 'max':
					$n = self::int( $raw, (int) ( $spec[2] ?? 0 ), (int) ( $spec[3] ?? 9999999999999 ) );
					if ( null !== $n ) {
						$out[ $name ] = $n;
					}
					break;
				case 'maxf':
					$n = self::float( $raw, (float) $spec[2], (float) $spec[3] );
					if ( null !== $n ) {
						$out[ $name ] = $n;
					}
					break;
				case 'flag':
					if ( self::truthy( $raw ) ) {
						$out[ $name ] = true;
					}
					break;
			}
		}

		// Rental duration in days.
		if ( isset( $params['rental_duration'] ) ) {
			$n = self::int( $params['rental_duration'], 1, 3650 );
			if ( null !== $n ) {
				$out['rental_duration'] = $n;
			}
		}

		// Dealer (marketplace dealer UUIDs).
		if ( isset( $params['dealer'] ) ) {
			$uuids = array_values( array_unique( array_filter( array_map( 'strtolower', self::to_list( $params['dealer'] ) ), static fn( string $u ): bool => Uuid::is_valid( $u ) ) ) );
			if ( $uuids ) {
				$out['dealer'] = $uuids;
			}
		}

		// Equipment: all listed codes must be present (max 10).
		if ( isset( $params['equipment'] ) ) {
			$codes = array_values( array_unique( array_intersect( array_map( 'strtolower', self::to_list( $params['equipment'] ) ), Enums::equipment_codes() ) ) );
			if ( $codes ) {
				$out['equipment'] = array_slice( $codes, 0, 10 );
			}
		}

		// Posted within N days.
		if ( isset( $params['posted_since'] ) ) {
			$n = self::int( $params['posted_since'], 1, 365 );
			if ( null !== $n ) {
				$out['posted_since'] = $n;
			}
		}

		// Geo: needs both coordinates.
		$lat = isset( $params['lat'] ) ? self::float( $params['lat'], -90, 90 ) : null;
		$lng = isset( $params['lng'] ) ? self::float( $params['lng'], -180, 180 ) : null;
		if ( null !== $lat && null !== $lng ) {
			$out['lat']       = $lat;
			$out['lng']       = $lng;
			$out['radius_km'] = self::int( $params['radius_km'] ?? 50, 1, 500 ) ?? 50;
		}

		ksort( $out );
		return $out;
	}

	/** @return array{page:int,per_page:int,sort:string} */
	public static function paging( array $params, bool $has_geo ): array {
		$per_page = self::int( $params['per_page'] ?? 12, 1, self::MAX_PER_PAGE ) ?? 12;
		$page     = self::int( $params['page'] ?? 1, 1, (int) floor( self::MAX_OFFSET / $per_page ) ) ?? 1;
		$sort     = isset( $params['sort'] ) && is_string( $params['sort'] ) && in_array( $params['sort'], self::SORTS, true ) ? $params['sort'] : 'relevance';
		if ( 'distance' === $sort && ! $has_geo ) {
			$sort = 'relevance';
		}
		return array( 'page' => $page, 'per_page' => $per_page, 'sort' => $sort );
	}

	/** @return list<string> */
	private static function to_list( mixed $raw ): array {
		if ( is_int( $raw ) || is_float( $raw ) ) {
			$raw = (string) $raw;
		}
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$v = trim( sanitize_text_field( (string) $item ) );
			if ( '' !== $v ) {
				$out[] = $v;
			}
			if ( count( $out ) >= self::MAX_LIST ) {
				break;
			}
		}
		return $out;
	}

	private static function int( mixed $raw, int $min, int $max ): ?int {
		if ( is_int( $raw ) ) {
			$n = $raw;
		} elseif ( is_string( $raw ) && 1 === preg_match( '/^-?\d{1,15}$/', trim( $raw ) ) ) {
			$n = (int) trim( $raw );
		} else {
			return null;
		}
		return max( $min, min( $max, $n ) );
	}

	private static function float( mixed $raw, float $min, float $max ): ?float {
		if ( is_int( $raw ) || is_float( $raw ) ) {
			$n = (float) $raw;
		} elseif ( is_string( $raw ) && is_numeric( trim( $raw ) ) ) {
			$n = (float) trim( $raw );
		} else {
			return null;
		}
		return is_finite( $n ) ? max( $min, min( $max, $n ) ) : null;
	}

	private static function truthy( mixed $raw ): bool {
		return in_array( is_string( $raw ) ? strtolower( $raw ) : $raw, array( true, 1, '1', 'true', 'yes', 'on' ), true );
	}
}
