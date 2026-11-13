<?php
/**
 * CRM — business profile and website appearance.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;
use ADC\Settings;

if ( ! current_user_can( 'adc_manage_settings' ) ) {
	return;
}
$s      = Settings::all();
$colors = Settings::colors();
Manager::head( __( 'Settings', 'automotive-dealer-crm' ), __( 'Everything you change here updates your website immediately. No coding needed.', 'automotive-dealer-crm' ), Manager::link_button( home_url( '/' ), __( 'View my website', 'automotive-dealer-crm' ), 'amds-btn--secondary', 'external' ) );
?>
<form class="amds-form" method="post" enctype="multipart/form-data">
	<?php Manager::action_fields( 'settings_save' ); ?>
	<div class="adc-form-layout">
		<div class="adc-form-layout__main">
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Business', 'automotive-dealer-crm' ); ?></h2></div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'business_name', __( 'Business name', 'automotive-dealer-crm' ), $s['business_name'], 'text', array( 'attrs' => array( 'required' => true ) ) );
					Manager::field( 'tagline', __( 'Tagline', 'automotive-dealer-crm' ), $s['tagline'], 'text', array( 'attrs' => array( 'placeholder' => __( 'Reliable vehicles in Dakar since 2010', 'automotive-dealer-crm' ) ) ) );
					?>
				</div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'phone', __( 'Phone', 'automotive-dealer-crm' ), $s['phone'], 'tel' );
					Manager::field( 'whatsapp', 'WhatsApp', $s['whatsapp'], 'tel' );
					Manager::field( 'email', __( 'Public email', 'automotive-dealer-crm' ), $s['email'], 'email' );
					?>
				</div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'address', __( 'Address', 'automotive-dealer-crm' ), $s['address'] );
					Manager::field( 'city', __( 'City', 'automotive-dealer-crm' ), $s['city'] );
					Manager::field( 'country', __( 'Country code', 'automotive-dealer-crm' ), $s['country'], 'text', array( 'attrs' => array( 'maxlength' => 2 ) ) );
					?>
				</div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'hours', __( 'Opening hours', 'automotive-dealer-crm' ), $s['hours'], 'text', array( 'attrs' => array( 'placeholder' => __( 'Mon–Sat 9:00–19:00', 'automotive-dealer-crm' ) ) ) );
					Manager::field( 'map_url', __( 'Google Maps link', 'automotive-dealer-crm' ), $s['map_url'], 'url' );
					Manager::field( 'currency', __( 'Currency', 'automotive-dealer-crm' ), $s['currency'], 'select', array( 'options' => array_combine( array_keys( ADC\Labels::currencies() ), array_keys( ADC\Labels::currencies() ) ) ) );
					?>
				</div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'facebook', 'Facebook', $s['facebook'], 'url' );
					Manager::field( 'instagram', 'Instagram', $s['instagram'], 'url' );
					Manager::field( 'lead_email', __( 'Email receiving website requests', 'automotive-dealer-crm' ), $s['lead_email'], 'email' );
					?>
				</div>
			</section>

			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Homepage and pages', 'automotive-dealer-crm' ); ?></h2></div>
				<?php
				Manager::field( 'hero_title', __( 'Homepage headline', 'automotive-dealer-crm' ), $s['hero_title'], 'text', array( 'attrs' => array( 'placeholder' => __( 'Your next car is here', 'automotive-dealer-crm' ) ) ) );
				Manager::field( 'hero_text', __( 'Homepage introduction', 'automotive-dealer-crm' ), $s['hero_text'], 'textarea', array( 'attrs' => array( 'rows' => 2 ) ) );
				Manager::field( 'about_text', __( '"About us" text', 'automotive-dealer-crm' ), $s['about_text'], 'textarea', array( 'attrs' => array( 'rows' => 6 ), 'help' => __( 'Your story, your values, your services. Separate paragraphs with an empty line.', 'automotive-dealer-crm' ) ) );
				?>
				<input type="hidden" name="show_rentals" value="0">
				<label class="adc-check"><input type="checkbox" name="show_rentals" value="1" <?php checked( $s['show_rentals'], 1 ); ?>> <?php esc_html_e( 'Show the "Rentals" page', 'automotive-dealer-crm' ); ?></label>
				<input type="hidden" name="show_financing" value="0">
				<label class="adc-check"><input type="checkbox" name="show_financing" value="1" <?php checked( $s['show_financing'], 1 ); ?>> <?php esc_html_e( 'Show the "Financing" page', 'automotive-dealer-crm' ); ?></label>
				<?php Manager::field( 'financing_text', __( 'Financing information', 'automotive-dealer-crm' ), $s['financing_text'], 'textarea', array( 'attrs' => array( 'rows' => 4 ), 'help' => __( 'Partner banks, conditions, documents to provide.', 'automotive-dealer-crm' ) ) ); ?>
			</section>
		</div>

		<aside class="adc-form-layout__side">
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Design', 'automotive-dealer-crm' ); ?></h2></div>
				<div class="adc-presets">
					<?php foreach ( Settings::presets() as $key => $preset ) : ?>
						<label class="adc-preset">
							<input type="radio" name="preset" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['preset'], $key ); ?>>
							<span class="adc-preset__swatch"><span style="background:<?php echo esc_attr( $preset[1] ); ?>"></span><span style="background:<?php echo esc_attr( $preset[2] ); ?>"></span></span>
							<span><?php echo esc_html( $preset[0] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'color_primary', __( 'Custom main colour', 'automotive-dealer-crm' ), $s['color_primary'] ? $s['color_primary'] : $colors['primary'], 'color' );
					Manager::field( 'color_accent', __( 'Custom accent colour', 'automotive-dealer-crm' ), $s['color_accent'] ? $s['color_accent'] : $colors['accent'], 'color' );
					?>
				</div>
				<label class="adc-check"><input type="checkbox" name="reset_colors" value="1"> <?php esc_html_e( 'Use the colours of the chosen design', 'automotive-dealer-crm' ); ?></label>
			</section>
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Logo', 'automotive-dealer-crm' ); ?></h2></div>
				<?php if ( $s['logo_id'] ) : ?>
					<div class="adc-image-preview"><?php echo wp_get_attachment_image( (int) $s['logo_id'], 'medium' ); ?></div>
					<label class="adc-check"><input type="checkbox" name="remove_logo" value="1"> <?php esc_html_e( 'Remove', 'automotive-dealer-crm' ); ?></label>
				<?php endif; ?>
				<?php Manager::field( 'logo', __( 'Upload a logo (PNG with transparent background recommended)', 'automotive-dealer-crm' ), '', 'file', array( 'attrs' => array( 'accept' => 'image/png,image/jpeg,image/webp' ) ) ); ?>
			</section>
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Homepage photo', 'automotive-dealer-crm' ); ?></h2></div>
				<?php if ( $s['hero_image_id'] ) : ?>
					<div class="adc-image-preview"><?php echo wp_get_attachment_image( (int) $s['hero_image_id'], 'medium_large' ); ?></div>
					<label class="adc-check"><input type="checkbox" name="remove_hero_image" value="1"> <?php esc_html_e( 'Remove', 'automotive-dealer-crm' ); ?></label>
				<?php endif; ?>
				<?php Manager::field( 'hero_image', __( 'Wide photo of your showroom or vehicles (1920 px)', 'automotive-dealer-crm' ), '', 'file', array( 'attrs' => array( 'accept' => 'image/jpeg,image/webp,image/png' ) ) ); ?>
			</section>
			<button class="amds-btn amds-btn--primary amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Save and update my website', 'automotive-dealer-crm' ); ?></button>
		</aside>
	</div>
</form>
