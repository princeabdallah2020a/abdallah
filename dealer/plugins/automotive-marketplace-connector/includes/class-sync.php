<?php
/**
 * Vehicle synchronisation CRM → marketplace.
 *
 * - Every CRM change ("adc_vehicle_changed") marks the vehicle as pending in
 *   acx_items; the payload is built when it is sent, so rapid edits are
 *   coalesced into one call carrying the latest state.
 * - The queue is processed right after the change (single cron event) and
 *   every five minutes, with exponential backoff on failure.
 * - An hourly heartbeat sends the list of vehicles that should be online;
 *   the marketplace unpublishes the others and answers with the ones it is
 *   missing, which are queued again (self-healing).
 *
 * A vehicle is "wanted" on the marketplace when "Publish on the marketplace"
 * is ticked in the CRM and its status is published or reserved.
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

defined( 'ABSPATH' ) || exit;

final class Sync {

	const CRON      = 'acx_sync_run';
	const HEARTBEAT = 'acx_heartbeat';
	const BACKOFF   = array( 60, 300, 900, 3600, 10800, 21600 );
	const BATCH     = 20;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( 'adc_vehicle_changed', array( __CLASS__, 'vehicle_changed' ), 10, 3 );
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( self::CRON . '_now', array( __CLASS__, 'run' ) );
		add_action( self::HEARTBEAT, array( __CLASS__, 'heartbeat' ) );
		add_action(
			'init',
			static function () {
				if ( ! Connection::is_connected() ) {
					return;
				}
				if ( ! wp_next_scheduled( self::CRON ) ) {
					wp_schedule_event( time() + 120, 'acx_five_minutes', self::CRON );
				}
				if ( ! wp_next_scheduled( self::HEARTBEAT ) ) {
					wp_schedule_event( time() + 300, 'hourly', self::HEARTBEAT );
				}
			}
		);
	}

	public static function schedules( $schedules ) {
		$schedules['acx_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'automotive-marketplace-connector' ),
		);
		return $schedules;
	}

	/* ------------------------------------------------------------------ */
	/* Queue                                                               */
	/* ------------------------------------------------------------------ */

	public static function is_wanted( $vehicle ) {
		return $vehicle && ! empty( $vehicle->publish_to_marketplace ) && in_array( $vehicle->status, array( 'published', 'reserved' ), true );
	}

	private static function item( $uid ) {
		global $wpdb;
		$table = Database::table( 'items' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE uid = %s", $uid ) );
	}

	/**
	 * Mark a vehicle for synchronisation.
	 *
	 * @param string $uid        Vehicle uid (marketplace external_id).
	 * @param int    $vehicle_id Local id (0 once deleted).
	 * @param string $op         put|delete.
	 */
	public static function queue( $uid, $vehicle_id, $op = 'put' ) {
		global $wpdb;
		if ( ! $uid ) {
			return;
		}
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . Database::table( 'items' ) . ' (uid, vehicle_id, op, state, attempts, next_attempt_at, updated_at) VALUES (%s, %d, %s, %s, 0, %s, %s)
				ON DUPLICATE KEY UPDATE vehicle_id = VALUES(vehicle_id), op = VALUES(op), state = VALUES(state), attempts = 0, next_attempt_at = VALUES(next_attempt_at), updated_at = VALUES(updated_at)',
				$uid,
				absint( $vehicle_id ),
				'delete' === $op ? 'delete' : 'put',
				'pending',
				$now,
				$now
			)
		);
		if ( Connection::is_connected() && ! wp_next_scheduled( self::CRON . '_now' ) ) {
			wp_schedule_single_event( time() + 5, self::CRON . '_now' );
		}
	}

	/**
	 * CRM hook.
	 *
	 * @param int    $vehicle_id Vehicle id.
	 * @param string $event      created|updated|status|deleted|…
	 * @param string $uid        Uid (given for deletions).
	 */
	public static function vehicle_changed( $vehicle_id, $event, $uid = '' ) {
		if ( 'deleted' === $event ) {
			$item = self::item( (string) $uid );
			if ( $item && $item->remote_id ) {
				self::queue( (string) $uid, 0, 'delete' );
			} elseif ( $item ) {
				global $wpdb;
				$wpdb->delete( Database::table( 'items' ), array( 'uid' => (string) $uid ) );
			}
			return;
		}
		$vehicle = \ADC\Vehicles::get( $vehicle_id );
		if ( ! $vehicle ) {
			return;
		}
		$item = self::item( $vehicle->uid );
		// Nothing to do for a vehicle that is neither wanted nor already on the marketplace.
		if ( ! self::is_wanted( $vehicle ) && ( ! $item || ! $item->remote_id ) ) {
			return;
		}
		self::queue( $vehicle->uid, $vehicle->id );
	}

	/** Queue every vehicle (first connection, resync requested by the marketplace). */
	public static function queue_all() {
		global $wpdb;
		$table = \ADC\Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( "SELECT id, uid, status, publish_to_marketplace FROM $table" );
		$count = 0;
		foreach ( (array) $rows as $row ) {
			$item = self::item( $row->uid );
			if ( self::is_wanted( $row ) || ( $item && $item->remote_id ) ) {
				self::queue( $row->uid, (int) $row->id );
				++$count;
			}
		}
		Database::log( sprintf( /* translators: %d: number of vehicles */ _n( 'Full synchronisation: %d vehicle queued.', 'Full synchronisation: %d vehicles queued.', $count, 'automotive-marketplace-connector' ), $count ) );
		return $count;
	}

	/* ------------------------------------------------------------------ */
	/* Sending                                                             */
	/* ------------------------------------------------------------------ */

	/** Map the CRM status to the marketplace one. */
	private static function remote_status( $vehicle ) {
		if ( ! $vehicle->publish_to_marketplace ) {
			return 'unpublished';
		}
		$map = array(
			'published' => 'active',
			'reserved'  => 'reserved',
			'sold'      => 'sold',
			'rented'    => 'rented',
		);
		return isset( $map[ $vehicle->status ] ) ? $map[ $vehicle->status ] : 'unpublished';
	}

	/** Public data sent to the marketplace — never purchase price, notes or customers. */
	public static function payload( $vehicle ) {
		$business = adc_business();
		$media    = array();
		foreach ( \ADC\Vehicles::media( $vehicle->id ) as $attachment_id ) {
			$image = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( $image ) {
				$media[] = array(
					'url'    => $image[0],
					'width'  => (int) $image[1],
					'height' => (int) $image[2],
					'alt'    => $vehicle->title,
				);
			}
		}
		$thumb = $vehicle->thumbnail_id ? wp_get_attachment_image_url( $vehicle->thumbnail_id, 'medium_large' ) : '';
		return array(
			'title'             => $vehicle->title,
			'brand'             => $vehicle->brand,
			'model'             => $vehicle->model,
			'year'              => (int) $vehicle->year,
			'mileage_km'        => (int) $vehicle->mileage_km,
			'fuel_type'         => $vehicle->fuel_type,
			'transmission'      => $vehicle->transmission,
			'body_type'         => $vehicle->body_type,
			'vehicle_condition' => $vehicle->vehicle_condition,
			'color'             => $vehicle->color,
			'listing_type'      => $vehicle->listing_type,
			'price_amount'      => (int) $vehicle->price_amount,
			'price_period'      => $vehicle->price_period,
			'currency'          => $vehicle->currency,
			'city'              => $business->city,
			'country_code'      => $business->country,
			'description'       => $vehicle->description,
			'features'          => array_values( (array) $vehicle->features ),
			'thumbnail_url'     => $thumb ? $thumb : ( $media ? $media[0]['url'] : '' ),
			'media'             => $media,
			'source_url'        => adc_vehicle_url( $vehicle ),
			'status'            => self::remote_status( $vehicle ),
			'source_updated_at' => mysql_to_rfc3339( $vehicle->updated_at ),
		);
	}

	/** Process pending items (cron). */
	public static function run() {
		global $wpdb;
		if ( ! Connection::is_connected() || get_transient( 'acx_sync_lock' ) ) {
			return;
		}
		set_transient( 'acx_sync_lock', 1, 4 * MINUTE_IN_SECONDS );
		$table = Database::table( 'items' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE state = 'pending' AND next_attempt_at <= %s ORDER BY updated_at ASC LIMIT %d", current_time( 'mysql', true ), self::BATCH ) );
		foreach ( (array) $rows as $row ) {
			self::send( $row );
		}
		delete_transient( 'acx_sync_lock' );
		// More waiting: continue soon.
		if ( count( (array) $rows ) === self::BATCH && ! wp_next_scheduled( self::CRON . '_now' ) ) {
			wp_schedule_single_event( time() + 30, self::CRON . '_now' );
		}
	}

	/**
	 * Send one item.
	 *
	 * @return bool Success.
	 */
	public static function send( $row ) {
		global $wpdb;
		$table = Database::table( 'items' );
		$now   = current_time( 'mysql', true );
		$path  = '/sync/vehicles/' . rawurlencode( $row->uid );
		// Same idempotency key while the state is unchanged, so a retry after a timeout is never applied twice.
		$idem = substr( hash( 'sha256', $row->uid . '|' . $row->op . '|' . $row->updated_at ), 0, 40 );

		if ( 'delete' === $row->op ) {
			$result = Connection::request( 'DELETE', $path, null, $idem );
			if ( ! is_wp_error( $result ) ) {
				$wpdb->delete( $table, array( 'uid' => $row->uid ) );
				return true;
			}
		} else {
			$vehicle = \ADC\Vehicles::get( $row->vehicle_id );
			if ( ! $vehicle ) {
				$wpdb->delete( $table, array( 'uid' => $row->uid ) );
				return true;
			}
			$result = Connection::request( 'PUT', $path, self::payload( $vehicle ), $idem );
			if ( ! is_wp_error( $result ) ) {
				$wpdb->update(
					$table,
					array(
						'state'         => 'synced',
						'attempts'      => 0,
						'remote_id'     => isset( $result['id'] ) ? absint( $result['id'] ) : (int) $row->remote_id,
						'remote_url'    => isset( $result['url'] ) ? esc_url_raw( (string) $result['url'] ) : $row->remote_url,
						'remote_status' => isset( $result['status'] ) ? sanitize_key( (string) $result['status'] ) : '',
						'last_error'    => '',
						'synced_at'     => $now,
					),
					array( 'uid' => $row->uid )
				);
				return true;
			}
		}

		// Failure: retry with backoff, except for errors a retry cannot fix.
		$status   = (int) ( $result->get_error_data()['status'] ?? 0 );
		$attempts = (int) $row->attempts + 1;
		$final    = in_array( $status, array( 400, 402, 409, 422 ), true ) || $attempts > count( self::BACKOFF );
		$wpdb->update(
			$table,
			array(
				'state'           => $final ? 'error' : 'pending',
				'attempts'        => $attempts,
				'last_error'      => mb_substr( $result->get_error_message(), 0, 255 ),
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + ( $final ? 0 : self::BACKOFF[ $attempts - 1 ] ) ),
			),
			array( 'uid' => $row->uid )
		);
		if ( $final ) {
			Database::log( $row->uid . ': ' . $result->get_error_message(), 'error' );
		}
		return false;
	}

	/** Hourly reconciliation. */
	public static function heartbeat() {
		global $wpdb;
		if ( ! Connection::is_connected() ) {
			return;
		}
		Database::cleanup();
		$table = \ADC\Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( "SELECT id, uid, updated_at FROM $table WHERE publish_to_marketplace = 1 AND status IN ('published','reserved')" );
		$wanted = array();
		$ids    = array();
		foreach ( (array) $rows as $row ) {
			$wanted[]          = array(
				'external_id' => $row->uid,
				'updated_at'  => mysql_to_rfc3339( $row->updated_at ),
			);
			$ids[ $row->uid ] = (int) $row->id;
		}
		$result = Connection::request( 'POST', '/sync/heartbeat', array( 'vehicles' => $wanted ) );
		if ( is_wp_error( $result ) ) {
			Database::log( __( 'Heartbeat failed:', 'automotive-marketplace-connector' ) . ' ' . $result->get_error_message(), 'error' );
			return;
		}
		Connection::save_remote( $result );
		Contacts::push();
		foreach ( (array) ( $result['missing'] ?? array() ) as $uid ) {
			if ( isset( $ids[ $uid ] ) ) {
				self::queue( (string) $uid, $ids[ $uid ] );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Reporting                                                           */
	/* ------------------------------------------------------------------ */

	public static function counts() {
		global $wpdb;
		$table = Database::table( 'items' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT state, COUNT(*) AS n FROM $table GROUP BY state" );
		$out  = array(
			'synced'  => 0,
			'pending' => 0,
			'error'   => 0,
		);
		foreach ( (array) $rows as $row ) {
			$out[ $row->state ] = (int) $row->n;
		}
		return $out;
	}

	public static function items( $state = '', $limit = 100 ) {
		global $wpdb;
		$i     = Database::table( 'items' );
		$v     = \ADC\Database::table( 'vehicles' );
		$where = $state ? $wpdb->prepare( ' WHERE i.state = %s', $state ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.*, v.title, v.status AS local_status FROM $i i LEFT JOIN $v v ON v.id = i.vehicle_id $where ORDER BY i.updated_at DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	/** Put failed items back in the queue. */
	public static function retry_errors() {
		global $wpdb;
		return (int) $wpdb->update( Database::table( 'items' ), array( 'state' => 'pending', 'attempts' => 0, 'next_attempt_at' => current_time( 'mysql', true ) ), array( 'state' => 'error' ) );
	}
}
