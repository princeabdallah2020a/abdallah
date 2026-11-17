<?php
/**
 * Marketplace search + results (server-rendered; works without JavaScript - the filters are a plain GET form).
 * Data: wotoyi_mp_search() / wotoyi_mp_facets() (marketplace plugin). Without the plugin: branded empty state.
 */
defined( 'ABSPATH' ) || exit;
$action = wotoyi_url( 'search' );
$p      = wotoyi_request_params();
$mp     = wotoyi_marketplace_active();
$res    = $mp ? wotoyi_mp_search( $p ) : wotoyi_mp_search( array() );
$fac    = $mp ? wotoyi_mp_facets( $p ) : array( 'facets' => array(), 'ranges' => array() );
$items  = (array) $res['items'];
$total  = (int) $res['total'];
$page   = max( 1, (int) $res['page'] );
$pages  = (int) $res['pages'];
$sort   = (string) $res['sort'];
$val    = static fn( string $k ): string => isset( $p[ $k ] ) && is_string( $p[ $k ] ) ? $p[ $k ] : '';
$multi  = static fn( string $k ): array => isset( $p[ $k ] ) ? array_map( 'strval', (array) ( is_array( $p[ $k ] ) ? $p[ $k ] : explode( ',', (string) $p[ $k ] ) ) ) : array();
$f      = (array) ( $fac['facets'] ?? array() );
$sorts  = array(
	'relevance'    => __( 'Pertinence', 'wotoyi' ),
	'price_asc'    => __( 'Prix croissant', 'wotoyi' ),
	'price_desc'   => __( 'Prix décroissant', 'wotoyi' ),
	'year_desc'    => __( 'Plus récents', 'wotoyi' ),
	'mileage_asc'  => __( 'Kilométrage croissant', 'wotoyi' ),
	'newest'       => __( 'Dernières annonces', 'wotoyi' ),
);
$groups = array(
	'body_type'    => array( __( 'Carrosserie', 'wotoyi' ), 'body' ),
	'fuel'         => array( __( 'Carburant', 'wotoyi' ), 'fuel' ),
	'transmission' => array( __( 'Boîte', 'wotoyi' ), 'trans' ),
	'condition'    => array( __( 'État', 'wotoyi' ), 'cond' ),
	'drive'        => array( __( 'Transmission', 'wotoyi' ), 'drive' ),
	'ext_colour'   => array( __( 'Couleur extérieure', 'wotoyi' ), 'colour' ),
	'int_colour'   => array( __( 'Couleur intérieure', 'wotoyi' ), 'colour' ),
	'upholstery'   => array( __( 'Sellerie', 'wotoyi' ), 'uph' ),
	'customs_status' => array( __( 'Dédouanement', 'wotoyi' ), 'customs' ),
	'origin_country' => array( __( 'Pays d’origine', 'wotoyi' ), 'country' ),
	'equipment'    => array( __( 'Équipements', 'wotoyi' ), 'equip' ),
);
/* Enumerations the plugin accepts but does not facet (no counts): offered from the schema enums when the plugin is active. */
$static_enum = static function ( string $cls ): array {
	return class_exists( '\\Wotoyi\\Marketplace\\Schema\\Enums' ) ? array_map( static fn( $c ) => array( 'value' => $c, 'count' => null ), (array) constant( '\\Wotoyi\\Marketplace\\Schema\\Enums::' . $cls ) ) : array();
};
foreach ( array( 'int_colour' => 'COLOUR', 'upholstery' => 'UPHOLSTERY' ) as $fk => $en ) {
	if ( empty( $f[ $fk ] ) && $mp ) {
		$f[ $fk ] = $static_enum( $en );
	}
}
$ranges = array(
	array( __( 'Année et kilométrage', 'wotoyi' ), array(
		array( 'year_min', __( 'Année min.', 'wotoyi' ), 1900, 1 ), array( 'year_max', __( 'Année max.', 'wotoyi' ), 1900, 1 ),
		array( 'mileage_min', __( 'Km min.', 'wotoyi' ), 0, 5000 ), array( 'mileage_max', __( 'Km max.', 'wotoyi' ), 0, 5000 ),
	) ),
	array( __( 'Moteur et performances', 'wotoyi' ), array(
		array( 'engine_min', __( 'Cylindrée min. (cm³)', 'wotoyi' ), 0, 100 ), array( 'engine_max', __( 'Cylindrée max. (cm³)', 'wotoyi' ), 0, 100 ),
		array( 'power_min', __( 'Puissance min. (ch)', 'wotoyi' ), 0, 10 ), array( 'power_max', __( 'Puissance max. (ch)', 'wotoyi' ), 0, 10 ),
		array( 'consumption_max', __( 'Conso. max. (L/100 km)', 'wotoyi' ), 0, 0.1 ), array( 'range_min', __( 'Autonomie min. (km, électrique)', 'wotoyi' ), 0, 10 ),
	) ),
	array( __( 'Places, portes, propriétaires', 'wotoyi' ), array(
		array( 'seats_min', __( 'Places min.', 'wotoyi' ), 0, 1 ), array( 'owners_max', __( 'Propriétaires max.', 'wotoyi' ), 0, 1 ),
		array( 'monthly_price_max', __( 'Loyer mensuel max. (FCFA)', 'wotoyi' ), 0, 10000 ),
	) ),
);
$flags2 = array(
	'service_history' => __( 'Carnet d’entretien', 'wotoyi' ),
	'delivery'        => __( 'Livraison possible', 'wotoyi' ),
	'financing'       => __( 'Financement possible', 'wotoyi' ),
	'trade_in'        => __( 'Reprise acceptée', 'wotoyi' ),
	'has_video'       => __( 'Avec vidéo', 'wotoyi' ),
	'has_360'         => __( 'Avec vue 360°', 'wotoyi' ),
);
$since = array( '' => __( 'Toutes les dates', 'wotoyi' ), '1' => __( 'Dernières 24 h', 'wotoyi' ), '7' => __( '7 derniers jours', 'wotoyi' ), '30' => __( '30 derniers jours', 'wotoyi' ) );
$flags  = array(
	'inspected'       => __( 'Inspecté par Wotoyi', 'wotoyi' ),
	'verified_dealer' => __( 'Concessionnaire vérifié', 'wotoyi' ),
	'accident_free'   => __( 'Sans accident', 'wotoyi' ),
	'warranty'        => __( 'Sous garantie', 'wotoyi' ),
	'has_photos'      => __( 'Avec photos', 'wotoyi' ),
);
$active = 0;
foreach ( $p as $k => $v ) {
	if ( ! in_array( $k, array( 'page', 'sort', 'per_page' ), true ) && '' !== $v && array() !== $v ) {
		++$active;
	}
}
$self = static fn( array $args ): string => esc_url( add_query_arg( array_merge( $p, $args ), $action ) );
?>
<div class="wy-container-wide wy-search-head">
	<nav class="wy-breadcrumb" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'wotoyi' ); ?>"><ol><li><a href="<?php echo esc_url( wotoyi_url( 'home' ) ); ?>"><?php esc_html_e( 'Accueil', 'wotoyi' ); ?></a></li><li aria-current="page"><?php esc_html_e( 'Véhicules', 'wotoyi' ); ?></li></ol></nav>
	<h1 class="wy-display-m"><?php esc_html_e( 'Véhicules à vendre et à louer', 'wotoyi' ); ?></h1>
</div>

<?php if ( ! $mp ) : ?>
	<div class="wy-container"><?php echo wotoyi_empty_state( __( 'Le catalogue arrive bientôt', 'wotoyi' ), __( 'La place de marché Wotoyi n\'est pas encore activée sur ce site.', 'wotoyi' ), array( array( 'url' => wotoyi_url( 'home' ), 'label' => __( 'Retour à l\'accueil', 'wotoyi' ), 'class' => 'wy-btn--primary' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<?php return; ?>
<?php endif; ?>

<form action="<?php echo esc_url( $action ); ?>" method="get" data-wy-search-form data-wy-count-url="<?php echo esc_url( rest_url( 'wotoyi/v1/vehicles/count' ) ); ?>" role="search" aria-label="<?php esc_attr_e( 'Recherche de véhicules', 'wotoyi' ); ?>">
	<div class="wy-searchbar" data-wy-searchbar>
		<div class="wy-container-wide wy-searchbar__in">
			<div class="wy-search wy-search--nl wy-searchbar__q">
				<?php echo wotoyi_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<input class="wy-input" type="search" name="q" value="<?php echo esc_attr( $val( 'q' ) ); ?>" placeholder="<?php esc_attr_e( 'Marque, modèle, mot-clé', 'wotoyi' ); ?>" aria-label="<?php esc_attr_e( 'Recherche par mot-clé', 'wotoyi' ); ?>" autocomplete="off">
			</div>
			<button class="wy-btn wy-btn--ghost wy-not-lg" type="button" data-wy-filters-open aria-controls="filters" aria-expanded="false"><?php echo wotoyi_icon( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Filtres', 'wotoyi' ); ?> (<?php echo (int) $active; ?>)</span></button>
			<label class="wy-sort"><span class="wy-visually-hidden"><?php esc_html_e( 'Trier par', 'wotoyi' ); ?></span>
				<select class="wy-select" name="sort" data-wy-sort aria-label="<?php esc_attr_e( 'Trier par', 'wotoyi' ); ?>">
					<?php foreach ( $sorts as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( $sort, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
				</select></label>
			<div class="wy-viewtoggle wy-only-lg-up" role="group" aria-label="<?php esc_attr_e( 'Affichage', 'wotoyi' ); ?>">
				<button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-view="grid" aria-pressed="true" aria-label="<?php esc_attr_e( 'Grille', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-view="list" aria-pressed="false" aria-label="<?php esc_attr_e( 'Liste', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
			<button class="wy-btn wy-btn--primary wy-btn--sm" type="submit"><span class="wy-btn__label"><?php esc_html_e( 'Rechercher', 'wotoyi' ); ?></span></button>
		</div>
	</div>

	<div class="wy-container-wide wy-search-meta">
		<div class="wy-result-bar"><p aria-live="polite"><strong data-wy-count-live><?php echo esc_html( wotoyi_number( $total ) ); ?></strong> <?php echo esc_html( _n( 'résultat', 'résultats', $total, 'wotoyi' ) ); ?></p></div>
	</div>

	<div class="wy-container-wide wy-search-layout">
		<aside class="wy-filter-panel" id="filters" data-wy-filters aria-label="<?php esc_attr_e( 'Filtres', 'wotoyi' ); ?>">
			<div class="wy-sheet__grip" data-wy-grip aria-hidden="true"><i></i></div>
			<header class="wy-filter-panel__head" data-wy-sheet-head>
				<h2 class="wy-h4"><?php esc_html_e( 'Filtres', 'wotoyi' ); ?></h2>
				<div class="wy-row"><a class="wy-link" href="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'Réinitialiser', 'wotoyi' ); ?></a><button class="wy-btn wy-btn--icon wy-not-lg" type="button" data-wy-close aria-label="<?php esc_attr_e( 'Fermer', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button></div>
			</header>
			<div class="wy-filter-panel__body" data-lenis-prevent>
				<?php
				$gi = 0;
				$open = static function ( string $title, bool $on = true ) use ( &$gi ): string {
					++$gi;
					return '<section class="wy-filter-group' . ( $on ? ' is-open' : '' ) . '"><h3><button class="wy-filter-group__h" type="button" aria-expanded="' . ( $on ? 'true' : 'false' ) . '" aria-controls="fg-' . (int) $gi . '"><span>' . esc_html( $title ) . '</span>' . wotoyi_icon( 'chevron-down', 'wy-chev' ) . '</button></h3><div class="wy-filter-group__in" id="fg-' . (int) $gi . '"' . ( $on ? '' : ' hidden' ) . '>';
				};
				echo $open( __( 'Offre', 'wotoyi' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
					<div class="wy-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Type d\'offre', 'wotoyi' ); ?>">
						<?php foreach ( array( '' => __( 'Tout', 'wotoyi' ), 'sale' => __( 'Achat', 'wotoyi' ), 'rental' => __( 'Location', 'wotoyi' ) ) as $k => $l ) : ?>
							<label><input type="radio" name="offer_type" value="<?php echo esc_attr( $k ); ?>"<?php checked( $val( 'offer_type' ), $k ); ?>><span><?php echo esc_html( $l ); ?></span></label>
						<?php endforeach; ?>
					</div>
				</div></section>
				<?php echo $open( __( 'Marque et ville', 'wotoyi' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php foreach ( array( 'make' => __( 'Marque', 'wotoyi' ), 'city' => __( 'Ville', 'wotoyi' ) ) as $k => $l ) : if ( empty( $f[ $k ] ) && '' === $val( $k ) ) { continue; } ?>
						<label class="wy-field"><span class="wy-field__l"><?php echo esc_html( $l ); ?></span>
							<select class="wy-select" name="<?php echo esc_attr( $k ); ?>"><option value=""><?php esc_html_e( 'Toutes', 'wotoyi' ); ?></option>
							<?php foreach ( (array) ( $f[ $k ] ?? array() ) as $o ) : ?><option value="<?php echo esc_attr( (string) $o['value'] ); ?>"<?php selected( $val( $k ), (string) $o['value'] ); ?>><?php echo esc_html( $o['value'] . ' (' . $o['count'] . ')' ); ?></option><?php endforeach; ?></select></label>
					<?php endforeach; ?>
				</div></section>
				<?php foreach ( array( 'model' => __( 'Modèle', 'wotoyi' ), 'trim' => __( 'Finition', 'wotoyi' ) ) as $k => $l ) : if ( empty( $f[ $k ] ) ) { continue; } ?>
					<?php echo $open( $l, '' !== $val( $k ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<label class="wy-field"><span class="wy-field__l"><?php echo esc_html( $l ); ?></span>
							<select class="wy-select" name="<?php echo esc_attr( $k ); ?>"><option value=""><?php esc_html_e( 'Tous', 'wotoyi' ); ?></option>
							<?php foreach ( (array) $f[ $k ] as $o ) : ?><option value="<?php echo esc_attr( (string) $o['value'] ); ?>"<?php selected( $val( $k ), (string) $o['value'] ); ?>><?php echo esc_html( $o['value'] . ' (' . $o['count'] . ')' ); ?></option><?php endforeach; ?></select></label>
					</div></section>
				<?php endforeach; ?>
				<?php
				$rp = (array) ( $fac['ranges']['price'] ?? array() );
				if ( ! empty( $rp['max'] ) ) :
					echo $open( __( 'Budget', 'wotoyi' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
					<div class="wy-field-row">
						<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Minimum (FCFA)', 'wotoyi' ); ?></span><input class="wy-input" type="number" inputmode="numeric" min="0" step="100000" name="price_min" value="<?php echo esc_attr( $val( 'price_min' ) ); ?>" placeholder="<?php echo esc_attr( (string) (int) $rp['min'] ); ?>"></label>
						<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Maximum (FCFA)', 'wotoyi' ); ?></span><input class="wy-input" type="number" inputmode="numeric" min="0" step="100000" name="price_max" value="<?php echo esc_attr( $val( 'price_max' ) ); ?>" placeholder="<?php echo esc_attr( (string) (int) $rp['max'] ); ?>"></label>
					</div>
					</div></section>
				<?php endif; ?>
				<?php foreach ( $groups as $key => $g ) : if ( empty( $f[ $key ] ) ) { continue; } $sel = $multi( $key ); ?>
					<?php echo $open( $g[0], false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php foreach ( (array) $f[ $key ] as $o ) : ?>
							<label class="wy-check"><input type="checkbox" name="<?php echo esc_attr( 'equipment' === $key ? 'equipment' : $key ); ?>[]" value="<?php echo esc_attr( (string) $o['value'] ); ?>"<?php checked( in_array( (string) $o['value'], $sel, true ) ); ?>><span><?php echo esc_html( 'country' === $g[1] ? (string) $o['value'] : wotoyi_label( $g[1], (string) $o['value'] ) ); ?><?php if ( null !== ( $o['count'] ?? null ) ) : ?> <span class="wy-muted">(<?php echo (int) $o['count']; ?>)</span><?php endif; ?></span></label>
						<?php endforeach; ?>
					</div></section>
				<?php endforeach; ?>
				<?php foreach ( $ranges as $rg ) : ?>
					<?php echo $open( $rg[0], false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<div class="wy-field-row">
						<?php foreach ( $rg[1] as $fd ) : ?>
							<label class="wy-field"><span class="wy-field__l"><?php echo esc_html( $fd[1] ); ?></span><input class="wy-input" type="number" inputmode="decimal" min="<?php echo esc_attr( (string) $fd[2] ); ?>" step="any" name="<?php echo esc_attr( $fd[0] ); ?>" value="<?php echo esc_attr( $val( $fd[0] ) ); ?>"></label>
						<?php endforeach; ?>
						</div>
					</div></section>
				<?php endforeach; ?>
				<?php echo $open( __( 'Portes', 'wotoyi' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php $dsel = $multi( 'doors' ); foreach ( array( '2', '3', '4', '5' ) as $dn ) : ?>
						<label class="wy-check"><input type="checkbox" name="doors[]" value="<?php echo esc_attr( $dn ); ?>"<?php checked( in_array( $dn, $dsel, true ) ); ?>><span><?php echo esc_html( $dn ); ?></span></label>
					<?php endforeach; ?>
				</div></section>
				<?php echo $open( __( 'Date de publication', 'wotoyi' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Publiée', 'wotoyi' ); ?></span><select class="wy-select" name="posted_since"><?php foreach ( $since as $k => $l ) : ?><option value="<?php echo esc_attr( (string) $k ); ?>"<?php selected( $val( 'posted_since' ), (string) $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				</div></section>
				<?php echo $open( __( 'Services et médias', 'wotoyi' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php foreach ( $flags2 as $k => $l ) : ?>
						<label class="wy-check"><input type="checkbox" name="<?php echo esc_attr( $k ); ?>" value="1"<?php checked( '1' === $val( $k ) ); ?>><span><?php echo esc_html( $l ); ?></span></label>
					<?php endforeach; ?>
				</div></section>
				<?php echo $open( __( 'Confiance', 'wotoyi' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php foreach ( $flags as $k => $l ) : ?>
						<label class="wy-check"><input type="checkbox" name="<?php echo esc_attr( $k ); ?>" value="1"<?php checked( '1' === $val( $k ) ); ?>><span><?php echo esc_html( $l ); ?></span></label>
					<?php endforeach; ?>
				</div></section>
			</div>
			<footer class="wy-filter-panel__foot"><a class="wy-btn wy-btn--ghost" href="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'Réinitialiser', 'wotoyi' ); ?></a><button class="wy-btn wy-btn--primary" type="submit"><span class="wy-btn__label"><?php esc_html_e( 'Voir les résultats', 'wotoyi' ); ?></span></button></footer>
		</aside>

		<section class="wy-search-results" aria-label="<?php esc_attr_e( 'Résultats', 'wotoyi' ); ?>">
			<?php if ( $items ) : ?>
				<div class="wy-results wy-grid" data-wy-results data-view="grid">
					<?php foreach ( $items as $i => $v ) { echo wotoyi_vehicle_card( (array) $v, array( 'eager' => $i < 2 ) ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<?php if ( $pages > 1 ) : ?>
					<nav class="wy-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'wotoyi' ); ?>">
						<?php
						$from = max( 1, $page - 2 );
						$to   = min( $pages, $page + 2 );
						if ( $page > 1 ) {
							echo '<a class="wy-page" rel="prev" href="' . $self( array( 'page' => $page - 1 ) ) . '" aria-label="' . esc_attr__( 'Page précédente', 'wotoyi' ) . '">' . wotoyi_icon( 'arrow-right', 'wy-flip wy-rev' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						for ( $n = $from; $n <= $to; $n++ ) {
							echo '<a class="wy-page" href="' . $self( array( 'page' => $n ) ) . '"' . ( $n === $page ? ' aria-current="page"' : '' ) . '>' . (int) $n . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						if ( $page < $pages ) {
							echo '<a class="wy-page" rel="next" href="' . $self( array( 'page' => $page + 1 ) ) . '" aria-label="' . esc_attr__( 'Page suivante', 'wotoyi' ) . '">' . wotoyi_icon( 'arrow-right', 'wy-flip' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<?php echo wotoyi_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
					$active ? __( 'Aucun véhicule ne correspond', 'wotoyi' ) : __( 'Les premières annonces arrivent', 'wotoyi' ),
					$active ? __( 'Élargissez vos critères ou réinitialisez les filtres.', 'wotoyi' ) : __( 'Aucun véhicule n\'est encore publié.', 'wotoyi' ),
					$active ? array( array( 'url' => $action, 'label' => __( 'Réinitialiser les filtres', 'wotoyi' ), 'class' => 'wy-btn--primary' ) ) : array()
				); ?>
			<?php endif; ?>
		</section>
	</div>
</form>
