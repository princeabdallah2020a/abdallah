<?php
/**
 * CRM — rentals: availability planner and list.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'upcoming';
$view    = in_array( $view, array( 'upcoming', 'active', 'past', 'all' ), true ) ? $view : 'upcoming';
$rentals = ADC\Rentals::search( array( 'view' => $view ) );
$planner = ADC\Rentals::planner( 14 );
Manager::head( __( 'Rentals', 'automotive-dealer-crm' ), __( 'Overlapping confirmed rentals are blocked automatically.', 'automotive-dealer-crm' ), current_user_can( 'adc_manage_rentals' ) ? Manager::link_button( adc_manager_url( 'rentals', 'new' ), __( 'New rental', 'automotive-dealer-crm' ), 'amds-btn--primary', 'plus' ) : '' );
?>
<section class="adc-panel">
	<div class="adc-panel__head"><h2><?php esc_html_e( 'Availability — next 14 days', 'automotive-dealer-crm' ); ?></h2>
		<span class="adc-legend"><span class="adc-legend__item adc-legend__item--confirmed"><?php esc_html_e( 'Confirmed', 'automotive-dealer-crm' ); ?></span><span class="adc-legend__item adc-legend__item--active"><?php esc_html_e( 'Vehicle out', 'automotive-dealer-crm' ); ?></span><span class="adc-legend__item adc-legend__item--quote"><?php esc_html_e( 'Quote', 'automotive-dealer-crm' ); ?></span></span>
	</div>
	<?php if ( ! $planner['vehicles'] ) : ?>
		<p class="adc-muted"><?php esc_html_e( 'No vehicle offered for rent yet. Set "Offer: For rent" on a vehicle.', 'automotive-dealer-crm' ); ?></p>
	<?php else : ?>
		<div class="adc-table-wrap">
			<table class="adc-planner">
				<thead><tr><th scope="col"><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></th>
					<?php foreach ( $planner['days'] as $day ) : ?>
						<th scope="col" class="<?php echo in_array( wp_date( 'N', $day ), array( '6', '7' ), true ) ? 'is-weekend' : ''; ?>"><span><?php echo esc_html( wp_date( 'D', $day ) ); ?></span><?php echo esc_html( wp_date( 'j', $day ) ); ?></th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody>
					<?php foreach ( $planner['vehicles'] as $v ) : ?>
						<tr>
							<th scope="row"><a href="<?php echo esc_url( adc_manager_url( 'rentals', 'new', array( 'vehicle' => $v->id ) ) ); ?>"><?php echo esc_html( $v->title ); ?></a></th>
							<?php foreach ( $planner['days'] as $i => $day ) : ?>
								<?php $cell = isset( $planner['cells'][ $v->id ][ $i ] ) ? $planner['cells'][ $v->id ][ $i ] : null; ?>
								<td class="<?php echo $cell ? 'is-' . esc_attr( $cell->status ) : 'is-free'; ?>">
									<?php if ( $cell ) : ?>
										<a href="<?php echo esc_url( adc_manager_url( 'rentals', $cell->id ) ); ?>" title="<?php echo esc_attr( $cell->customer_name . ' · ' . adc_date( $cell->starts_at, true ) . ' → ' . adc_date( $cell->ends_at, true ) ); ?>"><span class="screen-reader-text"><?php echo esc_html( $cell->customer_name ); ?></span></a>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<nav class="amds-tabs adc-tabs">
	<?php foreach ( array( 'upcoming' => __( 'Upcoming', 'automotive-dealer-crm' ), 'active' => __( 'Vehicle out', 'automotive-dealer-crm' ), 'past' => __( 'Finished', 'automotive-dealer-crm' ), 'all' => __( 'All', 'automotive-dealer-crm' ) ) as $key => $label ) : ?>
		<a class="amds-tabs__item<?php echo $key === $view ? ' is-active' : ''; ?>" href="<?php echo esc_url( adc_manager_url( 'rentals', '', array( 'view' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<section class="adc-panel adc-panel--flush">
	<div class="adc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Customer', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Period', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Total', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Payment', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-dealer-crm' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rentals ) : ?><tr><td colspan="6" class="adc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-dealer-crm' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $rentals as $r ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( adc_manager_url( 'rentals', $r->id ) ); ?>"><strong><?php echo esc_html( $r->vehicle_title ); ?></strong></a></td>
						<td><?php echo esc_html( $r->customer_name ); ?><br><small><?php echo esc_html( $r->customer_phone ); ?></small></td>
						<td><?php echo esc_html( adc_date( $r->starts_at, true ) ); ?><br><small>→ <?php echo esc_html( adc_date( $r->ends_at, true ) ); ?></small></td>
						<td><?php echo esc_html( adc_money( $r->total_amount, $r->currency ) ); ?></td>
						<td><?php echo adc_badge( $r->payment_status, 'payment_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo adc_badge( $r->status, 'rental_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
