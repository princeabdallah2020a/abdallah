<?php
/**
 * Dealer REST API — namespace dealer/v1.
 *
 * Phase 3: public, read-only inventory (no private field: no VIN, purchase
 * price or internal data). Authenticated synchronisation and AI endpoints are
 * added in Phases 4 and 5.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Rest_Api {

	const NS = 'dealer/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/vehicles',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function ( \WP_REST_Request $request ) {
					$result = Vehicles::search_public( $request->get_query_params(), max( 1, absint( $request->get_param( 'page' ) ) ), min( 48, max( 1, absint( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 12 ) ) ) );
					return rest_ensure_response(
						array(
							'items' => array_map( array( __CLASS__, 'vehicle' ), $result['items'] ),
							'total' => $result['total'],
							'pages' => $result['pages'],
						)
					);
				},
			)
		);
		register_rest_route(
			self::NS,
			'/business',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$b = adc_business();
					return rest_ensure_response(
						array(
							'name'     => $b->name,
							'city'     => $b->city,
							'address'  => $b->address,
							'phone'    => $b->phone,
							'whatsapp' => $b->whatsapp,
							'logo'     => $b->logo,
							'url'      => home_url( '/' ),
						)
					);
				},
			)
		);
	}

	/** Public representation (allow-list). */
	public static function vehicle( $v ) {
		$media = array();
		foreach ( Vehicles::media( $v->id ) as $attachment_id ) {
			$image = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( $image ) {
				$media[] = array( 'url' => $image[0], 'width' => (int) $image[1], 'height' => (int) $image[2], 'alt' => $v->title );
			}
		}
		return array(
			'external_id'       => $v->uid,
			'slug'              => $v->slug,
			'url'               => adc_vehicle_url( $v ),
			'title'             => $v->title,
			'brand'             => $v->brand,
			'model'             => $v->model,
			'year'              => $v->year,
			'mileage_km'        => $v->mileage_km,
			'fuel_type'         => $v->fuel_type,
			'transmission'      => $v->transmission,
			'body_type'         => $v->body_type,
			'vehicle_condition' => $v->vehicle_condition,
			'color'             => $v->color,
			'listing_type'      => $v->listing_type,
			'price_amount'      => $v->price_amount,
			'price_period'      => $v->price_period,
			'currency'          => $v->currency,
			'description'       => $v->description,
			'features'          => $v->features,
			'status'            => 'published' === $v->status ? 'active' : $v->status,
			'media'             => $media,
			'updated_at'        => mysql_to_rfc3339( $v->updated_at ),
		);
	}
}
