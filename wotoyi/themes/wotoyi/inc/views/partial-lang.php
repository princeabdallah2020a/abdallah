<?php
/**
 * Language switcher. Rendered ONLY when the wotoyi-i18n plugin supplies languages through `wotoyi_theme_languages`:
 * [ [ 'code' => 'fr', 'label' => 'FR', 'url' => '...', 'current' => true, 'disabled' => false ], ... ]
 *
 * @var string $extra_class
 */
defined( 'ABSPATH' ) || exit;
$langs = (array) apply_filters( 'wotoyi_theme_languages', array() );
if ( ! $langs ) {
	return;
}
?>
<div class="wy-lang <?php echo esc_attr( $extra_class ?? '' ); ?>" role="group" aria-label="<?php esc_attr_e( 'Langue', 'wotoyi' ); ?>">
<?php
foreach ( $langs as $l ) {
	$code = sanitize_key( (string) ( $l['code'] ?? '' ) );
	$lab  = esc_html( (string) ( $l['label'] ?? strtoupper( $code ) ) );
	if ( ! empty( $l['disabled'] ) || empty( $l['url'] ) ) {
		echo '<span aria-disabled="true" lang="' . esc_attr( $code ) . '"' . ( ! empty( $l['title'] ) ? ' title="' . esc_attr( (string) $l['title'] ) . '"' : '' ) . '>' . $lab . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<a href="' . esc_url( (string) $l['url'] ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '"' . ( ! empty( $l['current'] ) ? ' aria-current="true"' : '' ) . '>' . $lab . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
?>
</div>
