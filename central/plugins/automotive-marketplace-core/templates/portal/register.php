<?php
/**
 * Dealer registration wizard (/dealer-registration/).
 * Without JavaScript all steps are shown one after another and the server
 * validates everything.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$logged = is_user_logged_in();
$open   = (bool) AMC\Settings::get( 'registration_open' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- preselection from the pricing page.
$plan   = Portal::old( 'plan', isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( $_GET['plan'] ) ) : '' );
$months = (int) Portal::old( 'months', isset( $_GET['months'] ) ? (string) absint( $_GET['months'] ) : '1' );
// phpcs:enable

$field = static function ( $name, $label, $type = 'text', array $attrs = array() ) {
	$id   = 'amc-r-' . $name;
	$html = '';
	foreach ( $attrs as $key => $value ) {
		$html .= ' ' . esc_attr( $key ) . ( true === $value ? '' : '="' . esc_attr( $value ) . '"' );
	}
	echo '<div class="amds-field' . ( isset( Portal::$errors[ $name ] ) ? ' has-error' : '' ) . '"><label class="amds-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( isset( $attrs['required'] ) ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>';
	if ( 'textarea' === $type ) {
		echo '<textarea class="amds-textarea" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $html . '>' . esc_textarea( Portal::old( $name ) ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		$value = 'password' === $type || 'file' === $type ? '' : ' value="' . esc_attr( Portal::old( $name ) ) . '"';
		echo '<input class="amds-input" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $value . $html . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo Portal::error( $name ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
};
$select = static function ( $name, $label, array $options, $default = '' ) {
	$id = 'amc-r-' . $name;
	echo '<div class="amds-field"><label class="amds-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select class="amds-select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
	foreach ( $options as $key => $text ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( Portal::old( $name, $default ), (string) $key, false ) . '>' . esc_html( $text ) . '</option>';
	}
	echo '</select></div>';
};
$file_types = 'application/pdf,image/jpeg,image/png,image/webp';

get_header();
?>
<main id="main" class="amc-main amc-register">
	<header class="amc-page-header">
		<div class="amds-container">
			<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automotive-marketplace-core' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automotive-marketplace-core' ); ?></a>
				<a href="<?php echo esc_url( Portal::url( 'pricing' ) ); ?>"><?php esc_html_e( 'Plans and prices', 'automotive-marketplace-core' ); ?></a>
				<span aria-current="page"><?php esc_html_e( 'Registration', 'automotive-marketplace-core' ); ?></span>
			</nav>
			<h1 class="amc-page-header__title"><?php esc_html_e( 'Open your dealer account', 'automotive-marketplace-core' ); ?></h1>
			<p class="amc-page-header__lead"><?php esc_html_e( 'Five short steps. Our team reviews every application before activation.', 'automotive-marketplace-core' ); ?></p>
		</div>
	</header>

	<div class="amds-container">
		<?php if ( ! $open ) : ?>
			<div class="amds-alert amds-alert--warning"><?php esc_html_e( 'Registrations are temporarily closed. Please contact us.', 'automotive-marketplace-core' ); ?></div>
		<?php else : ?>
			<?php if ( isset( Portal::$errors['form'] ) ) : ?>
				<div class="amds-alert amds-alert--error" role="alert"><?php echo esc_html( Portal::$errors['form'] ); ?></div>
			<?php elseif ( Portal::$errors ) : ?>
				<div class="amds-alert amds-alert--error" role="alert"><?php esc_html_e( 'Some information is missing or incorrect. Please check the highlighted fields.', 'automotive-marketplace-core' ); ?></div>
			<?php endif; ?>

			<div class="amc-register__layout">
				<form class="amc-wizard" method="post" enctype="multipart/form-data" action="<?php echo esc_url( Portal::url( 'register' ) ); ?>" novalidate data-amc-wizard>
					<?php wp_nonce_field( 'amc_register', '_amc_nonce', false ); ?>
					<div class="amc-hp" aria-hidden="true"><label for="amc-company-url">URL</label><input type="text" id="amc-company-url" name="company_url" tabindex="-1" autocomplete="off"></div>

					<ol class="amc-stepper" data-amc-stepper>
						<li><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></li>
						<li><?php esc_html_e( 'Account', 'automotive-marketplace-core' ); ?></li>
						<li><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></li>
						<li><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></li>
						<li><?php esc_html_e( 'Payment', 'automotive-marketplace-core' ); ?></li>
					</ol>

					<section class="amc-wizard__step" data-amc-step>
						<h2 class="amc-wizard__title"><?php esc_html_e( 'Choose your plan', 'automotive-marketplace-core' ); ?></h2>
						<?php
						amc_get_template(
							'parts/plan-picker',
							array(
								'plan'   => $plan,
								'months' => $months,
							)
						);
						echo Portal::error( 'plan' ) . Portal::error( 'months' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</section>

					<section class="amc-wizard__step" data-amc-step>
						<h2 class="amc-wizard__title"><?php esc_html_e( 'Your account', 'automotive-marketplace-core' ); ?></h2>
						<?php if ( $logged ) : ?>
							<p class="amds-alert amds-alert--info">
								<?php
								/* translators: %s: email address */
								echo esc_html( sprintf( __( 'You are logged in as %s. The dealership will be attached to this account.', 'automotive-marketplace-core' ), wp_get_current_user()->user_email ) );
								?>
							</p>
						<?php else : ?>
							<div class="amds-form__row">
								<?php
								$field( 'full_name', __( 'Full name', 'automotive-marketplace-core' ), 'text', array( 'required' => true, 'autocomplete' => 'name', 'maxlength' => 120 ) );
								$field( 'email', __( 'Email', 'automotive-marketplace-core' ), 'email', array( 'required' => true, 'autocomplete' => 'email' ) );
								?>
							</div>
							<?php $field( 'password', __( 'Password (8 characters minimum)', 'automotive-marketplace-core' ), 'password', array( 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password' ) ); ?>
							<p class="amds-help">
								<?php esc_html_e( 'Already a member?', 'automotive-marketplace-core' ); ?>
								<a href="<?php echo esc_url( add_query_arg( 'redirect_to', rawurlencode( Portal::url( 'register' ) ), Portal::url( 'login' ) ) ); ?>"><?php esc_html_e( 'Log in', 'automotive-marketplace-core' ); ?></a>
							</p>
						<?php endif; ?>
						<?php $field( 'phone', __( 'Mobile phone', 'automotive-marketplace-core' ), 'tel', array( 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+221' ) ); ?>
					</section>

					<section class="amc-wizard__step" data-amc-step>
						<h2 class="amc-wizard__title"><?php esc_html_e( 'Your dealership', 'automotive-marketplace-core' ); ?></h2>
						<div class="amds-form__row">
							<?php
							$field( 'business_name', __( 'Trading name', 'automotive-marketplace-core' ), 'text', array( 'required' => true, 'maxlength' => 191 ) );
							$field( 'legal_name', __( 'Legal name (if different)', 'automotive-marketplace-core' ), 'text', array( 'maxlength' => 191 ) );
							?>
						</div>
						<div class="amds-form__row">
							<?php
							$field( 'city', __( 'City', 'automotive-marketplace-core' ), 'text', array( 'required' => true, 'maxlength' => 100, 'placeholder' => 'Dakar' ) );
							$field( 'address', __( 'Address', 'automotive-marketplace-core' ), 'text', array( 'maxlength' => 255 ) );
							?>
						</div>
						<div class="amds-form__row">
							<?php
							$field( 'whatsapp', __( 'WhatsApp number (if different)', 'automotive-marketplace-core' ), 'tel', array( 'placeholder' => '+221' ) );
							$select( 'activity', __( 'Activity', 'automotive-marketplace-core' ), AMC\Memberships::activities(), 'sale' );
							$select( 'fleet_size', __( 'Vehicles in stock', 'automotive-marketplace-core' ), AMC\Memberships::fleet_sizes(), '1-10' );
							?>
						</div>
						<div class="amds-form__row">
							<?php
							$field( 'registration_number', __( 'RCCM number', 'automotive-marketplace-core' ), 'text', array( 'maxlength' => 80, 'placeholder' => 'SN-DKR-…' ) );
							$field( 'tax_id', __( 'NINEA', 'automotive-marketplace-core' ), 'text', array( 'maxlength' => 80 ) );
							?>
						</div>
						<?php $field( 'description', __( 'Short presentation of your dealership', 'automotive-marketplace-core' ), 'textarea', array( 'rows' => 3, 'maxlength' => 2000 ) ); ?>

						<fieldset class="amds-fieldset amc-docs">
							<legend class="amds-label"><?php esc_html_e( 'Verification documents', 'automotive-marketplace-core' ); ?></legend>
							<p class="amds-help"><?php esc_html_e( 'PDF or photo, 5 MB maximum each. You can also send them later from your dealer space.', 'automotive-marketplace-core' ); ?></p>
							<div class="amds-form__row">
								<?php
								$field( 'doc_rccm', __( 'Trade register (RCCM)', 'automotive-marketplace-core' ), 'file', array( 'accept' => $file_types ) );
								$field( 'doc_ninea', __( 'Tax ID (NINEA)', 'automotive-marketplace-core' ), 'file', array( 'accept' => $file_types ) );
								$field( 'doc_id', __( 'ID of the manager', 'automotive-marketplace-core' ), 'file', array( 'accept' => $file_types ) );
								?>
							</div>
						</fieldset>
					</section>

					<section class="amc-wizard__step" data-amc-step>
						<h2 class="amc-wizard__title"><?php esc_html_e( 'Your website', 'automotive-marketplace-core' ); ?></h2>
						<p class="amds-help"><?php esc_html_e( 'Each dealership receives its own independent website. Tell us about your domain name.', 'automotive-marketplace-core' ); ?></p>
						<div class="amc-options" data-amc-website-options>
							<?php foreach ( AMC\Memberships::website_options() as $key => $label ) : ?>
								<label class="amc-choice amc-choice--row">
									<input type="radio" name="website_option" value="<?php echo esc_attr( $key ); ?>" <?php checked( Portal::old( 'website_option', 'new_domain' ), $key ); ?>>
									<span class="amc-choice__body"><span class="amc-choice__title"><?php echo esc_html( $label ); ?></span></span>
								</label>
							<?php endforeach; ?>
						</div>
						<div data-amc-domain-field>
							<?php $field( 'domain', __( 'Domain name (wished or existing)', 'automotive-marketplace-core' ), 'text', array( 'placeholder' => 'my-dealership.com', 'maxlength' => 191 ) ); ?>
						</div>
						<?php $field( 'notes', __( 'Anything else we should know? (optional)', 'automotive-marketplace-core' ), 'textarea', array( 'rows' => 3, 'maxlength' => 2000 ) ); ?>
					</section>

					<section class="amc-wizard__step" data-amc-step>
						<h2 class="amc-wizard__title"><?php esc_html_e( 'Payment', 'automotive-marketplace-core' ); ?></h2>
						<p class="amds-help"><?php esc_html_e( 'You can pay now and send the receipt, or pay later from your dealer space. Your subscription starts once your application is approved and your payment confirmed.', 'automotive-marketplace-core' ); ?></p>
						<div class="amc-options" data-amc-pay-options>
							<?php foreach ( AMC\Payments::providers() as $id => $provider ) : ?>
								<label class="amc-choice amc-choice--row">
									<input type="radio" name="payment_method" value="<?php echo esc_attr( $id ); ?>" <?php checked( Portal::old( 'payment_method', 'later' ), $id ); ?>>
									<span class="amc-choice__body">
										<span class="amc-choice__title"><?php echo esc_html( $provider->label() ); ?></span>
										<span class="amc-choice__meta"><?php echo esc_html( $provider instanceof AMC\Manual_Payment_Provider && $provider->details() ? $provider->details() : __( 'Our team will send you the payment details.', 'automotive-marketplace-core' ) ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
							<label class="amc-choice amc-choice--row">
								<input type="radio" name="payment_method" value="later" <?php checked( Portal::old( 'payment_method', 'later' ), 'later' ); ?>>
								<span class="amc-choice__body"><span class="amc-choice__title"><?php esc_html_e( 'I will pay later', 'automotive-marketplace-core' ); ?></span></span>
							</label>
						</div>
						<?php echo Portal::error( 'payment_method' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="amds-form__row" data-amc-pay-details>
							<?php
							$field( 'payer_reference', __( 'Transaction reference', 'automotive-marketplace-core' ), 'text', array( 'maxlength' => 120 ) );
							$field( 'payment_proof', __( 'Receipt (screenshot or PDF)', 'automotive-marketplace-core' ), 'file', array( 'accept' => $file_types ) );
							?>
						</div>
						<label class="amc-check<?php echo isset( Portal::$errors['accept_terms'] ) ? ' has-error' : ''; ?>">
							<input type="checkbox" name="accept_terms" value="1" required <?php checked( Portal::old( 'accept_terms' ), '1' ); ?>>
							<span>
								<?php
								$terms = AMC\Content::page_url( 'terms' );
								if ( $terms ) {
									/* translators: %s: link to the terms */
									printf( esc_html__( 'I accept the %s and certify that the information provided is accurate.', 'automotive-marketplace-core' ), '<a href="' . esc_url( $terms ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms and conditions', 'automotive-marketplace-core' ) . '</a>' );
								} else {
									esc_html_e( 'I accept the terms and conditions and certify that the information provided is accurate.', 'automotive-marketplace-core' );
								}
								?>
							</span>
						</label>
						<?php echo Portal::error( 'accept_terms' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</section>

					<div class="amc-wizard__nav">
						<button class="amds-btn amds-btn--ghost" type="button" data-amc-prev hidden><?php esc_html_e( 'Back', 'automotive-marketplace-core' ); ?></button>
						<button class="amds-btn amds-btn--primary amds-btn--lg" type="button" data-amc-next hidden><?php esc_html_e( 'Continue', 'automotive-marketplace-core' ); ?></button>
						<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit" data-amc-submit><?php esc_html_e( 'Send my application', 'automotive-marketplace-core' ); ?></button>
					</div>
				</form>

				<aside class="amc-register__aside">
					<?php amc_get_template( 'parts/order-summary' ); ?>
					<ul class="amc-reassure">
						<li><?php echo amc_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Your documents are stored privately and only seen by our team.', 'automotive-marketplace-core' ); ?></li>
						<li><?php echo amc_icon( 'globe', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Your website and your data remain yours.', 'automotive-marketplace-core' ); ?></li>
						<li><?php echo amc_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'A question? Our team answers from your dealer space.', 'automotive-marketplace-core' ); ?></li>
					</ul>
				</aside>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
