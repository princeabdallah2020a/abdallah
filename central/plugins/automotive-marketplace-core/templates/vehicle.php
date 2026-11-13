<?php
/**
 * Vehicle detail page.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$vehicle   = AMC\Marketplace::context( 'vehicle' );
$dealer    = AMC\Marketplace::context( 'dealer' );
$similar   = AMC\Marketplace::context( 'similar' );
$available = in_array( $vehicle->status, array( 'active', 'reserved' ), true );
$page_url  = amc_vehicle_url( $vehicle );

$specs = array_filter(
	array(
		__( 'Brand', 'automotive-marketplace-core' )        => $vehicle->brand,
		__( 'Model', 'automotive-marketplace-core' )        => $vehicle->model,
		__( 'Year', 'automotive-marketplace-core' )         => $vehicle->year ? (string) $vehicle->year : '',
		__( 'Mileage', 'automotive-marketplace-core' )      => $vehicle->mileage_km ? amc_format_mileage( $vehicle->mileage_km ) : '',
		__( 'Fuel type', 'automotive-marketplace-core' )    => amc_label( 'fuel_types', $vehicle->fuel_type ),
		__( 'Transmission', 'automotive-marketplace-core' ) => amc_label( 'transmissions', $vehicle->transmission ),
		__( 'Body type', 'automotive-marketplace-core' )    => amc_label( 'body_types', $vehicle->body_type ),
		__( 'Condition', 'automotive-marketplace-core' )    => amc_label( 'conditions', $vehicle->vehicle_condition ),
		__( 'Color', 'automotive-marketplace-core' )        => $vehicle->color,
		__( 'Location', 'automotive-marketplace-core' )     => $vehicle->city,
	)
);

$whatsapp = '';
if ( $dealer ) {
	$whatsapp = amc_whatsapp_url(
		$dealer->whatsapp,
		/* translators: 1: vehicle title, 2: page URL */
		sprintf( __( 'Hello, I am interested in this vehicle: %1$s — %2$s', 'automotive-marketplace-core' ), $vehicle->title, $page_url )
	);
}

get_header();
?>
<main id="main" class="amc-main">
	<div class="amds-container amc-vehicle">
		<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automotive-marketplace-core' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automotive-marketplace-core' ); ?></a>
			<a href="<?php echo esc_url( amc_catalog_url( 'rent' === $vehicle->listing_type ? 'rent' : 'sale' ) ); ?>"><?php echo esc_html( amc_label( 'listing_types', $vehicle->listing_type ) ); ?></a>
			<a href="<?php echo esc_url( amc_catalog_url( 'all', array( 'brand' => $vehicle->brand_slug ) ) ); ?>"><?php echo esc_html( $vehicle->brand ); ?></a>
			<span aria-current="page"><?php echo esc_html( $vehicle->title ); ?></span>
		</nav>

		<?php if ( ! $available ) : ?>
			<div class="amds-alert amds-alert--warning" role="status">
				<?php echo esc_html( 'sold' === $vehicle->status ? __( 'This vehicle has been sold.', 'automotive-marketplace-core' ) : __( 'This vehicle is currently rented.', 'automotive-marketplace-core' ) ); ?>
				<a href="#amc-similar"><?php esc_html_e( 'See similar vehicles', 'automotive-marketplace-core' ); ?></a>
			</div>
		<?php elseif ( $vehicle->is_stale ) : ?>
			<div class="amds-alert amds-alert--info" role="status">
				<?php esc_html_e( 'The dealer has not confirmed this listing recently. Please check availability with the dealer.', 'automotive-marketplace-core' ); ?>
			</div>
		<?php endif; ?>

		<div class="amc-vehicle__layout">
			<div class="amc-vehicle__main">
				<section class="amc-gallery" data-amc-gallery aria-label="<?php esc_attr_e( 'Vehicle photos', 'automotive-marketplace-core' ); ?>">
					<?php if ( $vehicle->media ) : ?>
						<div class="amc-gallery__stage">
							<img data-amc-gallery-main src="<?php echo esc_url( $vehicle->media[0]->url ); ?>" alt="<?php echo esc_attr( $vehicle->media[0]->alt ? $vehicle->media[0]->alt : $vehicle->title ); ?>" width="1200" height="800" fetchpriority="high" decoding="async">
							<?php if ( count( $vehicle->media ) > 1 ) : ?>
								<button class="amc-gallery__nav amc-gallery__nav--prev" type="button" data-amc-gallery-prev aria-label="<?php esc_attr_e( 'Previous photo', 'automotive-marketplace-core' ); ?>"><?php echo amc_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<button class="amc-gallery__nav amc-gallery__nav--next" type="button" data-amc-gallery-next aria-label="<?php esc_attr_e( 'Next photo', 'automotive-marketplace-core' ); ?>"><?php echo amc_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<span class="amc-gallery__count" aria-hidden="true"><span data-amc-gallery-index>1</span> / <?php echo esc_html( count( $vehicle->media ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( count( $vehicle->media ) > 1 ) : ?>
							<ul class="amc-gallery__thumbs">
								<?php foreach ( $vehicle->media as $index => $media ) : ?>
									<li>
										<a href="<?php echo esc_url( $media->url ); ?>" data-amc-gallery-thumb data-alt="<?php echo esc_attr( $media->alt ? $media->alt : $vehicle->title ); ?>"<?php echo 0 === $index ? ' class="is-active" aria-current="true"' : ''; ?>>
											<?php /* translators: %d: photo number */ ?>
											<img src="<?php echo esc_url( $media->url ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Photo %d', 'automotive-marketplace-core' ), $index + 1 ) ); ?>" width="150" height="100" loading="lazy" decoding="async">
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					<?php else : ?>
						<div class="amc-gallery__stage amc-gallery__stage--empty"><?php echo amc_icon( 'car', 56 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p><?php esc_html_e( 'No photo available', 'automotive-marketplace-core' ); ?></p></div>
					<?php endif; ?>
				</section>

				<section class="amc-block" aria-labelledby="amc-specs">
					<h2 id="amc-specs"><?php esc_html_e( 'Specifications', 'automotive-marketplace-core' ); ?></h2>
					<dl class="amc-specs">
						<?php foreach ( $specs as $label => $value ) : ?>
							<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				</section>

				<?php if ( $vehicle->description ) : ?>
					<section class="amc-block amc-prose" aria-labelledby="amc-description">
						<h2 id="amc-description"><?php esc_html_e( 'Description', 'automotive-marketplace-core' ); ?></h2>
						<?php echo wp_kses_post( wpautop( esc_html( $vehicle->description ) ) ); ?>
					</section>
				<?php endif; ?>

				<?php if ( $vehicle->features ) : ?>
					<section class="amc-block" aria-labelledby="amc-features">
						<h2 id="amc-features"><?php esc_html_e( 'Features', 'automotive-marketplace-core' ); ?></h2>
						<ul class="amc-features">
							<?php foreach ( $vehicle->features as $feature ) : ?>
								<li><?php echo amc_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
			</div>

			<aside class="amc-vehicle__aside">
				<div class="amc-summary">
					<p class="amc-summary__badges">
						<span class="amds-badge amds-badge--<?php echo 'rent' === $vehicle->listing_type ? 'info' : 'dark'; ?>"><?php echo esc_html( amc_label( 'listing_types', $vehicle->listing_type ) ); ?></span>
						<span class="amds-badge amds-badge--<?php echo 'active' === $vehicle->status ? 'success' : 'warning'; ?>"><?php echo esc_html( amc_label( 'statuses', $vehicle->status ) ); ?></span>
						<?php if ( $vehicle->is_featured ) : ?>
							<span class="amds-badge amds-badge--accent"><?php esc_html_e( 'Featured', 'automotive-marketplace-core' ); ?></span>
						<?php endif; ?>
						<?php if ( $vehicle->is_demo ) : ?>
							<span class="amds-badge amds-badge--neutral"><?php esc_html_e( 'Demo', 'automotive-marketplace-core' ); ?></span>
						<?php endif; ?>
					</p>
					<h1 class="amc-summary__title"><?php echo esc_html( $vehicle->title ); ?></h1>
					<p class="amc-summary__price"><?php echo esc_html( amc_vehicle_price( $vehicle ) ); ?></p>
					<ul class="amc-summary__facts">
						<?php if ( $vehicle->year ) : ?><li><?php echo amc_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $vehicle->year ); ?></li><?php endif; ?>
						<?php if ( $vehicle->mileage_km ) : ?><li><?php echo amc_icon( 'gauge', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( amc_format_mileage( $vehicle->mileage_km ) ); ?></li><?php endif; ?>
						<?php if ( $vehicle->city ) : ?><li><?php echo amc_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $vehicle->city ); ?></li><?php endif; ?>
					</ul>

					<?php if ( $dealer && $available ) : ?>
						<div class="amc-summary__actions">
							<?php if ( $whatsapp ) : ?>
								<a class="amds-btn amds-btn--whatsapp amds-btn--lg amds-btn--block" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener nofollow"><?php echo amc_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Contact on WhatsApp', 'automotive-marketplace-core' ); ?></a>
							<?php endif; ?>
							<?php if ( $dealer->phone ) : ?>
								<a class="amds-btn amds-btn--secondary amds-btn--lg amds-btn--block" href="<?php echo esc_url( 'tel:' . $dealer->phone ); ?>"><?php echo amc_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $dealer->phone ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $dealer ) : ?>
					<section class="amc-seller" aria-labelledby="amc-seller-title">
						<h2 id="amc-seller-title" class="amc-seller__heading"><?php esc_html_e( 'Sold by', 'automotive-marketplace-core' ); ?></h2>
						<?php amc_dealer_card( $dealer ); ?>
						<div class="amc-seller__links">
							<a class="amds-link" href="<?php echo esc_url( amc_dealer_url( $dealer->slug ) ); ?>"><?php esc_html_e( 'See all vehicles from this dealer', 'automotive-marketplace-core' ); ?><?php echo amc_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							<?php $site_link = $vehicle->source_url ? $vehicle->source_url : $dealer->website_url; ?>
							<?php if ( $site_link ) : ?>
								<a class="amds-link" href="<?php echo esc_url( $site_link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vehicle->source_url ? __( 'View on the dealer website', 'automotive-marketplace-core' ) : __( 'Visit website', 'automotive-marketplace-core' ) ); ?><?php echo amc_icon( 'external', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>

				<p class="amc-vehicle__notice"><?php esc_html_e( 'The sale or rental is concluded directly with the dealer. Never send money before seeing the vehicle.', 'automotive-marketplace-core' ); ?></p>
			</aside>
		</div>

		<?php if ( $similar ) : ?>
			<section class="amc-block" id="amc-similar" aria-labelledby="amc-similar-title">
				<h2 id="amc-similar-title"><?php esc_html_e( 'Similar vehicles', 'automotive-marketplace-core' ); ?></h2>
				<div class="amc-grid amc-grid--4">
					<?php
					foreach ( $similar as $item ) {
						amc_vehicle_card( $item );
					}
					?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
