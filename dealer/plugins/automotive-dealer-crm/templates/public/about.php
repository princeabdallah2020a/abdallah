<?php
/**
 * About page (text edited in the CRM settings).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$b    = adc_business();
$text = (string) ADC\Settings::get( 'about_text' );
$hero = (int) ADC\Settings::get( 'hero_image_id' );
get_header();
?>
<main id="main" class="adc-main">
	<header class="adc-page-head">
		<div class="amds-container">
			<p class="adc-eyebrow"><?php esc_html_e( 'About us', 'automotive-dealer-crm' ); ?></p>
			<h1 class="adc-page-head__title"><?php echo esc_html( $b->name ); ?></h1>
			<?php if ( $b->tagline ) : ?><p class="adc-page-head__lead"><?php echo esc_html( $b->tagline ); ?></p><?php endif; ?>
		</div>
	</header>
	<div class="amds-container adc-split">
		<div class="adc-prose">
			<?php echo $text ? wp_kses_post( wpautop( esc_html( $text ) ) ) : '<p>' . esc_html__( 'Welcome to our dealership. Our team is happy to help you find the right vehicle, for sale or for rent.', 'automotive-dealer-crm' ) . '</p>'; ?>
			<p><a class="amds-btn amds-btn--primary" href="<?php echo esc_url( adc_inventory_url() ); ?>"><?php esc_html_e( 'See our vehicles', 'automotive-dealer-crm' ); ?></a></p>
		</div>
		<?php if ( $hero ) : ?>
			<figure class="adc-split__media"><?php echo wp_get_attachment_image( $hero, 'large', false, array( 'loading' => 'lazy' ) ); ?></figure>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
