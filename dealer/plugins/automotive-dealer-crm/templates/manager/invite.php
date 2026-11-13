<?php
/**
 * Invitation acceptance (/manager/invite/{token}/).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

$invite = ADC\Team::find_invite( ADC\Router::item() );
adc_template(
	'manager/auth',
	array(
		'title' => __( 'Join the team', 'automotive-dealer-crm' ),
		'body'  => static function () use ( $invite ) {
			if ( ! $invite ) {
				echo '<p>' . esc_html__( 'This invitation is invalid or has expired. Ask the dealership owner to send a new one.', 'automotive-dealer-crm' ) . '</p>';
				return;
			}
			?>
			<p class="adc-muted">
				<?php
				/* translators: 1: email, 2: role */
				echo esc_html( sprintf( __( 'Account for %1$s · Role: %2$s', 'automotive-dealer-crm' ), $invite->email, ADC\Roles::role_label( $invite->role ) ) );
				?>
			</p>
			<form class="amds-form" method="post">
				<?php wp_nonce_field( 'adc_invite', '_adc_nonce', false ); ?>
				<?php
				ADC\Manager::field( 'name', __( 'Full name', 'automotive-dealer-crm' ), $invite->name, 'text', array( 'attrs' => array( 'required' => true, 'autocomplete' => 'name' ) ) );
				ADC\Manager::field( 'password', __( 'Choose a password (8 characters minimum)', 'automotive-dealer-crm' ), '', 'password', array( 'attrs' => array( 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password' ) ) );
				?>
				<button class="amds-btn amds-btn--primary amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Create my access', 'automotive-dealer-crm' ); ?></button>
			</form>
			<?php
		},
	)
);
