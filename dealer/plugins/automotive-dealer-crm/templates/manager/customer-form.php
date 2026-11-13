<?php
/**
 * CRM — create / edit a customer.
 *
 * @var array $args { item: int, customer?: object }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

$c = isset( $args['customer'] ) ? $args['customer'] : null;
if ( ! current_user_can( 'adc_manage_customers' ) ) {
	return;
}
$val = static function ( $key ) use ( $c ) {
	return $c ? $c->$key : '';
};
if ( ! $c ) {
	Manager::head( __( 'New customer', 'automotive-dealer-crm' ) );
}
?>
<form class="adc-panel amds-form" method="post">
	<?php Manager::action_fields( 'customer_save', $c ? $c->id : 0 ); ?>
	<?php if ( $c ) : ?><div class="adc-panel__head"><h2><?php esc_html_e( 'Edit', 'automotive-dealer-crm' ); ?></h2></div><?php endif; ?>
	<div class="amds-form__row">
		<?php
		Manager::field( 'name', __( 'Full name or company', 'automotive-dealer-crm' ), $val( 'name' ), 'text', array( 'attrs' => array( 'required' => true ) ) );
		Manager::field( 'type', __( 'Type', 'automotive-dealer-crm' ), $c ? $c->type : 'individual', 'select', array( 'options' => array( 'individual' => __( 'Individual', 'automotive-dealer-crm' ), 'company' => __( 'Company', 'automotive-dealer-crm' ) ) ) );
		Manager::field( 'phone', __( 'Phone', 'automotive-dealer-crm' ), $val( 'phone' ), 'tel' );
		Manager::field( 'email', __( 'Email', 'automotive-dealer-crm' ), $val( 'email' ), 'email' );
		?>
	</div>
	<div class="amds-form__row">
		<?php
		Manager::field( 'address', __( 'Address', 'automotive-dealer-crm' ), $val( 'address' ) );
		Manager::field( 'city', __( 'City', 'automotive-dealer-crm' ), $val( 'city' ) );
		Manager::field( 'id_number', __( 'ID / licence number', 'automotive-dealer-crm' ), $val( 'id_number' ) );
		?>
	</div>
	<?php Manager::field( 'notes', __( 'Notes', 'automotive-dealer-crm' ), $val( 'notes' ), 'textarea', array( 'attrs' => array( 'rows' => 3 ) ) ); ?>
	<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Save', 'automotive-dealer-crm' ); ?></button></div>
</form>
