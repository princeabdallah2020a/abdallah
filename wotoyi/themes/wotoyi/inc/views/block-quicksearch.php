<?php
/**
 * Hero quick search. Works without JavaScript (plain GET to the search page). Options come from the marketplace facets;
 * when the plugin is inactive the form degrades to a keyword search.
 *
 * @var array $attrs
 */
defined( 'ABSPATH' ) || exit;
$fac    = wotoyi_mp_facets( array( 'facets' => 'make,city' ) );
$makes  = array_map( static fn( $r ) => (string) $r['value'], (array) ( $fac['facets']['make'] ?? array() ) );
$cities = array_map( static fn( $r ) => (string) $r['value'], (array) ( $fac['facets']['city'] ?? array() ) );
$maxp   = (int) ( $fac['ranges']['price']['max'] ?? 0 );
$steps  = (array) apply_filters( 'wotoyi_theme_budget_steps', array( 2000000, 3000000, 5000000, 8000000, 12000000, 20000000, 30000000, 50000000 ) );
$steps  = array_values( array_filter( array_map( 'intval', $steps ), static fn( $n ) => $n > 0 && ( 0 === $maxp || $n <= $maxp * 1.5 ) ) );
$models = (array) apply_filters( 'wotoyi_theme_models_map', array() ); // make => [models]; supplied by the plugin when available.
$total  = (int) ( wotoyi_mp_search( array( 'per_page' => 1 ) )['total'] ?? 0 );
$action = wotoyi_url( 'search' );
$has    = wotoyi_marketplace_active();
?>
<form class="wy-quicksearch wy-glass" action="<?php echo esc_url( $action ); ?>" method="get" data-wy-quicksearch data-wy-models="<?php echo esc_attr( wp_json_encode( $models ) ); ?>" aria-label="<?php esc_attr_e( 'Recherche rapide de vehicules', 'wotoyi' ); ?>">
	<div class="wy-quicksearch__top">
		<div class="wy-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Type d\'offre', 'wotoyi' ); ?>">
			<label><input type="radio" name="offer_type" value="sale" checked><span><?php esc_html_e( 'Acheter', 'wotoyi' ); ?></span></label>
			<label><input type="radio" name="offer_type" value="rental"><span><?php esc_html_e( 'Louer', 'wotoyi' ); ?></span></label>
		</div>
		<?php if ( $total > 0 ) : ?>
			<p class="wy-quicksearch__count" aria-live="polite"><strong data-wy-counter="<?php echo (int) $total; ?>"><?php echo esc_html( wotoyi_number( $total ) ); ?></strong> <?php echo esc_html( _n( 'vehicule disponible', 'vehicules disponibles', $total, 'wotoyi' ) ); ?></p>
		<?php endif; ?>
	</div>
	<div class="wy-quicksearch__grid">
		<?php if ( $makes ) : ?>
			<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Marque', 'wotoyi' ); ?></span>
				<select class="wy-select" name="make" data-wy-make><option value=""><?php esc_html_e( 'Toutes les marques', 'wotoyi' ); ?></option>
				<?php foreach ( $makes as $m ) : ?><option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option><?php endforeach; ?></select></label>
			<?php if ( $models ) : ?>
				<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Modele', 'wotoyi' ); ?></span>
					<select class="wy-select" name="model" data-wy-model data-all="<?php esc_attr_e( 'Tous les modeles', 'wotoyi' ); ?>" disabled><option value=""><?php esc_html_e( 'Tous les modeles', 'wotoyi' ); ?></option></select></label>
			<?php endif; ?>
		<?php else : ?>
			<label class="wy-field wy-field--wide"><span class="wy-field__l"><?php esc_html_e( 'Marque, modele, mot-cle', 'wotoyi' ); ?></span>
				<input class="wy-input" type="search" name="q" autocomplete="off" placeholder="<?php esc_attr_e( 'Ex. Toyota Land Cruiser', 'wotoyi' ); ?>"></label>
		<?php endif; ?>
		<?php if ( $steps && $has ) : ?>
			<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Budget maximum', 'wotoyi' ); ?></span>
				<select class="wy-select" name="price_max"><option value=""><?php esc_html_e( 'Sans limite', 'wotoyi' ); ?></option>
				<?php foreach ( $steps as $s ) : ?><option value="<?php echo (int) $s; ?>"><?php echo esc_html( wotoyi_price( $s ) ); ?></option><?php endforeach; ?></select></label>
		<?php endif; ?>
		<?php if ( $cities ) : ?>
			<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Ville', 'wotoyi' ); ?></span>
				<select class="wy-select" name="city"><option value=""><?php esc_html_e( 'Toutes les villes', 'wotoyi' ); ?></option>
				<?php foreach ( $cities as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select></label>
		<?php endif; ?>
		<button class="wy-btn wy-btn--primary wy-btn--lg" type="submit" data-motion="magnetic"><span class="wy-btn__label"><?php esc_html_e( 'Rechercher', 'wotoyi' ); ?></span><?php echo wotoyi_icon( 'arrow-right', 'wy-flip' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
	</div>
	<?php if ( ! $has ) : ?>
		<p class="wy-caption wy-muted"><?php esc_html_e( 'Le catalogue sera disponible des l\'activation de la place de marche.', 'wotoyi' ); ?></p>
	<?php endif; ?>
</form>
