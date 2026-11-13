<?php
/**
 * CRM — customers.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$q         = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$customers = ADC\Customers::search( $q );
Manager::head( __( 'Customers', 'automotive-dealer-crm' ), '', current_user_can( 'adc_manage_customers' ) ? Manager::link_button( adc_manager_url( 'customers', 'new' ), __( 'New customer', 'automotive-dealer-crm' ), 'amds-btn--primary', 'plus' ) : '' );
?>
<form class="adc-toolbar" method="get" action="<?php echo esc_url( adc_manager_url( 'customers' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="adc_path" value="manager/customers"><?php endif; ?>
	<input class="amds-input" type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Name, phone or email', 'automotive-dealer-crm' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'automotive-dealer-crm' ); ?>">
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Search', 'automotive-dealer-crm' ); ?></button>
</form>
<section class="adc-panel adc-panel--flush">
	<div class="adc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Name', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Phone', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Email', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'City', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Since', 'automotive-dealer-crm' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $customers ) : ?><tr><td colspan="5" class="adc-muted"><?php esc_html_e( 'No customer found.', 'automotive-dealer-crm' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $customers as $c ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( adc_manager_url( 'customers', $c->id ) ); ?>"><strong><?php echo esc_html( $c->name ); ?></strong></a></td>
						<td><?php echo esc_html( $c->phone ); ?></td>
						<td><?php echo esc_html( $c->email ); ?></td>
						<td><?php echo esc_html( $c->city ); ?></td>
						<td><?php echo esc_html( adc_date( $c->created_at ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
