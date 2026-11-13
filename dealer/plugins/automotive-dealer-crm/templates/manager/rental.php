<?php
/**
 * CRM — rental detail and workflow.
 *
 * @var array $args { item: int }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

$r = ADC\Rentals::get( $args['item'] );
if ( ! $r ) {
	Manager::head( __( 'Rental not found', 'automotive-dealer-crm' ) );
	return;
}
$can = current_user_can( 'adc_manage_rentals' );
Manager::head( (string) $r->vehicle_title, $r->customer_name . ' · ' . adc_date( $r->starts_at, true ) . ' → ' . adc_date( $r->ends_at, true ), adc_badge( $r->status, 'rental_statuses' ) );
$conflicts = 'quote' === $r->status ? ADC\Rentals::conflicts( $r->vehicle_id, $r->starts_at, $r->ends_at, $r->id ) : array();
?>
<?php if ( $conflicts ) : ?>
	<div class="amds-alert amds-alert--warning">
		<?php
		/* translators: %s: customer name */
		echo esc_html( sprintf( __( 'Warning: this period overlaps a confirmed rental (%s). It cannot be confirmed as is.', 'automotive-dealer-crm' ), $conflicts[0]->customer_name ) );
		?>
	</div>
<?php endif; ?>
<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Rental', 'automotive-dealer-crm' ); ?></h2></div>
		<dl class="adc-dl">
			<div><dt><?php esc_html_e( 'Customer', 'automotive-dealer-crm' ); ?></dt><dd><a href="<?php echo esc_url( adc_manager_url( 'customers', $r->customer_id ) ); ?>"><?php echo esc_html( $r->customer_name ); ?></a> <?php echo $r->customer_phone ? '<a href="' . esc_url( 'tel:' . $r->customer_phone ) . '">' . esc_html( $r->customer_phone ) . '</a>' : ''; ?></dd></div>
			<div><dt><?php esc_html_e( 'Rate', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( adc_money( $r->rate_amount, $r->currency ) . ' / ' . Labels::price_periods()[ $r->rate_unit ] . ' × ' . $r->units ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Total', 'automotive-dealer-crm' ); ?></dt><dd><strong><?php echo esc_html( adc_money( $r->total_amount, $r->currency ) ); ?></strong></dd></div>
			<div><dt><?php esc_html_e( 'Deposit', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( adc_money( $r->deposit_amount, $r->currency ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Payment', 'automotive-dealer-crm' ); ?></dt><dd><?php echo adc_badge( $r->payment_status, 'payment_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
			<div><dt><?php esc_html_e( 'With driver', 'automotive-dealer-crm' ); ?></dt><dd><?php echo $r->with_driver ? esc_html__( 'Yes', 'automotive-dealer-crm' ) : esc_html__( 'No', 'automotive-dealer-crm' ); ?></dd></div>
			<?php if ( $r->pickup_mileage || $r->return_mileage ) : ?>
				<div><dt><?php esc_html_e( 'Mileage out / back', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( adc_km( $r->pickup_mileage ) . ' / ' . ( $r->return_mileage ? adc_km( $r->return_mileage ) : '—' ) ); ?></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Agent', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( Manager::user_name( $r->agent_id ) ); ?></dd></div>
		</dl>
		<?php if ( $r->notes ) : ?><p class="adc-muted"><?php echo esc_html( $r->notes ); ?></p><?php endif; ?>
		<?php if ( $r->return_notes ) : ?><p><strong><?php esc_html_e( 'Return:', 'automotive-dealer-crm' ); ?></strong> <?php echo esc_html( $r->return_notes ); ?></p><?php endif; ?>
	</section>
	<?php if ( $can && ! in_array( $r->status, array( 'returned', 'cancelled' ), true ) ) : ?>
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Next step', 'automotive-dealer-crm' ); ?></h2></div>
			<?php if ( 'quote' === $r->status ) : ?>
				<?php Manager::button( 'rental_confirm', $r->id, __( 'Confirm the booking', 'automotive-dealer-crm' ), 'amds-btn--accent amds-btn--lg' ); ?>
			<?php elseif ( 'confirmed' === $r->status ) : ?>
				<form class="amds-form" method="post">
					<?php Manager::action_fields( 'rental_start', $r->id ); ?>
					<?php Manager::field( 'mileage', __( 'Mileage at departure (km)', 'automotive-dealer-crm' ), '', 'number', array( 'attrs' => array( 'min' => 0 ) ) ); ?>
					<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit"><?php esc_html_e( 'Hand over the vehicle', 'automotive-dealer-crm' ); ?></button>
				</form>
			<?php elseif ( 'active' === $r->status ) : ?>
				<form class="amds-form" method="post">
					<?php Manager::action_fields( 'rental_finish', $r->id ); ?>
					<?php
					Manager::field( 'mileage', __( 'Mileage at return (km)', 'automotive-dealer-crm' ), '', 'number', array( 'attrs' => array( 'min' => 0 ) ) );
					Manager::field( 'return_notes', __( 'Condition at return', 'automotive-dealer-crm' ), '', 'textarea', array( 'attrs' => array( 'rows' => 3, 'placeholder' => __( 'Fuel level, damage, cleanliness…', 'automotive-dealer-crm' ) ) ) );
					?>
					<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit"><?php esc_html_e( 'Vehicle returned', 'automotive-dealer-crm' ); ?></button>
				</form>
			<?php endif; ?>
			<form class="adc-inline-fields" method="post">
				<?php Manager::action_fields( 'rental_payment', $r->id ); ?>
				<select class="amds-select" name="payment_status" aria-label="<?php esc_attr_e( 'Payment', 'automotive-dealer-crm' ); ?>"><?php foreach ( Labels::payment_statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $r->payment_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
				<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Update payment', 'automotive-dealer-crm' ); ?></button>
			</form>
			<div class="adc-actions"><?php Manager::button( 'rental_cancel', $r->id, __( 'Cancel the rental', 'automotive-dealer-crm' ), 'amds-btn--ghost adc-danger', array(), true ); ?></div>
		</section>
	<?php endif; ?>
</div>
