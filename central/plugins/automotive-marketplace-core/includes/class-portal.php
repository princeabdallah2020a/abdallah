<?php
/**
 * Membership portal: public pages (pricing, registration, login) and the two
 * applications — the dealer space (/dealer-dashboard/) and the platform
 * administration (/platform-admin/), both separate from wp-admin.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Portal {

	const PATHS = array(
		'pricing'  => 'pricing',
		'register' => 'dealer-registration',
		'login'    => 'dealer-login',
		'account'  => 'dealer-dashboard',
		'platform' => 'platform-admin',
	);

	private static $view    = '';
	private static $section = '';
	private static $item    = 0;

	/** @var array Form errors and submitted values to re-display. */
	public static $errors = array();
	public static $old    = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter(
			'query_vars',
			static function ( $vars ) {
				return array_merge( $vars, array( 'amc_section', 'amc_item' ) );
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 60 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function register_rewrites() {
		add_rewrite_rule( '^pricing/?$', 'index.php?amc_view=pricing', 'top' );
		add_rewrite_rule( '^dealer-registration/?$', 'index.php?amc_view=register', 'top' );
		add_rewrite_rule( '^dealer-login/?$', 'index.php?amc_view=login', 'top' );
		foreach ( array( 'account' => 'dealer-dashboard', 'platform' => 'platform-admin' ) as $view => $path ) {
			add_rewrite_rule( '^' . $path . '/?$', 'index.php?amc_view=' . $view, 'top' );
			add_rewrite_rule( '^' . $path . '/([a-z-]+)/?$', 'index.php?amc_view=' . $view . '&amc_section=$matches[1]', 'top' );
			add_rewrite_rule( '^' . $path . '/([a-z-]+)/(\d+)/?$', 'index.php?amc_view=' . $view . '&amc_section=$matches[1]&amc_item=$matches[2]', 'top' );
		}
		add_rewrite_rule( '^dealer-onboarding/?$', 'index.php?amc_view=account&amc_section=onboarding', 'top' );
		add_rewrite_rule( '^subscription-checkout/?$', 'index.php?amc_view=account&amc_section=checkout', 'top' );
		add_rewrite_rule( '^application-status/?$', 'index.php?amc_view=account&amc_section=overview', 'top' );
	}

	/** Rewrite rules are flushed once after an update that adds routes. */
	public static function maybe_flush() {
		if ( get_option( 'amc_flush_rewrites' ) ) {
			delete_option( 'amc_flush_rewrites' );
			flush_rewrite_rules();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Navigation                                                          */
	/* ------------------------------------------------------------------ */

	public static function account_sections() {
		return array(
			'overview'     => array( __( 'Dashboard', 'automotive-marketplace-core' ), 'chart' ),
			'onboarding'   => array( __( 'Getting started', 'automotive-marketplace-core' ), 'check' ),
			'subscription' => array( __( 'Subscription', 'automotive-marketplace-core' ), 'star' ),
			'payments'     => array( __( 'Payments', 'automotive-marketplace-core' ), 'calendar' ),
			'website'      => array( __( 'My website', 'automotive-marketplace-core' ), 'globe' ),
			'profile'      => array( __( 'Public profile', 'automotive-marketplace-core' ), 'store' ),
			'documents'    => array( __( 'Documents', 'automotive-marketplace-core' ), 'shield' ),
			'support'      => array( __( 'Support', 'automotive-marketplace-core' ), 'chat' ),
		);
	}

	public static function platform_sections() {
		static $counts = null;
		if ( null === $counts ) {
			$apps   = Memberships::application_counts();
			$counts = array(
				'applications'  => ( isset( $apps['submitted'] ) ? $apps['submitted'] : 0 ) + ( isset( $apps['under_review'] ) ? $apps['under_review'] : 0 ),
				'payments'      => Payments::pending_count(),
				'websites'      => Provisioning::open_count(),
				'support'       => Support::open_count(),
				'connections'   => Credentials::problem_count(),
			);
		}
		$count = static function ( $key ) use ( $counts ) {
			return isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
		};
		return array(
			'overview'      => array( __( 'Overview', 'automotive-marketplace-core' ), 'chart', 0 ),
			'reports'       => array( __( 'Reports', 'automotive-marketplace-core' ), 'gauge', 0 ),
			'applications'  => array( __( 'Applications', 'automotive-marketplace-core' ), 'mail', $count( 'applications' ) ),
			'dealers'       => array( __( 'Dealers', 'automotive-marketplace-core' ), 'store', 0 ),
			'subscriptions' => array( __( 'Subscriptions', 'automotive-marketplace-core' ), 'star', 0 ),
			'payments'      => array( __( 'Payments', 'automotive-marketplace-core' ), 'calendar', $count( 'payments' ) ),
			'websites'      => array( __( 'Dealer websites', 'automotive-marketplace-core' ), 'globe', $count( 'websites' ) ),
			'listings'      => array( __( 'Listings', 'automotive-marketplace-core' ), 'car', 0 ),
			'connections'   => array( __( 'Connections', 'automotive-marketplace-core' ), 'sync', $count( 'connections' ) ),
			'plans'         => array( __( 'Plans and prices', 'automotive-marketplace-core' ), 'filter', 0 ),
			'support'       => array( __( 'Support', 'automotive-marketplace-core' ), 'chat', $count( 'support' ) ),
			'assistant'     => array( __( 'AI assistant', 'automotive-marketplace-core' ), 'sparkle', 0 ),
			'channels'      => array( __( 'WhatsApp & voice', 'automotive-marketplace-core' ), 'phone', 0 ),
			'ai'            => array( __( 'AI usage', 'automotive-marketplace-core' ), 'gauge', 0 ),
			'audit'         => array( __( 'Audit log', 'automotive-marketplace-core' ), 'clock', 0 ),
			'settings'      => array( __( 'Settings', 'automotive-marketplace-core' ), 'gearbox', 0 ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Request handling                                                    */
	/* ------------------------------------------------------------------ */

	public static function view() {
		return self::$view;
	}

	public static function section() {
		return self::$section;
	}

	public static function item() {
		return self::$item;
	}

	public static function is_app() {
		return in_array( self::$view, array( 'account', 'platform' ), true );
	}

	public static function dispatch() {
		$view = (string) get_query_var( 'amc_view' );
		if ( ! isset( self::PATHS[ $view ] ) ) {
			return;
		}
		self::$view    = $view;
		self::$section = sanitize_key( (string) get_query_var( 'amc_section' ) );
		self::$item    = absint( get_query_var( 'amc_item' ) );

		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
		if ( 'pricing' !== $view ) {
			nocache_headers();
		}
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		switch ( $view ) {
			case 'login':
				if ( is_user_logged_in() ) {
					wp_safe_redirect( self::home_for_user() );
					exit;
				}
				if ( $is_post ) {
					self::handle_login();
				}
				break;

			case 'register':
				if ( $is_post ) {
					self::$errors = Memberships::handle_registration(); // Redirects on success.
					self::$old    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
					unset( self::$old['password'] );
				} elseif ( Memberships::current_dealer() ) {
					wp_safe_redirect( self::url( 'account' ) );
					exit;
				}
				break;

			case 'account':
				if ( ! is_user_logged_in() ) {
					wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( self::current_url() ), self::url( 'login' ) ) );
					exit;
				}
				$dealer = Memberships::current_dealer();
				if ( ! $dealer ) {
					wp_safe_redirect( Permissions::is_staff() ? self::url( 'platform' ) : self::url( 'register' ) );
					exit;
				}
				if ( 'checkout' !== self::$section && ! isset( self::account_sections()[ self::$section ] ) ) {
					self::$section = 'overview';
				}
				if ( $is_post ) {
					self::handle_post( 'account', $dealer );
				}
				break;

			case 'platform':
				if ( ! is_user_logged_in() ) {
					auth_redirect();
				}
				if ( ! Permissions::is_staff() ) {
					wp_die( esc_html__( 'You are not allowed to do this.', 'automotive-marketplace-core' ), '', array( 'response' => 403 ) );
				}
				if ( ! isset( self::platform_sections()[ self::$section ] ) ) {
					self::$section = 'overview';
				}
				if ( $is_post ) {
					self::handle_post( 'platform' );
				}
				break;
		}
	}

	private static function handle_login() {
		if ( ! isset( $_POST['_amc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_amc_nonce'] ) ), 'amc_login' ) ) {
			self::$errors = array( 'form' => __( 'Your session has expired. Please try again.', 'automotive-marketplace-core' ) );
			return;
		}
		if ( ! Rate_Limiter::allow( 'login', 10, 15 * MINUTE_IN_SECONDS ) ) {
			self::$errors = array( 'form' => __( 'Too many attempts. Please try again later.', 'automotive-marketplace-core' ) );
			return;
		}
		$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$user  = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are not sanitised.
				'remember'      => ! empty( $_POST['remember'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			self::$errors = array( 'form' => __( 'Incorrect email or password.', 'automotive-marketplace-core' ) );
			self::$old    = array( 'log' => $login );
			return;
		}
		$to = isset( $_REQUEST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' ) : '';
		wp_safe_redirect( $to ? $to : self::home_for_user( $user ) );
		exit;
	}

	private static function handle_post( $area, $dealer = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['amc_action'] ) ? sanitize_key( wp_unslash( $_POST['amc_action'] ) ) : '';
		$nonce  = isset( $_POST['_amc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_amc_nonce'] ) ) : '';
		// phpcs:enable
		if ( '' === $action || ! wp_verify_nonce( $nonce, 'amc_' . $action ) ) {
			self::flash( 'error', __( 'Your session has expired. Please try again.', 'automotive-marketplace-core' ) );
			self::back();
		}
		$result = 'account' === $area ? Dealer_Account::handle( $action, $dealer ) : Platform_Admin::handle( $action );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
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

	private static function back() {
		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : self::url( self::$view, self::$section, self::$item ) );
		exit;
	}

	public static function home_for_user( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( user_can( $user, Permissions::STAFF ) ) {
			return self::url( 'platform' );
		}
		if ( self::dealer_exists( $user->ID ) ) {
			return self::url( 'account' );
		}
		return user_can( $user, Permissions::DEALER ) ? self::url( 'register' ) : home_url( '/' );
	}

	private static function dealer_exists( $user_id ) {
		return (bool) Memberships::dealer_for_user( $user_id );
	}

	private static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $uri );
	}

	public static function template_include( $template ) {
		if ( ! self::$view ) {
			return $template;
		}
		$file = Marketplace::locate( 'portal/' . self::$view );
		return $file ? $file : $template;
	}

	public static function title( $parts ) {
		if ( ! self::$view ) {
			return $parts;
		}
		$titles = array(
			'pricing'  => __( 'Plans and prices for dealerships', 'automotive-marketplace-core' ),
			'register' => __( 'Dealer registration', 'automotive-marketplace-core' ),
			'login'    => __( 'Dealer login', 'automotive-marketplace-core' ),
			'account'  => __( 'Dealer space', 'automotive-marketplace-core' ),
			'platform' => __( 'Platform admin', 'automotive-marketplace-core' ),
		);
		$parts['title'] = $titles[ self::$view ];
		$sections       = 'platform' === self::$view ? self::platform_sections() : ( 'account' === self::$view ? self::account_sections() : array() );
		if ( isset( $sections[ self::$section ] ) ) {
			$parts['title'] = $sections[ self::$section ][0] . ' – ' . $parts['title'];
		}
		unset( $parts['tagline'] );
		return $parts;
	}

	public static function robots( $robots ) {
		if ( self::$view && 'pricing' !== self::$view && 'register' !== self::$view ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	public static function body_class( $classes ) {
		if ( self::$view ) {
			$classes[] = 'amc-portal';
			$classes[] = 'amc-portal--' . self::$view;
		}
		return $classes;
	}

	public static function enqueue() {
		if ( ! self::$view ) {
			return;
		}
		wp_enqueue_style( 'amc-portal', AMC_URL . 'assets/css/portal.css', array( 'amds' ), AMC_VERSION );
		wp_enqueue_script( 'amc-portal', AMC_URL . 'assets/js/portal.js', array(), AMC_VERSION, true );
		wp_localize_script(
			'amc-portal',
			'amcPortal',
			array(
				'i18n' => array(
					'confirm'  => __( 'Please confirm this action.', 'automotive-marketplace-core' ),
					'required' => __( 'Please complete the required fields of this step.', 'automotive-marketplace-core' ),
				),
			)
		);
		if ( 'platform' === self::$view ) {
			Assistant_Ui::enqueue_for( 'admin' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Helpers for templates                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * URL of a portal page.
	 *
	 * @param string $view    pricing|register|login|account|platform.
	 * @param string $section Section slug.
	 * @param int    $item    Item id.
	 * @param array  $args    Query arguments.
	 */
	public static function url( $view, $section = '', $item = 0, array $args = array() ) {
		$view = isset( self::PATHS[ $view ] ) ? $view : 'account';
		if ( 'overview' === $section && ! $item ) {
			$section = '';
		}
		if ( get_option( 'permalink_structure' ) ) {
			$path = self::PATHS[ $view ] . ( $section ? '/' . $section : '' ) . ( $item ? '/' . absint( $item ) : '' );
			$url  = home_url( user_trailingslashit( $path ) );
		} else {
			$url = add_query_arg(
				array_filter(
					array(
						'amc_view'    => $view,
						'amc_section' => $section,
						'amc_item'    => $item ? absint( $item ) : '',
					)
				),
				home_url( '/' )
			);
		}
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/** Hidden fields for a portal form action. */
	public static function action_fields( $action ) {
		echo '<input type="hidden" name="amc_action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( 'amc_' . $action, '_amc_nonce', false );
	}

	private static function flash_key() {
		return 'amc_flash_' . md5( get_current_user_id() . '|' . Rate_Limiter::ip_hash() );
	}

	public static function flash( $type, $message ) {
		set_transient( self::flash_key(), array( 'type' => $type, 'message' => $message ), 120 );
	}

	public static function render_flash() {
		$flash = get_transient( self::flash_key() );
		if ( $flash ) {
			delete_transient( self::flash_key() );
			echo '<div class="amds-alert amds-alert--' . esc_attr( 'error' === $flash['type'] ? 'error' : 'success' ) . '" role="' . ( 'error' === $flash['type'] ? 'alert' : 'status' ) . '">' . esc_html( $flash['message'] ) . '</div>';
		}
	}

	public static function status_label( $status ) {
		$labels = array(
			'pending'         => __( 'Pending', 'automotive-marketplace-core' ),
			'under_review'    => __( 'Under review', 'automotive-marketplace-core' ),
			'approved'        => __( 'Approved', 'automotive-marketplace-core' ),
			'active'          => __( 'Active', 'automotive-marketplace-core' ),
			'suspended'       => __( 'Suspended', 'automotive-marketplace-core' ),
			'expired'         => __( 'Expired', 'automotive-marketplace-core' ),
			'cancelled'       => __( 'Cancelled', 'automotive-marketplace-core' ),
			'submitted'       => __( 'Submitted', 'automotive-marketplace-core' ),
			'rejected'        => __( 'Rejected', 'automotive-marketplace-core' ),
			'pending_payment' => __( 'Awaiting payment', 'automotive-marketplace-core' ),
			'paid'            => __( 'Paid, starts at approval', 'automotive-marketplace-core' ),
			'verified'        => __( 'Confirmed', 'automotive-marketplace-core' ),
			'queued'          => __( 'To do', 'automotive-marketplace-core' ),
			'in_progress'     => __( 'In progress', 'automotive-marketplace-core' ),
			'done'            => __( 'Completed', 'automotive-marketplace-core' ),
			'open'            => __( 'Open', 'automotive-marketplace-core' ),
			'answered'        => __( 'Answered', 'automotive-marketplace-core' ),
			'closed'          => __( 'Closed', 'automotive-marketplace-core' ),
			'grace'           => __( 'Grace period', 'automotive-marketplace-core' ),
			'reserved'        => __( 'Reserved', 'automotive-marketplace-core' ),
			'sold'            => __( 'Sold', 'automotive-marketplace-core' ),
			'rented'          => __( 'Rented', 'automotive-marketplace-core' ),
			'unpublished'     => __( 'Hidden', 'automotive-marketplace-core' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	public static function badge( $status ) {
		$tones = array(
			'success' => array( 'active', 'approved', 'verified', 'done', 'paid' ),
			'warning' => array( 'pending', 'submitted', 'pending_payment', 'queued', 'open', 'grace', 'reserved' ),
			'info'    => array( 'under_review', 'in_progress', 'answered' ),
			'danger'  => array( 'suspended', 'rejected', 'expired', 'cancelled' ),
		);
		$tone = 'neutral';
		foreach ( $tones as $name => $list ) {
			if ( in_array( $status, $list, true ) ) {
				$tone = $name;
				break;
			}
		}
		return '<span class="amds-badge amds-badge--' . esc_attr( $tone ) . '">' . esc_html( self::status_label( $status ) ) . '</span>';
	}

	public static function date( $utc, $with_time = false ) {
		if ( ! $utc ) {
			return '—';
		}
		$format = get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' );
		return wp_date( $format, strtotime( $utc . ' UTC' ) );
	}

	public static function old( $key, $default = '' ) {
		return isset( self::$old[ $key ] ) && is_scalar( self::$old[ $key ] ) ? (string) self::$old[ $key ] : $default;
	}

	public static function error( $key ) {
		return isset( self::$errors[ $key ] ) ? '<p class="amc-field-error" role="alert">' . esc_html( self::$errors[ $key ] ) . '</p>' : '';
	}

	/** Page heading inside the app shell. */
	public static function page_head( $title, $lead = '', $actions = '' ) {
		echo '<div class="amc-app__head"><div><h1 class="amc-app__title">' . esc_html( $title ) . '</h1>';
		if ( $lead ) {
			echo '<p class="amc-app__lead">' . esc_html( $lead ) . '</p>';
		}
		echo '</div>';
		if ( $actions ) {
			echo '<div class="amc-app__actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts by callers.
		}
		echo '</div>';
	}

	/** Opening markup of the application shell (dealer space / platform admin). */
	public static function shell_open( $area ) {
		$sections = 'platform' === $area ? self::platform_sections() : self::account_sections();
		$user     = wp_get_current_user();
		$dealer   = 'account' === $area ? Memberships::current_dealer() : null;
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'amc-app-body' ); ?>>
<?php wp_body_open(); ?>
<div class="amc-app amc-app--<?php echo esc_attr( $area ); ?>">
	<aside class="amc-app__side" id="amc-app-nav">
		<a class="amc-app__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="amc-app__mark" aria-hidden="true"></span><?php bloginfo( 'name' ); ?></a>
		<p class="amc-app__area"><?php echo esc_html( 'platform' === $area ? __( 'Platform admin', 'automotive-marketplace-core' ) : __( 'Dealer space', 'automotive-marketplace-core' ) ); ?></p>
		<?php if ( $dealer ) : ?>
			<div class="amc-app__account">
				<strong><?php echo esc_html( $dealer->business_name ); ?></strong>
				<?php echo self::badge( $dealer->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
		<nav aria-label="<?php esc_attr_e( 'Sections', 'automotive-marketplace-core' ); ?>">
			<ul class="amc-app__nav">
				<?php foreach ( $sections as $key => $section ) : ?>
					<?php $active = self::$section === $key || ( 'checkout' === self::$section && 'subscription' === $key ); ?>
					<li>
						<a href="<?php echo esc_url( self::url( $area, $key ) ); ?>"<?php echo $active ? ' class="is-active" aria-current="page"' : ''; ?>>
							<?php echo amc_icon( $section[1], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php echo esc_html( $section[0] ); ?></span>
							<?php if ( ! empty( $section[2] ) ) : ?>
								<span class="amc-app__count"><?php echo esc_html( $section[2] ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div class="amc-app__side-foot">
			<span class="amc-app__user"><?php echo esc_html( $user->display_name ); ?></span>
			<?php if ( 'platform' === $area && current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'WordPress admin', 'automotive-marketplace-core' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View the website', 'automotive-marketplace-core' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'automotive-marketplace-core' ); ?></a>
		</div>
	</aside>
	<div class="amc-app__main">
		<header class="amc-app__top">
			<button class="amc-app__menu" type="button" data-amc-app-menu aria-controls="amc-app-nav" aria-expanded="false"><?php echo amc_icon( 'menu', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'automotive-marketplace-core' ); ?></span></button>
			<a class="amc-app__brand amc-app__brand--top" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="amc-app__mark" aria-hidden="true"></span><?php bloginfo( 'name' ); ?></a>
		</header>
		<main id="main" class="amc-app__content">
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
