<?php
/** Optional top strip (e.g. demo mode). Plugins: add_filter( 'wotoyi_theme_notice', fn() => 'Mode demonstration ...' ). */
defined( 'ABSPATH' ) || exit;
$notice = (string) apply_filters( 'wotoyi_theme_notice', '' );
if ( '' === $notice ) {
	return;
}
?>
<div class="wy-demo-strip" role="note"><span class="wy-badge wy-badge--demo"><?php esc_html_e( 'Demo', 'wotoyi' ); ?></span> <span><?php echo esc_html( $notice ); ?></span></div>
