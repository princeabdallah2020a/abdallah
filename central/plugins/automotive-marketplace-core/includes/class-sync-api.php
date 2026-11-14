<?php
/**
 * Authenticated dealer synchronisation API — marketplace/v1/sync/*.
 *
 * Every request is signed by the dealer's Marketplace Connector:
 *   X-AMC-Key        key id
 *   X-AMC-Timestamp  unix time (±300 s)
 *   X-AMC-Signature  hex(HMAC-SHA256(secret, ts \n METHOD \n route \n sha256(body)))
 *   Idempotency-Key  required on writes; a replay returns the first response.
 *
 * The dealer is ALWAYS resolved from the key: any dealer id in the body is ignored.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Sync_Api {

	const WINDOW = 300;

	/** @var object|null Credential of the current request. */
	private static $credential = null;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$auth  = array( __CLASS__, 'authenticate' );
		$route = static function ( $path, $methods, $callback ) use ( $auth ) {
			register_rest_route(
				Api::NS,
				$path,
				array(
					'methods'             => $methods,
					'callback'            => static function ( \WP_REST_Request $request ) use ( $callback ) {
						return self::idempotent( $request, $callback );
					},
					'permission_callback' => $auth,
				)
			);
		};
		$route( '/sync/handshake', 'POST', array( __CLASS__, 'handshake' ) );
		$route( '/sync/status', 'GET', array( __CLASS__, 'status' ) );
		$route( '/sync/heartbeat', 'POST', array( __CLASS__, 'heartbeat' ) );
		$route( '/sync/vehicles/(?P<external_id>[A-Za-z0-9_-]{1,64})', 'PUT', array( __CLASS__, 'put_vehicle' ) );
		$route( '/sync/vehicles/(?P<external_id>[A-Za-z0-9_-]{1,64})/status', 'PATCH', array( __CLASS__, 'patch_status' ) );
		$route( '/sync/vehicles/(?P<external_id>[A-Za-z0-9_-]{1,64})', 'DELETE', array( __CLASS__, 'delete_vehicle' ) );
		$route( '/sync/contacts', 'POST', array( __CLASS__, 'contacts' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Authentication                                                      */
	/* ------------------------------------------------------------------ */

	private static function deny( $code, $message, $status ) {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/** @return true|\WP_Error */
	public static function authenticate( \WP_REST_Request $request ) {
		$key       = (string) $request->get_header( 'x-amc-key' );
		$timestamp = (int) $request->get_header( 'x-amc-timestamp' );
		$signature = strtolower( (string) $request->get_header( 'x-amc-signature' ) );
		if ( '' === $key || ! $timestamp || '' === $signature ) {
			return self::deny( 'amc_auth_missing', 'Missing authentication headers.', 401 );
		}
		if ( abs( time() - $timestamp ) > self::WINDOW ) {
			return self::deny( 'amc_auth_clock', 'Timestamp outside the allowed window. Check the server clock.', 401 );
		}
		$credential = Credentials::find_by_key( $key );
		if ( ! $credential || 'active' !== $credential->status ) {
			return self::deny( 'amc_auth_key', 'Unknown or revoked key.', 401 );
		}
		$secret   = Credentials::secret( $credential );
		$expected = $secret ? Crypto::sign( $secret, $timestamp, $request->get_method(), $request->get_route(), $request->get_body() ) : '';
		if ( '' === $expected || ! hash_equals( $expected, $signature ) ) {
			Credentials::touch( $credential->id, array( 'last_error' => 'Invalid signature', 'failures' => (int) $credential->failures + 1 ) );
			return self::deny( 'amc_auth_signature', 'Invalid signature.', 401 );
		}
		$limit = 'amc_sync_rl_' . md5( $key . '|' . floor( time() / MINUTE_IN_SECONDS ) );
		$hits  = (int) get_transient( $limit );
		if ( $hits >= 600 ) {
			return self::deny( 'amc_rate_limited', 'Too many requests.', 429 );
		}
		set_transient( $limit, $hits + 1, MINUTE_IN_SECONDS );

		self::$credential = $credential;
		return true;
	}

	private static function dealer() {
		return Memberships::get_dealer( self::$credential->dealer_id );
	}

	/** Dealer must be active with a valid subscription to publish listings. */
	private static function require_active() {
		$dealer = self::dealer();
		if ( ! $dealer || 'active' !== $dealer->status ) {
			return self::deny( 'amc_dealer_inactive', 'Dealer account is not active on the marketplace.', 402 );
		}
		return true;
	}

	/**
	 * Run a handler with idempotency, logging and connection monitoring.
	 *
	 * @param callable $callback Handler.
	 */
	private static function idempotent( \WP_REST_Request $request, $callback ) {
		global $wpdb;
		$credential = self::$credential;
		$key        = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $request->get_header( 'idempotency-key' ) ), 0, 64 );
		$write      = 'GET' !== $request->get_method();
		$table      = Database::table( 'sync_events' );

		if ( $write && '' === $key ) {
			return self::deny( 'amc_idempotency', 'Idempotency-Key header is required.', 400 );
		}
		if ( $write ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$previous = $wpdb->get_row( $wpdb->prepare( "SELECT status_code, response FROM $table WHERE dealer_id = %d AND idempotency_key = %s ORDER BY id ASC LIMIT 1", (int) $credential->dealer_id, $key ) );
			if ( $previous ) {
				$replay = new \WP_REST_Response( json_decode( (string) $previous->response, true ), (int) $previous->status_code );
				$replay->header( 'Idempotent-Replay', 'true' );
				return $replay;
			}
		}

		$result   = call_user_func( $callback, $request );
		$response = rest_ensure_response( $result );
		if ( is_wp_error( $result ) ) {
			$response = rest_convert_error_to_response( $result );
		}
		$code = $response->get_status();

		if ( $write || $code >= 400 ) {
			$wpdb->insert(
				$table,
				array(
					'dealer_id'       => (int) $credential->dealer_id,
					'idempotency_key' => $key,
					'method'          => $request->get_method(),
					'route'           => substr( $request->get_route(), 0, 191 ),
					'external_id'     => substr( (string) $request['external_id'], 0, 64 ),
					'status_code'     => $code,
					'response'        => wp_json_encode( $response->get_data() ),
					'error'           => $code >= 400 && is_wp_error( $result ) ? substr( $result->get_error_message(), 0, 255 ) : '',
					'created_at'      => current_time( 'mysql', true ),
				)
			);
		}
		$now = current_time( 'mysql', true );
		if ( $code < 400 ) {
			Credentials::touch( $credential->id, array( 'last_success_at' => $now, 'failures' => 0, 'last_error' => '' ) );
		} else {
			Credentials::touch( $credential->id, array( 'last_error' => substr( is_wp_error( $result ) ? $result->get_error_message() : 'HTTP ' . $code, 0, 255 ) ) );
		}
		return $response;
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	private static function limits() {
		$dealer  = self::dealer();
		$current = Subscriptions::current_for_dealer( $dealer->id );
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE dealer_id = %d AND status IN ('active','reserved')", $dealer->id ) );
		return array(
			'dealer'       => array(
				'name'     => $dealer->business_name,
				'status'   => $dealer->status,
				'verified' => (bool) $dealer->is_verified,
				'profile'  => 'active' === $dealer->status ? amc_dealer_url( $dealer->slug ) : '',
			),
			'subscription' => $current ? array(
				'plan'      => $current->plan_name,
				'ends_at'   => mysql_to_rfc3339( $current->ends_at ),
				'in_grace'  => Subscriptions::in_grace( $current ),
			) : null,
			'limits'       => array(
				'vehicles'      => $current ? (int) $current->vehicle_limit : 0,
				'vehicles_used' => $used,
				'ai_quota'      => $current ? (int) $current->ai_quota : 0,
			),
			'server_time'  => time(),
		);
	}

	public static function handshake( \WP_REST_Request $request ) {
		$site = esc_url_raw( (string) $request->get_param( 'site_url' ), array( 'https', 'http' ) );
		Credentials::touch(
			self::$credential->id,
			array(
				'site_url'          => $site ? untrailingslashit( $site ) : self::$credential->site_url,
				'connector_version' => substr( sanitize_text_field( (string) $request->get_param( 'connector_version' ) ), 0, 20 ),
				'last_handshake_at' => current_time( 'mysql', true ),
			)
		);
		Audit::log( 'connection.handshake', 'dealer', self::$credential->dealer_id, array( 'site' => $site ) );
		return rest_ensure_response( array( 'ok' => true ) + self::limits() );
	}

	public static function status() {
		return rest_ensure_response( self::limits() );
	}

	/** Map dealer CRM statuses to marketplace listing statuses. */
	private static function map_status( $status ) {
		$map = array(
			'active'      => 'active',
			'published'   => 'active',
			'reserved'    => 'reserved',
			'sold'        => 'sold',
			'rented'      => 'rented',
			'unpublished' => 'unpublished',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : 'unpublished';
	}

	private static function existing( $external_id ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, status, source_updated_at FROM $table WHERE dealer_id = %d AND external_id = %s", (int) self::$credential->dealer_id, $external_id ) );
	}

	public static function put_vehicle( \WP_REST_Request $request ) {
		$active = self::require_active();
		if ( is_wp_error( $active ) ) {
			return $active;
		}
		$external_id = (string) $request['external_id'];
		$data        = (array) $request->get_json_params();
		$existing    = self::existing( $external_id );
		$updated     = isset( $data['source_updated_at'] ) ? strtotime( (string) $data['source_updated_at'] ) : false;

		// Out-of-order delivery: never overwrite a newer version.
		if ( $existing && $updated && $existing->source_updated_at && strtotime( $existing->source_updated_at . ' UTC' ) > $updated ) {
			return rest_ensure_response( array( 'id' => (int) $existing->id, 'ignored' => 'stale' ) );
		}

		$status = self::map_status( isset( $data['status'] ) ? (string) $data['status'] : 'active' );
		if ( in_array( $status, array( 'active', 'reserved' ), true ) && ( ! $existing || ! in_array( $existing->status, array( 'active', 'reserved' ), true ) ) ) {
			$limits = self::limits()['limits'];
			if ( $limits['vehicles_used'] >= $limits['vehicles'] ) {
				return self::deny( 'amc_plan_limit', sprintf( 'Plan limit reached (%d listings).', $limits['vehicles'] ), 409 );
			}
		}

		// Fields the dealer may never set.
		unset( $data['is_featured'], $data['is_demo'], $data['dealer_id'], $data['published_at'] );
		$data['external_id'] = $external_id;
		$data['status']      = $status;
		if ( empty( $data['thumbnail_url'] ) && ! empty( $data['media'][0]['url'] ) ) {
			$data['thumbnail_url'] = $data['media'][0]['url'];
		}
		$id = Vehicles::upsert( (int) self::$credential->dealer_id, $data );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( $updated ) {
			global $wpdb;
			$wpdb->update( Database::table( 'vehicles' ), array( 'source_updated_at' => gmdate( 'Y-m-d H:i:s', $updated ) ), array( 'id' => $id ) );
		}
		$vehicle = Vehicles::get_by_id( $id );
		return new \WP_REST_Response(
			array(
				'id'     => $id,
				'status' => $status,
				'url'    => $vehicle ? amc_vehicle_url( $vehicle ) : '',
			),
			$existing ? 200 : 201
		);
	}

	public static function patch_status( \WP_REST_Request $request ) {
		global $wpdb;
		$existing = self::existing( (string) $request['external_id'] );
		if ( ! $existing ) {
			return self::deny( 'amc_not_found', 'Unknown listing.', 404 );
		}
		$status = self::map_status( (string) $request->get_param( 'status' ) );
		$wpdb->update(
			Database::table( 'vehicles' ),
			array(
				'status'         => $status,
				'last_synced_at' => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $existing->id )
		);
		Vehicles::flush_cache();
		return rest_ensure_response( array( 'id' => (int) $existing->id, 'status' => $status ) );
	}

	public static function delete_vehicle( \WP_REST_Request $request ) {
		global $wpdb;
		$existing = self::existing( (string) $request['external_id'] );
		if ( ! $existing ) {
			return rest_ensure_response( array( 'deleted' => false ) );
		}
		$wpdb->delete( Database::table( 'vehicle_media' ), array( 'vehicle_id' => (int) $existing->id ) );
		$wpdb->delete( Database::table( 'vehicles' ), array( 'id' => (int) $existing->id ) );
		Vehicles::flush_cache();
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Heartbeat + reconciliation. Body: { vehicles: [ { external_id, updated_at } ] }
	 * listing every vehicle the dealer wants on the marketplace.
	 */
	public static function heartbeat( \WP_REST_Request $request ) {
		global $wpdb;
		$table     = Database::table( 'vehicles' );
		$dealer_id = (int) self::$credential->dealer_id;
		$wanted    = array();
		foreach ( (array) $request->get_param( 'vehicles' ) as $item ) {
			if ( is_array( $item ) && isset( $item['external_id'] ) && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $item['external_id'] ) ) {
				$wanted[ (string) $item['external_id'] ] = isset( $item['updated_at'] ) ? strtotime( (string) $item['updated_at'] ) : 0;
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT id, external_id, status, source_updated_at FROM $table WHERE dealer_id = %d", $dealer_id ) );
		$known   = array();
		$touch   = array();
		$removed = 0;
		foreach ( (array) $rows as $row ) {
			$known[ $row->external_id ] = $row;
			if ( isset( $wanted[ $row->external_id ] ) ) {
				$touch[] = (int) $row->id;
			} elseif ( in_array( $row->status, array( 'active', 'reserved' ), true ) ) {
				$wpdb->update( $table, array( 'status' => 'unpublished', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row->id ) );
				++$removed;
			}
		}
		if ( $touch ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( "UPDATE $table SET last_synced_at = %s WHERE dealer_id = %d AND id IN (" . implode( ',', array_map( 'absint', $touch ) ) . ')', current_time( 'mysql', true ), $dealer_id ) );
		}
		$missing = array();
		foreach ( $wanted as $external_id => $updated ) {
			$row = isset( $known[ $external_id ] ) ? $known[ $external_id ] : null;
			if ( ! $row || 'unpublished' === $row->status || ( $updated && ( ! $row->source_updated_at || strtotime( $row->source_updated_at . ' UTC' ) < $updated ) ) ) {
				$missing[] = $external_id;
			}
		}
		Vehicles::flush_cache();
		Credentials::touch( self::$credential->id, array( 'last_heartbeat_at' => current_time( 'mysql', true ) ) );
		return rest_ensure_response( array( 'missing' => array_slice( $missing, 0, 500 ), 'unpublished' => $removed ) + self::limits() );
	}

	/** Team phone numbers allowed to use the WhatsApp assistant (Phase 5). */
	public static function contacts( \WP_REST_Request $request ) {
		$saved = Ai_Contacts::replace_for_dealer( (int) self::$credential->dealer_id, (array) $request->get_param( 'contacts' ) );
		return rest_ensure_response( array( 'saved' => $saved ) );
	}
}
