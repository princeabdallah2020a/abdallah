<?php
/**
 * CRM — new rental.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

if ( ! current_user_can( 'adc_manage_rentals' ) ) {
	Manager::head( __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
	return;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$vehicle_id  = isset( $_GET['vehicle'] ) ? absint( $_GET['vehicle'] ) : 0;
$lead        = isset( $_GET['lead'] ) ? ADC\Leads::get( absint( $_GET['lead'] ) ) : null;
$customer_id = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : ( $lead ? (int) $lead->customer_id : 0 );
// phpcs:enable
$vehicles = array();
$rates    = array();
foreach ( ADC\Vehicles::search( array( 'statuses' => array( 'published', 'reserved', 'rented' ), 'type' => 'rent' ) ) as $v ) {
	$vehicles[ $v->id ] = $v->title . ' — ' . adc_vehicle_price( $v );
	$rates[ $v->id ]    = $v->price_amount;
}
$start = wp_date( 'Y-m-d\TH:00', time() + HOUR_IN_SECONDS );
$end   = wp_date( 'Y-m-d\TH:00', time() + DAY_IN_SECONDS + HOUR_IN_SECONDS );
Manager::head( __( 'New rental', 'automotive-dealer-crm' ), __( 'Save as a quote, or confirm right away: the system refuses double bookings.', 'automotive-dealer-crm' ) );
?>
<form class="adc-panel amds-form" method="post" enctype="multipart/form-data" data-adc-rental data-adc-prices="<?php echo esc_attr( wp_json_encode( $rates ) ); ?>" data-currency="<?php echo esc_attr( ADC\Labels::currencies()[ ADC\Settings::get( 'currency' ) ]['symbol'] ?? '' ); ?>">
	<?php Manager::action_fields( 'rental_create' ); ?>
	<div class="amds-form__row">
		<?php
		Manager::field( 'vehicle_id', __( 'Vehicle', 'automotive-dealer-crm' ), $vehicle_id, 'select', array( 'options' => array( '' => '—' ) + $vehicles, 'attrs' => array( 'required' => true, 'data-adc-vehicle' => true ) ) );
		Manager::field( 'starts_at', __( 'Start', 'automotive-dealer-crm' ), $start, 'datetime-local', array( 'attrs' => array( 'required' => true ) ) );
		Manager::field( 'ends_at', __( 'Return', 'automotive-dealer-crm' ), $end, 'datetime-local', array( 'attrs' => array( 'required' => true ) ) );
		?>
	</div>
	<div class="amds-form__row">
		<?php
		Manager::field( 'rate_unit', __( 'Billed per', 'automotive-dealer-crm' ), 'day', 'select', array( 'options' => array( 'day' => Labels::price_periods()['day'], 'hour' => Labels::price_periods()['hour'] ) ) );
		Manager::field( 'rate_amount', __( 'Rate', 'automotive-dealer-crm' ), $vehicle_id && isset( $rates[ $vehicle_id ] ) ? $rates[ $vehicle_id ] : '', 'number', array( 'attrs' => array( 'min' => 0, 'data-adc-price' => true ) ) );
		Manager::field( 'deposit_amount', __( 'Deposit', 'automotive-dealer-crm' ), '', 'number', array( 'attrs' => array( 'min' => 0 ) ) );
		Manager::field( 'payment_status', __( 'Payment', 'automotive-dealer-crm' ), 'unpaid', 'select', array( 'options' => Labels::payment_statuses() ) );
		?>
	</div>
	<p class="adc-total" data-adc-total></p>
	<?php Manager::customer_picker( $customer_id, $lead ? array( 'name' => $lead->name, 'phone' => $lead->phone ) : array() ); ?>
	<label class="adc-check"><input type="checkbox" name="with_driver" value="1"> <?php esc_html_e( 'With driver', 'automotive-dealer-crm' ); ?></label>
	<div class="amds-form__row">
		<?php
		Manager::field( 'notes', __( 'Notes', 'automotive-dealer-crm' ), '', 'textarea', array( 'attrs' => array( 'rows' => 3 ) ) );
		Manager::field( 'document', __( 'Contract or licence (optional)', 'automotive-dealer-crm' ), '', 'file', array( 'attrs' => array( 'accept' => 'application/pdf,image/jpeg,image/png,image/webp' ) ) );
		?>
	</div>
	<label class="adc-check adc-check--strong"><input type="checkbox" name="confirm" value="1" checked> <?php esc_html_e( 'Confirm the booking now', 'automotive-dealer-crm' ); ?></label>
	<div><button class="amds-btn amds-btn--primary amds-btn--lg" type="submit"><?php esc_html_e( 'Save the rental', 'automotive-dealer-crm' ); ?></button></div>
</form>
