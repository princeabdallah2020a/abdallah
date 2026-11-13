<?php
/**
 * Payment declaration for an order waiting for payment.
 *
 * @var array $args { subscription: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$sub       = $args['subscription'];
$reference = AMC\Payments::reference_for( $sub->id );
$providers = AMC\Payments::providers();
$first     = array_key_first( $providers );
?>
<form class="amds-form amc-payform" method="post" enctype="multipart/form-data">
	<?php AMC\Portal::action_fields( 'payment' ); ?>
	<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub->id ); ?>">
	<div class="amc-options" data-amc-pay-options>
		<?php foreach ( $providers as $id => $provider ) : ?>
			<label class="amc-choice amc-choice--row">
				<input type="radio" name="payment_method" value="<?php echo esc_attr( $id ); ?>" <?php checked( $first, $id ); ?>>
				<span class="amc-choice__body">
					<span class="amc-choice__title"><?php echo esc_html( $provider->label() ); ?></span>
					<span class="amc-choice__meta"><?php echo esc_html( $provider->instructions( $sub->amount, $sub->currency, $reference ) ); ?></span>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
	<div class="amds-form__row">
		<div class="amds-field">
			<label class="amds-label" for="amc-payer-ref"><?php esc_html_e( 'Transaction reference', 'automotive-marketplace-core' ); ?></label>
			<input class="amds-input" type="text" id="amc-payer-ref" name="payer_reference" maxlength="120">
		</div>
		<div class="amds-field">
			<label class="amds-label" for="amc-proof"><?php esc_html_e( 'Receipt (screenshot or PDF)', 'automotive-marketplace-core' ); ?></label>
			<input class="amds-input" type="file" id="amc-proof" name="payment_proof" accept="application/pdf,image/jpeg,image/png,image/webp">
		</div>
	</div>
	<button class="amds-btn amds-btn--accent amds-btn--lg" type="submit"><?php esc_html_e( 'I have paid — send for confirmation', 'automotive-marketplace-core' ); ?></button>
</form>
