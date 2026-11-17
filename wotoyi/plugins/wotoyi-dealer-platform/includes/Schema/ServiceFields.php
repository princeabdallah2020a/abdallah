<?php
/**
 * Service field catalogue (maintenance, rental with driver, import, detailing, ...). All optional.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Schema;

defined( 'ABSPATH' ) || exit;

final class ServiceFields {

	public const ENVELOPE = array( 'source_id', 'version', 'status', 'dealer_id', 'dealer_uuid' );

	/** @return array<string,array> */
	public static function defs(): array {
		return array(
			'title'            => array( 'group' => 'identity', 'type' => 'string', 'len' => 160 ),
			'category'         => array( 'group' => 'identity', 'type' => 'enum', 'values' => Enums::SERVICE_CATEGORY ),
			'description'      => array( 'group' => 'identity', 'type' => 'text' ),
			'description_i18n' => array( 'group' => 'identity', 'type' => 'json_map', 'map' => 'i18n' ),
			'features'         => array( 'group' => 'identity', 'type' => 'json_list', 'item' => 'string', 'max_items' => 30 ),
			'languages'        => array( 'group' => 'identity', 'type' => 'string', 'len' => 40 ),
			'price_from'       => array( 'group' => 'pricing', 'type' => 'int', 'min' => 0, 'max' => 9999999999 ),
			'price_to'         => array( 'group' => 'pricing', 'type' => 'int', 'min' => 0, 'max' => 9999999999 ),
			'currency'         => array( 'group' => 'pricing', 'type' => 'currency' ),
			'price_unit'       => array( 'group' => 'pricing', 'type' => 'enum', 'values' => Enums::PRICE_UNIT ),
			'price_on_request' => array( 'group' => 'pricing', 'type' => 'bool' ),
			'duration_minutes' => array( 'group' => 'pricing', 'type' => 'int', 'min' => 1, 'max' => 100000 ),
			'mobile_service'   => array( 'group' => 'location', 'type' => 'bool' ),
			'city'             => array( 'group' => 'location', 'type' => 'string', 'len' => 80 ),
			'area'             => array( 'group' => 'location', 'type' => 'string', 'len' => 80 ),
			'country'          => array( 'group' => 'location', 'type' => 'country' ),
			'latitude'         => array( 'group' => 'location', 'type' => 'decimal', 'precision' => 9, 'scale' => 6, 'min' => -90, 'max' => 90 ),
			'longitude'        => array( 'group' => 'location', 'type' => 'decimal', 'precision' => 9, 'scale' => 6, 'min' => -180, 'max' => 180 ),
			'cover_image'      => array( 'group' => 'media', 'type' => 'url' ),
			'gallery'          => array( 'group' => 'media', 'type' => 'json_list', 'item' => 'url', 'max_items' => 20 ),
			'booking_url'      => array( 'group' => 'media', 'type' => 'url' ),
		);
	}
}
