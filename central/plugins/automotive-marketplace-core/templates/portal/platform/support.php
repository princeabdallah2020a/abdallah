<?php
/**
 * Platform admin — support conversations.
 *
 * @var array $args { item: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$ticket = $args['item'] ? AMC\Support::get( $args['item'] ) : null;

if ( $ticket ) :
	Portal::page_head( $ticket->subject, $ticket->business_name . ' · ' . ( AMC\Support::categories()[ $ticket->category ] ?? '' ), Portal::badge( $ticket->status ) );
	amc_get_template( 'parts/ticket-thread', array( 'ticket' => $ticket, 'staff' => true ) );
	?>
	<form class="amc-panel amds-form" method="post">
		<?php Portal::action_fields( 'ticket_reply' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $ticket->id ); ?>">
		<div class="amds-field"><label class="amds-label" for="amc-st-msg"><?php esc_html_e( 'Reply to the dealer', 'automotive-marketplace-core' ); ?></label><textarea class="amds-textarea" id="amc-st-msg" name="message" rows="5" required maxlength="5000"></textarea></div>
		<label class="amc-check"><input type="checkbox" name="close" value="1"> <?php esc_html_e( 'Close the conversation after sending', 'automotive-marketplace-core' ); ?></label>
		<div class="amc-actions">
			<button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Send', 'automotive-marketplace-core' ); ?></button>
		</div>
	</form>
	<form method="post" class="amc-inline">
		<?php Portal::action_fields( 'ticket_status' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $ticket->id ); ?>">
		<input type="hidden" name="status" value="<?php echo 'closed' === $ticket->status ? 'open' : 'closed'; ?>">
		<button class="amds-btn amds-btn--ghost" type="submit"><?php echo esc_html( 'closed' === $ticket->status ? __( 'Reopen', 'automotive-marketplace-core' ) : __( 'Close without replying', 'automotive-marketplace-core' ) ); ?></button>
	</form>
	<p><a class="amds-link" href="<?php echo esc_url( Portal::url( 'platform', 'dealers', $ticket->dealer_id ) ); ?>"><?php esc_html_e( 'Open the dealer record', 'automotive-marketplace-core' ); ?> →</a></p>
	<?php
	return;
endif;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$rows   = AMC\Support::list( $status );
Portal::page_head( __( 'Support', 'automotive-marketplace-core' ) );
?>
<nav class="amds-tabs amc-app__tabs">
	<?php foreach ( array( '' => __( 'All', 'automotive-marketplace-core' ), 'open' => __( 'Waiting for us', 'automotive-marketplace-core' ), 'answered' => __( 'Answered', 'automotive-marketplace-core' ), 'closed' => __( 'Closed', 'automotive-marketplace-core' ) ) as $key => $label ) : ?>
		<a class="amds-tabs__item<?php echo $key === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( Portal::url( 'platform', 'support', 0, $key ? array( 'status' => $key ) : array() ) ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Subject', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Dealership', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Topic', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Updated', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Status', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5" class="amc-muted"><?php esc_html_e( 'Nothing here.', 'automotive-marketplace-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Portal::url( 'platform', 'support', $row->id ) ); ?>"><strong><?php echo esc_html( $row->subject ); ?></strong></a></td>
						<td><?php echo esc_html( $row->business_name ); ?></td>
						<td><?php echo esc_html( AMC\Support::categories()[ $row->category ] ?? $row->category ); ?></td>
						<td><?php echo esc_html( Portal::date( $row->updated_at, true ) ); ?></td>
						<td><?php echo Portal::badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
