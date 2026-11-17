<?php
/** Make marquee (names only: no third-party logos are shipped). Renders nothing without data. */
defined( 'ABSPATH' ) || exit;
$fac   = wotoyi_mp_facets( array( 'facets' => 'make' ) );
$makes = array_map( static fn( $r ) => (string) $r['value'], (array) ( $fac['facets']['make'] ?? array() ) );
if ( count( $makes ) < 3 ) {
	return;
}
$search = wotoyi_url( 'search' );
?>
<section class="wy-marquee" aria-label="<?php esc_attr_e( 'Marques disponibles', 'wotoyi' ); ?>" data-wy-marquee>
	<p class="wy-overline wy-marquee__cap"><?php esc_html_e( 'Marques disponibles', 'wotoyi' ); ?></p>
	<div class="wy-marquee__viewport"><ul class="wy-marquee__track">
		<?php foreach ( array_slice( $makes, 0, 16 ) as $m ) : ?>
			<li><a class="wy-chip" href="<?php echo esc_url( add_query_arg( 'make', rawurlencode( $m ), $search ) ); ?>"><?php echo esc_html( $m ); ?></a></li>
		<?php endforeach; ?>
	</ul></div>
</section>
