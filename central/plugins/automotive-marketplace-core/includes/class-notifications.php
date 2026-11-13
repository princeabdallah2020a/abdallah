<?php
/**
 * Transactional emails (dealers and platform staff).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Notifications {

	/**
	 * Send a branded HTML email.
	 *
	 * @param string   $to        Recipient.
	 * @param string   $subject   Subject (site name is prefixed).
	 * @param string[] $lines     Paragraphs (plain text).
	 * @param string   $cta_label Button label.
	 * @param string   $cta_url   Button URL.
	 */
	public static function send( $to, $subject, array $lines, $cta_label = '', $cta_url = '' ) {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$body = '<div style="background:#f6f7f9;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;color:#0e1726">'
			. '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #dfe3e9;border-radius:14px;overflow:hidden">'
			. '<div style="background:#0e1726;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold">' . esc_html( $site ) . '</div>'
			. '<div style="padding:24px;font-size:15px;line-height:1.6">';
		foreach ( $lines as $line ) {
			$body .= '<p style="margin:0 0 14px">' . nl2br( esc_html( $line ) ) . '</p>';
		}
		if ( $cta_label && $cta_url ) {
			$body .= '<p style="margin:22px 0 4px"><a href="' . esc_url( $cta_url ) . '" style="display:inline-block;background:#cf3e1a;color:#fff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:10px">' . esc_html( $cta_label ) . '</a></p>';
		}
		$body .= '</div></div><p style="text-align:center;font-size:12px;color:#566070;margin-top:16px">' . esc_html( $site ) . ' · ' . esc_html( home_url( '/' ) ) . '</p></div>';

		return wp_mail( $to, '[' . $site . '] ' . $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function staff_email() {
		return Settings::contact_email();
	}

	public static function dealer_email( $dealer ) {
		if ( ! $dealer ) {
			return '';
		}
		if ( $dealer->contact_email ) {
			return $dealer->contact_email;
		}
		$user = $dealer->user_id ? get_userdata( (int) $dealer->user_id ) : null;
		return $user ? $user->user_email : '';
	}

	public static function application_received( $dealer_id ) {
		$dealer = Memberships::get_dealer( $dealer_id );
		self::send(
			self::dealer_email( $dealer ),
			__( 'We have received your application', 'automotive-marketplace-core' ),
			array(
				/* translators: %s: business name */
				sprintf( __( 'Hello %s,', 'automotive-marketplace-core' ), $dealer->business_name ),
				__( 'Thank you for choosing us. Your application has been received and will be reviewed by our team, usually within two working days.', 'automotive-marketplace-core' ),
				__( 'You can follow each step, add documents and send your payment from your dealer space.', 'automotive-marketplace-core' ),
			),
			__( 'Open my dealer space', 'automotive-marketplace-core' ),
			Portal::url( 'account' )
		);
		self::send(
			self::staff_email(),
			/* translators: %s: business name */
			sprintf( __( 'New dealer application: %s', 'automotive-marketplace-core' ), $dealer->business_name ),
			array(
				/* translators: 1: business name, 2: city */
				sprintf( __( '%1$s (%2$s) has submitted an application.', 'automotive-marketplace-core' ), $dealer->business_name, $dealer->city ),
			),
			__( 'Review the application', 'automotive-marketplace-core' ),
			Portal::url( 'platform', 'applications' )
		);
	}

	public static function application_decision( $dealer_id, $approved, $reason = '' ) {
		$dealer = Memberships::get_dealer( $dealer_id );
		$lines  = $approved
			? array(
				__( 'Good news: your application has been approved.', 'automotive-marketplace-core' ),
				__( 'Our team is now preparing your website and your CRM. You will receive your access details as soon as they are ready. If your payment has not been sent yet, you can send it from your dealer space.', 'automotive-marketplace-core' ),
			)
			: array_filter(
				array(
					__( 'After review, we are unable to approve your application at this stage.', 'automotive-marketplace-core' ),
					/* translators: %s: reason */
					$reason ? sprintf( __( 'Reason: %s', 'automotive-marketplace-core' ), $reason ) : '',
					__( 'You can reply through the support section of your dealer space if you have questions or new documents.', 'automotive-marketplace-core' ),
				)
			);
		self::send( self::dealer_email( $dealer ), $approved ? __( 'Your application is approved', 'automotive-marketplace-core' ) : __( 'About your application', 'automotive-marketplace-core' ), $lines, __( 'Open my dealer space', 'automotive-marketplace-core' ), Portal::url( 'account' ) );
	}

	public static function payment_submitted( $payment_id ) {
		$payment = Payments::get( $payment_id );
		$dealer  = Memberships::get_dealer( $payment->dealer_id );
		self::send(
			self::staff_email(),
			/* translators: %s: business name */
			sprintf( __( 'Payment to verify: %s', 'automotive-marketplace-core' ), $dealer->business_name ),
			array(
				/* translators: 1: amount, 2: method, 3: reference */
				sprintf( __( 'Amount: %1$s · Method: %2$s · Reference: %3$s', 'automotive-marketplace-core' ), amc_format_money( $payment->amount, $payment->currency ), Payments::method_label( $payment->method ), $payment->reference ),
			),
			__( 'Verify the payment', 'automotive-marketplace-core' ),
			Portal::url( 'platform', 'payments' )
		);
	}

	public static function payment_decision( $payment_id, $verified, $reason = '' ) {
		$payment = Payments::get( $payment_id );
		$dealer  = Memberships::get_dealer( $payment->dealer_id );
		$amount  = amc_format_money( $payment->amount, $payment->currency );
		$lines   = $verified
			/* translators: %s: amount */
			? array( sprintf( __( 'We have received and confirmed your payment of %s. Thank you.', 'automotive-marketplace-core' ), $amount ) )
			: array_filter(
				array(
					/* translators: %s: amount */
					sprintf( __( 'We could not confirm your payment of %s.', 'automotive-marketplace-core' ), $amount ),
					/* translators: %s: reason */
					$reason ? sprintf( __( 'Reason: %s', 'automotive-marketplace-core' ), $reason ) : '',
					__( 'Please check the details and send a new receipt from your dealer space.', 'automotive-marketplace-core' ),
				)
			);
		self::send( self::dealer_email( $dealer ), $verified ? __( 'Payment confirmed', 'automotive-marketplace-core' ) : __( 'Payment not confirmed', 'automotive-marketplace-core' ), $lines, __( 'Open my dealer space', 'automotive-marketplace-core' ), Portal::url( 'account', 'subscription' ) );
	}

	public static function subscription_active( $sub_id ) {
		$sub    = Subscriptions::get( $sub_id );
		$dealer = Memberships::get_dealer( $sub->dealer_id );
		self::send(
			self::dealer_email( $dealer ),
			__( 'Your subscription is active', 'automotive-marketplace-core' ),
			array(
				/* translators: 1: plan name, 2: end date */
				sprintf( __( 'Your %1$s plan is active until %2$s.', 'automotive-marketplace-core' ), $sub->plan_name, Portal::date( $sub->ends_at ) ),
			),
			__( 'Open my dealer space', 'automotive-marketplace-core' ),
			Portal::url( 'account' )
		);
	}

	public static function subscription_reminder( $sub_id, $days ) {
		$sub    = Subscriptions::get( $sub_id );
		$dealer = Memberships::get_dealer( $sub->dealer_id );
		self::send(
			self::dealer_email( $dealer ),
			__( 'Your subscription expires soon', 'automotive-marketplace-core' ),
			array(
				/* translators: 1: plan name, 2: end date */
				sprintf( __( 'Your %1$s plan ends on %2$s.', 'automotive-marketplace-core' ), $sub->plan_name, Portal::date( $sub->ends_at ) ),
				__( 'Renew now to keep your website, your CRM and your listings online without interruption.', 'automotive-marketplace-core' ),
			),
			__( 'Renew my subscription', 'automotive-marketplace-core' ),
			Portal::url( 'account', 'checkout' )
		);
	}

	public static function subscription_expired( $sub_id ) {
		$sub    = Subscriptions::get( $sub_id );
		$dealer = Memberships::get_dealer( $sub->dealer_id );
		self::send(
			self::dealer_email( $dealer ),
			__( 'Your subscription has expired', 'automotive-marketplace-core' ),
			array(
				__( 'Your subscription has expired and your listings are no longer shown on the marketplace.', 'automotive-marketplace-core' ),
				__( 'Renew at any time to restore them immediately after payment confirmation.', 'automotive-marketplace-core' ),
			),
			__( 'Renew my subscription', 'automotive-marketplace-core' ),
			Portal::url( 'account', 'checkout' )
		);
	}

	public static function website_ready( $job_id ) {
		$job    = Provisioning::get( $job_id );
		$dealer = Memberships::get_dealer( $job->dealer_id );
		self::send(
			self::dealer_email( $dealer ),
			__( 'Your website is ready', 'automotive-marketplace-core' ),
			array_filter(
				array(
					__( 'Your dealer website and your CRM have been set up.', 'automotive-marketplace-core' ),
					/* translators: %s: URL */
					$job->site_url ? sprintf( __( 'Website: %s', 'automotive-marketplace-core' ), $job->site_url ) : '',
					__( 'Your login details are sent separately by our team. For security, change your password at first login.', 'automotive-marketplace-core' ),
				)
			),
			__( 'Open my dealer space', 'automotive-marketplace-core' ),
			Portal::url( 'account', 'website' )
		);
	}

	public static function ticket_reply( $ticket_id, $to_staff ) {
		$ticket = Support::get( $ticket_id );
		if ( $to_staff ) {
			self::send(
				self::staff_email(),
				/* translators: %s: ticket subject */
				sprintf( __( 'Support: %s', 'automotive-marketplace-core' ), $ticket->subject ),
				array( __( 'A dealer has written in a support conversation.', 'automotive-marketplace-core' ) ),
				__( 'Open the conversation', 'automotive-marketplace-core' ),
				Portal::url( 'platform', 'support', $ticket_id )
			);
			return;
		}
		self::send(
			self::dealer_email( Memberships::get_dealer( $ticket->dealer_id ) ),
			/* translators: %s: ticket subject */
			sprintf( __( 'Support: %s', 'automotive-marketplace-core' ), $ticket->subject ),
			array( __( 'Our team has replied to your request.', 'automotive-marketplace-core' ) ),
			__( 'Open the conversation', 'automotive-marketplace-core' ),
			Portal::url( 'account', 'support', $ticket_id )
		);
	}
}
