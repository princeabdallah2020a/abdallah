<?php
/**
 * Vehicle detail (server-rendered for SEO and no-JS). Data: wotoyi_mp_vehicle( ref ) from the marketplace plugin.
 * Lead form: dialog [data-wy-lead] posts to the marketplace REST /leads with a single-use token fetched from /leads/token?vehicle=<uuid> (see assets/js/vehicle.js).
 *
 * @var array $attrs
 */
defined( 'ABSPATH' ) || exit;
$ref = (string) get_query_var( 'wotoyi_ref' );
$v   = wotoyi_mp_vehicle( $ref );
if ( ! $v ) {
	$active = wotoyi_marketplace_active();
	echo '<div class="wy-container">' . wotoyi_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
		$active ? __( 'Annonce introuvable', 'wotoyi' ) : __( 'Le catalogue arrive bientôt', 'wotoyi' ),
		$active ? __( 'Ce véhicule n\'est plus disponible ou le lien est incorrect.', 'wotoyi' ) : __( 'La place de marché n\'est pas encore activée sur ce site.', 'wotoyi' ),
		array( array( 'url' => wotoyi_url( 'search' ), 'label' => __( 'Voir les véhicules', 'wotoyi' ), 'class' => 'wy-btn--primary' ) )
	) . '</div>';
	return;
}
$t      = (array) $v['tabs'];
$id     = (array) ( $t['identity'] ?? array() );
$eng    = (array) ( $t['engine'] ?? array() );
$com    = (array) ( $t['commercial'] ?? array() );
$his    = (array) ( $t['history'] ?? array() );
$med    = (array) ( $t['media'] ?? array() );
$trust  = (array) ( $t['trust'] ?? array() );
$dealer = (array) $v['dealer'];
$flat   = array_merge( $id, $eng, $com, $his );
$title  = (string) $v['title'];
$photos = array();
if ( ! empty( $med['cover_image'] ) ) {
	$photos[] = (string) $med['cover_image'];
}
foreach ( (array) ( $med['gallery'] ?? array() ) as $u ) {
	if ( is_string( $u ) && ! in_array( $u, $photos, true ) ) {
		$photos[] = $u;
	}
}
$card = array_merge( $flat, array( 'id' => $v['id'], 'slug' => $v['slug'], 'title' => $title, 'featured' => $v['featured'], 'wotoyi_inspected' => ! empty( $trust['wotoyi_inspected'] ), 'dealer' => $dealer, 'cover_image' => $photos[0] ?? '' ) );

$labels = array(
	'make' => __( 'Marque', 'wotoyi' ), 'model' => __( 'Modèle', 'wotoyi' ), 'trim_level' => __( 'Finition', 'wotoyi' ), 'year' => __( 'Année', 'wotoyi' ),
	'body_type' => __( 'Carrosserie', 'wotoyi' ), 'condition_type' => __( 'État', 'wotoyi' ), 'ext_colour' => __( 'Couleur extérieure', 'wotoyi' ), 'int_colour' => __( 'Couleur intérieure', 'wotoyi' ),
	'fuel_type' => __( 'Carburant', 'wotoyi' ), 'transmission' => __( 'Boîte de vitesses', 'wotoyi' ), 'drive_type' => __( 'Transmission', 'wotoyi' ), 'engine_cc' => __( 'Cylindrée (cm3)', 'wotoyi' ),
	'power_hp' => __( 'Puissance (ch)', 'wotoyi' ), 'power_kw' => __( 'Puissance (kW)', 'wotoyi' ), 'torque_nm' => __( 'Couple (Nm)', 'wotoyi' ), 'cylinders' => __( 'Cylindres', 'wotoyi' ), 'gears' => __( 'Rapports', 'wotoyi' ),
	'doors' => __( 'Portes', 'wotoyi' ), 'seats' => __( 'Places', 'wotoyi' ), 'mileage_km' => __( 'Kilométrage', 'wotoyi' ), 'owners_count' => __( 'Propriétaires', 'wotoyi' ),
	'accident_free' => __( 'Sans accident', 'wotoyi' ), 'service_book' => __( 'Carnet d\'entretien', 'wotoyi' ), 'warranty' => __( 'Garantie', 'wotoyi' ), 'warranty_months' => __( 'Garantie (mois)', 'wotoyi' ),
	'customs_status' => __( 'Dédouanement', 'wotoyi' ), 'origin_country' => __( 'Pays d\'origine', 'wotoyi' ), 'last_service_date' => __( 'Dernier entretien', 'wotoyi' ), 'technical_inspection_date' => __( 'Contrôle technique', 'wotoyi' ),
	'consumption_combined' => __( 'Consommation mixte (L/100 km)', 'wotoyi' ), 'co2_gkm' => __( 'CO2 (g/km)', 'wotoyi' ), 'euro_norm' => __( 'Norme Euro', 'wotoyi' ), 'tank_l' => __( 'Réservoir (L)', 'wotoyi' ), 'boot_l' => __( 'Coffre (L)', 'wotoyi' ),
	'top_speed_kmh' => __( 'Vitesse maxi (km/h)', 'wotoyi' ), 'accel_0_100' => __( '0 à 100 km/h (s)', 'wotoyi' ), 'electric_range_km' => __( 'Autonomie (km)', 'wotoyi' ), 'battery_kwh' => __( 'Batterie (kWh)', 'wotoyi' ),
	'length_mm' => __( 'Longueur (mm)', 'wotoyi' ), 'width_mm' => __( 'Largeur (mm)', 'wotoyi' ), 'height_mm' => __( 'Hauteur (mm)', 'wotoyi' ), 'wheelbase_mm' => __( 'Empattement (mm)', 'wotoyi' ), 'kerb_weight_kg' => __( 'Poids à vide (kg)', 'wotoyi' ),
	'city' => __( 'Ville', 'wotoyi' ), 'area' => __( 'Quartier', 'wotoyi' ), 'availability' => __( 'Disponibilité', 'wotoyi' ),
);
$enum   = array( 'body_type' => 'body', 'condition_type' => 'cond', 'fuel_type' => 'fuel', 'transmission' => 'trans', 'drive_type' => 'drive' );
$fmt    = static function ( string $k, $val ) use ( $enum ): string {
	if ( is_bool( $val ) ) {
		return $val ? __( 'Oui', 'wotoyi' ) : __( 'Non', 'wotoyi' );
	}
	if ( isset( $enum[ $k ] ) ) {
		return wotoyi_label( $enum[ $k ], (string) $val );
	}
	if ( is_int( $val ) || is_float( $val ) ) {
		return 'year' === $k ? (string) (int) $val : wotoyi_number( $val );
	}
	return is_scalar( $val ) ? (string) $val : '';
};
$spec_tabs = array(
	'identity' => array( __( 'Identité', 'wotoyi' ), array( 'make', 'model', 'trim_level', 'year', 'body_type', 'condition_type', 'ext_colour', 'int_colour', 'doors', 'seats' ) ),
	'engine'   => array( __( 'Moteur', 'wotoyi' ), array( 'fuel_type', 'transmission', 'drive_type', 'engine_cc', 'power_hp', 'power_kw', 'torque_nm', 'cylinders', 'gears', 'consumption_combined', 'co2_gkm', 'euro_norm', 'tank_l', 'boot_l', 'top_speed_kmh', 'accel_0_100', 'electric_range_km', 'battery_kwh' ) ),
	'history'  => array( __( 'Historique', 'wotoyi' ), array( 'mileage_km', 'owners_count', 'accident_free', 'service_book', 'last_service_date', 'technical_inspection_date', 'warranty', 'warranty_months', 'customs_status', 'origin_country' ) ),
	'commercial' => array( __( 'Annonce', 'wotoyi' ), array( 'city', 'area', 'availability' ) ),
);
$panels = array();
foreach ( $spec_tabs as $key => $def ) {
	$rows = array();
	foreach ( $def[1] as $f ) {
		$src = (array) ( $t[ $key ] ?? array() );
		if ( isset( $src[ $f ] ) && '' !== $src[ $f ] && ! is_array( $src[ $f ] ) ) {
			$rows[ $f ] = $fmt( $f, $src[ $f ] );
		}
	}
	if ( $rows ) {
		$panels[ $key ] = array( $def[0], $rows );
	}
}
$equip = (array) ( $t['equipment'] ?? array() );
if ( $equip ) {
	$panels['equipment'] = array( __( 'Équipements', 'wotoyi' ), array() );
}

$keyfacts = array();
if ( ! empty( $flat['year'] ) ) {
	$keyfacts[] = array( 'calendar', __( 'Année', 'wotoyi' ), (string) (int) $flat['year'] );
}
if ( isset( $flat['mileage_km'] ) ) {
	$keyfacts[] = array( 'gauge', __( 'Kilométrage', 'wotoyi' ), wotoyi_number( $flat['mileage_km'] ) . "\u{00A0}km" );
}
if ( ! empty( $flat['fuel_type'] ) ) {
	$keyfacts[] = array( 'fuel', __( 'Carburant', 'wotoyi' ), wotoyi_label( 'fuel', (string) $flat['fuel_type'] ) );
}
if ( ! empty( $flat['transmission'] ) ) {
	$keyfacts[] = array( 'shifter', __( 'Boîte', 'wotoyi' ), wotoyi_label( 'trans', (string) $flat['transmission'] ) );
}
if ( ! empty( $flat['city'] ) ) {
	$keyfacts[] = array( 'pin', __( 'Ville', 'wotoyi' ), (string) $flat['city'] );
}

$phone = preg_replace( '/[^0-9+]/', '', (string) ( $dealer['phone'] ?? '' ) );
$wa    = preg_replace( '/\D/', '', (string) ( $dealer['whatsapp'] ?? '' ) );
$self  = wotoyi_vehicle_url( array( 'slug' => $v['slug'] ) );
/* translators: %s: vehicle title */
$wa_url = '' !== $wa ? 'https://wa.me/' . $wa . '?text=' . rawurlencode( sprintf( __( 'Bonjour, je suis intéressé par : %1$s (%2$s)', 'wotoyi' ), $title, $self ) ) : '';
$durl   = (string) apply_filters( 'wotoyi_theme_dealer_url', '', $dealer );

$contact = static function () use ( $wa_url, $phone, $durl, $dealer ): string {
	$h = '<div class="wy-contact__row">';
	if ( '' !== $wa_url ) {
		$h .= '<a class="wy-btn wy-btn--whatsapp" href="' . esc_url( $wa_url ) . '" rel="noopener">' . wotoyi_icon( 'whatsapp' ) . '<span>WhatsApp</span></a>';
	}
	if ( '' !== $phone ) {
		$h .= '<a class="wy-btn wy-btn--ghost" href="tel:' . esc_attr( $phone ) . '">' . wotoyi_icon( 'phone' ) . '<span>' . esc_html__( 'Appeler', 'wotoyi' ) . '</span></a>';
	}
	$h .= '</div>';
	$h .= '<button class="wy-btn wy-btn--primary wy-btn--block" type="button" data-wy-lead-open hidden>' . wotoyi_icon( 'message' ) . '<span>' . esc_html__( 'Envoyer une demande', 'wotoyi' ) . '</span></button>';
	if ( '' === $wa_url && '' === $phone ) {
		$h = '<p class="wy-caption wy-muted">' . esc_html__( 'Contact direct non renseigné par le vendeur.', 'wotoyi' ) . '</p>';
	}
	if ( '' !== $durl ) {
		$h .= '<a class="wy-link wy-link--arrow" href="' . esc_url( $durl ) . '">' . esc_html__( 'Voir le concessionnaire', 'wotoyi' ) . ' ' . wotoyi_icon( 'arrow-right', 'wy-flip' ) . '</a>';
	}
	return $h;
};
$record = array( 'url' => $self, 'title' => $title, 'img' => $photos[0] ?? '', 'price' => ! empty( $flat['price'] ) ? wotoyi_price( $flat['price'], (string) ( $flat['currency'] ?? 'XOF' ) ) : '' );
?>
<div class="wy-container-wide wy-vehicle" data-wy-vehicle data-wy-record="<?php echo esc_attr( wp_json_encode( $record ) ); ?>">
	<nav class="wy-breadcrumb" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'wotoyi' ); ?>"><ol><li><a href="<?php echo esc_url( wotoyi_url( 'home' ) ); ?>"><?php esc_html_e( 'Accueil', 'wotoyi' ); ?></a></li><li><a href="<?php echo esc_url( wotoyi_url( 'search' ) ); ?>"><?php esc_html_e( 'Véhicules', 'wotoyi' ); ?></a></li><li aria-current="page"><?php echo esc_html( $title ); ?></li></ol></nav>
	<div class="wy-vehicle__grid">
		<div class="wy-vehicle__main">
			<section class="wy-gallery" aria-label="<?php esc_attr_e( 'Photos', 'wotoyi' ); ?>" data-wy-gallery>
				<div class="wy-gallery__panel" id="gp-photos">
					<div class="wy-gallery__stage" data-wy-stage>
						<div class="wy-gallery__badges"><?php echo wotoyi_vehicle_badges( $card, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<div class="wy-gallery__img" data-wy-main-img>
							<?php if ( $photos ) : ?><img src="<?php echo esc_url( $photos[0] ); ?>" width="1200" height="800" alt="<?php echo esc_attr( $title ); ?>" fetchpriority="high" decoding="async"><?php else : ?><span class="wy-vcard__ph" role="img" aria-label="<?php esc_attr_e( 'Photo bientôt disponible', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php endif; ?>
						</div>
						<?php if ( count( $photos ) > 1 ) : ?>
							<span class="wy-gallery__count" data-wy-gcount aria-live="polite">1 / <?php echo (int) count( $photos ); ?></span>
							<button class="wy-btn wy-btn--icon wy-gallery__fs" type="button" data-wy-lightbox-open aria-label="<?php esc_attr_e( 'Plein écran', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'fullscreen' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						<?php endif; ?>
					</div>
					<?php if ( count( $photos ) > 1 ) : ?>
						<div class="wy-gallery__thumbs" data-wy-thumbs role="list">
							<?php foreach ( array_slice( $photos, 0, 40 ) as $i => $u ) : ?>
								<button class="wy-thumb" type="button" role="listitem" data-wy-thumb data-src="<?php echo esc_url( $u ); ?>" data-full="<?php echo esc_url( $u ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: photo number */ __( 'Photo %d', 'wotoyi' ), $i + 1 ) ); ?>"><img src="<?php echo esc_url( $u ); ?>" width="160" height="110" alt="" loading="lazy" decoding="async"></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $med['view360_url'] ) ) : ?><p><a class="wy-link wy-link--arrow" href="<?php echo esc_url( (string) $med['view360_url'] ); ?>" rel="noopener"><?php esc_html_e( 'Voir en 360', 'wotoyi' ); ?> <?php echo wotoyi_icon( 'rotate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></p><?php endif; ?>
				</div>
			</section>

			<section class="wy-titleblock" data-wy-titleblock aria-labelledby="v-title">
				<h1 id="v-title" class="wy-display-m"><?php echo esc_html( $title ); ?></h1>
				<div class="wy-price-row"><?php echo wotoyi_vehicle_prices( $card, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php if ( $keyfacts ) : ?>
					<ul class="wy-keyfacts"><?php foreach ( $keyfacts as $k ) : ?><li><?php echo wotoyi_icon( $k[0] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="wy-caption wy-muted"><?php echo esc_html( $k[1] ); ?></span><span class="wy-keyfacts__v"><?php echo esc_html( $k[2] ); ?></span></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</section>

			<div class="wy-contact-inline wy-not-xl"><?php echo $contact(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

			<?php if ( $panels ) : ?>
				<section class="wy-specs" aria-label="<?php esc_attr_e( 'Caractéristiques', 'wotoyi' ); ?>">
					<div class="wy-tabs" data-wy-tabs>
						<div class="wy-tabs__bar" role="tablist" data-wy-tablist aria-label="<?php esc_attr_e( 'Caractéristiques', 'wotoyi' ); ?>">
							<?php $first = true; foreach ( $panels as $k => $pn ) : ?>
								<button class="wy-tab" role="tab" type="button" id="tab-<?php echo esc_attr( $k ); ?>" data-tab="<?php echo esc_attr( $k ); ?>" aria-controls="tp-<?php echo esc_attr( $k ); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" tabindex="<?php echo $first ? '0' : '-1'; ?>"><?php echo esc_html( $pn[0] ); ?></button>
							<?php $first = false; endforeach; ?>
						</div>
						<div class="wy-tabs__panels" data-wy-tabpanels>
							<?php $first = true; foreach ( $panels as $k => $pn ) : ?>
								<div class="wy-tabpanel" role="tabpanel" id="tp-<?php echo esc_attr( $k ); ?>" aria-labelledby="tab-<?php echo esc_attr( $k ); ?>"<?php echo $first ? '' : ' hidden'; ?>>
									<?php if ( 'equipment' === $k ) : foreach ( $equip as $cat => $codes ) : ?>
										<div class="wy-equip"><h3 class="wy-h5"><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $cat ) ) ); ?></h3><ul class="wy-equip-list"><?php foreach ( (array) $codes as $c ) : ?><li><?php echo wotoyi_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $c ) ) ); ?></li><?php endforeach; ?></ul></div>
									<?php endforeach; else : ?>
										<dl class="wy-spec-table"><?php foreach ( $pn[1] as $f => $val ) : ?><div><dt><?php echo esc_html( $labels[ $f ] ?? ucfirst( str_replace( '_', ' ', $f ) ) ); ?></dt><dd><?php echo esc_html( $val ); ?></dd></div><?php endforeach; ?></dl>
									<?php endif; ?>
								</div>
							<?php $first = false; endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $id['description'] ) ) : ?>
				<section class="wy-block" aria-labelledby="v-desc"><h2 id="v-desc" class="wy-h3"><?php esc_html_e( 'Description', 'wotoyi' ); ?></h2><div class="wy-prose"><?php echo wp_kses_post( wpautop( esc_html( (string) $id['description'] ) ) ); ?></div></section>
			<?php endif; ?>

			<?php if ( ! empty( $trust['wotoyi_inspected'] ) ) : ?>
				<section class="wy-block" aria-labelledby="v-insp"><h2 id="v-insp" class="wy-h3"><?php esc_html_e( 'Inspection Wotoyi', 'wotoyi' ); ?></h2>
					<p><?php echo wotoyi_badge( 'inspected' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
					<?php if ( ! empty( $med['inspection_report_url'] ) || ! empty( $his['inspection_report_url'] ) ) : $rep = (string) ( $med['inspection_report_url'] ?? $his['inspection_report_url'] ); ?>
						<a class="wy-link wy-link--arrow" href="<?php echo esc_url( $rep ); ?>" rel="noopener"><?php esc_html_e( 'Consulter le rapport', 'wotoyi' ); ?> <?php echo wotoyi_icon( 'arrow-right', 'wy-flip' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="wy-block" aria-labelledby="v-dealer"><h2 id="v-dealer" class="wy-h3"><?php esc_html_e( 'Vendeur', 'wotoyi' ); ?></h2>
				<?php echo wotoyi_dealer_card( array( 'name' => (string) ( $dealer['name'] ?? '' ), 'slug' => (string) ( $dealer['slug'] ?? '' ), 'logo_url' => $dealer['logo_url'] ?? '', 'verified' => ! empty( $dealer['verified'] ), 'city' => (string) ( $dealer['city'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</section>
		</div>

		<aside class="wy-vehicle__side" aria-label="<?php esc_attr_e( 'Contacter le vendeur', 'wotoyi' ); ?>"><div class="wy-contact"><?php echo $contact(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></aside>
	</div>

	<section class="wy-block wy-safety" id="securite" aria-labelledby="v-safe">
		<h2 id="v-safe" class="wy-h3"><?php echo wotoyi_icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Achetez en sécurité', 'wotoyi' ); ?></h2>
		<ul class="wy-checks">
			<li><?php echo wotoyi_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Voyez le véhicule en personne avant tout paiement.', 'wotoyi' ); ?></li>
			<li><?php echo wotoyi_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Vérifiez les papiers et le numéro de chassis.', 'wotoyi' ); ?></li>
			<li><?php echo wotoyi_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Ne payez jamais à l\'avance un vendeur que vous n\'avez pas rencontré.', 'wotoyi' ); ?></li>
		</ul>
	</section>
</div>

<?php if ( '' !== $wa_url || '' !== $phone ) : ?>
<div class="wy-cta-bar" data-wy-ctabar hidden>
	<?php if ( '' !== $wa_url ) : ?><a class="wy-btn wy-btn--whatsapp" href="<?php echo esc_url( $wa_url ); ?>" rel="noopener"><?php echo wotoyi_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>WhatsApp</span></a><?php endif; ?>
	<?php if ( '' !== $phone ) : ?><a class="wy-btn wy-btn--icon wy-btn--ghost" href="tel:<?php echo esc_attr( $phone ); ?>" aria-label="<?php esc_attr_e( 'Appeler', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a><?php endif; ?>
</div>
<?php endif; ?>
<dialog class="wy-lightbox" data-wy-lightbox aria-label="<?php esc_attr_e( 'Photos', 'wotoyi' ); ?>">
	<button class="wy-btn wy-btn--icon wy-lightbox__x" type="button" data-wy-lightbox-close aria-label="<?php esc_attr_e( 'Fermer', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
	<div class="wy-lightbox__img" data-wy-lightbox-img></div>
</dialog>
<?php
$lead_ref = (string) ( $v['id'] ?? '' );
if ( '' !== $lead_ref ) :
	?>
<dialog class="wy-modal" data-wy-lead data-wy-lead-ref="<?php echo esc_attr( $lead_ref ); ?>" data-wy-token-url="<?php echo esc_url( rest_url( 'wotoyi/v1/leads/token' ) ); ?>" data-wy-post-url="<?php echo esc_url( rest_url( 'wotoyi/v1/leads' ) ); ?>" data-wy-locale="fr" aria-labelledby="lead-title"
	data-m-wait="<?php esc_attr_e( 'Un instant, nous sécurisons le formulaire…', 'wotoyi' ); ?>"
	data-m-ok="<?php esc_attr_e( 'Demande envoyée. Le concessionnaire vous répondra directement.', 'wotoyi' ); ?>"
	data-m-contact="<?php esc_attr_e( 'Indiquez un téléphone ou un e-mail.', 'wotoyi' ); ?>"
	data-m-consent="<?php esc_attr_e( 'Cochez la case de consentement pour envoyer.', 'wotoyi' ); ?>"
	data-m-limit="<?php esc_attr_e( 'Trop de demandes. Réessayez plus tard ou contactez le vendeur sur WhatsApp.', 'wotoyi' ); ?>"
	data-m-invalid="<?php esc_attr_e( 'Vérifiez votre numéro ou votre e-mail.', 'wotoyi' ); ?>"
	data-m-fail="<?php esc_attr_e( 'Envoi impossible pour le moment. Réessayez ou contactez le vendeur sur WhatsApp.', 'wotoyi' ); ?>">
	<form class="wy-lead-form" method="dialog" novalidate data-wy-lead-form>
		<header><h2 id="lead-title" class="wy-h3"><?php esc_html_e( 'Envoyer une demande', 'wotoyi' ); ?></h2>
			<button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-lead-close aria-label="<?php esc_attr_e( 'Fermer', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button></header>
		<p class="wy-caption wy-muted"><?php echo esc_html( $title ); ?></p>
		<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Nom', 'wotoyi' ); ?></span><input class="wy-input" type="text" name="name" autocomplete="name" maxlength="120"></label>
		<div class="wy-field-row">
			<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Téléphone', 'wotoyi' ); ?></span><input class="wy-input" type="tel" name="phone" autocomplete="tel" inputmode="tel" dir="ltr"></label>
			<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'E-mail', 'wotoyi' ); ?></span><input class="wy-input" type="email" name="email" autocomplete="email" dir="ltr"></label>
		</div>
		<label class="wy-field"><span class="wy-field__l"><?php esc_html_e( 'Message', 'wotoyi' ); ?></span><textarea class="wy-input" name="message" rows="3" maxlength="2000"></textarea></label>
		<div class="wy-visually-hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
		<label class="wy-check"><input type="checkbox" name="consent" value="1"><span class="wy-caption"><?php esc_html_e( 'J’accepte que ces informations soient transmises à ce concessionnaire afin qu’il me recontacte.', 'wotoyi' ); ?></span></label>
		<p class="wy-caption" role="status" aria-live="polite" data-wy-lead-msg></p>
		<button class="wy-btn wy-btn--primary" type="submit" data-wy-lead-send><span class="wy-btn__label"><?php esc_html_e( 'Envoyer', 'wotoyi' ); ?></span></button>
	</form>
</dialog>
<?php endif; ?>
