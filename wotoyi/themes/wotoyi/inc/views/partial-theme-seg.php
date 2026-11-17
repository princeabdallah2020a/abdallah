<?php
/** Theme preference radios (Auto / Clair / Sombre). @var int $n unique group id */
defined( 'ABSPATH' ) || exit;
$labels = array( 'auto' => __( 'Auto', 'wotoyi' ), 'light' => __( 'Clair', 'wotoyi' ), 'dark' => __( 'Sombre', 'wotoyi' ) );
?>
<div class="wy-segmented wy-segmented--sm" role="radiogroup" aria-label="<?php esc_attr_e( 'Thème', 'wotoyi' ); ?>">
<?php foreach ( $labels as $v => $l ) : ?>
	<label><input type="radio" name="wy-theme-<?php echo (int) $n; ?>" value="<?php echo esc_attr( $v ); ?>" data-wy-theme-radio><span><?php echo esc_html( $l ); ?></span></label>
<?php endforeach; ?>
</div>
