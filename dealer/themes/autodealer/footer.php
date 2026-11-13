<?php
/**
 * Footer.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

$b = autodealer_business();
?>
<footer class="ad-footer">
	<div class="amds-container ad-footer__grid">
		<div>
			<p class="ad-footer__name"><?php echo esc_html( $b->name ); ?></p>
			<?php if ( $b->tagline ) : ?><p><?php echo esc_html( $b->tagline ); ?></p><?php endif; ?>
			<div class="ad-footer__social">
				<?php if ( $b->facebook ) : ?><a href="<?php echo esc_url( $b->facebook ); ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
				<?php if ( $b->instagram ) : ?><a href="<?php echo esc_url( $b->instagram ); ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
			</div>
		</div>
		<nav aria-label="<?php esc_attr_e( 'Footer', 'autodealer' ); ?>">
			<h2 class="ad-footer__title"><?php esc_html_e( 'Navigation', 'autodealer' ); ?></h2>
			<ul>
				<?php foreach ( autodealer_nav() as $item ) : ?>
					<li><a href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[0] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div>
			<h2 class="ad-footer__title"><?php esc_html_e( 'Contact', 'autodealer' ); ?></h2>
			<ul class="ad-footer__contact">
				<?php if ( $b->address || $b->city ) : ?><li><?php echo autodealer_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( trim( $b->address . ', ' . $b->city, ', ' ) ); ?></li><?php endif; ?>
				<?php if ( $b->phone ) : ?><li><?php echo autodealer_icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo esc_html( $b->phone ); ?></a></li><?php endif; ?>
				<?php if ( $b->email ) : ?><li><?php echo autodealer_icon( 'mail', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( 'mailto:' . $b->email ); ?>"><?php echo esc_html( $b->email ); ?></a></li><?php endif; ?>
				<?php if ( $b->hours ) : ?><li><?php echo autodealer_icon( 'clock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $b->hours ); ?></li><?php endif; ?>
			</ul>
		</div>
	</div>
	<div class="amds-container ad-footer__bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $b->name ); ?></p>
		<p>
			<?php if ( autodealer_has_crm() ) : ?><a href="<?php echo esc_url( adc_url( 'manager/login' ) ); ?>" rel="nofollow"><?php esc_html_e( 'Staff area', 'autodealer' ); ?></a> · <?php endif; ?>
			<?php esc_html_e( 'Website by AutoBuzy', 'autodealer' ); ?>
		</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
