<?php
/**
 * Platform admin — dealers.
 *
 * @var array $args { item: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$dealer = $args['item'] ? AMC\Memberships::get_dealer( $args['item'] ) : null;

if ( $dealer ) :
	$app     = AMC\Memberships::latest_application( $dealer->id );
	$subs    = AMC\Subscriptions::for_dealer( $dealer->id );
	$job     = AMC\Provisioning::for_dealer( $dealer->id );
	$user    = $dealer->user_id ? get_userdata( (int) $dealer->user_id ) : null;
	$actions = 'active' === $dealer->status ? '<a class="amds-btn amds-btn--secondary" href="' . esc_url( amc_dealer_url( $dealer->slug ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Public profile', 'automotive-marketplace-core' ) . '</a>' : '';
	Portal::page_head( $dealer->business_name, $dealer->city, Portal::badge( $dealer->status ) . $actions );
	?>
	<?php if ( $dealer->status_reason ) : ?>
		<p class="amds-alert amds-alert--warning"><?php echo esc_html( __( 'Reason:', 'automotive-marketplace-core' ) . ' ' . $dealer->status_reason ); ?></p>
	<?php endif; ?>
	<section class="amc-panel amc-decision">
		<form method="post"><?php Portal::action_fields( 'dealer_verify' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>"><button class="amds-btn amds-btn--secondary" type="submit"><?php echo amc_icon( 'shield', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $dealer->is_verified ? __( 'Remove verified badge', 'automotive-marketplace-core' ) : __( 'Grant verified badge', 'automotive-marketplace-core' ) ); ?></button></form>
		<?php if ( in_array( $dealer->status, array( 'suspended', 'cancelled', 'expired' ), true ) ) : ?>
			<form method="post" data-amc-confirm><?php Portal::action_fields( 'dealer_reactivate' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>"><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Reactivate', 'automotive-marketplace-core' ); ?></button></form>
		<?php endif; ?>
		<?php if ( 'suspended' !== $dealer->status && 'cancelled' !== $dealer->status ) : ?>
			<form method="post" class="amc-inline-form" data-amc-confirm><?php Portal::action_fields( 'dealer_suspend' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>"><input class="amds-input" name="reason" placeholder="<?php esc_attr_e( 'Reason', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Reason', 'automotive-marketplace-core' ); ?>" required><button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Suspend', 'automotive-marketplace-core' ); ?></button></form>
		<?php endif; ?>
		<?php if ( 'cancelled' !== $dealer->status ) : ?>
			<form method="post" data-amc-confirm><?php Portal::action_fields( 'dealer_cancel' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>"><button class="amds-btn amds-btn--ghost amc-danger" type="submit"><?php esc_html_e( 'Cancel account', 'automotive-marketplace-core' ); ?></button></form>
		<?php endif; ?>
	</section>
	<div class="amc-grid-2">
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Profile', 'automotive-marketplace-core' ); ?></h2></div>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Owner', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $user ? $user->display_name . ' · ' . $user->user_email : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Phone', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $dealer->phone ? $dealer->phone : '—' ); ?></dd></div>
				<div><dt>WhatsApp</dt><dd><?php echo esc_html( $dealer->whatsapp ? $dealer->whatsapp : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $dealer->website_url ? '<a href="' . esc_url( $dealer->website_url ) . '" target="_blank" rel="noopener">' . esc_html( $dealer->website_url ) . '</a>' : '—'; ?></dd></div>
				<div><dt><?php esc_html_e( 'Verified', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $dealer->is_verified ? esc_html__( 'Yes', 'automotive-marketplace-core' ) : esc_html__( 'No', 'automotive-marketplace-core' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Listings online', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( AMC\Dealer_Account::listing_count( $dealer->id ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Member since', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( Portal::date( $dealer->created_at ) ); ?></dd></div>
			</dl>
		</section>
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Membership', 'automotive-marketplace-core' ); ?></h2></div>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Application', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $app ? '<a href="' . esc_url( Portal::url( 'platform', 'applications', $app->id ) ) . '">#' . esc_html( $app->id ) . '</a> ' . Portal::badge( $app->status ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
				<div><dt><?php esc_html_e( 'Website setup', 'automotive-marketplace-core' ); ?></dt><dd>
					<?php if ( $job ) : ?>
						<a href="<?php echo esc_url( Portal::url( 'platform', 'websites', $job->id ) ); ?>"><?php esc_html_e( 'Open', 'automotive-marketplace-core' ); ?></a> <?php echo Portal::badge( $job->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<form method="post" class="amc-inline"><?php Portal::action_fields( 'job_create' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>"><button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Create setup job', 'automotive-marketplace-core' ); ?></button></form>
					<?php endif; ?>
				</dd></div>
			</dl>
		</section>
	</div>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Subscriptions', 'automotive-marketplace-core' ); ?></h2></div>
		<?php amc_get_template( 'parts/subscriptions-table', array( 'rows' => $subs, 'show_dealer' => false ) ); ?>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Payments', 'automotive-marketplace-core' ); ?></h2></div>
		<?php amc_get_template( 'parts/payments-table', array( 'rows' => AMC\Payments::for_dealer( $dealer->id ), 'show_dealer' => false ) ); ?>
	</section>
	<?php
	return;
endif;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
// phpcs:enable
$result = AMC\Memberships::dealers( array( 'status' => $status, 'q' => $q, 'limit' => 100 ) );
$counts = AMC\Memberships::dealer_counts();
Portal::page_head( __( 'Dealers', 'automotive-marketplace-core' ) );
?>
<nav class="amds-tabs amc-app__tabs">
	<a class="amds-tabs__item<?php echo '' === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( Portal::url( 'platform', 'dealers' ) ); ?>"><?php esc_html_e( 'All', 'automotive-marketplace-core' ); ?> (<?php echo esc_html( array_sum( $counts ) ); ?>)</a>
	<?php foreach ( AMC\Catalog::dealer_statuses() as $key ) : ?>
		<?php if ( ! empty( $counts[ $key ] ) ) : ?>
			<a class="amds-tabs__item<?php echo $key === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( Portal::url( 'platform', 'dealers', 0, array( 'status' => $key ) ) ); ?>"><?php echo esc_html( Portal::status_label( $key ) . ' (' . $counts[ $key ] . ')' ); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
<form class="amc-search" method="get" action="<?php echo esc_url( Portal::url( 'platform', 'dealers' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="amc_view" value="platform"><input type="hidden" name="amc_section" value="dealers"><?php endif; ?>
	<input class="amds-input" type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Name, city or email', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'automotive-marketplace-core' ); ?>">
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Search', 'automotive-marketplace-core' ); ?></button>
</form>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'City', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Valid until', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $result['items'] ) : ?>
					<tr><td colspan="5" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php $sub = AMC\Subscriptions::current_for_dealer( $row->id ); ?>
					<tr>
						<td><a href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $row->id ) ); ?>"><strong><?php echo esc_html( $row->business_name ); ?></strong></a><?php echo $row->is_verified ? ' <span class="amc-verified" title="' . esc_attr__( 'Verified', 'automotive-marketplace-core' ) . '">' . amc_icon( 'shield', 14 ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo esc_html( $row->city ); ?></td>
						<td><?php echo esc_html( $sub ? $sub->plan_name : '—' ); ?></td>
						<td><?php echo esc_html( $sub ? Portal::date( $sub->ends_at ) : '—' ); ?></td>
						<td><?php echo Portal::badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
