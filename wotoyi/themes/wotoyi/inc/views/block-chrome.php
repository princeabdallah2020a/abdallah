<?php
/** Overlay chrome: scrim, mobile menu drawer, bottom nav (phone), AI assistant button + panel, toasts, icon sprite. */
defined( 'ABSPATH' ) || exit;
$ai_on   = (bool) apply_filters( 'wotoyi_ai_enabled', false );
$search  = wotoyi_url( 'search' );
$prompts = (array) apply_filters( 'wotoyi_theme_ai_prompts', array( 'SUV diesel Dakar', 'Pick-up 4x4', 'Berline automatique' ) );
$view    = wotoyi_view();
$nav     = array(
	array( 'search', __( 'Acheter', 'wotoyi' ) ),
	array( 'rent', __( 'Louer', 'wotoyi' ) ),
	array( 'services', __( 'Services', 'wotoyi' ) ),
	array( 'dealers', __( 'Concessionnaires', 'wotoyi' ) ),
	array( 'inspection', __( 'Inspection', 'wotoyi' ) ),
);
?>
<div class="wy-scrim" data-wy-scrim hidden></div>

<aside class="wy-drawer wy-drawer--menu" id="wy-menu" data-wy-menu aria-label="<?php esc_attr_e( 'Menu', 'wotoyi' ); ?>" hidden>
	<div class="wy-drawer__head"><span class="wy-h4"><?php esc_html_e( 'Menu', 'wotoyi' ); ?></span><button class="wy-btn wy-btn--icon" type="button" data-wy-close aria-label="<?php esc_attr_e( 'Fermer', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button></div>
	<nav class="wy-drawer__nav" aria-label="<?php esc_attr_e( 'Navigation principale', 'wotoyi' ); ?>">
		<?php foreach ( $nav as $n ) : ?>
			<a href="<?php echo esc_url( wotoyi_url( $n[0] ) ); ?>"><?php echo esc_html( $n[1] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
	$lang = wotoyi_view_html( 'partial-lang', array() );
	if ( '' !== trim( $lang ) ) {
		echo '<div class="wy-drawer__block"><p class="wy-overline">' . esc_html__( 'Langue', 'wotoyi' ) . '</p>' . $lang . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
	<div class="wy-drawer__block"><p class="wy-overline"><?php esc_html_e( 'Theme', 'wotoyi' ); ?></p><?php echo wotoyi_view_html( 'partial-theme-seg', array( 'n' => 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<?php if ( '' !== wotoyi_url( 'pro' ) ) : ?>
		<a class="wy-btn wy-btn--primary" href="<?php echo esc_url( wotoyi_url( 'pro' ) ); ?>"><span class="wy-btn__label"><?php esc_html_e( 'Espace pro', 'wotoyi' ); ?></span></a>
	<?php endif; ?>
</aside>

<nav class="wy-bottom-nav" aria-label="<?php esc_attr_e( 'Navigation mobile', 'wotoyi' ); ?>" data-wy-bottom-nav>
	<a href="<?php echo esc_url( wotoyi_url( 'home' ) ); ?>"<?php echo 'home' === $view ? ' aria-current="page"' : ''; ?>><?php echo wotoyi_icon( 'store' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Accueil', 'wotoyi' ); ?></span></a>
	<a href="<?php echo esc_url( $search ); ?>"<?php echo 'search' === $view ? ' aria-current="page"' : ''; ?>><?php echo wotoyi_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Rechercher', 'wotoyi' ); ?></span></a>
	<button type="button" data-wy-favs-open data-href="<?php echo esc_url( wotoyi_url( 'favourites' ) ); ?>"><?php echo wotoyi_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Favoris', 'wotoyi' ); ?></span><span class="wy-count-dot" data-wy-favs-count hidden>0</span></button>
	<?php if ( '' !== wotoyi_url( 'account' ) ) : ?>
		<a href="<?php echo esc_url( wotoyi_url( 'account' ) ); ?>"><?php echo wotoyi_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Compte', 'wotoyi' ); ?></span></a>
	<?php else : ?>
		<a href="<?php echo esc_url( wotoyi_url( 'dealer_cta' ) ); ?>"><?php echo wotoyi_icon( 'store' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Pro', 'wotoyi' ); ?></span></a>
	<?php endif; ?>
</nav>

<button class="wy-ai-fab" type="button" data-wy-ai-open aria-label="<?php esc_attr_e( 'Ouvrir l\'assistant', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="wy-ai-fab__t"><?php esc_html_e( 'Assistant', 'wotoyi' ); ?></span></button>

<aside class="wy-drawer wy-drawer--ai" id="wy-ai" data-wy-ai role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Assistant Wotoyi', 'wotoyi' ); ?>" hidden>
	<div class="wy-drawer__head"><span class="wy-h4"><?php echo wotoyi_icon( 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Assistant Wotoyi', 'wotoyi' ); ?></span><button class="wy-btn wy-btn--icon" type="button" data-wy-close aria-label="<?php esc_attr_e( 'Fermer', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button></div>
	<?php
	$custom = apply_filters( 'wotoyi_ai_panel_html', '' );
	if ( is_string( $custom ) && '' !== $custom ) :
		echo '<div class="wy-drawer__scroll">' . wp_kses_post( $custom ) . '</div>';
	else :
		?>
		<div class="wy-drawer__scroll">
			<p class="wy-notice"><?php echo wotoyi_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo $ai_on ? esc_html__( 'L\'assistant est en cours de chargement.', 'wotoyi' ) : esc_html__( 'Recherche simple activée : l\'assistant IA complet n\'est pas encore configuré. Saisissez des mots-clés (marque, modèle, ville).', 'wotoyi' ); ?></span></p>
			<p class="wy-overline"><?php esc_html_e( 'Exemples', 'wotoyi' ); ?></p>
			<div class="wy-chips wy-chips--col">
				<?php foreach ( $prompts as $p ) : ?>
					<a class="wy-chip" href="<?php echo esc_url( add_query_arg( 'q', rawurlencode( (string) $p ), $search ) ); ?>"><?php echo esc_html( (string) $p ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<form class="wy-ai-form" action="<?php echo esc_url( $search ); ?>" method="get" role="search">
			<label class="wy-visually-hidden" for="wy-ai-input"><?php esc_html_e( 'Décrivez le véhicule recherché', 'wotoyi' ); ?></label>
			<input id="wy-ai-input" class="wy-input" name="q" autocomplete="off" placeholder="<?php esc_attr_e( 'Décrivez le véhicule recherché…', 'wotoyi' ); ?>">
			<button class="wy-btn wy-btn--primary wy-btn--icon" type="submit" aria-label="<?php esc_attr_e( 'Envoyer', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'send', 'wy-flip' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</form>
	<?php endif; ?>
</aside>
<div class="wy-toasts" data-wy-toasts role="status" aria-live="polite"></div>
<?php
// Sprite last: <use href="#i-*"> resolves anywhere in the document.
echo file_get_contents( WOTOYI_THEME_DIR . '/inc/sprite.svg.html' ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-authored static SVG.
