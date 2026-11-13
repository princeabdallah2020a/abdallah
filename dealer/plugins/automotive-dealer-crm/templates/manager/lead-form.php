<?php
/**
 * CRM — new lead (and edit, through lead.php).
 *
 * @var array $args { item: int, lead?: object }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

$lead = isset( $args['lead'] ) ? $args['lead'] : null;
if ( ! current_user_can( 'adc_manage_leads' ) ) {
	Manager::head( __( 'You do not have permission to do this.', 'automotive-dealer-crm' ) );
	return;
}
$val      = static function ( $key, $default = '' ) use ( $lead ) {
	return $lead ? $lead->$key : $default;
};
$vehicles = array( '' => '—' );
foreach ( ADC\Vehicles::search( array( 'statuses' => array( 'published', 'reserved', 'rented' ) ) ) as $v ) {
	$vehicles[ $v->id ] = $v->title . ' — ' . adc_vehicle_price( $v );
}
if ( ! $lead ) {
	Manager::head( __( 'New lead', 'automotive-dealer-crm' ), __( 'A call, a WhatsApp message, a visit: record it so nobody is forgotten.', 'automotive-dealer-crm' ) );
}
?>
<form class="adc-panel amds-form" method="post">
	<?php Manager::action_fields( 'lead_save', $lead ? $lead->id : 0 ); ?>
	<?php if ( $lead ) : ?><div class="adc-panel__head"><h2><?php esc_html_e( 'Edit the lead', 'automotive-dealer-crm' ); ?></h2></div><?php endif; ?>
	<div class="amds-form__row">
		<?php
		Manager::field( 'name', __( 'Full name', 'automotive-dealer-crm' ), $val( 'name' ), 'text', array( 'attrs' => array( 'required' => true ) ) );
		Manager::field( 'phone', __( 'Phone / WhatsApp', 'automotive-dealer-crm' ), $val( 'phone' ), 'tel', array( 'attrs' => array( 'placeholder' => '+221' ) ) );
		Manager::field( 'email', __( 'Email', 'automotive-dealer-crm' ), $val( 'email' ), 'email' );
		?>
	</div>
	<div class="amds-form__row">
		<?php
		Manager::field( 'vehicle_id', __( 'Vehicle of interest', 'automotive-dealer-crm' ), $val( 'vehicle_id' ), 'select', array( 'options' => $vehicles ) );
		Manager::field( 'interest', __( 'Looking to', 'automotive-dealer-crm' ), $val( 'interest', 'sale' ), 'select', array( 'options' => array( 'sale' => __( 'Buy', 'automotive-dealer-crm' ), 'rent' => __( 'Rent', 'automotive-dealer-crm' ) ) ) );
		Manager::field( 'budget_amount', __( 'Budget', 'automotive-dealer-crm' ), $val( 'budget_amount' ) ? $val( 'budget_amount' ) : '', 'number', array( 'attrs' => array( 'min' => 0 ) ) );
		Manager::field( 'source', __( 'Source', 'automotive-dealer-crm' ), $val( 'source', 'phone' ), 'select', array( 'options' => Labels::lead_sources() ) );
		?>
	</div>
	<div class="amds-form__row">
		<?php
		if ( current_user_can( 'adc_view_all_leads' ) ) {
			$members = array( '0' => __( 'Not assigned', 'automotive-dealer-crm' ) );
			foreach ( ADC\Team::members_with( 'adc_manage_leads' ) as $member ) {
				$members[ $member->ID ] = $member->display_name;
			}
			Manager::field( 'assigned_to', __( 'Assigned to', 'automotive-dealer-crm' ), $val( 'assigned_to', 0 ), 'select', array( 'options' => $members ) );
		}
		Manager::field( 'follow_up_at', __( 'Next follow-up', 'automotive-dealer-crm' ), $lead ? adc_input_datetime( $lead->follow_up_at ) : '', 'datetime-local' );
		?>
	</div>
	<?php Manager::field( 'message', __( 'Request / notes', 'automotive-dealer-crm' ), $val( 'message' ), 'textarea', array( 'attrs' => array( 'rows' => 3 ) ) ); ?>
	<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Save the lead', 'automotive-dealer-crm' ); ?></button></div>
</form>
