<?php
/**
 * Leads and sales pipeline: New → Contacted → Interested → Appointment →
 * Negotiation → Won / Lost.
 *
 * Team members without "adc_view_all_leads" only see leads assigned to them
 * and unassigned leads.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Leads {

	/** SQL condition restricting leads to what the current user may see. */
	private static function visibility() {
		global $wpdb;
		if ( current_user_can( 'adc_view_all_leads' ) ) {
			return '1=1';
		}
		return $wpdb->prepare( '(l.assigned_to = %d OR l.assigned_to = 0)', get_current_user_id() );
	}

	public static function can_see( $lead ) {
		return $lead && ( current_user_can( 'adc_view_all_leads' ) || in_array( (int) $lead->assigned_to, array( 0, get_current_user_id() ), true ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$l = Database::table( 'leads' );
		$v = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT l.*, v.title AS vehicle_title FROM $l l LEFT JOIN $v v ON v.id = l.vehicle_id WHERE l.id = %d", absint( $id ) ) );
		return self::can_see( $lead ) ? $lead : null;
	}

	/**
	 * Visible leads.
	 *
	 * @param array $args { status, source, q, assigned, open_only, limit }.
	 */
	public static function search( array $args = array() ) {
		global $wpdb;
		$l      = Database::table( 'leads' );
		$v      = Database::table( 'vehicles' );
		$where  = array( self::visibility() );
		$params = array();
		if ( ! empty( $args['status'] ) && isset( Labels::lead_statuses()[ $args['status'] ] ) ) {
			$where[]  = 'l.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['open_only'] ) ) {
			$where[] = "l.status NOT IN ('won','lost')";
		}
		if ( ! empty( $args['source'] ) && isset( Labels::lead_sources()[ $args['source'] ] ) ) {
			$where[]  = 'l.source = %s';
			$params[] = $args['source'];
		}
		if ( ! empty( $args['mine'] ) ) {
			$where[]  = 'l.assigned_to = %d';
			$params[] = get_current_user_id();
		}
		if ( ! empty( $args['q'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['q'] ) . '%';
			$where[]  = '(l.name LIKE %s OR l.phone LIKE %s OR l.email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$params[] = isset( $args['limit'] ) ? absint( $args['limit'] ) : 300;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, v.title AS vehicle_title FROM $l l LEFT JOIN $v v ON v.id = l.vehicle_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY l.updated_at DESC LIMIT %d', $params ) );
		return $rows ? $rows : array();
	}

	/**
	 * Create or update a lead.
	 *
	 * @param array  $in     Raw input.
	 * @param int    $id     Lead id (0 = create).
	 * @param string $source Forced source for public forms ('' = from input).
	 * @return int|\WP_Error
	 */
	public static function save( array $in, $id = 0, $source = '' ) {
		global $wpdb;
		$text = static function ( $key, $max = 191 ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, $max ) : '';
		};
		$existing = $id ? self::get( $id ) : null;
		if ( $id && ! $existing ) {
			return new \WP_Error( 'adc_lead', __( 'Lead not found.', 'automotive-dealer-crm' ) );
		}
		$name  = $text( 'name', 150 );
		$phone = adc_normalize_phone( $text( 'phone', 32 ) );
		$email = sanitize_email( $text( 'email' ) );
		if ( '' === $name || ( '' === $phone && '' === $email ) ) {
			return new \WP_Error( 'adc_lead', __( 'A name and a phone number or email are required.', 'automotive-dealer-crm' ) );
		}
		$now  = current_time( 'mysql', true );
		$data = array(
			'name'          => $name,
			'phone'         => $phone,
			'email'         => $email,
			'vehicle_id'    => absint( $text( 'vehicle_id', 20 ) ),
			'budget_amount' => absint( preg_replace( '/\D/', '', $text( 'budget_amount', 20 ) ) ),
			'interest'      => 'rent' === $text( 'interest', 10 ) ? 'rent' : 'sale',
			'updated_at'    => $now,
		);
		if ( $source ) {
			$data['source']  = $source;
			$data['message'] = isset( $in['message'] ) ? mb_substr( sanitize_textarea_field( (string) $in['message'] ), 0, 3000 ) : '';
		} else {
			$data['source'] = isset( Labels::lead_sources()[ $text( 'source', 20 ) ] ) ? $text( 'source', 20 ) : 'manual';
			if ( isset( $in['message'] ) ) {
				$data['message'] = mb_substr( sanitize_textarea_field( (string) $in['message'] ), 0, 3000 );
			}
			if ( isset( $in['follow_up_at'] ) ) {
				$data['follow_up_at'] = adc_utc_from_local( $in['follow_up_at'] ) ? adc_utc_from_local( $in['follow_up_at'] ) : null;
			}
			if ( isset( $in['assigned_to'] ) && current_user_can( 'adc_view_all_leads' ) ) {
				$data['assigned_to'] = Team::is_member( absint( $in['assigned_to'] ) ) ? absint( $in['assigned_to'] ) : 0;
			}
		}
		$table = Database::table( 'leads' );
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			Activity::log( 'lead_updated', 'lead', $existing->id, $name );
			return (int) $existing->id;
		}
		$data['status']     = 'new';
		$data['created_by'] = get_current_user_id();
		$data['created_at'] = $now;
		if ( ! isset( $data['assigned_to'] ) ) {
			$data['assigned_to'] = $source ? 0 : ( current_user_can( 'adc_view_all_leads' ) ? 0 : get_current_user_id() );
		}
		$data['message'] = isset( $data['message'] ) ? $data['message'] : '';
		$wpdb->insert( $table, $data );
		$lead_id = (int) $wpdb->insert_id;
		self::note( $lead_id, 'system', sprintf( /* translators: %s: source */ __( 'Lead created (%s).', 'automotive-dealer-crm' ), Labels::label( 'lead_sources', $data['source'] ) ) );
		Activity::log( 'lead_created', 'lead', $lead_id, $name );
		do_action( 'adc_lead_created', $lead_id );
		return $lead_id;
	}

	public static function set_status( $id, $status, $reason = '' ) {
		global $wpdb;
		$lead = self::get( $id );
		if ( ! $lead || ! isset( Labels::lead_statuses()[ $status ] ) ) {
			return new \WP_Error( 'adc_lead', __( 'Lead not found.', 'automotive-dealer-crm' ) );
		}
		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( 'lost' === $status ) {
			$data['lost_reason'] = mb_substr( sanitize_text_field( $reason ), 0, 255 );
		}
		if ( ! $lead->assigned_to && 'new' !== $status ) {
			$data['assigned_to'] = get_current_user_id();
		}
		$wpdb->update( Database::table( 'leads' ), $data, array( 'id' => $lead->id ) );
		self::note( $lead->id, 'status', Labels::label( 'lead_statuses', $lead->status ) . ' → ' . Labels::label( 'lead_statuses', $status ) . ( 'lost' === $status && $reason ? ' (' . $reason . ')' : '' ) );
		Activity::log( 'lead_status', 'lead', $lead->id, $lead->name . ' → ' . Labels::label( 'lead_statuses', $status ) );
		return true;
	}

	public static function assign( $id, $user_id ) {
		global $wpdb;
		$lead = self::get( $id );
		if ( ! $lead || ! current_user_can( 'adc_view_all_leads' ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		$user_id = Team::is_member( $user_id ) ? absint( $user_id ) : 0;
		$wpdb->update( Database::table( 'leads' ), array( 'assigned_to' => $user_id, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $lead->id ) );
		$user = $user_id ? get_userdata( $user_id ) : null;
		self::note( $lead->id, 'system', $user ? sprintf( /* translators: %s: name */ __( 'Assigned to %s.', 'automotive-dealer-crm' ), $user->display_name ) : __( 'Unassigned.', 'automotive-dealer-crm' ) );
		return true;
	}

	public static function note( $lead_id, $kind, $body ) {
		global $wpdb;
		$body = mb_substr( sanitize_textarea_field( $body ), 0, 3000 );
		if ( '' === $body ) {
			return;
		}
		$wpdb->insert(
			Database::table( 'lead_notes' ),
			array(
				'lead_id'    => absint( $lead_id ),
				'user_id'    => get_current_user_id(),
				'kind'       => in_array( $kind, array( 'note', 'call', 'status', 'system' ), true ) ? $kind : 'note',
				'body'       => $body,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$wpdb->update( Database::table( 'leads' ), array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $lead_id ) ) );
	}

	public static function notes( $lead_id ) {
		global $wpdb;
		$table = Database::table( 'lead_notes' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE lead_id = %d ORDER BY id DESC", absint( $lead_id ) ) );
		return $rows ? $rows : array();
	}

	/** Create a customer from a lead (or return the linked one). */
	public static function convert( $id ) {
		global $wpdb;
		$lead = self::get( $id );
		if ( ! $lead || ! current_user_can( 'adc_manage_customers' ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		if ( $lead->customer_id && Customers::get( $lead->customer_id ) ) {
			return (int) $lead->customer_id;
		}
		$existing    = $lead->phone ? Customers::find_by_phone( $lead->phone ) : null;
		$customer_id = $existing ? (int) $existing->id : Customers::save(
			array(
				'name'  => $lead->name,
				'phone' => $lead->phone,
				'email' => $lead->email,
			)
		);
		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}
		$wpdb->update( Database::table( 'leads' ), array( 'customer_id' => $customer_id ), array( 'id' => $lead->id ) );
		self::note( $lead->id, 'system', __( 'Customer record created.', 'automotive-dealer-crm' ) );
		return $customer_id;
	}

	public static function counts_by_status() {
		$out = array_fill_keys( array_keys( Labels::lead_statuses() ), 0 );
		foreach ( self::search( array( 'limit' => 5000 ) ) as $lead ) {
			++$out[ $lead->status ];
		}
		return $out;
	}

	/** Follow-ups due today or overdue for the current user. */
	public static function due_followups() {
		global $wpdb;
		$l = Database::table( 'leads' );
		$v = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, v.title AS vehicle_title FROM $l l LEFT JOIN $v v ON v.id = l.vehicle_id WHERE " . self::visibility() . " AND l.status NOT IN ('won','lost') AND l.follow_up_at IS NOT NULL AND l.follow_up_at <= %s ORDER BY l.follow_up_at ASC LIMIT 20", gmdate( 'Y-m-d 23:59:59' ) ) );
		return $rows ? $rows : array();
	}
}
