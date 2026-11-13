<?php
/**
 * Site footer.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

$has = autobuzy_has_marketplace();

$columns = array();
if ( $has ) {
	$columns[ __( 'Marketplace', 'autobuzy' ) ] = array(
		array( __( 'All vehicles', 'autobuzy' ), amc_catalog_url() ),
		array( __( 'Cars for sale', 'autobuzy' ), amc_catalog_url( 'sale' ) ),
		array( __( 'Cars for rent', 'autobuzy' ), amc_catalog_url( 'rent' ) ),
		array( __( 'Featured vehicles', 'autobuzy' ), amc_catalog_url( 'featured' ) ),
		array( __( 'Dealers directory', 'autobuzy' ), amc_dealers_url() ),
	);
	$columns[ __( 'Company', 'autobuzy' ) ] = array(
		array( __( 'About us', 'autobuzy' ), autobuzy_page_url( 'about' ) ),
		array( __( 'Our services', 'autobuzy' ), autobuzy_page_url( 'services' ) ),
		array( __( 'How it works', 'autobuzy' ), autobuzy_page_url( 'how-it-works' ) ),
		array( __( 'FAQ', 'autobuzy' ), autobuzy_page_url( 'faq' ) ),
		array( __( 'Contact', 'autobuzy' ), autobuzy_page_url( 'contact' ) ),
	);
	$columns[ __( 'Dealers', 'autobuzy' ) ] = array(
		array( __( 'Become a dealer', 'autobuzy' ), autobuzy_page_url( 'join-dealer' ) ),
		array( __( 'Plans and prices', 'autobuzy' ), amc_portal_url( 'pricing' ) ),
		array( __( 'Apply online', 'autobuzy' ), amc_portal_url( 'register' ) ),
		array( __( 'Dealer login', 'autobuzy' ), amc_portal_url( 'login' ) ),
	);
}
$legal = $has ? array(
	array( __( 'Privacy policy', 'autobuzy' ), autobuzy_page_url( 'privacy-policy' ) ),
	array( __( 'Terms and conditions', 'autobuzy' ), autobuzy_page_url( 'terms' ) ),
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
				<p><?php echo esc_html( get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : __( 'The automotive marketplace of verified dealerships.', 'autobuzy' ) ); ?></p>
				<ul class="am-footer__contact">
					<?php if ( $address ) : ?><li><?php echo autobuzy_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $address ); ?></li><?php endif; ?>
					<?php if ( $phone ) : ?><li><?php echo autobuzy_icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( 'tel:' . amc_normalize_phone( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li><?php endif; ?>
					<?php if ( $whatsapp ) : ?><li><?php echo autobuzy_icon( 'chat', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
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
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'autobuzy' ); ?></p>
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
