<?php
/**
 * Controlled vocabularies (codes are stored in English; labels are translated by the i18n plugin).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Schema;

defined( 'ABSPATH' ) || exit;

final class Enums {

	public const OFFER_TYPE      = array( 'sale', 'rental', 'both' );
	public const BODY_TYPE       = array( 'sedan', 'suv', 'hatchback', 'wagon', 'coupe', 'convertible', 'pickup', 'van', 'minivan', 'truck', 'bus', 'motorcycle', 'other' );
	public const FUEL            = array( 'petrol', 'diesel', 'hybrid', 'plugin_hybrid', 'electric', 'lpg', 'other' );
	public const TRANSMISSION    = array( 'manual', 'automatic', 'cvt', 'semi_automatic' );
	public const DRIVE           = array( 'fwd', 'rwd', '4x4', 'awd' );
	public const CONDITION       = array( 'new', 'used', 'imported' );
	public const CUSTOMS         = array( 'cleared', 'in_transit', 'not_applicable' );
	public const UPHOLSTERY      = array( 'leather', 'fabric', 'alcantara', 'vinyl', 'mixed', 'other' );
	public const COLOUR          = array( 'white', 'black', 'grey', 'silver', 'blue', 'red', 'green', 'brown', 'beige', 'yellow', 'orange', 'gold', 'purple', 'other' );
	public const EURO            = array( 'euro3', 'euro4', 'euro5', 'euro6', 'euro6d' );
	public const AVAILABILITY    = array( 'available', 'reserved', 'unavailable' );
	public const SERVICE_BOOK    = array( 'full', 'partial', 'none' );
	public const VEHICLE_STATUS  = array( 'draft', 'published', 'unpublished', 'sold', 'deleted' );
	public const SERVICE_STATUS  = array( 'draft', 'published', 'unpublished', 'deleted' );
	public const DEALER_STATUS   = array( 'pending', 'active', 'suspended', 'disconnected' );
	public const SERVICE_CATEGORY = array( 'maintenance', 'repair', 'bodywork', 'detailing', 'rental_with_driver', 'import', 'inspection', 'insurance', 'financing', 'towing', 'spare_parts', 'other' );
	public const PRICE_UNIT      = array( 'fixed', 'hour', 'day', 'km', 'month' );
	public const LANGS           = array( 'fr', 'en', 'wo', 'ar' );

	/**
	 * Equipment taxonomy: category => allowed codes.
	 *
	 * @return array<string,list<string>>
	 */
	public static function equipment(): array {
		return array(
			'comfort'          => array( 'air_conditioning', 'climate_control', 'heated_seats', 'ventilated_seats', 'electric_seats', 'cruise_control', 'keyless_entry', 'power_windows', 'power_tailgate', 'massage_seats' ),
			'safety'           => array( 'airbags_front', 'airbags_side', 'airbags_curtain', 'abs', 'esp', 'isofix', 'tpms', 'alarm', 'immobilizer' ),
			'multimedia'       => array( 'gps', 'bluetooth', 'carplay_android_auto', 'usb', 'premium_audio', 'rear_screens', 'wireless_charging' ),
			'exterior'         => array( 'sunroof', 'panoramic_roof', 'alloy_wheels', 'roof_rails', 'tow_bar', 'tinted_windows', 'running_boards' ),
			'interior'         => array( 'leather_seats', 'seven_seats', 'third_row', 'folding_rear_seats', 'ambient_lighting', 'sport_seats' ),
			'lighting'         => array( 'led_headlights', 'xenon', 'adaptive_headlights', 'fog_lights', 'drl' ),
			'driver_assistance' => array( 'rear_camera', 'camera_360', 'parking_sensors_front', 'parking_sensors_rear', 'lane_assist', 'blind_spot', 'adaptive_cruise', 'auto_emergency_braking', 'auto_parking' ),
		);
	}

	/** @return list<string> All equipment codes. */
	public static function equipment_codes(): array {
		return array_merge( ...array_values( self::equipment() ) );
	}

	public static function equipment_category( string $code ): ?string {
		foreach ( self::equipment() as $cat => $codes ) {
			if ( in_array( $code, $codes, true ) ) {
				return $cat;
			}
		}
		return null;
	}
}
