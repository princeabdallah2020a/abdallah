<?php
/**
 * CRM — vehicles list.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
// phpcs:enable
$counts   = ADC\Vehicles::counts();
$vehicles = ADC\Vehicles::search( array( 'status' => $status, 'type' => $type, 'q' => $q ) );

Manager::head(
	__( 'Vehicles', 'automotive-dealer-crm' ),
	__( 'Your stock is the source of truth: published vehicles appear on your website (and on the marketplace once connected).', 'automotive-dealer-crm' ),
	current_user_can( 'adc_manage_vehicles' ) ? Manager::link_button( adc_manager_url( 'vehicles', 'new' ), __( 'Add a vehicle', 'automotive-dealer-crm' ), 'amds-btn--primary', 'plus' ) : ''
);
?>
<nav class="amds-tabs adc-tabs">
	<a class="amds-tabs__item<?php echo '' === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( adc_manager_url( 'vehicles' ) ); ?>"><?php esc_html_e( 'All', 'automotive-dealer-crm' ); ?></a>
	<?php foreach ( ADC\Labels::vehicle_statuses() as $key => $label ) : ?>
		<?php if ( $counts[ $key ] ) : ?>
			<a class="amds-tabs__item<?php echo $key === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( adc_manager_url( 'vehicles', '', array( 'status' => $key ) ) ); ?>"><?php echo esc_html( $label . ' (' . $counts[ $key ] . ')' ); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
<form class="adc-toolbar" method="get" action="<?php echo esc_url( adc_manager_url( 'vehicles' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="adc_path" value="manager/vehicles"><?php endif; ?>
	<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
	<input class="amds-input" type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Brand, model or VIN', 'automotive-dealer-crm' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'automotive-dealer-crm' ); ?>">
	<select class="amds-select" name="type" aria-label="<?php esc_attr_e( 'Type', 'automotive-dealer-crm' ); ?>"><option value=""><?php esc_html_e( 'Sale and rental', 'automotive-dealer-crm' ); ?></option><?php foreach ( ADC\Labels::listing_types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Filter', 'automotive-dealer-crm' ); ?></button>
</form>

<?php if ( ! $vehicles ) : ?>
	<div class="adc-empty">
		<?php echo adc_icon( 'car', 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<h2><?php esc_html_e( 'No vehicle here yet', 'automotive-dealer-crm' ); ?></h2>
		<?php if ( current_user_can( 'adc_manage_vehicles' ) ) : ?><?php echo Manager::link_button( adc_manager_url( 'vehicles', 'new' ), __( 'Add your first vehicle', 'automotive-dealer-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
	</div>
<?php else : ?>
	<div class="adc-stock">
		<?php foreach ( $vehicles as $v ) : ?>
			<?php $thumb = ADC\Vehicles::thumbnail_url( $v, 'medium' ); ?>
			<article class="adc-stock__item">
				<a class="adc-stock__media" href="<?php echo esc_url( adc_manager_url( 'vehicles', $v->id ) ); ?>">
					<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy"><?php else : ?><span><?php echo adc_icon( 'car', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php endif; ?>
				</a>
				<div class="adc-stock__body">
					<div class="adc-stock__top">
						<a href="<?php echo esc_url( adc_manager_url( 'vehicles', $v->id ) ); ?>"><strong><?php echo esc_html( $v->title ); ?></strong></a>
						<?php echo adc_badge( $v->status, 'vehicle_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<p class="adc-stock__price"><?php echo esc_html( adc_vehicle_price( $v ) ); ?></p>
					<p class="adc-stock__meta"><?php echo esc_html( implode( ' · ', array_filter( array( adc_label( 'listing_types', $v->listing_type ), $v->mileage_km ? adc_km( $v->mileage_km ) : '', adc_label( 'fuel_types', $v->fuel_type ), $v->color ) ) ) ); ?></p>
				</div>
				<div class="adc-stock__actions">
					<?php if ( 'pending_review' === $v->status && current_user_can( 'adc_publish_vehicles' ) ) : ?>
						<?php Manager::button( 'vehicle_status', $v->id, __( 'Approve and publish', 'automotive-dealer-crm' ), 'amds-btn--accent', array( 'status' => 'published' ) ); ?>
					<?php endif; ?>
					<?php if ( 'published' === $v->status ) : ?>
						<?php Manager::button( 'vehicle_status', $v->id, __( 'Reserve', 'automotive-dealer-crm' ), 'amds-btn--ghost', array( 'status' => 'reserved' ) ); ?>
					<?php elseif ( 'reserved' === $v->status ) : ?>
						<?php Manager::button( 'vehicle_status', $v->id, __( 'Release', 'automotive-dealer-crm' ), 'amds-btn--ghost', array( 'status' => 'published' ) ); ?>
					<?php endif; ?>
					<?php if ( in_array( $v->status, array( 'published', 'reserved' ), true ) && 'sale' === $v->listing_type && current_user_can( 'adc_manage_sales' ) ) : ?>
						<a class="amds-btn amds-btn--ghost" href="<?php echo esc_url( adc_manager_url( 'sales', 'new', array( 'vehicle' => $v->id ) ) ); ?>"><?php esc_html_e( 'Record a sale', 'automotive-dealer-crm' ); ?></a>
					<?php endif; ?>
					<?php if ( 'rent' === $v->listing_type && in_array( $v->status, array( 'published', 'reserved' ), true ) && current_user_can( 'adc_manage_rentals' ) ) : ?>
						<a class="amds-btn amds-btn--ghost" href="<?php echo esc_url( adc_manager_url( 'rentals', 'new', array( 'vehicle' => $v->id ) ) ); ?>"><?php esc_html_e( 'Rent', 'automotive-dealer-crm' ); ?></a>
					<?php endif; ?>
					<?php if ( in_array( $v->status, array( 'published', 'reserved' ), true ) ) : ?>
						<a class="amds-btn amds-btn--ghost" href="<?php echo esc_url( adc_vehicle_url( $v ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View on the website', 'automotive-dealer-crm' ); ?>"><?php echo adc_icon( 'external', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
