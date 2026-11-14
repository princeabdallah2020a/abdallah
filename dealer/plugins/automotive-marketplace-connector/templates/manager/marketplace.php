<?php
/**
 * CRM — Marketplace connection, synchronisation status and AI assistant number.
 *
 * @package AutomotiveMarketplaceConnector
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;
use ACX\Connection;
use ACX\Sync;

$owner     = current_user_can( 'adc_manage_settings' );
$connected = Connection::is_connected();
$remote    = Connection::remote();
$counts    = Sync::counts();
$errors    = $connected ? Sync::items( 'error', 50 ) : array();
$recent    = $connected ? Sync::items( '', 15 ) : array();

$status_labels = array(
	'active'      => __( 'Online', 'automotive-marketplace-connector' ),
	'reserved'    => __( 'Reserved', 'automotive-marketplace-connector' ),
	'sold'        => __( 'Sold', 'automotive-marketplace-connector' ),
	'rented'      => __( 'Rented', 'automotive-marketplace-connector' ),
	'unpublished' => __( 'Not shown', 'automotive-marketplace-connector' ),
);

Manager::head(
	__( 'Marketplace', 'automotive-marketplace-connector' ),
	__( 'Your vehicles with "Also publish on the AutoBuzy marketplace" ticked are shown on AutoBuzy automatically, and buyer requests arrive in your leads.', 'automotive-marketplace-connector' ),
	$connected ? '<span class="amds-badge amds-badge--success">' . esc_html__( 'Connected', 'automotive-marketplace-connector' ) . '</span>' : '<span class="amds-badge amds-badge--neutral">' . esc_html__( 'Not connected', 'automotive-marketplace-connector' ) . '</span>'
);
?>
<?php if ( ! $connected ) : ?>
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Connect this website', 'automotive-marketplace-connector' ); ?></h2></div>
		<ol>
			<li><?php esc_html_e( 'Your AutoBuzy membership must be active.', 'automotive-marketplace-connector' ); ?></li>
			<li><?php esc_html_e( 'Ask the AutoBuzy team for your connection code (it starts with AB1.).', 'automotive-marketplace-connector' ); ?></li>
			<li><?php esc_html_e( 'Paste it below. Your vehicles are then sent automatically.', 'automotive-marketplace-connector' ); ?></li>
		</ol>
		<?php if ( $owner ) : ?>
			<form class="amds-form" method="post" autocomplete="off">
				<?php Manager::action_fields( 'acx_connect' ); ?>
				<?php Manager::field( 'code', __( 'Connection code', 'automotive-marketplace-connector' ), '', 'textarea', array( 'attrs' => array( 'rows' => 3, 'required' => true, 'spellcheck' => 'false', 'placeholder' => 'AB1.…' ) ) ); ?>
				<div class="adc-actions"><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Connect', 'automotive-marketplace-connector' ); ?></button></div>
			</form>
		<?php else : ?>
			<p class="adc-muted"><?php esc_html_e( 'Only the owner of the account can connect the website.', 'automotive-marketplace-connector' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
	return;
endif;

$limits = $remote['limits'] ?? array();
$sub    = $remote['subscription'] ?? null;
$dealer = $remote['dealer'] ?? array();
?>
<div class="adc-kpis">
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Online on the marketplace', 'automotive-marketplace-connector' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( isset( $limits['vehicles_used'] ) ? $limits['vehicles_used'] . ' / ' . $limits['vehicles'] : '—' ); ?></strong><span class="adc-kpi__sub"><?php esc_html_e( 'Plan limit', 'automotive-marketplace-connector' ); ?></span></div>
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Up to date', 'automotive-marketplace-connector' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $counts['synced'] ) ); ?></strong></div>
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Being sent', 'automotive-marketplace-connector' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $counts['pending'] ) ); ?></strong></div>
	<div class="adc-kpi"><span class="adc-kpi__label"><?php esc_html_e( 'Refused', 'automotive-marketplace-connector' ); ?></span><strong class="adc-kpi__value"><?php echo esc_html( number_format_i18n( $counts['error'] ) ); ?></strong></div>
</div>

<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Your AutoBuzy account', 'automotive-marketplace-connector' ); ?></h2></div>
		<dl class="adc-dl">
			<div><dt><?php esc_html_e( 'Account', 'automotive-marketplace-connector' ); ?></dt><dd><?php echo esc_html( $dealer['name'] ?? '—' ); ?><?php if ( ! empty( $dealer['status'] ) && 'active' !== $dealer['status'] ) : ?> <span class="amds-badge amds-badge--warning"><?php esc_html_e( 'Inactive: listings are not shown', 'automotive-marketplace-connector' ); ?></span><?php endif; ?></dd></div>
			<div><dt><?php esc_html_e( 'Plan', 'automotive-marketplace-connector' ); ?></dt><dd><?php echo esc_html( $sub['plan'] ?? '—' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Valid until', 'automotive-marketplace-connector' ); ?></dt><dd><?php echo esc_html( ! empty( $sub['ends_at'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $sub['ends_at'] ) ) : '—' ); ?></dd></div>
			<?php if ( ! empty( $dealer['profile'] ) ) : ?>
				<div><dt><?php esc_html_e( 'Public page', 'automotive-marketplace-connector' ); ?></dt><dd><a class="amds-link" href="<?php echo esc_url( $dealer['profile'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View on AutoBuzy', 'automotive-marketplace-connector' ); ?></a></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Marketplace', 'automotive-marketplace-connector' ); ?></dt><dd><?php echo esc_html( preg_replace( '#^https?://#', '', Connection::get()['url'] ) ); ?></dd></div>
		</dl>
		<div class="adc-actions">
			<?php Manager::button( 'acx_check', 0, __( 'Test the connection', 'automotive-marketplace-connector' ) ); ?>
			<?php if ( $counts['error'] && current_user_can( 'adc_publish_vehicles' ) ) : ?>
				<?php Manager::button( 'acx_retry', 0, __( 'Retry refused vehicles', 'automotive-marketplace-connector' ) ); ?>
			<?php endif; ?>
			<?php if ( $owner ) : ?>
				<?php Manager::button( 'acx_resync', 0, __( 'Send everything again', 'automotive-marketplace-connector' ), 'amds-btn--ghost' ); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php echo adc_icon( 'sparkle', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'AI assistant on WhatsApp', 'automotive-marketplace-connector' ); ?></h2></div>
		<p><?php esc_html_e( 'Manage your CRM by writing or sending voice notes on WhatsApp, in French, Wolof or English: "add a lead", "what is available under 10 million?", "mark the Corolla as sold"… Sensitive actions always ask for your confirmation.', 'automotive-marketplace-connector' ); ?></p>
		<form class="amds-form" method="post">
			<?php Manager::action_fields( 'acx_whatsapp' ); ?>
			<?php Manager::field( 'phone', __( 'My WhatsApp number', 'automotive-marketplace-connector' ), ACX\Contacts::own(), 'tel', array( 'attrs' => array( 'placeholder' => '+221 77 123 45 67', 'maxlength' => 20 ) ) ); ?>
			<p class="amds-help"><?php esc_html_e( 'Only this number can act as you. The assistant has exactly your CRM permissions.', 'automotive-marketplace-connector' ); ?></p>
			<div class="adc-actions"><button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Save my number', 'automotive-marketplace-connector' ); ?></button></div>
		</form>
	</section>
</div>

<?php if ( $errors ) : ?>
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Vehicles refused by the marketplace', 'automotive-marketplace-connector' ); ?></h2></div>
		<div class="adc-table-wrap">
			<table class="amds-table">
				<thead><tr><th><?php esc_html_e( 'Vehicle', 'automotive-marketplace-connector' ); ?></th><th><?php esc_html_e( 'Reason', 'automotive-marketplace-connector' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $errors as $row ) : ?>
						<tr>
							<td><?php if ( $row->vehicle_id ) : ?><a href="<?php echo esc_url( adc_manager_url( 'vehicles', $row->vehicle_id ) ); ?>"><?php echo esc_html( $row->title ? $row->title : $row->uid ); ?></a><?php else : ?><?php echo esc_html( $row->uid ); ?><?php endif; ?></td>
							<td class="adc-danger"><?php echo esc_html( $row->last_error ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php endif; ?>

<div class="adc-grid-2">
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Latest synchronisations', 'automotive-marketplace-connector' ); ?></h2></div>
		<ul class="adc-list">
			<?php if ( ! $recent ) : ?>
				<li class="adc-muted"><?php esc_html_e( 'Tick "Also publish on the AutoBuzy marketplace" on a published vehicle to send it.', 'automotive-marketplace-connector' ); ?></li>
			<?php endif; ?>
			<?php foreach ( $recent as $row ) : ?>
				<li>
					<span><?php echo esc_html( $row->title ? $row->title : $row->uid ); ?></span>
					<?php if ( 'synced' === $row->state ) : ?>
						<?php if ( $row->remote_url && in_array( $row->remote_status, array( 'active', 'reserved' ), true ) ) : ?>
							<a class="amds-link" href="<?php echo esc_url( $row->remote_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $status_labels[ $row->remote_status ] ?? $row->remote_status ); ?></a>
						<?php else : ?>
							<span class="amds-badge amds-badge--neutral"><?php echo esc_html( $status_labels[ $row->remote_status ] ?? $row->remote_status ); ?></span>
						<?php endif; ?>
					<?php elseif ( 'pending' === $row->state ) : ?>
						<span class="amds-badge amds-badge--info"><?php esc_html_e( 'Sending…', 'automotive-marketplace-connector' ); ?></span>
					<?php else : ?>
						<span class="amds-badge amds-badge--danger"><?php esc_html_e( 'Refused', 'automotive-marketplace-connector' ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<section class="adc-panel">
		<div class="adc-panel__head"><h2><?php esc_html_e( 'Connection log', 'automotive-marketplace-connector' ); ?></h2></div>
		<ul class="adc-list">
			<?php foreach ( ACX\Database::recent_log( 15 ) as $entry ) : ?>
				<li><span class="<?php echo 'error' === $entry->level ? 'adc-danger' : ''; ?>"><?php echo esc_html( $entry->message ); ?></span><small class="adc-muted"><?php echo esc_html( adc_date( $entry->created_at, true ) ); ?></small></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>

<?php if ( $owner ) : ?>
	<details class="adc-panel adc-danger-zone">
		<summary><?php esc_html_e( 'Disconnect', 'automotive-marketplace-connector' ); ?></summary>
		<p><?php esc_html_e( 'The website stops updating the marketplace. To change the key, paste a new code after disconnecting.', 'automotive-marketplace-connector' ); ?></p>
		<?php Manager::button( 'acx_disconnect', 0, __( 'Disconnect this website', 'automotive-marketplace-connector' ), 'amds-btn--ghost adc-danger', array(), true ); ?>
	</details>
<?php endif; ?>
