<?php
/**
 * Signed webhooks from the marketplace to dealer websites.
 *
 * Deliveries are queued in the database and sent by WP-Cron with
 * exponential backoff, so a dealer website being offline never loses an
 * event. Target: {site_url}/wp-json/dealer/v1/webhooks/marketplace
 *
 * Events: lead.created, resync.requested, dealer.updated.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Webhooks {

	const CRON     = 'amc_webhooks_run';
	const SCHEDULE = 'amc_five_minutes';
	const ROUTE    = '/dealer/v1/webhooks/marketplace';
	const BACKOFF  = array( 60, 300, 900, 3600, 10800, 21600, 43200, 86400 );

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( self::CRON . '_now', array( __CLASS__, 'run' ) );
		add_action( 'amc_dealer_changed', array( __CLASS__, 'dealer_changed' ) );
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( self::CRON ) ) {
					wp_schedule_event( time() + 60, self::SCHEDULE, self::CRON );
				}
			}
		);
	}

	public static function schedules( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'automotive-marketplace-core' ),
		);
		return $schedules;
	}

	/** True when the dealer has a connected website able to receive webhooks. */
	public static function can_deliver( $dealer_id ) {
		$credential = Credentials::active_for_dealer( $dealer_id );
		return $credential && $credential->site_url && $credential->last_handshake_at;
	}

	/**
	 * Queue an event.
	 *
	 * @return int Delivery row id, 0 when the dealer is not connected.
	 */
	public static function enqueue( $dealer_id, $event, array $data ) {
		global $wpdb;
		if ( ! self::can_deliver( $dealer_id ) ) {
			return 0;
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'webhook_deliveries' ),
			array(
				'dealer_id'       => absint( $dealer_id ),
				'delivery_id'     => wp_generate_uuid4(),
				'event'           => preg_replace( '/[^a-z_.]/', '', (string) $event ),
				'payload'         => wp_json_encode( $data ),
				'status'          => 'pending',
				'next_attempt_at' => $now,
				'created_at'      => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		// Try quickly instead of waiting for the five-minute run.
		if ( ! wp_next_scheduled( self::CRON . '_now' ) ) {
			wp_schedule_single_event( time(), self::CRON . '_now' );
		}
		return $id;
	}

	/** @var int[] Dealers changed during this request. */
	private static $changed = array();

	/** Collect changes; the event is built at the end of the request, with the final state. */
	public static function dealer_changed( $dealer_id ) {
		if ( ! self::$changed ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_changes' ) );
		}
		self::$changed[ absint( $dealer_id ) ] = true;
	}

	public static function flush_changes() {
		foreach ( array_keys( self::$changed ) as $dealer_id ) {
			$dealer = Memberships::get_dealer( $dealer_id );
			if ( ! $dealer ) {
				continue;
			}
			$current = Subscriptions::current_for_dealer( $dealer_id );
			self::enqueue(
				$dealer_id,
				'dealer.updated',
				array(
					'status'   => $dealer->status,
					'plan'     => $current ? $current->plan_name : '',
					'ends_at'  => $current && $current->ends_at ? mysql_to_rfc3339( $current->ends_at ) : null,
					'vehicles' => $current ? (int) $current->vehicle_limit : 0,
				)
			);
		}
		self::$changed = array();
	}

	/** Process due deliveries (cron). */
	public static function run() {
		global $wpdb;
		if ( get_transient( 'amc_webhooks_lock' ) ) {
			return;
		}
		set_transient( 'amc_webhooks_lock', 1, 4 * MINUTE_IN_SECONDS );
		$table = Database::table( 'webhook_deliveries' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = 'pending' AND next_attempt_at <= %s ORDER BY id ASC LIMIT 25", current_time( 'mysql', true ) ) );
		foreach ( (array) $rows as $row ) {
			self::deliver( $row );
		}
		// Housekeeping: keep 30 days of history.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE status <> 'pending' AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		$events = Database::table( 'sync_events' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $events WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS ) ) );
		delete_transient( 'amc_webhooks_lock' );
	}

	/**
	 * Send one delivery and record the outcome.
	 *
	 * @param object $row Delivery row.
	 * @return bool Delivered.
	 */
	public static function deliver( $row ) {
		global $wpdb;
		$table      = Database::table( 'webhook_deliveries' );
		$credential = Credentials::active_for_dealer( $row->dealer_id );
		$secret     = $credential ? Credentials::secret( $credential ) : '';
		$attempts   = (int) $row->attempts + 1;
		$error      = '';

		if ( ! $credential || ! $credential->site_url || ! $secret ) {
			$error = 'Dealer website not connected';
		} else {
			$body = wp_json_encode(
				array(
					'id'         => $row->delivery_id,
					'event'      => $row->event,
					'created_at' => mysql_to_rfc3339( $row->created_at ),
					'data'       => json_decode( (string) $row->payload, true ),
				)
			);
			$ts       = time();
			$response = wp_safe_remote_post(
				$credential->site_url . '/wp-json' . self::ROUTE,
				array(
					'timeout'     => 10,
					'redirection' => 0,
					'headers'     => array(
						'Content-Type'    => 'application/json',
						'X-AMC-Key'       => $credential->key_id,
						'X-AMC-Timestamp' => (string) $ts,
						'X-AMC-Signature' => Crypto::sign( $secret, $ts, 'POST', self::ROUTE, $body ),
						'X-AMC-Delivery'  => $row->delivery_id,
					),
					'body'        => $body,
				)
			);
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				$wpdb->update(
					$table,
					array(
						'status'       => 'delivered',
						'attempts'     => $attempts,
						'last_error'   => '',
						'delivered_at' => current_time( 'mysql', true ),
					),
					array( 'id' => (int) $row->id )
				);
				do_action( 'amc_webhook_delivered', $row );
				return true;
			}
			$error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
		}

		$final = $attempts > count( self::BACKOFF );
		$wpdb->update(
			$table,
			array(
				'status'          => $final ? 'failed' : 'pending',
				'attempts'        => $attempts,
				'last_error'      => mb_substr( $error, 0, 255 ),
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + ( $final ? 0 : self::BACKOFF[ $attempts - 1 ] ) ),
			),
			array( 'id' => (int) $row->id )
		);
		if ( $final ) {
			do_action( 'amc_webhook_failed', $row );
		}
		return false;
	}

	/** Retry a delivery now (staff action). */
	public static function retry( $id ) {
		global $wpdb;
		$table = Database::table( 'webhook_deliveries' );
		$wpdb->update( $table, array( 'status' => 'pending', 'next_attempt_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $id ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
		return $row ? self::deliver( $row ) : false;
	}

	public static function recent( $limit = 50 ) {
		global $wpdb;
		$w = Database::table( 'webhook_deliveries' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT w.id, w.dealer_id, w.event, w.status, w.attempts, w.last_error, w.created_at, w.delivered_at, d.business_name FROM $w w LEFT JOIN $d d ON d.id = w.dealer_id ORDER BY w.id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	public static function sync_log( $limit = 50 ) {
		global $wpdb;
		$e = Database::table( 'sync_events' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.id, e.method, e.route, e.external_id, e.status_code, e.error, e.created_at, d.business_name FROM $e e LEFT JOIN $d d ON d.id = e.dealer_id ORDER BY e.id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}
}
