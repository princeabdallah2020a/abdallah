<?php
/**
 * Per-dealer API credentials (one active credential per dealer).
 *
 * The secret is shown once to staff inside a "connection code" pasted into
 * the dealer's Marketplace Connector, then stored encrypted.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Credentials {

	public static function active_for_dealer( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'api_credentials' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE dealer_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", absint( $dealer_id ) ) );
	}

	public static function find_by_key( $key_id ) {
		global $wpdb;
		$table = Database::table( 'api_credentials' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE key_id = %s", sanitize_text_field( (string) $key_id ) ) );
	}

	public static function secret( $credential ) {
		return Crypto::decrypt( $credential->secret_enc );
	}

	/**
	 * Issue new credentials, revoking the previous ones (rotation).
	 *
	 * @return array { key_id, secret, code }
	 */
	public static function issue( $dealer_id ) {
		global $wpdb;
		$old = self::active_for_dealer( $dealer_id );
		if ( $old ) {
			self::revoke( $old->id );
		}
		$key_id = 'ab_' . bin2hex( random_bytes( 12 ) );
		$secret = bin2hex( random_bytes( 32 ) );
		$wpdb->insert(
			Database::table( 'api_credentials' ),
			array(
				'dealer_id'  => absint( $dealer_id ),
				'key_id'     => $key_id,
				'secret_enc' => Crypto::encrypt( $secret ),
				'status'     => 'active',
				'site_url'   => $old ? $old->site_url : '',
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		Audit::log( 'credentials.issued', 'dealer', $dealer_id, array( 'key' => $key_id ) );
		return array(
			'key_id' => $key_id,
			'secret' => $secret,
			'code'   => self::connection_code( $key_id, $secret ),
		);
	}

	/** Code pasted in the dealer connector: base64url( JSON ). */
	public static function connection_code( $key_id, $secret ) {
		$json = wp_json_encode(
			array(
				'v'      => 1,
				'url'    => untrailingslashit( home_url( '/' ) ),
				'key'    => $key_id,
				'secret' => $secret,
			)
		);
		return 'AB1.' . rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	}

	public static function revoke( $id ) {
		global $wpdb;
		$wpdb->update( Database::table( 'api_credentials' ), array( 'status' => 'revoked', 'revoked_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $id ) ) );
		Audit::log( 'credentials.revoked', 'api_credential', $id );
	}

	public static function touch( $id, array $fields ) {
		global $wpdb;
		$wpdb->update( Database::table( 'api_credentials' ), $fields, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Health of a dealer connection.
	 *
	 * @return string none|ok|stale|error
	 */
	public static function health( $credential ) {
		if ( ! $credential ) {
			return 'none';
		}
		if ( ! $credential->last_success_at ) {
			return $credential->last_error ? 'error' : 'stale';
		}
		if ( $credential->failures >= 3 ) {
			return 'error';
		}
		$age = time() - strtotime( $credential->last_success_at . ' UTC' );
		return $age > 3 * HOUR_IN_SECONDS ? 'stale' : 'ok';
	}

	/** All dealers with their current credential (staff screen). */
	public static function overview() {
		global $wpdb;
		$d = Database::table( 'dealers' );
		$c = Database::table( 'api_credentials' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT d.id AS dealer_id, d.business_name, d.status AS dealer_status, c.* FROM $d d LEFT JOIN $c c ON c.dealer_id = d.id AND c.status = 'active' WHERE d.is_demo = 0 ORDER BY d.business_name ASC" );
		return $rows ? $rows : array();
	}

	public static function problem_count() {
		$count = 0;
		foreach ( self::overview() as $row ) {
			if ( $row->key_id && in_array( self::health( $row ), array( 'error', 'stale' ), true ) && 'active' === $row->dealer_status ) {
				++$count;
			}
		}
		return $count;
	}
}
