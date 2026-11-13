<?php
/**
 * Platform admin — payments to verify.
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

use AMC\Portal;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
$tabs   = array(
	'pending'  => __( 'To verify', 'automotive-marketplace-core' ) . ' (' . AMC\Payments::pending_count() . ')',
	'verified' => __( 'Confirmed', 'automotive-marketplace-core' ),
	'rejected' => __( 'Rejected', 'automotive-marketplace-core' ),
	'all'      => __( 'All', 'automotive-marketplace-core' ),
);
Portal::page_head( __( 'Payments', 'automotive-marketplace-core' ), __( 'Check each receipt against your Wave, Orange Money or bank statement before confirming.', 'automotive-marketplace-core' ) );
?>
<nav class="amds-tabs amc-app__tabs">
	<?php foreach ( $tabs as $key => $label ) : ?>
		<a class="amds-tabs__item<?php echo $key === $status ? ' is-active' : ''; ?>" href="<?php echo esc_url( Portal::url( 'platform', 'payments', 0, array( 'status' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<section class="amc-panel">
	<?php amc_get_template( 'parts/payments-table', array( 'rows' => AMC\Payments::list( 'all' === $status ? '' : $status ), 'show_dealer' => true ) ); ?>
</section>
