<?php
/**
 * CRM — activity log.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'adc_view_reports' ) ) {
	return;
}
ADC\Manager::head( __( 'Activity', 'automotive-dealer-crm' ), __( 'Who did what, and when.', 'automotive-dealer-crm' ) );
?>
<section class="adc-panel adc-panel--flush">
	<div class="adc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Date', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Person', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Action', 'automotive-dealer-crm' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( ADC\Activity::latest( 300 ) as $event ) : ?>
					<tr>
						<td><?php echo esc_html( adc_date( $event->created_at, true ) ); ?></td>
						<td><?php echo esc_html( ADC\Manager::user_name( $event->user_id ) ); ?></td>
						<td><code><?php echo esc_html( $event->action ); ?></code> <?php echo esc_html( $event->summary ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
