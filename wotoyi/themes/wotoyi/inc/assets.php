<?php
/**
 * Assets: inline critical CSS, per-template CSS/JS, font preloads, deferred scripts.
 *
 * Home:    critical (tokens + fonts + base + hero) inlined; chrome/cards/home CSS loaded asynchronously (below the fold).
 * Others:  critical (tokens + fonts + base) inlined; chrome + page CSS render-blocking (small) so there is no layout shift.
 * JS:      always `defer`, in footer; page scripts only on their template. No third-party runtime (Lenis is intentionally not bundled yet).
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

/** Absolute path + URL of a built bundle (assets/dist), falling back to the source file with the same name. */
function wotoyi_bundle( string $name, string $type ): array {
	$dist = 'assets/dist/' . $name . '.' . $type;
	if ( is_readable( WOTOYI_THEME_DIR . '/' . $dist ) ) {
		return array( WOTOYI_THEME_DIR . '/' . $dist, WOTOYI_THEME_URI . '/' . $dist );
	}
	$src = 'assets/' . $type . '/' . $name . '.' . $type;
	return array( WOTOYI_THEME_DIR . '/' . $src, WOTOYI_THEME_URI . '/' . $src );
}

function wotoyi_asset_ver( string $path ): string {
	return is_readable( $path ) ? (string) filemtime( $path ) : WOTOYI_THEME_VERSION;
}

/** CSS bundles per view (names = assets/dist/<name>.css). */
function wotoyi_css_for_view( string $view ): array {
	$map = array(
		'home'    => array( 'chrome', 'cards', 'home' ),
		'search'  => array( 'chrome', 'cards', 'search' ),
		'vehicle' => array( 'chrome', 'cards', 'vehicle' ),
		'dealer'  => array( 'chrome', 'cards', 'dealer' ),
	);
	$list = $map[ $view ] ?? array( 'chrome', 'content' );
	if ( ! is_readable( WOTOYI_THEME_DIR . '/assets/dist/chrome.css' ) ) { // no build output: the chrome bundle is chrome + motion + blocks.
		$list = array_merge( array( 'chrome', 'motion', 'blocks' ), array_slice( $list, 1 ) );
	}
	return $list;
}

add_action(
	'wp_head',
	static function (): void {
		$view = wotoyi_view();
		list( $file ) = wotoyi_bundle( 'home' === $view ? 'critical-home' : 'critical-default', 'css' );
		$css = '';
		if ( is_readable( $file ) ) {
			$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} else {
			// No build output (assets/dist) shipped: assemble the critical CSS from the sources (slower, still correct).
			foreach ( array_merge( array( 'tokens', 'fonts', 'base' ), 'home' === $view ? array( 'home-critical' ) : array() ) as $part ) {
				$src = WOTOYI_THEME_DIR . '/assets/css/' . $part . '.css';
				$css .= is_readable( $src ) ? (string) file_get_contents( $src ) . "\n" : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		if ( '' !== $css ) {
			$css = str_replace( '../fonts/', WOTOYI_THEME_URI . '/assets/fonts/', $css );
			echo '<style id="wy-critical">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-authored CSS.
		}
		// Preload only the fonts above the fold (LCP text): Manrope latin always; the serif display on home.
		$fonts = array( 'manrope-latin-wght-normal.woff2' );
		if ( 'home' === $view ) {
			$fonts[] = 'instrument-serif-latin-400-normal.woff2';
			$fonts[] = 'instrument-serif-latin-400-italic.woff2';
		}
		foreach ( $fonts as $f ) {
			echo '<link rel="preload" href="' . esc_url( WOTOYI_THEME_URI . '/assets/fonts/' . $f ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	},
	2
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$view = wotoyi_view();

		foreach ( wotoyi_css_for_view( $view ) as $name ) {
			list( $path, $url ) = wotoyi_bundle( $name, 'css' );
			wp_enqueue_style( 'wy-' . $name, $url, array(), wotoyi_asset_ver( $path ) );
		}

		$js = array(
			'core'   => array( 'core', array() ),
			'motion' => array( 'motion', array( 'wy-core' ) ),
		);
		if ( 'home' === $view ) {
			$js['home'] = array( 'home', array( 'wy-motion' ) );
		} elseif ( 'search' === $view ) {
			$js['search'] = array( 'search', array( 'wy-motion' ) );
		} elseif ( 'vehicle' === $view ) {
			$js['vehicle'] = array( 'vehicle', array( 'wy-motion' ) );
		}
		foreach ( $js as $handle => $def ) {
			list( $path, $url ) = wotoyi_bundle( $def[0], 'js' );
			wp_enqueue_script( 'wy-' . $handle, $url, $def[1], wotoyi_asset_ver( $path ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
		$i18n = array(
			'favAdd'    => __( 'Ajouter aux favoris', 'wotoyi' ),
			'favRemove' => __( 'Retirer des favoris', 'wotoyi' ),
			'favCount'  => __( '{n} favori(s)', 'wotoyi' ),
			'copied'    => __( 'Lien copié', 'wotoyi' ),
		);
		wp_add_inline_script( 'wy-core', 'window.wyI18n=' . wp_json_encode( $i18n ) . ';', 'before' );
	}
);

/** Home only: non-critical stylesheets load without blocking first paint. */
add_filter(
	'style_loader_tag',
	static function ( string $tag, string $handle ): string {
		if ( 'home' !== wotoyi_view() || ! in_array( $handle, array( 'wy-chrome', 'wy-cards', 'wy-home' ), true ) ) {
			return $tag;
		}
		$async = preg_replace( "/rel='stylesheet'/", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag, 1 );
		return $async . '<noscript>' . $tag . '</noscript>';
	},
	10,
	2
);

// Leaner head: emoji scripts are not used by the design.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
