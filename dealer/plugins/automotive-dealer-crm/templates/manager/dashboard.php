<?php
/**
 * CRM dashboard.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

$counts   = ADC\Vehicles::counts();
$leads    = ADC\Leads::counts_by_status();
$monthly  = ADC\Sales::monthly( 6 );
$month    = wp_date( 'Y-m' );
$reports  = current_user_can( 'adc_view_reports' );
$followup = ADC\Leads::due_followups();
$total    = array_sum( $counts ) - $counts['archived'];

Manager::head(
	/* translators: %s: first name */
	sprintf( __( 'Hello %s', 'automotive-dealer-crm' ), wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name ),
	wp_date( 'l j F Y' )
);
?>
<div class="adc-kpis">
	<a class="adc-kpi" href="<?php echo esc_url( adc_manager_url( 'vehicles' ) ); ?>"><span class="adc-kpi__label"><?php esc_html_e( 'Vehicles in stock', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span class="adc-kpi__sub"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d available', 'automotive-dealer-crm' ), $counts['published'] ) ); ?></span></a>
	<a class="adc-kpi" href="<?php echo esc_url( adc_manager_url( 'vehicles', '', array( 'status' => 'reserved' ) ) ); ?>"><span class="adc-kpi__label"><?php esc_html_e( 'Reserved', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $counts['reserved'] ) ); ?></strong><span class="adc-kpi__sub"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d rented out', 'automotive-dealer-crm' ), $counts['rented'] ) ); ?></span></a>
	<a class="adc-kpi" href="<?php echo esc_url( adc_manager_url( 'leads', '', array( 'status' => 'new' ) ) ); ?>"><span class="adc-kpi__label"><?php esc_html_e( 'New leads', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $leads['new'] ) ); ?></strong><span class="adc-kpi__sub"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '%d in negotiation', 'automotive-dealer-crm' ), $leads['negotiation'] ) ); ?></span></a>
	<a class="adc-kpi" href="<?php echo esc_url( adc_manager_url( 'sales' ) ); ?>"><span class="adc-kpi__label"><?php esc_html_e( 'Sales this month', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $monthly[ $month ][0] ) ); ?></strong><span class="adc-kpi__sub"><?php echo $reports ? esc_html( adc_money( $monthly[ $month ][1] ) ) : esc_html( sprintf( /* translators: %d: count */ __( '%d sold in total', 'automotive-dealer-crm' ), $counts['sold'] ) ); ?></span></a>
	<?php if ( $reports ) : ?>
		<a class="adc-kpi" href="<?php echo esc_url( adc_manager_url( 'rentals' ) ); ?>"><span class="adc-kpi__label"><?php esc_html_e( 'Rental revenue this month', 'automotive-dealer-crm' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( adc_money( ADC\Rentals::revenue_this_month() ) ); ?></strong></a>
	<?php endif; ?>
</div>

<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'To follow up today', 'automotive-dealer-crm' ); ?></h2><a class="amds-link" href="<?php echo esc_url( adc_manager_url( 'leads' ) ); ?>"><?php esc_html_e( 'All leads', 'automotive-dealer-crm' ); ?></a></div>
		<?php if ( ! $followup ) : ?>
			<p class="adc-muted"><?php esc_html_e( 'No follow-up due. Well done.', 'automotive-dealer-crm' ); ?></p>
		<?php else : ?>
			<ul class="adc-list">
				<?php foreach ( $followup as $lead ) : ?>
					<li>
						<span><a href="<?php echo esc_url( adc_manager_url( 'leads', $lead->id ) ); ?>"><strong><?php echo esc_html( $lead->name ); ?></strong></a><small><?php echo esc_html( ( $lead->vehicle_title ? $lead->vehicle_title . ' · ' : '' ) . adc_date( $lead->follow_up_at, true ) ); ?></small></span>
						<?php if ( $lead->phone ) : ?><a class="amds-btn amds-btn--ghost" href="<?php echo esc_url( 'tel:' . $lead->phone ); ?>"><?php echo adc_icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Upcoming appointments', 'automotive-dealer-crm' ); ?></h2></div>
		<?php $appointments = ADC\Appointments::upcoming( 8 ); ?>
		<?php if ( ! $appointments ) : ?>
			<p class="adc-muted"><?php esc_html_e( 'No appointment scheduled.', 'automotive-dealer-crm' ); ?></p>
		<?php else : ?>
			<ul class="adc-list">
				<?php foreach ( $appointments as $appointment ) : ?>
					<li>
						<span class="adc-date-chip"><?php echo esc_html( wp_date( 'j M', strtotime( $appointment->starts_at . ' UTC' ) ) ); ?><small><?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $appointment->starts_at . ' UTC' ) ) ); ?></small></span>
						<span><strong><?php echo esc_html( $appointment->title ? $appointment->title : ( $appointment->lead_name ? $appointment->lead_name : __( 'Appointment', 'automotive-dealer-crm' ) ) ); ?></strong><small><?php echo esc_html( trim( $appointment->lead_name . ' · ' . $appointment->vehicle_title . ' · ' . Manager::user_name( $appointment->user_id ), ' ·' ) ); ?></small></span>
						<?php if ( $appointment->lead_id ) : ?><a class="amds-link" href="<?php echo esc_url( adc_manager_url( 'leads', $appointment->lead_id ) ); ?>"><?php esc_html_e( 'Open', 'automotive-dealer-crm' ); ?></a><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>

<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Pipeline', 'automotive-dealer-crm' ); ?></h2></div>
		<?php $max = max( 1, max( $leads ) ); ?>
		<ul class="adc-bars">
			<?php foreach ( ADC\Labels::lead_statuses() as $key => $label ) : ?>
				<li><a href="<?php echo esc_url( adc_manager_url( 'leads', '', array( 'status' => $key ) ) ); ?>"><span class="adc-bars__label"><?php echo esc_html( $label ); ?></span><span class="adc-bars__bar adc-bars__bar--<?php echo esc_attr( $key ); ?>"><span style="width:<?php echo esc_attr( (int) round( 100 * $leads[ $key ] / $max ) ); ?>%"></span></span><span class="adc-bars__value"><?php echo esc_html( $leads[ $key ] ); ?></span></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php echo esc_html( $reports ? __( 'Sales — last 6 months', 'automotive-dealer-crm' ) : __( 'Vehicles by status', 'automotive-dealer-crm' ) ); ?></h2></div>
		<?php if ( $reports ) : ?>
			<?php $max = max( 1, max( array_map( static function ( $m ) { return $m[1]; }, $monthly ) ) ); ?>
			<div class="adc-columns">
				<?php foreach ( $monthly as $key => $m ) : ?>
					<div class="adc-columns__col" title="<?php echo esc_attr( adc_money( $m[1] ) ); ?>">
						<span class="adc-columns__value"><?php echo esc_html( $m[0] ); ?></span>
						<span class="adc-columns__bar" style="height:<?php echo esc_attr( max( 4, (int) round( 100 * $m[1] / $max ) ) ); ?>%"></span>
						<span class="adc-columns__label"><?php echo esc_html( wp_date( 'M', strtotime( $key . '-15' ) ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<ul class="adc-list">
				<?php foreach ( ADC\Labels::vehicle_statuses() as $key => $label ) : ?>
					<li><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $counts[ $key ] ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>

<section class="adc-panel">
	<div class="adc-panel__head"><h2><?php esc_html_e( 'Recent activity', 'automotive-dealer-crm' ); ?></h2><?php if ( current_user_can( 'adc_view_reports' ) ) : ?><a class="amds-link" href="<?php echo esc_url( adc_manager_url( 'activity' ) ); ?>"><?php esc_html_e( 'All', 'automotive-dealer-crm' ); ?></a><?php endif; ?></div>
	<ul class="adc-list">
		<?php foreach ( ADC\Activity::latest( 8 ) as $event ) : ?>
			<li><span><strong><?php echo esc_html( Manager::user_name( $event->user_id ) ); ?></strong> <?php echo esc_html( $event->summary ? $event->summary : $event->action ); ?><small><?php echo esc_html( adc_date( $event->created_at, true ) ); ?></small></span></li>
		<?php endforeach; ?>
	</ul>
</section>
