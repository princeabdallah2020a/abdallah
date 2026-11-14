<?php
/**
 * Central REST API — namespace marketplace/v1.
 *
 * Phase 1 exposes the PUBLIC, read-only catalog. Authenticated dealer
 * synchronisation endpoints are added in Phase 4 (see docs/03-api-contracts.md).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Api {

	const NS = 'marketplace/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'headers' ), 10, 3 );
		add_action( 'send_headers', array( __CLASS__, 'page_headers' ) );
	}

	/** Public catalog reads may be cached briefly; everything else never. */
	public static function headers( $response, $server, $request ) {
		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::NS ) || ! $response instanceof \WP_HTTP_Response ) {
			return $response;
		}
		$public = 'GET' === $request->get_method() && preg_match( '#^/' . preg_quote( self::NS, '#' ) . '/(vehicles|facets|dealers)#', $route ) && $response->get_status() < 400;
		$response->header( 'Cache-Control', $public ? 'public, max-age=60, stale-while-revalidate=300' : 'no-store, private' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	/** Hardening headers on every front-end page. */
	public static function page_headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
		global $wp;
		$view = $wp && isset( $wp->query_vars['amc_view'] ) ? $wp->query_vars['amc_view'] : '';
		if ( in_array( $view, array( 'account', 'platform', 'login', 'register' ), true ) ) {
			header( 'X-Frame-Options: SAMEORIGIN' ); // No clickjacking of forms.
		}
	}

	public static function register_routes() {
		$public = array( __CLASS__, 'public_permission' );

		register_rest_route(
			self::NS,
			'/vehicles',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_vehicles' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/vehicles/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_vehicle' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/facets',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_facets' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/dealers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_dealers' ),
				'permission_callback' => $public,
			)
		);
		// Server-rendered results used by the catalog page (AJAX filtering).
		register_rest_route(
			self::NS,
			'/catalog',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'catalog_fragment' ),
				'permission_callback' => $public,
			)
		);

		// AI assistant — visitors (public, throttled) and administrators.
		register_rest_route(
			self::NS,
			'/assistant',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'assistant' ),
				'permission_callback' => static function () {
					if ( ! Settings::get( 'ai_visitor_enabled' ) ) {
						return new \WP_Error( 'amc_disabled', __( 'The assistant is disabled.', 'automotive-marketplace-core' ), array( 'status' => 403 ) );
					}
					if ( ! Rate_Limiter::allow( 'assistant', 20, 10 * MINUTE_IN_SECONDS ) ) {
						return new \WP_Error( 'amc_rate_limited', __( 'You have sent many messages. Please wait a few minutes.', 'automotive-marketplace-core' ), array( 'status' => 429 ) );
					}
					return true;
				},
			)
		);
		$admin_only = static function () {
			return Permissions::is_staff();
		};
		register_rest_route(
			self::NS,
			'/admin/assistant',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'admin_assistant' ),
				'permission_callback' => $admin_only,
			)
		);
		register_rest_route(
			self::NS,
			'/admin/inquiries/(?P<id>\d+)/draft-reply',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $request ) {
					$draft = Assistant::draft_inquiry_reply( $request['id'] );
					return is_wp_error( $draft ) ? $draft : rest_ensure_response( array( 'draft' => $draft ) );
				},
				'permission_callback' => $admin_only,
			)
		);
	}

	public static function assistant( \WP_REST_Request $request ) {
		return self::assistant_response( 'visitor', $request );
	}

	public static function admin_assistant( \WP_REST_Request $request ) {
		return self::assistant_response( 'admin', $request );
	}

	private static function assistant_response( $scope, \WP_REST_Request $request ) {
		$messages = Assistant::sanitize_messages( $request->get_param( 'messages' ) );
		if ( ! $messages ) {
			return new \WP_Error( 'amc_invalid', __( 'Please write a message.', 'automotive-marketplace-core' ), array( 'status' => 400 ) );
		}
		$response = rest_ensure_response( Assistant::reply( $scope, $messages ) );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Public endpoints are open but rate limited per IP.
	 *
	 * @return true|\WP_Error
	 */
	public static function public_permission() {
		if ( ! Rate_Limiter::allow( 'api', (int) Settings::get( 'api_rate_limit' ), MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'amc_rate_limited', __( 'Too many requests. Please retry in a minute.', 'automotive-marketplace-core' ), array( 'status' => 429 ) );
		}
		return true;
	}

	public static function list_vehicles( \WP_REST_Request $request ) {
		$filters = Vehicles::sanitize_filters( $request->get_query_params() );
		$results = Vehicles::search( $filters, absint( $request->get_param( 'page' ) ), absint( $request->get_param( 'per_page' ) ) );

		$response = new \WP_REST_Response(
			array(
				'items'    => array_map( array( __CLASS__, 'vehicle_summary' ), $results['items'] ),
				'total'    => $results['total'],
				'pages'    => $results['pages'],
				'page'     => $results['page'],
				'per_page' => $results['per_page'],
			)
		);
		$response->header( 'X-WP-Total', $results['total'] );
		$response->header( 'X-WP-TotalPages', $results['pages'] );
		return $response;
	}

	public static function get_vehicle( \WP_REST_Request $request ) {
		$vehicle = Vehicles::get_by_id( $request['id'] );
		if ( ! $vehicle ) {
			return new \WP_Error( 'amc_not_found', __( 'Listing not found.', 'automotive-marketplace-core' ), array( 'status' => 404 ) );
		}
		$data                = self::vehicle_summary( $vehicle );
		$data['description'] = $vehicle->description;
		$data['features']    = $vehicle->features;
		$data['media']       = array_map(
			static function ( $media ) {
				return array(
					'url'    => $media->url,
					'alt'    => $media->alt,
					'width'  => (int) $media->width,
					'height' => (int) $media->height,
				);
			},
			$vehicle->media
		);
		$dealer              = Dealers::get_public_by_id( $vehicle->dealer_id );
		$data['dealer']      = $dealer ? self::dealer_public( $dealer ) : null;
		return rest_ensure_response( $data );
	}

	public static function get_facets() {
		$facets = Vehicles::facets();
		return rest_ensure_response(
			array(
				'brands'        => $facets['brands'],
				'cities'        => $facets['cities'],
				'dealers'       => $facets['dealers'],
				'years'         => $facets['years'],
				'fuel_types'    => Catalog::fuel_types(),
				'transmissions' => Catalog::transmissions(),
				'body_types'    => Catalog::body_types(),
				'conditions'    => Catalog::conditions(),
				'listing_types' => Catalog::listing_types(),
			)
		);
	}

	public static function list_dealers( \WP_REST_Request $request ) {
		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 24 ) ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$results  = Dealers::search(
			array(
				'city'   => (string) $request->get_param( 'city' ),
				'q'      => (string) $request->get_param( 'q' ),
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);
		return rest_ensure_response(
			array(
				'items' => array_map( array( __CLASS__, 'dealer_public' ), $results['items'] ),
				'total' => $results['total'],
				'page'  => $page,
			)
		);
	}

	public static function catalog_fragment( \WP_REST_Request $request ) {
		$preset = (string) $request->get_param( 'preset' );
		$preset = isset( Marketplace::presets()[ $preset ] ) ? $preset : 'all';
		$dealer = sanitize_title( (string) $request->get_param( 'dealer_page' ) );

		$filters = Marketplace::apply_preset( Vehicles::sanitize_filters( $request->get_query_params() ), $preset );
		if ( $dealer ) {
			// Dealer profile page: the dealer filter is fixed by the page.
			$filters['dealer'] = $dealer;
			$base_url          = amc_dealer_url( $dealer );
		} else {
			$base_url = amc_catalog_url( $preset );
		}
		$page    = max( 1, absint( $request->get_param( 'pg' ) ) );
		$results = Vehicles::search( $filters, $page );

		$query = amc_filters_to_query( $filters, $preset );
		if ( $dealer ) {
			unset( $query['dealer'] );
		}
		$html = Marketplace::render_results( $results, $filters, $base_url, $query );
		if ( $page > 1 ) {
			$query['pg'] = $page;
		}

		return rest_ensure_response(
			array(
				'html'  => $html,
				'total' => $results['total'],
				'url'   => $query ? add_query_arg( array_map( 'rawurlencode', $query ), $base_url ) : $base_url,
			)
		);
	}

	/**
	 * Public representation of a listing. This is an explicit allow-list:
	 * adding a database column never exposes it by accident.
	 *
	 * @param object $vehicle Hydrated listing.
	 */
	public static function vehicle_summary( $vehicle ) {
		return array(
			'id'            => $vehicle->id,
			'slug'          => $vehicle->slug,
			'url'           => amc_vehicle_url( $vehicle ),
			'title'         => $vehicle->title,
			'brand'         => $vehicle->brand,
			'model'         => $vehicle->model,
			'year'          => $vehicle->year,
			'mileage_km'    => $vehicle->mileage_km,
			'fuel_type'     => $vehicle->fuel_type,
			'transmission'  => $vehicle->transmission,
			'body_type'     => $vehicle->body_type,
			'condition'     => $vehicle->vehicle_condition,
			'color'         => $vehicle->color,
			'listing_type'  => $vehicle->listing_type,
			'price'         => array(
				'amount'    => $vehicle->price_amount,
				'currency'  => $vehicle->currency,
				'period'    => $vehicle->price_period,
				'formatted' => amc_vehicle_price( $vehicle ),
			),
			'city'          => $vehicle->city,
			'country_code'  => $vehicle->country_code,
			'status'        => $vehicle->status,
			'is_featured'   => (bool) $vehicle->is_featured,
			'is_stale'      => (bool) $vehicle->is_stale,
			'thumbnail_url' => $vehicle->thumbnail_url,
			'dealer'        => array(
				'name'     => $vehicle->dealer_name,
				'slug'     => $vehicle->dealer_slug,
				'verified' => (bool) $vehicle->dealer_verified,
				'url'      => amc_dealer_url( $vehicle->dealer_slug ),
			),
			'published_at'  => mysql_to_rfc3339( $vehicle->published_at ),
		);
	}

	public static function dealer_public( $dealer ) {
		$data = array(
			'slug'        => $dealer->slug,
			'url'         => amc_dealer_url( $dealer->slug ),
			'name'        => $dealer->business_name,
			'logo_url'    => $dealer->logo_url,
			'description' => $dealer->description,
			'address'     => $dealer->address,
			'city'        => $dealer->city,
			'country'     => $dealer->country_code,
			'phone'       => $dealer->phone,
			'whatsapp'    => $dealer->whatsapp,
			'website_url' => $dealer->website_url,
			'verified'    => (bool) $dealer->is_verified,
		);
		if ( isset( $dealer->vehicle_count ) ) {
			$data['vehicle_count'] = $dealer->vehicle_count;
		}
		return $data;
	}
}
