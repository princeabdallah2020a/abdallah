<?php
/**
 * Platform admin — audit log.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$table  = AMC\Database::table( 'audit_log' );
$where  = $q ? $wpdb->prepare( 'WHERE action LIKE %s', $wpdb->esc_like( $q ) . '%' ) : '';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$events = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY id DESC LIMIT 300" );
AMC\Portal::page_head( __( 'Audit log', 'automotive-marketplace-core' ), __( 'Every administrative action is recorded here and cannot be edited.', 'automotive-marketplace-core' ) );
?>
<form class="amc-search" method="get" action="<?php echo esc_url( AMC\Portal::url( 'platform', 'audit' ) ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="amc_view" value="platform"><input type="hidden" name="amc_section" value="audit"><?php endif; ?>
	<input class="amds-input" type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Event starts with… (e.g. payment.)', 'automotive-marketplace-core' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'automotive-marketplace-core' ); ?>">
	<button class="amds-btn amds-btn--secondary" type="submit"><?php esc_html_e( 'Filter', 'automotive-marketplace-core' ); ?></button>
</form>
<section class="amc-panel">
	<div class="amc-table-wrap">
		<table class="amds-table">
			<thead><tr><th><?php esc_html_e( 'Date', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Event', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Object', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'User', 'automotive-marketplace-core' ); ?></th><th><?php esc_html_e( 'Details', 'automotive-marketplace-core' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( (array) $events as $event ) : ?>
					<?php $user = $event->actor_user_id ? get_userdata( (int) $event->actor_user_id ) : null; ?>
					<tr>
						<td><?php echo esc_html( AMC\Portal::date( $event->created_at, true ) ); ?></td>
						<td><code><?php echo esc_html( $event->action ); ?></code></td>
						<td><?php echo esc_html( trim( $event->object_type . ' ' . ( $event->object_id ? '#' . $event->object_id : '' ) ) ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : __( 'System', 'automotive-marketplace-core' ) ); ?></td>
						<td><small><?php echo esc_html( '[]' === $event->context ? '' : $event->context ); ?></small></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
