<?php
/**
 * Public routes for the marketplace views: /vehicules/, /vehicule/{slug}/, /concessionnaire/{slug}/.
 * They are virtual (no posts): the theme marks the request with `wotoyi_view` and picks the matching block template.
 * Disable with add_filter( 'wotoyi_theme_routes', '__return_false' ) if the plugin ever registers its own routes.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

const WOTOYI_ROUTES_VERSION = '1';

add_action(
	'init',
	static function (): void {
		if ( ! apply_filters( 'wotoyi_theme_routes', true ) ) {
			return;
		}
		add_rewrite_rule( '^vehicules/?$', 'index.php?wotoyi_view=search', 'top' );
		add_rewrite_rule( '^vehicule/([^/]+)/?$', 'index.php?wotoyi_view=vehicle&wotoyi_ref=$matches[1]', 'top' );
		add_rewrite_rule( '^concessionnaire/([^/]+)/?$', 'index.php?wotoyi_view=dealer&wotoyi_ref=$matches[1]', 'top' );
	}
);

add_filter(
	'query_vars',
	static function ( array $v ): array {
		$v[] = 'wotoyi_ref';
		return $v;
	}
);

/** Flush once per routes version (rules are stored in the database). */
add_action(
	'admin_init',
	static function (): void {
		if ( get_option( 'wotoyi_routes_version' ) !== WOTOYI_ROUTES_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'wotoyi_routes_version', WOTOYI_ROUTES_VERSION, true );
		}
	}
);
add_action( 'after_switch_theme', static fn() => delete_option( 'wotoyi_routes_version' ) );

/** Template for virtual routes (works in block themes: the names are templates/*.html). */
foreach ( array( 'front_page', 'home' ) as $wotoyi_type ) {
	add_filter(
		$wotoyi_type . '_template_hierarchy',
		static function ( array $t ): array {
			switch ( (string) get_query_var( 'wotoyi_view' ) ) {
				case 'search':
					return array( 'archive-wotoyi_vehicle' );
				case 'vehicle':
					return array( 'single-wotoyi_vehicle' );
				case 'dealer':
					return array( 'single-wotoyi_dealer' );
			}
			return $t;
		}
	);
}
unset( $wotoyi_type );

/** Light main query on virtual routes (no post loop needed). */
add_action(
	'pre_get_posts',
	static function ( \WP_Query $q ): void {
		if ( $q->is_main_query() && '' !== (string) $q->get( 'wotoyi_view' ) ) {
			$q->set( 'posts_per_page', 1 );
			$q->set( 'no_found_rows', true );
			$q->set( 'ignore_sticky_posts', true );
		}
	}
);

/** Unknown vehicle/dealer => real 404 (only when the plugin is active, so a missing plugin shows the empty state instead). */
add_action(
	'template_redirect',
	static function (): void {
		$view = (string) get_query_var( 'wotoyi_view' );
		$ref  = (string) get_query_var( 'wotoyi_ref' );
		if ( ! in_array( $view, array( 'vehicle', 'dealer' ), true ) || ! wotoyi_marketplace_active() ) {
			return;
		}
		$found = 'vehicle' === $view ? wotoyi_mp_vehicle( $ref ) : wotoyi_mp_dealer( $ref );
		if ( null === $found ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	1
);

/** Document titles for virtual routes. */
add_filter(
	'pre_get_document_title',
	static function ( $title ) {
		$view = (string) get_query_var( 'wotoyi_view' );
		$ref  = (string) get_query_var( 'wotoyi_ref' );
		$site = get_bloginfo( 'name' );
		if ( 'search' === $view ) {
			return __( 'Véhicules à vendre et à louer', 'wotoyi' ) . ' - ' . $site;
		}
		if ( 'vehicle' === $view && ( $v = wotoyi_mp_vehicle( $ref ) ) ) {
			return (string) $v['title'] . ' - ' . $site;
		}
		if ( 'dealer' === $view && ( $d = wotoyi_mp_dealer( $ref ) ) ) {
			return (string) $d['name'] . ' - ' . $site;
		}
		return $title;
	}
);

/** Default dealer URL used by dealer cards. */
add_filter(
	'wotoyi_theme_dealer_url',
	static function ( string $url, array $d ): string {
		if ( '' !== $url || empty( $d['slug'] ) ) {
			return $url;
		}
		return home_url( '/concessionnaire/' . rawurlencode( (string) $d['slug'] ) . '/' );
	},
	10,
	2
);
