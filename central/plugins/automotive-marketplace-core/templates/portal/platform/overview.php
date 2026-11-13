<?php
/**
 * Platform admin — overview.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$apps     = AMC\Memberships::application_counts();
$dealers  = AMC\Memberships::dealer_counts();
$waiting  = ( $apps['submitted'] ?? 0 ) + ( $apps['under_review'] ?? 0 );
$payments = AMC\Payments::pending_count();
$jobs     = AMC\Provisioning::open_count();
$tickets  = AMC\Support::open_count();
$expiring = AMC\Subscriptions::expiring_count( 14 );
$stats    = AMC\Assistant_Tools::platform_stats();
$currency = AMC\Settings::get( 'default_currency' );
$visible  = array_sum( $stats['visible_listings_by_type'] );

Portal::page_head( __( 'Overview', 'automotive-marketplace-core' ), wp_date( get_option( 'date_format' ) ), '<a class="amds-btn amds-btn--secondary" href="' . esc_url( Portal::url( 'platform', 'assistant' ) ) . '">' . amc_icon( 'sparkle', 16 ) . esc_html__( 'Ask the AI assistant', 'automotive-marketplace-core' ) . '</a>' );
?>
<div class="amc-kpis">
	<a class="amc-kpi" href="<?php echo esc_url( Portal::url( 'platform', 'dealers', 0, array( 'status' => 'active' ) ) ); ?>"><span class="amc-kpi__label"><?php esc_html_e( 'Active dealers', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $dealers['active'] ?? 0 ) ); ?></strong></a>
	<a class="amc-kpi" href="<?php echo esc_url( Portal::url( 'platform', 'subscriptions' ) ); ?>"><span class="amc-kpi__label"><?php esc_html_e( 'Monthly recurring revenue', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( amc_format_money( AMC\Subscriptions::mrr(), $currency ) ); ?></strong></a>
	<a class="amc-kpi" href="<?php echo esc_url( Portal::url( 'platform', 'listings' ) ); ?>"><span class="amc-kpi__label"><?php esc_html_e( 'Listings online', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $visible ) ); ?></strong></a>
	<a class="amc-kpi" href="<?php echo esc_url( Portal::url( 'platform', 'ai' ) ); ?>"><span class="amc-kpi__label"><?php esc_html_e( 'AI requests (30 days)', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( array_sum( $stats['ai_requests_30_days'] ) ) ); ?></strong></a>
</div>

<section class="amc-panel">
	<div class="amc-panel__head"><h2><?php esc_html_e( 'To do', 'automotive-marketplace-core' ); ?></h2></div>
	<ul class="amc-todo">
		<?php
		$todo = array(
			/* translators: %d: count */
			array( $waiting, _n( '%d application to review', '%d applications to review', $waiting, 'automotive-marketplace-core' ), Portal::url( 'platform', 'applications' ) ),
			/* translators: %d: count */
			array( $payments, _n( '%d payment to verify', '%d payments to verify', $payments, 'automotive-marketplace-core' ), Portal::url( 'platform', 'payments' ) ),
			/* translators: %d: count */
			array( $jobs, _n( '%d website to set up', '%d websites to set up', $jobs, 'automotive-marketplace-core' ), Portal::url( 'platform', 'websites' ) ),
			/* translators: %d: count */
			array( $tickets, _n( '%d support request waiting', '%d support requests waiting', $tickets, 'automotive-marketplace-core' ), Portal::url( 'platform', 'support' ) ),
			/* translators: %d: count */
			array( $expiring, _n( '%d subscription ends within 14 days', '%d subscriptions end within 14 days', $expiring, 'automotive-marketplace-core' ), Portal::url( 'platform', 'subscriptions', 0, array( 'status' => 'active' ) ) ),
			/* translators: %d: count */
			array( $stats['listings_not_confirmed'], _n( '%d listing not confirmed by its dealer website', '%d listings not confirmed by their dealer websites', $stats['listings_not_confirmed'], 'automotive-marketplace-core' ), Portal::url( 'platform', 'listings' ) ),
		);
		$empty = true;
		foreach ( $todo as $row ) :
			if ( ! $row[0] ) {
				continue;
			}
			$empty = false;
			?>
			<li><a href="<?php echo esc_url( $row[2] ); ?>"><span class="amc-todo__count"><?php echo esc_html( $row[0] ); ?></span><?php echo esc_html( sprintf( $row[1], $row[0] ) ); ?><?php echo amc_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
		<?php endforeach; ?>
		<?php if ( $empty ) : ?>
			<li class="amc-muted"><?php esc_html_e( 'Nothing waiting. Well done.', 'automotive-marketplace-core' ); ?></li>
		<?php endif; ?>
	</ul>
</section>

<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Latest applications', 'automotive-marketplace-core' ); ?></h2><a class="amds-link" href="<?php echo esc_url( Portal::url( 'platform', 'applications', 0, array( 'status' => 'all' ) ) ); ?>"><?php esc_html_e( 'All', 'automotive-marketplace-core' ); ?></a></div>
		<ul class="amc-files">
			<?php foreach ( AMC\Memberships::applications( '', 6 ) as $app ) : ?>
				<li><span><a href="<?php echo esc_url( Portal::url( 'platform', 'applications', $app->id ) ); ?>"><strong><?php echo esc_html( $app->business_name ); ?></strong></a><small><?php echo esc_html( $app->city . ' · ' . $app->plan_name . ' · ' . Portal::date( $app->created_at ) ); ?></small></span><?php echo Portal::badge( $app->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Recent activity', 'automotive-marketplace-core' ); ?></h2><a class="amds-link" href="<?php echo esc_url( Portal::url( 'platform', 'audit' ) ); ?>"><?php esc_html_e( 'All', 'automotive-marketplace-core' ); ?></a></div>
		<ul class="amc-files">
			<?php foreach ( AMC\Audit::latest( 8 ) as $event ) : ?>
				<?php $user = $event->actor_user_id ? get_userdata( (int) $event->actor_user_id ) : null; ?>
				<li><span><code><?php echo esc_html( $event->action ); ?></code><small><?php echo esc_html( ( $user ? $user->display_name : __( 'System', 'automotive-marketplace-core' ) ) . ' · ' . Portal::date( $event->created_at, true ) ); ?></small></span></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
