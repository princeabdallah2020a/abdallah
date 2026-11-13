<?php
/**
 * Dealers directory.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$dealers = AMC\Marketplace::context( 'dealers' );
$pages   = (int) AMC\Marketplace::context( 'pages' );
$page    = (int) AMC\Marketplace::context( 'page' );
$meta    = AMC\Seo::meta();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only search.
$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

get_header();
?>
<main id="main" class="amc-main">
	<header class="amc-page-header">
		<div class="amds-container">
			<nav class="amds-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'automotive-marketplace-core' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'automotive-marketplace-core' ); ?></a>
				<span aria-current="page"><?php esc_html_e( 'Dealers', 'automotive-marketplace-core' ); ?></span>
			</nav>
			<h1 class="amc-page-header__title"><?php echo esc_html( preg_replace( '/ – .*$/u', '', $meta['title'] ) ); ?></h1>
			<p class="amc-page-header__lead"><?php echo esc_html( $meta['description'] ); ?></p>
			<form class="amc-inline-search" method="get" action="<?php echo esc_url( amc_dealers_url() ); ?>" role="search">
				<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
					<input type="hidden" name="amc_view" value="dealers">
				<?php endif; ?>
				<label class="screen-reader-text" for="amc-dealer-q"><?php esc_html_e( 'Search a dealer by name', 'automotive-marketplace-core' ); ?></label>
				<input class="amds-input" type="search" id="amc-dealer-q" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Search a dealer by name', 'automotive-marketplace-core' ); ?>">
				<button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Search', 'automotive-marketplace-core' ); ?></button>
			</form>
		</div>
	</header>

	<div class="amds-container amc-section">
		<?php if ( ! $dealers['items'] ) : ?>
			<div class="amds-empty">
				<?php echo amc_icon( 'store', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h2 class="amds-empty__title"><?php esc_html_e( 'No dealer found', 'automotive-marketplace-core' ); ?></h2>
				<a class="amds-btn amds-btn--secondary" href="<?php echo esc_url( amc_dealers_url() ); ?>"><?php esc_html_e( 'See all dealers', 'automotive-marketplace-core' ); ?></a>
			</div>
		<?php else : ?>
			<div class="amc-dealer-grid">
				<?php
				foreach ( $dealers['items'] as $dealer ) {
					amc_dealer_card( $dealer );
				}
				?>
			</div>
			<?php if ( $pages > 1 ) : ?>
				<nav class="amds-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'automotive-marketplace-core' ); ?>">
					<?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
						<?php $url = add_query_arg( array_filter( array( 'q' => rawurlencode( $q ), 'pg' => $p > 1 ? $p : null ) ), amc_dealers_url() ); ?>
						<?php if ( $p === $page ) : ?>
							<span class="amds-pagination__link is-current" aria-current="page"><?php echo esc_html( $p ); ?></span>
						<?php else : ?>
							<a class="amds-pagination__link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $p ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
