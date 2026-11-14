<?php
/**
 * Dealer space — dashboard.
 *
 * @var array $args { dealer: object }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$dealer   = $args['dealer'];
$app      = AMC\Memberships::latest_application( $dealer->id );
$current  = AMC\Subscriptions::current_for_dealer( $dealer->id );
$open     = AMC\Subscriptions::open_for_dealer( $dealer->id );
$pending  = $open ? AMC\Payments::pending_for_subscription( $open->id ) : null;
$job      = AMC\Provisioning::for_dealer( $dealer->id );
$listings = AMC\Dealer_Account::listing_count( $dealer->id );
$steps    = AMC\Dealer_Account::onboarding( $dealer );
$done     = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );

// Next action, most important first.
if ( 'suspended' === $dealer->status ) {
	$next = array( 'warning', __( 'Your account is suspended', 'automotive-marketplace-core' ), $dealer->status_reason ? $dealer->status_reason : __( 'Please contact our team.', 'automotive-marketplace-core' ), __( 'Contact support', 'automotive-marketplace-core' ), Portal::url( 'account', 'support' ) );
} elseif ( $app && 'rejected' === $app->status ) {
	$next = array( 'warning', __( 'Your application was not approved', 'automotive-marketplace-core' ), $app->decision_reason, __( 'Contact support', 'automotive-marketplace-core' ), Portal::url( 'account', 'support' ) );
} elseif ( $open && 'pending_payment' === $open->status && ! $pending ) {
	/* translators: %s: amount */
	$next = array( 'accent', __( 'Pay your subscription', 'automotive-marketplace-core' ), sprintf( __( 'Amount due: %s. Your subscription starts once the payment is confirmed.', 'automotive-marketplace-core' ), amc_format_money( $open->amount, $open->currency ) ), __( 'Pay now', 'automotive-marketplace-core' ), Portal::url( 'account', 'subscription' ) . '#amc-pay' );
} elseif ( $pending ) {
	$next = array( 'info', __( 'Payment being confirmed', 'automotive-marketplace-core' ), __( 'We have received your payment declaration and are checking it.', 'automotive-marketplace-core' ), '', '' );
} elseif ( $app && in_array( $app->status, array( 'submitted', 'under_review' ), true ) ) {
	$next = array( 'info', __( 'Your application is being reviewed', 'automotive-marketplace-core' ), __( 'Our team is checking your information. Sending your documents speeds things up.', 'automotive-marketplace-core' ), __( 'Add documents', 'automotive-marketplace-core' ), Portal::url( 'account', 'documents' ) );
} elseif ( $job && 'done' !== $job->status ) {
	list( $d, $t ) = AMC\Provisioning::progress( $job );
	/* translators: 1: steps done, 2: total steps */
	$next = array( 'info', __( 'Your website is being set up', 'automotive-marketplace-core' ), sprintf( __( '%1$d of %2$d steps completed.', 'automotive-marketplace-core' ), $d, $t ), __( 'Follow the setup', 'automotive-marketplace-core' ), Portal::url( 'account', 'website' ) );
} elseif ( 'expired' === $dealer->status ) {
	$next = array( 'warning', __( 'Your subscription has expired', 'automotive-marketplace-core' ), __( 'Your listings are hidden from the marketplace. Renew to restore them.', 'automotive-marketplace-core' ), __( 'Renew', 'automotive-marketplace-core' ), Portal::url( 'account', 'checkout' ) );
} elseif ( 'active' === $dealer->status ) {
	$next = array( 'success', __( 'You are online', 'automotive-marketplace-core' ), __( 'Your dealership is visible on the marketplace.', 'automotive-marketplace-core' ), __( 'See my public profile', 'automotive-marketplace-core' ), amc_dealer_url( $dealer->slug ) );
} else {
	$next = array( 'info', __( 'Welcome', 'automotive-marketplace-core' ), __( 'Follow the steps below to get online.', 'automotive-marketplace-core' ), '', '' );
}

/* translators: %s: business name */
Portal::page_head( sprintf( __( 'Hello, %s', 'automotive-marketplace-core' ), $dealer->business_name ), __( 'Everything about your membership, in one place.', 'automotive-marketplace-core' ) );
?>
<?php if ( isset( $_GET['welcome'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="amds-alert amds-alert--success"><?php esc_html_e( 'Your application has been sent. A confirmation email is on its way.', 'automotive-marketplace-core' ); ?></div>
<?php endif; ?>

<section class="amc-next amc-next--<?php echo esc_attr( $next[0] ); ?>">
	<div>
		<h2 class="amc-next__title"><?php echo esc_html( $next[1] ); ?></h2>
		<?php if ( $next[2] ) : ?><p><?php echo esc_html( $next[2] ); ?></p><?php endif; ?>
	</div>
	<?php if ( $next[3] ) : ?>
		<a class="amds-btn amds-btn--<?php echo 'accent' === $next[0] ? 'accent' : 'primary'; ?>" href="<?php echo esc_url( $next[4] ); ?>"><?php echo esc_html( $next[3] ); ?></a>
	<?php endif; ?>
</section>

<ol class="amc-timeline">
	<?php foreach ( AMC\Memberships::timeline( $dealer ) as $step ) : ?>
		<li class="<?php echo $step['done'] ? 'is-done' : ( ! empty( $step['current'] ) ? 'is-current' : '' ); ?>">
			<span class="amc-timeline__dot"><?php echo $step['done'] ? amc_icon( 'check', 14 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span><?php echo esc_html( $step['label'] ); ?></span>
		</li>
	<?php endforeach; ?>
</ol>

<div class="amc-kpis">
	<div class="amc-kpi">
		<span class="amc-kpi__label"><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></span>
		<strong class="amc-kpi__value"><?php echo esc_html( $current ? $current->plan_name : ( $open ? $open->plan_name : '—' ) ); ?></strong>
		<span class="amc-kpi__sub"><?php echo $current ? Portal::badge( AMC\Subscriptions::in_grace( $current ) ? 'grace' : 'active' ) : ( $open ? Portal::badge( $open->status ) : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<div class="amc-kpi">
		<span class="amc-kpi__label"><?php esc_html_e( 'Valid until', 'automotive-marketplace-core' ); ?></span>
		<strong class="amc-kpi__value"><?php echo esc_html( $current ? Portal::date( $current->ends_at ) : '—' ); ?></strong>
		<?php if ( $current ) : ?><a class="amc-kpi__sub" href="<?php echo esc_url( Portal::url( 'account', 'checkout' ) ); ?>"><?php esc_html_e( 'Renew', 'automotive-marketplace-core' ); ?></a><?php endif; ?>
	</div>
	<div class="amc-kpi">
		<span class="amc-kpi__label"><?php esc_html_e( 'Vehicles online', 'automotive-marketplace-core' ); ?></span>
		<strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $listings ) ); ?><?php if ( $current ) : ?><small> / <?php echo esc_html( number_format_i18n( $current->vehicle_limit ) ); ?></small><?php endif; ?></strong>
	</div>
	<div class="amc-kpi">
		<span class="amc-kpi__label"><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></span>
		<strong class="amc-kpi__value"><?php echo $job ? Portal::badge( $job->status ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
		<?php if ( $job && $job->site_url ) : ?><a class="amc-kpi__sub" href="<?php echo esc_url( $job->site_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $job->site_url, PHP_URL_HOST ) ); ?></a><?php endif; ?>
	</div>
</div>

<section class="amc-panel">
	<div class="amc-panel__head">
		<h2><?php esc_html_e( 'Getting started', 'automotive-marketplace-core' ); ?></h2>
		<span class="amc-panel__meta">
			<?php
			/* translators: 1: steps done, 2: total */
			echo esc_html( sprintf( __( '%1$d / %2$d done', 'automotive-marketplace-core' ), $done, count( $steps ) ) );
			?>
		</span>
	</div>
	<div class="amc-progress"><span style="width:<?php echo esc_attr( (int) round( 100 * $done / max( 1, count( $steps ) ) ) ); ?>%"></span></div>
	<ul class="amc-checklist">
		<?php foreach ( $steps as $step ) : ?>
			<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
				<span class="amc-checklist__box"><?php echo $step['done'] ? amc_icon( 'check', 14 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="amc-checklist__text"><?php echo esc_html( $step['label'] ); ?></span>
				<?php if ( ! $step['done'] && $step['url'] ) : ?><a class="amds-link" href="<?php echo esc_url( $step['url'] ); ?>"><?php esc_html_e( 'Do it', 'automotive-marketplace-core' ); ?><?php echo amc_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<?php
$amc_credential = AMC\Credentials::active_for_dealer( $dealer->id );
$amc_health     = AMC\Credentials::health( $amc_credential );
$amc_quota      = $current ? (int) $current->ai_quota : 0;
$amc_used       = AMC\Whatsapp::month_count( $dealer->id );
$amc_wa_number  = (string) AMC\Settings::get( 'wa_business_number' );
$amc_health_txt = array(
	'none'  => __( 'Not connected yet: our team will send you a connection code for your CRM.', 'automotive-marketplace-core' ),
	'ok'    => __( 'Connected: your vehicles are synchronised automatically.', 'automotive-marketplace-core' ),
	'stale' => __( 'No recent contact from your website. Check the Marketplace section of your CRM.', 'automotive-marketplace-core' ),
	'error' => __( 'Your website reports errors. Open the Marketplace section of your CRM or contact support.', 'automotive-marketplace-core' ),
);
?>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Website ↔ marketplace', 'automotive-marketplace-core' ); ?></h2></div>
		<p><?php echo esc_html( $amc_health_txt[ $amc_health ] ); ?></p>
		<?php if ( $amc_credential && $amc_credential->last_success_at ) : ?>
			<p class="amc-muted">
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Last synchronisation: %s', 'automotive-marketplace-core' ), Portal::date( $amc_credential->last_success_at, true ) ) );
				?>
			</p>
		<?php endif; ?>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'AI assistant on WhatsApp', 'automotive-marketplace-core' ); ?></h2><?php if ( $amc_quota ) : ?><span class="amc-panel__meta"><?php echo esc_html( $amc_used . ' / ' . $amc_quota ); ?></span><?php endif; ?></div>
		<?php if ( ! AMC\Whatsapp::enabled() ) : ?>
			<p class="amc-muted"><?php esc_html_e( 'Coming soon: manage your CRM by WhatsApp, in French, Wolof or English.', 'automotive-marketplace-core' ); ?></p>
		<?php elseif ( ! $amc_quota ) : ?>
			<p><?php esc_html_e( 'Not included in your plan. Choose a higher plan to manage your CRM by WhatsApp.', 'automotive-marketplace-core' ); ?></p>
		<?php else : ?>
			<p>
				<?php
				/* translators: %s: WhatsApp number */
				echo esc_html( sprintf( __( 'Register your number in your CRM (Marketplace section), then write to %s. Messages used this month are shown above.', 'automotive-marketplace-core' ), $amc_wa_number ? $amc_wa_number : 'AutoBuzy' ) );
				?>
			</p>
		<?php endif; ?>
	</section>
</div>
