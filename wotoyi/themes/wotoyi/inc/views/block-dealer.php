<?php
/** Dealer page: profile + their published vehicles. Data: wotoyi_mp_dealer() / wotoyi_mp_search( dealer ). */
defined( 'ABSPATH' ) || exit;
$ref = (string) get_query_var( 'wotoyi_ref' );
$d   = wotoyi_mp_dealer( $ref );
if ( ! $d ) {
	$active = wotoyi_marketplace_active();
	echo '<div class="wy-container">' . wotoyi_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
		$active ? __( 'Concessionnaire introuvable', 'wotoyi' ) : __( 'Le catalogue arrive bientot', 'wotoyi' ),
		$active ? __( 'Ce profil n\'existe pas ou n\'est plus actif.', 'wotoyi' ) : __( 'La place de marche n\'est pas encore activee sur ce site.', 'wotoyi' ),
		array( array( 'url' => wotoyi_url( 'home' ), 'label' => __( 'Retour a l\'accueil', 'wotoyi' ), 'class' => 'wy-btn--primary' ) )
	) . '</div>';
	return;
}
$res   = wotoyi_mp_search( array( 'dealer' => (string) $d['id'], 'per_page' => 24 ) );
$items = (array) $res['items'];
$name  = (string) $d['name'];
$logo  = ! empty( $d['logo_url'] ) ? '<span class="wy-dcard__logo" aria-hidden="true"><img src="' . esc_url( (string) $d['logo_url'] ) . '" alt="" width="88" height="88" decoding="async"></span>' : '<span class="wy-dcard__logo" aria-hidden="true">' . esc_html( wotoyi_initials( $name ) ) . '</span>';
$phone = preg_replace( '/[^0-9+]/', '', (string) ( $d['phone'] ?? '' ) );
$wa    = preg_replace( '/\D/', '', (string) ( $d['whatsapp'] ?? '' ) );
$loc   = trim( implode( ', ', array_filter( array( (string) ( $d['area'] ?? '' ), (string) ( $d['city'] ?? '' ) ) ) ) );
?>
<section class="wy-dealer-cover wy-night" aria-labelledby="h-dealer">
	<div class="wy-container wy-dealer-cover__in">
		<div class="wy-dealer-cover__id">
			<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div>
				<h1 id="h-dealer" class="wy-display-m"><?php echo esc_html( $name ); ?></h1>
				<?php if ( ! empty( $d['verified'] ) ) : ?><div class="wy-badges"><?php echo wotoyi_badge( 'verified' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
				<?php if ( '' !== $loc ) : ?><p class="wy-caption"><?php echo wotoyi_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $loc ); ?></p><?php endif; ?>
			</div>
		</div>
		<div class="wy-row">
			<?php if ( '' !== $wa ) : ?><a class="wy-btn wy-btn--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . $wa ); ?>" rel="noopener"><?php echo wotoyi_icon( 'whatsapp' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span>WhatsApp</span></a><?php endif; ?>
			<?php if ( '' !== $phone ) : ?><a class="wy-btn wy-btn--ghost" href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo wotoyi_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Appeler', 'wotoyi' ); ?></span></a><?php endif; ?>
			<?php if ( ! empty( $d['website_url'] ) ) : ?><a class="wy-btn wy-btn--ghost" href="<?php echo esc_url( (string) $d['website_url'] ); ?>" rel="noopener"><?php echo wotoyi_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Site web', 'wotoyi' ); ?></span></a><?php endif; ?>
		</div>
	</div>
</section>
<div class="wy-container wy-dealer-body">
	<dl class="wy-dealer-figures">
		<div><dt><?php esc_html_e( 'Vehicules en ligne', 'wotoyi' ); ?></dt><dd><?php echo esc_html( wotoyi_number( $res['total'] ) ); ?></dd></div>
		<?php if ( ! empty( $d['languages'] ) ) : ?><div><dt><?php esc_html_e( 'Langues', 'wotoyi' ); ?></dt><dd><?php echo esc_html( strtoupper( implode( ' / ', array_map( 'sanitize_key', (array) $d['languages'] ) ) ) ); ?></dd></div><?php endif; ?>
	</dl>
	<?php if ( ! empty( $d['description'] ) ) : ?><section aria-labelledby="h-about"><h2 id="h-about" class="wy-h3"><?php esc_html_e( 'A propos', 'wotoyi' ); ?></h2><div class="wy-prose"><?php echo wp_kses_post( wpautop( esc_html( (string) $d['description'] ) ) ); ?></div></section><?php endif; ?>
	<section aria-labelledby="h-stock">
		<h2 id="h-stock" class="wy-h3"><?php esc_html_e( 'Vehicules disponibles', 'wotoyi' ); ?></h2>
		<?php if ( $items ) : ?>
			<div class="wy-grid wy-grid--3"><?php foreach ( $items as $i => $v ) { echo wotoyi_vehicle_card( (array) $v, array( 'eager' => $i < 2 ) ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php else : echo wotoyi_empty_state( __( 'Aucun vehicule en ligne', 'wotoyi' ), __( 'Ce concessionnaire n\'a pas encore publie d\'annonce.', 'wotoyi' ) ); endif; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</section>
</div>
