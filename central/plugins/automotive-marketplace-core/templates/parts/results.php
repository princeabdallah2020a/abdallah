<?php
/**
 * Results area: count, sorting, grid, pagination. Also returned by the
 * /catalog REST endpoint, so it must not depend on global request state.
 *
 * @var array $args { results: array, filters: array, base_url: string, query: array }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$results  = $args['results'];
$filters  = $args['filters'];
$base_url = $args['base_url'];
$sort     = isset( $filters['sort'] ) ? $filters['sort'] : 'latest';
?>
<div class="amc-results__bar">
	<p class="amc-results__count" role="status">
		<?php
		/* translators: %s: number of vehicles */
		echo esc_html( sprintf( _n( '%s vehicle', '%s vehicles', $results['total'], 'automotive-marketplace-core' ), number_format_i18n( $results['total'] ) ) );
		?>
	</p>
	<div class="amc-results__sort">
		<label class="amds-label" for="amc-sort"><?php esc_html_e( 'Sort by', 'automotive-marketplace-core' ); ?></label>
		<select class="amds-select amds-select--sm" id="amc-sort" data-amc-sort>
			<?php foreach ( AMC\Catalog::sort_options() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sort, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</div>

<?php if ( ! $results['items'] ) : ?>
	<div class="amds-empty">
		<?php echo amc_icon( 'search', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<h2 class="amds-empty__title"><?php esc_html_e( 'No vehicle matches your search', 'automotive-marketplace-core' ); ?></h2>
		<p><?php esc_html_e( 'Try removing a filter or widening the price range.', 'automotive-marketplace-core' ); ?></p>
		<a class="amds-btn amds-btn--secondary" href="<?php echo esc_url( $base_url ); ?>" data-amc-reset><?php esc_html_e( 'Reset filters', 'automotive-marketplace-core' ); ?></a>
	</div>
<?php else : ?>
	<div class="amc-grid">
		<?php
		foreach ( $results['items'] as $index => $vehicle ) {
			amc_vehicle_card(
				$vehicle,
				array(
					'heading' => 'h2',
					'eager'   => $index < 3,
				)
			);
		}
		?>
	</div>

	<?php if ( $results['pages'] > 1 ) : ?>
		<nav class="amds-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'automotive-marketplace-core' ); ?>">
			<?php
			$query = $args['query'];
			$link  = static function ( $page ) use ( $base_url, $query ) {
				$args = array_map( 'rawurlencode', $query );
				if ( $page > 1 ) {
					$args['pg'] = $page;
				}
				return $args ? add_query_arg( $args, $base_url ) : $base_url;
			};
			$current = $results['page'];
			if ( $current > 1 ) {
				echo '<a class="amds-pagination__link" rel="prev" data-amc-page="' . esc_attr( $current - 1 ) . '" href="' . esc_url( $link( $current - 1 ) ) . '">' . esc_html__( 'Previous', 'automotive-marketplace-core' ) . '</a>';
			}
			for ( $p = 1; $p <= $results['pages']; $p++ ) {
				if ( $p > 1 && $p < $results['pages'] && abs( $p - $current ) > 2 ) {
					if ( 2 === $p || $results['pages'] - 1 === $p ) {
						echo '<span class="amds-pagination__gap" aria-hidden="true">…</span>';
					}
					continue;
				}
				if ( $p === $current ) {
					echo '<span class="amds-pagination__link is-current" aria-current="page">' . esc_html( $p ) . '</span>';
				} else {
					/* translators: %d: page number */
					echo '<a class="amds-pagination__link" data-amc-page="' . esc_attr( $p ) . '" aria-label="' . esc_attr( sprintf( __( 'Page %d', 'automotive-marketplace-core' ), $p ) ) . '" href="' . esc_url( $link( $p ) ) . '">' . esc_html( $p ) . '</a>';
				}
			}
			if ( $current < $results['pages'] ) {
				echo '<a class="amds-pagination__link" rel="next" data-amc-page="' . esc_attr( $current + 1 ) . '" href="' . esc_url( $link( $current + 1 ) ) . '">' . esc_html__( 'Next', 'automotive-marketplace-core' ) . '</a>';
			}
			?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
