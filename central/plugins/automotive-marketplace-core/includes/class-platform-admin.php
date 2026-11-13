<?php
/**
 * Actions of platform staff in /platform-admin/. Capability and nonce are
 * checked by the portal before any handler runs; every action is audited.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Platform_Admin {

	/**
	 * @param string $action Action key.
	 * @return array|\WP_Error
	 */
	public static function handle( $action ) {
		if ( ! Permissions::is_staff() ) {
			return new \WP_Error( 'amc_forbidden', __( 'You are not allowed to do this.', 'automotive-marketplace-core' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by Portal::handle_post().
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id   = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		$text = static function ( $key, $max = 500 ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, $max ) : '';
		};
		// phpcs:enable
		$ok   = static function ( $result, $message ) {
			return is_wp_error( $result ) ? $result : array( 'message' => $message );
		};

		switch ( $action ) {
			// Applications.
			case 'app_review':
				return $ok( Memberships::start_review( $id ), __( 'Application marked as under review.', 'automotive-marketplace-core' ) );
			case 'app_approve':
				return $ok( Memberships::approve( $id ), __( 'Application approved. A website setup job has been created.', 'automotive-marketplace-core' ) );
			case 'app_reject':
				if ( '' === $text( 'reason' ) ) {
					return new \WP_Error( 'amc_reason', __( 'Please give a reason: it is sent to the dealer.', 'automotive-marketplace-core' ) );
				}
				return $ok( Memberships::reject( $id, $text( 'reason' ) ), __( 'Application rejected.', 'automotive-marketplace-core' ) );

			// Dealers.
			case 'dealer_suspend':
				if ( '' === $text( 'reason' ) ) {
					return new \WP_Error( 'amc_reason', __( 'Please give a reason.', 'automotive-marketplace-core' ) );
				}
				Memberships::set_dealer_status( $id, 'suspended', $text( 'reason' ) );
				return array( 'message' => __( 'Dealer suspended: its listings are hidden from the marketplace.', 'automotive-marketplace-core' ) );
			case 'dealer_reactivate':
				$dealer = Memberships::get_dealer( $id );
				if ( ! $dealer ) {
					return new \WP_Error( 'amc_dealer', __( 'Dealer not found.', 'automotive-marketplace-core' ) );
				}
				$app = Memberships::latest_application( $id );
				Memberships::set_dealer_status( $id, $app && 'approved' === $app->status ? 'approved' : 'under_review' );
				Memberships::maybe_activate_dealer( $id );
				return array( 'message' => __( 'Dealer reactivated.', 'automotive-marketplace-core' ) );
			case 'dealer_cancel':
				Memberships::set_dealer_status( $id, 'cancelled', $text( 'reason' ) );
				foreach ( Subscriptions::for_dealer( $id ) as $sub ) {
					if ( in_array( $sub->status, array( 'pending_payment', 'paid', 'active' ), true ) ) {
						Subscriptions::cancel( $sub->id );
					}
				}
				return array( 'message' => __( 'Account cancelled.', 'automotive-marketplace-core' ) );
			case 'dealer_verify':
				global $wpdb;
				$dealer = Memberships::get_dealer( $id );
				if ( ! $dealer ) {
					return new \WP_Error( 'amc_dealer', __( 'Dealer not found.', 'automotive-marketplace-core' ) );
				}
				$wpdb->update( Database::table( 'dealers' ), array( 'is_verified' => $dealer->is_verified ? 0 : 1 ), array( 'id' => $id ) );
				Vehicles::flush_cache();
				Audit::log( $dealer->is_verified ? 'dealer.unverified' : 'dealer.verified', 'dealer', $id );
				return array( 'message' => $dealer->is_verified ? __( 'Verified badge removed.', 'automotive-marketplace-core' ) : __( 'Verified badge granted.', 'automotive-marketplace-core' ) );

			// Subscriptions.
			case 'sub_create':
				$plan   = Plans::get_by_code( $text( 'plan', 40 ) );
				$dealer = Memberships::get_dealer( absint( $text( 'dealer_id', 20 ) ) );
				if ( ! $plan || ! $dealer ) {
					return new \WP_Error( 'amc_sub', __( 'Choose a dealer and a plan.', 'automotive-marketplace-core' ) );
				}
				$sub_id = Subscriptions::create( $dealer->id, $plan->id, absint( $text( 'months', 3 ) ) );
				if ( is_wp_error( $sub_id ) ) {
					return $sub_id;
				}
				if ( ! empty( $in['paid'] ) ) {
					Payments::create( $dealer->id, $sub_id, 'manual', $text( 'reference', 120 ), 0, 'verified' );
					Subscriptions::on_paid( $sub_id );
				}
				return array( 'message' => __( 'Subscription created.', 'automotive-marketplace-core' ) );
			case 'sub_activate':
				$sub = Subscriptions::get( $id );
				if ( $sub && 'pending_payment' === $sub->status ) {
					Payments::create( $sub->dealer_id, $sub->id, 'manual', $text( 'reference', 120 ), 0, 'verified' );
				}
				return $ok( Subscriptions::activate( $id ), __( 'Subscription activated.', 'automotive-marketplace-core' ) );
			case 'sub_extend':
				return $ok( Subscriptions::extend( $id, absint( $text( 'months', 3 ) ) ), __( 'Subscription extended.', 'automotive-marketplace-core' ) );
			case 'sub_cancel':
				return $ok( Subscriptions::cancel( $id ), __( 'Subscription cancelled.', 'automotive-marketplace-core' ) );

			// Payments.
			case 'pay_verify':
				return $ok( Payments::verify( $id ), __( 'Payment confirmed.', 'automotive-marketplace-core' ) );
			case 'pay_reject':
				if ( '' === $text( 'reason' ) ) {
					return new \WP_Error( 'amc_reason', __( 'Please give a reason: it is sent to the dealer.', 'automotive-marketplace-core' ) );
				}
				return $ok( Payments::reject( $id, $text( 'reason' ) ), __( 'Payment rejected.', 'automotive-marketplace-core' ) );

			// Websites.
			case 'job_step':
				return $ok( Provisioning::set_step( $id, sanitize_key( $text( 'step', 40 ) ), ! empty( $in['done'] ) ), __( 'Checklist updated.', 'automotive-marketplace-core' ) );
			case 'job_details':
				Provisioning::save_details( $id, $text( 'site_url' ), $text( 'admin_url' ), isset( $in['notes'] ) ? (string) $in['notes'] : '' );
				return array( 'message' => __( 'Details saved.', 'automotive-marketplace-core' ) );
			case 'job_create':
				$app    = Memberships::latest_application( $id );
				$job_id = Provisioning::create_job( $id, $app ? $app->website_option : 'later', $app ? $app->domain : '' );
				return array(
					'message'  => __( 'Website setup job created.', 'automotive-marketplace-core' ),
					'redirect' => Portal::url( 'platform', 'websites', $job_id ),
				);

			// Plans.
			case 'plan_save':
				$saved = Plans::save( $in, $id );
				Audit::log( $id ? 'plan.updated' : 'plan.created', 'plan', is_wp_error( $saved ) ? 0 : $saved );
				return $ok( $saved, __( 'Plan saved. New prices apply to new orders.', 'automotive-marketplace-core' ) );
			case 'durations_save':
				Plans::save_durations( isset( $in['durations'] ) && is_array( $in['durations'] ) ? $in['durations'] : array() );
				Audit::log( 'plan.durations_updated', 'plan' );
				return array( 'message' => __( 'Durations and discounts saved.', 'automotive-marketplace-core' ) );

			// Listings.
			case 'listing_feature':
			case 'listing_unfeature':
			case 'listing_hide':
			case 'listing_show':
				return self::moderate_listing( $action, $id, absint( $text( 'days', 4 ) ) );

			// Support.
			case 'ticket_reply':
				$result = Support::reply( $id, isset( $in['message'] ) ? (string) $in['message'] : '', true );
				if ( ! is_wp_error( $result ) && ! empty( $in['close'] ) ) {
					Support::set_status( $id, 'closed' );
				}
				return $ok( $result, __( 'Reply sent to the dealer.', 'automotive-marketplace-core' ) );
			case 'ticket_status':
				Support::set_status( $id, sanitize_key( $text( 'status', 20 ) ) );
				return array( 'message' => __( 'Conversation updated.', 'automotive-marketplace-core' ) );

			// Settings.
			case 'settings_save':
				update_option( Settings::OPTION, Settings::sanitize( array_merge( Settings::all(), $in ) ) );
				return array( 'message' => __( 'Settings saved.', 'automotive-marketplace-core' ) );
		}

		return new \WP_Error( 'amc_action', __( 'Unknown action.', 'automotive-marketplace-core' ) );
	}

	/**
	 * Feature / hide a marketplace listing. Hiding sets the listing to
	 * "unpublished"; the dealer website stays the source of truth and its
	 * next update may publish it again.
	 */
	private static function moderate_listing( $action, $id, $days ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		$data  = array();
		switch ( $action ) {
			case 'listing_feature':
				$data = array(
					'is_featured'    => 1,
					'featured_until' => $days ? gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) : null,
				);
				break;
			case 'listing_unfeature':
				$data = array(
					'is_featured'    => 0,
					'featured_until' => null,
				);
				break;
			case 'listing_hide':
				$data = array( 'status' => 'unpublished' );
				break;
			case 'listing_show':
				$data = array( 'status' => 'active' );
				break;
		}
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
		Vehicles::flush_cache();
		Audit::log( 'listing.' . substr( $action, 8 ), 'vehicle', $id );
		return array( 'message' => __( 'Listing updated.', 'automotive-marketplace-core' ) );
	}

	/** Marketplace listings for moderation. */
	public static function listings( $q = '', $limit = 100 ) {
		global $wpdb;
		$v      = Database::table( 'vehicles' );
		$d      = Database::table( 'dealers' );
		$where  = '1=1';
		$params = array();
		if ( '' !== $q ) {
			$where   .= ' AND (v.title LIKE %s OR d.business_name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $q ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		$params[] = absint( $limit );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT v.id, v.slug, v.title, v.status, v.listing_type, v.price_amount, v.price_period, v.currency, v.is_featured, v.featured_until, v.last_synced_at, v.is_demo, d.business_name, d.status AS dealer_status FROM $v v LEFT JOIN $d d ON d.id = v.dealer_id WHERE $where ORDER BY v.updated_at DESC LIMIT %d", $params ) );
		return $rows ? $rows : array();
	}
}
