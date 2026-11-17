<?php
/** 404 content. */
defined( 'ABSPATH' ) || exit;
?>
<div class="wy-container"><div class="wy-404">
	<p class="wy-404__code" aria-hidden="true">404</p>
	<h1 class="wy-display-m"><?php esc_html_e( 'Cette page est introuvable', 'wotoyi' ); ?></h1>
	<p class="wy-lead"><?php esc_html_e( 'Le lien est peut-etre incorrect ou l\'annonce n\'est plus disponible.', 'wotoyi' ); ?></p>
	<div class="wy-row wy-row--center">
		<a class="wy-btn wy-btn--primary" href="<?php echo esc_url( wotoyi_url( 'home' ) ); ?>"><span class="wy-btn__label"><?php esc_html_e( 'Retour a l\'accueil', 'wotoyi' ); ?></span></a>
		<a class="wy-btn wy-btn--ghost" href="<?php echo esc_url( wotoyi_url( 'search' ) ); ?>"><?php esc_html_e( 'Voir les vehicules', 'wotoyi' ); ?></a>
	</div>
</div></div>
