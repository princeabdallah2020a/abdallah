<?php
/**
 * Platform admin — membership settings.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$s = AMC\Settings::all();
AMC\Portal::page_head(
	__( 'Settings', 'automotive-marketplace-core' ),
	__( 'Memberships and payments. Marketplace, SEO and AI settings are in the WordPress dashboard.', 'automotive-marketplace-core' ),
	current_user_can( 'manage_options' ) ? '<a class="amds-btn amds-btn--secondary" href="' . esc_url( admin_url( 'admin.php?page=amc-settings' ) ) . '">' . esc_html__( 'Marketplace settings', 'automotive-marketplace-core' ) . '</a>' : ''
);
$text = static function ( $key, $label, $help = '', $type = 'text' ) use ( $s ) {
	echo '<div class="amds-field"><label class="amds-label" for="amc-s-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input class="amds-input" type="' . esc_attr( $type ) . '" id="amc-s-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '">' . ( $help ? '<p class="amds-help">' . esc_html( $help ) . '</p>' : '' ) . '</div>';
};
?>
<form class="amds-form" method="post">
	<?php AMC\Portal::action_fields( 'settings_save' ); ?>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Registrations', 'automotive-marketplace-core' ); ?></h2></div>
		<input type="hidden" name="registration_open" value="0">
		<label class="amc-check"><input type="checkbox" name="registration_open" value="1" <?php checked( $s['registration_open'], 1 ); ?>> <?php esc_html_e( 'Accept new dealer applications', 'automotive-marketplace-core' ); ?></label>
		<input type="hidden" name="require_documents" value="0">
		<label class="amc-check"><input type="checkbox" name="require_documents" value="1" <?php checked( $s['require_documents'], 1 ); ?>> <?php esc_html_e( 'Require at least one business document with the application', 'automotive-marketplace-core' ); ?></label>
		<?php $text( 'grace_days', __( 'Grace period after the end of a subscription (days)', 'automotive-marketplace-core' ), __( 'The dealer stays online during this period, then the account expires and listings are hidden.', 'automotive-marketplace-core' ), 'number' ); ?>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Payment details shown to dealers', 'automotive-marketplace-core' ); ?></h2></div>
		<p class="amds-help"><?php esc_html_e( 'A method appears only when its details are filled in. Dealers pay outside the website and upload their receipt; you confirm it in Payments.', 'automotive-marketplace-core' ); ?></p>
		<?php
		$text( 'pay_wave_details', __( 'Wave — number and account name', 'automotive-marketplace-core' ), __( 'Example: +221 77 000 00 00 (AutoBuzy SARL)', 'automotive-marketplace-core' ) );
		$text( 'pay_om_details', __( 'Orange Money — number and account name', 'automotive-marketplace-core' ) );
		$text( 'pay_bank_details', __( 'Bank transfer — bank, account holder, IBAN / RIB', 'automotive-marketplace-core' ) );
		$text( 'pay_cash_details', __( 'Cash — office address and opening hours', 'automotive-marketplace-core' ) );
		?>
	</section>
	<div><button class="amds-btn amds-btn--primary amds-btn--lg" type="submit"><?php esc_html_e( 'Save', 'automotive-marketplace-core' ); ?></button></div>
</form>
