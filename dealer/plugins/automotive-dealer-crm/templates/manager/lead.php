<?php
/**
 * CRM — lead detail.
 *
 * @var array $args { item: int }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Labels;
use ADC\Manager;

$lead = ADC\Leads::get( $args['item'] );
if ( ! $lead ) {
	Manager::head( __( 'Lead not found', 'automotive-dealer-crm' ), __( 'It does not exist or is assigned to someone else.', 'automotive-dealer-crm' ) );
	return;
}
$can     = current_user_can( 'adc_manage_leads' );
$actions = '';
if ( $lead->phone ) {
	$actions .= Manager::link_button( adc_whatsapp_url( $lead->phone, sprintf( /* translators: %s: name */ __( 'Hello %s,', 'automotive-dealer-crm' ), $lead->name ) ), 'WhatsApp', 'amds-btn--whatsapp', 'chat' );
	$actions .= Manager::link_button( 'tel:' . $lead->phone, __( 'Call', 'automotive-dealer-crm' ), 'amds-btn--secondary', 'phone' );
}
Manager::head( $lead->name, Labels::label( 'lead_sources', $lead->source ) . ' · ' . adc_date( $lead->created_at, true ), $actions );
?>
<div class="adc-stages">
	<?php foreach ( Labels::lead_statuses() as $key => $label ) : ?>
		<?php if ( 'lost' === $key ) { continue; } ?>
		<?php $is_current = $lead->status === $key; ?>
		<?php if ( $can && ! $is_current ) : ?>
			<form method="post" class="adc-stage">
				<?php Manager::action_fields( 'lead_status', $lead->id ); ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $key ); ?>">
				<button type="submit"><?php echo esc_html( $label ); ?></button>
			</form>
		<?php else : ?>
			<span class="adc-stage<?php echo $is_current ? ' is-current' : ''; ?>"><span><?php echo esc_html( $label ); ?></span></span>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
<?php if ( 'lost' === $lead->status ) : ?>
	<div class="amds-alert amds-alert--warning"><?php echo esc_html( __( 'Lost', 'automotive-dealer-crm' ) . ( $lead->lost_reason ? ' — ' . $lead->lost_reason : '' ) ); ?></div>
<?php endif; ?>

<div class="adc-form-layout">
	<div class="adc-form-layout__main">
		<?php if ( $can ) : ?>
			<form class="adc-panel amds-form" method="post">
				<?php Manager::action_fields( 'lead_note', $lead->id ); ?>
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Add to the history', 'automotive-dealer-crm' ); ?></h2></div>
				<div class="adc-note-kinds">
					<label class="adc-check"><input type="radio" name="kind" value="note" checked> <?php esc_html_e( 'Note', 'automotive-dealer-crm' ); ?></label>
					<label class="adc-check"><input type="radio" name="kind" value="call"> <?php esc_html_e( 'Call / message', 'automotive-dealer-crm' ); ?></label>
				</div>
				<?php Manager::field( 'body', __( 'What happened?', 'automotive-dealer-crm' ), '', 'textarea', array( 'attrs' => array( 'rows' => 3, 'required' => true ) ) ); ?>
				<div class="adc-inline-fields">
					<?php Manager::field( 'follow_up_at', __( 'Next follow-up (optional)', 'automotive-dealer-crm' ), '', 'datetime-local' ); ?>
					<button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Add', 'automotive-dealer-crm' ); ?></button>
				</div>
			</form>
		<?php endif; ?>

		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'History', 'automotive-dealer-crm' ); ?></h2></div>
			<?php if ( $lead->message ) : ?>
				<blockquote class="adc-quote"><?php echo esc_html( $lead->message ); ?></blockquote>
			<?php endif; ?>
			<ol class="adc-timeline">
				<?php foreach ( ADC\Leads::notes( $lead->id ) as $note ) : ?>
					<li class="adc-timeline__item adc-timeline__item--<?php echo esc_attr( $note->kind ); ?>">
						<span class="adc-timeline__dot"></span>
						<div><p><?php echo esc_html( $note->body ); ?></p><small><?php echo esc_html( Manager::user_name( $note->user_id ) . ' · ' . adc_date( $note->created_at, true ) ); ?></small></div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>

		<?php
		if ( $can ) {
			adc_template( 'manager/lead-form', array( 'item' => $lead->id, 'lead' => $lead ) );
		}
		?>
	</div>

	<aside class="adc-form-layout__side">
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Details', 'automotive-dealer-crm' ); ?></h2></div>
			<dl class="adc-dl">
				<div><dt><?php esc_html_e( 'Phone', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( $lead->phone ? $lead->phone : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Email', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( $lead->email ? $lead->email : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Vehicle', 'automotive-dealer-crm' ); ?></dt><dd><?php echo $lead->vehicle_id ? '<a href="' . esc_url( adc_manager_url( 'vehicles', $lead->vehicle_id ) ) . '">' . esc_html( $lead->vehicle_title ) . '</a>' : '—'; ?></dd></div>
				<div><dt><?php esc_html_e( 'Budget', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( $lead->budget_amount ? adc_money( $lead->budget_amount ) : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Follow-up', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( adc_date( $lead->follow_up_at, true ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Assigned to', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( Manager::user_name( $lead->assigned_to ) ); ?></dd></div>
			</dl>
			<?php if ( current_user_can( 'adc_view_all_leads' ) && $can ) : ?>
				<form method="post" class="adc-inline-fields">
					<?php Manager::action_fields( 'lead_assign', $lead->id ); ?>
					<select class="amds-select" name="assigned_to" aria-label="<?php esc_attr_e( 'Assigned to', 'automotive-dealer-crm' ); ?>">
						<option value="0"><?php esc_html_e( 'Not assigned', 'automotive-dealer-crm' ); ?></option>
						<?php foreach ( ADC\Team::members_with( 'adc_manage_leads' ) as $member ) : ?>
							<option value="<?php echo esc_attr( $member->ID ); ?>" <?php selected( (int) $lead->assigned_to, $member->ID ); ?>><?php echo esc_html( $member->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Assign', 'automotive-dealer-crm' ); ?></button>
				</form>
			<?php endif; ?>
		</section>

		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Next steps', 'automotive-dealer-crm' ); ?></h2></div>
			<div class="adc-stack">
				<?php if ( $lead->customer_id ) : ?>
					<a class="amds-btn amds-btn--secondary amds-btn--block" href="<?php echo esc_url( adc_manager_url( 'customers', $lead->customer_id ) ); ?>"><?php esc_html_e( 'Open the customer record', 'automotive-dealer-crm' ); ?></a>
				<?php elseif ( current_user_can( 'adc_manage_customers' ) ) : ?>
					<?php Manager::button( 'lead_convert', $lead->id, __( 'Create the customer record', 'automotive-dealer-crm' ), 'amds-btn--secondary amds-btn--block' ); ?>
				<?php endif; ?>
				<?php if ( 'rent' !== $lead->interest && current_user_can( 'adc_manage_sales' ) ) : ?>
					<a class="amds-btn amds-btn--primary amds-btn--block" href="<?php echo esc_url( adc_manager_url( 'sales', 'new', array( 'lead' => $lead->id, 'vehicle' => $lead->vehicle_id ) ) ); ?>"><?php esc_html_e( 'Record the sale', 'automotive-dealer-crm' ); ?></a>
				<?php endif; ?>
				<?php if ( 'rent' === $lead->interest && current_user_can( 'adc_manage_rentals' ) ) : ?>
					<a class="amds-btn amds-btn--primary amds-btn--block" href="<?php echo esc_url( adc_manager_url( 'rentals', 'new', array( 'lead' => $lead->id, 'vehicle' => $lead->vehicle_id ) ) ); ?>"><?php esc_html_e( 'Create the rental', 'automotive-dealer-crm' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( $can && ! in_array( $lead->status, array( 'won', 'lost' ), true ) ) : ?>
				<form method="post" class="adc-lost">
					<?php Manager::action_fields( 'lead_status', $lead->id ); ?>
					<input type="hidden" name="status" value="lost">
					<input class="amds-input" name="reason" placeholder="<?php esc_attr_e( 'Reason (price, bought elsewhere…)', 'automotive-dealer-crm' ); ?>" aria-label="<?php esc_attr_e( 'Reason', 'automotive-dealer-crm' ); ?>">
					<button class="amds-btn amds-btn--ghost adc-danger" type="submit"><?php esc_html_e( 'Mark as lost', 'automotive-dealer-crm' ); ?></button>
				</form>
			<?php endif; ?>
		</section>

		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Appointments', 'automotive-dealer-crm' ); ?></h2></div>
			<ul class="adc-list">
				<?php foreach ( ADC\Appointments::upcoming( 20, $lead->id ) as $appointment ) : ?>
					<li>
						<span><strong><?php echo esc_html( adc_date( $appointment->starts_at, true ) ); ?></strong><small><?php echo esc_html( $appointment->title ); ?></small></span>
						<?php if ( 'scheduled' === $appointment->status && $can ) : ?>
							<?php Manager::button( 'appointment_status', $appointment->id, __( 'Done', 'automotive-dealer-crm' ), 'amds-btn--ghost adc-btn-xs', array( 'status' => 'done' ) ); ?>
						<?php else : ?>
							<?php echo adc_badge( $appointment->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $can ) : ?>
				<form class="amds-form" method="post">
					<?php Manager::action_fields( 'appointment_create' ); ?>
					<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead->id ); ?>">
					<?php
					Manager::field( 'starts_at', __( 'Date and time', 'automotive-dealer-crm' ), '', 'datetime-local', array( 'attrs' => array( 'required' => true ) ) );
					Manager::field( 'title', __( 'Purpose', 'automotive-dealer-crm' ), '', 'text', array( 'attrs' => array( 'placeholder' => __( 'Test drive, visit, delivery…', 'automotive-dealer-crm' ) ) ) );
					?>
					<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Schedule', 'automotive-dealer-crm' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	</aside>
</div>
