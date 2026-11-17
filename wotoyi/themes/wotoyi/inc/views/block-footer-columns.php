<?php
/** Footer link columns. Entries without a URL are not rendered. Filter `wotoyi_theme_footer_columns` to change the structure. */
defined( 'ABSPATH' ) || exit;
$s    = wotoyi_url( 'search' );
$cols = array(
	array( __( 'Acheter', 'wotoyi' ), array(
		array( __( 'SUV', 'wotoyi' ), add_query_arg( 'body_type', 'suv', $s ) ),
		array( __( 'Berlines', 'wotoyi' ), add_query_arg( 'body_type', 'sedan', $s ) ),
		array( __( 'Pick-up', 'wotoyi' ), add_query_arg( 'body_type', 'pickup', $s ) ),
		array( __( 'Tous les véhicules', 'wotoyi' ), $s ),
	) ),
	array( __( 'Louer', 'wotoyi' ), array(
		array( __( 'Toutes les locations', 'wotoyi' ), wotoyi_url( 'rent' ) ),
		array( __( 'Services', 'wotoyi' ), wotoyi_url( 'services' ) ),
	) ),
	array( __( 'Concessionnaires', 'wotoyi' ), array(
		array( __( 'Concessionnaires vérifiés', 'wotoyi' ), wotoyi_url( 'dealers' ) ),
		array( __( 'Rejoindre Wotoyi', 'wotoyi' ), wotoyi_url( 'dealer_cta' ) ),
		array( 'Wotoyi Pro', wotoyi_url( 'pro' ) ),
	) ),
	array( __( 'Aide', 'wotoyi' ), array(
		array( __( 'Inspection', 'wotoyi' ), wotoyi_url( 'inspection' ) ),
		array( __( 'Conseils de sécurité', 'wotoyi' ), wotoyi_url( 'safety' ) ),
		array( __( 'Contact', 'wotoyi' ), wotoyi_url( 'contact' ) ),
	) ),
	array( __( 'Légal', 'wotoyi' ), array(
		array( __( 'Conditions d\'utilisation', 'wotoyi' ), wotoyi_url( 'terms' ) ),
		array( __( 'Confidentialité', 'wotoyi' ), wotoyi_url( 'privacy' ) ),
		array( __( 'Mentions légales', 'wotoyi' ), wotoyi_url( 'notices' ) ),
	) ),
);
$cols = (array) apply_filters( 'wotoyi_theme_footer_columns', $cols );
?>
<nav class="wy-footer__cols" aria-label="<?php esc_attr_e( 'Pied de page', 'wotoyi' ); ?>">
<?php
foreach ( $cols as $col ) {
	$items = array_filter( (array) $col[1], static fn( $i ) => ! empty( $i[1] ) );
	if ( ! $items ) {
		continue;
	}
	echo '<div><h2 class="wy-h5">' . esc_html( (string) $col[0] ) . '</h2><ul>';
	foreach ( $items as $i ) {
		echo '<li><a href="' . esc_url( (string) $i[1] ) . '">' . esc_html( (string) $i[0] ) . '</a></li>';
	}
	echo '</ul></div>';
}
?>
</nav>
