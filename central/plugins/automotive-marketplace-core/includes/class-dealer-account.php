<?php
/**
 * Actions available to a dealership owner in the dealer space. The dealer is
 * always the one resolved from the logged-in user by the portal.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Dealer_Account {

	/**
	 * @param string $action Action key (nonce already verified).
	 * @param object $dealer Current user's dealership.
	 * @return array|\WP_Error { message, redirect }
	 */
	public static function handle( $action, $dealer ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by Portal::handle_post().
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$text = static function ( $key, $max = 191 ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, $max ) : '';
		};
		// phpcs:enable

		switch ( $action ) {
			case 'profile':
				return self::save_profile( $dealer, $in, $text );

			case 'document':
				if ( ! Documents::has_upload( 'document' ) ) {
					return new \WP_Error( 'amc_doc', __( 'Choose a file to upload.', 'automotive-marketplace-core' ) );
				}
				$check = Documents::validate( 'document' );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
				$type = $text( 'type', 30 );
				$app  = Memberships::latest_application( $dealer->id );
				Documents::store( 'document', $dealer->id, 'payment_proof' === $type ? 'other' : $type, $app ? $app->id : 0 );
				Audit::log( 'document.uploaded', 'dealer', $dealer->id );
				return array( 'message' => __( 'Document uploaded. Our team will review it.', 'automotive-marketplace-core' ) );

			case 'payment':
				$sub = Subscriptions::get( absint( $text( 'subscription_id', 20 ) ) );
				if ( ! $sub || (int) $sub->dealer_id !== (int) $dealer->id || 'pending_payment' !== $sub->status ) {
					return new \WP_Error( 'amc_pay', __( 'This order is not waiting for payment.', 'automotive-marketplace-core' ) );
				}
				$method   = $text( 'payment_method', 30 );
				$provider = isset( Payments::providers()[ $method ] ) ? Payments::providers()[ $method ] : null;
				if ( ! $provider ) {
					return new \WP_Error( 'amc_pay', __( 'Choose a payment method.', 'automotive-marketplace-core' ) );
				}
				if ( Payments::pending_for_subscription( $sub->id ) ) {
					return new \WP_Error( 'amc_pay', __( 'A payment for this order is already waiting for confirmation.', 'automotive-marketplace-core' ) );
				}
				$proof = 0;
				if ( Documents::has_upload( 'payment_proof' ) ) {
					$check = Documents::validate( 'payment_proof' );
					if ( is_wp_error( $check ) ) {
						return $check;
					}
					$proof = Documents::store( 'payment_proof', $dealer->id, 'payment_proof' );
				}
				if ( $provider->requires_proof() && ! $proof && '' === $text( 'payer_reference', 120 ) ) {
					return new \WP_Error( 'amc_pay', __( 'Attach the receipt or enter the transaction reference.', 'automotive-marketplace-core' ) );
				}
				Payments::create( $dealer->id, $sub->id, $method, $text( 'payer_reference', 120 ), $proof );
				return array( 'message' => __( 'Thank you. Your payment has been sent for confirmation.', 'automotive-marketplace-core' ) );

			case 'checkout':
				if ( in_array( $dealer->status, array( 'cancelled', 'suspended' ), true ) ) {
					return new \WP_Error( 'amc_checkout', __( 'Your account cannot place an order at the moment. Please contact support.', 'automotive-marketplace-core' ) );
				}
				$plan   = Plans::get_by_code( $text( 'plan', 40 ) );
				$months = absint( $text( 'months', 3 ) );
				if ( ! $plan || ! $plan->is_active || ! isset( Plans::durations()[ $months ] ) ) {
					return new \WP_Error( 'amc_checkout', __( 'Choose a plan and a duration.', 'automotive-marketplace-core' ) );
				}
				$open = Subscriptions::open_for_dealer( $dealer->id );
				if ( $open && 'paid' === $open->status ) {
					return new \WP_Error( 'amc_checkout', __( 'You already have a paid order waiting to start.', 'automotive-marketplace-core' ) );
				}
				if ( $open && Payments::pending_for_subscription( $open->id ) ) {
					return new \WP_Error( 'amc_checkout', __( 'A payment is waiting for confirmation. Please wait for it before placing a new order.', 'automotive-marketplace-core' ) );
				}
				if ( $open ) {
					Subscriptions::cancel( $open->id ); // Replaced by the new order.
				}
				$id = Subscriptions::create( $dealer->id, $plan->id, $months );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				return array(
					'message'  => __( 'Order created. Complete your payment below.', 'automotive-marketplace-core' ),
					'redirect' => Portal::url( 'account', 'subscription' ) . '#amc-pay',
				);

			case 'ticket_new':
				$id = Support::create( $dealer->id, $text( 'subject' ), $text( 'category', 20 ), isset( $in['message'] ) ? (string) $in['message'] : '' );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				return array(
					'message'  => __( 'Your request has been sent. We will reply here and by email.', 'automotive-marketplace-core' ),
					'redirect' => Portal::url( 'account', 'support', $id ),
				);

			case 'ticket_reply':
				$ticket = Support::get( absint( $text( 'ticket_id', 20 ) ) );
				if ( ! $ticket || (int) $ticket->dealer_id !== (int) $dealer->id ) {
					return new \WP_Error( 'amc_ticket', __( 'Conversation not found.', 'automotive-marketplace-core' ) );
				}
				$ok = Support::reply( $ticket->id, isset( $in['message'] ) ? (string) $in['message'] : '', false );
				return is_wp_error( $ok ) ? $ok : array( 'message' => __( 'Message sent.', 'automotive-marketplace-core' ) );
		}

		return new \WP_Error( 'amc_action', __( 'Unknown action.', 'automotive-marketplace-core' ) );
	}

	private static function save_profile( $dealer, array $in, $text ) {
		global $wpdb;
		$city = $text( 'city', 100 );
		$data = array(
			'description' => isset( $in['description'] ) ? mb_substr( sanitize_textarea_field( (string) $in['description'] ), 0, 2000 ) : $dealer->description,
			'address'     => $text( 'address', 255 ),
			'city'        => $city ? $city : $dealer->city,
			'city_slug'   => sanitize_title( $city ? $city : $dealer->city ),
			'phone'       => amc_normalize_phone( $text( 'phone', 32 ) ),
			'whatsapp'    => amc_normalize_phone( $text( 'whatsapp', 32 ) ),
			'website_url' => esc_url_raw( $text( 'website_url', 500 ), array( 'http', 'https' ) ),
			'updated_at'  => current_time( 'mysql', true ),
		);

		if ( isset( $_FILES['logo']['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['logo']['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( (int) $_FILES['logo']['size'] > 2097152 ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return new \WP_Error( 'amc_logo', __( 'The logo is too large (2 MB maximum).', 'automotive-marketplace-core' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$upload = wp_handle_upload(
				$_FILES['logo'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
				array(
					'test_form' => false,
					'mimes'     => array(
						'jpg|jpeg' => 'image/jpeg',
						'png'      => 'image/png',
						'webp'     => 'image/webp',
					),
				)
			);
			if ( isset( $upload['error'] ) ) {
				return new \WP_Error( 'amc_logo', $upload['error'] );
			}
			$data['logo_url'] = esc_url_raw( $upload['url'] );
		}

		$wpdb->update( Database::table( 'dealers' ), $data, array( 'id' => (int) $dealer->id ) );
		Vehicles::flush_cache();
		Audit::log( 'dealer.profile_updated', 'dealer', $dealer->id );
		return array( 'message' => __( 'Profile saved.', 'automotive-marketplace-core' ) );
	}

	/**
	 * Getting-started checklist.
	 *
	 * @return array[] { label, done, url, hint }
	 */
	public static function onboarding( $dealer ) {
		$docs    = Documents::for_dealer( $dealer->id );
		$current = Subscriptions::current_for_dealer( $dealer->id );
		$open    = Subscriptions::open_for_dealer( $dealer->id );
		$job     = Provisioning::for_dealer( $dealer->id );
		$has_doc = (bool) array_filter(
			$docs,
			static function ( $doc ) {
				return 'payment_proof' !== $doc->type;
			}
		);
		return array(
			array(
				'label' => __( 'Send your business documents', 'automotive-marketplace-core' ),
				'hint'  => __( 'RCCM, NINEA and the ID of the manager speed up the verification.', 'automotive-marketplace-core' ),
				'done'  => $has_doc,
				'url'   => Portal::url( 'account', 'documents' ),
			),
			array(
				'label' => __( 'Pay your subscription', 'automotive-marketplace-core' ),
				'hint'  => __( 'Wave, Orange Money, bank transfer or cash.', 'automotive-marketplace-core' ),
				'done'  => $current || ( $open && 'paid' === $open->status ),
				'url'   => Portal::url( 'account', 'subscription' ),
			),
			array(
				'label' => __( 'Add your logo and description', 'automotive-marketplace-core' ),
				'hint'  => __( 'They appear on your public profile and your website.', 'automotive-marketplace-core' ),
				'done'  => '' !== $dealer->logo_url && '' !== trim( $dealer->description ),
				'url'   => Portal::url( 'account', 'profile' ),
			),
			array(
				'label' => __( 'Receive your website and CRM', 'automotive-marketplace-core' ),
				'hint'  => __( 'Our team sets them up after approval and sends you the access details.', 'automotive-marketplace-core' ),
				'done'  => $job && 'done' === $job->status,
				'url'   => Portal::url( 'account', 'website' ),
			),
			array(
				'label' => __( 'Publish your first vehicles', 'automotive-marketplace-core' ),
				'hint'  => __( 'From your CRM: published vehicles appear on your website and on the marketplace.', 'automotive-marketplace-core' ),
				'done'  => self::listing_count( $dealer->id ) > 0,
				'url'   => '',
			),
		);
	}

	public static function listing_count( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE dealer_id = %d AND status IN ('active','reserved')", absint( $dealer_id ) ) );
	}
}
