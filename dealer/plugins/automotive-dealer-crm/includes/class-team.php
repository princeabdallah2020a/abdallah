<?php
/**
 * Team members and invitations.
 *
 * Invitations are single-use links valid 7 days; only a hash of the token is
 * stored. Only users with "adc_manage_team" (owners) manage the team, and the
 * last owner can never be removed or demoted.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Team {

	/** @return \WP_User[] */
	public static function members() {
		return get_users(
			array(
				'role__in' => array_merge( array_keys( Roles::roles() ) ),
				'orderby'  => 'display_name',
			)
		);
	}

	public static function is_member( $user_id ) {
		return $user_id && user_can( $user_id, 'adc_access' );
	}

	/** Members who can handle a capability (for assignment lists). */
	public static function members_with( $cap ) {
		return array_values(
			array_filter(
				self::members(),
				static function ( $user ) use ( $cap ) {
					return user_can( $user, $cap );
				}
			)
		);
	}

	private static function owners() {
		return get_users( array( 'role' => 'adc_owner', 'fields' => 'ID' ) );
	}

	/** Extra capabilities granted individually (beyond the role). */
	public static function extra_caps( \WP_User $user ) {
		$role  = Roles::role_of( $user );
		$roles = Roles::roles();
		$base  = isset( $roles[ $role ] ) ? $roles[ $role ][1] : array();
		$out   = array();
		foreach ( array_keys( Roles::capabilities() ) as $cap ) {
			if ( ! empty( $user->caps[ $cap ] ) && ! in_array( $cap, $base, true ) ) {
				$out[] = $cap;
			}
		}
		return $out;
	}

	private static function clean_caps( $caps ) {
		return array_values( array_intersect( (array) $caps, array_diff( array_keys( Roles::capabilities() ), array( 'adc_manage_team' ) ) ) );
	}

	/** @return true|\WP_Error */
	public static function invite( array $in ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_team' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$email = sanitize_email( isset( $in['email'] ) ? (string) $in['email'] : '' );
		$role  = isset( $in['role'] ) ? sanitize_key( $in['role'] ) : '';
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'adc_invite', __( 'Enter a valid email address.', 'automotive-dealer-crm' ) );
		}
		if ( ! isset( Roles::roles()[ $role ] ) ) {
			return new \WP_Error( 'adc_invite', __( 'Choose a role.', 'automotive-dealer-crm' ) );
		}
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'adc_invite', __( 'This person already has an account on this website.', 'automotive-dealer-crm' ) );
		}
		$token = wp_generate_password( 40, false, false );
		$wpdb->insert(
			Database::table( 'invites' ),
			array(
				'email'      => $email,
				'name'       => mb_substr( sanitize_text_field( isset( $in['name'] ) ? (string) $in['name'] : '' ), 0, 120 ),
				'role'       => $role,
				'caps'       => wp_json_encode( self::clean_caps( isset( $in['caps'] ) ? $in['caps'] : array() ) ),
				'token_hash' => hash( 'sha256', $token ),
				'invited_by' => get_current_user_id(),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$link = adc_manager_url( 'invite', $token );
		$site = wp_specialchars_decode( adc_business()->name, ENT_QUOTES );
		wp_mail(
			$email,
			/* translators: %s: business name */
			sprintf( __( 'You are invited to join %s', 'automotive-dealer-crm' ), $site ),
			sprintf(
				/* translators: 1: inviter, 2: business, 3: role, 4: link */
				__( "Hello,\n\n%1\$s invites you to join the team of %2\$s as %3\$s.\n\nCreate your access here (valid 7 days):\n%4\$s\n", 'automotive-dealer-crm' ),
				wp_get_current_user()->display_name,
				$site,
				Roles::role_label( $role ),
				$link
			)
		);
		Activity::log( 'team_invited', 'invite', (int) $wpdb->insert_id, $email );
		return true;
	}

	public static function pending_invites() {
		global $wpdb;
		$table = Database::table( 'invites' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE accepted_at IS NULL AND expires_at > %s ORDER BY id DESC", gmdate( 'Y-m-d H:i:s' ) ) );
		return $rows ? $rows : array();
	}

	public static function revoke_invite( $id ) {
		global $wpdb;
		$check = Roles::require_cap( 'adc_manage_team' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$wpdb->update( Database::table( 'invites' ), array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ), array( 'id' => absint( $id ) ) );
		return true;
	}

	public static function find_invite( $token ) {
		global $wpdb;
		$table = Database::table( 'invites' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token_hash = %s AND accepted_at IS NULL AND expires_at > %s", hash( 'sha256', (string) $token ), gmdate( 'Y-m-d H:i:s' ) ) );
	}

	/** @return int|\WP_Error New user id. */
	public static function accept_invite( $token, $name, $password ) {
		global $wpdb;
		$invite = self::find_invite( $token );
		if ( ! $invite ) {
			return new \WP_Error( 'adc_invite', __( 'This invitation is invalid or has expired.', 'automotive-dealer-crm' ) );
		}
		if ( mb_strlen( (string) $password ) < 8 ) {
			return new \WP_Error( 'adc_invite', __( 'Choose a password of at least 8 characters.', 'automotive-dealer-crm' ) );
		}
		if ( email_exists( $invite->email ) ) {
			return new \WP_Error( 'adc_invite', __( 'This person already has an account on this website.', 'automotive-dealer-crm' ) );
		}
		$name  = mb_substr( sanitize_text_field( $name ), 0, 120 );
		$name  = $name ? $name : $invite->name;
		$login = sanitize_user( strstr( $invite->email, '@', true ), true );
		$login = $login ? $login : 'team';
		$base  = $login;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $i++;
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $invite->email,
				'user_pass'    => $password,
				'display_name' => $name ? $name : $login,
				'first_name'   => $name,
				'role'         => $invite->role,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$user = get_userdata( $user_id );
		foreach ( (array) json_decode( (string) $invite->caps, true ) as $cap ) {
			$user->add_cap( $cap );
		}
		$wpdb->update( Database::table( 'invites' ), array( 'accepted_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $invite->id ) );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		Activity::log( 'team_joined', 'user', $user_id, $user->display_name );
		return $user_id;
	}

	/** Change role and individual permissions of a member. */
	public static function update_member( $user_id, $role, $caps ) {
		$check = Roles::require_cap( 'adc_manage_team' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! self::is_member( $user->ID ) || in_array( 'administrator', (array) $user->roles, true ) ) {
			return new \WP_Error( 'adc_team', __( 'Team member not found.', 'automotive-dealer-crm' ) );
		}
		if ( ! isset( Roles::roles()[ $role ] ) ) {
			return new \WP_Error( 'adc_team', __( 'Choose a role.', 'automotive-dealer-crm' ) );
		}
		$owners = self::owners();
		if ( in_array( 'adc_owner', (array) $user->roles, true ) && 'adc_owner' !== $role && count( $owners ) <= 1 ) {
			return new \WP_Error( 'adc_team', __( 'The dealership must keep at least one owner.', 'automotive-dealer-crm' ) );
		}
		$user->set_role( $role );
		foreach ( array_keys( Roles::capabilities() ) as $cap ) {
			$user->remove_cap( $cap );
		}
		$user = get_userdata( $user->ID );
		foreach ( self::clean_caps( $caps ) as $cap ) {
			$user->add_cap( $cap );
		}
		Activity::log( 'team_updated', 'user', $user->ID, $user->display_name . ' → ' . Roles::role_label( $role ) );
		return true;
	}

	/** Remove CRM access (the account is kept for history, without any role). */
	public static function deactivate( $user_id ) {
		$check = Roles::require_cap( 'adc_manage_team' );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || $user->ID === get_current_user_id() || in_array( 'administrator', (array) $user->roles, true ) ) {
			return new \WP_Error( 'adc_team', __( 'You cannot remove this account.', 'automotive-dealer-crm' ) );
		}
		if ( in_array( 'adc_owner', (array) $user->roles, true ) && count( self::owners() ) <= 1 ) {
			return new \WP_Error( 'adc_team', __( 'The dealership must keep at least one owner.', 'automotive-dealer-crm' ) );
		}
		$user->set_role( '' );
		foreach ( array_keys( Roles::capabilities() ) as $cap ) {
			$user->remove_cap( $cap );
		}
		\WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		Activity::log( 'team_removed', 'user', $user->ID, $user->display_name );
		return true;
	}
}
