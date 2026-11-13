<?php
/**
 * Public contact / vehicle enquiry form → creates a lead in the CRM.
 *
 * @var array $args { vehicle?: object, title?: string }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$vehicle = isset( $args['vehicle'] ) ? $args['vehicle'] : null;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
$sent     = isset( $_GET['adc_sent'] ) ? sanitize_key( wp_unslash( $_GET['adc_sent'] ) ) : '';
$messages = array(
	'ok'      => array( 'success', __( 'Thank you! Your request has been sent. We will contact you very soon.', 'automotive-dealer-crm' ) ),
	'invalid' => array( 'error', __( 'Please enter your name and a phone number or email.', 'automotive-dealer-crm' ) ),
	'rate'    => array( 'error', __( 'Too many requests. Please call us directly.', 'automotive-dealer-crm' ) ),
	'error'   => array( 'error', __( 'Your request could not be sent. Please reload the page and try again.', 'automotive-dealer-crm' ) ),
);
?>
<div class="adc-lead-form" id="adc-lead">
	<?php if ( ! empty( $args['title'] ) ) : ?><h2 class="adc-lead-form__title"><?php echo esc_html( $args['title'] ); ?></h2><?php endif; ?>
	<?php if ( isset( $messages[ $sent ] ) ) : ?>
		<div class="amds-alert amds-alert--<?php echo esc_attr( $messages[ $sent ][0] ); ?>" role="<?php echo 'ok' === $sent ? 'status' : 'alert'; ?>"><?php echo esc_html( $messages[ $sent ][1] ); ?></div>
	<?php endif; ?>
	<?php if ( 'ok' !== $sent ) : ?>
		<form class="amds-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adc_lead">
			<input type="hidden" name="adc_ts" value="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( 'adc_public_lead', '_adc_nonce', false ); ?>
			<?php if ( $vehicle ) : ?><input type="hidden" name="vehicle_id" value="<?php echo esc_attr( $vehicle->id ); ?>"><?php endif; ?>
			<div class="adc-hp" aria-hidden="true"><label for="adc-website">Website</label><input type="text" id="adc-website" name="website" tabindex="-1" autocomplete="off"></div>
			<div class="amds-field"><label class="amds-label" for="adc-l-name"><?php esc_html_e( 'Your name', 'automotive-dealer-crm' ); ?> *</label><input class="amds-input" id="adc-l-name" name="name" required maxlength="150" autocomplete="name"></div>
			<div class="amds-form__row">
				<div class="amds-field"><label class="amds-label" for="adc-l-phone"><?php esc_html_e( 'Phone / WhatsApp', 'automotive-dealer-crm' ); ?></label><input class="amds-input" type="tel" id="adc-l-phone" name="phone" maxlength="32" autocomplete="tel" placeholder="+221"></div>
				<div class="amds-field"><label class="amds-label" for="adc-l-email"><?php esc_html_e( 'Email', 'automotive-dealer-crm' ); ?></label><input class="amds-input" type="email" id="adc-l-email" name="email" maxlength="191" autocomplete="email"></div>
			</div>
			<?php if ( ! $vehicle ) : ?>
				<div class="amds-field"><label class="amds-label" for="adc-l-interest"><?php esc_html_e( 'I would like to', 'automotive-dealer-crm' ); ?></label><select class="amds-select" id="adc-l-interest" name="interest"><option value="sale"><?php esc_html_e( 'Buy a vehicle', 'automotive-dealer-crm' ); ?></option><option value="rent"><?php esc_html_e( 'Rent a vehicle', 'automotive-dealer-crm' ); ?></option></select></div>
			<?php endif; ?>
			<div class="amds-field"><label class="amds-label" for="adc-l-message"><?php esc_html_e( 'Message', 'automotive-dealer-crm' ); ?></label><textarea class="amds-textarea" id="adc-l-message" name="message" rows="4" maxlength="3000"><?php echo $vehicle ? esc_textarea( sprintf( /* translators: %s: vehicle */ __( 'Hello, I am interested in the %s. Is it still available?', 'automotive-dealer-crm' ), $vehicle->title ) ) : ''; ?></textarea></div>
			<button class="amds-btn amds-btn--accent amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Send my request', 'automotive-dealer-crm' ); ?></button>
			<p class="adc-lead-form__note"><?php esc_html_e( 'Your details are only used to answer your request.', 'automotive-dealer-crm' ); ?></p>
		</form>
	<?php endif; ?>
</div>
