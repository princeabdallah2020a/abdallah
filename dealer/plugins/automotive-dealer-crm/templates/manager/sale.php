<?php
/**
 * CRM — sale detail.
 *
 * @var array $args { item: int }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

$s = ADC\Sales::get( $args['item'] );
if ( ! $s ) {
	Manager::head( __( 'Sale not found', 'automotive-dealer-crm' ) );
	return;
}
$can = current_user_can( 'adc_manage_sales' );
Manager::head( (string) $s->vehicle_title, adc_date( $s->sold_on ) . ' · ' . $s->customer_name, adc_badge( $s->status, 'sale_statuses' ) );
?>
<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Sale', 'automotive-dealer-crm' ); ?></h2></div>
		<dl class="adc-dl">
			<div><dt><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></dt><dd><a href="<?php echo esc_url( adc_manager_url( 'vehicles', $s->vehicle_id ) ); ?>"><?php echo esc_html( $s->vehicle_title ); ?></a></dd></div>
			<div><dt><?php esc_html_e( 'Customer', 'automotive-dealer-crm' ); ?></dt><dd><a href="<?php echo esc_url( adc_manager_url( 'customers', $s->customer_id ) ); ?>"><?php echo esc_html( $s->customer_name ); ?></a></dd></div>
			<div><dt><?php esc_html_e( 'Price', 'automotive-dealer-crm' ); ?></dt><dd><strong><?php echo esc_html( adc_money( $s->sale_price, $s->currency ) ); ?></strong></dd></div>
			<div><dt><?php esc_html_e( 'Salesperson', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( Manager::user_name( $s->salesperson_id ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Payment method', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( Labels::label( 'payment_methods', $s->payment_method ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Amount received', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( adc_money( $s->amount_paid, $s->currency ) ); ?> <?php echo adc_badge( $s->payment_status, 'payment_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
		</dl>
		<?php if ( $s->notes ) : ?><p class="adc-muted"><?php echo esc_html( $s->notes ); ?></p><?php endif; ?>
		<?php if ( $can ) : ?>
			<div class="adc-actions">
				<?php if ( 'draft' === $s->status ) : ?>
					<?php Manager::button( 'sale_confirm', $s->id, __( 'Confirm the sale', 'automotive-dealer-crm' ), 'amds-btn--accent', array(), true ); ?>
				<?php endif; ?>
				<?php if ( 'cancelled' !== $s->status ) : ?>
					<?php Manager::button( 'sale_cancel', $s->id, __( 'Cancel the sale', 'automotive-dealer-crm' ), 'amds-btn--ghost adc-danger', array(), true ); ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php if ( $can && 'cancelled' !== $s->status ) : ?>
		<form class="adc-panel amds-form" method="post">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Payment follow-up', 'automotive-dealer-crm' ); ?></h2></div>
			<?php Manager::action_fields( 'sale_payment', $s->id ); ?>
			<?php
			Manager::field( 'payment_status', __( 'Payment', 'automotive-dealer-crm' ), $s->payment_status, 'select', array( 'options' => Labels::payment_statuses() ) );
			Manager::field( 'amount_paid', __( 'Amount received', 'automotive-dealer-crm' ), $s->amount_paid, 'number', array( 'attrs' => array( 'min' => 0 ) ) );
			?>
			<div><button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Update', 'automotive-dealer-crm' ); ?></button></div>
		</form>
	<?php endif; ?>
</div>
