<?php
/**
 * Dealer space — order a plan (renewal or change of plan).
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$current = AMC\Subscriptions::current_for_dealer( $args['dealer']->id );
AMC\Portal::page_head(
	$current ? __( 'Renew or change plan', 'automotive-marketplace-core' ) : __( 'Choose a plan', 'automotive-marketplace-core' ),
	$current
		/* translators: %s: date */
		? sprintf( __( 'Your new period will start on %s, at the end of the current one.', 'automotive-marketplace-core' ), AMC\Portal::date( $current->ends_at ) )
		: __( 'Your subscription starts once the payment is confirmed.', 'automotive-marketplace-core' )
);
?>
<form class="amc-checkout" method="post">
	<?php AMC\Portal::action_fields( 'checkout' ); ?>
	<div class="amc-panel">
		<?php
		amc_get_template(
			'parts/plan-picker',
			array(
				'plan'   => $current ? $current->plan_code : '',
				'months' => $current ? (int) $current->duration_months : 1,
			)
		);
		?>
	</div>
	<aside class="amc-checkout__aside">
		<?php amc_get_template( 'parts/order-summary' ); ?>
		<button class="amds-btn amds-btn--accent amds-btn--lg amds-btn--block" type="submit"><?php esc_html_e( 'Continue to payment', 'automotive-marketplace-core' ); ?></button>
	</aside>
</form>
