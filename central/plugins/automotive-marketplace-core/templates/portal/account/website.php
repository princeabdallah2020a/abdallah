<?php
/**
 * Dealer space — website setup status.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$dealer = $args['dealer'];
$job    = AMC\Provisioning::for_dealer( $dealer->id );
$app    = AMC\Memberships::latest_application( $dealer->id );
Portal::page_head( __( 'My website', 'automotive-marketplace-core' ), __( 'Your independent website and CRM, on your own domain.', 'automotive-marketplace-core' ) );

if ( ! $job ) :
	?>
	<section class="amc-panel amc-empty-panel">
		<?php echo amc_icon( 'globe', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<h2><?php esc_html_e( 'Setup starts after approval', 'automotive-marketplace-core' ); ?></h2>
		<p>
			<?php
			echo esc_html(
				$app && $app->domain
					/* translators: %s: domain */
					? sprintf( __( 'Requested domain: %s. Our team will prepare your website as soon as your application is approved.', 'automotive-marketplace-core' ), $app->domain )
					: __( 'Our team will prepare your website as soon as your application is approved.', 'automotive-marketplace-core' )
			);
			?>
		</p>
	</section>
	<?php
	return;
endif;

list( $done, $total ) = AMC\Provisioning::progress( $job );
$labels               = AMC\Provisioning::provider( $job->provider )->steps();
?>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Setup progress', 'automotive-marketplace-core' ); ?></h2><?php echo Portal::badge( $job->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="amc-progress amc-progress--lg"><span style="width:<?php echo esc_attr( (int) round( 100 * $done / max( 1, $total ) ) ); ?>%"></span></div>
		<ul class="amc-checklist">
			<?php foreach ( $job->steps as $key => $step ) : ?>
				<li class="<?php echo empty( $step['done'] ) ? '' : 'is-done'; ?>">
					<span class="amc-checklist__box"><?php echo empty( $step['done'] ) ? '' : amc_icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="amc-checklist__text"><?php echo esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Details', 'automotive-marketplace-core' ); ?></h2></div>
		<dl class="amc-dl">
			<div><dt><?php esc_html_e( 'Domain', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $job->domain ? $job->domain : '—' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $job->site_url ? '<a href="' . esc_url( $job->site_url ) . '" target="_blank" rel="noopener">' . esc_html( $job->site_url ) . '</a>' : '—'; ?></dd></div>
			<div><dt><?php esc_html_e( 'CRM and administration', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $job->admin_url ? '<a href="' . esc_url( $job->admin_url ) . '" target="_blank" rel="noopener">' . esc_html( $job->admin_url ) . '</a>' : '—'; ?></dd></div>
		</dl>
		<p class="amc-muted"><?php esc_html_e( 'Your login details are sent to you separately by our team.', 'automotive-marketplace-core' ); ?></p>
	</section>
</div>
