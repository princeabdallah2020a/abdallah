<?php
/**
 * CRM — reports (managers).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;
use ADC\Reports;

if ( ! current_user_can( 'adc_view_reports' ) ) {
	return;
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
$r    = Reports::build( $days );

$tabs = '';
foreach ( Reports::periods() as $value => $label ) {
	$tabs .= '<a class="amds-btn ' . ( $value === $r['days'] ? 'amds-btn--primary' : 'amds-btn--ghost' ) . '" href="' . esc_url( adc_manager_url( 'reports', '', array( 'days' => $value ) ) ) . '">' . esc_html( $label ) . '</a>';
}
Manager::head( __( 'Reports', 'automotive-dealer-crm' ), __( 'How your dealership is doing: where your customers come from, what sells and how fast.', 'automotive-dealer-crm' ), $tabs );
?>
<div class="adc-kpis">
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'New leads', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $r['leads'] ) ); ?></strong><span class="adc-kpi__sub"><?php /* translators: %s: percentage */ echo esc_html( sprintf( __( '%s%% converted', 'automotive-dealer-crm' ), number_format_i18n( $r['conversion'], 1 ) ) ); ?></span></div>
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Sales', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $r['sales'] ) ); ?></strong><span class="adc-kpi__sub"><?php echo esc_html( adc_money( $r['revenue'] ) ); ?></span></div>
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Gross margin', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( adc_money( $r['margin'] ) ); ?></strong><span class="adc-kpi__sub"><?php /* translators: 1: sales with a purchase price, 2: all sales */ echo esc_html( sprintf( __( 'on %1$d of %2$d sales (purchase price known)', 'automotive-dealer-crm' ), $r['with_cost'], $r['sales'] ) ); ?></span></div>
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Average time to sell', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( null === $r['days_to_sell'] ? '—' : sprintf( /* translators: %d: days */ _n( '%d day', '%d days', (int) $r['days_to_sell'], 'automotive-dealer-crm' ), (int) $r['days_to_sell'] ) ); ?></strong></div>
	<?php if ( $r['fleet'] ) : ?>
		<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Rental income', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( adc_money( $r['rent_income'] ) ); ?></strong><span class="adc-kpi__sub"><?php /* translators: %s: percentage */ echo esc_html( sprintf( __( 'Fleet occupancy %s%%', 'automotive-dealer-crm' ), number_format_i18n( (float) $r['utilisation'], 1 ) ) ); ?></span></div>
	<?php endif; ?>
</div>

<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Where your leads come from', 'automotive-dealer-crm' ); ?></h2></div>
		<?php if ( ! $r['sources'] ) : ?>
			<p class="adc-muted"><?php esc_html_e( 'No leads in this period.', 'automotive-dealer-crm' ); ?></p>
		<?php else : ?>
			<?php $max = max( 1, max( wp_list_pluck( $r['sources'], 'total' ) ) ); ?>
			<ul class="adc-bars">
				<?php foreach ( $r['sources'] as $row ) : ?>
					<li><span class="adc-bars__label"><?php echo esc_html( ADC\Labels::label( 'lead_sources', $row->source ) ); ?></span><span class="adc-bars__bar adc-bars__bar--won"><span style="width:<?php echo esc_attr( (int) round( 100 * $row->total / $max ) ); ?>%"></span></span><span class="adc-bars__value"><?php echo esc_html( $row->total . ' · ' . (int) $row->won . ' ✓' ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<p class="amds-help"><?php esc_html_e( 'Number of leads · leads won. Invest where leads turn into sales.', 'automotive-dealer-crm' ); ?></p>
		<?php endif; ?>
	</section>
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Team', 'automotive-dealer-crm' ); ?></h2></div>
		<ul class="adc-list">
			<?php if ( ! $r['team'] ) : ?>
				<li class="adc-muted"><?php esc_html_e( 'No assigned leads in this period.', 'automotive-dealer-crm' ); ?></li>
			<?php endif; ?>
			<?php foreach ( $r['team'] as $row ) : ?>
				<li><span><?php echo esc_html( Manager::user_name( $row->assigned_to ) ); ?></span><span><?php /* translators: 1: won, 2: total */ echo esc_html( sprintf( __( '%1$d won / %2$d leads', 'automotive-dealer-crm' ), (int) $row->won, (int) $row->total ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>

<section class="adc-panel">
	<div class="adc-panel__head"><h2><?php esc_html_e( 'Vehicles for sale for more than 60 days', 'automotive-dealer-crm' ); ?></h2></div>
	<?php if ( ! $r['aging'] ) : ?>
		<p class="adc-muted"><?php esc_html_e( 'None: your stock is moving.', 'automotive-dealer-crm' ); ?></p>
	<?php else : ?>
		<ul class="adc-list">
			<?php foreach ( $r['aging'] as $v ) : ?>
				<li>
					<a href="<?php echo esc_url( adc_manager_url( 'vehicles', $v->id ) ); ?>"><?php echo esc_html( $v->title ); ?></a>
					<span><?php echo esc_html( adc_money( $v->price_amount, $v->currency ) ); ?> · <?php /* translators: %d: days */ echo esc_html( sprintf( _n( '%d day', '%d days', (int) floor( ( time() - strtotime( $v->published_at . ' UTC' ) ) / DAY_IN_SECONDS ), 'automotive-dealer-crm' ), (int) floor( ( time() - strtotime( $v->published_at . ' UTC' ) ) / DAY_IN_SECONDS ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="amds-help"><?php esc_html_e( 'Consider adjusting the price, the photos or the description of these vehicles.', 'automotive-dealer-crm' ); ?></p>
	<?php endif; ?>
</section>
