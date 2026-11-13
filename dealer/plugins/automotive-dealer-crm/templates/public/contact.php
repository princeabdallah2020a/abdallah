<?php
/**
 * Contact page.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$b = adc_business();
get_header();
?>
<main id="main" class="adc-main">
	<header class="adc-page-head">
		<div class="amds-container">
			<p class="adc-eyebrow"><?php esc_html_e( 'Contact', 'automotive-dealer-crm' ); ?></p>
			<h1 class="adc-page-head__title"><?php esc_html_e( 'Let\'s talk about your next vehicle', 'automotive-dealer-crm' ); ?></h1>
		</div>
	</header>
	<div class="amds-container adc-split">
		<div>
			<ul class="adc-contact">
				<?php if ( $b->phone ) : ?><li><?php echo adc_icon( 'phone', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Phone', 'automotive-dealer-crm' ); ?></small><a href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo esc_html( $b->phone ); ?></a></span></li><?php endif; ?>
				<?php if ( $b->whatsapp ) : ?><li><?php echo adc_icon( 'chat', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small>WhatsApp</small><a href="<?php echo esc_url( adc_whatsapp_url( $b->whatsapp ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $b->whatsapp ); ?></a></span></li><?php endif; ?>
				<?php if ( $b->email ) : ?><li><?php echo adc_icon( 'mail', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Email', 'automotive-dealer-crm' ); ?></small><a href="<?php echo esc_url( 'mailto:' . $b->email ); ?>"><?php echo esc_html( $b->email ); ?></a></span></li><?php endif; ?>
				<?php if ( $b->address || $b->city ) : ?><li><?php echo adc_icon( 'pin', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Address', 'automotive-dealer-crm' ); ?></small><?php echo esc_html( trim( $b->address . ', ' . $b->city, ', ' ) ); ?><?php if ( $b->map_url ) : ?> <a href="<?php echo esc_url( $b->map_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the map', 'automotive-dealer-crm' ); ?></a><?php endif; ?></span></li><?php endif; ?>
				<?php if ( $b->hours ) : ?><li><?php echo adc_icon( 'clock', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><small><?php esc_html_e( 'Opening hours', 'automotive-dealer-crm' ); ?></small><?php echo esc_html( $b->hours ); ?></span></li><?php endif; ?>
			</ul>
		</div>
		<div class="adc-summary"><?php adc_template( 'public/parts/lead-form', array( 'title' => __( 'Send us a message', 'automotive-dealer-crm' ) ) ); ?></div>
	</div>
</main>
<?php
get_footer();
