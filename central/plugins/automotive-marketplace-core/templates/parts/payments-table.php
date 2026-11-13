<?php
/**
 * Staff payments table with verification actions.
 *
 * @var array $args { rows: object[], show_dealer: bool }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;
?>
<div class="amc-table-wrap">
	<table class="amds-table">
		<thead><tr>
			<th><?php esc_html_e( 'Date', 'automotive-marketplace-core' ); ?></th>
			<?php if ( $args['show_dealer'] ) : ?><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><?php endif; ?>
			<th><?php esc_html_e( 'Amount', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Method', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'References', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Receipt', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th>
		</tr></thead>
		<tbody>
			<?php if ( ! $args['rows'] ) : ?>
				<tr><td colspan="7" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $args['rows'] as $pay ) : ?>
				<tr>
					<td><?php echo esc_html( Portal::date( $pay->created_at, true ) ); ?></td>
					<?php if ( $args['show_dealer'] ) : ?><td><a href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $pay->dealer_id ) ); ?>"><?php echo esc_html( $pay->business_name ); ?></a></td><?php endif; ?>
					<td><strong><?php echo esc_html( amc_format_money( $pay->amount, $pay->currency ) ); ?></strong></td>
					<td><?php echo esc_html( AMC\Payments::method_label( $pay->method ) ); ?></td>
					<td><code><?php echo esc_html( $pay->reference ); ?></code><?php echo $pay->payer_reference ? '<br><small>' . esc_html( $pay->payer_reference ) . '</small>' : ''; ?></td>
					<td><?php echo $pay->proof_document_id ? '<a href="' . esc_url( AMC\Documents::url( $pay->proof_document_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'automotive-marketplace-core' ) . '</a>' : '—'; ?></td>
					<td>
						<?php echo Portal::badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( 'pending' === $pay->status ) : ?>
							<div class="amc-actions">
								<form method="post" data-amc-confirm><?php Portal::action_fields( 'pay_verify' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $pay->id ); ?>"><button class="amds-btn amds-btn--accent" type="submit"><?php esc_html_e( 'Confirm', 'automotive-marketplace-core' ); ?></button></form>
								<form method="post" class="amc-inline-form"><?php Portal::action_fields( 'pay_reject' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $pay->id ); ?>"><input class="amds-input" name="reason" placeholder="<?php esc_attr_e( 'Reason', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Reason', 'automotive-marketplace-core' ); ?>" required><button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Reject', 'automotive-marketplace-core' ); ?></button></form>
							</div>
						<?php elseif ( $pay->note ) : ?>
							<br><small><?php echo esc_html( $pay->note ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
