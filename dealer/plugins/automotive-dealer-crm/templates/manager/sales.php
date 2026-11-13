<?php
/**
 * CRM — sales.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$month   = isset( $_GET['month'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
$sales   = ADC\Sales::search( array( 'month' => $month ) );
$reports = current_user_can( 'adc_view_reports' );
$total   = 0;
foreach ( $sales as $s ) {
	$total += 'confirmed' === $s->status ? (int) $s->sale_price : 0;
}
Manager::head( __( 'Sales', 'automotive-dealer-crm' ), $reports && $month ? sprintf( /* translators: %s: amount */ __( 'Confirmed revenue: %s', 'automotive-dealer-crm' ), adc_money( $total ) ) : '', current_user_can( 'adc_manage_sales' ) ? Manager::link_button( adc_manager_url( 'sales', 'new' ), __( 'Record a sale', 'automotive-dealer-crm' ), 'amds-btn--primary', 'plus' ) : '' );
?>
<form class="adc-toolbar" method="get" action="<?php echo esc_url( adc_manager_url( 'sales' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="adc_path" value="manager/sales"><?php endif; ?>
	<label class="amds-label" for="adc-month"><?php esc_html_e( 'Month', 'automotive-dealer-crm' ); ?></label>
	<input class="amds-input" type="month" id="adc-month" name="month" value="<?php echo esc_attr( $month ); ?>">
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Filter', 'automotive-dealer-crm' ); ?></button>
</form>
<section class="adc-panel adc-panel--flush">
	<div class="adc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Date', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Customer', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Salesperson', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Price', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Payment', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-dealer-crm' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $sales ) : ?><tr><td colspan="7" class="adc-muted"><?php esc_html_e( 'No sale recorded.', 'automotive-dealer-crm' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $sales as $s ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( adc_manager_url( 'sales', $s->id ) ); ?>"><?php echo esc_html( adc_date( $s->sold_on ) ); ?></a></td>
						<td><a href="<?php echo esc_url( adc_manager_url( 'sales', $s->id ) ); ?>"><strong><?php echo esc_html( $s->vehicle_title ); ?></strong></a></td>
						<td><?php echo esc_html( $s->customer_name ); ?></td>
						<td><?php echo esc_html( Manager::user_name( $s->salesperson_id ) ); ?></td>
						<td><?php echo esc_html( adc_money( $s->sale_price, $s->currency ) ); ?></td>
						<td><?php echo adc_badge( $s->payment_status, 'payment_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo adc_badge( $s->status, 'sale_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
