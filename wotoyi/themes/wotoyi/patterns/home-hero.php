<?php
/**
 * Title: Accueil - Hero nuit avec recherche rapide
 * Slug: wotoyi/home-hero
 * Categories: wotoyi-home
 * Inserter: yes
 * Description: Hero cinématique (nuit, aurore, voiture) et formulaire de recherche rapide.
 */
?>
<!-- wp:group {"tagName":"section","className":"wy-hero wy-night"} -->
<section class="wp-block-group wy-hero wy-night"><!-- wp:html -->
<div class="wy-aurora" aria-hidden="true" data-wy-aurora><i class="wy-aurora__l wy-aurora__l--1"></i><i class="wy-aurora__l wy-aurora__l--2"></i><i class="wy-aurora__l wy-aurora__l--3"></i></div>
<!-- /wp:html -->
<!-- wp:group {"className":"wy-container wy-hero__in"} -->
<div class="wp-block-group wy-container wy-hero__in"><!-- wp:group {"className":"wy-hero__copy"} -->
<div class="wp-block-group wy-hero__copy"><!-- wp:paragraph {"className":"wy-overline"} -->
<p class="wy-overline"><?php echo wp_kses_post( __( 'Marketplace automobile · Sénégal', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1,"anchor":"h1","className":"wy-display-xl wy-hero__h1"} -->
<h1 class="wp-block-heading wy-display-xl wy-hero__h1" id="h1" data-wy-sheen><?php echo wp_kses_post( __( 'Le showroom automobile du Sénégal, <em>en ligne</em>.', 'wotoyi' ) ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"wy-lead"} -->
<p class="wy-lead"><?php echo wp_kses_post( __( 'Achetez ou louez un véhicule auprès de concessionnaires vérifiés, faites-le inspecter par un tiers indépendant, puis contactez le vendeur directement sur WhatsApp.', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:html -->
<div class="wy-hero__car"><div class="wy-hero__layer wy-hero__layer--back" data-wy-parallax="-1"><div class="wy-hero__sun"></div></div><div class="wy-hero__layer wy-hero__layer--car" data-wy-parallax="1"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/hero-suv.svg' ) ); ?>" width="660" height="260" alt="<?php esc_attr_e( 'Illustration provisoire d’un véhicule gris argent', 'wotoyi' ); ?>" fetchpriority="high" decoding="async"></div><div class="wy-hero__floor" data-wy-parallax="2"></div></div>
<!-- /wp:html -->
<!-- wp:wotoyi/quicksearch /-->
</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->
