<?php
/** Body-type shortcut chips with counts. Renders nothing without data. */
defined( 'ABSPATH' ) || exit;
$fac   = wotoyi_mp_facets( array( 'facets' => 'body_type' ) );
$rows  = (array) ( $fac['facets']['body_type'] ?? array() );
$icons = array( 'sedan' => 'sedan', 'suv' => 'suv', 'pickup' => 'pickup', 'hatchback' => 'hatch', 'van' => 'van', 'minivan' => 'van', 'wagon' => 'sedan', 'coupe' => 'sedan', 'truck' => 'van' );
if ( ! $rows ) {
	return;
}
$s = wotoyi_url( 'search' );
?>
<section class="wy-section wy-section--tight" aria-labelledby="h-types">
	<div class="wy-container">
		<h2 id="h-types" class="wy-visually-hidden"><?php esc_html_e( 'Parcourir par carrosserie', 'wotoyi' ); ?></h2>
		<ul class="wy-shortcuts" data-motion="reveal">
			<?php foreach ( array_slice( $rows, 0, 8 ) as $r ) : ?>
				<li><a class="wy-shortcut" href="<?php echo esc_url( add_query_arg( 'body_type', rawurlencode( (string) $r['value'] ), $s ) ); ?>"><?php echo wotoyi_icon( $icons[ $r['value'] ] ?? 'suv' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( wotoyi_label( 'body', (string) $r['value'] ) ); ?> <span class="wy-shortcut__n"><?php echo esc_html( wotoyi_number( $r['count'] ) ); ?></span></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
