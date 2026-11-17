<?php
/**
 * Data adapter between the theme and the wotoyi-marketplace plugin.
 *
 * The theme never queries the database itself. Every function returns an empty result when the plugin is inactive or throws,
 * so templates degrade to a branded empty state. Any result can be replaced with the filter `wotoyi_theme_data_{name}`
 * (return a non-null value) - used by tests and by other plugins.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

/** Two-letter UI language the plugin understands (fr, en, ar, wo); defaults to fr. */
function wotoyi_lang(): string {
	$l = strtolower( substr( (string) determine_locale(), 0, 2 ) );
	return in_array( $l, array( 'fr', 'en', 'ar', 'wo' ), true ) ? $l : 'fr';
}

/** @return mixed|null Filter override or null. */
function wotoyi_data_override( string $name, array $args ) {
	return apply_filters( 'wotoyi_theme_data_' . $name, null, $args );
}

/** Runs a plugin call safely. */
function wotoyi_mp_try( callable $fn, $fallback ) {
	if ( ! wotoyi_marketplace_active() || ! class_exists( '\\Wotoyi\\Marketplace\\Plugin' ) ) {
		return $fallback;
	}
	try {
		return $fn();
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'wotoyi theme: marketplace call failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $fallback;
	}
}

/** @return array{items:array,total:int,page:int,pages:int,per_page:int,sort:string} */
function wotoyi_mp_search( array $params ): array {
	$empty = array( 'items' => array(), 'total' => 0, 'page' => 1, 'pages' => 0, 'per_page' => 12, 'sort' => 'relevance' );
	$o     = wotoyi_data_override( 'search', $params );
	if ( is_array( $o ) ) {
		return $o + $empty;
	}
	$r = wotoyi_mp_try( static fn() => \Wotoyi\Marketplace\Plugin::search()->search( $params ), $empty );
	return is_array( $r ) ? $r + $empty : $empty;
}

/** @return array{facets:array,ranges:array} */
function wotoyi_mp_facets( array $params = array() ): array {
	$empty = array( 'facets' => array(), 'ranges' => array() );
	$o     = wotoyi_data_override( 'facets', $params );
	if ( is_array( $o ) ) {
		return $o + $empty;
	}
	$r = wotoyi_mp_try( static fn() => \Wotoyi\Marketplace\Plugin::search()->facets( $params ), $empty );
	return is_array( $r ) ? $r + $empty : $empty;
}

function wotoyi_mp_vehicle( string $ref ): ?array {
	static $cache = array();
	$ref = strtolower( trim( $ref ) );
	if ( '' === $ref ) {
		return null;
	}
	if ( array_key_exists( $ref, $cache ) ) {
		return $cache[ $ref ];
	}
	$o = wotoyi_data_override( 'vehicle', array( $ref ) );
	$v = is_array( $o ) ? $o : wotoyi_mp_try( static fn() => \Wotoyi\Marketplace\Plugin::search()->vehicle_detail( $ref, wotoyi_lang() ), null );
	$cache[ $ref ] = is_array( $v ) ? $v : null;
	return $cache[ $ref ];
}

/** @return array{items:array,total:int} dealers in the shape of DealerPresenter::profile plus `url`. */
function wotoyi_mp_dealers( array $f = array() ): array {
	$empty = array( 'items' => array(), 'total' => 0 );
	$o     = wotoyi_data_override( 'dealers', $f );
	if ( is_array( $o ) ) {
		return $o + $empty;
	}
	$r = wotoyi_mp_try(
		static function () use ( $f ) {
			$res = \Wotoyi\Marketplace\Plugin::dealers()->public_list( $f );
			return array(
				'items' => array_map( array( '\\Wotoyi\\Marketplace\\Presenter\\DealerPresenter', 'profile' ), (array) $res['items'] ),
				'total' => (int) $res['total'],
			);
		},
		$empty
	);
	return is_array( $r ) ? $r : $empty;
}

function wotoyi_mp_dealer( string $ref ): ?array {
	static $cache = array();
	$ref = strtolower( trim( $ref ) );
	if ( '' === $ref ) {
		return null;
	}
	if ( array_key_exists( $ref, $cache ) ) {
		return $cache[ $ref ];
	}
	$o = wotoyi_data_override( 'dealer', array( $ref ) );
	if ( is_array( $o ) ) {
		$cache[ $ref ] = $o;
		return $o;
	}
	$d = wotoyi_mp_try(
		static function () use ( $ref ) {
			$row = \Wotoyi\Marketplace\Plugin::dealers()->public_by_ref( $ref );
			return $row ? \Wotoyi\Marketplace\Presenter\DealerPresenter::profile( $row ) : null;
		},
		null
	);
	$cache[ $ref ] = is_array( $d ) ? $d : null;
	return $cache[ $ref ];
}

/** @return array{items:array,total:int} */
function wotoyi_mp_services( array $params = array() ): array {
	$empty = array( 'items' => array(), 'total' => 0 );
	$o     = wotoyi_data_override( 'services', $params );
	if ( is_array( $o ) ) {
		return $o + $empty;
	}
	$r = wotoyi_mp_try( static fn() => \Wotoyi\Marketplace\Plugin::search()->search_services( $params ), $empty );
	return is_array( $r ) ? $r + $empty : $empty;
}

/** Keep only plain scalar GET params (arrays allowed one level for multi-selects). Plugin parsing does the real validation. */
function wotoyi_request_params(): array {
	$out = array();
	foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public search filters.
		$k = sanitize_key( (string) $k );
		if ( '' === $k ) {
			continue;
		}
		if ( is_array( $v ) ) {
			$out[ $k ] = array_values( array_filter( array_map( static fn( $x ) => is_scalar( $x ) ? sanitize_text_field( wp_unslash( (string) $x ) ) : '', $v ) ) );
		} elseif ( is_scalar( $v ) ) {
			$out[ $k ] = sanitize_text_field( wp_unslash( (string) $v ) );
		}
	}
	if ( ! isset( $out['q'] ) && isset( $out['s'] ) ) {
		$out['q'] = $out['s']; // native WordPress search (?s=) feeds the marketplace keyword search.
	}
	unset( $out['s'] );
	return $out;
}
