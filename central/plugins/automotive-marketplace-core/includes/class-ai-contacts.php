<?php
/**
 * WhatsApp numbers of dealer team members (sent by each dealer connector).
 * A number maps to one dealer and one CRM user; the dealer website decides
 * what that user may do.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Ai_Contacts {

	public static function normalize( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );
		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}
		if ( '' !== $phone && '+' !== $phone[0] ) {
			$phone = '+' . $phone; // WhatsApp sends numbers without "+".
		}
		return preg_match( '/^\+[1-9][0-9]{7,14}$/', $phone ) ? $phone : '';
	}

	/**
	 * Replace the list of a dealer. A number already registered by ANOTHER
	 * dealer is skipped (first come, first served; staff can resolve conflicts).
	 *
	 * @return int Saved numbers.
	 */
	public static function replace_for_dealer( $dealer_id, array $contacts ) {
		global $wpdb;
		$table = Database::table( 'ai_contacts' );
		$keep  = array();
		$now   = current_time( 'mysql', true );
		foreach ( array_slice( $contacts, 0, 50 ) as $contact ) {
			if ( ! is_array( $contact ) ) {
				continue;
			}
			$phone = self::normalize( $contact['phone'] ?? '' );
			if ( '' === $phone ) {
				continue;
			}
			$existing = self::find( $phone );
			if ( $existing && (int) $existing->dealer_id !== (int) $dealer_id ) {
				continue;
			}
			$data = array(
				'dealer_id'  => absint( $dealer_id ),
				'phone'      => $phone,
				'user_ref'   => absint( $contact['user_ref'] ?? 0 ),
				'name'       => mb_substr( sanitize_text_field( (string) ( $contact['name'] ?? '' ) ), 0, 150 ),
				'role'       => mb_substr( sanitize_text_field( (string) ( $contact['role'] ?? '' ) ), 0, 60 ),
				'updated_at' => $now,
			);
			if ( $existing ) {
				$wpdb->update( $table, $data, array( 'id' => (int) $existing->id ) );
			} else {
				$wpdb->insert( $table, $data );
			}
			$keep[] = $phone;
		}
		// Remove numbers the dealer no longer lists.
		$current = (array) $wpdb->get_col( $wpdb->prepare( "SELECT phone FROM $table WHERE dealer_id = %d", absint( $dealer_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( array_diff( $current, $keep ) as $phone ) {
			$wpdb->delete( $table, array( 'phone' => $phone ) );
		}
		return count( $keep );
	}

	public static function find( $phone ) {
		global $wpdb;
		$table = Database::table( 'ai_contacts' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s", self::normalize( $phone ) ) );
	}

	public static function all() {
		global $wpdb;
		$c = Database::table( 'ai_contacts' );
		$d = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT c.*, d.business_name FROM $c c LEFT JOIN $d d ON d.id = c.dealer_id ORDER BY d.business_name ASC, c.name ASC" );
		return $rows ? $rows : array();
	}
}
