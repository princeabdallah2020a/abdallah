<?php
/**
 * Catalog: all vehicles, for sale, for rent, featured and search results.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$preset  = AMC\Marketplace::context( 'preset' );
$filters = AMC\Marketplace::context( 'filters' );
$results = AMC\Marketplace::context( 'results' );
$meta    = AMC\Seo::meta();
$tabs    = array(
	'all'      => __( 'All vehicles', 'automotive-marketplace-core' ),
	'sale'     => __( 'For sale', 'automotive-marketplace-core' ),
	'rent'     => __( 'For rent', 'automotive-marketplace-core' ),
	'featured' => __( 'Featured', 'automotive-marketplace-core' ),
);

get_header();
?>
<main id="main" class="amc-main">
	<header class="amc-page-header">
		<div class="amds-container">
			<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automotive-marketplace-core' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automotive-marketplace-core' ); ?></a>
				<span aria-current="page"><?php echo esc_html( $tabs[ $preset ] ); ?></span>
			</nav>
			<h1 class="amc-page-header__title"><?php echo esc_html( preg_replace( '/ – .*$/u', '', $meta['title'] ) ); ?></h1>
			<nav class="amds-tabs" aria-label="<?php esc_attr_e( 'Catalog sections', 'automotive-marketplace-core' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="amds-tabs__item<?php echo $key === $preset ? ' is-active' : ''; ?>" href="<?php echo esc_url( amc_catalog_url( $key ) ); ?>"<?php echo $key === $preset ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</header>

	<div class="amds-container amc-catalog">
		<aside class="amc-catalog__sidebar" id="amc-filters-panel">
			<details class="amc-catalog__toggle" open>
				<summary><?php echo amc_icon( 'filter', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Filters', 'automotive-marketplace-core' ); ?></summary>
				<?php
				amc_search_form(
					array(
						'layout'  => 'sidebar',
						'filters' => $filters,
						'preset'  => $preset,
					)
				);
				?>
			</details>
		</aside>
		<section class="amc-results" data-amc-results aria-live="polite" aria-busy="false" aria-label="<?php esc_attr_e( 'Search results', 'automotive-marketplace-core' ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output, escaped inside.
			echo AMC\Marketplace::render_results( $results, $filters, amc_catalog_url( $preset ), amc_filters_to_query( $filters, $preset ) );
			?>
		</section>
	</div>
</main>
<?php
get_footer();
