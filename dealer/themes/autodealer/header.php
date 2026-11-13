<?php
/**
 * Header.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

$b        = autodealer_business();
$whatsapp = function_exists( 'adc_whatsapp_url' ) ? adc_whatsapp_url( $b->whatsapp ) : '';
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
<a class="ad-skip screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'autodealer' ); ?></a>
<?php if ( $b->phone || $b->hours ) : ?>
	<div class="ad-topbar">
		<div class="amds-container ad-topbar__inner">
			<?php if ( $b->hours ) : ?><span><?php echo autodealer_icon( 'clock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $b->hours ); ?></span><?php endif; ?>
			<?php if ( $b->address || $b->city ) : ?><span class="ad-topbar__address"><?php echo autodealer_icon( 'pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( trim( $b->address . ', ' . $b->city, ', ' ) ); ?></span><?php endif; ?>
			<?php if ( $b->phone ) : ?><a href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo autodealer_icon( 'phone', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $b->phone ); ?></a><?php endif; ?>
		</div>
	</div>
<?php endif; ?>
<header class="ad-header">
	<div class="amds-container ad-header__inner">
		<?php autodealer_brand(); ?>
		<button class="ad-header__toggle" type="button" aria-expanded="false" aria-controls="ad-nav" data-ad-nav-toggle>
			<?php echo autodealer_icon( 'menu', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'autodealer' ); ?></span>
		</button>
		<nav class="ad-nav" id="ad-nav" aria-label="<?php esc_attr_e( 'Main menu', 'autodealer' ); ?>">
			<ul class="ad-nav__list">
				<?php foreach ( autodealer_nav() as $item ) : ?>
					<li><a href="<?php echo esc_url( $item[1] ); ?>"<?php echo $item[2] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item[0] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $whatsapp ) : ?>
				<a class="amds-btn amds-btn--whatsapp ad-nav__cta" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener"><?php echo autodealer_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>WhatsApp</a>
			<?php elseif ( $b->phone ) : ?>
				<a class="amds-btn amds-btn--accent ad-nav__cta" href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo autodealer_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Call us', 'autodealer' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>
