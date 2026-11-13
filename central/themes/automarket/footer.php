<?php
/**
 * Site footer.
 *
 * @package AutoMarket
 */

defined( 'ABSPATH' ) || exit;

$has = automarket_has_marketplace();

$columns = array();
if ( $has ) {
	$columns[ __( 'Marketplace', 'automarket' ) ] = array(
		array( __( 'All vehicles', 'automarket' ), amc_catalog_url() ),
		array( __( 'Cars for sale', 'automarket' ), amc_catalog_url( 'sale' ) ),
		array( __( 'Cars for rent', 'automarket' ), amc_catalog_url( 'rent' ) ),
		array( __( 'Featured vehicles', 'automarket' ), amc_catalog_url( 'featured' ) ),
		array( __( 'Dealers directory', 'automarket' ), amc_dealers_url() ),
	);
	$columns[ __( 'Company', 'automarket' ) ] = array(
		array( __( 'About us', 'automarket' ), automarket_page_url( 'about' ) ),
		array( __( 'Our services', 'automarket' ), automarket_page_url( 'services' ) ),
		array( __( 'How it works', 'automarket' ), automarket_page_url( 'how-it-works' ) ),
		array( __( 'FAQ', 'automarket' ), automarket_page_url( 'faq' ) ),
		array( __( 'Contact', 'automarket' ), automarket_page_url( 'contact' ) ),
	);
	$columns[ __( 'Dealers', 'automarket' ) ] = array(
		array( __( 'Become a dealer', 'automarket' ), automarket_page_url( 'join-dealer' ) ),
	);
}
$legal = $has ? array(
	array( __( 'Privacy policy', 'automarket' ), automarket_page_url( 'privacy-policy' ) ),
	array( __( 'Terms and conditions', 'automarket' ), automarket_page_url( 'terms' ) ),
) : array();

$phone    = $has ? AMC\Settings::get( 'company_phone' ) : '';
$whatsapp = $has ? amc_whatsapp_url( AMC\Settings::get( 'company_whatsapp' ) ) : '';
$address  = $has ? AMC\Settings::get( 'company_address' ) : '';
?>
<footer class="am-footer">
	<div class="amds-container">
		<div class="am-footer__grid">
			<div class="am-footer__about">
				<p class="am-footer__brand"><?php bloginfo( 'name' ); ?></p>
				<p><?php echo esc_html( get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : __( 'The automotive marketplace of verified dealerships.', 'automarket' ) ); ?></p>
				<ul class="am-footer__contact">
					<?php if ( $address ) : ?><li><?php echo automarket_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $address ); ?></li><?php endif; ?>
					<?php if ( $phone ) : ?><li><?php echo automarket_icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( 'tel:' . amc_normalize_phone( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li><?php endif; ?>
					<?php if ( $whatsapp ) : ?><li><?php echo automarket_icon( 'chat', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
				</ul>
			</div>
			<?php foreach ( $columns as $title => $links ) : ?>
				<nav class="am-footer__col" aria-label="<?php echo esc_attr( $title ); ?>">
					<h2 class="am-footer__title"><?php echo esc_html( $title ); ?></h2>
					<ul>
						<?php foreach ( $links as $link ) : ?>
							<?php if ( $link[1] ) : ?>
								<li><a href="<?php echo esc_url( $link[1] ); ?>"><?php echo esc_html( $link[0] ); ?></a></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
		</div>
		<div class="am-footer__bottom">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'automarket' ); ?></p>
			<ul>
				<?php foreach ( $legal as $link ) : ?>
					<?php if ( $link[1] ) : ?>
						<li><a href="<?php echo esc_url( $link[1] ); ?>"><?php echo esc_html( $link[0] ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
