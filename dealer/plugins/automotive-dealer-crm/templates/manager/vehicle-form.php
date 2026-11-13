<?php
/**
 * CRM — add / edit a vehicle.
 *
 * @var array $args { item: int }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

$v = $args['item'] ? ADC\Vehicles::get( $args['item'] ) : null;
if ( $args['item'] && ! $v ) {
	Manager::head( __( 'Vehicle not found', 'automotive-dealer-crm' ) );
	return;
}
$can_edit  = current_user_can( 'adc_manage_vehicles' );
$can_pub   = current_user_can( 'adc_publish_vehicles' );
$is_public = $v && in_array( $v->status, ADC\Vehicles::PUBLIC_STATUSES, true );
$price_ro  = $is_public && ! current_user_can( 'adc_edit_prices' );
$val       = static function ( $key, $default = '' ) use ( $v ) {
	return $v ? $v->$key : $default;
};
$media     = $v ? ADC\Vehicles::media( $v->id ) : array();

$actions = '';
if ( $v && $is_public ) {
	$actions .= Manager::link_button( adc_vehicle_url( $v ), __( 'View on the website', 'automotive-dealer-crm' ), 'amds-btn--secondary', 'external' );
}
Manager::head( $v ? $v->title : __( 'Add a vehicle', 'automotive-dealer-crm' ), $v ? Labels::label( 'vehicle_statuses', $v->status ) . ' · ' . sprintf( /* translators: %s: date */ __( 'updated %s', 'automotive-dealer-crm' ), adc_date( $v->updated_at, true ) ) : '', $actions );
?>
<?php if ( $v && 'pending_review' === $v->status ) : ?>
	<div class="amds-alert amds-alert--warning"><?php esc_html_e( 'This vehicle is waiting for a manager to approve it before it appears on the website.', 'automotive-dealer-crm' ); ?></div>
<?php endif; ?>

<form class="adc-vehicle-form" method="post" enctype="multipart/form-data">
	<?php Manager::action_fields( 'vehicle_save', $v ? $v->id : 0 ); ?>
	<fieldset class="adc-fieldset-reset" <?php disabled( ! $can_edit ); ?>>
	<div class="adc-form-layout">
		<div class="adc-form-layout__main">
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></h2></div>
				<div class="amds-form__row">
					<?php
					Manager::field( 'brand', __( 'Brand', 'automotive-dealer-crm' ), $val( 'brand' ), 'text', array( 'attrs' => array( 'required' => true, 'list' => 'adc-brands', 'maxlength' => 80 ) ) );
					Manager::field( 'model', __( 'Model', 'automotive-dealer-crm' ), $val( 'model' ), 'text', array( 'attrs' => array( 'required' => true, 'maxlength' => 80 ) ) );
					Manager::field( 'year', __( 'Year', 'automotive-dealer-crm' ), $val( 'year' ) ? $val( 'year' ) : '', 'number', array( 'attrs' => array( 'min' => 1950, 'max' => (int) gmdate( 'Y' ) + 1 ) ) );
					Manager::field( 'mileage_km', __( 'Mileage (km)', 'automotive-dealer-crm' ), $val( 'mileage_km' ) ? $val( 'mileage_km' ) : '', 'number', array( 'attrs' => array( 'min' => 0 ) ) );
					?>
				</div>
				<datalist id="adc-brands"><?php foreach ( array( 'Toyota', 'Hyundai', 'Kia', 'Mercedes-Benz', 'BMW', 'Peugeot', 'Renault', 'Nissan', 'Mitsubishi', 'Ford', 'Suzuki', 'Volkswagen', 'Honda', 'Audi', 'Land Rover', 'Range Rover', 'Dacia', 'Isuzu', 'Lexus', 'Chevrolet' ) as $brand ) : ?><option value="<?php echo esc_attr( $brand ); ?>"><?php endforeach; ?></datalist>
				<div class="amds-form__row">
					<?php
					Manager::field( 'fuel_type', __( 'Fuel type', 'automotive-dealer-crm' ), $val( 'fuel_type' ), 'select', array( 'options' => array( '' => '—' ) + Labels::fuel_types() ) );
					Manager::field( 'transmission', __( 'Transmission', 'automotive-dealer-crm' ), $val( 'transmission' ), 'select', array( 'options' => array( '' => '—' ) + Labels::transmissions() ) );
					Manager::field( 'body_type', __( 'Body type', 'automotive-dealer-crm' ), $val( 'body_type' ), 'select', array( 'options' => array( '' => '—' ) + Labels::body_types() ) );
					Manager::field( 'vehicle_condition', __( 'Condition', 'automotive-dealer-crm' ), $val( 'vehicle_condition' ), 'select', array( 'options' => array( '' => '—' ) + Labels::conditions() ) );
					Manager::field( 'color', __( 'Color', 'automotive-dealer-crm' ), $val( 'color' ), 'text', array( 'attrs' => array( 'maxlength' => 40 ) ) );
					?>
				</div>
				<?php
				Manager::field( 'description', __( 'Description', 'automotive-dealer-crm' ), $val( 'description' ), 'textarea', array( 'attrs' => array( 'rows' => 6 ), 'help' => __( 'Condition, maintenance, history, options: buyers read this first.', 'automotive-dealer-crm' ) ) );
				Manager::field( 'features', __( 'Equipment (one per line)', 'automotive-dealer-crm' ), $v ? implode( "\n", $v->features ) : '', 'textarea', array( 'attrs' => array( 'rows' => 5, 'placeholder' => __( "Air conditioning\nReversing camera\nBluetooth", 'automotive-dealer-crm' ) ) ) );
				?>
			</section>

			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Photos', 'automotive-dealer-crm' ); ?></h2><span class="adc-muted"><?php esc_html_e( 'The first photo is the cover. Change the numbers to reorder.', 'automotive-dealer-crm' ); ?></span></div>
				<?php if ( $media ) : ?>
					<ul class="adc-photos">
						<?php foreach ( $media as $position => $attachment_id ) : ?>
							<li>
								<?php echo wp_get_attachment_image( $attachment_id, 'medium', false, array( 'loading' => 'lazy' ) ); ?>
								<div class="adc-photos__tools">
									<label><span class="screen-reader-text"><?php esc_html_e( 'Position', 'automotive-dealer-crm' ); ?></span><input class="amds-input" type="number" min="0" name="media_order[<?php echo esc_attr( $attachment_id ); ?>]" value="<?php echo esc_attr( $position ); ?>"></label>
									<label class="adc-check"><input type="checkbox" name="remove_media[]" value="<?php echo esc_attr( $attachment_id ); ?>"> <?php esc_html_e( 'Remove', 'automotive-dealer-crm' ); ?></label>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<label class="adc-drop" data-adc-drop>
					<?php echo adc_icon( 'plus', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Add photos (JPG, PNG, WEBP — several at once)', 'automotive-dealer-crm' ); ?></span>
					<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-adc-photos>
				</label>
				<ul class="adc-photos adc-photos--preview" data-adc-preview></ul>
			</section>
		</div>

		<aside class="adc-form-layout__side">
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Price and publication', 'automotive-dealer-crm' ); ?></h2></div>
				<?php
				Manager::field( 'listing_type', __( 'Offer', 'automotive-dealer-crm' ), $val( 'listing_type', 'sale' ), 'select', array( 'options' => Labels::listing_types(), 'attrs' => array( 'data-adc-listing-type' => true ) ) );
				Manager::field( 'price_amount', $price_ro ? __( 'Price (managers only)', 'automotive-dealer-crm' ) : __( 'Price', 'automotive-dealer-crm' ), $val( 'price_amount' ) ? $val( 'price_amount' ) : '', 'number', array( 'attrs' => array_filter( array( 'min' => 0, 'readonly' => $price_ro ) ), 'help' => __( 'For a rental: the rate per period below.', 'automotive-dealer-crm' ) ) );
				?>
				<div data-adc-rent-only>
					<?php Manager::field( 'price_period', __( 'Rental period', 'automotive-dealer-crm' ), $val( 'price_period', 'day' ), 'select', array( 'options' => array( 'day' => Labels::price_periods()['day'], 'hour' => Labels::price_periods()['hour'], 'month' => Labels::price_periods()['month'] ) ) ); ?>
				</div>
				<?php
				$statuses = array( 'draft' => Labels::label( 'vehicle_statuses', 'draft' ) );
				$statuses['published'] = $can_pub ? Labels::label( 'vehicle_statuses', 'published' ) : __( 'Send for publication', 'automotive-dealer-crm' );
				if ( ! $v || in_array( $v->status, array( 'draft', 'pending_review', 'published' ), true ) ) {
					Manager::field( 'status', __( 'Status', 'automotive-dealer-crm' ), $v ? ( 'pending_review' === $v->status ? 'published' : $v->status ) : 'draft', 'select', array( 'options' => $statuses ) );
				} else {
					echo '<p>' . adc_badge( $v->status, 'vehicle_statuses' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
				<label class="adc-check"><input type="checkbox" name="is_featured" value="1" <?php checked( $val( 'is_featured' ), 1 ); ?>> <?php esc_html_e( 'Highlight on my homepage', 'automotive-dealer-crm' ); ?></label>
				<label class="adc-check"><input type="checkbox" name="publish_to_marketplace" value="1" <?php checked( $v ? $v->publish_to_marketplace : 1, 1 ); ?>> <?php esc_html_e( 'Also publish on the AutoBuzy marketplace', 'automotive-dealer-crm' ); ?></label>
			</section>
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Private information', 'automotive-dealer-crm' ); ?></h2><?php echo adc_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php
				Manager::field( 'vin', __( 'VIN / chassis number', 'automotive-dealer-crm' ), $val( 'vin' ), 'text', array( 'attrs' => array( 'maxlength' => 40 ) ) );
				if ( current_user_can( 'adc_view_reports' ) ) {
					Manager::field( 'purchase_price', __( 'Purchase cost', 'automotive-dealer-crm' ), $val( 'purchase_price' ) ? $val( 'purchase_price' ) : '', 'number', array( 'attrs' => array( 'min' => 0 ), 'help' => __( 'Never shown publicly.', 'automotive-dealer-crm' ) ) );
				}
				?>
			</section>
			<?php if ( $can_edit ) : ?>
				<button class="amds-btn amds-btn--primary amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Save the vehicle', 'automotive-dealer-crm' ); ?></button>
			<?php endif; ?>
		</aside>
	</div>
	</fieldset>
</form>

<?php if ( $v ) : ?>
	<section class="adc-panel adc-danger-zone">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Status', 'automotive-dealer-crm' ); ?></h2></div>
		<div class="adc-actions">
			<?php
			$moves = array(
				'reserved'  => __( 'Mark reserved', 'automotive-dealer-crm' ),
				'published' => __( 'Back on sale', 'automotive-dealer-crm' ),
				'sold'      => __( 'Mark sold (without recording a sale)', 'automotive-dealer-crm' ),
				'archived'  => __( 'Archive', 'automotive-dealer-crm' ),
				'draft'     => __( 'Back to draft', 'automotive-dealer-crm' ),
			);
			foreach ( $moves as $status => $label ) {
				if ( $status !== $v->status ) {
					Manager::button( 'vehicle_status', $v->id, $label, 'archived' === $status ? 'amds-btn--ghost adc-danger' : 'amds-btn--ghost', array( 'status' => $status ), in_array( $status, array( 'sold', 'archived' ), true ) );
				}
			}
			if ( in_array( $v->status, array( 'draft', 'archived' ), true ) && $can_pub ) {
				Manager::button( 'vehicle_delete', $v->id, __( 'Delete permanently', 'automotive-dealer-crm' ), 'amds-btn--ghost adc-danger', array(), true );
			}
			?>
		</div>
	</section>
<?php endif; ?>
