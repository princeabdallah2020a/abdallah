<?php
/**
 * Staff subscriptions table with actions.
 *
 * @var array $args { rows: object[], show_dealer: bool }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$durations = array_keys( AMC\Plans::durations() );
?>
<div class="amc-table-wrap">
	<table class="amds-table">
		<thead><tr>
			<?php if ( $args['show_dealer'] ) : ?><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><?php endif; ?>
			<th><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Amount', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Period', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Actions', 'automotive-marketplace-core' ); ?></th>
		</tr></thead>
		<tbody>
			<?php if ( ! $args['rows'] ) : ?>
				<tr><td colspan="6" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $args['rows'] as $sub ) : ?>
				<tr>
					<?php if ( $args['show_dealer'] ) : ?><td><a href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $sub->dealer_id ) ); ?>"><?php echo esc_html( $sub->business_name ); ?></a></td><?php endif; ?>
					<td><?php echo esc_html( $sub->plan_name . ' · ' . $sub->duration_months . ' m' ); ?></td>
					<td><?php echo esc_html( amc_format_money( $sub->amount, $sub->currency ) ); ?></td>
					<td><?php echo esc_html( $sub->starts_at ? Portal::date( $sub->starts_at ) . ' → ' . Portal::date( $sub->ends_at ) : '—' ); ?></td>
					<td><?php echo Portal::badge( AMC\Subscriptions::in_grace( $sub ) ? 'grace' : $sub->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td class="amc-actions">
						<?php if ( in_array( $sub->status, array( 'pending_payment', 'paid' ), true ) ) : ?>
							<form method="post" data-amc-confirm><?php Portal::action_fields( 'sub_activate' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $sub->id ); ?>"><button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Activate', 'automotive-marketplace-core' ); ?></button></form>
						<?php endif; ?>
						<?php if ( 'active' === $sub->status ) : ?>
							<form method="post" class="amc-inline-form"><?php Portal::action_fields( 'sub_extend' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $sub->id ); ?>">
								<select class="amds-select amds-select--sm" name="months" aria-label="<?php esc_attr_e( 'Months', 'automotive-marketplace-core' ); ?>"><?php foreach ( $durations as $m ) : ?><option value="<?php echo esc_attr( $m ); ?>">+<?php echo esc_html( $m ); ?> m</option><?php endforeach; ?></select>
								<button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Extend', 'automotive-marketplace-core' ); ?></button>
							</form>
						<?php endif; ?>
						<?php if ( in_array( $sub->status, array( 'pending_payment', 'paid', 'active' ), true ) ) : ?>
							<form method="post" data-amc-confirm><?php Portal::action_fields( 'sub_cancel' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $sub->id ); ?>"><button class="amds-btn amds-btn--ghost amc-danger" type="submit"><?php esc_html_e( 'Cancel', 'automotive-marketplace-core' ); ?></button></form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
