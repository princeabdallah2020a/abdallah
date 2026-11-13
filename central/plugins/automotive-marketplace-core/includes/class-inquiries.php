<?php
/**
 * Contact requests sent to the platform (contact page, "become a dealer" page).
 *
 * Protection: nonce (CSRF), honeypot field, minimum fill time and per-IP
 * rate limit. Messages are stored in the database first, then emailed, so a
 * mail failure never loses a request.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Inquiries {

	const ACTION = 'amc_submit_inquiry';

	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function types() {
		return array( 'contact', 'dealer_interest' );
	}

	/**
	 * Print the form.
	 *
	 * @param string $type contact|dealer_interest.
	 */
	public static function render_form( $type = 'contact' ) {
		$type = in_array( $type, self::types(), true ) ? $type : 'contact';
		amc_get_template( 'parts/inquiry-form', array( 'type' => $type ) );
	}

	/** Result of the last submission for this visitor: sent|invalid|rate|error or ''. */
	public static function result() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		$result = isset( $_GET['amc_inquiry'] ) ? sanitize_key( wp_unslash( $_GET['amc_inquiry'] ) ) : '';
		return in_array( $result, array( 'sent', 'invalid', 'rate', 'error' ), true ) ? $result : '';
	}

	public static function handle() {
		$back = wp_get_referer();
		$back = $back ? remove_query_arg( 'amc_inquiry', $back ) : home_url( '/' );

		if ( ! isset( $_POST['_amc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_amc_nonce'] ) ), self::ACTION ) ) {
			self::redirect( $back, 'error' );
		}

		// Bots fill the hidden "website" field or submit instantly: pretend success.
		$elapsed = time() - ( isset( $_POST['amc_ts'] ) ? absint( $_POST['amc_ts'] ) : 0 );
		if ( ! empty( $_POST['website'] ) || $elapsed < 3 ) {
			self::redirect( $back, 'sent' );
		}

		if ( ! Rate_Limiter::allow( 'inquiry', 5, HOUR_IN_SECONDS ) ) {
			self::redirect( $back, 'rate' );
		}

		$field = static function ( $key, $max ) {
			return isset( $_POST[ $key ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ), 0, $max ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		};

		$data = array(
			'type'    => in_array( $field( 'type', 20 ), self::types(), true ) ? $field( 'type', 20 ) : 'contact',
			'name'    => $field( 'name', 120 ),
			'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'   => amc_normalize_phone( $field( 'phone', 32 ) ),
			'company' => $field( 'company', 191 ),
			'subject' => $field( 'subject', 191 ),
			'message' => isset( $_POST['message'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ), 0, 5000 ) : '',
		);

		// A name, a message and at least one way to answer are required.
		if ( '' === $data['name'] || '' === $data['message'] || ( '' === $data['email'] && '' === $data['phone'] ) ) {
			self::redirect( $back, 'invalid' );
		}
		if ( ! empty( $_POST['email'] ) && '' === $data['email'] ) {
			self::redirect( $back, 'invalid' );
		}

		$id = self::create( $data );
		if ( ! $id ) {
			self::redirect( $back, 'error' );
		}

		self::notify( $id, $data );
		self::redirect( $back, 'sent' );
	}

	/**
	 * Store a request.
	 *
	 * @param array $data Sanitised fields.
	 * @return int Row id, 0 on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$ok = $wpdb->insert(
			Database::table( 'inquiries' ),
			array_merge(
				$data,
				array(
					'ip_hash'    => Rate_Limiter::ip_hash(),
					'status'     => 'new',
					'created_at' => current_time( 'mysql', true ),
				)
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	private static function notify( $id, array $data ) {
		$labels = array(
			'contact'         => __( 'Contact request', 'automotive-marketplace-core' ),
			'dealer_interest' => __( 'Dealer membership request', 'automotive-marketplace-core' ),
		);
		$body   = implode(
			"\n",
			array(
				$labels[ $data['type'] ] . ' #' . $id,
				'',
				__( 'Name', 'automotive-marketplace-core' ) . ': ' . $data['name'],
				__( 'Company', 'automotive-marketplace-core' ) . ': ' . $data['company'],
				__( 'Email', 'automotive-marketplace-core' ) . ': ' . $data['email'],
				__( 'Phone', 'automotive-marketplace-core' ) . ': ' . $data['phone'],
				__( 'Subject', 'automotive-marketplace-core' ) . ': ' . $data['subject'],
				'',
				$data['message'],
			)
		);
		$headers = array();
		if ( $data['email'] ) {
			$headers[] = 'Reply-To: ' . $data['email'];
		}
		wp_mail( Settings::contact_email(), '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $labels[ $data['type'] ], $body, $headers );
	}

	private static function redirect( $url, $result ) {
		wp_safe_redirect( add_query_arg( 'amc_inquiry', $result, $url ) . '#amc-inquiry' );
		exit;
	}

	/**
	 * Latest requests for the admin screen.
	 *
	 * @param int $limit Max rows.
	 */
	public static function latest( $limit = 100 ) {
		global $wpdb;
		$table = Database::table( 'inquiries' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", absint( $limit ) ) );
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'new', 'handled' ), true ) ) {
			return false;
		}
		return (bool) $wpdb->update( Database::table( 'inquiries' ), array( 'status' => $status ), array( 'id' => absint( $id ) ) );
	}
}
