<?php
/**
 * Platform admin — plans, prices and commitment discounts.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$plans = AMC\Plans::all();
Portal::page_head( __( 'Plans and prices', 'automotive-marketplace-core' ), __( 'Changes apply to new orders; existing subscriptions keep the price they were sold at.', 'automotive-marketplace-core' ), '<a class="amds-btn amds-btn--secondary" href="' . esc_url( Portal::url( 'pricing' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'See the pricing page', 'automotive-marketplace-core' ) . '</a>' );

$plan_form = static function ( $plan ) {
	$v = static function ( $key, $default = '' ) use ( $plan ) {
		return $plan ? $plan->$key : $default;
	};
	?>
	<form class="amds-form amc-plan-form" method="post">
		<?php Portal::action_fields( 'plan_save' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $plan ? $plan->id : 0 ); ?>">
		<div class="amds-form__row">
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Name', 'automotive-marketplace-core' ); ?><input class="amds-input" name="name" value="<?php echo esc_attr( $v( 'name' ) ); ?>" required></label></div>
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Tagline', 'automotive-marketplace-core' ); ?><input class="amds-input" name="tagline" value="<?php echo esc_attr( $v( 'tagline' ) ); ?>"></label></div>
		</div>
		<div class="amds-form__row">
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Price per month', 'automotive-marketplace-core' ); ?><input class="amds-input" name="price_amount" inputmode="numeric" value="<?php echo esc_attr( $v( 'price_amount', 0 ) ); ?>" required></label></div>
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Currency', 'automotive-marketplace-core' ); ?><select class="amds-select" name="currency"><?php foreach ( array_keys( AMC\Catalog::currencies() ) as $code ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $v( 'currency', 'XOF' ), $code ); ?>><?php echo esc_html( $code ); ?></option><?php endforeach; ?></select></label></div>
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Vehicles', 'automotive-marketplace-core' ); ?><input class="amds-input" type="number" min="0" name="vehicle_limit" value="<?php echo esc_attr( $v( 'vehicle_limit', 10 ) ); ?>"></label></div>
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Users', 'automotive-marketplace-core' ); ?><input class="amds-input" type="number" min="1" name="team_members" value="<?php echo esc_attr( $v( 'team_members', 1 ) ); ?>"></label></div>
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'AI requests / month', 'automotive-marketplace-core' ); ?><input class="amds-input" type="number" min="0" name="ai_quota" value="<?php echo esc_attr( $v( 'ai_quota', 100 ) ); ?>"></label></div>
			<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Display order', 'automotive-marketplace-core' ); ?><input class="amds-input" type="number" name="sort_order" value="<?php echo esc_attr( $v( 'sort_order', 9 ) ); ?>"></label></div>
		</div>
		<div class="amds-field"><label class="amds-label"><?php esc_html_e( 'Features (one per line)', 'automotive-marketplace-core' ); ?><textarea class="amds-textarea" name="features" rows="5"><?php echo esc_textarea( $plan ? implode( "\n", $plan->features ) : '' ); ?></textarea></label></div>
		<div class="amc-checks">
			<label class="amc-check"><input type="checkbox" name="is_active" value="1" <?php checked( $v( 'is_active', 1 ), 1 ); ?>> <?php esc_html_e( 'Open for subscription', 'automotive-marketplace-core' ); ?></label>
			<label class="amc-check"><input type="checkbox" name="is_highlighted" value="1" <?php checked( $v( 'is_highlighted', 0 ), 1 ); ?>> <?php esc_html_e( 'Highlight as "most chosen"', 'automotive-marketplace-core' ); ?></label>
			<label class="amc-check"><input type="checkbox" name="visibility_boost" value="1" <?php checked( $v( 'visibility_boost', 0 ), 1 ); ?>> <?php esc_html_e( 'Premium visibility in the dealer directory', 'automotive-marketplace-core' ); ?></label>
		</div>
		<div><button class="amds-btn amds-btn--primary" type="submit"><?php echo esc_html( $plan ? __( 'Save', 'automotive-marketplace-core' ) : __( 'Create the plan', 'automotive-marketplace-core' ) ); ?></button></div>
	</form>
	<?php
};
?>
<?php foreach ( $plans as $plan ) : ?>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php echo esc_html( $plan->name ); ?> <small class="amc-muted"><?php echo esc_html( $plan->code ); ?></small></h2><?php echo $plan->is_active ? Portal::badge( 'active' ) : Portal::badge( 'cancelled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php $plan_form( $plan ); ?>
	</section>
<?php endforeach; ?>

<details class="amc-panel amc-collapsible">
	<summary><?php esc_html_e( 'Add a plan', 'automotive-marketplace-core' ); ?></summary>
	<?php $plan_form( null ); ?>
</details>

<section class="amc-panel">
	<div class="amc-panel__head"><h2><?php esc_html_e( 'Commitment durations and discounts', 'automotive-marketplace-core' ); ?></h2></div>
	<form class="amds-form" method="post">
		<?php Portal::action_fields( 'durations_save' ); ?>
		<table class="amds-table amc-durations">
			<thead><tr><th><?php esc_html_e( 'Months', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Discount (%)', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php $i = 0; ?>
				<?php foreach ( AMC\Plans::durations() + array( 'new1' => '', 'new2' => '' ) as $months => $discount ) : ?>
					<tr>
						<td><input class="amds-input" type="number" min="1" max="36" name="durations[<?php echo esc_attr( $i ); ?>][months]" value="<?php echo esc_attr( is_int( $months ) ? $months : '' ); ?>" aria-label="<?php esc_attr_e( 'Months', 'automotive-marketplace-core' ); ?>"></td>
						<td><input class="amds-input" type="number" min="0" max="90" step="0.5" name="durations[<?php echo esc_attr( $i ); ?>][discount]" value="<?php echo esc_attr( $discount ); ?>" aria-label="<?php esc_attr_e( 'Discount (%)', 'automotive-marketplace-core' ); ?>"></td>
					</tr>
					<?php ++$i; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="amc-muted"><?php esc_html_e( 'Leave the months empty to remove a line. Monthly billing (1 month) always exists.', 'automotive-marketplace-core' ); ?></p>
		<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Save', 'automotive-marketplace-core' ); ?></button></div>
	</form>
</section>
