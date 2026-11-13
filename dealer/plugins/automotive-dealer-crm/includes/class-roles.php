<?php
/**
 * Dealer team roles and granular capabilities.
 *
 * Every manager page and every action checks a capability server-side.
 * Owners can grant extra capabilities to an individual team member.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Roles {

	/** Capability => label. */
	public static function capabilities() {
		return array(
			'adc_access'           => __( 'Access the CRM', 'automotive-dealer-crm' ),
			'adc_manage_vehicles'  => __( 'Add and edit vehicles', 'automotive-dealer-crm' ),
			'adc_publish_vehicles' => __( 'Publish and archive vehicles', 'automotive-dealer-crm' ),
			'adc_edit_prices'      => __( 'Change prices of published vehicles', 'automotive-dealer-crm' ),
			'adc_manage_leads'     => __( 'Manage leads', 'automotive-dealer-crm' ),
			'adc_view_all_leads'   => __( 'See every lead (not only assigned ones)', 'automotive-dealer-crm' ),
			'adc_manage_customers' => __( 'Manage customers', 'automotive-dealer-crm' ),
			'adc_manage_sales'     => __( 'Record and confirm sales', 'automotive-dealer-crm' ),
			'adc_manage_rentals'   => __( 'Manage rentals', 'automotive-dealer-crm' ),
			'adc_view_reports'     => __( 'See revenue and statistics', 'automotive-dealer-crm' ),
			'adc_manage_settings'  => __( 'Edit business and website settings', 'automotive-dealer-crm' ),
			'adc_manage_team'      => __( 'Manage the team', 'automotive-dealer-crm' ),
		);
	}

	/** Role => [ label, caps ]. */
	public static function roles() {
		$all = array_keys( self::capabilities() );
		return array(
			'adc_owner'   => array( __( 'Dealer owner', 'automotive-dealer-crm' ), $all ),
			'adc_manager' => array( __( 'Dealer manager', 'automotive-dealer-crm' ), array_diff( $all, array( 'adc_manage_team' ) ) ),
			'adc_sales'   => array( __( 'Sales agent', 'automotive-dealer-crm' ), array( 'adc_access', 'adc_manage_vehicles', 'adc_manage_leads', 'adc_manage_customers', 'adc_manage_sales' ) ),
			'adc_rental'  => array( __( 'Rental agent', 'automotive-dealer-crm' ), array( 'adc_access', 'adc_manage_leads', 'adc_manage_customers', 'adc_manage_rentals' ) ),
			'adc_viewer'  => array( __( 'Viewer', 'automotive-dealer-crm' ), array( 'adc_access', 'adc_view_all_leads', 'adc_view_reports' ) ),
		);
	}

	public static function install() {
		foreach ( self::roles() as $role => $def ) {
			remove_role( $role );
			add_role( $role, $def[0], array_fill_keys( array_merge( array( 'read' ), $def[1] ), true ) );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( self::capabilities() ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'keep_team_out_of_wp_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	/** Team members without editorial rights use /manager/, never wp-admin. */
	public static function is_team_only( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return $user_id && user_can( $user_id, 'adc_access' ) && ! user_can( $user_id, 'edit_posts' );
	}

	public static function keep_team_out_of_wp_admin() {
		global $pagenow;
		if ( wp_doing_ajax() || in_array( $pagenow, array( 'admin-post.php', 'async-upload.php', 'profile.php' ), true ) || ! self::is_team_only() ) {
			return;
		}
		wp_safe_redirect( adc_manager_url() );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_team_only() ? false : $show;
	}

	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof \WP_User && ! $requested && user_can( $user, 'adc_access' ) && ! user_can( $user, 'edit_posts' ) ) {
			return adc_manager_url();
		}
		return $redirect;
	}

	/** Main CRM role of a user ('' if none). */
	public static function role_of( $user ) {
		foreach ( array_keys( self::roles() ) as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return $role;
			}
		}
		return in_array( 'administrator', (array) $user->roles, true ) ? 'administrator' : '';
	}

	public static function role_label( $role ) {
		if ( 'administrator' === $role ) {
			return __( 'Platform support', 'automotive-dealer-crm' );
		}
		$roles = self::roles();
		return isset( $roles[ $role ] ) ? $roles[ $role ][0] : '—';
	}

	/** Abort when the current user lacks a capability. */
	public static function require_cap( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			return new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		}
		return true;
	}
}
