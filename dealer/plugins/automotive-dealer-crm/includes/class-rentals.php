<?php
/**
 * Rentals: quote → confirmed → vehicle out → returned (or cancelled).
 *
 * Two confirmed/active rentals of the same vehicle can never overlap: the
 * check runs inside a transaction that locks the vehicle row.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Rentals {

	const BLOCKING = array( 'confirmed', 'active' );

	private static function select() {
		$r = Database::table( 'rentals' );
		$v = Database::table( 'vehicles' );
		$c = Database::table( 'customers' );
		return "SELECT r.*, v.title AS vehicle_title, c.name AS customer_name, c.phone AS customer_phone FROM $r r LEFT JOIN $v v ON v.id = r.vehicle_id LEFT JOIN $c c ON c.id = r.customer_id";
	}

	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( self::select() . ' WHERE r.id = %d', absint( $id ) ) );
	}

	/**
	 * @param array $args { view: upcoming|active|past|all, customer_id, vehicle_id }.
	 */
	public static function search( array $args = array() ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		$now    = gmdate( 'Y-m-d H:i:s' );
		$view   = isset( $args['view'] ) ? $args['view'] : 'all';
		if ( 'upcoming' === $view ) {
			$where[] = "r.status IN ('quote','confirmed')";
		} elseif ( 'active' === $view ) {
			$where[] = "r.status = 'active'";
		} elseif ( 'past' === $view ) {
			$where[] = "r.status IN ('returned','cancelled')";
		}
		foreach ( array( 'customer_id', 'vehicle_id' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$where[]  = "r.$key = %d";
				$params[] = absint( $args[ $key ] );
			}
		}
		$order    = 'past' === $view ? 'r.starts_at DESC' : 'r.starts_at ASC';
		$params[] = 300;
		unset( $now );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( self::select() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY $order LIMIT %d", $params ) );
		return $rows ? $rows : array();
	}

	/** Units billed: days (24 h, started day counts) or hours. */
	public static function units( $starts_utc, $ends_utc, $unit ) {
		$seconds = max( 0, strtotime( $ends_utc . ' UTC' ) - strtotime( $starts_utc . ' UTC' ) );
		return max( 1, (int) ceil( $seconds / ( 'hour' === $unit ? HOUR_IN_SECONDS : DAY_IN_SECONDS ) ) );
	}

	/** Blocking rentals of a vehicle overlapping a period. */
	public static function conflicts( $vehicle_id, $starts_utc, $ends_utc, $exclude_id = 0 ) {
		global $wpdb;
		$r = Database::table( 'rentals' );
		$c = Database::table( 'customers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT r.*, c.name AS customer_name FROM $r r LEFT JOIN $c c ON c.id = r.customer_id WHERE r.vehicle_id = %d AND r.id <> %d AND r.status IN ('confirmed','active') AND r.starts_at < %s AND r.ends_at > %s", absint( $vehicle_id ), absint( $exclude_id ), $ends_utc, $starts_utc ) );
	}

	/** @return int|\WP_Error Rental id. */
	public static function create( array $in ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_rentals' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$vehicle = Vehicles::get( isset( $in['vehicle_id'] ) ? absint( $in['vehicle_id'] ) : 0 );
		if ( ! $vehicle || in_array( $vehicle->status, array( 'sold', 'archived' ), true ) ) {
			return new \WP_Error( 'adc_rental', __( 'Choose a vehicle available for rental.', 'automotive-dealer-crm' ) );
		}
		$starts = adc_utc_from_local( isset( $in['starts_at'] ) ? $in['starts_at'] : '' );
		$ends   = adc_utc_from_local( isset( $in['ends_at'] ) ? $in['ends_at'] : '' );
		if ( ! $starts || ! $ends || strtotime( $ends ) <= strtotime( $starts ) ) {
			return new \WP_Error( 'adc_rental', __( 'The return date must be after the start date.', 'automotive-dealer-crm' ) );
		}
		$customer_id = Customers::from_form( $in );
		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}
		$unit  = isset( $in['rate_unit'] ) && 'hour' === $in['rate_unit'] ? 'hour' : 'day';
		$rate  = absint( preg_replace( '/\D/', '', isset( $in['rate_amount'] ) ? (string) $in['rate_amount'] : '' ) );
		$rate  = $rate ? $rate : ( 'rent' === $vehicle->listing_type ? $vehicle->price_amount : 0 );
		$units = self::units( $starts, $ends, $unit );
		$now   = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'rentals' ),
			array(
				'vehicle_id'     => $vehicle->id,
				'customer_id'    => $customer_id,
				'agent_id'       => get_current_user_id(),
				'starts_at'      => $starts,
				'ends_at'        => $ends,
				'rate_unit'      => $unit,
				'rate_amount'    => $rate,
				'units'          => $units,
				'total_amount'   => $rate * $units,
				'deposit_amount' => absint( preg_replace( '/\D/', '', isset( $in['deposit_amount'] ) ? (string) $in['deposit_amount'] : '' ) ),
				'currency'       => $vehicle->currency,
				'payment_status' => isset( $in['payment_status'] ) && isset( Labels::payment_statuses()[ $in['payment_status'] ] ) ? $in['payment_status'] : 'unpaid',
				'status'         => 'quote',
				'with_driver'    => empty( $in['with_driver'] ) ? 0 : 1,
				'return_notes'   => '',
				'notes'          => isset( $in['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $in['notes'] ), 0, 3000 ) : '',
				'created_by'     => get_current_user_id(),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new \WP_Error( 'adc_db', __( 'The rental could not be saved.', 'automotive-dealer-crm' ) );
		}
		Files::store( 'document', array( 'customer_id' => $customer_id, 'rental_id' => $id ), __( 'Rental document', 'automotive-dealer-crm' ) );
		Activity::log( 'rental_created', 'rental', $id, $vehicle->title );
		if ( ! empty( $in['confirm'] ) ) {
			$confirmed = self::confirm( $id );
			if ( is_wp_error( $confirmed ) ) {
				return new \WP_Error( 'adc_rental_saved', $confirmed->get_error_message() . ' ' . __( 'The rental was saved as a quote.', 'automotive-dealer-crm' ), array( 'id' => $id ) );
			}
		}
		return $id;
	}

	/** Confirm a quote, refusing any overlap with another confirmed rental. */
	public static function confirm( $id ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_rentals' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$rental = self::get( $id );
		if ( ! $rental || 'quote' !== $rental->status ) {
			return new \WP_Error( 'adc_rental', __( 'Only a quote can be confirmed.', 'automotive-dealer-crm' ) );
		}
		$v = Database::table( 'vehicles' );
		$wpdb->query( 'START TRANSACTION' );
		// Lock the vehicle row so two agents cannot confirm overlapping rentals at the same time.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->get_var( $wpdb->prepare( "SELECT id FROM $v WHERE id = %d FOR UPDATE", (int) $rental->vehicle_id ) );
		$conflicts = self::conflicts( $rental->vehicle_id, $rental->starts_at, $rental->ends_at, $rental->id );
		if ( $conflicts ) {
			$wpdb->query( 'ROLLBACK' );
			$first = $conflicts[0];
			/* translators: 1: customer, 2: start, 3: end */
			return new \WP_Error( 'adc_overlap', sprintf( __( 'This vehicle is already booked by %1$s from %2$s to %3$s.', 'automotive-dealer-crm' ), $first->customer_name, adc_date( $first->starts_at, true ), adc_date( $first->ends_at, true ) ) );
		}
		$wpdb->update( Database::table( 'rentals' ), array( 'status' => 'confirmed', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $rental->id ) );
		$wpdb->query( 'COMMIT' );
		Activity::log( 'rental_confirmed', 'rental', $rental->id, $rental->vehicle_title . ' — ' . $rental->customer_name );
		return true;
	}

	/** Vehicle handed over to the customer. */
	public static function start( $id, $mileage = 0 ) {
		global $wpdb;
		$rental = self::get( $id );
		if ( ! $rental || 'confirmed' !== $rental->status ) {
			return new \WP_Error( 'adc_rental', __( 'Only a confirmed rental can start.', 'automotive-dealer-crm' ) );
		}
		$check = Roles::require_cap( 'adc_manage_rentals' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$wpdb->update( Database::table( 'rentals' ), array( 'status' => 'active', 'pickup_mileage' => absint( $mileage ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $rental->id ) );
		Vehicles::system_status( $rental->vehicle_id, 'rented' );
		Activity::log( 'rental_started', 'rental', $rental->id, (string) $rental->vehicle_title );
		return true;
	}

	/** Vehicle returned. */
	public static function finish( $id, $mileage, $notes ) {
		global $wpdb;
		$rental = self::get( $id );
		if ( ! $rental || 'active' !== $rental->status ) {
			return new \WP_Error( 'adc_rental', __( 'Only a rental in progress can be closed.', 'automotive-dealer-crm' ) );
		}
		$check = Roles::require_cap( 'adc_manage_rentals' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$mileage = absint( $mileage );
		$wpdb->update(
			Database::table( 'rentals' ),
			array(
				'status'         => 'returned',
				'return_mileage' => $mileage,
				'return_notes'   => mb_substr( sanitize_textarea_field( (string) $notes ), 0, 3000 ),
				'returned_at'    => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $rental->id )
		);
		$vehicle = Vehicles::get( $rental->vehicle_id );
		if ( $vehicle && 'rented' === $vehicle->status ) {
			Vehicles::system_status( $vehicle->id, 'published' );
		}
		if ( $vehicle && $mileage > $vehicle->mileage_km ) {
			$wpdb->update( Database::table( 'vehicles' ), array( 'mileage_km' => $mileage ), array( 'id' => $vehicle->id ) );
		}
		Activity::log( 'rental_returned', 'rental', $rental->id, (string) $rental->vehicle_title );
		return true;
	}

	public static function cancel( $id ) {
		global $wpdb;
		$rental = self::get( $id );
		if ( ! $rental || in_array( $rental->status, array( 'returned', 'cancelled' ), true ) ) {
			return new \WP_Error( 'adc_rental', __( 'This rental is already closed.', 'automotive-dealer-crm' ) );
		}
		$check = Roles::require_cap( 'adc_manage_rentals' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$wpdb->update( Database::table( 'rentals' ), array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $rental->id ) );
		if ( 'active' === $rental->status ) {
			Vehicles::system_status( $rental->vehicle_id, 'published' );
		}
		Activity::log( 'rental_cancelled', 'rental', $rental->id, (string) $rental->vehicle_title );
		return true;
	}

	public static function update_payment( $id, $status ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_rentals' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$wpdb->update( Database::table( 'rentals' ), array( 'payment_status' => isset( Labels::payment_statuses()[ $status ] ) ? $status : 'unpaid', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $id ) ) );
		return true;
	}

	/**
	 * Availability grid: vehicle id => [ 'Y-m-d' => rental|null ] over N days.
	 *
	 * @return array { days: string[], vehicles: object[], cells: array }
	 */
	public static function planner( $days = 14 ) {
		global $wpdb;
		$start    = strtotime( wp_date( 'Y-m-d 00:00:00' ) . ' ' . wp_timezone_string() );
		$vehicles = Vehicles::search( array( 'type' => 'rent', 'statuses' => array( 'published', 'reserved', 'rented' ) ) );
		$r        = Database::table( 'rentals' );
		$c        = Database::table( 'customers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rentals = $wpdb->get_results( $wpdb->prepare( "SELECT r.*, c.name AS customer_name FROM $r r LEFT JOIN $c c ON c.id = r.customer_id WHERE r.status IN ('quote','confirmed','active') AND r.ends_at > %s AND r.starts_at < %s", gmdate( 'Y-m-d H:i:s', $start ), gmdate( 'Y-m-d H:i:s', $start + $days * DAY_IN_SECONDS ) ) );
		$out     = array(
			'days'     => array(),
			'vehicles' => $vehicles,
			'cells'    => array(),
		);
		for ( $i = 0; $i < $days; $i++ ) {
			$out['days'][] = $start + $i * DAY_IN_SECONDS;
		}
		foreach ( (array) $rentals as $rental ) {
			$from = strtotime( $rental->starts_at . ' UTC' );
			$to   = strtotime( $rental->ends_at . ' UTC' );
			foreach ( $out['days'] as $i => $day ) {
				if ( $from < $day + DAY_IN_SECONDS && $to > $day ) {
					$current = isset( $out['cells'][ $rental->vehicle_id ][ $i ] ) ? $out['cells'][ $rental->vehicle_id ][ $i ] : null;
					if ( ! $current || 'quote' === $current->status ) {
						$out['cells'][ $rental->vehicle_id ][ $i ] = $rental;
					}
				}
			}
		}
		return $out;
	}

	/** Revenue of rentals started this month (returned or in progress). */
	public static function revenue_this_month() {
		global $wpdb;
		$table = Database::table( 'rentals' );
		$from  = get_gmt_from_date( wp_date( 'Y-m-01 00:00:00' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_amount), 0) FROM $table WHERE status IN ('confirmed','active','returned') AND starts_at >= %s", $from ) );
	}
}
