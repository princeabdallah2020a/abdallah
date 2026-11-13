<?php
/**
 * Plan + duration picker with live price summary (registration and checkout).
 *
 * @var array $args { plan: string, months: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$plans     = AMC\Plans::all( true );
$durations = AMC\Plans::durations();
$selected  = $args['plan'];
$months    = isset( $durations[ $args['months'] ] ) ? (int) $args['months'] : 1;
$data      = array();
foreach ( $plans as $plan ) {
	foreach ( array_keys( $durations ) as $m ) {
		$price                            = AMC\Plans::price( $plan, $m );
		$data[ $plan->code ]['name']      = $plan->name;
		$data[ $plan->code ]['prices'][ $m ] = array(
			'monthly'  => amc_format_money( $price['monthly'], $plan->currency ),
			'total'    => amc_format_money( $price['total'], $plan->currency ),
			'saving'   => $price['saving'] ? amc_format_money( $price['saving'], $plan->currency ) : '',
			'discount' => $price['discount'],
		);
	}
}
if ( ! isset( $data[ $selected ] ) ) {
	$selected = '';
	foreach ( $plans as $plan ) {
		if ( $plan->is_highlighted || '' === $selected ) {
			$selected = $plan->code;
		}
	}
}
?>
<div class="amc-picker" data-amc-picker data-prices="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
	<fieldset class="amds-fieldset">
		<legend class="amds-label"><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></legend>
		<div class="amc-picker__plans">
			<?php foreach ( $plans as $plan ) : ?>
				<?php $price = AMC\Plans::price( $plan, 1 ); ?>
				<label class="amc-choice<?php echo $plan->is_highlighted ? ' amc-choice--highlight' : ''; ?>">
					<input type="radio" name="plan" value="<?php echo esc_attr( $plan->code ); ?>" <?php checked( $selected, $plan->code ); ?> required>
					<span class="amc-choice__body">
						<span class="amc-choice__title"><?php echo esc_html( $plan->name ); ?>
							<?php if ( $plan->is_highlighted ) : ?><span class="amds-badge amds-badge--accent"><?php esc_html_e( 'Most chosen', 'automotive-marketplace-core' ); ?></span><?php endif; ?>
						</span>
						<span class="amc-choice__price"><?php echo esc_html( amc_format_money( $price['monthly'], $plan->currency ) ); ?> <small><?php esc_html_e( '/ month', 'automotive-marketplace-core' ); ?></small></span>
						<span class="amc-choice__meta">
							<?php
							/* translators: 1: number of vehicles, 2: number of users */
							echo esc_html( sprintf( __( 'Up to %1$d vehicles · %2$d users', 'automotive-marketplace-core' ), $plan->vehicle_limit, $plan->team_members ) );
							?>
						</span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<fieldset class="amds-fieldset">
		<legend class="amds-label"><?php esc_html_e( 'Commitment', 'automotive-marketplace-core' ); ?></legend>
		<div class="amc-segment">
			<?php foreach ( $durations as $m => $discount ) : ?>
				<label>
					<input type="radio" name="months" value="<?php echo esc_attr( $m ); ?>" <?php checked( $months, $m ); ?>>
					<span>
						<?php
						/* translators: %d: number of months */
						echo esc_html( sprintf( _n( '%d month', '%d months', $m, 'automotive-marketplace-core' ), $m ) );
						?>
						<?php if ( $discount > 0 ) : ?><em>−<?php echo esc_html( rtrim( rtrim( number_format( $discount, 1, ',', '' ), '0' ), ',' ) ); ?>%</em><?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
</div>
