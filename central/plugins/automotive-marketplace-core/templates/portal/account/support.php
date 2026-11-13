<?php
/**
 * Dealer space — support conversations.
 *
 * @var array $args { dealer: object, item: int }
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

$dealer = $args['dealer'];
$ticket = $args['item'] ? AMC\Support::get( $args['item'] ) : null;
if ( $ticket && (int) $ticket->dealer_id !== (int) $dealer->id ) {
	$ticket = null; // Never show another dealer's conversation.
}

if ( $ticket ) :
	Portal::page_head( $ticket->subject, AMC\Support::categories()[ $ticket->category ] ?? '', Portal::badge( $ticket->status ) );
	amc_get_template( 'parts/ticket-thread', array( 'ticket' => $ticket, 'staff' => false ) );
	if ( 'closed' !== $ticket->status ) :
		?>
		<form class="amc-panel amds-form" method="post">
			<?php Portal::action_fields( 'ticket_reply' ); ?>
			<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket->id ); ?>">
			<div class="amds-field"><label class="amds-label" for="amc-t-msg"><?php esc_html_e( 'Your reply', 'automotive-marketplace-core' ); ?></label><textarea class="amds-textarea" id="amc-t-msg" name="message" rows="4" required maxlength="5000"></textarea></div>
			<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Send', 'automotive-marketplace-core' ); ?></button></div>
		</form>
	<?php endif; ?>
	<p><a class="amds-link" href="<?php echo esc_url( Portal::url( 'account', 'support' ) ); ?>">← <?php esc_html_e( 'All conversations', 'automotive-marketplace-core' ); ?></a></p>
	<?php
	return;
endif;

$tickets = AMC\Support::for_dealer( $dealer->id );
Portal::page_head( __( 'Support', 'automotive-marketplace-core' ), __( 'Our team answers here and by email.', 'automotive-marketplace-core' ) );
?>
<div class="amc-grid-2">
	<section class="amc-panel">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'Your conversations', 'automotive-marketplace-core' ); ?></h2></div>
		<?php if ( ! $tickets ) : ?>
			<p class="amc-muted"><?php esc_html_e( 'No conversation yet.', 'automotive-marketplace-core' ); ?></p>
		<?php else : ?>
			<ul class="amc-files">
				<?php foreach ( $tickets as $item ) : ?>
					<li>
						<?php echo amc_icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><a href="<?php echo esc_url( Portal::url( 'account', 'support', $item->id ) ); ?>"><strong><?php echo esc_html( $item->subject ); ?></strong></a><small><?php echo esc_html( Portal::date( $item->updated_at, true ) ); ?></small></span>
						<?php echo Portal::badge( $item->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<form class="amc-panel amds-form" method="post">
		<div class="amc-panel__head"><h2><?php esc_html_e( 'New request', 'automotive-marketplace-core' ); ?></h2></div>
		<?php Portal::action_fields( 'ticket_new' ); ?>
		<div class="amds-field">
			<label class="amds-label" for="amc-t-cat"><?php esc_html_e( 'Topic', 'automotive-marketplace-core' ); ?></label>
			<select class="amds-select" id="amc-t-cat" name="category">
				<?php foreach ( AMC\Support::categories() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="amds-field"><label class="amds-label" for="amc-t-sub"><?php esc_html_e( 'Subject', 'automotive-marketplace-core' ); ?></label><input class="amds-input" type="text" id="amc-t-sub" name="subject" required maxlength="190"></div>
		<div class="amds-field"><label class="amds-label" for="amc-t-body"><?php esc_html_e( 'Message', 'automotive-marketplace-core' ); ?></label><textarea class="amds-textarea" id="amc-t-body" name="message" rows="5" required maxlength="5000"></textarea></div>
		<div><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Send', 'automotive-marketplace-core' ); ?></button></div>
	</form>
</div>
