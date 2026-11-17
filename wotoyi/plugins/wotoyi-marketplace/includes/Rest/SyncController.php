<?php
/**
 * Signed sync endpoints. The dealer comes ONLY from Authenticator (never from request data).
 * Handlers read the route and the raw JSON body (both covered by the signature) and never query parameters
 * (a non-empty query string is refused by Authenticator::permission()).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Rest;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Sync\Authenticator;
use Wotoyi\Marketplace\Sync\Receiver;

defined( 'ABSPATH' ) || exit;

final class SyncController {

	/** @return array<string,mixed> */
	private function dealer( \WP_REST_Request $request ): array {
		$dealer = Authenticator::dealer( $request );
		if ( ! $dealer ) { // Cannot happen if permission_callback ran; fail closed.
			throw new \LogicException( 'unauthenticated sync request' );
		}
		return $dealer;
	}

	/** @return array<string,mixed>|\WP_Error Decoded JSON object (empty array for empty body). */
	private function json( \WP_REST_Request $request, bool $required = true ): array|\WP_Error {
		$raw = (string) $request->get_body();
		if ( '' === trim( $raw ) ) {
			return $required ? new \WP_Error( 'wotoyi_invalid_json', 'JSON body required.', array( 'status' => 400 ) ) : array();
		}
		$data = json_decode( $raw, true, 32 );
		if ( ! is_array( $data ) || ( array() !== $data && array_is_list( $data ) ) ) {
			return new \WP_Error( 'wotoyi_invalid_json', 'Body must be a JSON object.', array( 'status' => 400 ) );
		}
		return $data;
	}

	private function respond( array $result ): \WP_REST_Response {
		$status = (int) ( $result['http_status'] ?? 200 );
		return new \WP_REST_Response( $result, $status );
	}

	private function request_id( \WP_REST_Request $request ): string {
		$id = (string) $request->get_header( 'x_request_id' );
		return 1 === preg_match( '/^[A-Za-z0-9_.:\-]{1,64}$/', $id ) ? $id : '';
	}

	/* ------------------------------------------------------------ */

	public function pair( \WP_REST_Request $request ) {
		$body = $this->json( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$res = Plugin::pairing()->pair( $body, Ip::client() );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$r = new \WP_REST_Response( $res, 201 );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	public function health( \WP_REST_Request $request ): \WP_REST_Response {
		$dealer = $this->dealer( $request );
		return new \WP_REST_Response(
			array(
				'ok'          => true,
				'dealer_id'   => $dealer['uuid'],
				'api_version' => Receiver::API_VERSION,
				'server_time' => Clock::now(),
				'vehicles'    => Plugin::vehicles()->count_by_dealer( (int) $dealer['id'] ),
				'services'    => Plugin::services()->count_by_dealer( (int) $dealer['id'] ),
			)
		);
	}

	public function event( \WP_REST_Request $request ) {
		$body = $this->json( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		return $this->respond( Plugin::receiver()->handle_event( $this->dealer( $request ), $body, 'http', $this->request_id( $request ) ) );
	}

	public function batch( \WP_REST_Request $request ) {
		$body = $this->json( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( ! isset( $body['events'] ) || ! is_array( $body['events'] ) || ! array_is_list( $body['events'] ) ) {
			return new \WP_Error( 'wotoyi_invalid_json', 'events must be a list.', array( 'status' => 422 ) );
		}
		return new \WP_REST_Response( Plugin::receiver()->handle_batch( $this->dealer( $request ), $body['events'], 'http', $this->request_id( $request ) ) );
	}

	/** REST-style wrappers: build an idempotent event from route + body. */
	private function wrap( \WP_REST_Request $request, string $entity, string $action ) {
		$body = $this->json( $request, 'deleted' !== $action );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		// M1: ONLY the signed route segment. `$request['source_id']` would be shadowed by a JSON body or query value.
		$source_id = (string) ( $request->get_url_params()['source_id'] ?? '' );
		if ( isset( $body['source_id'] ) && (string) $body['source_id'] !== $source_id ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'source_id in body does not match the URL.', array( 'status' => 422 ) );
		}
		$body['source_id'] = $source_id;
		$dealer            = $this->dealer( $request );
		$event             = array(
			'event_id'    => substr( $action[0] . '-' . hash( 'sha256', $dealer['id'] . '|' . $request->get_method() . '|' . $request->get_route() . '|' . $request->get_body() ), 0, 40 ),
			'event_type'  => $entity . '.' . $action,
			'api_version' => Receiver::API_VERSION,
			'data'        => $body,
		);
		return $this->respond( Plugin::receiver()->handle_event( $dealer, $event, 'http', $this->request_id( $request ) ) );
	}

	public function vehicle_put( \WP_REST_Request $request ) {
		return $this->wrap( $request, 'vehicle', 'updated' );
	}

	public function vehicle_delete( \WP_REST_Request $request ) {
		return $this->wrap( $request, 'vehicle', 'deleted' );
	}

	public function service_put( \WP_REST_Request $request ) {
		return $this->wrap( $request, 'service', 'updated' );
	}

	public function service_delete( \WP_REST_Request $request ) {
		return $this->wrap( $request, 'service', 'deleted' );
	}

	public function leads_pull( \WP_REST_Request $request ) {
		$body = $this->json( $request, false );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$limit = isset( $body['limit'] ) ? (int) $body['limit'] : 50;
		return new \WP_REST_Response( array( 'leads' => Plugin::receiver()->pull_leads( $this->dealer( $request ), $limit ) ) );
	}

	public function leads_ack( \WP_REST_Request $request ) {
		$body = $this->json( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$ids = isset( $body['lead_ids'] ) && is_array( $body['lead_ids'] ) ? $body['lead_ids'] : array();
		return new \WP_REST_Response( array( 'acknowledged' => Plugin::receiver()->ack_leads( $this->dealer( $request ), $ids ) ) );
	}

	/** Reconciliation: what the marketplace holds for this dealer (source_id, version, status), cursor-paginated. */
	public function manifest( \WP_REST_Request $request ) {
		$body = $this->json( $request, false );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$type = $body['type'] ?? 'vehicle';
		if ( ! in_array( $type, array( 'vehicle', 'service' ), true ) ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'type must be vehicle or service.', array( 'status' => 422 ) );
		}
		$repo = 'service' === $type ? Plugin::services() : Plugin::vehicles();
		$res  = $repo->manifest( (int) $this->dealer( $request )['id'], (int) ( $body['cursor'] ?? 0 ), (int) ( $body['limit'] ?? 200 ) );
		return new \WP_REST_Response( array( 'type' => $type, 'items' => $res['items'], 'next_cursor' => $res['next'] ) );
	}

	public function rotate_key( \WP_REST_Request $request ): \WP_REST_Response {
		$dealer = $this->dealer( $request );
		$creds  = Plugin::dealers()->rotate_secret( $dealer, Settings::int( 'key_grace' ) );
		Plugin::audit()->record( 'dealer', 0, 'key.rotate', (int) $dealer['id'], array( 'key_grace' => Settings::int( 'key_grace' ) ), $this->request_id( $request ) );
		$r     = new \WP_REST_Response( $creds + array( 'previous_valid_for' => Settings::int( 'key_grace' ) ) );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	public function disconnect( \WP_REST_Request $request ): \WP_REST_Response {
		$dealer = $this->dealer( $request );
		Plugin::dealers()->disconnect( (int) $dealer['id'] );
		Plugin::audit()->record( 'dealer', 0, 'dealer.disconnect', (int) $dealer['id'], array( 'status' => array( $dealer['status'], 'disconnected' ) ), $this->request_id( $request ) );
		return new \WP_REST_Response( array( 'ok' => true, 'status' => 'disconnected' ) );
	}
}
