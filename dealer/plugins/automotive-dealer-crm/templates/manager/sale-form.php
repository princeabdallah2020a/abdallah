<?php
/**
 * CRM — record a sale.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

if ( ! current_user_can( 'adc_manage_sales' ) ) {
	Manager::head( __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
	return;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$vehicle_id  = isset( $_GET['vehicle'] ) ? absint( $_GET['vehicle'] ) : 0;
$lead        = isset( $_GET['lead'] ) ? ADC\Leads::get( absint( $_GET['lead'] ) ) : null;
$customer_id = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : ( $lead ? (int) $lead->customer_id : 0 );
// phpcs:enable
$vehicles = array();
$prices   = array();
foreach ( ADC\Vehicles::search( array( 'statuses' => array( 'published', 'reserved', 'draft' ), 'type' => 'sale' ) ) as $v ) {
	$vehicles[ $v->id ] = $v->title . ' — ' . adc_vehicle_price( $v ) . ( 'published' !== $v->status ? ' (' . Labels::label( 'vehicle_statuses', $v->status ) . ')' : '' );
	$prices[ $v->id ]   = $v->price_amount;
}
$sellers = array();
foreach ( ADC\Team::members_with( 'adc_manage_sales' ) as $member ) {
	$sellers[ $member->ID ] = $member->display_name;
}
Manager::head( __( 'Record a sale', 'automotive-dealer-crm' ), __( 'Confirming the sale marks the vehicle as sold everywhere.', 'automotive-dealer-crm' ) );
?>
<form class="adc-panel amds-form" method="post" enctype="multipart/form-data" data-adc-prices="<?php echo esc_attr( wp_json_encode( $prices ) ); ?>">
	<?php Manager::action_fields( 'sale_create' ); ?>
	<?php if ( $lead ) : ?><input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead->id ); ?>"><?php endif; ?>
	<?php if ( ! $vehicles ) : ?>
		<div class="amds-alert amds-alert--warning"><?php esc_html_e( 'No vehicle available for sale.', 'automotive-dealer-crm' ); ?></div>
	<?php endif; ?>
	<div class="amds-form__row">
		<?php
		Manager::field( 'vehicle_id', __( 'Vehicle', 'automotive-dealer-crm' ), $vehicle_id, 'select', array( 'options' => array( '' => '—' ) + $vehicles, 'attrs' => array( 'required' => true, 'data-adc-vehicle' => true ) ) );
		Manager::field( 'sale_price', __( 'Final sale price', 'automotive-dealer-crm' ), $vehicle_id && isset( $prices[ $vehicle_id ] ) ? $prices[ $vehicle_id ] : '', 'number', array( 'attrs' => array( 'required' => true, 'min' => 1, 'data-adc-price' => true ) ) );
		Manager::field( 'sold_on', __( 'Date', 'automotive-dealer-crm' ), wp_date( 'Y-m-d' ), 'date' );
		?>
	</div>
	<?php Manager::customer_picker( $customer_id, $lead ? array( 'name' => $lead->name, 'phone' => $lead->phone ) : array() ); ?>
	<div class="amds-form__row">
		<?php
		Manager::field( 'salesperson_id', __( 'Salesperson', 'automotive-dealer-crm' ), get_current_user_id(), 'select', array( 'options' => $sellers ) );
		Manager::field( 'payment_method', __( 'Payment method', 'automotive-dealer-crm' ), 'cash', 'select', array( 'options' => Labels::payment_methods() ) );
		Manager::field( 'payment_status', __( 'Payment', 'automotive-dealer-crm' ), 'unpaid', 'select', array( 'options' => Labels::payment_statuses() ) );
		Manager::field( 'amount_paid', __( 'Amount received', 'automotive-dealer-crm' ), '', 'number', array( 'attrs' => array( 'min' => 0 ) ) );
		?>
	</div>
	<div class="amds-form__row">
		<?php
		Manager::field( 'notes', __( 'Notes', 'automotive-dealer-crm' ), '', 'textarea', array( 'attrs' => array( 'rows' => 3 ) ) );
		Manager::field( 'document', __( 'Supporting document (optional)', 'automotive-dealer-crm' ), '', 'file', array( 'attrs' => array( 'accept' => 'application/pdf,image/jpeg,image/png,image/webp' ) ) );
		?>
	</div>
	<label class="adc-check adc-check--strong"><input type="checkbox" name="confirm" value="1" checked> <?php esc_html_e( 'Confirm the sale now (the vehicle becomes "sold")', 'automotive-dealer-crm' ); ?></label>
	<div><button class="amds-btn amds-btn--primary amds-btn--lg" type="submit"><?php esc_html_e( 'Save the sale', 'automotive-dealer-crm' ); ?></button></div>
</form>
