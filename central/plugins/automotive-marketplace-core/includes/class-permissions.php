<?php
/**
 * Roles and access rules.
 *
 * - "amc_dealer_owner": owner of a dealership account on the central
 *   platform. Uses the dealer space (/dealer-dashboard/), never wp-admin.
 * - "amc_platform_manager": platform staff. Uses /platform-admin/.
 * - Administrators also receive the platform capability.
 *
 * The dealer an account belongs to is always resolved server-side from the
 * logged-in user (amc_dealers.user_id), never from request parameters.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Permissions {

	const STAFF  = 'amc_manage_platform';
	const DEALER = 'amc_dealer_portal';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'keep_dealers_out_of_wp_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	public static function install_roles() {
		add_role( 'amc_dealer_owner', 'Dealer owner', array( 'read' => true, self::DEALER => true ) );
		add_role( 'amc_platform_manager', 'Platform manager', array( 'read' => true, self::STAFF => true ) );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::STAFF );
		}
	}

	public static function is_staff( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return $user_id && user_can( $user_id, self::STAFF );
	}

	/** Dealer-only accounts (no staff or editorial rights). */
	public static function is_dealer_only( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return $user_id && user_can( $user_id, self::DEALER ) && ! user_can( $user_id, self::STAFF ) && ! user_can( $user_id, 'edit_posts' );
	}

	public static function keep_dealers_out_of_wp_admin() {
		global $pagenow;
		if ( wp_doing_ajax() || in_array( $pagenow, array( 'admin-post.php', 'profile.php' ), true ) || ! self::is_dealer_only() ) {
			return;
		}
		wp_safe_redirect( Portal::url( 'account' ) );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_dealer_only() ? false : $show;
	}

	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof \WP_User && ! $requested ) {
			if ( user_can( $user, self::STAFF ) && ! user_can( $user, 'manage_options' ) ) {
				return Portal::url( 'platform' );
			}
			if ( user_can( $user, self::DEALER ) && ! user_can( $user, 'edit_posts' ) ) {
				return Portal::url( 'account' );
			}
		}
		return $redirect;
	}
}
