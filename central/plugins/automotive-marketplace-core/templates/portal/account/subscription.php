<?php
/**
 * Dealer space — subscription and payment of the open order.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$dealer  = $args['dealer'];
$current = AMC\Subscriptions::current_for_dealer( $dealer->id );
$open    = AMC\Subscriptions::open_for_dealer( $dealer->id );
$pending = $open ? AMC\Payments::pending_for_subscription( $open->id ) : null;
$history = AMC\Subscriptions::for_dealer( $dealer->id );

Portal::page_head(
	__( 'Subscription', 'automotive-marketplace-core' ),
	'',
	'<a class="amds-btn amds-btn--primary" href="' . esc_url( Portal::url( 'account', 'checkout' ) ) . '">' . esc_html( $current ? __( 'Renew or change plan', 'automotive-marketplace-core' ) : __( 'Choose a plan', 'automotive-marketplace-core' ) ) . '</a>'
);
?>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Current plan', 'automotive-marketplace-core' ); ?></h2><?php echo $current ? Portal::badge( AMC\Subscriptions::in_grace( $current ) ? 'grace' : 'active' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php if ( $current ) : ?>
			<p class="amc-bigline"><?php echo esc_html( $current->plan_name ); ?></p>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Period', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( Portal::date( $current->starts_at ) . ' → ' . Portal::date( $current->ends_at ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Vehicles', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $current->vehicle_limit ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Users', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $current->team_members ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'AI requests / month', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $current->ai_quota ) ); ?></dd></div>
			</dl>
			<?php if ( AMC\Subscriptions::in_grace( $current ) ) : ?>
				<p class="amds-alert amds-alert--warning">
					<?php
					/* translators: %s: date */
					echo esc_html( sprintf( __( 'Your period has ended. Renew before %s to stay online.', 'automotive-marketplace-core' ), Portal::date( $current->grace_ends_at ) ) );
					?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<p class="amc-muted"><?php esc_html_e( 'No active subscription yet.', 'automotive-marketplace-core' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="amc-panel" id="amc-pay">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Order to pay', 'automotive-marketplace-core' ); ?></h2><?php echo $open ? Portal::badge( $open->status ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php if ( ! $open ) : ?>
			<p class="amc-muted"><?php esc_html_e( 'Nothing to pay at the moment.', 'automotive-marketplace-core' ); ?></p>
		<?php else : ?>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $open->plan_name ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Duration', 'automotive-marketplace-core' ); ?></dt><dd>
					<?php
					/* translators: %d: months */
					echo esc_html( sprintf( _n( '%d month', '%d months', $open->duration_months, 'automotive-marketplace-core' ), $open->duration_months ) );
					?>
				</dd></div>
				<div><dt><?php esc_html_e( 'Amount', 'automotive-marketplace-core' ); ?></dt><dd><strong><?php echo esc_html( amc_format_money( $open->amount, $open->currency ) ); ?></strong></dd></div>
				<div><dt><?php esc_html_e( 'Payment reference', 'automotive-marketplace-core' ); ?></dt><dd><code><?php echo esc_html( AMC\Payments::reference_for( $open->id ) ); ?></code></dd></div>
			</dl>
			<?php if ( 'paid' === $open->status ) : ?>
				<p class="amds-alert amds-alert--success"><?php esc_html_e( 'Payment confirmed. Your subscription starts as soon as your application is approved.', 'automotive-marketplace-core' ); ?></p>
			<?php elseif ( $pending ) : ?>
				<p class="amds-alert amds-alert--info"><?php esc_html_e( 'Your payment is being confirmed by our team. You will receive an email.', 'automotive-marketplace-core' ); ?></p>
			<?php else : ?>
				<?php amc_get_template( 'parts/payment-form', array( 'subscription' => $open ) ); ?>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</div>

<section class="amc-panel">
	<div class="amc-panel__head"><h2><?php esc_html_e( 'History', 'automotive-marketplace-core' ); ?></h2></div>
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Duration', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Amount', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Period', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $history ) : ?>
					<tr><td colspan="5" class="amc-muted"><?php esc_html_e( 'No subscription yet.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $history as $sub ) : ?>
					<tr>
						<td><?php echo esc_html( $sub->plan_name ); ?></td>
						<td>
							<?php
							/* translators: %d: months */
							echo esc_html( sprintf( _n( '%d month', '%d months', $sub->duration_months, 'automotive-marketplace-core' ), $sub->duration_months ) );
							?>
						</td>
						<td><?php echo esc_html( amc_format_money( $sub->amount, $sub->currency ) ); ?></td>
						<td><?php echo esc_html( $sub->starts_at ? Portal::date( $sub->starts_at ) . ' → ' . Portal::date( $sub->ends_at ) : '—' ); ?></td>
						<td><?php echo Portal::badge( $sub->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
