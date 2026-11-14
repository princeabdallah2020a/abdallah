<?php
/**
 * Platform admin — growth reports.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$months = AMC\Reports::monthly();
$top    = AMC\Reports::top_dealers();
$totals = array_fill_keys( array( 'dealers', 'revenue', 'leads', 'whatsapp', 'listings' ), 0 );
foreach ( $months as $m ) {
	foreach ( $totals as $key => $value ) {
		$totals[ $key ] += $m[ $key ];
	}
}
$max = max( 1, max( wp_list_pluck( $months, 'revenue' ) ) );
Portal::page_head( __( 'Reports', 'automotive-marketplace-core' ), __( 'Growth of the platform over the last 12 months.', 'automotive-marketplace-core' ) );
?>
<div class="amc-kpis">
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Payments received (12 months)', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( amc_format_money( $totals['revenue'] ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'New dealers', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $totals['dealers'] ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Requests sent to dealers', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $totals['leads'] ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'WhatsApp messages received', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $totals['whatsapp'] ) ); ?></strong></div>
</div>

<section class="amc-panel">
	<div class="amc-panel__head"><h2><?php esc_html_e( 'Payments received per month', 'automotive-marketplace-core' ); ?></h2></div>
	<div class="amc-chart" role="img" aria-label="<?php esc_attr_e( 'Payments received per month', 'automotive-marketplace-core' ); ?>">
		<?php foreach ( $months as $key => $m ) : ?>
			<div class="amc-chart__col" title="<?php echo esc_attr( amc_format_money( $m['revenue'] ) ); ?>">
				<span class="amc-chart__bar" style="height:<?php echo esc_attr( max( 2, (int) round( 100 * $m['revenue'] / $max ) ) ); ?>%"></span>
				<span class="amc-chart__label"><?php echo esc_html( date_i18n( 'M', strtotime( $key . '-15' ) ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Month by month', 'automotive-marketplace-core' ); ?></h2></div>
		<div class="amc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Month', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealers', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Listings', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Requests', 'automotive-marketplace-core' ); ?></th><th>WhatsApp</th></tr></thead>
				<tbody>
					<?php foreach ( array_reverse( $months, true ) as $key => $m ) : ?>
						<tr><td><?php echo esc_html( date_i18n( 'F Y', strtotime( $key . '-15' ) ) ); ?></td><td><?php echo esc_html( $m['dealers'] ); ?></td><td><?php echo esc_html( $m['listings'] ); ?></td><td><?php echo esc_html( $m['leads'] ); ?></td><td><?php echo esc_html( $m['whatsapp'] ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Most requested dealers (90 days)', 'automotive-marketplace-core' ); ?></h2></div>
		<div class="amc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Requests', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Listings online', 'automotive-marketplace-core' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $top ) : ?>
						<tr><td colspan="3" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $top as $row ) : ?>
						<tr><td><a href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $row->id ) ); ?>"><?php echo esc_html( $row->business_name ); ?></a></td><td><?php echo esc_html( $row->leads ); ?></td><td><?php echo esc_html( $row->listings ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
