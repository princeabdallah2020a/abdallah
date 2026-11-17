<?php
/**
 * Vehicle field catalogue (all optional). Single source of truth for columns, validation and presentation.
 * Groups: identity, powertrain, consumption, dimensions, equipment, condition, history, pricing, location, media, trust.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Schema;

defined( 'ABSPATH' ) || exit;

final class VehicleFields {

	/** @var array<string,array>|null */
	private static ?array $defs = null;

	/** Envelope keys of a vehicle payload that are not catalogue fields. */
	public const ENVELOPE = array( 'source_id', 'version', 'status', 'dealer_id', 'dealer_uuid' );

	/** @return array<string,array> */
	public static function defs(): array {
		if ( null !== self::$defs ) {
			return self::$defs;
		}
		$s = static fn( string $g, int $len = 80, array $o = array() ): array => array( 'group' => $g, 'type' => 'string', 'len' => $len ) + $o;
		$i = static fn( string $g, int $min, int $max, array $o = array() ): array => array( 'group' => $g, 'type' => 'int', 'min' => $min, 'max' => $max ) + $o;
		$d = static fn( string $g, int $p, int $sc, float $min, float $max ): array => array( 'group' => $g, 'type' => 'decimal', 'precision' => $p, 'scale' => $sc, 'min' => $min, 'max' => $max );
		$e = static fn( string $g, array $values, array $o = array() ): array => array( 'group' => $g, 'type' => 'enum', 'values' => $values ) + $o;
		$b = static fn( string $g, array $o = array() ): array => array( 'group' => $g, 'type' => 'bool' ) + $o;
		$dt = static fn( string $g, array $o = array() ): array => array( 'group' => $g, 'type' => 'date' ) + $o;
		$u = static fn( string $g, array $o = array() ): array => array( 'group' => $g, 'type' => 'url' ) + $o;

		self::$defs = array(
			// Identity.
			'title'                    => $s( 'identity', 160 ),
			'make'                     => $s( 'identity', 60 ),
			'model'                    => $s( 'identity', 80 ),
			'trim_level'               => $s( 'identity', 80 ),
			'year'                     => $i( 'identity', 1900, 2100 ),
			'first_registration_date'  => $dt( 'identity' ),
			'vin'                      => $s( 'identity', 32, array( 'private' => true ) ),
			'plate'                    => $s( 'identity', 20, array( 'private' => true ) ),
			'body_type'                => $e( 'identity', Enums::BODY_TYPE ),
			'ext_colour'               => $e( 'identity', Enums::COLOUR ),
			'int_colour'               => $e( 'identity', Enums::COLOUR ),
			'upholstery'               => $e( 'identity', Enums::UPHOLSTERY ),
			'condition_type'           => $e( 'identity', Enums::CONDITION ),
			'origin_country'           => array( 'group' => 'identity', 'type' => 'country' ),
			'customs_status'           => $e( 'identity', Enums::CUSTOMS ),
			'registration_country'     => array( 'group' => 'identity', 'type' => 'country' ),
			'description'              => array( 'group' => 'identity', 'type' => 'text' ),
			'description_i18n'         => array( 'group' => 'identity', 'type' => 'json_map', 'map' => 'i18n' ),

			// Powertrain and performance.
			'fuel_type'                => $e( 'powertrain', Enums::FUEL ),
			'engine_cc'                => $i( 'powertrain', 50, 20000 ),
			'cylinders'                => $i( 'powertrain', 1, 16 ),
			'power_hp'                 => $i( 'powertrain', 1, 3000 ),
			'power_kw'                 => $i( 'powertrain', 1, 2500 ),
			'torque_nm'                => $i( 'powertrain', 1, 5000 ),
			'transmission'             => $e( 'powertrain', Enums::TRANSMISSION ),
			'gears'                    => $i( 'powertrain', 1, 12 ),
			'drive_type'               => $e( 'powertrain', Enums::DRIVE ),
			'top_speed_kmh'            => $i( 'powertrain', 20, 500 ),
			'accel_0_100'              => $d( 'powertrain', 4, 1, 1, 99 ),

			// Consumption and environment.
			'consumption_urban'        => $d( 'consumption', 4, 1, 0, 99 ),
			'consumption_extra'        => $d( 'consumption', 4, 1, 0, 99 ),
			'consumption_combined'     => $d( 'consumption', 4, 1, 0, 99 ),
			'co2_gkm'                  => $i( 'consumption', 0, 1000 ),
			'euro_norm'                => $e( 'consumption', Enums::EURO ),
			'battery_kwh'              => $d( 'consumption', 5, 1, 0, 500 ),
			'electric_range_km'        => $i( 'consumption', 1, 2000 ),
			'charge_time_min'          => $i( 'consumption', 1, 6000 ),
			'charge_ports'             => $s( 'consumption', 60 ),

			// Dimensions and capacity.
			'length_mm'                => $i( 'dimensions', 500, 20000 ),
			'width_mm'                 => $i( 'dimensions', 500, 4000 ),
			'height_mm'                => $i( 'dimensions', 500, 5000 ),
			'wheelbase_mm'             => $i( 'dimensions', 500, 10000 ),
			'kerb_weight_kg'           => $i( 'dimensions', 50, 50000 ),
			'payload_kg'               => $i( 'dimensions', 0, 50000 ),
			'towing_kg'                => $i( 'dimensions', 0, 50000 ),
			'boot_l'                   => $i( 'dimensions', 0, 10000 ),
			'tank_l'                   => $i( 'dimensions', 0, 1000 ),
			'seats'                    => $i( 'dimensions', 1, 80 ),
			'doors'                    => $i( 'dimensions', 1, 8 ),

			// Equipment by category (codes validated against Enums::equipment()).
			'equipment'                => array( 'group' => 'equipment', 'type' => 'json_map', 'map' => 'equipment' ),

			// Condition.
			'mileage_km'               => $i( 'condition', 0, 5000000 ),
			'owners_count'             => $i( 'condition', 0, 50 ),
			'accident_free'            => $b( 'condition' ),
			'accident_notes'           => $s( 'condition', 255 ),
			'registration_papers'      => $b( 'condition' ),
			'customs_documents'        => $b( 'condition' ),

			// History.
			'service_book'             => $e( 'history', Enums::SERVICE_BOOK ),
			'last_service_date'        => $dt( 'history' ),
			'technical_inspection_date' => $dt( 'history' ),
			'warranty'                 => $b( 'history' ),
			'warranty_months'          => $i( 'history', 0, 240 ),

			// Pricing and commercial terms.
			'offer_type'               => $e( 'pricing', Enums::OFFER_TYPE ),
			'price'                    => $i( 'pricing', 0, 9999999999999 ),
			'currency'                 => array( 'group' => 'pricing', 'type' => 'currency' ),
			'price_negotiable'         => $b( 'pricing' ),
			'rental_price_day'         => $i( 'pricing', 0, 9999999999 ),
			'rental_price_week'        => $i( 'pricing', 0, 9999999999 ),
			'rental_price_month'       => $i( 'pricing', 0, 9999999999 ),
			'rental_deposit'           => $i( 'pricing', 0, 9999999999 ),
			'rental_min_days'          => $i( 'pricing', 1, 3650 ),
			'financing_available'      => $b( 'pricing' ),
			'trade_in_accepted'        => $b( 'pricing' ),
			'delivery_available'       => $b( 'pricing' ),
			'availability'             => $e( 'pricing', Enums::AVAILABILITY ),
			'available_from'           => $dt( 'pricing' ),

			// Location.
			'city'                     => $s( 'location', 80 ),
			'area'                     => $s( 'location', 80 ),
			'country'                  => array( 'group' => 'location', 'type' => 'country' ),
			'latitude'                 => $d( 'location', 9, 6, -90, 90 ),
			'longitude'                => $d( 'location', 9, 6, -180, 180 ),

			// Media.
			'cover_image'              => $u( 'media' ),
			'gallery'                  => array( 'group' => 'media', 'type' => 'json_list', 'item' => 'url', 'max_items' => 40 ),
			'video_url'                => $u( 'media' ),
			'view360_url'              => $u( 'media' ),
			// M5: registration cards, customs papers and invoices carry the previous owner's identity: dealer-side only, never public.
			'documents'                => array( 'group' => 'media', 'type' => 'json_list', 'item' => 'url', 'max_items' => 10, 'private' => true ),

			// Trust: managed by the marketplace, never accepted from dealer payloads.
			'wotoyi_inspected'         => $b( 'trust', array( 'managed' => true ) ),
			'inspection_date'          => $dt( 'trust', array( 'managed' => true ) ),
			'inspection_report_url'    => $u( 'trust', array( 'managed' => true ) ),
		);
		return self::$defs;
	}

	/** @return list<string> Field names safe to show publicly. */
	public static function public_fields(): array {
		return array_keys( array_filter( self::defs(), static fn( array $d ): bool => empty( $d['private'] ) ) );
	}

	/** Columns returned by search result cards (light). */
	public const CARD_FIELDS = array(
		'title', 'make', 'model', 'trim_level', 'year', 'body_type', 'ext_colour', 'condition_type', 'fuel_type', 'transmission',
		'drive_type', 'engine_cc', 'power_hp', 'seats', 'mileage_km', 'offer_type', 'price', 'currency', 'price_negotiable',
		'rental_price_day', 'rental_price_week', 'rental_price_month', 'availability', 'city', 'area', 'country',
		'cover_image', 'wotoyi_inspected',
	);
}
