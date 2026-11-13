<?php
/**
 * CRM — leads: pipeline board and list.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$view   = isset( $_GET['view'] ) && 'list' === $_GET['view'] ? 'list' : 'board';
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$mine   = ! empty( $_GET['mine'] );
// phpcs:enable
if ( $status ) {
	$view = 'list';
}
$leads = ADC\Leads::search(
	array(
		'status' => $status,
		'source' => $source,
		'q'      => $q,
		'mine'   => $mine,
	)
);

Manager::head(
	__( 'Leads', 'automotive-dealer-crm' ),
	current_user_can( 'adc_view_all_leads' ) ? '' : __( 'You see the leads assigned to you and the unassigned ones.', 'automotive-dealer-crm' ),
	current_user_can( 'adc_manage_leads' ) ? Manager::link_button( adc_manager_url( 'leads', 'new' ), __( 'New lead', 'automotive-dealer-crm' ), 'amds-btn--primary', 'plus' ) : ''
);
?>
<form class="adc-toolbar" method="get" action="<?php echo esc_url( adc_manager_url( 'leads' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="adc_path" value="manager/leads"><?php endif; ?>
	<div class="adc-segment">
		<a class="<?php echo 'board' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( adc_manager_url( 'leads' ) ); ?>"><?php esc_html_e( 'Pipeline', 'automotive-dealer-crm' ); ?></a>
		<a class="<?php echo 'list' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( adc_manager_url( 'leads', '', array( 'view' => 'list' ) ) ); ?>"><?php esc_html_e( 'List', 'automotive-dealer-crm' ); ?></a>
	</div>
	<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
	<input class="amds-input" type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Name, phone or email', 'automotive-dealer-crm' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'automotive-dealer-crm' ); ?>">
	<select class="amds-select" name="source" aria-label="<?php esc_attr_e( 'Source', 'automotive-dealer-crm' ); ?>"><option value=""><?php esc_html_e( 'All sources', 'automotive-dealer-crm' ); ?></option><?php foreach ( Labels::lead_sources() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $source, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
	<?php if ( 'list' === $view ) : ?>
		<select class="amds-select" name="status" aria-label="<?php esc_attr_e( 'Stage', 'automotive-dealer-crm' ); ?>"><option value=""><?php esc_html_e( 'All stages', 'automotive-dealer-crm' ); ?></option><?php foreach ( Labels::lead_statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
	<?php endif; ?>
	<label class="adc-check"><input type="checkbox" name="mine" value="1" <?php checked( $mine ); ?>> <?php esc_html_e( 'Mine only', 'automotive-dealer-crm' ); ?></label>
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Filter', 'automotive-dealer-crm' ); ?></button>
</form>

<?php if ( 'board' === $view ) : ?>
	<div class="adc-board">
		<?php foreach ( Labels::lead_statuses() as $key => $label ) : ?>
			<?php
			$column = array_filter(
				$leads,
				static function ( $lead ) use ( $key ) {
					return $lead->status === $key;
				}
			);
			if ( in_array( $key, array( 'won', 'lost' ), true ) ) {
				$column = array_slice( $column, 0, 8 );
			}
			?>
			<section class="adc-board__col adc-board__col--<?php echo esc_attr( $key ); ?>">
				<header><span><?php echo esc_html( $label ); ?></span><span class="adc-board__count"><?php echo esc_html( count( $column ) ); ?></span></header>
				<?php foreach ( $column as $lead ) : ?>
					<article class="adc-lead-card">
						<a class="adc-lead-card__name" href="<?php echo esc_url( adc_manager_url( 'leads', $lead->id ) ); ?>"><?php echo esc_html( $lead->name ); ?></a>
						<?php if ( $lead->vehicle_title ) : ?><span class="adc-lead-card__meta"><?php echo adc_icon( 'car', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $lead->vehicle_title ); ?></span><?php endif; ?>
						<span class="adc-lead-card__meta"><?php echo esc_html( Labels::label( 'lead_sources', $lead->source ) . ' · ' . human_time_diff( strtotime( $lead->created_at . ' UTC' ) ) ); ?></span>
						<?php if ( $lead->follow_up_at ) : ?>
							<?php $late = strtotime( $lead->follow_up_at . ' UTC' ) < time(); ?>
							<span class="adc-lead-card__follow<?php echo $late ? ' is-late' : ''; ?>"><?php echo adc_icon( 'clock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( adc_date( $lead->follow_up_at, true ) ); ?></span>
						<?php endif; ?>
						<footer>
							<span class="adc-avatar" title="<?php echo esc_attr( Manager::user_name( $lead->assigned_to ) ); ?>"><?php echo esc_html( $lead->assigned_to ? adc_initials( Manager::user_name( $lead->assigned_to ) ) : '—' ); ?></span>
							<?php if ( current_user_can( 'adc_manage_leads' ) && ! in_array( $key, array( 'won', 'lost' ), true ) ) : ?>
								<?php $keys = array_keys( Labels::lead_statuses() ); $next = $keys[ array_search( $key, $keys, true ) + 1 ]; ?>
								<?php if ( 'won' !== $next ) : ?>
									<?php Manager::button( 'lead_status', $lead->id, Labels::label( 'lead_statuses', $next ) . ' →', 'amds-btn--ghost adc-btn-xs', array( 'status' => $next ) ); ?>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( $lead->phone ) : ?>
								<a class="adc-icon-btn" href="<?php echo esc_url( adc_whatsapp_url( $lead->phone ) ); ?>" target="_blank" rel="noopener" title="WhatsApp"><?php echo adc_icon( 'chat', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
								<a class="adc-icon-btn" href="<?php echo esc_url( 'tel:' . $lead->phone ); ?>" title="<?php esc_attr_e( 'Call', 'automotive-dealer-crm' ); ?>"><?php echo adc_icon( 'phone', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<section class="adc-panel adc-panel--flush">
		<div class="adc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Name', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Source', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Assigned to', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Follow-up', 'automotive-dealer-crm' ); ?></th><th><?php esc_html_e( 'Stage', 'automotive-dealer-crm' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $leads ) : ?><tr><td colspan="6" class="adc-muted"><?php esc_html_e( 'No lead found.', 'automotive-dealer-crm' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $leads as $lead ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( adc_manager_url( 'leads', $lead->id ) ); ?>"><strong><?php echo esc_html( $lead->name ); ?></strong></a><br><small><?php echo esc_html( $lead->phone ); ?></small></td>
							<td><?php echo esc_html( $lead->vehicle_title ? $lead->vehicle_title : '—' ); ?></td>
							<td><?php echo esc_html( Labels::label( 'lead_sources', $lead->source ) ); ?></td>
							<td><?php echo esc_html( Manager::user_name( $lead->assigned_to ) ); ?></td>
							<td><?php echo esc_html( adc_date( $lead->follow_up_at, true ) ); ?></td>
							<td><?php echo adc_badge( $lead->status, 'lead_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endif; ?>
