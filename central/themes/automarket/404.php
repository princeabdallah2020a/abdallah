<?php
/**
 * 404 page.
 *
 * @package AutoMarket
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="am-page">
	<div class="amds-container">
		<div class="amds-empty am-404">
			<?php echo automarket_icon( 'search', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h1 class="amds-empty__title"><?php esc_html_e( 'Page not found', 'automarket' ); ?></h1>
			<p><?php esc_html_e( 'This page does not exist or the vehicle is no longer listed.', 'automarket' ); ?></p>
			<?php if ( automarket_has_marketplace() ) : ?>
				<a class="amds-btn amds-btn--primary" href="<?php echo esc_url( amc_catalog_url() ); ?>"><?php esc_html_e( 'Browse all vehicles', 'automarket' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
