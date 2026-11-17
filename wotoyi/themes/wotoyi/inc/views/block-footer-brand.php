<?php
/** Footer brand column: logo, mission (site tagline, editable in Settings), theme + language controls. */
defined( 'ABSPATH' ) || exit;
$mission = trim( (string) get_bloginfo( 'description' ) );
if ( '' === $mission ) {
	$mission = __( 'Le showroom automobile du Sénégal et de l\'Afrique de l\'Ouest : véhicules vérifiés, concessionnaires de confiance, contact direct.', 'wotoyi' );
}
?>
<div class="wy-footer__brand">
	<?php echo wotoyi_view_html( 'block-logo', array( 'attrs' => array( 'variant' => 'f' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p class="wy-muted"><?php echo esc_html( $mission ); ?></p>
	<div class="wy-footer__ctl">
		<?php
		$l = wotoyi_view_html( 'partial-lang', array() );
		if ( '' !== trim( $l ) ) {
			echo '<div><p class="wy-overline">' . esc_html__( 'Langue', 'wotoyi' ) . '</p>' . $l . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
		<div><p class="wy-overline"><?php esc_html_e( 'Thème', 'wotoyi' ); ?></p><?php echo wotoyi_view_html( 'partial-theme-seg', array( 'n' => 2 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</div>
</div>
