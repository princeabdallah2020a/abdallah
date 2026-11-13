<?php
/**
 * Financing information page (optional).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$text = (string) ADC\Settings::get( 'financing_text' );
get_header();
?>
<main id="main" class="adc-main">
	<header class="adc-page-head">
		<div class="amds-container">
			<p class="adc-eyebrow"><?php esc_html_e( 'Financing', 'automotive-dealer-crm' ); ?></p>
			<h1 class="adc-page-head__title"><?php esc_html_e( 'Finance your vehicle', 'automotive-dealer-crm' ); ?></h1>
		</div>
	</header>
	<div class="amds-container adc-split">
		<div class="adc-prose"><?php echo $text ? wp_kses_post( wpautop( esc_html( $text ) ) ) : '<p>' . esc_html__( 'Contact us to discuss the financing solutions available with our partners.', 'automotive-dealer-crm' ) . '</p>'; ?></div>
		<div class="adc-summary"><?php adc_template( 'public/parts/lead-form', array( 'title' => __( 'Ask for a financing study', 'automotive-dealer-crm' ) ) ); ?></div>
	</div>
</main>
<?php
get_footer();
