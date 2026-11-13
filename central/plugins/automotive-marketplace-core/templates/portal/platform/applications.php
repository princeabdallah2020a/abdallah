<?php
/**
 * Platform admin — dealer applications.
 *
 * @var array $args { item: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$app = $args['item'] ? AMC\Memberships::get_application( $args['item'] ) : null;

if ( $app ) :
	$dealer  = AMC\Memberships::get_dealer( $app->dealer_id );
	$user    = $app->user_id ? get_userdata( (int) $app->user_id ) : null;
	$open    = AMC\Subscriptions::open_for_dealer( $app->dealer_id );
	$current = AMC\Subscriptions::current_for_dealer( $app->dealer_id );
	$pays    = AMC\Payments::for_dealer( $app->dealer_id );
	$docs    = AMC\Documents::for_dealer( $app->dealer_id );
	$active  = in_array( $app->status, array( 'submitted', 'under_review' ), true );
	Portal::page_head( $app->business_name, sprintf( '#%d · %s', $app->id, Portal::date( $app->created_at, true ) ), Portal::badge( $app->status ) );
	?>
	<?php if ( $active ) : ?>
		<section class="amc-panel amc-decision">
			<?php if ( 'submitted' === $app->status ) : ?>
				<form method="post"><?php Portal::action_fields( 'app_review' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $app->id ); ?>"><button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Start review', 'automotive-marketplace-core' ); ?></button></form>
			<?php endif; ?>
			<form method="post" data-amc-confirm><?php Portal::action_fields( 'app_approve' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $app->id ); ?>"><button class="amds-btn amds-btn--accent" type="submit"><?php echo amc_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Approve', 'automotive-marketplace-core' ); ?></button></form>
			<form method="post" class="amc-inline-form" data-amc-confirm><?php Portal::action_fields( 'app_reject' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $app->id ); ?>">
				<label class="screen-reader-text" for="amc-reject-reason"><?php esc_html_e( 'Reason (sent to the dealer)', 'automotive-marketplace-core' ); ?></label>
				<input class="amds-input" id="amc-reject-reason" name="reason" placeholder="<?php esc_attr_e( 'Reason (sent to the dealer)', 'automotive-marketplace-core' ); ?>" required>
				<button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Reject', 'automotive-marketplace-core' ); ?></button>
			</form>
		</section>
	<?php elseif ( 'rejected' === $app->status && $app->decision_reason ) : ?>
		<p class="amds-alert amds-alert--warning"><?php echo esc_html( __( 'Reason:', 'automotive-marketplace-core' ) . ' ' . $app->decision_reason ); ?></p>
	<?php endif; ?>

	<div class="amc-grid-2">
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Company', 'automotive-marketplace-core' ); ?></h2></div>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Trading name', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $dealer->business_name ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Legal name', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $app->legal_name ? $app->legal_name : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'RCCM number', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $app->registration_number ? $app->registration_number : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'NINEA', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $app->tax_id ? $app->tax_id : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Activity', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( AMC\Memberships::activities()[ $app->activity ] ?? $app->activity ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Vehicles in stock', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( AMC\Memberships::fleet_sizes()[ $app->fleet_size ] ?? '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Address', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( trim( $dealer->address . ', ' . $dealer->city, ', ' ) ); ?></dd></div>
			</dl>
			<?php if ( $dealer->description ) : ?><p class="amc-muted"><?php echo esc_html( $dealer->description ); ?></p><?php endif; ?>
		</section>
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Contact', 'automotive-marketplace-core' ); ?></h2></div>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Name', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $app->contact_name ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Email', 'automotive-marketplace-core' ); ?></dt><dd><a href="<?php echo esc_url( 'mailto:' . ( $user ? $user->user_email : $dealer->contact_email ) ); ?>"><?php echo esc_html( $user ? $user->user_email : $dealer->contact_email ); ?></a></dd></div>
				<div><dt><?php esc_html_e( 'Phone', 'automotive-marketplace-core' ); ?></dt><dd><a href="<?php echo esc_url( 'tel:' . $app->contact_phone ); ?>"><?php echo esc_html( $app->contact_phone ); ?></a></dd></div>
				<div><dt>WhatsApp</dt><dd><?php echo $dealer->whatsapp ? '<a href="' . esc_url( amc_whatsapp_url( $dealer->whatsapp ) ) . '" target="_blank" rel="noopener">' . esc_html( $dealer->whatsapp ) . '</a>' : '—'; ?></dd></div>
			</dl>
		</section>
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Plan and payment', 'automotive-marketplace-core' ); ?></h2></div>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Plan requested', 'automotive-marketplace-core' ); ?></dt><dd>
					<?php
					/* translators: 1: plan, 2: months */
					echo esc_html( sprintf( _n( '%1$s · %2$d month', '%1$s · %2$d months', $app->duration_months, 'automotive-marketplace-core' ), $app->plan_name, $app->duration_months ) );
					?>
				</dd></div>
				<div><dt><?php esc_html_e( 'Order', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $current ? Portal::badge( 'active' ) : ( $open ? esc_html( amc_format_money( $open->amount, $open->currency ) ) . ' ' . Portal::badge( $open->status ) : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
				<?php foreach ( $pays as $pay ) : ?>
					<div><dt><?php echo esc_html( AMC\Payments::method_label( $pay->method ) ); ?></dt><dd><?php echo Portal::badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="<?php echo esc_url( Portal::url( 'platform', 'payments' ) ); ?>"><?php esc_html_e( 'Payments', 'automotive-marketplace-core' ); ?></a></dd></div>
				<?php endforeach; ?>
			</dl>
		</section>
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></h2></div>
			<dl class="amc-dl">
				<div><dt><?php esc_html_e( 'Option', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( AMC\Memberships::website_options()[ $app->website_option ] ?? $app->website_option ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Domain', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $app->domain ? $app->domain : '—' ); ?></dd></div>
			</dl>
			<?php if ( $app->notes ) : ?><p class="amc-muted"><?php echo esc_html( $app->notes ); ?></p><?php endif; ?>
		</section>
	</div>

	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Documents', 'automotive-marketplace-core' ); ?></h2></div>
		<?php if ( ! $docs ) : ?>
			<p class="amc-muted"><?php esc_html_e( 'No document sent.', 'automotive-marketplace-core' ); ?></p>
		<?php else : ?>
			<ul class="amc-files">
				<?php foreach ( $docs as $doc ) : ?>
					<li><?php echo amc_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><strong><?php echo esc_html( AMC\Documents::type_label( $doc->type ) ); ?></strong><small><?php echo esc_html( $doc->original_name . ' · ' . size_format( $doc->size ) . ' · ' . Portal::date( $doc->created_at ) ); ?></small></span><a class="amds-link" href="<?php echo esc_url( AMC\Documents::url( $doc->id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open', 'automotive-marketplace-core' ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<p><a class="amds-link" href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $app->dealer_id ) ); ?>"><?php esc_html_e( 'Open the dealer record', 'automotive-marketplace-core' ); ?> →</a></p>
	<?php
	return;
endif;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
$counts = AMC\Memberships::application_counts();
$rows   = 'pending' === $status
	? array_merge( AMC\Memberships::applications( 'submitted' ), AMC\Memberships::applications( 'under_review' ) )
	: AMC\Memberships::applications( 'all' === $status ? '' : $status );
Portal::page_head( __( 'Applications', 'automotive-marketplace-core' ) );
$tabs = array(
	'pending'  => __( 'To review', 'automotive-marketplace-core' ) . ' (' . ( ( $counts['submitted'] ?? 0 ) + ( $counts['under_review'] ?? 0 ) ) . ')',
	'approved' => __( 'Approved', 'automotive-marketplace-core' ),
	'rejected' => __( 'Rejected', 'automotive-marketplace-core' ),
	'all'      => __( 'All', 'automotive-marketplace-core' ),
);
?>
<nav class="amds-tabs amc-app__tabs">
	<?php foreach ( $tabs as $key => $label ) : ?>
		<a class="amds-tabs__item<?php echo $key === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( Portal::url( 'platform', 'applications', 0, array( 'status' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'City', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Plan', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Received', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Portal::url( 'platform', 'applications', $row->id ) ); ?>"><strong><?php echo esc_html( $row->business_name ); ?></strong></a></td>
						<td><?php echo esc_html( $row->city ); ?></td>
						<td><?php echo esc_html( $row->plan_name . ' · ' . $row->duration_months . ' m' ); ?></td>
						<td><?php echo esc_html( Portal::date( $row->created_at ) ); ?></td>
						<td><?php echo Portal::badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
