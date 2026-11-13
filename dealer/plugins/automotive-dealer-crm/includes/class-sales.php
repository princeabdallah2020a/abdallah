<?php
/**
 * Vehicle sales. Confirming a sale marks the vehicle as sold and the linked
 * lead as won; cancelling a confirmed sale puts the vehicle back on sale.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Sales {

	private static function select() {
		$s = Database::table( 'sales' );
		$v = Database::table( 'vehicles' );
		$c = Database::table( 'customers' );
		return "SELECT s.*, v.title AS vehicle_title, v.slug AS vehicle_slug, c.name AS customer_name, c.phone AS customer_phone FROM $s s LEFT JOIN $v v ON v.id = s.vehicle_id LEFT JOIN $c c ON c.id = s.customer_id";
	}

	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( self::select() . ' WHERE s.id = %d', absint( $id ) ) );
	}

	public static function search( array $args = array() ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 's.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$where[]  = 's.customer_id = %d';
			$params[] = absint( $args['customer_id'] );
		}
		if ( ! empty( $args['month'] ) && preg_match( '/^\d{4}-\d{2}$/', $args['month'] ) ) {
			$where[]  = 's.sold_on BETWEEN %s AND %s';
			$params[] = $args['month'] . '-01';
			$params[] = gmdate( 'Y-m-t', strtotime( $args['month'] . '-01' ) );
		}
		$params[] = isset( $args['limit'] ) ? absint( $args['limit'] ) : 300;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( self::select() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY s.sold_on DESC, s.id DESC LIMIT %d', $params ) );
		return $rows ? $rows : array();
	}

	/** @return int|\WP_Error Sale id. */
	public static function create( array $in ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_sales' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$vehicle = Vehicles::get( isset( $in['vehicle_id'] ) ? absint( $in['vehicle_id'] ) : 0 );
		if ( ! $vehicle || in_array( $vehicle->status, array( 'sold', 'archived' ), true ) ) {
			return new \WP_Error( 'adc_sale', __( 'Choose a vehicle that is still available.', 'automotive-dealer-crm' ) );
		}
		$customer_id = Customers::from_form( $in );
		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}
		$price = absint( preg_replace( '/\D/', '', isset( $in['sale_price'] ) ? (string) $in['sale_price'] : '' ) );
		if ( $price <= 0 ) {
			return new \WP_Error( 'adc_sale', __( 'Enter the sale price.', 'automotive-dealer-crm' ) );
		}
		$date   = isset( $in['sold_on'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $in['sold_on'] ) ? $in['sold_on'] : wp_date( 'Y-m-d' );
		$seller = isset( $in['salesperson_id'] ) && Team::is_member( absint( $in['salesperson_id'] ) ) ? absint( $in['salesperson_id'] ) : get_current_user_id();
		$lead   = isset( $in['lead_id'] ) ? Leads::get( absint( $in['lead_id'] ) ) : null;
		$now    = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'sales' ),
			array(
				'vehicle_id'     => $vehicle->id,
				'customer_id'    => $customer_id,
				'lead_id'        => $lead ? (int) $lead->id : 0,
				'salesperson_id' => $seller,
				'sale_price'     => $price,
				'currency'       => $vehicle->currency,
				'sold_on'        => $date,
				'payment_method' => isset( $in['payment_method'] ) && isset( Labels::payment_methods()[ $in['payment_method'] ] ) ? $in['payment_method'] : 'cash',
				'payment_status' => isset( $in['payment_status'] ) && isset( Labels::payment_statuses()[ $in['payment_status'] ] ) ? $in['payment_status'] : 'unpaid',
				'amount_paid'    => absint( preg_replace( '/\D/', '', isset( $in['amount_paid'] ) ? (string) $in['amount_paid'] : '' ) ),
				'status'         => 'draft',
				'notes'          => isset( $in['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $in['notes'] ), 0, 3000 ) : '',
				'created_by'     => get_current_user_id(),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new \WP_Error( 'adc_db', __( 'The sale could not be saved.', 'automotive-dealer-crm' ) );
		}
		Files::store( 'document', array( 'customer_id' => $customer_id, 'sale_id' => $id ), __( 'Sale document', 'automotive-dealer-crm' ) );
		Activity::log( 'sale_created', 'sale', $id, $vehicle->title );
		if ( ! empty( $in['confirm'] ) ) {
			$confirmed = self::confirm( $id );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		} elseif ( 'published' === $vehicle->status ) {
			Vehicles::system_status( $vehicle->id, 'reserved' );
		}
		return $id;
	}

	/** Confirm: vehicle sold, lead won. */
	public static function confirm( $id ) {
		global $wpdb;
		$sale = self::get( $id );
		if ( ! $sale || 'draft' !== $sale->status ) {
			return new \WP_Error( 'adc_sale', __( 'Only a draft sale can be confirmed.', 'automotive-dealer-crm' ) );
		}
		$check = Roles::require_cap( 'adc_manage_sales' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$vehicle = Vehicles::get( $sale->vehicle_id );
		if ( ! $vehicle || 'sold' === $vehicle->status ) {
			return new \WP_Error( 'adc_sale', __( 'This vehicle has already been sold.', 'automotive-dealer-crm' ) );
		}
		$wpdb->update( Database::table( 'sales' ), array( 'status' => 'confirmed', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $sale->id ) );
		Vehicles::system_status( $sale->vehicle_id, 'sold' );
		if ( $sale->lead_id ) {
			$wpdb->update( Database::table( 'leads' ), array( 'status' => 'won', 'customer_id' => $sale->customer_id, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $sale->lead_id ) );
			Leads::note( $sale->lead_id, 'system', __( 'Sale confirmed.', 'automotive-dealer-crm' ) );
		}
		Activity::log( 'sale_confirmed', 'sale', $sale->id, $sale->vehicle_title . ' — ' . adc_money( $sale->sale_price, $sale->currency ) );
		return true;
	}

	public static function cancel( $id ) {
		global $wpdb;
		$sale = self::get( $id );
		if ( ! $sale || 'cancelled' === $sale->status ) {
			return new \WP_Error( 'adc_sale', __( 'This sale is already cancelled.', 'automotive-dealer-crm' ) );
		}
		$check = Roles::require_cap( 'adc_manage_sales' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$wpdb->update( Database::table( 'sales' ), array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $sale->id ) );
		$vehicle = Vehicles::get( $sale->vehicle_id );
		if ( $vehicle && in_array( $vehicle->status, array( 'sold', 'reserved' ), true ) ) {
			Vehicles::system_status( $vehicle->id, 'published' );
		}
		Activity::log( 'sale_cancelled', 'sale', $sale->id, (string) $sale->vehicle_title );
		return true;
	}

	public static function update_payment( $id, $status, $amount_paid ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_sales' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$wpdb->update(
			Database::table( 'sales' ),
			array(
				'payment_status' => isset( Labels::payment_statuses()[ $status ] ) ? $status : 'unpaid',
				'amount_paid'    => absint( preg_replace( '/\D/', '', (string) $amount_paid ) ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) )
		);
		return true;
	}

	/** Confirmed sales per month: [ 'YYYY-MM' => [count, revenue] ] for the last N months. */
	public static function monthly( $months = 6 ) {
		global $wpdb;
		$table = Database::table( 'sales' );
		$since = wp_date( 'Y-m-01', strtotime( '-' . ( $months - 1 ) . ' months' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTR(sold_on, 1, 7) AS m, COUNT(*) AS n, SUM(sale_price) AS total FROM $table WHERE status = 'confirmed' AND sold_on >= %s GROUP BY SUBSTR(sold_on, 1, 7)", $since ) );
		$out  = array();
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$out[ wp_date( 'Y-m', strtotime( '-' . $i . ' months', strtotime( wp_date( 'Y-m-01' ) ) ) ) ] = array( 0, 0 );
		}
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row->m ] ) ) {
				$out[ $row->m ] = array( (int) $row->n, (int) $row->total );
			}
		}
		return $out;
	}
}
