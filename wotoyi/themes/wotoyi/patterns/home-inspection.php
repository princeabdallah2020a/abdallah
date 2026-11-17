<?php
/**
 * Title: Accueil - Inspection indépendante
 * Slug: wotoyi/home-inspection
 * Categories: wotoyi-home
 * Inserter: yes
 * Description: Texte, liste de contrôle, avertissement et exemple de rapport (étiqueté illustratif).
 */
?>
<!-- wp:group {"tagName":"section","anchor":"inspection","className":"wy-section"} -->
<section class="wp-block-group wy-section" id="inspection"><!-- wp:group {"className":"wy-container wy-split"} -->
<div class="wp-block-group wy-container wy-split"><!-- wp:group {"className":"wy-split__copy"} -->
<div class="wp-block-group wy-split__copy"><!-- wp:paragraph {"className":"wy-overline"} -->
<p class="wy-overline"><?php echo wp_kses_post( __( 'Inspection indépendante', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"anchor":"h-insp","className":"wy-display-l wy-split-heading"} -->
<h2 class="wp-block-heading wy-display-l wy-split-heading" id="h-insp"><?php echo wp_kses_post( __( 'Faites inspecter avant d’acheter', 'wotoyi' ) ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"wy-lead"} -->
<p class="wy-lead"><?php echo wp_kses_post( __( 'Un inspecteur sans lien avec le vendeur examine le véhicule selon une liste de contrôle et vous remet un rapport PDF daté, avec photos et résultats par point.', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"is-style-wy-checks"} -->
<ul class="wp-block-list is-style-wy-checks"><!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Extérieur et intérieur : carrosserie, peinture, vitres, pneus, usure', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Mécanique : moteur, boîte, suspension, freins, fuites', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Diagnostic électronique : codes défaut OBD', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Cohérence du kilométrage : indicateurs uniquement', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Documents présentés : carte grise, pièces douanières', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Essai routier, lorsqu’il est possible et autorisé', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
<!-- wp:html -->
<p class="wy-notice"><svg class="wy-i" aria-hidden="true"><use href="#i-info"/></svg><span><?php echo wp_kses_post( __( 'Le rapport décrit l’état observé à la date de l’inspection avec les méthodes listées. Il ne garantit ni l’authenticité du kilométrage, ni l’absence d’accident passé, ni l’absence de défauts cachés.', 'wotoyi' ) ); ?></span></p>
<!-- /wp:html -->
<?php $wy_c = wotoyi_url( 'contact' ); if ( '' !== $wy_c ) : ?>
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $wy_c ); ?>"><?php esc_html_e( 'Commander une inspection', 'wotoyi' ); ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
<?php endif; ?>
</div>
<!-- /wp:group -->
<!-- wp:html -->
<figure class="wy-report" aria-label="<?php esc_attr_e( 'Exemple illustratif', 'wotoyi' ); ?>"><figcaption class="wy-report__cap"><span class="wy-badge wy-badge--demo"><?php echo wp_kses_post( __( 'Exemple illustratif', 'wotoyi' ) ); ?></span></figcaption><div class="wy-report__head"><svg class="wy-i" aria-hidden="true"><use href="#i-clipboard"/></svg><div><strong><?php echo wp_kses_post( __( 'Rapport d’inspection', 'wotoyi' ) ); ?></strong><span class="wy-caption wy-muted"><?php echo wp_kses_post( __( 'Réf. et date : exemple', 'wotoyi' ) ); ?></span></div></div><ul class="wy-report__list"><li><span><?php echo wp_kses_post( __( 'Carrosserie et peinture', 'wotoyi' ) ); ?></span><span class="wy-pill wy-pill--ok"><?php echo wp_kses_post( __( 'Conforme', 'wotoyi' ) ); ?></span></li><li><span><?php echo wp_kses_post( __( 'Freinage', 'wotoyi' ) ); ?></span><span class="wy-pill wy-pill--ok"><?php echo wp_kses_post( __( 'Conforme', 'wotoyi' ) ); ?></span></li><li><span><?php echo wp_kses_post( __( 'Pneus', 'wotoyi' ) ); ?></span><span class="wy-pill wy-pill--warn"><?php echo wp_kses_post( __( 'À surveiller', 'wotoyi' ) ); ?></span></li><li><span><?php echo wp_kses_post( __( 'Diagnostic OBD', 'wotoyi' ) ); ?></span><span class="wy-pill wy-pill--ok"><?php echo wp_kses_post( __( 'Conforme', 'wotoyi' ) ); ?></span></li><li><span><?php echo wp_kses_post( __( 'Kilométrage (indicateurs)', 'wotoyi' ) ); ?></span><span class="wy-pill wy-pill--info"><?php echo wp_kses_post( __( 'Indicateur', 'wotoyi' ) ); ?></span></li></ul><p class="wy-caption wy-muted"><?php echo wp_kses_post( __( 'Mise en page indicative : le rapport réel suit le modèle validé avec les inspecteurs.', 'wotoyi' ) ); ?></p></figure>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->
