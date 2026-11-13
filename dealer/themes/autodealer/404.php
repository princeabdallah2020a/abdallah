<?php
/**
 * 404.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="ad-page">
	<div class="amds-container">
		<div class="adc-empty">
			<?php echo autodealer_icon( 'search', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h1><?php esc_html_e( 'Page not found', 'autodealer' ); ?></h1>
			<p><?php esc_html_e( 'This vehicle may have been sold. Discover the ones still available.', 'autodealer' ); ?></p>
			<?php if ( autodealer_has_crm() ) : ?><a class="amds-btn amds-btn--primary" href="<?php echo esc_url( adc_inventory_url() ); ?>"><?php esc_html_e( 'See our vehicles', 'autodealer' ); ?></a><?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
