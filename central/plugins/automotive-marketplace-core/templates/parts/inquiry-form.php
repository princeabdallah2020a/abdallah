<?php
/**
 * Contact / dealer interest form.
 *
 * @var array $args { type: contact|dealer_interest }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$type    = $args['type'];
$result  = AMC\Inquiries::result();
$notices = array(
	'sent'    => array( 'success', __( 'Thank you. Your message has been sent; we will get back to you shortly.', 'automotive-marketplace-core' ) ),
	'invalid' => array( 'error', __( 'Please enter your name, your message and a valid email address or phone number.', 'automotive-marketplace-core' ) ),
	'rate'    => array( 'error', __( 'Too many messages sent. Please try again later.', 'automotive-marketplace-core' ) ),
	'error'   => array( 'error', __( 'Your message could not be sent. Please reload the page and try again.', 'automotive-marketplace-core' ) ),
);
?>
<div id="amc-inquiry" class="amc-inquiry">
	<?php if ( $result ) : ?>
		<div class="amds-alert amds-alert--<?php echo esc_attr( $notices[ $result ][0] ); ?>" role="<?php echo 'sent' === $result ? 'status' : 'alert'; ?>"><?php echo esc_html( $notices[ $result ][1] ); ?></div>
	<?php endif; ?>

	<?php if ( 'sent' !== $result ) : ?>
		<form class="amds-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( AMC\Inquiries::ACTION ); ?>">
			<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
			<input type="hidden" name="amc_ts" value="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( AMC\Inquiries::ACTION, '_amc_nonce' ); ?>
			<div class="amc-hp" aria-hidden="true">
				<label for="amc-website">Website</label>
				<input type="text" id="amc-website" name="website" tabindex="-1" autocomplete="off">
			</div>

			<div class="amds-form__row">
				<div class="amds-field">
					<label class="amds-label" for="amc-name"><?php esc_html_e( 'Full name', 'automotive-marketplace-core' ); ?> <span aria-hidden="true">*</span></label>
					<input class="amds-input" type="text" id="amc-name" name="name" required maxlength="120" autocomplete="name">
				</div>
				<div class="amds-field">
					<label class="amds-label" for="amc-company"><?php echo esc_html( 'dealer_interest' === $type ? __( 'Dealership name', 'automotive-marketplace-core' ) : __( 'Company (optional)', 'automotive-marketplace-core' ) ); ?></label>
					<input class="amds-input" type="text" id="amc-company" name="company" maxlength="191" autocomplete="organization"<?php echo 'dealer_interest' === $type ? ' required' : ''; ?>>
				</div>
			</div>
			<div class="amds-form__row">
				<div class="amds-field">
					<label class="amds-label" for="amc-phone"><?php esc_html_e( 'Phone / WhatsApp', 'automotive-marketplace-core' ); ?></label>
					<input class="amds-input" type="tel" id="amc-phone" name="phone" maxlength="32" autocomplete="tel" placeholder="+221">
				</div>
				<div class="amds-field">
					<label class="amds-label" for="amc-email"><?php esc_html_e( 'Email', 'automotive-marketplace-core' ); ?></label>
					<input class="amds-input" type="email" id="amc-email" name="email" maxlength="191" autocomplete="email">
				</div>
			</div>
			<p class="amds-help"><?php esc_html_e( 'Enter at least a phone number or an email address so we can answer you.', 'automotive-marketplace-core' ); ?></p>
			<?php if ( 'contact' === $type ) : ?>
				<div class="amds-field">
					<label class="amds-label" for="amc-subject"><?php esc_html_e( 'Subject', 'automotive-marketplace-core' ); ?></label>
					<input class="amds-input" type="text" id="amc-subject" name="subject" maxlength="191">
				</div>
			<?php endif; ?>
			<div class="amds-field">
				<label class="amds-label" for="amc-message"><?php echo esc_html( 'dealer_interest' === $type ? __( 'Tell us about your dealership (city, number of vehicles, sale or rental)', 'automotive-marketplace-core' ) : __( 'Message', 'automotive-marketplace-core' ) ); ?> <span aria-hidden="true">*</span></label>
				<textarea class="amds-textarea" id="amc-message" name="message" rows="5" required maxlength="5000"></textarea>
			</div>
			<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit"><?php echo esc_html( 'dealer_interest' === $type ? __( 'Send my request', 'automotive-marketplace-core' ) : __( 'Send message', 'automotive-marketplace-core' ) ); ?></button>
		</form>
	<?php endif; ?>
</div>
