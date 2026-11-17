<?php
/**
 * Vehicle listings section. Attributes: source (featured|rental|newest), layout (rail|grid), eyebrow, title, linkLabel, linkUrl, limit, id.
 * Featured shows a branded empty state when there is nothing to show; other sources render nothing.
 *
 * @var array $attrs
 */
defined( 'ABSPATH' ) || exit;
$src    = in_array( (string) ( $attrs['source'] ?? '' ), array( 'featured', 'rental', 'newest' ), true ) ? (string) $attrs['source'] : 'featured';
$layout = 'grid' === ( $attrs['layout'] ?? '' ) ? 'grid' : 'rail';
$limit  = max( 1, min( 24, (int) ( $attrs['limit'] ?? 8 ) ) );
$id     = sanitize_html_class( (string) ( $attrs['id'] ?? $src ) );
$params = array( 'per_page' => $limit );
if ( 'rental' === $src ) {
	$params['offer_type'] = 'rental';
} elseif ( 'newest' === $src ) {
	$params['sort'] = 'newest';
}
$res   = wotoyi_mp_search( $params );
$items = (array) $res['items'];
if ( 'featured' === $src ) {
	$feat  = array_values( array_filter( $items, static fn( $v ) => ! empty( $v['featured'] ) ) );
	$items = $feat ? $feat : $items;
}
if ( ! $items && 'featured' !== $src ) {
	return;
}
$title = (string) ( $attrs['title'] ?? '' );
$link  = (string) ( $attrs['linkUrl'] ?? '' );
$head  = wotoyi_section_head( 'h-' . $id, (string) ( $attrs['eyebrow'] ?? '' ), $title, (string) ( $attrs['linkLabel'] ?? '' ), '' !== $link ? $link : ( 'rental' === $src ? wotoyi_url( 'rent' ) : wotoyi_url( 'search' ) ) );
echo wotoyi_section_open( $id, '', 'h-' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput
echo '<div class="wy-container">' . $head; // phpcs:ignore WordPress.Security.EscapeOutput
if ( ! $items ) {
	echo wotoyi_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
		wotoyi_marketplace_active() ? __( 'Les premieres annonces arrivent', 'wotoyi' ) : __( 'Le catalogue arrive bientot', 'wotoyi' ),
		wotoyi_marketplace_active() ? __( 'Aucun vehicule n\'est encore publie. Revenez bientot ou deposez une alerte.', 'wotoyi' ) : __( 'La place de marche n\'est pas encore activee sur ce site.', 'wotoyi' )
	);
	echo '</div></section>';
	return;
}
if ( 'grid' === $layout ) {
	echo '<div class="wy-grid wy-grid--3" data-motion="reveal">';
	foreach ( $items as $i => $v ) {
		echo wotoyi_vehicle_card( (array) $v, array( 'eager' => 0 === $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></div></section>';
	return;
}
echo '</div><div class="wy-container-wide wy-hscroll" data-wy-hscroll><div class="wy-hscroll__track" tabindex="0" role="region" aria-label="' . esc_attr( $title ) . '">';
foreach ( $items as $v ) {
	echo wotoyi_vehicle_card( (array) $v ); // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '</div><div class="wy-hscroll__nav"><button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-prev aria-label="' . esc_attr__( 'Precedent', 'wotoyi' ) . '">' . wotoyi_icon( 'arrow-right', 'wy-flip wy-rev' ) . '</button><button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-next aria-label="' . esc_attr__( 'Suivant', 'wotoyi' ) . '">' . wotoyi_icon( 'arrow-right', 'wy-flip' ) . '</button></div></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
