<?php
/**
 * Vehicle search form. Works as a plain GET form; catalog.js upgrades the
 * sidebar layout to AJAX filtering.
 *
 * @var array $args { layout: hero|sidebar, filters: array, preset: string, dealer_page?: string }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$layout  = $args['layout'];
$filters = $args['filters'];
$preset  = $args['preset'];
$facets  = AMC\Vehicles::facets();
$uid     = 'amc-' . $layout;
$value   = static function ( $key ) use ( $filters ) {
	return isset( $filters[ $key ] ) ? $filters[ $key ] : '';
};
$dealer_page = isset( $args['dealer_page'] ) ? $args['dealer_page'] : '';
$action      = $dealer_page ? amc_dealer_url( $dealer_page ) : amc_catalog_url( $preset );
$models      = $value( 'brand' ) && isset( $facets['brands'][ $value( 'brand' ) ] ) ? $facets['brands'][ $value( 'brand' ) ]['models'] : array();

/**
 * Print a <select>.
 */
$select = static function ( $name, $label, array $options, $current, $placeholder ) use ( $uid ) {
	$id = $uid . '-' . $name;
	echo '<div class="amds-field"><label class="amds-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	echo '<select class="amds-select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
	echo '<option value="">' . esc_html( $placeholder ) . '</option>';
	foreach ( $options as $key => $text ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( (string) $current, (string) $key, false ) . '>' . esc_html( $text ) . '</option>';
	}
	echo '</select></div>';
};

$brands = array();
foreach ( $facets['brands'] as $slug => $brand ) {
	$brands[ $slug ] = $brand['name'];
}
$years = array();
if ( $facets['years'][1] ) {
	for ( $y = $facets['years'][1]; $y >= $facets['years'][0]; $y-- ) {
		$years[ $y ] = $y;
	}
}
$mileages = array();
foreach ( array( 25000, 50000, 75000, 100000, 150000, 200000 ) as $km ) {
	/* translators: %s: mileage */
	$mileages[ $km ] = sprintf( __( 'Up to %s', 'automotive-marketplace-core' ), amc_format_mileage( $km ) );
}
$any = __( 'All', 'automotive-marketplace-core' );
?>
<form class="amc-filters amc-filters--<?php echo esc_attr( $layout ); ?>" method="get" action="<?php echo esc_url( $action ); ?>" role="search"
	aria-label="<?php esc_attr_e( 'Search vehicles', 'automotive-marketplace-core' ); ?>"
	<?php if ( 'sidebar' === $layout ) : ?>
		data-amc-filters data-preset="<?php echo esc_attr( $preset ); ?>" data-dealer-page="<?php echo esc_attr( $dealer_page ); ?>"
	<?php endif; ?>>

	<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
		<input type="hidden" name="amc_view" value="<?php echo esc_attr( $dealer_page ? 'dealer' : 'catalog' ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $dealer_page ? 'amc_slug' : 'amc_preset' ); ?>" value="<?php echo esc_attr( $dealer_page ? $dealer_page : $preset ); ?>">
	<?php endif; ?>

	<?php if ( 'hero' === $layout ) : ?>
		<?php
		$select( 'type', __( 'Sale or rental', 'automotive-marketplace-core' ), AMC\Catalog::listing_types(), $value( 'type' ), __( 'Sale and rental', 'automotive-marketplace-core' ) );
		$select( 'brand', __( 'Brand', 'automotive-marketplace-core' ), $brands, $value( 'brand' ), __( 'All brands', 'automotive-marketplace-core' ) );
		$select( 'model', __( 'Model', 'automotive-marketplace-core' ), $models, $value( 'model' ), __( 'All models', 'automotive-marketplace-core' ) );
		$select( 'city', __( 'Location', 'automotive-marketplace-core' ), $facets['cities'], $value( 'city' ), __( 'All cities', 'automotive-marketplace-core' ) );
		?>
		<div class="amds-field">
			<label class="amds-label" for="<?php echo esc_attr( $uid ); ?>-price_max"><?php esc_html_e( 'Maximum price', 'automotive-marketplace-core' ); ?></label>
			<input class="amds-input" type="number" inputmode="numeric" min="0" step="1" id="<?php echo esc_attr( $uid ); ?>-price_max" name="price_max" placeholder="<?php echo esc_attr( AMC\Catalog::currencies()[ AMC\Settings::get( 'default_currency' ) ]['symbol'] ); ?>">
		</div>
		<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit"><?php echo amc_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Search', 'automotive-marketplace-core' ); ?></button>

	<?php else : ?>
		<div class="amds-field">
			<label class="amds-label" for="<?php echo esc_attr( $uid ); ?>-q"><?php esc_html_e( 'Keyword', 'automotive-marketplace-core' ); ?></label>
			<input class="amds-input" type="search" id="<?php echo esc_attr( $uid ); ?>-q" name="q" value="<?php echo esc_attr( $value( 'q' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Toyota Corolla', 'automotive-marketplace-core' ); ?>" maxlength="80">
		</div>
		<?php
		if ( 'all' === $preset || 'featured' === $preset ) {
			$select( 'type', __( 'Sale or rental', 'automotive-marketplace-core' ), AMC\Catalog::listing_types(), $value( 'type' ), __( 'Sale and rental', 'automotive-marketplace-core' ) );
		}
		$select( 'brand', __( 'Brand', 'automotive-marketplace-core' ), $brands, $value( 'brand' ), __( 'All brands', 'automotive-marketplace-core' ) );
		$select( 'model', __( 'Model', 'automotive-marketplace-core' ), $models, $value( 'model' ), __( 'All models', 'automotive-marketplace-core' ) );
		?>
		<fieldset class="amds-fieldset">
			<legend class="amds-label"><?php esc_html_e( 'Price range', 'automotive-marketplace-core' ); ?></legend>
			<div class="amc-filters__pair">
				<input class="amds-input" type="number" inputmode="numeric" min="0" name="price_min" value="<?php echo esc_attr( $value( 'price_min' ) ); ?>" placeholder="<?php esc_attr_e( 'Min', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Minimum price', 'automotive-marketplace-core' ); ?>">
				<input class="amds-input" type="number" inputmode="numeric" min="0" name="price_max" value="<?php echo esc_attr( $value( 'price_max' ) ); ?>" placeholder="<?php esc_attr_e( 'Max', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Maximum price', 'automotive-marketplace-core' ); ?>">
			</div>
		</fieldset>
		<fieldset class="amds-fieldset">
			<legend class="amds-label"><?php esc_html_e( 'Year', 'automotive-marketplace-core' ); ?></legend>
			<div class="amc-filters__pair">
				<select class="amds-select" name="year_min" aria-label="<?php esc_attr_e( 'Minimum year', 'automotive-marketplace-core' ); ?>">
					<option value=""><?php esc_html_e( 'From', 'automotive-marketplace-core' ); ?></option>
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo esc_attr( $y ); ?>" <?php selected( (string) $value( 'year_min' ), (string) $y ); ?>><?php echo esc_html( $y ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="amds-select" name="year_max" aria-label="<?php esc_attr_e( 'Maximum year', 'automotive-marketplace-core' ); ?>">
					<option value=""><?php esc_html_e( 'To', 'automotive-marketplace-core' ); ?></option>
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo esc_attr( $y ); ?>" <?php selected( (string) $value( 'year_max' ), (string) $y ); ?>><?php echo esc_html( $y ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</fieldset>
		<?php
		$select( 'mileage_max', __( 'Mileage', 'automotive-marketplace-core' ), $mileages, $value( 'mileage_max' ), $any );
		$select( 'fuel', __( 'Fuel type', 'automotive-marketplace-core' ), AMC\Catalog::fuel_types(), $value( 'fuel' ), $any );
		$select( 'transmission', __( 'Transmission', 'automotive-marketplace-core' ), AMC\Catalog::transmissions(), $value( 'transmission' ), $any );
		$select( 'body', __( 'Body type', 'automotive-marketplace-core' ), AMC\Catalog::body_types(), $value( 'body' ), $any );
		$select( 'condition', __( 'Condition', 'automotive-marketplace-core' ), AMC\Catalog::conditions(), $value( 'condition' ), $any );
		$select( 'city', __( 'Location', 'automotive-marketplace-core' ), $facets['cities'], $value( 'city' ), __( 'All cities', 'automotive-marketplace-core' ) );
		if ( ! $dealer_page ) {
			$select( 'dealer', __( 'Dealer', 'automotive-marketplace-core' ), $facets['dealers'], $value( 'dealer' ), __( 'All dealers', 'automotive-marketplace-core' ) );
		}
		$select(
			'availability',
			__( 'Availability', 'automotive-marketplace-core' ),
			array(
				'available' => __( 'Available now', 'automotive-marketplace-core' ),
				'reserved'  => __( 'Reserved', 'automotive-marketplace-core' ),
			),
			$value( 'availability' ),
			$any
		);
		?>
		<input type="hidden" name="sort" value="<?php echo esc_attr( $value( 'sort' ) ); ?>">
		<div class="amc-filters__actions">
			<button class="amds-btn amds-btn--primary amds-btn--block" type="submit"><?php esc_html_e( 'Apply filters', 'automotive-marketplace-core' ); ?></button>
			<a class="amds-btn amds-btn--ghost amds-btn--block" href="<?php echo esc_url( $action ); ?>" data-amc-reset><?php esc_html_e( 'Reset', 'automotive-marketplace-core' ); ?></a>
		</div>
	<?php endif; ?>
</form>
