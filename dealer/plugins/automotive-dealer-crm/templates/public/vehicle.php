<?php
/**
 * Public vehicle page.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;

$v         = ADC\Router::$data['vehicle'];
$media     = ADC\Router::$data['media'];
$similar   = array_values( array_filter( ADC\Router::$data['similar'], static function ( $item ) use ( $v ) {
	return (int) $item->id !== (int) $v->id;
} ) );
$b         = adc_business();
$available = in_array( $v->status, ADC\Vehicles::PUBLIC_STATUSES, true );
$specs     = array_filter(
	array(
		__( 'Brand', 'automotive-dealer-crm' )        => $v->brand,
		__( 'Model', 'automotive-dealer-crm' )        => $v->model,
		__( 'Year', 'automotive-dealer-crm' )         => $v->year ? (string) $v->year : '',
		__( 'Mileage', 'automotive-dealer-crm' )      => $v->mileage_km ? adc_km( $v->mileage_km ) : '',
		__( 'Fuel type', 'automotive-dealer-crm' )    => adc_label( 'fuel_types', $v->fuel_type ),
		__( 'Transmission', 'automotive-dealer-crm' ) => adc_label( 'transmissions', $v->transmission ),
		__( 'Body type', 'automotive-dealer-crm' )    => adc_label( 'body_types', $v->body_type ),
		__( 'Condition', 'automotive-dealer-crm' )    => adc_label( 'conditions', $v->vehicle_condition ),
		__( 'Color', 'automotive-dealer-crm' )        => $v->color,
	)
);
$whatsapp  = adc_whatsapp_url( $b->whatsapp, sprintf( /* translators: 1: vehicle, 2: url */ __( 'Hello, I am interested in the %1$s: %2$s', 'automotive-dealer-crm' ), $v->title, adc_vehicle_url( $v ) ) );

get_header();
?>
<main id="main" class="adc-main">
	<div class="amds-container adc-vehicle">
		<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automotive-dealer-crm' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automotive-dealer-crm' ); ?></a>
			<a href="<?php echo esc_url( adc_inventory_url( array(), 'rent' === $v->listing_type && ADC\Settings::get( 'show_rentals' ) ) ); ?>"><?php echo esc_html( 'rent' === $v->listing_type ? __( 'Rentals', 'automotive-dealer-crm' ) : __( 'Our vehicles', 'automotive-dealer-crm' ) ); ?></a>
			<span aria-current="page"><?php echo esc_html( $v->title ); ?></span>
		</nav>
		<?php if ( ! $available ) : ?>
			<div class="amds-alert amds-alert--warning"><?php echo esc_html( 'sold' === $v->status ? __( 'This vehicle has been sold. Discover our other vehicles below.', 'automotive-dealer-crm' ) : __( 'This vehicle is currently rented. Contact us for the next availability.', 'automotive-dealer-crm' ) ); ?></div>
		<?php endif; ?>
		<div class="adc-vehicle__layout">
			<div class="adc-vehicle__main">
				<section class="adc-gallery" data-adc-gallery aria-label="<?php esc_attr_e( 'Photos', 'automotive-dealer-crm' ); ?>">
					<?php if ( $media ) : ?>
						<div class="adc-gallery__stage">
							<?php echo wp_get_attachment_image( $media[0], 'large', false, array( 'data-adc-gallery-main' => true, 'fetchpriority' => 'high', 'loading' => 'eager', 'alt' => $v->title ) ); ?>
							<?php if ( count( $media ) > 1 ) : ?>
								<button type="button" class="adc-gallery__nav adc-gallery__nav--prev" data-adc-prev aria-label="<?php esc_attr_e( 'Previous photo', 'automotive-dealer-crm' ); ?>"><?php echo adc_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<button type="button" class="adc-gallery__nav adc-gallery__nav--next" data-adc-next aria-label="<?php esc_attr_e( 'Next photo', 'automotive-dealer-crm' ); ?>"><?php echo adc_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<span class="adc-gallery__count"><span data-adc-index>1</span> / <?php echo esc_html( count( $media ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( count( $media ) > 1 ) : ?>
							<ul class="adc-gallery__thumbs">
								<?php foreach ( $media as $i => $attachment_id ) : ?>
									<?php $large = wp_get_attachment_image_src( $attachment_id, 'large' ); ?>
									<li><a href="<?php echo esc_url( $large ? $large[0] : '' ); ?>" data-adc-thumb data-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $attachment_id, 'large' ) ); ?>"<?php echo 0 === $i ? ' class="is-active"' : ''; ?>><?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => sprintf( /* translators: %d: number */ __( 'Photo %d', 'automotive-dealer-crm' ), $i + 1 ) ) ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					<?php else : ?>
						<div class="adc-gallery__stage adc-gallery__stage--empty"><?php echo adc_icon( 'car', 56 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
				</section>

				<section class="adc-block">
					<h2><?php esc_html_e( 'Specifications', 'automotive-dealer-crm' ); ?></h2>
					<dl class="adc-specs">
						<?php foreach ( $specs as $label => $value ) : ?>
							<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				</section>
				<?php if ( $v->description ) : ?>
					<section class="adc-block adc-prose"><h2><?php esc_html_e( 'Description', 'automotive-dealer-crm' ); ?></h2><?php echo wp_kses_post( wpautop( esc_html( $v->description ) ) ); ?></section>
				<?php endif; ?>
				<?php if ( $v->features ) : ?>
					<section class="adc-block"><h2><?php esc_html_e( 'Equipment', 'automotive-dealer-crm' ); ?></h2>
						<ul class="adc-features"><?php foreach ( $v->features as $feature ) : ?><li><?php echo adc_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul>
					</section>
				<?php endif; ?>
			</div>

			<aside class="adc-vehicle__aside">
				<div class="adc-summary">
					<p class="adc-summary__badges">
						<span class="amds-badge amds-badge--<?php echo 'rent' === $v->listing_type ? 'info' : 'dark'; ?>"><?php echo esc_html( Labels::label( 'listing_types', $v->listing_type ) ); ?></span>
						<?php if ( 'reserved' === $v->status ) : ?><span class="amds-badge amds-badge--warning"><?php esc_html_e( 'Reserved', 'automotive-dealer-crm' ); ?></span><?php endif; ?>
					</p>
					<h1 class="adc-summary__title"><?php echo esc_html( $v->title ); ?></h1>
					<p class="adc-summary__price"><?php echo esc_html( adc_vehicle_price( $v ) ); ?></p>
					<?php if ( $available ) : ?>
						<div class="adc-summary__actions">
							<?php if ( $whatsapp ) : ?><a class="amds-btn amds-btn--whatsapp amds-btn--lg amds-btn--block" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener"><?php echo adc_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Ask on WhatsApp', 'automotive-dealer-crm' ); ?></a><?php endif; ?>
							<?php if ( $b->phone ) : ?><a class="amds-btn amds-btn--secondary amds-btn--lg amds-btn--block" href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo adc_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $b->phone ); ?></a><?php endif; ?>
							<a class="amds-btn amds-btn--ghost amds-btn--block" href="#adc-lead"><?php echo adc_icon( 'mail', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Send a request', 'automotive-dealer-crm' ); ?></a>
						</div>
					<?php endif; ?>
					<ul class="adc-summary__trust">
						<?php if ( $b->address || $b->city ) : ?><li><?php echo adc_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( trim( $b->address . ', ' . $b->city, ', ' ) ); ?></li><?php endif; ?>
						<?php if ( $b->hours ) : ?><li><?php echo adc_icon( 'clock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $b->hours ); ?></li><?php endif; ?>
					</ul>
				</div>
				<?php if ( $available ) : ?>
					<div class="adc-summary">
						<?php adc_template( 'public/parts/lead-form', array( 'vehicle' => $v, 'title' => __( 'Interested? Leave us your number', 'automotive-dealer-crm' ) ) ); ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<?php if ( $similar ) : ?>
			<section class="adc-block">
				<h2><?php esc_html_e( 'You may also like', 'automotive-dealer-crm' ); ?></h2>
				<div class="adc-grid adc-grid--4"><?php foreach ( array_slice( $similar, 0, 4 ) as $item ) { adc_template( 'public/parts/vehicle-card', array( 'vehicle' => $item ) ); } ?></div>
			</section>
		<?php endif; ?>
	</div>
	<?php if ( $available && ( $whatsapp || $b->phone ) ) : ?>
		<div class="adc-sticky-cta">
			<?php if ( $b->phone ) : ?><a class="amds-btn amds-btn--secondary" href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo adc_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Call', 'automotive-dealer-crm' ); ?></a><?php endif; ?>
			<?php if ( $whatsapp ) : ?><a class="amds-btn amds-btn--whatsapp" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener"><?php echo adc_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>WhatsApp</a><?php endif; ?>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
