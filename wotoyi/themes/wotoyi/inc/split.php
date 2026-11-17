<?php
/**
 * Server-side word splitting for headlines (DESIGN 3.2 #2): words only, never letters (keeps Arabic shaping).
 * Headings with the class `wy-split` get data-motion="split" and word spans; <em> becomes the gradient emphasis.
 *
 * @package Wotoyi\Theme
 */

defined( 'ABSPATH' ) || exit;

/** @param string $html Already-escaped inline HTML (text + simple tags). */
function wotoyi_split_words( string $html, bool $with_wrapper_attrs = false ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$parts = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $parts ) ) {
		return $html;
	}
	$i   = 0;
	$em  = 0;
	$out = '';
	foreach ( $parts as $part ) {
		if ( '<' === $part[0] ) {
			if ( preg_match( '/^<em\b/i', $part ) ) {
				++$em;
				continue;
			}
			if ( preg_match( '/^<\/em>/i', $part ) ) {
				$em = max( 0, $em - 1 );
				continue;
			}
			$out .= $part;
			continue;
		}
		$tokens = preg_split( '/(\s+)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		foreach ( (array) $tokens as $tok ) {
			if ( preg_match( '/^\s+$/u', $tok ) ) {
				$out .= ' ';
				continue;
			}
			$out .= '<span class="wy-split__w"><span' . ( $em ? ' class="wy-em"' : '' ) . ' style="--i:' . min( $i, 8 ) . '">' . $tok . '</span></span>';
			++$i;
		}
	}
	return $out;
}

add_filter(
	'render_block_core/heading',
	static function ( string $html, array $block ): string {
		$class = (string) ( $block['attrs']['className'] ?? '' );
		if ( false === strpos( ' ' . $class . ' ', ' wy-split ' ) ) {
			return $html;
		}
		if ( ! preg_match( '/^(\s*<h[1-6][^>]*>)(.*)(<\/h[1-6]>\s*)$/s', $html, $m ) ) {
			return $html;
		}
		$open = $m[1];
		$open = preg_replace( '/class="/', 'data-motion="split" data-split="1" class="wy-words ', $open, 1 );
		return $open . wotoyi_split_words( $m[2] ) . $m[3];
	},
	10,
	2
);
