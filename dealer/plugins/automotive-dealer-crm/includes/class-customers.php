<?php
/**
 * Customers (buyers and renters).
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Customers {

	public static function get( $id ) {
		global $wpdb;
		$table = Database::table( 'customers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
	}

	public static function find_by_phone( $phone ) {
		global $wpdb;
		$table = Database::table( 'customers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s LIMIT 1", adc_normalize_phone( $phone ) ) );
	}

	public static function search( $q = '', $limit = 300 ) {
		global $wpdb;
		$table = Database::table( 'customers' );
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s ORDER BY name ASC LIMIT %d", $like, $like, $like, absint( $limit ) ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d", absint( $limit ) ) );
		}
		return $rows ? $rows : array();
	}

	/** @return int|\WP_Error */
	public static function save( array $in, $id = 0 ) {
		global $wpdb;
		if ( ! current_user_can( 'adc_manage_customers' ) && ! current_user_can( 'adc_manage_sales' ) && ! current_user_can( 'adc_manage_rentals' ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		$text = static function ( $key, $max = 191 ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, $max ) : '';
		};
		$name = $text( 'name', 150 );
		if ( '' === $name ) {
			return new \WP_Error( 'adc_customer', __( 'The customer name is required.', 'automotive-dealer-crm' ) );
		}
		$now  = current_time( 'mysql', true );
		$data = array(
			'type'       => 'company' === $text( 'type', 20 ) ? 'company' : 'individual',
			'name'       => $name,
			'phone'      => adc_normalize_phone( $text( 'phone', 32 ) ),
			'email'      => sanitize_email( $text( 'email' ) ),
			'address'    => $text( 'address', 255 ),
			'city'       => $text( 'city', 100 ),
			'id_number'  => $text( 'id_number', 80 ),
			'notes'      => isset( $in['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $in['notes'] ), 0, 5000 ) : '',
			'updated_at' => $now,
		);
		$table = Database::table( 'customers' );
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
			Activity::log( 'customer_updated', 'customer', $id, $name );
			return absint( $id );
		}
		$data['created_by'] = get_current_user_id();
		$data['created_at'] = $now;
		$wpdb->insert( $table, $data );
		$id = (int) $wpdb->insert_id;
		Activity::log( 'customer_created', 'customer', $id, $name );
		return $id;
	}

	/**
	 * Customer chosen in a sale/rental form: existing id or quick creation.
	 *
	 * @return int|\WP_Error
	 */
	public static function from_form( array $in ) {
		$id = isset( $in['customer_id'] ) ? absint( $in['customer_id'] ) : 0;
		if ( $id && self::get( $id ) ) {
			return $id;
		}
		$quick = array(
			'name'      => isset( $in['new_customer_name'] ) ? $in['new_customer_name'] : '',
			'phone'     => isset( $in['new_customer_phone'] ) ? $in['new_customer_phone'] : '',
			'id_number' => isset( $in['new_customer_id_number'] ) ? $in['new_customer_id_number'] : '',
		);
		if ( '' === trim( (string) $quick['name'] ) ) {
			return new \WP_Error( 'adc_customer', __( 'Choose a customer or enter the name of a new one.', 'automotive-dealer-crm' ) );
		}
		$existing = $quick['phone'] ? self::find_by_phone( $quick['phone'] ) : null;
		return $existing ? (int) $existing->id : self::save( $quick );
	}
}
