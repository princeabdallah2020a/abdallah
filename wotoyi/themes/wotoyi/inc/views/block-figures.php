<?php
/** Platform figures: only real counts from the marketplace; hidden when all are zero. */
defined( 'ABSPATH' ) || exit;
$v = (int) ( wotoyi_mp_search( array( 'per_page' => 1 ) )['total'] ?? 0 );
$d = (int) ( wotoyi_mp_dealers( array( 'per_page' => 1 ) )['total'] ?? 0 );
$c = count( (array) ( wotoyi_mp_facets( array( 'facets' => 'city' ) )['facets']['city'] ?? array() ) );
if ( ! ( $v || $d || $c ) ) {
	return;
}
$fig = array( array( __( 'Vehicules', 'wotoyi' ), $v ), array( __( 'Concessionnaires', 'wotoyi' ), $d ), array( __( 'Villes', 'wotoyi' ), $c ) );
?>
<section class="wy-section" aria-labelledby="h-fig">
	<div class="wy-container">
		<h2 id="h-fig" class="wy-display-m" data-motion="split"><?php esc_html_e( 'Wotoyi en chiffres', 'wotoyi' ); ?></h2>
		<dl class="wy-figures" data-motion="reveal">
			<?php foreach ( $fig as $f ) : if ( ! $f[1] ) { continue; } ?>
				<div><dt><?php echo esc_html( $f[0] ); ?></dt><dd><span class="wy-counter" data-wy-counter="<?php echo (int) $f[1]; ?>"><?php echo esc_html( wotoyi_number( $f[1] ) ); ?></span></dd></div>
			<?php endforeach; ?>
		</dl>
	</div>
</section>
