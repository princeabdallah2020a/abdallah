<?php
/**
 * Dealer memberships: registration, applications and the account status
 * workflow (pending → under review → approved → active, plus suspended,
 * expired and cancelled).
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Memberships {

	public static function application_statuses() {
		return array(
			'submitted'    => __( 'Submitted', 'automotive-marketplace-core' ),
			'under_review' => __( 'Under review', 'automotive-marketplace-core' ),
			'approved'     => __( 'Approved', 'automotive-marketplace-core' ),
			'rejected'     => __( 'Rejected', 'automotive-marketplace-core' ),
		);
	}

	public static function website_options() {
		return array(
			'new_domain'      => __( 'I need a new domain name', 'automotive-marketplace-core' ),
			'existing_domain' => __( 'I already own a domain name', 'automotive-marketplace-core' ),
			'later'           => __( 'I will decide later', 'automotive-marketplace-core' ),
		);
	}

	public static function activities() {
		return array(
			'sale' => __( 'Vehicle sales', 'automotive-marketplace-core' ),
			'rent' => __( 'Vehicle rental', 'automotive-marketplace-core' ),
			'both' => __( 'Sales and rental', 'automotive-marketplace-core' ),
		);
	}

	public static function fleet_sizes() {
		return array(
			'1-10'   => __( '1 to 10 vehicles', 'automotive-marketplace-core' ),
			'11-40'  => __( '11 to 40 vehicles', 'automotive-marketplace-core' ),
			'41-100' => __( '41 to 100 vehicles', 'automotive-marketplace-core' ),
			'100+'   => __( 'More than 100 vehicles', 'automotive-marketplace-core' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Lookups                                                             */
	/* ------------------------------------------------------------------ */

	/** Dealership of the logged-in user (resolved server-side only). */
	public static function current_dealer() {
		return is_user_logged_in() ? self::dealer_for_user( get_current_user_id() ) : null;
	}

	public static function dealer_for_user( $user_id ) {
		global $wpdb;
		$table = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 1", absint( $user_id ) ) );
		return $row ? $row : null;
	}

	/** Full dealer record, including private fields. Staff or owner use only. */
	public static function get_dealer( $id ) {
		global $wpdb;
		$table = Database::table( 'dealers' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $id ) ) );
		return $row ? $row : null;
	}

	/**
	 * Staff dealer list.
	 *
	 * @param array $args { status, q, limit, offset }.
	 */
	public static function dealers( array $args = array() ) {
		global $wpdb;
		$table  = Database::table( 'dealers' );
		$where  = array( 'is_demo = 0' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['q'] ) ) {
			$where[]  = '(business_name LIKE %s OR city LIKE %s OR contact_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['q'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['include_demo'] ) ) {
			array_shift( $where );
			$where = $where ? $where : array( '1=1' );
		}
		$limit    = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		$sql      = "FROM $table WHERE " . implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total    = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) $sql", $params ) : "SELECT COUNT(*) $sql" );
		$items    = $wpdb->get_results( $wpdb->prepare( "SELECT * $sql ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $limit, $offset ) ) ) );
		// phpcs:enable
		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	public static function dealer_counts() {
		global $wpdb;
		$table = Database::table( 'dealers' );
		$out   = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM $table WHERE is_demo = 0 GROUP BY status" ) as $row ) {
			$out[ $row->status ] = (int) $row->n;
		}
		return $out;
	}

	public static function get_application( $id ) {
		global $wpdb;
		$a = Database::table( 'applications' );
		$d = Database::table( 'dealers' );
		$p = Database::table( 'plans' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT a.*, d.business_name, d.city, d.status AS dealer_status, p.name AS plan_name FROM $a a LEFT JOIN $d d ON d.id = a.dealer_id LEFT JOIN $p p ON p.id = a.plan_id WHERE a.id = %d", absint( $id ) ) );
	}

	public static function latest_application( $dealer_id ) {
		global $wpdb;
		$table = Database::table( 'applications' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE dealer_id = %d ORDER BY id DESC LIMIT 1", absint( $dealer_id ) ) );
	}

	public static function applications( $status = '', $limit = 100 ) {
		global $wpdb;
		$a     = Database::table( 'applications' );
		$d     = Database::table( 'dealers' );
		$p     = Database::table( 'plans' );
		$where = $status ? $wpdb->prepare( 'WHERE a.status = %s', $status ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.*, d.business_name, d.city, p.name AS plan_name FROM $a a LEFT JOIN $d d ON d.id = a.dealer_id LEFT JOIN $p p ON p.id = a.plan_id $where ORDER BY a.id DESC LIMIT %d", absint( $limit ) ) );
		return $rows ? $rows : array();
	}

	public static function application_counts() {
		global $wpdb;
		$table = Database::table( 'applications' );
		$out   = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM $table GROUP BY status" ) as $row ) {
			$out[ $row->status ] = (int) $row->n;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Registration                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Process the registration form. Redirects on success.
	 *
	 * @return array Field errors (field => message), "form" for general errors.
	 */
	public static function handle_registration() {
		if ( ! Settings::get( 'registration_open' ) ) {
			return array( 'form' => __( 'Registrations are temporarily closed. Please contact us.', 'automotive-marketplace-core' ) );
		}
		if ( ! isset( $_POST['_amc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_amc_nonce'] ) ), 'amc_register' ) ) {
			return array( 'form' => __( 'Your session has expired. Please submit the form again.', 'automotive-marketplace-core' ) );
		}
		if ( ! empty( $_POST['company_url'] ) ) {
			return array( 'form' => __( 'Your application could not be sent.', 'automotive-marketplace-core' ) );
		}
		if ( ! Rate_Limiter::allow( 'register', 5, HOUR_IN_SECONDS ) ) {
			return array( 'form' => __( 'Too many attempts. Please try again later.', 'automotive-marketplace-core' ) );
		}

		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$text   = static function ( $key, $max = 191 ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? mb_substr( sanitize_text_field( (string) $in[ $key ] ), 0, $max ) : '';
		};
		$errors = array();
		$logged = is_user_logged_in();

		$plan   = Plans::get_by_code( $text( 'plan', 40 ) );
		$months = absint( $text( 'months', 3 ) );
		if ( ! $plan || ! $plan->is_active ) {
			$errors['plan'] = __( 'Choose a plan.', 'automotive-marketplace-core' );
		}
		if ( ! isset( Plans::durations()[ $months ] ) ) {
			$errors['months'] = __( 'Choose a duration.', 'automotive-marketplace-core' );
		}

		$name     = $text( 'full_name', 120 );
		$email    = sanitize_email( $text( 'email' ) );
		$password = isset( $in['password'] ) && is_string( $in['password'] ) ? $in['password'] : '';
		if ( ! $logged ) {
			if ( '' === $name ) {
				$errors['full_name'] = __( 'Enter your full name.', 'automotive-marketplace-core' );
			}
			if ( ! is_email( $email ) ) {
				$errors['email'] = __( 'Enter a valid email address.', 'automotive-marketplace-core' );
			} elseif ( email_exists( $email ) ) {
				$errors['email'] = __( 'An account already exists with this email. Log in first, then come back to this page.', 'automotive-marketplace-core' );
			}
			if ( mb_strlen( $password ) < 8 ) {
				$errors['password'] = __( 'Choose a password of at least 8 characters.', 'automotive-marketplace-core' );
			}
		} else {
			$user  = wp_get_current_user();
			$email = $user->user_email;
			$name  = $name ? $name : $user->display_name;
		}

		$phone    = amc_normalize_phone( $text( 'phone', 32 ) );
		$whatsapp = amc_normalize_phone( $text( 'whatsapp', 32 ) );
		$business = $text( 'business_name' );
		$city     = $text( 'city', 100 );
		$rccm     = $text( 'registration_number', 80 );
		$ninea    = $text( 'tax_id', 80 );
		if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
			$errors['phone'] = __( 'Enter a valid phone number.', 'automotive-marketplace-core' );
		}
		if ( '' === $business ) {
			$errors['business_name'] = __( 'Enter the name of your dealership.', 'automotive-marketplace-core' );
		}
		if ( '' === $city ) {
			$errors['city'] = __( 'Enter your city.', 'automotive-marketplace-core' );
		}
		if ( '' === $rccm && '' === $ninea ) {
			$errors['registration_number'] = __( 'Enter at least your RCCM or NINEA number.', 'automotive-marketplace-core' );
		}
		$activity = isset( self::activities()[ $text( 'activity', 10 ) ] ) ? $text( 'activity', 10 ) : 'sale';
		$fleet    = isset( self::fleet_sizes()[ $text( 'fleet_size', 20 ) ] ) ? $text( 'fleet_size', 20 ) : '';

		$website_option = isset( self::website_options()[ $text( 'website_option', 20 ) ] ) ? $text( 'website_option', 20 ) : 'later';
		$domain         = self::clean_domain( $text( 'domain' ) );
		if ( 'later' !== $website_option && '' === $domain ) {
			$errors['domain'] = __( 'Enter a valid domain name, for example mydealership.com.', 'automotive-marketplace-core' );
		}

		$method = $text( 'payment_method', 30 );
		if ( 'later' !== $method && ! isset( Payments::providers()[ $method ] ) ) {
			$errors['payment_method'] = __( 'Choose a payment method.', 'automotive-marketplace-core' );
		}

		$doc_fields = array(
			'doc_rccm'  => 'rccm',
			'doc_ninea' => 'ninea',
			'doc_id'    => 'id',
		);
		$has_doc    = false;
		foreach ( array_merge( array_keys( $doc_fields ), array( 'payment_proof' ) ) as $field ) {
			if ( Documents::has_upload( $field ) ) {
				$check = Documents::validate( $field );
				if ( is_wp_error( $check ) ) {
					$errors[ $field ] = $check->get_error_message();
				} elseif ( 'payment_proof' !== $field ) {
					$has_doc = true;
				}
			}
		}
		if ( Settings::get( 'require_documents' ) && ! $has_doc && ! isset( $errors['doc_rccm'] ) ) {
			$errors['doc_rccm'] = __( 'Attach at least one business document.', 'automotive-marketplace-core' );
		}
		if ( empty( $in['accept_terms'] ) ) {
			$errors['accept_terms'] = __( 'Please accept the terms to continue.', 'automotive-marketplace-core' );
		}

		if ( $errors ) {
			return $errors;
		}

		// 1. Account.
		if ( $logged ) {
			$user_id = get_current_user_id();
			if ( self::dealer_for_user( $user_id ) ) {
				return array( 'form' => __( 'Your account already has a dealership.', 'automotive-marketplace-core' ) );
			}
			if ( ! Permissions::is_staff( $user_id ) ) {
				wp_get_current_user()->add_role( 'amc_dealer_owner' );
			}
		} else {
			$user_id = wp_insert_user(
				array(
					'user_login'   => self::unique_login( $email ),
					'user_email'   => $email,
					'user_pass'    => $password,
					'display_name' => $name,
					'first_name'   => $name,
					'role'         => 'amc_dealer_owner',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				return array( 'form' => $user_id->get_error_message() );
			}
		}

		// 2. Dealership (private until active).
		$dealer_id = Dealers::create(
			array(
				'business_name' => $business,
				'description'   => isset( $in['description'] ) ? mb_substr( sanitize_textarea_field( (string) $in['description'] ), 0, 2000 ) : '',
				'address'       => $text( 'address', 255 ),
				'city'          => $city,
				'phone'         => $phone,
				'whatsapp'      => $whatsapp ? $whatsapp : $phone,
				'contact_email' => $email,
				'status'        => 'pending',
				'sync_mode'     => 'api',
			)
		);
		if ( is_wp_error( $dealer_id ) ) {
			return array( 'form' => $dealer_id->get_error_message() );
		}
		global $wpdb;
		$wpdb->update( Database::table( 'dealers' ), array( 'user_id' => $user_id ), array( 'id' => $dealer_id ) );

		// 3. Application.
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Database::table( 'applications' ),
			array(
				'dealer_id'           => $dealer_id,
				'user_id'             => $user_id,
				'plan_id'             => (int) $plan->id,
				'duration_months'     => $months,
				'legal_name'          => $text( 'legal_name' ),
				'registration_number' => $rccm,
				'tax_id'              => $ninea,
				'contact_name'        => $name,
				'contact_phone'       => $phone,
				'activity'            => $activity,
				'fleet_size'          => $fleet,
				'website_option'      => $website_option,
				'domain'              => $domain,
				'notes'               => isset( $in['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $in['notes'] ), 0, 2000 ) : '',
				'status'              => 'submitted',
				'created_at'          => $now,
				'updated_at'          => $now,
			)
		);
		$application_id = (int) $wpdb->insert_id;

		// 4. Documents, subscription order, optional payment declaration.
		foreach ( $doc_fields as $field => $type ) {
			Documents::store( $field, $dealer_id, $type, $application_id );
		}
		$subscription_id = Subscriptions::create( $dealer_id, $plan->id, $months, $application_id );
		if ( 'later' !== $method && ! is_wp_error( $subscription_id ) ) {
			$proof = Documents::store( 'payment_proof', $dealer_id, 'payment_proof', $application_id );
			Payments::create( $dealer_id, $subscription_id, $method, $text( 'payer_reference', 120 ), $proof );
		}

		Audit::log( 'application.submitted', 'application', $application_id, array( 'dealer' => $dealer_id ) );
		Notifications::application_received( $dealer_id );

		if ( ! $logged ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true, is_ssl() );
		}
		wp_safe_redirect( Portal::url( 'account', 'overview', 0, array( 'welcome' => 1 ) ) );
		exit;
	}

	private static function unique_login( $email ) {
		$base  = sanitize_user( strstr( $email, '@', true ), true );
		$base  = $base ? substr( $base, 0, 40 ) : 'dealer';
		$login = $base;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $i++;
		}
		return $login;
	}

	/** "https://www.My-Dealer.com/path" → "my-dealer.com" ('' if invalid). */
	public static function clean_domain( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '#^[a-z]+://#', '', $value );
		$value = preg_replace( '#^www\.#', '', $value );
		$value = preg_replace( '#[/?\#].*$#', '', $value );
		return preg_match( '/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $value ) ? $value : '';
	}

	/* ------------------------------------------------------------------ */
	/* Workflow                                                            */
	/* ------------------------------------------------------------------ */

	public static function set_dealer_status( $dealer_id, $status, $reason = '' ) {
		global $wpdb;
		if ( ! in_array( $status, Catalog::dealer_statuses(), true ) ) {
			return false;
		}
		$wpdb->update(
			Database::table( 'dealers' ),
			array(
				'status'        => $status,
				'status_reason' => mb_substr( sanitize_text_field( $reason ), 0, 255 ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $dealer_id ) )
		);
		Vehicles::flush_cache();
		Audit::log( 'dealer.status.' . $status, 'dealer', $dealer_id, $reason ? array( 'reason' => $reason ) : array() );
		do_action( 'amc_dealer_changed', absint( $dealer_id ) );
		return true;
	}

	/**
	 * Bring the dealer status in line with application + subscription:
	 * approved application and active subscription → active; active dealer
	 * without subscription → expired. Manual suspensions and cancellations
	 * are never overridden here.
	 */
	public static function maybe_activate_dealer( $dealer_id ) {
		$dealer = self::get_dealer( $dealer_id );
		if ( ! $dealer || in_array( $dealer->status, array( 'suspended', 'cancelled' ), true ) ) {
			return;
		}
		$app      = self::latest_application( $dealer_id );
		$approved = ( $app && 'approved' === $app->status ) || in_array( $dealer->status, array( 'active', 'expired' ), true );
		$current  = Subscriptions::current_for_dealer( $dealer_id );

		if ( $approved && $current && 'active' !== $dealer->status ) {
			self::set_dealer_status( $dealer_id, 'active' );
		} elseif ( ! $current && 'active' === $dealer->status ) {
			self::set_dealer_status( $dealer_id, 'expired' );
		}
	}

	private static function update_application( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( Database::table( 'applications' ), $data, array( 'id' => absint( $id ) ) );
	}

	public static function start_review( $id ) {
		$app = self::get_application( $id );
		if ( ! $app || 'submitted' !== $app->status ) {
			return new \WP_Error( 'amc_app', __( 'This application is not waiting for review.', 'automotive-marketplace-core' ) );
		}
		self::update_application( $id, array( 'status' => 'under_review' ) );
		self::set_dealer_status( $app->dealer_id, 'under_review' );
		Audit::log( 'application.under_review', 'application', $id );
		return true;
	}

	public static function approve( $id ) {
		$app = self::get_application( $id );
		if ( ! $app || ! in_array( $app->status, array( 'submitted', 'under_review' ), true ) ) {
			return new \WP_Error( 'amc_app', __( 'This application has already been decided.', 'automotive-marketplace-core' ) );
		}
		self::update_application(
			$id,
			array(
				'status'      => 'approved',
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql', true ),
			)
		);
		self::set_dealer_status( $app->dealer_id, 'approved' );
		Provisioning::create_job( $app->dealer_id, $app->website_option, $app->domain );

		// A payment verified before approval starts now.
		$open = Subscriptions::open_for_dealer( $app->dealer_id );
		if ( $open && 'paid' === $open->status ) {
			Subscriptions::activate( $open->id );
		}
		self::maybe_activate_dealer( $app->dealer_id );

		Audit::log( 'application.approved', 'application', $id );
		Notifications::application_decision( $app->dealer_id, true );
		return true;
	}

	public static function reject( $id, $reason ) {
		$app = self::get_application( $id );
		if ( ! $app || ! in_array( $app->status, array( 'submitted', 'under_review' ), true ) ) {
			return new \WP_Error( 'amc_app', __( 'This application has already been decided.', 'automotive-marketplace-core' ) );
		}
		$reason = mb_substr( sanitize_text_field( $reason ), 0, 500 );
		self::update_application(
			$id,
			array(
				'status'          => 'rejected',
				'decision_reason' => $reason,
				'reviewed_by'     => get_current_user_id(),
				'reviewed_at'     => current_time( 'mysql', true ),
			)
		);
		self::set_dealer_status( $app->dealer_id, 'cancelled', $reason );
		$open = Subscriptions::open_for_dealer( $app->dealer_id );
		if ( $open && 'pending_payment' === $open->status ) {
			Subscriptions::cancel( $open->id );
		}
		Audit::log( 'application.rejected', 'application', $id );
		Notifications::application_decision( $app->dealer_id, false, $reason );
		return true;
	}

	/** Steps shown on the dealer's progress timeline. */
	public static function timeline( $dealer ) {
		$app     = self::latest_application( $dealer->id );
		$job     = Provisioning::for_dealer( $dealer->id );
		$current = Subscriptions::current_for_dealer( $dealer->id );
		$paid    = $current || ( ( $open = Subscriptions::open_for_dealer( $dealer->id ) ) && 'paid' === $open->status );
		$app_ok  = $app && 'approved' === $app->status;

		return array(
			array( 'label' => __( 'Application sent', 'automotive-marketplace-core' ), 'done' => (bool) $app ),
			array( 'label' => __( 'Review by our team', 'automotive-marketplace-core' ), 'done' => $app_ok, 'current' => $app && in_array( $app->status, array( 'submitted', 'under_review' ), true ) ),
			array( 'label' => __( 'Payment confirmed', 'automotive-marketplace-core' ), 'done' => $paid ),
			array( 'label' => __( 'Website and CRM set up', 'automotive-marketplace-core' ), 'done' => $job && 'done' === $job->status, 'current' => $job && 'done' !== $job->status ),
			array( 'label' => __( 'Online on the marketplace', 'automotive-marketplace-core' ), 'done' => 'active' === $dealer->status ),
		);
	}
}
