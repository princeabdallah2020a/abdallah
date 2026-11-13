<?php
/**
 * Dealer public profile. Only public profile fields are available here:
 * nothing from the dealer's CRM is ever loaded.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$dealer   = AMC\Marketplace::context( 'dealer' );
$filters  = AMC\Marketplace::context( 'filters' );
$results  = AMC\Marketplace::context( 'results' );
$featured = amc_get_vehicles(
	array(
		'dealer'   => $dealer->slug,
		'featured' => 1,
	),
	4
);
$whatsapp = amc_whatsapp_url(
	$dealer->whatsapp,
	/* translators: %s: site name */
	sprintf( __( 'Hello, I found your dealership on %s.', 'automotive-marketplace-core' ), get_bloginfo( 'name' ) )
);

get_header();
?>
<main id="main" class="amc-main">
	<header class="amc-page-header">
		<div class="amds-container">
			<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automotive-marketplace-core' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automotive-marketplace-core' ); ?></a>
				<a href="<?php echo esc_url( amc_dealers_url() ); ?>"><?php esc_html_e( 'Dealers', 'automotive-marketplace-core' ); ?></a>
				<span aria-current="page"><?php echo esc_html( $dealer->business_name ); ?></span>
			</nav>
			<div class="amc-dealer-hero">
				<div class="amc-dealer-hero__logo">
					<?php if ( $dealer->logo_url ) : ?>
						<img src="<?php echo esc_url( $dealer->logo_url ); ?>" alt="<?php echo esc_attr( $dealer->business_name ); ?>" width="96" height="96">
					<?php else : ?>
						<span><?php echo esc_html( amc_initials( $dealer->business_name ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="amc-dealer-hero__body">
					<h1 class="amc-page-header__title"><?php echo esc_html( $dealer->business_name ); ?></h1>
					<p class="amc-dealer-hero__badges">
						<?php if ( $dealer->is_verified ) : ?>
							<span class="amds-badge amds-badge--success"><?php echo amc_icon( 'shield', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Verified dealer', 'automotive-marketplace-core' ); ?></span>
						<?php else : ?>
							<span class="amds-badge amds-badge--neutral"><?php esc_html_e( 'Verification in progress', 'automotive-marketplace-core' ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $dealer->is_demo ) ) : ?>
							<span class="amds-badge amds-badge--neutral"><?php esc_html_e( 'Demo', 'automotive-marketplace-core' ); ?></span>
						<?php endif; ?>
					</p>
					<?php if ( $dealer->address || $dealer->city ) : ?>
						<p class="amc-dealer-hero__address"><?php echo amc_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( implode( ', ', array_filter( array( $dealer->address, $dealer->city ) ) ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="amc-dealer-hero__actions">
					<?php if ( $whatsapp ) : ?>
						<a class="amds-btn amds-btn--whatsapp" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener nofollow"><?php echo amc_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>WhatsApp</a>
					<?php endif; ?>
					<?php if ( $dealer->phone ) : ?>
						<a class="amds-btn amds-btn--secondary" href="<?php echo esc_url( 'tel:' . $dealer->phone ); ?>"><?php echo amc_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $dealer->phone ); ?></a>
					<?php endif; ?>
					<?php if ( $dealer->website_url ) : ?>
						<a class="amds-btn amds-btn--ghost" href="<?php echo esc_url( $dealer->website_url ); ?>" target="_blank" rel="noopener"><?php echo amc_icon( 'external', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Visit website', 'automotive-marketplace-core' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</header>

	<div class="amds-container amc-section">
		<?php if ( $dealer->description ) : ?>
			<section class="amc-prose" aria-labelledby="amc-dealer-about">
				<h2 id="amc-dealer-about"><?php esc_html_e( 'About this dealership', 'automotive-marketplace-core' ); ?></h2>
				<?php echo wp_kses_post( wpautop( esc_html( $dealer->description ) ) ); ?>
			</section>
		<?php endif; ?>

		<?php if ( $featured ) : ?>
			<section class="amc-block" aria-labelledby="amc-dealer-featured">
				<h2 id="amc-dealer-featured"><?php esc_html_e( 'Featured vehicles', 'automotive-marketplace-core' ); ?></h2>
				<div class="amc-grid amc-grid--4">
					<?php
					foreach ( $featured as $vehicle ) {
						amc_vehicle_card( $vehicle );
					}
					?>
				</div>
			</section>
		<?php endif; ?>

		<section class="amc-block" aria-labelledby="amc-dealer-stock">
			<h2 id="amc-dealer-stock"><?php esc_html_e( 'Vehicles in stock', 'automotive-marketplace-core' ); ?></h2>
			<form hidden data-amc-filters data-preset="all" data-dealer-page="<?php echo esc_attr( $dealer->slug ); ?>"><input type="hidden" name="sort" value="<?php echo esc_attr( isset( $filters['sort'] ) ? $filters['sort'] : '' ); ?>"></form>
			<div class="amc-results" data-amc-results aria-live="polite" aria-busy="false">
				<?php
				$query = $filters;
				unset( $query['dealer'] );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output, escaped inside.
				echo AMC\Marketplace::render_results( $results, $filters, amc_dealer_url( $dealer->slug ), $query );
				?>
			</div>
		</section>
	</div>
</main>
<?php
get_footer();
