<?php
/**
 * Site header.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

$join_url = autobuzy_page_url( 'join-dealer' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="am-skip screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'autobuzy' ); ?></a>

<header class="am-header">
	<div class="amds-container am-header__inner">
		<?php autobuzy_brand(); ?>

		<button class="am-header__toggle" type="button" aria-expanded="false" aria-controls="am-nav" data-am-nav-toggle>
			<span class="am-header__toggle-open"><?php echo autobuzy_icon( 'menu', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="am-header__toggle-close"><?php echo autobuzy_icon( 'close', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'autobuzy' ); ?></span>
		</button>

		<nav class="am-nav" id="am-nav" aria-label="<?php esc_attr_e( 'Main menu', 'autobuzy' ); ?>">
			<?php autobuzy_primary_nav(); ?>
			<?php if ( $join_url ) : ?>
				<a class="amds-btn amds-btn--primary am-nav__cta" href="<?php echo esc_url( $join_url ); ?>"><?php esc_html_e( 'Become a dealer', 'autobuzy' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>
