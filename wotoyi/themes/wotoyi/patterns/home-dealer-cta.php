<?php
/**
 * Title: Accueil - Appel aux concessionnaires
 * Slug: wotoyi/home-dealer-cta
 * Categories: wotoyi-home
 * Inserter: yes
 * Description: Argumentaire Wotoyi Pro avec maquette schématique sans données.
 */
?>
<!-- wp:group {"tagName":"section","anchor":"dealer-cta","className":"wy-section wy-section--sand"} -->
<section class="wp-block-group wy-section wy-section--sand" id="dealer-cta"><!-- wp:group {"className":"wy-container wy-split"} -->
<div class="wp-block-group wy-container wy-split"><!-- wp:group -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"wy-overline"} -->
<p class="wy-overline"><?php echo wp_kses_post( __( 'Pour les professionnels', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"anchor":"h-dcta","className":"wy-display-l wy-split-heading"} -->
<h2 class="wp-block-heading wy-display-l wy-split-heading" id="h-dcta"><?php echo wp_kses_post( __( 'Vous êtes concessionnaire ?', 'wotoyi' ) ); ?></h2>
<!-- /wp:heading -->
<!-- wp:html -->
<ul class="wy-benefits"><li><svg class="wy-i" aria-hidden="true"><use href="#i-globe"/></svg><div><strong><?php echo wp_kses_post( __( 'Votre propre site web', 'wotoyi' ) ); ?></strong><span><?php echo wp_kses_post( __( 'Domaine, hébergement et marque à vous.', 'wotoyi' ) ); ?></span></div></li><li><svg class="wy-i" aria-hidden="true"><use href="#i-send"/></svg><div><strong><?php echo wp_kses_post( __( 'Publication automatique sur Wotoyi', 'wotoyi' ) ); ?></strong><span><?php echo wp_kses_post( __( 'Un véhicule saisi une fois apparaît sur les deux.', 'wotoyi' ) ); ?></span></div></li><li><svg class="wy-i" aria-hidden="true"><use href="#i-sparkle"/></svg><div><strong><?php echo wp_kses_post( __( 'CRM et IA', 'wotoyi' ) ); ?></strong><span><?php echo wp_kses_post( __( 'Prospects, tâches et réponses assistées au même endroit.', 'wotoyi' ) ); ?></span></div></li></ul>
<!-- /wp:html -->
<?php $wy_pro = wotoyi_url( 'pro' ); if ( '' !== $wy_pro ) : ?>
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $wy_pro ); ?>"><?php esc_html_e( 'Découvrir Wotoyi Pro', 'wotoyi' ); ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
<?php endif; ?>
</div>
<!-- /wp:group -->
<!-- wp:html -->
<figure class="wy-mock" aria-label="<?php esc_attr_e( 'Maquette schématique de Wotoyi Pro, sans données', 'wotoyi' ); ?>"><div class="wy-mock__bar"><i></i><i></i><i></i><span class="wy-caption">Wotoyi Pro</span></div><div class="wy-mock__body"><div class="wy-mock__rail"><i></i><i class="is-on"></i><i></i><i></i><i></i></div><div class="wy-mock__main"><div class="wy-mock__kpis"><div><span class="wy-caption"><?php echo wp_kses_post( __( 'Nouveaux prospects', 'wotoyi' ) ); ?></span><b></b></div><div><span class="wy-caption"><?php echo wp_kses_post( __( 'Tâches du jour', 'wotoyi' ) ); ?></span><b></b></div><div><span class="wy-caption"><?php echo wp_kses_post( __( 'Véhicules à mettre à jour', 'wotoyi' ) ); ?></span><b></b></div></div><div class="wy-mock__list"><div><i></i><span></span><em></em></div><div><i></i><span></span><em></em></div><div><i></i><span></span><em></em></div></div></div></div><figcaption class="wy-caption wy-muted"><?php echo wp_kses_post( __( 'Maquette schématique de Wotoyi Pro, sans données', 'wotoyi' ) ); ?></figcaption></figure>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->
