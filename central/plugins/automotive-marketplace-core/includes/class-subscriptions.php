<?php
/**
 * Dealer subscriptions: activation, renewal queueing, reminders, expiry.
 *
 * Statuses: pending_payment → (paid, while the application is not yet
 * approved) → active → expired; cancelled at any time by staff.
 * An active subscription stays active during the grace period after its end
 * date; it expires when the grace period is over.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Subscriptions {

	const CRON = 'amc_daily';

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'process_daily' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Create an order for a plan.
	 *
	 * @return int|\WP_Error Subscription id.
	 */
	public static function create( $dealer_id, $plan_id, $months, $application_id = 0 ) {
		global $wpdb;
		$plan = Plans::get( $plan_id );
		if ( ! $plan ) {
			return new \WP_Error( 'amc_plan', __( 'Choose a plan.', 'automotive-marketplace-core' ) );
		}
		$price = Plans::price( $plan, $months );
		$now   = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'subscriptions' ),
			array(
				'dealer_id'       => absint( $dealer_id ),
				'plan_id'         => (int) $plan->id,
				'application_id'  => absint( $application_id ),
				'duration_months' => $price['months'],
				'amount'          => $price['total'],
				'currency'        => $plan->currency,
				'status'          => 'pending_payment',
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		Audit::log( 'subscription.created', 'subscription', $id, array( 'plan' => $plan->code, 'months' => $price['months'] ) );
		return $id;
	}

	private static function select() {
		$s = Database::table( 'subscriptions' );
		$p = Database::table( 'plans' );
		$d = Database::table( 'dealers' );
		return "SELECT s.*, p.name AS plan_name, p.code AS plan_code, p.vehicle_limit, p.ai_quota, p.team_members, p.visibility_boost, d.business_name
			FROM $s s LEFT JOIN $p p ON p.id = s.plan_id LEFT JOIN $d d ON d.id = s.dealer_id";
	}

	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( self::select() . ' WHERE s.id = %d', absint( $id ) ) );
	}

	public static function for_dealer( $dealer_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( self::select() . ' WHERE s.dealer_id = %d ORDER BY s.id DESC', absint( $dealer_id ) ) );
		return $rows ? $rows : array();
	}

	/** Active subscription that ends last (includes a renewal already queued). */
	public static function current_for_dealer( $dealer_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( self::select() . " WHERE s.dealer_id = %d AND s.status = 'active' ORDER BY s.ends_at DESC LIMIT 1", absint( $dealer_id ) ) );
	}

	/** Latest order waiting for payment (or paid, waiting for approval). */
	public static function open_for_dealer( $dealer_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( self::select() . " WHERE s.dealer_id = %d AND s.status IN ('pending_payment','paid') ORDER BY s.id DESC LIMIT 1", absint( $dealer_id ) ) );
	}

	public static function list( $status = '', $limit = 200 ) {
		global $wpdb;
		$where = $status ? $wpdb->prepare( ' WHERE s.status = %s', $status ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( self::select() . $where . ' ORDER BY s.id DESC LIMIT %d', absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	public static function in_grace( $sub ) {
		return $sub && 'active' === $sub->status && $sub->ends_at && strtotime( $sub->ends_at . ' UTC' ) < time();
	}

	/** Called when a payment for this subscription is verified. */
	public static function on_paid( $id ) {
		global $wpdb;
		$sub = self::get( $id );
		if ( ! $sub || 'pending_payment' !== $sub->status ) {
			return;
		}
		$app    = Memberships::latest_application( $sub->dealer_id );
		$dealer = Memberships::get_dealer( $sub->dealer_id );
		$ready  = ( $app && 'approved' === $app->status ) || ( $dealer && in_array( $dealer->status, array( 'active', 'expired', 'approved' ), true ) );
		if ( $ready ) {
			self::activate( $id );
			return;
		}
		$wpdb->update( Database::table( 'subscriptions' ), array( 'status' => 'paid', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
	}

	/**
	 * Start a subscription. A renewal starts when the current period ends.
	 *
	 * @return true|\WP_Error
	 */
	public static function activate( $id ) {
		global $wpdb;
		$sub = self::get( $id );
		if ( ! $sub || ! in_array( $sub->status, array( 'pending_payment', 'paid' ), true ) ) {
			return new \WP_Error( 'amc_sub', __( 'This subscription cannot be activated.', 'automotive-marketplace-core' ) );
		}
		$utc     = new \DateTimeZone( 'UTC' );
		$start   = new \DateTime( 'now', $utc );
		$current = self::current_for_dealer( $sub->dealer_id );
		if ( $current && strtotime( $current->ends_at . ' UTC' ) > time() ) {
			$start = new \DateTime( $current->ends_at, $utc );
		}
		$end   = ( clone $start )->modify( '+' . (int) $sub->duration_months . ' months' );
		$grace = ( clone $end )->modify( '+' . max( 0, (int) Settings::get( 'grace_days' ) ) . ' days' );

		$wpdb->update(
			Database::table( 'subscriptions' ),
			array(
				'status'         => 'active',
				'starts_at'      => $start->format( 'Y-m-d H:i:s' ),
				'ends_at'        => $end->format( 'Y-m-d H:i:s' ),
				'grace_ends_at'  => $grace->format( 'Y-m-d H:i:s' ),
				'reminder_stage' => 0,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		$wpdb->update( Database::table( 'dealers' ), array( 'visibility_boost' => (int) $sub->visibility_boost ), array( 'id' => (int) $sub->dealer_id ) );
		Audit::log( 'subscription.activated', 'subscription', $id );
		do_action( 'amc_dealer_changed', (int) $sub->dealer_id );
		Memberships::maybe_activate_dealer( $sub->dealer_id );
		Notifications::subscription_active( $id );
		return true;
	}

	public static function extend( $id, $months ) {
		global $wpdb;
		$sub    = self::get( $id );
		$months = absint( $months );
		if ( ! $sub || 'active' !== $sub->status || $months < 1 ) {
			return new \WP_Error( 'amc_sub', __( 'Only an active subscription can be extended.', 'automotive-marketplace-core' ) );
		}
		$utc   = new \DateTimeZone( 'UTC' );
		$end   = ( new \DateTime( $sub->ends_at, $utc ) )->modify( '+' . $months . ' months' );
		$grace = ( clone $end )->modify( '+' . max( 0, (int) Settings::get( 'grace_days' ) ) . ' days' );
		$wpdb->update(
			Database::table( 'subscriptions' ),
			array(
				'ends_at'        => $end->format( 'Y-m-d H:i:s' ),
				'grace_ends_at'  => $grace->format( 'Y-m-d H:i:s' ),
				'reminder_stage' => 0,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		Audit::log( 'subscription.extended', 'subscription', $id, array( 'months' => $months ) );
		do_action( 'amc_dealer_changed', (int) $sub->dealer_id );
		return true;
	}

	public static function cancel( $id ) {
		global $wpdb;
		$sub = self::get( $id );
		if ( ! $sub || in_array( $sub->status, array( 'cancelled', 'expired' ), true ) ) {
			return new \WP_Error( 'amc_sub', __( 'This subscription is already closed.', 'automotive-marketplace-core' ) );
		}
		$wpdb->update( Database::table( 'subscriptions' ), array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
		Audit::log( 'subscription.cancelled', 'subscription', $id );
		do_action( 'amc_dealer_changed', (int) $sub->dealer_id );
		Memberships::maybe_activate_dealer( $sub->dealer_id );
		return true;
	}

	/** Daily: renewal reminders and expiry. */
	public static function process_daily() {
		global $wpdb;
		$table = Database::table( 'subscriptions' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'active' AND ends_at IS NOT NULL" );
		foreach ( (array) $rows as $sub ) {
			$current = self::current_for_dealer( $sub->dealer_id );
			$renewed = $current && (int) $current->id !== (int) $sub->id;

			if ( strtotime( $sub->grace_ends_at . ' UTC' ) < time() ) {
				$wpdb->update( $table, array( 'status' => 'expired', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $sub->id ) );
				Audit::log( 'subscription.expired', 'subscription', $sub->id );
				do_action( 'amc_dealer_changed', (int) $sub->dealer_id );
				Memberships::maybe_activate_dealer( $sub->dealer_id );
				if ( ! $renewed ) {
					Notifications::subscription_expired( $sub->id );
				}
				continue;
			}
			if ( $renewed ) {
				continue;
			}
			$days_left = ( strtotime( $sub->ends_at . ' UTC' ) - time() ) / DAY_IN_SECONDS;
			$stage     = (int) $sub->reminder_stage;
			if ( $days_left <= 1 && $stage < 2 ) {
				Notifications::subscription_reminder( $sub->id, 1 );
				$wpdb->update( $table, array( 'reminder_stage' => 2 ), array( 'id' => (int) $sub->id ) );
			} elseif ( $days_left <= 7 && $stage < 1 ) {
				Notifications::subscription_reminder( $sub->id, 7 );
				$wpdb->update( $table, array( 'reminder_stage' => 1 ), array( 'id' => (int) $sub->id ) );
			}
		}
	}

	/** Monthly recurring revenue (active subscriptions, monthly equivalent). */
	public static function mrr() {
		global $wpdb;
		$table = Database::table( 'subscriptions' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) round( (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount / duration_months), 0) FROM $table WHERE status = 'active'" ) );
	}

	public static function expiring_count( $days ) {
		global $wpdb;
		$table = Database::table( 'subscriptions' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = 'active' AND ends_at BETWEEN %s AND %s", gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) ) );
	}
}
