<?php
/**
 * Logged-in buyer endpoints: favourites and saved searches. Always scoped to the current user id.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Rest;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Search\Filters;

defined( 'ABSPATH' ) || exit;

final class AccountController {

	public function favourites(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'vehicles' => Plugin::favourites()->list_vehicles( get_current_user_id() ) ) );
	}

	public function favourite_add( \WP_REST_Request $request ) {
		$params = (array) json_decode( (string) $request->get_body(), true, 8 );
		$type   = isset( $params['type'] ) && 'service' === $params['type'] ? 'service' : 'vehicle';
		$ref    = isset( $params['ref'] ) && is_string( $params['ref'] ) ? $params['ref'] : '';
		$target = 'service' === $type ? Plugin::search()->published_service_ref( $ref ) : Plugin::search()->published_vehicle_ref( $ref );
		if ( ! $target ) {
			return new \WP_Error( 'wotoyi_not_found', 'Item not found.', array( 'status' => 404 ) );
		}
		if ( ! Plugin::favourites()->add( get_current_user_id(), $type, $target['id'] ) ) {
			return new \WP_Error( 'wotoyi_limit_reached', 'Favourites limit reached.', array( 'status' => 409 ) );
		}
		return new \WP_REST_Response( array( 'ok' => true ), 201 );
	}

	public function favourite_remove( \WP_REST_Request $request ) {
		$type   = (string) $request['type'];
		$target = 'service' === $type ? Plugin::search()->published_service_ref( (string) $request['ref'] ) : Plugin::search()->published_vehicle_ref( (string) $request['ref'] );
		if ( $target ) {
			Plugin::favourites()->remove( get_current_user_id(), $type, $target['id'] );
		}
		return new \WP_REST_Response( array( 'ok' => true ) ); // Idempotent.
	}

	public function saved_searches(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'items' => Plugin::saved_searches()->list_for_user( get_current_user_id() ) ) );
	}

	public function saved_search_add( \WP_REST_Request $request ) {
		$params  = (array) json_decode( (string) $request->get_body(), true, 8 );
		$name    = isset( $params['name'] ) && is_string( $params['name'] ) ? mb_substr( trim( sanitize_text_field( $params['name'] ) ), 0, 120 ) : '';
		$filters = Filters::parse( isset( $params['filters'] ) && is_array( $params['filters'] ) ? $params['filters'] : array() );
		if ( '' === $name ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'name is required.', array( 'status' => 422 ) );
		}
		$res = Plugin::saved_searches()->create( get_current_user_id(), $name, $filters, ! empty( $params['notify'] ) );
		if ( is_string( $res ) ) {
			return new \WP_Error( 'wotoyi_' . $res, 'Saved search not created: ' . $res, array( 'status' => 'limit_reached' === $res || 'duplicate' === $res ? 409 : 422 ) );
		}
		return new \WP_REST_Response( array( 'ok' => true, 'id' => $res, 'filters' => $filters ), 201 );
	}

	public function saved_search_delete( \WP_REST_Request $request ) {
		if ( ! Plugin::saved_searches()->delete( get_current_user_id(), (int) $request['id'] ) ) {
			return new \WP_Error( 'wotoyi_not_found', 'Saved search not found.', array( 'status' => 404 ) ); // Also for other users' ids.
		}
		return new \WP_REST_Response( array( 'ok' => true ) );
	}
}
