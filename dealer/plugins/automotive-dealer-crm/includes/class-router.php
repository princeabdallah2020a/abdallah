<?php
/**
 * Routes of the dealer website and of the CRM.
 *
 * Public:  /inventory/  /rentals/  /vehicle/{slug}/  /about/  /contact/  /financing/
 * CRM:     /manager/  /manager/{section}/  /manager/{section}/{id|new}/
 *          /manager/login/  /manager/invite/{token}/
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Router {

	private static $view    = '';
	private static $section = '';
	private static $item    = '';

	/** @var array Data prepared for the current public view. */
	public static $data = array();

	public static function sections() {
		return array(
			'dashboard' => array( __( 'Dashboard', 'automotive-dealer-crm' ), 'chart', 'adc_access' ),
			'vehicles'  => array( __( 'Vehicles', 'automotive-dealer-crm' ), 'car', 'adc_access' ),
			'leads'     => array( __( 'Leads', 'automotive-dealer-crm' ), 'flag', 'adc_access' ),
			'customers' => array( __( 'Customers', 'automotive-dealer-crm' ), 'users', 'adc_access' ),
			'sales'     => array( __( 'Sales', 'automotive-dealer-crm' ), 'tag', 'adc_access' ),
			'rentals'   => array( __( 'Rentals', 'automotive-dealer-crm' ), 'key', 'adc_access' ),
			'team'      => array( __( 'Team', 'automotive-dealer-crm' ), 'user', 'adc_manage_team' ),
			'settings'  => array( __( 'Settings', 'automotive-dealer-crm' ), 'gear', 'adc_manage_settings' ),
			'activity'  => array( __( 'Activity', 'automotive-dealer-crm' ), 'clock', 'adc_view_reports' ),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_action(
			'init',
			static function () {
				if ( get_option( 'adc_flush_rewrites' ) ) {
					delete_option( 'adc_flush_rewrites' );
					flush_rewrite_rules();
				}
			},
			99
		);
		add_filter(
			'query_vars',
			static function ( $vars ) {
				return array_merge( $vars, array( 'adc_view', 'adc_slug', 'adc_section', 'adc_item', 'adc_path' ) );
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 50 );
		add_filter( 'redirect_canonical', static function ( $url ) {
			return get_query_var( 'adc_view' ) ? false : $url;
		} );
		add_filter( 'body_class', static function ( $classes ) {
			if ( self::$view ) {
				$classes[] = 'adc-view-' . self::$view;
			}
			return $classes;
		} );
	}

	public static function register_rewrites() {
		add_rewrite_rule( '^inventory/?$', 'index.php?adc_view=inventory', 'top' );
		add_rewrite_rule( '^rentals/?$', 'index.php?adc_view=rentals', 'top' );
		add_rewrite_rule( '^vehicle/([^/]+)/?$', 'index.php?adc_view=vehicle&adc_slug=$matches[1]', 'top' );
		add_rewrite_rule( '^about/?$', 'index.php?adc_view=about', 'top' );
		add_rewrite_rule( '^contact/?$', 'index.php?adc_view=contact', 'top' );
		add_rewrite_rule( '^financing/?$', 'index.php?adc_view=financing', 'top' );
		add_rewrite_rule( '^manager/login/?$', 'index.php?adc_view=login', 'top' );
		add_rewrite_rule( '^manager/invite/([A-Za-z0-9]+)/?$', 'index.php?adc_view=invite&adc_item=$matches[1]', 'top' );
		add_rewrite_rule( '^manager/?$', 'index.php?adc_view=manager', 'top' );
		add_rewrite_rule( '^manager/([a-z-]+)/?$', 'index.php?adc_view=manager&adc_section=$matches[1]', 'top' );
		add_rewrite_rule( '^manager/([a-z-]+)/([a-z0-9-]+)/?$', 'index.php?adc_view=manager&adc_section=$matches[1]&adc_item=$matches[2]', 'top' );
	}

	public static function view() {
		return self::$view;
	}

	public static function section() {
		return self::$section;
	}

	public static function item() {
		return self::$item;
	}

	public static function is_manager() {
		return in_array( self::$view, array( 'manager', 'login', 'invite' ), true );
	}

	/** Without pretty permalinks, "?adc_path=manager/leads/3" is mapped to the same views. */
	private static function view_from_path() {
		$path = trim( (string) get_query_var( 'adc_path' ), '/' );
		if ( '' === $path ) {
			return;
		}
		$parts = explode( '/', $path );
		if ( 'vehicle' === $parts[0] && isset( $parts[1] ) ) {
			set_query_var( 'adc_view', 'vehicle' );
			set_query_var( 'adc_slug', $parts[1] );
		} elseif ( 'manager' === $parts[0] ) {
			if ( isset( $parts[1] ) && 'login' === $parts[1] ) {
				set_query_var( 'adc_view', 'login' );
			} elseif ( isset( $parts[1] ) && 'invite' === $parts[1] ) {
				set_query_var( 'adc_view', 'invite' );
				set_query_var( 'adc_item', isset( $parts[2] ) ? $parts[2] : '' );
			} else {
				set_query_var( 'adc_view', 'manager' );
				set_query_var( 'adc_section', isset( $parts[1] ) ? $parts[1] : '' );
				set_query_var( 'adc_item', isset( $parts[2] ) ? $parts[2] : '' );
			}
		} else {
			set_query_var( 'adc_view', sanitize_key( $parts[0] ) );
		}
	}

	public static function dispatch() {
		self::view_from_path();
		$view = (string) get_query_var( 'adc_view' );
		if ( ! in_array( $view, array( 'inventory', 'rentals', 'vehicle', 'about', 'contact', 'financing', 'manager', 'login', 'invite' ), true ) ) {
			return;
		}
		self::$view    = $view;
		self::$section = sanitize_key( (string) get_query_var( 'adc_section' ) );
		self::$item    = preg_replace( '/[^A-Za-z0-9-]/', '', (string) get_query_var( 'adc_item' ) );

		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		switch ( $view ) {
			case 'inventory':
			case 'rentals':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filters.
				$raw = $_GET;
				if ( 'rentals' === $view ) {
					if ( ! Settings::get( 'show_rentals' ) ) {
						return self::not_found();
					}
					$raw['type'] = 'rent';
				}
				$page        = isset( $raw['pg'] ) ? absint( $raw['pg'] ) : 1;
				self::$data  = Vehicles::search_public( $raw, $page, 12 );
				break;

			case 'vehicle':
				$vehicle = Vehicles::get_public( get_query_var( 'adc_slug' ) );
				if ( ! $vehicle ) {
					return self::not_found();
				}
				self::$data = array(
					'vehicle' => $vehicle,
					'media'   => Vehicles::media( $vehicle->id ),
					'similar' => Vehicles::search_public( array( 'type' => $vehicle->listing_type, 'body' => $vehicle->body_type ), 1, 5 )['items'],
				);
				break;

			case 'financing':
				if ( ! Settings::get( 'show_financing' ) ) {
					return self::not_found();
				}
				break;

			case 'login':
				nocache_headers();
				if ( is_user_logged_in() && current_user_can( 'adc_access' ) ) {
					wp_safe_redirect( adc_manager_url() );
					exit;
				}
				if ( $is_post ) {
					Manager::handle_login();
				}
				break;

			case 'invite':
				nocache_headers();
				if ( $is_post ) {
					Manager::handle_invite_acceptance( self::$item );
				}
				break;

			case 'manager':
				nocache_headers();
				if ( ! is_user_logged_in() ) {
					wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' ) ), adc_url( 'manager/login' ) ) );
					exit;
				}
				if ( ! current_user_can( 'adc_access' ) ) {
					wp_die( esc_html__( 'You do not have access to the CRM. Ask the dealership owner for an invitation.', 'automotive-dealer-crm' ), '', array( 'response' => 403 ) );
				}
				$sections = self::sections();
				if ( ! isset( $sections[ self::$section ] ) ) {
					self::$section = 'dashboard';
				}
				if ( ! current_user_can( $sections[ self::$section ][2] ) ) {
					self::$section = 'dashboard';
				}
				if ( $is_post ) {
					Manager::handle_post();
				}
				break;
		}
	}

	private static function not_found() {
		global $wp_query;
		self::$view = '';
		$wp_query->set_404();
		status_header( 404 );
	}

	public static function template_include( $template ) {
		if ( ! self::$view ) {
			return $template;
		}
		$file = self::locate( self::is_manager() ? 'manager/' . ( 'manager' === self::$view ? 'app' : self::$view ) : 'public/' . self::$view );
		return $file ? $file : $template;
	}

	/**
	 * Theme override: the theme may provide "adc/{name}.php".
	 *
	 * @param string $name Template name.
	 */
	public static function locate( $name ) {
		$name  = preg_replace( '#[^a-z0-9/_-]#', '', $name );
		$theme = locate_template( 'adc/' . $name . '.php' );
		if ( $theme ) {
			return $theme;
		}
		$file = ADC_DIR . 'templates/' . $name . '.php';
		return is_readable( $file ) ? $file : '';
	}
}
