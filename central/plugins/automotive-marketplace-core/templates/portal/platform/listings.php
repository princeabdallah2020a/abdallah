<?php
/**
 * Platform admin — marketplace listings moderation and featuring.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$rows = AMC\Platform_Admin::listings( $q );
Portal::page_head( __( 'Listings', 'automotive-marketplace-core' ), __( 'Feature listings or hide one that breaks the rules. The dealer website remains the source of truth for its stock.', 'automotive-marketplace-core' ) );
?>
<form class="amc-search" method="get" action="<?php echo esc_url( Portal::url( 'platform', 'listings' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="amc_view" value="platform"><input type="hidden" name="amc_section" value="listings"><?php endif; ?>
	<input class="amds-input" type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Vehicle or dealership', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'automotive-marketplace-core' ); ?>">
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Search', 'automotive-marketplace-core' ); ?></button>
</form>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Vehicle', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Price', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Featured', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Actions', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $price = (object) array( 'price_amount' => (int) $row->price_amount, 'price_period' => $row->price_period, 'currency' => $row->currency ); ?>
					<tr>
						<td><a href="<?php echo esc_url( amc_entity_url( 'vehicle', $row->slug ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( $row->title ); ?></strong></a><?php echo $row->is_demo ? ' <span class="amds-badge amds-badge--neutral">' . esc_html__( 'Demo', 'automotive-marketplace-core' ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( $row->business_name ); ?></td>
						<td><?php echo esc_html( amc_vehicle_price( $price ) ); ?></td>
						<td><?php echo Portal::badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo $row->is_featured ? esc_html( $row->featured_until ? Portal::date( $row->featured_until ) : __( 'Yes', 'automotive-marketplace-core' ) ) : '—'; ?></td>
						<td class="amc-actions">
							<?php if ( $row->is_featured ) : ?>
								<form method="post"><?php Portal::action_fields( 'listing_unfeature' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $row->id ); ?>"><button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Unfeature', 'automotive-marketplace-core' ); ?></button></form>
							<?php else : ?>
								<form method="post" class="amc-inline-form"><?php Portal::action_fields( 'listing_feature' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $row->id ); ?>">
									<select class="amds-select amds-select--sm" name="days" aria-label="<?php esc_attr_e( 'Duration', 'automotive-marketplace-core' ); ?>"><option value="7">7 j</option><option value="30">30 j</option><option value="0">∞</option></select>
									<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Feature', 'automotive-marketplace-core' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( 'unpublished' === $row->status ) : ?>
								<form method="post"><?php Portal::action_fields( 'listing_show' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $row->id ); ?>"><button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Show', 'automotive-marketplace-core' ); ?></button></form>
							<?php else : ?>
								<form method="post" data-amc-confirm><?php Portal::action_fields( 'listing_hide' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $row->id ); ?>"><button class="amds-btn amds-btn--ghost amc-danger" type="submit"><?php esc_html_e( 'Hide', 'automotive-marketplace-core' ); ?></button></form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
