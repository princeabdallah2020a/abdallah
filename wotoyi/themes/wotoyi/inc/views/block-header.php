<?php
/** Sticky glass header: logo, primary nav, language/theme/favourites/account, "Espace pro" CTA, mobile menu button. */
defined( 'ABSPATH' ) || exit;
$tone = 'home' === wotoyi_view() ? ' data-tone="night"' : '';
$nav  = array(
	array( 'search', __( 'Acheter', 'wotoyi' ) ),
	array( 'rent', __( 'Louer', 'wotoyi' ) ),
	array( 'services', __( 'Services', 'wotoyi' ) ),
	array( 'dealers', __( 'Concessionnaires', 'wotoyi' ) ),
	array( 'inspection', __( 'Inspection', 'wotoyi' ) ),
);
$pro = wotoyi_url( 'pro' );
$acc = wotoyi_url( 'account' );
$fav = wotoyi_url( 'favourites' );
?>
<a class="wy-skip" href="#main"><?php esc_html_e( 'Aller au contenu', 'wotoyi' ); ?></a>
<header class="wy-header" data-wy-header<?php echo $tone; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="wy-header__in wy-container-wide">
		<?php echo wotoyi_view_html( 'block-logo', array( 'attrs' => array( 'variant' => 'h' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<nav class="wy-nav" aria-label="<?php esc_attr_e( 'Navigation principale', 'wotoyi' ); ?>">
			<?php foreach ( $nav as $n ) : ?>
				<a href="<?php echo esc_url( wotoyi_url( $n[0] ) ); ?>"><?php echo esc_html( $n[1] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="wy-header__tools">
			<?php echo wotoyi_view_html( 'partial-lang', array( 'extra_class' => 'wy-only-desktop' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<a class="wy-btn wy-btn--icon wy-only-phone" href="<?php echo esc_url( wotoyi_url( 'search' ) ); ?>" aria-label="<?php esc_attr_e( 'Rechercher', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<button class="wy-btn wy-btn--icon" type="button" data-wy-theme-toggle aria-label="<?php esc_attr_e( 'Changer de theme clair / sombre', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'sun', 'wy-i--sun' ) . wotoyi_icon( 'moon', 'wy-i--moon' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<button class="wy-btn wy-btn--icon wy-only-desktop" type="button" data-wy-favs-open data-href="<?php echo esc_url( $fav ); ?>" aria-label="<?php esc_attr_e( 'Favoris', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="wy-count-dot" data-wy-favs-count hidden>0</span></button>
			<?php if ( '' !== $acc ) : ?>
				<a class="wy-btn wy-btn--icon wy-only-desktop" href="<?php echo esc_url( $acc ); ?>" aria-label="<?php esc_attr_e( 'Mon compte', 'wotoyi' ); ?>"><?php echo wotoyi_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php endif; ?>
			<?php if ( '' !== $pro ) : ?>
				<a class="wy-btn wy-btn--primary wy-btn--sm wy-only-desktop" href="<?php echo esc_url( $pro ); ?>" data-motion="magnetic"><span class="wy-btn__label"><?php esc_html_e( 'Espace pro', 'wotoyi' ); ?></span></a>
			<?php endif; ?>
			<button class="wy-btn wy-btn--icon wy-not-desktop" type="button" data-wy-menu-open aria-label="<?php esc_attr_e( 'Menu', 'wotoyi' ); ?>" aria-expanded="false" aria-controls="wy-menu"><?php echo wotoyi_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
	</div>
</header>
<div data-wy-sentinel aria-hidden="true"></div>
