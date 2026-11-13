<?php
/**
 * Dealer homepage. All texts, photos and colours come from the CRM settings.
 *
 * @package AutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! autodealer_has_crm() ) :
	?>
	<main id="main" class="ad-page"><div class="amds-container"><div class="amds-alert amds-alert--warning"><?php esc_html_e( 'The AutoDealer theme requires the "AutoBuzy Dealer CRM" plugin. Please install and activate it.', 'autodealer' ); ?></div></div></main>
	<?php
	get_footer();
	return;
endif;

$b        = adc_business();
$s        = ADC\Settings::all();
$hero     = $s['hero_image_id'] ? wp_get_attachment_image_url( (int) $s['hero_image_id'], 'full' ) : '';
$featured = adc_get_vehicles( array( 'featured' => 1 ), 6 );
$latest   = adc_get_vehicles( array( 'sort' => 'latest', 'type' => 'sale' ), 8 );
$rentals  = $s['show_rentals'] ? adc_get_vehicles( array( 'type' => 'rent' ), 4 ) : array();
$total    = ADC\Vehicles::search_public( array(), 1, 1 )['total'];
$brands   = ADC\Vehicles::public_brands();
$whatsapp = adc_whatsapp_url( $b->whatsapp, __( 'Hello, I am looking for a vehicle.', 'autodealer' ) );
?>
<main id="main">
	<section class="ad-hero<?php echo $hero ? ' ad-hero--photo' : ''; ?>"<?php echo $hero ? ' style="--ad-hero:url(' . esc_url( $hero ) . ')"' : ''; ?>>
		<div class="amds-container ad-hero__inner">
			<p class="ad-hero__eyebrow"><?php echo esc_html( $b->name . ( $b->city ? ' · ' . $b->city : '' ) ); ?></p>
			<h1 class="ad-hero__title"><?php echo esc_html( $s['hero_title'] ? $s['hero_title'] : __( 'Your next car is here', 'autodealer' ) ); ?></h1>
			<p class="ad-hero__lead"><?php echo esc_html( $s['hero_text'] ? $s['hero_text'] : ( $b->tagline ? $b->tagline : __( 'Vehicles for sale and for rent, with real photos and clear prices. Visit us or contact us on WhatsApp.', 'autodealer' ) ) ); ?></p>
			<form class="ad-search" method="get" action="<?php echo esc_url( adc_inventory_url() ); ?>" role="search">
				<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="adc_path" value="inventory"><?php endif; ?>
				<div class="amds-field"><label class="amds-label" for="ad-s-type"><?php esc_html_e( 'I want to', 'autodealer' ); ?></label>
					<select class="amds-select" id="ad-s-type" name="type"><option value=""><?php esc_html_e( 'Buy or rent', 'autodealer' ); ?></option><option value="sale"><?php esc_html_e( 'Buy', 'autodealer' ); ?></option><?php if ( $s['show_rentals'] ) : ?><option value="rent"><?php esc_html_e( 'Rent', 'autodealer' ); ?></option><?php endif; ?></select></div>
				<div class="amds-field"><label class="amds-label" for="ad-s-brand"><?php esc_html_e( 'Brand', 'autodealer' ); ?></label>
					<select class="amds-select" id="ad-s-brand" name="brand"><option value=""><?php esc_html_e( 'All brands', 'autodealer' ); ?></option><?php foreach ( $brands as $brand ) : ?><option value="<?php echo esc_attr( $brand ); ?>"><?php echo esc_html( $brand ); ?></option><?php endforeach; ?></select></div>
				<div class="amds-field"><label class="amds-label" for="ad-s-price"><?php esc_html_e( 'Maximum budget', 'autodealer' ); ?></label>
					<input class="amds-input" type="number" min="0" id="ad-s-price" name="price_max" placeholder="<?php echo esc_attr( ADC\Labels::currencies()[ $s['currency'] ]['symbol'] ?? '' ); ?>"></div>
				<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit"><?php echo autodealer_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Search', 'autodealer' ); ?></button>
			</form>
			<ul class="ad-hero__facts">
				<li><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( 'vehicles available', 'autodealer' ); ?></li>
				<?php if ( $brands ) : ?><li><strong><?php echo esc_html( count( $brands ) ); ?></strong> <?php esc_html_e( 'brands', 'autodealer' ); ?></li><?php endif; ?>
				<?php if ( $whatsapp ) : ?><li><a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener"><?php echo autodealer_icon( 'chat', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Write to us on WhatsApp', 'autodealer' ); ?></a></li><?php endif; ?>
			</ul>
		</div>
	</section>

	<?php if ( $featured ) : ?>
		<section class="ad-section">
			<div class="amds-container">
				<div class="ad-section__head"><div><p class="adc-eyebrow"><?php esc_html_e( 'Selection', 'autodealer' ); ?></p><h2><?php esc_html_e( 'Our favourites', 'autodealer' ); ?></h2></div><a class="amds-link" href="<?php echo esc_url( adc_inventory_url() ); ?>"><?php esc_html_e( 'See all vehicles', 'autodealer' ); ?><?php echo autodealer_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></div>
				<div class="adc-grid"><?php foreach ( $featured as $i => $vehicle ) { adc_template( 'public/parts/vehicle-card', array( 'vehicle' => $vehicle, 'eager' => $i < 3 ) ); } ?></div>
			</div>
		</section>
	<?php endif; ?>

	<section class="ad-section ad-section--subtle">
		<div class="amds-container">
			<div class="ad-section__head"><div><p class="adc-eyebrow"><?php esc_html_e( 'In stock', 'autodealer' ); ?></p><h2><?php esc_html_e( 'Latest arrivals', 'autodealer' ); ?></h2></div><a class="amds-link" href="<?php echo esc_url( adc_inventory_url( array( 'type' => 'sale' ) ) ); ?>"><?php esc_html_e( 'All vehicles for sale', 'autodealer' ); ?><?php echo autodealer_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></div>
			<?php if ( $latest ) : ?>
				<div class="adc-grid adc-grid--4"><?php foreach ( $latest as $vehicle ) { adc_template( 'public/parts/vehicle-card', array( 'vehicle' => $vehicle ) ); } ?></div>
			<?php else : ?>
				<div class="adc-empty"><?php echo autodealer_icon( 'car', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><h2><?php esc_html_e( 'New vehicles arrive soon', 'autodealer' ); ?></h2><p><?php esc_html_e( 'Tell us what you are looking for and we will find it for you.', 'autodealer' ); ?></p></div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $rentals ) : ?>
		<section class="ad-section">
			<div class="amds-container">
				<div class="ad-section__head"><div><p class="adc-eyebrow"><?php esc_html_e( 'Rental', 'autodealer' ); ?></p><h2><?php esc_html_e( 'Rent by the day', 'autodealer' ); ?></h2></div><a class="amds-link" href="<?php echo esc_url( adc_page_url( 'rentals' ) ); ?>"><?php esc_html_e( 'All rentals', 'autodealer' ); ?><?php echo autodealer_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></div>
				<div class="adc-grid adc-grid--4"><?php foreach ( $rentals as $vehicle ) { adc_template( 'public/parts/vehicle-card', array( 'vehicle' => $vehicle ) ); } ?></div>
			</div>
		</section>
	<?php endif; ?>

	<section class="ad-section ad-section--subtle">
		<div class="amds-container ad-why">
			<?php
			$why = array(
				array( 'shield', __( 'Clear listings', 'autodealer' ), __( 'Real photos, full specifications and the price displayed for every vehicle.', 'autodealer' ) ),
				array( 'chat', __( 'Direct contact', 'autodealer' ), __( 'Call us, write on WhatsApp or visit the showroom: you talk to our team.', 'autodealer' ) ),
				array( 'key', __( 'Sale and rental', 'autodealer' ), __( 'Buy or rent, by the day or for longer, with or without a driver.', 'autodealer' ) ),
				array( 'star', __( 'Personal advice', 'autodealer' ), __( 'Tell us your budget and your needs: we help you choose.', 'autodealer' ) ),
			);
			foreach ( $why as $item ) :
				?>
				<div class="ad-why__item"><span class="ad-why__icon"><?php echo autodealer_icon( $item[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><h3><?php echo esc_html( $item[1] ); ?></h3><p><?php echo esc_html( $item[2] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</section>

	<?php if ( $s['about_text'] ) : ?>
		<section class="ad-section">
			<div class="amds-container ad-about">
				<div>
					<p class="adc-eyebrow"><?php esc_html_e( 'About us', 'autodealer' ); ?></p>
					<h2><?php echo esc_html( $b->name ); ?></h2>
					<p><?php echo esc_html( wp_html_excerpt( $s['about_text'], 420, '…' ) ); ?></p>
					<a class="amds-btn amds-btn--secondary" href="<?php echo esc_url( adc_page_url( 'about' ) ); ?>"><?php esc_html_e( 'Our story', 'autodealer' ); ?></a>
				</div>
				<?php if ( $hero ) : ?><figure><?php echo wp_get_attachment_image( (int) $s['hero_image_id'], 'large', false, array( 'loading' => 'lazy' ) ); ?></figure><?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="ad-band">
		<div class="amds-container ad-band__inner">
			<div>
				<h2><?php esc_html_e( 'Come and see us', 'autodealer' ); ?></h2>
				<p><?php echo esc_html( trim( $b->address . ', ' . $b->city, ', ' ) . ( $b->hours ? ' · ' . $b->hours : '' ) ); ?></p>
			</div>
			<div class="ad-band__actions">
				<?php if ( $whatsapp ) : ?><a class="amds-btn amds-btn--whatsapp amds-btn--lg" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener"><?php echo autodealer_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>WhatsApp</a><?php endif; ?>
				<?php if ( $b->phone ) : ?><a class="amds-btn amds-btn--on-dark amds-btn--lg" href="<?php echo esc_url( 'tel:' . $b->phone ); ?>"><?php echo autodealer_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $b->phone ); ?></a><?php endif; ?>
				<?php if ( $b->map_url ) : ?><a class="amds-btn amds-btn--outline-on-dark amds-btn--lg" href="<?php echo esc_url( $b->map_url ); ?>" target="_blank" rel="noopener"><?php echo autodealer_icon( 'pin', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Directions', 'autodealer' ); ?></a><?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
