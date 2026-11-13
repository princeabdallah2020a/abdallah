<?php
/**
 * Dealer space — payments.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$payments = AMC\Payments::for_dealer( $args['dealer']->id );
Portal::page_head( __( 'Payments', 'automotive-marketplace-core' ) );
?>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Date', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Amount', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Method', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Reference', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Receipt', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $payments ) : ?>
					<tr><td colspan="6" class="amc-muted"><?php esc_html_e( 'No payment yet.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $payments as $payment ) : ?>
					<tr>
						<td><?php echo esc_html( Portal::date( $payment->created_at ) ); ?></td>
						<td><?php echo esc_html( amc_format_money( $payment->amount, $payment->currency ) ); ?></td>
						<td><?php echo esc_html( AMC\Payments::method_label( $payment->method ) ); ?></td>
						<td><code><?php echo esc_html( $payment->reference ); ?></code><?php echo $payment->payer_reference ? '<br><small>' . esc_html( $payment->payer_reference ) . '</small>' : ''; ?></td>
						<td><?php echo $payment->proof_document_id ? '<a href="' . esc_url( AMC\Documents::url( $payment->proof_document_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'automotive-marketplace-core' ) . '</a>' : '—'; ?></td>
						<td><?php echo Portal::badge( $payment->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $payment->note ? '<br><small>' . esc_html( $payment->note ) . '</small>' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
