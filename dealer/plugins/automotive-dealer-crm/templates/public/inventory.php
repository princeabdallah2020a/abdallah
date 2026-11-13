<?php
/**
 * Public inventory (/inventory/) and rentals (/rentals/).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;

$data    = ADC\Router::$data;
$f       = $data['filters'];
$rentals = 'rentals' === ADC\Router::view();
$base    = adc_inventory_url( array(), $rentals );
$b       = adc_business();
$value   = static function ( $key ) use ( $f ) {
	return isset( $f[ $key ] ) ? (string) $f[ $key ] : '';
};
$select  = static function ( $name, $label, array $options, $current ) {
	echo '<div class="amds-field"><label class="amds-label" for="adc-i-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><select class="amds-select" id="adc-i-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'All', 'automotive-dealer-crm' ) . '</option>';
	foreach ( $options as $key => $text ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, (string) $key, false ) . '>' . esc_html( $text ) . '</option>';
	}
	echo '</select></div>';
};
$brands = ADC\Vehicles::public_brands();

get_header();
?>
<main id="main" class="adc-main">
	<header class="adc-page-head">
		<div class="amds-container">
			<p class="adc-eyebrow"><?php echo esc_html( $b->name ); ?></p>
			<h1 class="adc-page-head__title"><?php echo esc_html( $rentals ? __( 'Vehicles for rent', 'automotive-dealer-crm' ) : __( 'Our vehicles', 'automotive-dealer-crm' ) ); ?></h1>
			<p class="adc-page-head__lead">
				<?php
				/* translators: %d: number of vehicles */
				echo esc_html( sprintf( _n( '%d vehicle available', '%d vehicles available', $data['total'], 'automotive-dealer-crm' ), $data['total'] ) );
				?>
			</p>
		</div>
	</header>
	<div class="amds-container adc-inventory">
		<form class="adc-inventory__filters" method="get" action="<?php echo esc_url( $base ); ?>" data-adc-autosubmit>
			<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="adc_path" value="<?php echo esc_attr( $rentals ? 'rentals' : 'inventory' ); ?>"><?php endif; ?>
			<div class="amds-field"><label class="amds-label" for="adc-i-q"><?php esc_html_e( 'Search', 'automotive-dealer-crm' ); ?></label><input class="amds-input" type="search" id="adc-i-q" name="q" value="<?php echo esc_attr( $value( 'q' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Corolla', 'automotive-dealer-crm' ); ?>"></div>
			<?php
			if ( ! $rentals ) {
				$select( 'type', __( 'Offer', 'automotive-dealer-crm' ), Labels::listing_types(), $value( 'type' ) );
			}
			$select( 'brand', __( 'Brand', 'automotive-dealer-crm' ), array_combine( $brands, $brands ), $value( 'brand' ) );
			$select( 'body', __( 'Body type', 'automotive-dealer-crm' ), Labels::body_types(), $value( 'body' ) );
			$select( 'fuel', __( 'Fuel', 'automotive-dealer-crm' ), Labels::fuel_types(), $value( 'fuel' ) );
			$select( 'transmission', __( 'Gearbox', 'automotive-dealer-crm' ), Labels::transmissions(), $value( 'transmission' ) );
			?>
			<div class="amds-field"><label class="amds-label" for="adc-i-price"><?php esc_html_e( 'Maximum price', 'automotive-dealer-crm' ); ?></label><input class="amds-input" type="number" min="0" id="adc-i-price" name="price_max" value="<?php echo esc_attr( $value( 'price_max' ) ); ?>"></div>
			<?php $select( 'sort', __( 'Sort by', 'automotive-dealer-crm' ), array( 'latest' => __( 'Latest', 'automotive-dealer-crm' ), 'price_asc' => __( 'Lowest price', 'automotive-dealer-crm' ), 'price_desc' => __( 'Highest price', 'automotive-dealer-crm' ), 'mileage_asc' => __( 'Lowest mileage', 'automotive-dealer-crm' ) ), $value( 'sort' ) ); ?>
			<div class="adc-inventory__actions">
				<button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Show', 'automotive-dealer-crm' ); ?></button>
				<a class="amds-btn amds-btn--ghost" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Reset', 'automotive-dealer-crm' ); ?></a>
			</div>
		</form>
		<section class="adc-inventory__results" aria-live="polite">
			<?php if ( ! $data['items'] ) : ?>
				<div class="adc-empty">
					<?php echo adc_icon( 'search', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h2><?php esc_html_e( 'No vehicle matches your search', 'automotive-dealer-crm' ); ?></h2>
					<p><?php esc_html_e( 'Tell us what you are looking for: we often have new arrivals.', 'automotive-dealer-crm' ); ?></p>
					<a class="amds-btn amds-btn--primary" href="<?php echo esc_url( adc_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'Contact us', 'automotive-dealer-crm' ); ?></a>
				</div>
			<?php else : ?>
				<div class="adc-grid">
					<?php foreach ( $data['items'] as $i => $vehicle ) : ?>
						<?php adc_template( 'public/parts/vehicle-card', array( 'vehicle' => $vehicle, 'eager' => $i < 3 ) ); ?>
					<?php endforeach; ?>
				</div>
				<?php if ( $data['pages'] > 1 ) : ?>
					<nav class="amds-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'automotive-dealer-crm' ); ?>">
						<?php for ( $p = 1; $p <= $data['pages']; $p++ ) : ?>
							<?php $query = array_merge( array_map( 'strval', $f ), $p > 1 ? array( 'pg' => (string) $p ) : array() ); unset( $query['type'] ); if ( ! $rentals && isset( $f['type'] ) ) { $query['type'] = $f['type']; } ?>
							<?php if ( $p === $data['page'] ) : ?>
								<span class="amds-pagination__link is-current" aria-current="page"><?php echo esc_html( $p ); ?></span>
							<?php else : ?>
								<a class="amds-pagination__link" href="<?php echo esc_url( $query ? add_query_arg( array_map( 'rawurlencode', $query ), $base ) : $base ); ?>"><?php echo esc_html( $p ); ?></a>
							<?php endif; ?>
						<?php endfor; ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>
	</div>
</main>
<?php
get_footer();
