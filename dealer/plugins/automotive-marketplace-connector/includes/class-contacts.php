<?php
/**
 * WhatsApp numbers of the team allowed to use the AutoBuzy AI assistant.
 *
 * Each team member registers their own number. The list (number, user id,
 * name) is sent to the marketplace, which uses it to recognise who writes:
 * the AI then acts in the CRM with that person's own permissions.
 *
 * @package AutomotiveMarketplaceConnector
 */

namespace ACX;

defined( 'ABSPATH' ) || exit;

final class Contacts {

	const META = 'acx_whatsapp';

	public static function normalize( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}
		if ( '+' !== $phone[0] ) {
			// Local number: prefix the business country code (Senegal by default).
			$codes = array( 'SN' => '221', 'CI' => '225', 'ML' => '223', 'GN' => '224', 'MR' => '222', 'GM' => '220', 'FR' => '33', 'MA' => '212', 'BF' => '226', 'CM' => '237' );
			$cc    = $codes[ adc_business()->country ] ?? '221';
			$phone = '+' . $cc . ltrim( $phone, '0' );
		}
		return preg_match( '/^\+[1-9][0-9]{7,14}$/', $phone ) ? $phone : '';
	}

	public static function own() {
		return (string) get_user_meta( get_current_user_id(), self::META, true );
	}

	/** @return array|\WP_Error */
	public static function save_own( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' === $phone ) {
			delete_user_meta( get_current_user_id(), self::META );
		} else {
			$normalized = self::normalize( $phone );
			if ( '' === $normalized ) {
				return new \WP_Error( 'acx_phone', __( 'Please enter a valid WhatsApp number in international format, e.g. +221 77 123 45 67.', 'automotive-marketplace-connector' ) );
			}
			foreach ( self::members() as $member ) {
				if ( $member['phone'] === $normalized && (int) $member['user_ref'] !== get_current_user_id() ) {
					return new \WP_Error( 'acx_phone', __( 'This number is already used by another team member.', 'automotive-marketplace-connector' ) );
				}
			}
			update_user_meta( get_current_user_id(), self::META, $normalized );
		}
		$pushed = self::push();
		if ( is_wp_error( $pushed ) && Connection::is_connected() ) {
			return new \WP_Error( 'acx_phone', __( 'Number saved here, but the marketplace could not be updated yet. It will be retried automatically.', 'automotive-marketplace-connector' ) );
		}
		return array( 'message' => '' === $phone ? __( 'Number removed.', 'automotive-marketplace-connector' ) : __( 'Number saved. You can now write to the AutoBuzy assistant on WhatsApp.', 'automotive-marketplace-connector' ) );
	}

	/** Team members with a registered number. */
	public static function members() {
		$out = array();
		foreach ( \ADC\Team::members() as $user ) {
			$phone = (string) get_user_meta( $user->ID, self::META, true );
			if ( '' !== $phone && user_can( $user, 'adc_access' ) ) {
				$out[] = array(
					'phone'    => $phone,
					'user_ref' => (int) $user->ID,
					'name'     => $user->display_name,
					'role'     => \ADC\Roles::role_label( \ADC\Roles::role_of( $user ) ),
				);
			}
		}
		return $out;
	}

	/** Send the list to the marketplace. */
	public static function push() {
		if ( ! Connection::is_connected() ) {
			return false;
		}
		$result = Connection::request( 'POST', '/sync/contacts', array( 'contacts' => self::members() ) );
		if ( is_wp_error( $result ) ) {
			Database::log( __( 'Assistant numbers could not be sent:', 'automotive-marketplace-connector' ) . ' ' . $result->get_error_message(), 'error' );
		}
		return $result;
	}
}
