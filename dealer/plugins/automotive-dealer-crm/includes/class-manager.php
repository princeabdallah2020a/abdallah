<?php
/**
 * CRM controller: login, invitation acceptance, form actions (nonce +
 * capability on every action) and the application shell.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Manager {

	/** @var array Errors and old values for forms rendered without redirect. */
	public static $errors = array();
	public static $old    = array();

	/* ------------------------------------------------------------------ */
	/* Authentication                                                      */
	/* ------------------------------------------------------------------ */

	private static function throttle( $bucket, $limit, $window ) {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'adc_rl_' . md5( $bucket . '|' . $ip . '|' . floor( time() / $window ) );
		$hits = (int) get_transient( $key );
		if ( $hits >= $limit ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window );
		return true;
	}

	public static function handle_login() {
		if ( ! isset( $_POST['_adc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adc_nonce'] ) ), 'adc_login' ) ) {
			self::$errors['form'] = __( 'Your session has expired. Please try again.', 'automotive-dealer-crm' );
			return;
		}
		if ( ! self::throttle( 'login', 10, 15 * MINUTE_IN_SECONDS ) ) {
			self::$errors['form'] = __( 'Too many attempts. Please try again later.', 'automotive-dealer-crm' );
			return;
		}
		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$user  = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				'remember'      => ! empty( $_POST['remember'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			self::$errors['form'] = __( 'Incorrect email or password.', 'automotive-dealer-crm' );
			self::$old['log']     = $login;
			return;
		}
		$to = isset( $_REQUEST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' ) : '';
		wp_safe_redirect( $to ? $to : adc_manager_url() );
		exit;
	}

	public static function handle_invite_acceptance( $token ) {
		if ( ! isset( $_POST['_adc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adc_nonce'] ) ), 'adc_invite' ) ) {
			self::$errors['form'] = __( 'Your session has expired. Please try again.', 'automotive-dealer-crm' );
			return;
		}
		$result = Team::accept_invite(
			$token,
			isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		);
		if ( is_wp_error( $result ) ) {
			self::$errors['form'] = $result->get_error_message();
			return;
		}
		wp_safe_redirect( adc_manager_url() );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	public static function handle_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['adc_action'] ) ? sanitize_key( wp_unslash( $_POST['adc_action'] ) ) : '';
		$nonce  = isset( $_POST['_adc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_adc_nonce'] ) ) : '';
		// phpcs:enable
		if ( '' === $action || ! wp_verify_nonce( $nonce, 'adc_' . $action ) ) {
			self::flash( 'error', __( 'Your session has expired. Please try again.', 'automotive-dealer-crm' ) );
			self::back();
		}
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$result = self::run( $action, $in );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['redirect'] ) ) {
				wp_safe_redirect( $data['redirect'] );
				exit;
			}
		} elseif ( is_array( $result ) ) {
			if ( ! empty( $result['message'] ) ) {
				self::flash( 'success', $result['message'] );
			}
			if ( ! empty( $result['redirect'] ) ) {
				wp_safe_redirect( $result['redirect'] );
				exit;
			}
		}
		self::back();
	}

	/**
	 * @return array|\WP_Error { message, redirect }
	 */
	private static function run( $action, array $in ) {
		$id   = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		$done = static function ( $result, $message, $redirect = '' ) {
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'message' => $message, 'redirect' => $redirect );
		};
		$need = static function ( $cap ) {
			return current_user_can( $cap ) ? null : new \WP_Error( 'adc_forbidden', __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
		};

		switch ( $action ) {
			// Vehicles.
			case 'vehicle_save':
				$saved = Vehicles::save( $in, $id );
				if ( is_wp_error( $saved ) && 'adc_partial' === $saved->get_error_code() ) {
					$data = $saved->get_error_data();
					return new \WP_Error( 'adc_partial', __( 'Vehicle saved, but some photos were not added:', 'automotive-dealer-crm' ) . ' ' . $saved->get_error_message(), array( 'redirect' => adc_manager_url( 'vehicles', $data['id'] ) ) );
				}
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				$vehicle_id = $saved;
				$vehicle = Vehicles::get( $vehicle_id );
				$message = 'pending_review' === $vehicle->status ? __( 'Vehicle saved and sent for review to a manager.', 'automotive-dealer-crm' ) : __( 'Vehicle saved.', 'automotive-dealer-crm' );
				return array( 'message' => $message, 'redirect' => adc_manager_url( 'vehicles', $vehicle_id ) );
			case 'vehicle_status':
				return $done( Vehicles::set_status( $id, sanitize_key( isset( $in['status'] ) ? $in['status'] : '' ) ), __( 'Status updated.', 'automotive-dealer-crm' ) );
			case 'vehicle_delete':
				return $done( Vehicles::delete( $id ), __( 'Vehicle deleted.', 'automotive-dealer-crm' ), adc_manager_url( 'vehicles' ) );

			// Leads.
			case 'lead_save':
				if ( $error = $need( 'adc_manage_leads' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				$lead_id = Leads::save( $in, $id );
				return $done( $lead_id, __( 'Lead saved.', 'automotive-dealer-crm' ), is_wp_error( $lead_id ) ? '' : adc_manager_url( 'leads', $lead_id ) );
			case 'lead_status':
				if ( $error = $need( 'adc_manage_leads' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				return $done( Leads::set_status( $id, sanitize_key( isset( $in['status'] ) ? $in['status'] : '' ), isset( $in['reason'] ) ? (string) $in['reason'] : '' ), __( 'Lead updated.', 'automotive-dealer-crm' ) );
			case 'lead_assign':
				return $done( Leads::assign( $id, absint( isset( $in['assigned_to'] ) ? $in['assigned_to'] : 0 ) ), __( 'Lead assigned.', 'automotive-dealer-crm' ) );
			case 'lead_note':
				if ( $error = $need( 'adc_manage_leads' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				if ( ! Leads::get( $id ) ) {
					return new \WP_Error( 'adc_lead', __( 'Lead not found.', 'automotive-dealer-crm' ) );
				}
				Leads::note( $id, isset( $in['kind'] ) ? sanitize_key( $in['kind'] ) : 'note', isset( $in['body'] ) ? (string) $in['body'] : '' );
				if ( isset( $in['follow_up_at'] ) && '' !== $in['follow_up_at'] ) {
					global $wpdb;
					$wpdb->update( Database::table( 'leads' ), array( 'follow_up_at' => adc_utc_from_local( $in['follow_up_at'] ) ? adc_utc_from_local( $in['follow_up_at'] ) : null ), array( 'id' => $id ) );
				}
				return array( 'message' => __( 'Note added.', 'automotive-dealer-crm' ) );
			case 'lead_convert':
				$customer_id = Leads::convert( $id );
				return $done( $customer_id, __( 'Customer record created.', 'automotive-dealer-crm' ), is_wp_error( $customer_id ) ? '' : adc_manager_url( 'customers', $customer_id ) );
			case 'appointment_create':
				return $done( Appointments::create( $in ), __( 'Appointment scheduled.', 'automotive-dealer-crm' ) );
			case 'appointment_status':
				if ( $error = $need( 'adc_manage_leads' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				Appointments::set_status( $id, sanitize_key( isset( $in['status'] ) ? $in['status'] : '' ) );
				return array( 'message' => __( 'Appointment updated.', 'automotive-dealer-crm' ) );

			// Customers.
			case 'customer_save':
				if ( $error = $need( 'adc_manage_customers' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				$customer_id = Customers::save( $in, $id );
				return $done( $customer_id, __( 'Customer saved.', 'automotive-dealer-crm' ), is_wp_error( $customer_id ) ? '' : adc_manager_url( 'customers', $customer_id ) );
			case 'customer_file':
				if ( $error = $need( 'adc_manage_customers' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				$file = Files::store( 'document', array( 'customer_id' => $id ), isset( $in['label'] ) ? (string) $in['label'] : '' );
				if ( null === $file ) {
					return new \WP_Error( 'adc_upload', __( 'Choose a file to upload.', 'automotive-dealer-crm' ) );
				}
				return $done( $file, __( 'Document added.', 'automotive-dealer-crm' ) );

			// Sales.
			case 'sale_create':
				$sale_id = Sales::create( $in );
				return $done( $sale_id, ! empty( $in['confirm'] ) ? __( 'Sale confirmed. The vehicle is now marked as sold.', 'automotive-dealer-crm' ) : __( 'Sale recorded as draft. The vehicle is reserved.', 'automotive-dealer-crm' ), is_wp_error( $sale_id ) ? '' : adc_manager_url( 'sales', $sale_id ) );
			case 'sale_confirm':
				return $done( Sales::confirm( $id ), __( 'Sale confirmed. The vehicle is now marked as sold.', 'automotive-dealer-crm' ) );
			case 'sale_cancel':
				return $done( Sales::cancel( $id ), __( 'Sale cancelled. The vehicle is back on sale.', 'automotive-dealer-crm' ) );
			case 'sale_payment':
				return $done( Sales::update_payment( $id, sanitize_key( isset( $in['payment_status'] ) ? $in['payment_status'] : '' ), isset( $in['amount_paid'] ) ? $in['amount_paid'] : 0 ), __( 'Payment updated.', 'automotive-dealer-crm' ) );

			// Rentals.
			case 'rental_create':
				$rental_id = Rentals::create( $in );
				if ( is_wp_error( $rental_id ) && 'adc_rental_saved' === $rental_id->get_error_code() ) {
					$data = $rental_id->get_error_data();
					return new \WP_Error( 'adc_rental_saved', $rental_id->get_error_message(), array( 'redirect' => adc_manager_url( 'rentals', $data['id'] ) ) );
				}
				return $done( $rental_id, ! empty( $in['confirm'] ) ? __( 'Rental confirmed.', 'automotive-dealer-crm' ) : __( 'Quote saved.', 'automotive-dealer-crm' ), is_wp_error( $rental_id ) ? '' : adc_manager_url( 'rentals', $rental_id ) );
			case 'rental_confirm':
				return $done( Rentals::confirm( $id ), __( 'Rental confirmed.', 'automotive-dealer-crm' ) );
			case 'rental_start':
				return $done( Rentals::start( $id, isset( $in['mileage'] ) ? $in['mileage'] : 0 ), __( 'Vehicle handed over.', 'automotive-dealer-crm' ) );
			case 'rental_finish':
				return $done( Rentals::finish( $id, isset( $in['mileage'] ) ? $in['mileage'] : 0, isset( $in['return_notes'] ) ? $in['return_notes'] : '' ), __( 'Vehicle returned.', 'automotive-dealer-crm' ) );
			case 'rental_cancel':
				return $done( Rentals::cancel( $id ), __( 'Rental cancelled.', 'automotive-dealer-crm' ) );
			case 'rental_payment':
				return $done( Rentals::update_payment( $id, sanitize_key( isset( $in['payment_status'] ) ? $in['payment_status'] : '' ) ), __( 'Payment updated.', 'automotive-dealer-crm' ) );

			// Team.
			case 'team_invite':
				return $done( Team::invite( $in ), __( 'Invitation sent by email.', 'automotive-dealer-crm' ) );
			case 'team_update':
				return $done( Team::update_member( $id, sanitize_key( isset( $in['role'] ) ? $in['role'] : '' ), isset( $in['caps'] ) ? (array) $in['caps'] : array() ), __( 'Permissions updated.', 'automotive-dealer-crm' ) );
			case 'team_remove':
				return $done( Team::deactivate( $id ), __( 'Access removed.', 'automotive-dealer-crm' ) );
			case 'invite_revoke':
				return $done( Team::revoke_invite( $id ), __( 'Invitation cancelled.', 'automotive-dealer-crm' ) );

			// Settings.
			case 'settings_save':
				if ( $error = $need( 'adc_manage_settings' ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
					return $error;
				}
				foreach ( array( 'logo' => 'logo_id', 'hero_image' => 'hero_image_id' ) as $field => $key ) {
					$uploaded = self::upload_image( $field );
					if ( is_wp_error( $uploaded ) ) {
						return $uploaded;
					}
					if ( $uploaded ) {
						$in[ $key ] = $uploaded;
					}
					if ( ! empty( $in[ 'remove_' . $field ] ) ) {
						$in[ $key ] = 0;
					}
				}
				Settings::save( $in );
				Activity::log( 'settings_updated', 'settings', 0 );
				return array( 'message' => __( 'Settings saved. Your website is updated.', 'automotive-dealer-crm' ) );
		}

		return new \WP_Error( 'adc_action', __( 'Unknown action.', 'automotive-dealer-crm' ) );
	}

	/** Upload one image into the media library. @return int|\WP_Error|0 */
	private static function upload_image( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_FILES[ $field ]['error'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES[ $field ]['error'] ) {
			return 0;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type = wp_check_filetype( sanitize_file_name( (string) $_FILES[ $field ]['name'] ) )['type'];
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new \WP_Error( 'adc_image', __( 'Use a JPG, PNG or WEBP image.', 'automotive-dealer-crm' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		return media_handle_upload( $field, 0 );
	}

	/* ------------------------------------------------------------------ */
	/* Flash messages and navigation                                       */
	/* ------------------------------------------------------------------ */

	private static function flash_key() {
		return 'adc_flash_' . get_current_user_id();
	}

	public static function flash( $type, $message ) {
		set_transient( self::flash_key(), array( 'type' => $type, 'message' => $message ), 120 );
	}

	public static function render_flash() {
		$flash = get_transient( self::flash_key() );
		if ( $flash ) {
			delete_transient( self::flash_key() );
			echo '<div class="amds-alert amds-alert--' . ( 'error' === $flash['type'] ? 'error' : 'success' ) . '" role="' . ( 'error' === $flash['type'] ? 'alert' : 'status' ) . '">' . esc_html( $flash['message'] ) . '</div>';
		}
	}

	private static function back() {
		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : adc_manager_url( Router::section() ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Rendering helpers                                                   */
	/* ------------------------------------------------------------------ */

	public static function action_fields( $action, $id = 0 ) {
		echo '<input type="hidden" name="adc_action" value="' . esc_attr( $action ) . '">';
		if ( $id ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
		}
		wp_nonce_field( 'adc_' . $action, '_adc_nonce', false );
	}

	/** One-button form. */
	public static function button( $action, $id, $label, $class = 'amds-btn--secondary', array $extra = array(), $confirm = false ) {
		echo '<form method="post" class="adc-inline"' . ( $confirm ? ' data-adc-confirm' : '' ) . '>';
		self::action_fields( $action, $id );
		foreach ( $extra as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '<button class="amds-btn ' . esc_attr( $class ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	public static function head( $title, $lead = '', $actions = '' ) {
		echo '<div class="adc-head"><div><h1 class="adc-head__title">' . esc_html( $title ) . '</h1>';
		if ( $lead ) {
			echo '<p class="adc-head__lead">' . esc_html( $lead ) . '</p>';
		}
		echo '</div>' . ( $actions ? '<div class="adc-head__actions">' . $actions . '</div>' : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by callers from escaped parts.
	}

	public static function link_button( $url, $label, $class = 'amds-btn--primary', $icon = '' ) {
		return '<a class="amds-btn ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . ( $icon ? adc_icon( $icon, 16 ) : '' ) . esc_html( $label ) . '</a>';
	}

	/**
	 * Form field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param string $type  text|number|email|tel|date|datetime-local|textarea|select|file|url|color.
	 * @param array  $opts  options (select), attrs, help.
	 */
	public static function field( $name, $label, $value = '', $type = 'text', array $opts = array() ) {
		$id    = 'adc-f-' . preg_replace( '/[^a-z0-9_-]/', '-', strtolower( $name ) );
		$attrs = '';
		foreach ( isset( $opts['attrs'] ) ? $opts['attrs'] : array() as $key => $val ) {
			$attrs .= ' ' . esc_attr( $key ) . ( true === $val ? '' : '="' . esc_attr( $val ) . '"' );
		}
		echo '<div class="amds-field"><label class="amds-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'textarea' === $type ) {
			echo '<textarea class="amds-textarea" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attrs . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'select' === $type ) {
			echo '<select class="amds-select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( $opts['options'] as $key => $text ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input class="amds-input" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( 'file' === $type ? '' : ' value="' . esc_attr( (string) $value ) . '"' ) . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! empty( $opts['help'] ) ) {
			echo '<p class="amds-help">' . esc_html( $opts['help'] ) . '</p>';
		}
		echo '</div>';
	}

	/** Customer picker with quick creation (sales and rentals). */
	public static function customer_picker( $selected = 0, array $defaults = array() ) {
		$customers = Customers::search( '', 500 );
		echo '<fieldset class="amds-fieldset adc-customer-picker" data-adc-customer-picker><legend class="amds-label">' . esc_html__( 'Customer', 'automotive-dealer-crm' ) . '</legend>';
		echo '<select class="amds-select" name="customer_id" data-adc-customer-select><option value="">' . esc_html__( '+ New customer', 'automotive-dealer-crm' ) . '</option>';
		foreach ( $customers as $customer ) {
			echo '<option value="' . esc_attr( $customer->id ) . '"' . selected( (int) $selected, (int) $customer->id, false ) . '>' . esc_html( $customer->name . ( $customer->phone ? ' — ' . $customer->phone : '' ) ) . '</option>';
		}
		echo '</select><div class="amds-form__row" data-adc-new-customer>';
		self::field( 'new_customer_name', __( 'Full name', 'automotive-dealer-crm' ), isset( $defaults['name'] ) ? $defaults['name'] : '' );
		self::field( 'new_customer_phone', __( 'Phone', 'automotive-dealer-crm' ), isset( $defaults['phone'] ) ? $defaults['phone'] : '', 'tel' );
		self::field( 'new_customer_id_number', __( 'ID / licence number', 'automotive-dealer-crm' ) );
		echo '</div></fieldset>';
	}

	public static function user_name( $user_id ) {
		$user = $user_id ? get_userdata( (int) $user_id ) : null;
		return $user ? $user->display_name : '—';
	}

	public static function shell_open() {
		$business = adc_business();
		$user     = wp_get_current_user();
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( Router::sections()[ Router::section() ][0] . ' – ' . $business->name ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="adc-app-body">
<div class="adc-app">
	<aside class="adc-side" id="adc-side">
		<a class="adc-side__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( $business->logo ) : ?>
				<img src="<?php echo esc_url( $business->logo ); ?>" alt="" width="32" height="32">
			<?php else : ?>
				<span class="adc-side__mark"><?php echo esc_html( adc_initials( $business->name ) ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( $business->name ); ?></span>
		</a>
		<p class="adc-side__area"><?php esc_html_e( 'Dealer CRM', 'automotive-dealer-crm' ); ?></p>
		<a class="amds-btn amds-btn--accent adc-side__new" href="<?php echo esc_url( adc_manager_url( current_user_can( 'adc_manage_vehicles' ) ? 'vehicles' : 'leads', 'new' ) ); ?>"><?php echo adc_icon( 'plus', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( current_user_can( 'adc_manage_vehicles' ) ? __( 'Add a vehicle', 'automotive-dealer-crm' ) : __( 'New lead', 'automotive-dealer-crm' ) ); ?></a>
		<nav aria-label="<?php esc_attr_e( 'CRM sections', 'automotive-dealer-crm' ); ?>">
			<ul class="adc-nav">
				<?php foreach ( Router::sections() as $key => $section ) : ?>
					<?php
					if ( ! current_user_can( $section[2] ) ) {
						continue;
					}
					$active = Router::section() === $key;
					$count  = 'leads' === $key ? Leads::counts_by_status()['new'] : 0;
					?>
					<li><a href="<?php echo esc_url( adc_manager_url( 'dashboard' === $key ? '' : $key ) ); ?>"<?php echo $active ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo adc_icon( $section[1], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $section[0] ); ?></span><?php echo $count ? '<span class="adc-nav__count">' . esc_html( $count ) . '</span>' : ''; ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div class="adc-side__foot">
			<span class="adc-side__user"><?php echo esc_html( $user->display_name ); ?><small><?php echo esc_html( Roles::role_label( Roles::role_of( $user ) ) ); ?></small></span>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View my website', 'automotive-dealer-crm' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'My password', 'automotive-dealer-crm' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( adc_url( 'manager/login' ) ) ); ?>"><?php esc_html_e( 'Log out', 'automotive-dealer-crm' ); ?></a>
		</div>
	</aside>
	<div class="adc-main">
		<header class="adc-top">
			<button class="adc-top__menu" type="button" data-adc-menu aria-controls="adc-side" aria-expanded="false"><?php echo adc_icon( 'menu', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'automotive-dealer-crm' ); ?></span></button>
			<span class="adc-top__name"><?php echo esc_html( $business->name ); ?></span>
		</header>
		<main id="main" class="adc-content">
			<?php self::render_flash(); ?>
		<?php
	}

	public static function shell_close() {
		?>
		</main>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
		<?php
	}
}
