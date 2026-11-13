<?php
/**
 * Subscription payments.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Payments {

	/** @return Payment_Provider[] Available providers keyed by id. */
	public static function providers() {
		$all = apply_filters(
			'amc_payment_providers',
			array(
				'wave'          => new Manual_Payment_Provider( 'wave', 'Wave', 'pay_wave_details' ),
				'orange_money'  => new Manual_Payment_Provider( 'orange_money', 'Orange Money', 'pay_om_details' ),
				'bank_transfer' => new Manual_Payment_Provider( 'bank_transfer', __( 'Bank transfer', 'automotive-marketplace-core' ), 'pay_bank_details' ),
				'cash'          => new Manual_Payment_Provider( 'cash', __( 'Cash at our office', 'automotive-marketplace-core' ), 'pay_cash_details' ),
			)
		);
		$available = array_filter(
			$all,
			static function ( $provider ) {
				return $provider instanceof Payment_Provider && $provider->is_available();
			}
		);
		// Nothing configured yet: offer bank transfer, details sent by the team.
		return $available ? $available : array_intersect_key( $all, array( 'bank_transfer' => 1 ) );
	}

	public static function method_label( $method ) {
		$labels = array(
			'wave'          => 'Wave',
			'orange_money'  => 'Orange Money',
			'bank_transfer' => __( 'Bank transfer', 'automotive-marketplace-core' ),
			'cash'          => __( 'Cash at our office', 'automotive-marketplace-core' ),
			'manual'        => __( 'Recorded by staff', 'automotive-marketplace-core' ),
		);
		return isset( $labels[ $method ] ) ? $labels[ $method ] : $method;
	}

	/** Reference the dealer writes on the transfer, e.g. "AB000034". */
	public static function reference_for( $subscription_id ) {
		return 'AB' . str_pad( (string) absint( $subscription_id ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Record a payment declared by a dealer (or by staff).
	 *
	 * @return int Payment id.
	 */
	public static function create( $dealer_id, $subscription_id, $method, $payer_reference = '', $proof_document_id = 0, $status = 'pending' ) {
		global $wpdb;
		$sub = Subscriptions::get( $subscription_id );
		if ( ! $sub || (int) $sub->dealer_id !== (int) $dealer_id ) {
			return 0;
		}
		$wpdb->insert(
			Database::table( 'payments' ),
			array(
				'dealer_id'         => absint( $dealer_id ),
				'subscription_id'   => absint( $subscription_id ),
				'method'            => sanitize_key( $method ),
				'amount'            => (int) $sub->amount,
				'currency'          => $sub->currency,
				'reference'         => self::reference_for( $subscription_id ),
				'payer_reference'   => mb_substr( sanitize_text_field( $payer_reference ), 0, 120 ),
				'proof_document_id' => absint( $proof_document_id ),
				'status'            => 'verified' === $status ? 'verified' : 'pending',
				'verified_by'       => 'verified' === $status ? get_current_user_id() : 0,
				'verified_at'       => 'verified' === $status ? current_time( 'mysql', true ) : null,
				'created_at'        => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $id ) {
			Audit::log( 'payment.created', 'payment', $id, array( 'method' => $method ) );
			if ( 'pending' === $status ) {
				Notifications::payment_submitted( $id );
			}
		}
		return $id;
	}

	public static function get( $id ) {
		global $wpdb;
		$p = Database::table( 'payments' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT p.*, d.business_name FROM $p p LEFT JOIN $d d ON d.id = p.dealer_id WHERE p.id = %d", absint( $id ) ) );
	}

	public static function for_dealer( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'payments' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE dealer_id = %d ORDER BY id DESC", absint( $dealer_id ) ) );
		return $rows ? $rows : array();
	}

	public static function pending_for_subscription( $subscription_id ) {
		global $wpdb;
		$table = Database::table( 'payments' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE subscription_id = %d AND status = 'pending' ORDER BY id DESC LIMIT 1", absint( $subscription_id ) ) );
	}

	/**
	 * Staff list.
	 *
	 * @param string $status pending|verified|rejected or '' for all.
	 */
	public static function list( $status = '', $limit = 100 ) {
		global $wpdb;
		$p      = Database::table( 'payments' );
		$d      = Database::table( 'dealers' );
		$where  = $status ? $wpdb->prepare( 'WHERE p.status = %s', $status ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.*, d.business_name FROM $p p LEFT JOIN $d d ON d.id = p.dealer_id $where ORDER BY p.id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	public static function pending_count() {
		global $wpdb;
		$table = Database::table( 'payments' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
	}

	/**
	 * Confirm a payment and start (or queue) the subscription it pays.
	 *
	 * @return true|\WP_Error
	 */
	public static function verify( $id ) {
		global $wpdb;
		$payment = self::get( $id );
		if ( ! $payment || 'pending' !== $payment->status ) {
			return new \WP_Error( 'amc_payment', __( 'This payment has already been processed.', 'automotive-marketplace-core' ) );
		}
		$wpdb->update(
			Database::table( 'payments' ),
			array(
				'status'      => 'verified',
				'verified_by' => get_current_user_id(),
				'verified_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		Audit::log( 'payment.verified', 'payment', $id );
		Notifications::payment_decision( $id, true );
		Subscriptions::on_paid( (int) $payment->subscription_id );
		return true;
	}

	public static function reject( $id, $reason ) {
		global $wpdb;
		$payment = self::get( $id );
		if ( ! $payment || 'pending' !== $payment->status ) {
			return new \WP_Error( 'amc_payment', __( 'This payment has already been processed.', 'automotive-marketplace-core' ) );
		}
		$wpdb->update(
			Database::table( 'payments' ),
			array(
				'status'      => 'rejected',
				'note'        => mb_substr( sanitize_text_field( $reason ), 0, 500 ),
				'verified_by' => get_current_user_id(),
				'verified_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		Audit::log( 'payment.rejected', 'payment', $id );
		Notifications::payment_decision( $id, false, $reason );
		return true;
	}
}
