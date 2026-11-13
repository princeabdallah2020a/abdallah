<?php
/**
 * Dealer card.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$dealer = $args['dealer'];
$url    = amc_dealer_url( $dealer->slug );
?>
<article class="amc-dealer-card">
	<a class="amc-dealer-card__logo" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $dealer->logo_url ) : ?>
			<img src="<?php echo esc_url( $dealer->logo_url ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
		<?php else : ?>
			<span><?php echo esc_html( amc_initials( $dealer->business_name ) ); ?></span>
		<?php endif; ?>
	</a>
	<div class="amc-dealer-card__body">
		<h3 class="amc-dealer-card__name"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $dealer->business_name ); ?></a></h3>
		<p class="amc-dealer-card__meta">
			<?php if ( $dealer->city ) : ?>
				<span><?php echo amc_icon( 'pin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $dealer->city ); ?></span>
			<?php endif; ?>
			<?php if ( isset( $dealer->vehicle_count ) ) : ?>
				<span><?php echo amc_icon( 'car', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				/* translators: %d: number of vehicles */
				echo esc_html( sprintf( _n( '%d vehicle', '%d vehicles', $dealer->vehicle_count, 'automotive-marketplace-core' ), $dealer->vehicle_count ) );
				?>
				</span>
			<?php endif; ?>
		</p>
		<p class="amc-dealer-card__badges">
			<?php if ( $dealer->is_verified ) : ?>
				<span class="amds-badge amds-badge--success"><?php echo amc_icon( 'shield', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Verified dealer', 'automotive-marketplace-core' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $dealer->is_demo ) ) : ?>
				<span class="amds-badge amds-badge--neutral"><?php esc_html_e( 'Demo', 'automotive-marketplace-core' ); ?></span>
			<?php endif; ?>
		</p>
	</div>
</article>
