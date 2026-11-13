<?php
/**
 * Dealer space — getting started.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$steps = AMC\Dealer_Account::onboarding( $args['dealer'] );
$done  = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
AMC\Portal::page_head( __( 'Getting started', 'automotive-marketplace-core' ), __( 'Five steps to get your dealership online.', 'automotive-marketplace-core' ) );
?>
<section class="amc-panel">
	<div class="amc-progress amc-progress--lg"><span style="width:<?php echo esc_attr( (int) round( 100 * $done / max( 1, count( $steps ) ) ) ); ?>%"></span></div>
	<ol class="amc-checklist amc-checklist--large">
		<?php foreach ( $steps as $index => $step ) : ?>
			<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
				<span class="amc-checklist__box"><?php echo $step['done'] ? amc_icon( 'check', 16 ) : esc_html( $index + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="amc-checklist__text"><strong><?php echo esc_html( $step['label'] ); ?></strong><small><?php echo esc_html( $step['hint'] ); ?></small></span>
				<?php if ( ! $step['done'] && $step['url'] ) : ?>
					<a class="amds-btn amds-btn--secondary" href="<?php echo esc_url( $step['url'] ); ?>"><?php esc_html_e( 'Do it', 'automotive-marketplace-core' ); ?></a>
				<?php elseif ( $step['done'] ) : ?>
					<?php echo AMC\Portal::badge( 'done' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</section>
