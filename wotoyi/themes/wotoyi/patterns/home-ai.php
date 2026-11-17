<?php
/**
 * Title: Accueil - Assistant IA (nuit)
 * Slug: wotoyi/home-ai
 * Categories: wotoyi-home
 * Inserter: yes
 * Description: Présentation de l'assistant ; les exemples renvoient vers la recherche.
 */
?>
<!-- wp:group {"tagName":"section","anchor":"ai","className":"wy-section wy-night wy-ai-show"} -->
<section class="wp-block-group wy-section wy-night wy-ai-show" id="ai"><!-- wp:html -->
<div class="wy-aurora wy-aurora--soft" aria-hidden="true"><i class="wy-aurora__l wy-aurora__l--2"></i><i class="wy-aurora__l wy-aurora__l--1"></i></div>
<!-- /wp:html -->
<!-- wp:group {"className":"wy-container wy-split wy-split--ai"} -->
<div class="wp-block-group wy-container wy-split wy-split--ai"><!-- wp:group -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"wy-overline"} -->
<p class="wy-overline"><?php echo wp_kses_post( __( 'Assistant IA', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"anchor":"h-ai","className":"wy-display-l wy-split-heading"} -->
<h2 class="wp-block-heading wy-display-l wy-split-heading" id="h-ai"><?php echo wp_kses_post( __( 'Décrivez, l’assistant filtre', 'wotoyi' ) ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"wy-lead"} -->
<p class="wy-lead"><?php echo wp_kses_post( __( 'Écrivez comme vous parleriez à un vendeur, en français, anglais ou arabe. Le wolof arrivera après relecture par des locuteurs natifs.', 'wotoyi' ) ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"is-style-wy-checks"} -->
<ul class="wp-block-list is-style-wy-checks"><!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Recherche en langage naturel', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Comparer des véhicules', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Expliquer une fiche technique ou une inspection', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( 'Passer le relais au concessionnaire sur WhatsApp', 'wotoyi' ) ); ?></li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</div>
<!-- /wp:group -->
<!-- wp:html -->
<div class="wy-ai-prompts wy-glass"><p class="wy-overline"><?php esc_html_e( 'Exemples', 'wotoyi' ); ?></p><div class="wy-chips wy-chips--col"><?php foreach ( (array) apply_filters( 'wotoyi_theme_ai_prompts', array( 'SUV diesel Dakar', 'Pick-up 4x4', 'Berline automatique' ) ) as $wy_p ) : ?><a class="wy-chip" href="<?php echo esc_url( add_query_arg( 'q', rawurlencode( (string) $wy_p ), wotoyi_url( 'search' ) ) ); ?>"><?php echo esc_html( (string) $wy_p ); ?></a><?php endforeach; ?></div></div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->
