<?php
/**
 * CRM — customer record: contact, purchases, rentals, leads, documents.
 *
 * @var array $args { item: int }
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;

$c = ADC\Customers::get( $args['item'] );
if ( ! $c ) {
	Manager::head( __( 'Customer not found', 'automotive-dealer-crm' ) );
	return;
}
$sales   = ADC\Sales::search( array( 'customer_id' => $c->id ) );
$rentals = ADC\Rentals::search( array( 'customer_id' => $c->id ) );
$actions = '';
if ( $c->phone ) {
	$actions .= Manager::link_button( adc_whatsapp_url( $c->phone ), 'WhatsApp', 'amds-btn--whatsapp', 'chat' );
}
if ( current_user_can( 'adc_manage_sales' ) ) {
	$actions .= Manager::link_button( adc_manager_url( 'sales', 'new', array( 'customer' => $c->id ) ), __( 'New sale', 'automotive-dealer-crm' ), 'amds-btn--secondary' );
}
if ( current_user_can( 'adc_manage_rentals' ) ) {
	$actions .= Manager::link_button( adc_manager_url( 'rentals', 'new', array( 'customer' => $c->id ) ), __( 'New rental', 'automotive-dealer-crm' ), 'amds-btn--secondary' );
}
Manager::head( $c->name, trim( $c->phone . ' · ' . $c->email, ' ·' ), $actions );
?>
<div class="adc-form-layout">
	<div class="adc-form-layout__main">
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Purchases', 'automotive-dealer-crm' ); ?></h2></div>
			<ul class="adc-list">
				<?php if ( ! $sales ) : ?><li class="adc-muted"><?php esc_html_e( 'No purchase yet.', 'automotive-dealer-crm' ); ?></li><?php endif; ?>
				<?php foreach ( $sales as $s ) : ?>
					<li><span><a href="<?php echo esc_url( adc_manager_url( 'sales', $s->id ) ); ?>"><strong><?php echo esc_html( $s->vehicle_title ); ?></strong></a><small><?php echo esc_html( adc_date( $s->sold_on ) . ' · ' . adc_money( $s->sale_price, $s->currency ) ); ?></small></span><?php echo adc_badge( $s->status, 'sale_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Rentals', 'automotive-dealer-crm' ); ?></h2></div>
			<ul class="adc-list">
				<?php if ( ! $rentals ) : ?><li class="adc-muted"><?php esc_html_e( 'No rental yet.', 'automotive-dealer-crm' ); ?></li><?php endif; ?>
				<?php foreach ( $rentals as $r ) : ?>
					<li><span><a href="<?php echo esc_url( adc_manager_url( 'rentals', $r->id ) ); ?>"><strong><?php echo esc_html( $r->vehicle_title ); ?></strong></a><small><?php echo esc_html( adc_date( $r->starts_at ) . ' → ' . adc_date( $r->ends_at ) . ' · ' . adc_money( $r->total_amount, $r->currency ) ); ?></small></span><?php echo adc_badge( $r->status, 'rental_statuses' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php adc_template( 'manager/customer-form', array( 'item' => $c->id, 'customer' => $c ) ); ?>
	</div>
	<aside class="adc-form-layout__side">
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Details', 'automotive-dealer-crm' ); ?></h2></div>
			<dl class="adc-dl">
				<div><dt><?php esc_html_e( 'Address', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( trim( $c->address . ', ' . $c->city, ', ' ) ? trim( $c->address . ', ' . $c->city, ', ' ) : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'ID / licence', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( $c->id_number ? $c->id_number : '—' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Customer since', 'automotive-dealer-crm' ); ?></dt><dd><?php echo esc_html( adc_date( $c->created_at ) ); ?></dd></div>
			</dl>
			<?php if ( $c->notes ) : ?><p class="adc-muted"><?php echo esc_html( $c->notes ); ?></p><?php endif; ?>
		</section>
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Documents', 'automotive-dealer-crm' ); ?></h2><?php echo adc_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<ul class="adc-list">
				<?php foreach ( ADC\Files::for_customer( $c->id ) as $file ) : ?>
					<li><span><a href="<?php echo esc_url( ADC\Files::url( $file->id ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( $file->label ? $file->label : $file->original_name ); ?></strong></a><small><?php echo esc_html( $file->original_name . ' · ' . adc_date( $file->created_at ) ); ?></small></span></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( current_user_can( 'adc_manage_customers' ) ) : ?>
				<form class="amds-form" method="post" enctype="multipart/form-data">
					<?php Manager::action_fields( 'customer_file', $c->id ); ?>
					<?php
					Manager::field( 'label', __( 'Document', 'automotive-dealer-crm' ), '', 'text', array( 'attrs' => array( 'placeholder' => __( 'ID card, driving licence, contract…', 'automotive-dealer-crm' ) ) ) );
					Manager::field( 'document', __( 'File (PDF or photo, 8 MB max)', 'automotive-dealer-crm' ), '', 'file', array( 'attrs' => array( 'accept' => 'application/pdf,image/jpeg,image/png,image/webp', 'required' => true ) ) );
					?>
					<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Upload', 'automotive-dealer-crm' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
	</aside>
</div>
