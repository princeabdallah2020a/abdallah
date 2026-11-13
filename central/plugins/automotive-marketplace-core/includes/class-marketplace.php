<?php
/**
 * Public marketplace: URL routing, request resolution and template loading.
 *
 * Marketplace pages are "virtual" pages rendered from the custom tables:
 *   /cars/  /cars-for-sale/  /cars-for-rent/  /featured/
 *   /car/{slug}/  /dealers/  /dealer/{slug}/
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Marketplace {

	/** @var array|null Resolved context of the current marketplace request. */
	private static $context = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'wp', array( __CLASS__, 'resolve_request' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 50 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'disable_canonical_redirect' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function presets() {
		return array(
			'all'      => 'cars',
			'sale'     => 'cars-for-sale',
			'rent'     => 'cars-for-rent',
			'featured' => 'featured',
		);
	}

	public static function register_rewrites() {
		foreach ( self::presets() as $preset => $path ) {
			add_rewrite_rule( '^' . $path . '/?$', 'index.php?amc_view=catalog&amc_preset=' . $preset, 'top' );
		}
		add_rewrite_rule( '^car/([^/]+)/?$', 'index.php?amc_view=vehicle&amc_slug=$matches[1]', 'top' );
		add_rewrite_rule( '^dealers/?$', 'index.php?amc_view=dealers', 'top' );
		add_rewrite_rule( '^dealer/([^/]+)/?$', 'index.php?amc_view=dealer&amc_slug=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		return array_merge( $vars, array( 'amc_view', 'amc_preset', 'amc_slug' ) );
	}

	/** Current marketplace view name, or '' on regular WordPress pages. */
	public static function view() {
		return self::$context ? self::$context['view'] : '';
	}

	/**
	 * Value from the resolved request context.
	 *
	 * @param string $key vehicle|dealer|filters|results|preset|page|similar|dealers.
	 */
	public static function context( $key ) {
		return self::$context && isset( self::$context[ $key ] ) ? self::$context[ $key ] : null;
	}

	/**
	 * Resolve the request once the main query has run, and fix the query
	 * flags so WordPress neither treats the page as the blog nor as a 404.
	 */
	public static function resolve_request() {
		$view = get_query_var( 'amc_view' );
		if ( ! in_array( $view, array( 'catalog', 'vehicle', 'dealers', 'dealer' ), true ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only GET filters.
		$page    = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
		$context = array(
			'view' => $view,
			'page' => $page,
		);
		$found   = true;

		switch ( $view ) {
			case 'catalog':
				$preset = get_query_var( 'amc_preset' );
				$preset = isset( self::presets()[ $preset ] ) ? $preset : 'all';
				$filters            = self::apply_preset( Vehicles::sanitize_filters( $_GET ), $preset );
				$context['preset']  = $preset;
				$context['filters'] = $filters;
				$context['results'] = Vehicles::search( $filters, $page );
				break;

			case 'vehicle':
				$vehicle = Vehicles::get_by_slug( get_query_var( 'amc_slug' ) );
				if ( $vehicle ) {
					$context['vehicle'] = $vehicle;
					$context['dealer']  = Dealers::get_public_by_id( $vehicle->dealer_id );
					$context['similar'] = Vehicles::similar( $vehicle, 4 );
				}
				$found = (bool) $vehicle;
				break;

			case 'dealers':
				$per_page           = 24;
				$context['dealers'] = Dealers::search(
					array(
						'city'   => isset( $_GET['city'] ) ? sanitize_title( wp_unslash( $_GET['city'] ) ) : '',
						'q'      => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
						'limit'  => $per_page,
						'offset' => ( $page - 1 ) * $per_page,
					)
				);
				$context['pages']   = (int) ceil( $context['dealers']['total'] / $per_page );
				break;

			case 'dealer':
				$dealer = Dealers::get_by_slug( get_query_var( 'amc_slug' ) );
				if ( $dealer ) {
					$filters            = array_intersect_key( Vehicles::sanitize_filters( $_GET ), array( 'sort' => 1 ) );
					$filters['dealer']  = $dealer->slug;
					$context['dealer']  = $dealer;
					$context['filters'] = $filters;
					$context['results'] = Vehicles::search( $filters, $page );
				}
				$found = (bool) $dealer;
				break;
		}
		// phpcs:enable

		global $wp_query;
		if ( ! $found ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		self::$context        = $context;
		$wp_query->is_home    = false;
		$wp_query->is_archive = false;
		$wp_query->is_404     = false;
		status_header( 200 );
	}

	/**
	 * Force the filters implied by a catalog preset.
	 *
	 * @param array  $filters Clean filters.
	 * @param string $preset  all|sale|rent|featured.
	 */
	public static function apply_preset( array $filters, $preset ) {
		if ( 'sale' === $preset || 'rent' === $preset ) {
			$filters['type'] = $preset;
		} elseif ( 'featured' === $preset ) {
			$filters['featured'] = 1;
		}
		return $filters;
	}

	public static function template_include( $template ) {
		$view = self::view();
		if ( ! $view ) {
			return $template;
		}
		$found = self::locate( $view );
		return $found ? $found : $template;
	}

	/**
	 * Find a template: the theme may override any file by placing a copy in
	 * its "amc/" folder.
	 *
	 * @param string $name Template name without extension, e.g. "parts/vehicle-card".
	 */
	public static function locate( $name ) {
		$name  = preg_replace( '#[^a-z0-9/_-]#', '', $name );
		$theme = locate_template( 'amc/' . $name . '.php' );
		if ( $theme ) {
			return $theme;
		}
		$file = AMC_DIR . 'templates/' . $name . '.php';
		return is_readable( $file ) ? $file : '';
	}

	public static function disable_canonical_redirect( $redirect ) {
		return get_query_var( 'amc_view' ) ? false : $redirect;
	}

	public static function body_class( $classes ) {
		if ( self::view() ) {
			$classes[] = 'amc-page';
			$classes[] = 'amc-' . self::view();
		}
		return $classes;
	}

	public static function register_assets() {
		if ( ! wp_style_is( 'amds', 'registered' ) ) {
			wp_register_style( 'amds', AMC_URL . 'assets/css/design-system.css', array(), AMC_VERSION );
		}
		wp_register_style( 'amc-marketplace', AMC_URL . 'assets/css/marketplace.css', array( 'amds' ), AMC_VERSION );
		wp_register_script( 'amc-catalog', AMC_URL . 'assets/js/catalog.js', array(), AMC_VERSION, true );
		wp_register_script( 'amc-gallery', AMC_URL . 'assets/js/gallery.js', array(), AMC_VERSION, true );
	}

	public static function enqueue_assets() {
		// Vehicle cards and the search form are used on the home page too.
		wp_enqueue_style( 'amc-marketplace' );

		wp_enqueue_script( 'amc-catalog' );
		$brands = array();
		foreach ( Vehicles::facets()['brands'] as $slug => $brand ) {
			$brands[ $slug ] = $brand['models'];
		}
		wp_localize_script(
			'amc-catalog',
			'amcCatalog',
			array(
				'restUrl' => esc_url_raw( rest_url( 'marketplace/v1/catalog' ) ),
				'brands'  => $brands,
				'i18n'    => array(
					'allModels' => __( 'All models', 'automotive-marketplace-core' ),
					'error'     => __( 'Results could not be loaded. Please try again.', 'automotive-marketplace-core' ),
				),
			)
		);

		if ( 'vehicle' === self::view() ) {
			wp_enqueue_script( 'amc-gallery' );
		}
	}

	/**
	 * HTML of the results area (count, grid, pagination). Shared by the
	 * catalog template and the AJAX endpoint so both always render the same.
	 *
	 * @param array  $results  Output of Vehicles::search().
	 * @param array  $filters  Clean filters.
	 * @param string $base_url URL of the current view without query string.
	 * @param array  $query    User-chosen filters to carry in pagination links.
	 */
	public static function render_results( array $results, array $filters, $base_url, array $query = array() ) {
		ob_start();
		amc_get_template(
			'parts/results',
			array(
				'results'  => $results,
				'filters'  => $filters,
				'base_url' => $base_url,
				'query'    => $query,
			)
		);
		return ob_get_clean();
	}
}
