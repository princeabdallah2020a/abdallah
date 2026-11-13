<?php
/**
 * CRM login (/manager/login/).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

adc_template(
	'manager/auth',
	array(
		'title' => __( 'CRM login', 'automotive-dealer-crm' ),
		'body'  => static function () {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
			?>
			<form class="amds-form" method="post">
				<?php wp_nonce_field( 'adc_login', '_adc_nonce', false ); ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<?php
				ADC\Manager::field( 'log', __( 'Email or username', 'automotive-dealer-crm' ), isset( ADC\Manager::$old['log'] ) ? ADC\Manager::$old['log'] : '', 'text', array( 'attrs' => array( 'required' => true, 'autocomplete' => 'username' ) ) );
				ADC\Manager::field( 'pwd', __( 'Password', 'automotive-dealer-crm' ), '', 'password', array( 'attrs' => array( 'required' => true, 'autocomplete' => 'current-password' ) ) );
				?>
				<label class="adc-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Remember me', 'automotive-dealer-crm' ); ?></label>
				<button class="amds-btn amds-btn--primary amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Log in', 'automotive-dealer-crm' ); ?></button>
			</form>
			<p class="adc-auth__links"><a href="<?php echo esc_url( wp_lostpassword_url( adc_url( 'manager/login' ) ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'automotive-dealer-crm' ); ?></a></p>
			<?php
		},
	)
);
