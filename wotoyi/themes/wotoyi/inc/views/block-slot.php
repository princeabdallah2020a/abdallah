<?php
/**
 * Generic extension point. Plugins return HTML (already escaped) from the filter `wotoyi_slot_{name}`.
 *
 * @var array $attrs
 */
defined( 'ABSPATH' ) || exit;
$name = sanitize_key( (string) ( $attrs['name'] ?? '' ) );
if ( '' === $name ) {
	return;
}
$html = apply_filters( 'wotoyi_slot_' . $name, '', $attrs );
if ( is_string( $html ) && '' !== $html ) {
	echo wp_kses_post( $html );
}
