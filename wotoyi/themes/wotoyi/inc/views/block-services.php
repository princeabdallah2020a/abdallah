<?php
/** Services (workshops, detailing, etc.). Renders nothing without data. */
defined( 'ABSPATH' ) || exit;
$res = wotoyi_mp_services( array( 'per_page' => 8 ) );
$items = (array) $res['items'];
if ( ! $items ) {
	return;
}
echo wotoyi_section_open( 'services', 'sand', 'h-serv' ); // phpcs:ignore WordPress.Security.EscapeOutput
echo '<div class="wy-container">' . wotoyi_section_head( 'h-serv', __( 'Services', 'wotoyi' ), __( 'Tout pour votre vehicule', 'wotoyi' ) ) . '<div class="wy-grid wy-grid--4" data-motion="reveal">'; // phpcs:ignore WordPress.Security.EscapeOutput
foreach ( $items as $s ) {
	echo wotoyi_service_card( (array) $s ); // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '</div></div></section>';
