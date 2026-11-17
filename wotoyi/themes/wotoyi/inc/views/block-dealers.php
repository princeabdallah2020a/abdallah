<?php
/** Verified dealers rail. Renders nothing without data. */
defined( 'ABSPATH' ) || exit;
$res   = wotoyi_mp_dealers( array( 'verified' => true, 'per_page' => 12 ) );
$items = (array) $res['items'];
if ( ! $items ) {
	return;
}
echo wotoyi_section_open( 'dealers', 'sand', 'h-deal' ); // phpcs:ignore WordPress.Security.EscapeOutput
echo '<div class="wy-container">' . wotoyi_section_head( 'h-deal', __( 'Concessionnaires', 'wotoyi' ), __( 'Des professionnels vérifiés', 'wotoyi' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<div class="wy-container-wide wy-hscroll"><div class="wy-hscroll__track" tabindex="0" role="region" aria-label="' . esc_attr__( 'Concessionnaires vérifiés', 'wotoyi' ) . '">';
foreach ( $items as $d ) {
	echo wotoyi_dealer_card( (array) $d ); // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '</div></div></section>';
