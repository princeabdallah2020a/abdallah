<?php
/**
 * Homepage: company website + marketplace entry point.
 *
 * @package AutoMarket
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! automarket_has_marketplace() ) :
	?>
	<main id="main" class="am-page"><div class="amds-container"><div class="amds-alert amds-alert--warning"><?php esc_html_e( 'The AutoMarket theme requires the "Automotive Marketplace Core" plugin. Please install and activate it.', 'automarket' ); ?></div></div></main>
	<?php
	get_footer();
	return;
endif;

$hero_image   = automarket_option( 'hero_image' );
$total        = AMC\Vehicles::search( array(), 1, 1 )['total'];
$dealers      = amc_get_dealers( 6 );
$dealer_total = AMC\Dealers::search( array( 'limit' => 1 ) )['total'];
$featured     = amc_get_vehicles(
	array(
		'featured' => 1,
		'sort'     => 'featured',
	),
	4
);
$for_sale     = amc_get_vehicles( array( 'type' => 'sale' ), 4 );
$for_rent     = amc_get_vehicles( array( 'type' => 'rent' ), 4 );
// "Recently added" skips vehicles already shown in the sections above.
$shown        = wp_list_pluck( array_merge( $featured, $for_sale, $for_rent ), 'id' );
$recent       = array_slice(
	array_filter(
		amc_get_vehicles( array(), 24 ),
		static function ( $vehicle ) use ( $shown ) {
			return ! in_array( $vehicle->id, $shown, true );
		}
	),
	0,
	8
);
$testimonials = AMC\Content::testimonials( 3 );
$join_url     = automarket_page_url( 'join-dealer' );
$city         = AMC\Settings::get( 'default_city' );
?>
<main id="main">

	<section class="am-hero<?php echo $hero_image ? ' am-hero--photo' : ''; ?>"<?php echo $hero_image ? ' style="background-image:linear-gradient(90deg,rgba(14,23,38,.92) 0%,rgba(14,23,38,.55) 100%),url(' . esc_url( $hero_image ) . ')"' : ''; ?>>
		<div class="amds-container am-hero__inner">
			<p class="am-eyebrow am-eyebrow--on-dark">
				<?php
				/* translators: %s: city */
				echo esc_html( sprintf( __( 'Automotive marketplace · %s', 'automarket' ), $city ) );
				?>
			</p>
			<h1 class="am-hero__title"><?php echo esc_html( automarket_option( 'hero_title' ) ); ?></h1>
			<p class="am-hero__lead"><?php echo esc_html( automarket_option( 'hero_subtitle' ) ); ?></p>

			<?php amc_search_form( array( 'layout' => 'hero' ) ); ?>

			<?php if ( $total || $dealer_total ) : ?>
				<ul class="am-hero__stats">
					<li><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( 'vehicles online', 'automarket' ); ?></li>
					<li><strong><?php echo esc_html( number_format_i18n( $dealer_total ) ); ?></strong> <?php esc_html_e( 'dealerships', 'automarket' ); ?></li>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( ! $total ) : ?>
		<section class="am-section">
			<div class="amds-container">
				<div class="amds-empty">
					<?php echo automarket_icon( 'car', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h2 class="amds-empty__title"><?php esc_html_e( 'The first vehicles are coming soon', 'automarket' ); ?></h2>
					<p><?php esc_html_e( 'Dealerships are joining the platform. Come back shortly.', 'automarket' ); ?></p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $featured ) : ?>
		<section class="am-section" aria-label="<?php esc_attr_e( 'Featured vehicles', 'automarket' ); ?>">
			<div class="amds-container">
				<?php automarket_section_head( __( 'Featured vehicles', 'automarket' ), amc_catalog_url( 'featured' ), __( 'See all featured', 'automarket' ) ); ?>
				<div class="amc-grid amc-grid--4">
					<?php
					foreach ( $featured as $vehicle ) {
						amc_vehicle_card( $vehicle );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $for_sale ) : ?>
		<section class="am-section am-section--subtle" aria-label="<?php esc_attr_e( 'Cars for sale', 'automarket' ); ?>">
			<div class="amds-container">
				<?php automarket_section_head( __( 'Cars for sale', 'automarket' ), amc_catalog_url( 'sale' ), __( 'All cars for sale', 'automarket' ), __( 'New, imported and locally used vehicles from professional dealerships.', 'automarket' ) ); ?>
				<div class="amc-grid amc-grid--4">
					<?php
					foreach ( $for_sale as $vehicle ) {
						amc_vehicle_card( $vehicle );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $for_rent ) : ?>
		<section class="am-section" aria-label="<?php esc_attr_e( 'Cars for rent', 'automarket' ); ?>">
			<div class="amds-container">
				<?php automarket_section_head( __( 'Cars for rent', 'automarket' ), amc_catalog_url( 'rent' ), __( 'All cars for rent', 'automarket' ), __( 'By the day or for longer, with or without a driver.', 'automarket' ) ); ?>
				<div class="amc-grid amc-grid--4">
					<?php
					foreach ( $for_rent as $vehicle ) {
						amc_vehicle_card( $vehicle );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $dealers ) : ?>
		<section class="am-section am-section--subtle" aria-label="<?php esc_attr_e( 'Dealerships', 'automarket' ); ?>">
			<div class="amds-container">
				<?php automarket_section_head( __( 'Verified dealerships', 'automarket' ), amc_dealers_url(), __( 'All dealerships', 'automarket' ), __( 'Professionals whose business information has been checked by our team.', 'automarket' ) ); ?>
				<div class="amc-dealer-grid">
					<?php
					foreach ( $dealers as $dealer ) {
						amc_dealer_card( $dealer );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $recent ) : ?>
		<section class="am-section" aria-label="<?php esc_attr_e( 'Recently added', 'automarket' ); ?>">
			<div class="amds-container">
				<?php automarket_section_head( __( 'Recently added', 'automarket' ), amc_catalog_url(), __( 'Browse all vehicles', 'automarket' ) ); ?>
				<div class="amc-grid amc-grid--4">
					<?php
					foreach ( $recent as $vehicle ) {
						amc_vehicle_card( $vehicle );
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="am-section am-section--subtle" aria-label="<?php esc_attr_e( 'Why use our marketplace', 'automarket' ); ?>">
		<div class="amds-container">
			<?php automarket_section_head( __( 'Why use our marketplace', 'automarket' ) ); ?>
			<div class="am-features">
				<?php
				$why = array(
					array( 'shield', __( 'Verified dealerships', 'automarket' ), __( 'We check the business information of dealerships before they are marked as verified.', 'automarket' ) ),
					array( 'sync', __( 'Up-to-date listings', 'automarket' ), __( 'Listings come straight from each dealership\'s stock and are updated automatically.', 'automarket' ) ),
					array( 'search', __( 'Precise search', 'automarket' ), __( 'Filter by brand, model, price, year, mileage, fuel, gearbox and city.', 'automarket' ) ),
					array( 'chat', __( 'Direct contact', 'automarket' ), __( 'Reach the dealership by phone or WhatsApp in one tap, without intermediaries.', 'automarket' ) ),
				);
				foreach ( $why as $item ) :
					?>
					<div class="am-feature">
						<span class="am-feature__icon"><?php echo automarket_icon( $item[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h3 class="am-feature__title"><?php echo esc_html( $item[1] ); ?></h3>
						<p><?php echo esc_html( $item[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="am-band" aria-labelledby="am-band-title">
		<div class="amds-container am-band__inner">
			<div>
				<p class="am-eyebrow am-eyebrow--on-dark"><?php esc_html_e( 'For dealerships', 'automarket' ); ?></p>
				<h2 id="am-band-title" class="am-band__title"><?php esc_html_e( 'Your own website, your CRM and your vehicles on the marketplace', 'automarket' ); ?></h2>
				<p class="am-band__lead"><?php esc_html_e( 'One membership gives your dealership an independent website on your own domain, a management tool built for car sales and rentals, and automatic publication on the marketplace.', 'automarket' ); ?></p>
				<?php if ( $join_url ) : ?>
					<a class="amds-btn amds-btn--accent amds-btn--lg" href="<?php echo esc_url( $join_url ); ?>"><?php esc_html_e( 'Become a dealer', 'automarket' ); ?></a>
				<?php endif; ?>
			</div>
			<ul class="am-band__list">
				<?php
				$points = array(
					array( 'globe', __( 'Independent website', 'automarket' ), __( 'Your domain, your brand, your data.', 'automarket' ) ),
					array( 'chart', __( 'Dealer CRM', 'automarket' ), __( 'Stock, leads, customers, sales and rentals.', 'automarket' ) ),
					array( 'store', __( 'Marketplace listings', 'automarket' ), __( 'Published automatically from your stock.', 'automarket' ) ),
					array( 'users', __( 'Team access', 'automarket' ), __( 'Roles and permissions for your staff.', 'automarket' ) ),
				);
				foreach ( $points as $point ) :
					?>
					<li>
						<span class="am-band__icon"><?php echo automarket_icon( $point[0], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><strong><?php echo esc_html( $point[1] ); ?></strong><br><?php echo esc_html( $point[2] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<?php if ( $testimonials ) : ?>
		<section class="am-section" aria-label="<?php esc_attr_e( 'Testimonials', 'automarket' ); ?>">
			<div class="amds-container">
				<?php automarket_section_head( __( 'What they say', 'automarket' ) ); ?>
				<div class="am-quotes">
					<?php foreach ( $testimonials as $testimonial ) : ?>
						<figure class="am-quote">
							<blockquote><p><?php echo esc_html( $testimonial['quote'] ); ?></p></blockquote>
							<figcaption><strong><?php echo esc_html( $testimonial['name'] ); ?></strong><?php echo $testimonial['role'] ? '<br>' . esc_html( $testimonial['role'] ) : ''; ?></figcaption>
						</figure>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="am-section am-cta" aria-labelledby="am-cta-title">
		<div class="amds-container am-cta__inner">
			<h2 id="am-cta-title"><?php esc_html_e( 'Do you run a dealership or a rental agency?', 'automarket' ); ?></h2>
			<p><?php esc_html_e( 'Tell us about your business: our team will show you the plans and set everything up with you.', 'automarket' ); ?></p>
			<div class="am-cta__actions">
				<?php if ( $join_url ) : ?>
					<a class="amds-btn amds-btn--primary amds-btn--lg" href="<?php echo esc_url( $join_url ); ?>"><?php esc_html_e( 'Request my dealer account', 'automarket' ); ?></a>
				<?php endif; ?>
				<?php if ( automarket_page_url( 'contact' ) ) : ?>
					<a class="amds-btn amds-btn--secondary amds-btn--lg" href="<?php echo esc_url( automarket_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'Contact us', 'automarket' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
