<?php
/**
 * Vehicle card.
 *
 * @var array $args { vehicle: object, heading: string, eager: bool }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$vehicle = $args['vehicle'];
$heading = $args['heading'];
$url     = amc_vehicle_url( $vehicle );
?>
<article class="amc-card">
	<a class="amc-card__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $vehicle->thumbnail_url ) : ?>
			<img src="<?php echo esc_url( $vehicle->thumbnail_url ); ?>" alt="" width="600" height="400" loading="<?php echo $args['eager'] ? 'eager' : 'lazy'; ?>" decoding="async">
		<?php else : ?>
			<span class="amc-card__placeholder"><?php echo amc_icon( 'car', 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>
		<span class="amc-card__badges">
			<span class="amds-badge amds-badge--<?php echo 'rent' === $vehicle->listing_type ? 'info' : 'dark'; ?>"><?php echo esc_html( amc_label( 'listing_types', $vehicle->listing_type ) ); ?></span>
			<?php if ( $vehicle->is_featured ) : ?>
				<span class="amds-badge amds-badge--accent"><?php esc_html_e( 'Featured', 'automotive-marketplace-core' ); ?></span>
			<?php endif; ?>
			<?php if ( 'reserved' === $vehicle->status ) : ?>
				<span class="amds-badge amds-badge--warning"><?php esc_html_e( 'Reserved', 'automotive-marketplace-core' ); ?></span>
			<?php endif; ?>
			<?php if ( $vehicle->is_demo ) : ?>
				<span class="amds-badge amds-badge--neutral"><?php esc_html_e( 'Demo', 'automotive-marketplace-core' ); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<div class="amc-card__body">
		<<?php echo esc_html( $heading ); ?> class="amc-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $vehicle->title ); ?></a></<?php echo esc_html( $heading ); ?>>
		<p class="amc-card__price"><?php echo esc_html( amc_vehicle_price( $vehicle ) ); ?></p>
		<ul class="amc-card__specs">
			<?php if ( $vehicle->year ) : ?>
				<li><?php echo amc_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Year', 'automotive-marketplace-core' ); ?>: </span><?php echo esc_html( $vehicle->year ); ?></li>
			<?php endif; ?>
			<?php if ( $vehicle->mileage_km ) : ?>
				<li><?php echo amc_icon( 'gauge', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Mileage', 'automotive-marketplace-core' ); ?>: </span><?php echo esc_html( amc_format_mileage( $vehicle->mileage_km ) ); ?></li>
			<?php endif; ?>
			<?php if ( $vehicle->fuel_type ) : ?>
				<li><?php echo amc_icon( 'fuel', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Fuel', 'automotive-marketplace-core' ); ?>: </span><?php echo esc_html( amc_label( 'fuel_types', $vehicle->fuel_type ) ); ?></li>
			<?php endif; ?>
			<?php if ( $vehicle->transmission ) : ?>
				<li><?php echo amc_icon( 'gearbox', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Transmission', 'automotive-marketplace-core' ); ?>: </span><?php echo esc_html( amc_label( 'transmissions', $vehicle->transmission ) ); ?></li>
			<?php endif; ?>
		</ul>
		<p class="amc-card__meta">
			<span><?php echo amc_icon( 'pin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $vehicle->city ); ?></span>
			<span class="amc-card__dealer">
				<?php echo esc_html( $vehicle->dealer_name ); ?>
				<?php if ( $vehicle->dealer_verified ) : ?>
					<span class="amc-verified" title="<?php esc_attr_e( 'Verified dealer', 'automotive-marketplace-core' ); ?>"><?php echo amc_icon( 'shield', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'Verified dealer', 'automotive-marketplace-core' ); ?></span></span>
				<?php endif; ?>
			</span>
		</p>
	</div>
</article>
