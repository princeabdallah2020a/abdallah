<?php
/**
 * CRM — team and permissions (owners only).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Manager;
use ADC\Roles;

if ( ! current_user_can( 'adc_manage_team' ) ) {
	return;
}
$roles = array();
foreach ( Roles::roles() as $key => $role ) {
	$roles[ $key ] = $role[0];
}
$caps = Roles::capabilities();
unset( $caps['adc_access'], $caps['adc_manage_team'] );

Manager::head( __( 'Team', 'automotive-dealer-crm' ), __( 'Invite your staff and choose what each person can do. Everything is checked on the server.', 'automotive-dealer-crm' ) );
?>
<div class="adc-form-layout">
	<div class="adc-form-layout__main">
		<?php foreach ( ADC\Team::members() as $member ) : ?>
			<?php
			$role   = Roles::role_of( $member );
			$extra  = ADC\Team::extra_caps( $member );
			$base   = isset( Roles::roles()[ $role ] ) ? Roles::roles()[ $role ][1] : array();
			$is_me  = get_current_user_id() === $member->ID;
			?>
			<details class="adc-panel adc-member">
				<summary>
					<span class="adc-avatar adc-avatar--lg"><?php echo esc_html( adc_initials( $member->display_name ) ); ?></span>
					<span class="adc-member__who"><strong><?php echo esc_html( $member->display_name ); ?><?php echo $is_me ? ' · ' . esc_html__( 'you', 'automotive-dealer-crm' ) : ''; ?></strong><small><?php echo esc_html( $member->user_email ); ?></small></span>
					<span class="amds-badge amds-badge--neutral"><?php echo esc_html( Roles::role_label( $role ) ); ?></span>
				</summary>
				<form class="amds-form" method="post">
					<?php Manager::action_fields( 'team_update', $member->ID ); ?>
					<?php Manager::field( 'role', __( 'Role', 'automotive-dealer-crm' ), $role, 'select', array( 'options' => $roles ) ); ?>
					<fieldset class="amds-fieldset">
						<legend class="amds-label"><?php esc_html_e( 'Permissions', 'automotive-dealer-crm' ); ?></legend>
						<p class="amds-help"><?php esc_html_e( 'Ticked and greyed: included in the role. Tick more to grant them to this person only.', 'automotive-dealer-crm' ); ?></p>
						<div class="adc-caps">
							<?php foreach ( $caps as $cap => $label ) : ?>
								<?php $in_role = in_array( $cap, $base, true ); ?>
								<label class="adc-check<?php echo $in_role ? ' is-included' : ''; ?>"><input type="checkbox" name="caps[]" value="<?php echo esc_attr( $cap ); ?>" <?php checked( $in_role || in_array( $cap, $extra, true ) ); ?> <?php disabled( $in_role ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<div class="adc-actions"><button class="amds-btn amds-btn--primary" type="submit"><?php esc_html_e( 'Save', 'automotive-dealer-crm' ); ?></button></div>
				</form>
				<?php if ( ! $is_me ) : ?>
					<?php Manager::button( 'team_remove', $member->ID, __( 'Remove access', 'automotive-dealer-crm' ), 'amds-btn--ghost adc-danger', array(), true ); ?>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
	</div>
	<aside class="adc-form-layout__side">
		<form class="adc-panel amds-form" method="post">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Invite someone', 'automotive-dealer-crm' ); ?></h2></div>
			<?php Manager::action_fields( 'team_invite' ); ?>
			<?php
			Manager::field( 'name', __( 'Name', 'automotive-dealer-crm' ) );
			Manager::field( 'email', __( 'Email', 'automotive-dealer-crm' ), '', 'email', array( 'attrs' => array( 'required' => true ) ) );
			Manager::field( 'role', __( 'Role', 'automotive-dealer-crm' ), 'adc_sales', 'select', array( 'options' => $roles ) );
			?>
			<details class="adc-more"><summary><?php esc_html_e( 'Extra permissions', 'automotive-dealer-crm' ); ?></summary>
				<div class="adc-caps">
					<?php foreach ( $caps as $cap => $label ) : ?>
						<label class="adc-check"><input type="checkbox" name="caps[]" value="<?php echo esc_attr( $cap ); ?>"> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</div>
			</details>
			<button class="amds-btn amds-btn--primary amds-btn--block" type="submit"><?php esc_html_e( 'Send the invitation', 'automotive-dealer-crm' ); ?></button>
		</form>
		<?php $invites = ADC\Team::pending_invites(); ?>
		<?php if ( $invites ) : ?>
			<section class="adc-panel">
				<div class="adc-panel__head"><h2><?php esc_html_e( 'Pending invitations', 'automotive-dealer-crm' ); ?></h2></div>
				<ul class="adc-list">
					<?php foreach ( $invites as $invite ) : ?>
						<li><span><strong><?php echo esc_html( $invite->email ); ?></strong><small><?php echo esc_html( Roles::role_label( $invite->role ) . ' · ' . sprintf( /* translators: %s: date */ __( 'expires %s', 'automotive-dealer-crm' ), adc_date( $invite->expires_at ) ) ); ?></small></span><?php Manager::button( 'invite_revoke', $invite->id, __( 'Cancel', 'automotive-dealer-crm' ), 'amds-btn--ghost adc-btn-xs' ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
		<section class="adc-panel">
			<div class="adc-panel__head"><h2><?php esc_html_e( 'Roles', 'automotive-dealer-crm' ); ?></h2></div>
			<dl class="adc-roles">
				<dt><?php echo esc_html( $roles['adc_owner'] ); ?></dt><dd><?php esc_html_e( 'Everything, including the team.', 'automotive-dealer-crm' ); ?></dd>
				<dt><?php echo esc_html( $roles['adc_manager'] ); ?></dt><dd><?php esc_html_e( 'Everything except the team: publishes, sets prices, sees revenue.', 'automotive-dealer-crm' ); ?></dd>
				<dt><?php echo esc_html( $roles['adc_sales'] ); ?></dt><dd><?php esc_html_e( 'Own leads, customers, sales; adds vehicles for review.', 'automotive-dealer-crm' ); ?></dd>
				<dt><?php echo esc_html( $roles['adc_rental'] ); ?></dt><dd><?php esc_html_e( 'Rentals, customers and rental leads.', 'automotive-dealer-crm' ); ?></dd>
				<dt><?php echo esc_html( $roles['adc_viewer'] ); ?></dt><dd><?php esc_html_e( 'Read-only access, including statistics.', 'automotive-dealer-crm' ); ?></dd>
			</dl>
		</section>
	</aside>
</div>
