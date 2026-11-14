<?php
/**
 * "Message the dealer" form on a vehicle page.
 *
 * @var array $args { vehicle }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$vehicle = $args['vehicle'];
$result  = AMC\Marketplace_Leads::result();
$notices = array(
	'sent'    => array( 'success', __( 'Your message has been sent to the dealer. They will contact you directly.', 'automotive-marketplace-core' ) ),
	'invalid' => array( 'error', __( 'Please enter your name, a phone number or an email address, and accept to be contacted.', 'automotive-marketplace-core' ) ),
	'rate'    => array( 'error', __( 'Too many messages sent. Please try again later.', 'automotive-marketplace-core' ) ),
	'error'   => array( 'error', __( 'Your message could not be sent. Please reload the page and try again.', 'automotive-marketplace-core' ) ),
);
?>
<details id="amc-lead" class="amc-lead"<?php echo $result ? ' open' : ''; ?>>
	<summary class="amds-btn amds-btn--primary amds-btn--lg amds-btn--block"><?php echo amc_icon( 'mail', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Send a message to the dealer', 'automotive-marketplace-core' ); ?></summary>
	<div class="amc-lead__body">
		<?php if ( $result ) : ?>
			<div class="amds-alert amds-alert--<?php echo esc_attr( $notices[ $result ][0] ); ?>" role="<?php echo 'sent' === $result ? 'status' : 'alert'; ?>"><?php echo esc_html( $notices[ $result ][1] ); ?></div>
		<?php endif; ?>
		<?php if ( 'sent' !== $result ) : ?>
			<form class="amds-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AMC\Marketplace_Leads::ACTION ); ?>">
				<input type="hidden" name="vehicle_id" value="<?php echo esc_attr( $vehicle->id ); ?>">
				<input type="hidden" name="amc_ts" value="<?php echo esc_attr( time() ); ?>">
				<?php wp_nonce_field( AMC\Marketplace_Leads::ACTION, '_amc_nonce' ); ?>
				<div class="amc-hp" aria-hidden="true">
					<label for="amc-lead-website">Website</label>
					<input type="text" id="amc-lead-website" name="website" tabindex="-1" autocomplete="off">
				</div>
				<div class="amds-field">
					<label class="amds-label" for="amc-lead-name"><?php esc_html_e( 'Full name', 'automotive-marketplace-core' ); ?> <span aria-hidden="true">*</span></label>
					<input class="amds-input" type="text" id="amc-lead-name" name="name" required maxlength="120" autocomplete="name">
				</div>
				<div class="amds-field">
					<label class="amds-label" for="amc-lead-phone"><?php esc_html_e( 'Phone / WhatsApp', 'automotive-marketplace-core' ); ?></label>
					<input class="amds-input" type="tel" id="amc-lead-phone" name="phone" maxlength="32" autocomplete="tel" placeholder="+221">
				</div>
				<div class="amds-field">
					<label class="amds-label" for="amc-lead-email"><?php esc_html_e( 'Email', 'automotive-marketplace-core' ); ?></label>
					<input class="amds-input" type="email" id="amc-lead-email" name="email" maxlength="191" autocomplete="email">
				</div>
				<div class="amds-field">
					<label class="amds-label" for="amc-lead-message"><?php esc_html_e( 'Message', 'automotive-marketplace-core' ); ?></label>
					<?php /* translators: %s: vehicle title */ ?>
					<textarea class="amds-textarea" id="amc-lead-message" name="message" rows="4" maxlength="3000" placeholder="<?php echo esc_attr( sprintf( __( 'Hello, I am interested in the %s. Is it still available?', 'automotive-marketplace-core' ), $vehicle->title ) ); ?>"></textarea>
				</div>
				<label class="amc-lead__consent">
					<input type="checkbox" name="consent" value="1" required>
					<span><?php esc_html_e( 'I agree that my details are sent to this dealer so they can contact me.', 'automotive-marketplace-core' ); ?></span>
				</label>
				<button class="amds-btn amds-btn--accent amds-btn--block" type="submit"><?php esc_html_e( 'Send message', 'automotive-marketplace-core' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</details>
