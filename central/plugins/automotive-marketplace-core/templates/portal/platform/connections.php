<?php
/**
 * Platform admin — dealer website connections (API credentials, webhooks, sync log).
 *
 * @var array $args { item: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;
use AMC\Credentials;

$health_labels = array(
	'none'  => array( 'neutral', __( 'Not connected', 'automotive-marketplace-core' ) ),
	'ok'    => array( 'success', __( 'Healthy', 'automotive-marketplace-core' ) ),
	'stale' => array( 'warning', __( 'No recent contact', 'automotive-marketplace-core' ) ),
	'error' => array( 'danger', __( 'Errors', 'automotive-marketplace-core' ) ),
);
$health_badge  = static function ( $health ) use ( $health_labels ) {
	return '<span class="amds-badge amds-badge--' . esc_attr( $health_labels[ $health ][0] ) . '">' . esc_html( $health_labels[ $health ][1] ) . '</span>';
};

if ( $args['item'] ) :
	$dealer = AMC\Memberships::get_dealer( $args['item'] );
	if ( ! $dealer ) {
		echo '<div class="amds-alert amds-alert--error">' . esc_html__( 'Dealer not found.', 'automotive-marketplace-core' ) . '</div>';
		return;
	}
	$credential = Credentials::active_for_dealer( $dealer->id );
	$health     = Credentials::health( $credential );
	$shown      = get_transient( 'amc_conn_code_' . get_current_user_id() );
	if ( $shown && (int) $shown['dealer'] === (int) $dealer->id ) {
		delete_transient( 'amc_conn_code_' . get_current_user_id() );
	} else {
		$shown = null;
	}
	Portal::page_head( $dealer->business_name, __( 'Connection between the dealer website (CRM) and the marketplace.', 'automotive-marketplace-core' ), $health_badge( $health ) );
	?>
	<?php if ( $shown ) : ?>
		<section class="amc-panel amc-conn-code">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Connection code', 'automotive-marketplace-core' ); ?></h2></div>
			<p><?php esc_html_e( 'Send this code to the dealer through a secure channel. In the dealer CRM: Marketplace → paste the code → Connect. It contains a secret key and is shown only once.', 'automotive-marketplace-core' ); ?></p>
			<textarea class="amds-textarea amc-conn-code__value" rows="4" readonly onclick="this.select()"><?php echo esc_textarea( $shown['code'] ); ?></textarea>
		</section>
	<?php endif; ?>
	<div class="amc-grid-2">
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></h2></div>
			<?php if ( $credential ) : ?>
				<dl class="amc-dl">
					<div><dt><?php esc_html_e( 'Key', 'automotive-marketplace-core' ); ?></dt><dd><code><?php echo esc_html( $credential->key_id ); ?></code></dd></div>
					<div><dt><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></dt><dd><?php echo $credential->site_url ? '<a class="amds-link" href="' . esc_url( $credential->site_url ) . '" target="_blank" rel="noopener">' . esc_html( $credential->site_url ) . '</a>' : '—'; ?></dd></div>
					<div><dt><?php esc_html_e( 'Connector version', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $credential->connector_version ? $credential->connector_version : '—' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Connected on', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $credential->last_handshake_at ? Portal::date( $credential->last_handshake_at, true ) : __( 'Waiting for the dealer', 'automotive-marketplace-core' ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Last successful call', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $credential->last_success_at ? Portal::date( $credential->last_success_at, true ) : '—' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Last heartbeat', 'automotive-marketplace-core' ); ?></dt><dd><?php echo esc_html( $credential->last_heartbeat_at ? Portal::date( $credential->last_heartbeat_at, true ) : '—' ); ?></dd></div>
					<?php if ( $credential->last_error ) : ?>
						<div><dt><?php esc_html_e( 'Last error', 'automotive-marketplace-core' ); ?></dt><dd class="amc-text-danger"><?php echo esc_html( $credential->last_error ); ?></dd></div>
					<?php endif; ?>
				</dl>
			<?php else : ?>
				<p class="amc-muted"><?php esc_html_e( 'No connection code has been generated for this dealer yet.', 'automotive-marketplace-core' ); ?></p>
			<?php endif; ?>
		</section>
		<section class="amc-panel">
			<div class="amc-panel__head"><h2><?php esc_html_e( 'Actions', 'automotive-marketplace-core' ); ?></h2></div>
			<div class="amc-stack">
				<form method="post">
					<?php Portal::action_fields( 'conn_issue' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>">
					<button class="amds-btn amds-btn--primary" type="submit"<?php echo $credential ? ' onclick="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'The current key stops working immediately. Continue?', 'automotive-marketplace-core' ) . '"' : ''; ?>><?php echo esc_html( $credential ? __( 'Rotate the key (new code)', 'automotive-marketplace-core' ) : __( 'Generate a connection code', 'automotive-marketplace-core' ) ); ?></button>
				</form>
				<?php if ( $credential ) : ?>
					<form method="post">
						<?php Portal::action_fields( 'conn_resync' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>">
						<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Request a full resynchronisation', 'automotive-marketplace-core' ); ?></button>
					</form>
					<form method="post" onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e( 'Revoke this connection? The dealer will not be able to publish until a new code is generated.', 'automotive-marketplace-core' ); ?>">
						<?php Portal::action_fields( 'conn_revoke' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $dealer->id ); ?>">
						<button class="amds-btn amds-btn--ghost amc-text-danger" type="submit"><?php esc_html_e( 'Revoke the connection', 'automotive-marketplace-core' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<p class="amc-muted"><?php esc_html_e( 'Listings are published only while the dealer account is active with a valid subscription, within the plan limit.', 'automotive-marketplace-core' ); ?></p>
		</section>
	</div>
	<p><a class="amds-link" href="<?php echo esc_url( Portal::url( 'platform', 'connections' ) ); ?>">← <?php esc_html_e( 'All connections', 'automotive-marketplace-core' ); ?></a></p>
	<?php
	return;
endif;

$rows       = Credentials::overview();
$deliveries = AMC\Webhooks::recent( 30 );
$log        = AMC\Webhooks::sync_log( 30 );
$leads      = AMC\Marketplace_Leads::stats();
Portal::page_head( __( 'Connections', 'automotive-marketplace-core' ), __( 'Dealer websites publish their vehicles on the marketplace through a signed API, and receive marketplace requests in their CRM.', 'automotive-marketplace-core' ) );
?>
<div class="amc-kpis">
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Requests delivered to CRMs (30 days)', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $leads['delivered'] ?? 0 ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Sent by email (30 days)', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $leads['emailed'] ?? 0 ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Waiting for delivery', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $leads['pending'] ?? 0 ) ); ?></strong></div>
	<div class="amc-kpi"><span class="amc-kpi__label"><?php esc_html_e( 'Undeliverable', 'automotive-marketplace-core' ); ?></span><strong class="amc-kpi__value"><?php echo esc_html( number_format_i18n( $leads['undeliverable'] ?? 0 ) ); ?></strong></div>
</div>
<section class="amc-panel">
	<div class="amc-panel__head"><h2><?php esc_html_e( 'Dealer websites', 'automotive-marketplace-core' ); ?></h2></div>
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Website', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Last successful call', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Connection', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Account', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Portal::url( 'platform', 'connections', $row->dealer_id ) ); ?>"><strong><?php echo esc_html( $row->business_name ); ?></strong></a></td>
						<td><?php echo esc_html( $row->site_url ? preg_replace( '#^https?://#', '', $row->site_url ) : '—' ); ?></td>
						<td><?php echo esc_html( $row->last_success_at ? Portal::date( $row->last_success_at, true ) : '—' ); ?></td>
						<td><?php echo $health_badge( Credentials::health( $row->key_id ? $row : null ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo Portal::badge( $row->dealer_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Webhooks sent to dealers', 'automotive-marketplace-core' ); ?></h2></div>
		<div class="amc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Event', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th><th></th></tr></thead>
				<tbody>
					<?php if ( ! $deliveries ) : ?>
						<tr><td colspan="4" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $deliveries as $d ) : ?>
						<tr>
							<td><code><?php echo esc_html( $d->event ); ?></code><br><small class="amc-muted"><?php echo esc_html( Portal::date( $d->created_at, true ) ); ?></small></td>
							<td><?php echo esc_html( $d->business_name ); ?></td>
							<td><?php echo Portal::badge( 'delivered' === $d->status ? 'active' : ( 'failed' === $d->status ? 'rejected' : 'pending' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php if ( $d->last_error ) : ?><br><small class="amc-muted"><?php echo esc_html( $d->attempts . ' × ' . $d->last_error ); ?></small><?php endif; ?></td>
							<td>
								<?php if ( 'delivered' !== $d->status ) : ?>
									<form method="post"><?php Portal::action_fields( 'webhook_retry' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $d->id ); ?>"><button class="amds-btn amds-btn--ghost" type="submit"><?php esc_html_e( 'Retry', 'automotive-marketplace-core' ); ?></button></form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'API calls from dealers', 'automotive-marketplace-core' ); ?></h2></div>
		<div class="amc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Call', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Result', 'automotive-marketplace-core' ); ?></th></tr></thead>
				<tbody>
					<?php if ( ! $log ) : ?>
						<tr><td colspan="3" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $log as $e ) : ?>
						<tr>
							<td><code><?php echo esc_html( $e->method . ' ' . preg_replace( '#^/marketplace/v1/sync#', '', $e->route ) ); ?></code><br><small class="amc-muted"><?php echo esc_html( Portal::date( $e->created_at, true ) ); ?></small></td>
							<td><?php echo esc_html( $e->business_name ); ?></td>
							<td><span class="amds-badge amds-badge--<?php echo esc_attr( $e->status_code < 300 ? 'success' : ( $e->status_code < 500 ? 'warning' : 'danger' ) ); ?>"><?php echo esc_html( $e->status_code ); ?></span><?php if ( $e->error ) : ?><br><small class="amc-muted"><?php echo esc_html( $e->error ); ?></small><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
