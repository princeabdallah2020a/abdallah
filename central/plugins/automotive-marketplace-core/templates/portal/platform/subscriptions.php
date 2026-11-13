<?php
/**
 * Platform admin — subscriptions.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$dealers = AMC\Memberships::dealers( array( 'limit' => 500 ) )['items'];
Portal::page_head( __( 'Subscriptions', 'automotive-marketplace-core' ), sprintf( '%s : %s', __( 'Monthly recurring revenue', 'automotive-marketplace-core' ), amc_format_money( AMC\Subscriptions::mrr(), AMC\Settings::get( 'default_currency' ) ) ) );
$tabs = array(
	''                => __( 'All', 'automotive-marketplace-core' ),
	'active'          => __( 'Active', 'automotive-marketplace-core' ),
	'pending_payment' => __( 'Awaiting payment', 'automotive-marketplace-core' ),
	'paid'            => __( 'Paid, waiting', 'automotive-marketplace-core' ),
	'expired'         => __( 'Expired', 'automotive-marketplace-core' ),
	'cancelled'       => __( 'Cancelled', 'automotive-marketplace-core' ),
);
?>
<details class="amc-panel amc-collapsible">
	<summary><?php esc_html_e( 'Record a subscription manually (cash, offer, migration…)', 'automotive-marketplace-core' ); ?></summary>
	<form class="amds-form" method="post">
		<?php Portal::action_fields( 'sub_create' ); ?>
		<div class="amds-form__row">
			<div class="amds-field"><label class="amds-label" for="amc-sc-dealer"><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></label>
				<select class="amds-select" id="amc-sc-dealer" name="dealer_id" required><option value=""></option><?php foreach ( $dealers as $d ) : ?><option value="<?php echo esc_attr( $d->id ); ?>"><?php echo esc_html( $d->business_name . ' — ' . $d->city ); ?></option><?php endforeach; ?></select></div>
			<div class="amds-field"><label class="amds-label" for="amc-sc-plan"><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></label>
				<select class="amds-select" id="amc-sc-plan" name="plan"><?php foreach ( AMC\Plans::all() as $plan ) : ?><option value="<?php echo esc_attr( $plan->code ); ?>"><?php echo esc_html( $plan->name ); ?></option><?php endforeach; ?></select></div>
			<div class="amds-field"><label class="amds-label" for="amc-sc-months"><?php esc_html_e( 'Duration', 'automotive-marketplace-core' ); ?></label>
				<select class="amds-select" id="amc-sc-months" name="months"><?php foreach ( array_keys( AMC\Plans::durations() ) as $m ) : ?><option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?> m</option><?php endforeach; ?></select></div>
		</div>
		<div class="amds-form__row">
			<label class="amc-check"><input type="checkbox" name="paid" value="1"> <?php esc_html_e( 'Payment already received (activate now)', 'automotive-marketplace-core' ); ?></label>
			<div class="amds-field"><label class="amds-label" for="amc-sc-ref"><?php esc_html_e( 'Payment reference (optional)', 'automotive-marketplace-core' ); ?></label><input class="amds-input" id="amc-sc-ref" name="reference" maxlength="120"></div>
		</div>
		<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Create', 'automotive-marketplace-core' ); ?></button></div>
	</form>
</details>
<nav class="amds-tabs amc-app__tabs">
	<?php foreach ( $tabs as $key => $label ) : ?>
		<a class="amds-tabs__item<?php echo $key === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( Portal::url( 'platform', 'subscriptions', 0, $key ? array( 'status' => $key ) : array() ) ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<section class="amc-panel">
	<?php amc_get_template( 'parts/subscriptions-table', array( 'rows' => AMC\Subscriptions::list( $status ), 'show_dealer' => true ) ); ?>
</section>
