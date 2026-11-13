<?php
/**
 * 404 page.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="am-page">
	<div class="amds-container">
		<div class="amds-empty am-404">
			<?php echo autobuzy_icon( 'search', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h1 class="amds-empty__title"><?php esc_html_e( 'Page not found', 'autobuzy' ); ?></h1>
			<p><?php esc_html_e( 'This page does not exist or the vehicle is no longer listed.', 'autobuzy' ); ?></p>
			<?php if ( autobuzy_has_marketplace() ) : ?>
				<a class="amds-btn amds-btn--primary" href="<?php echo esc_url( amc_catalog_url() ); ?>"><?php esc_html_e( 'Browse all vehicles', 'autobuzy' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
