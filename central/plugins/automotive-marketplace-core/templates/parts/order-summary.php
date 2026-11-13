<?php
/**
 * Live order summary (filled by portal.js from the plan picker).
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="amc-summary-box" data-amc-summary>
	<p class="amc-summary-box__eyebrow"><?php esc_html_e( 'Your order', 'automotive-marketplace-core' ); ?></p>
	<p class="amc-summary-box__plan" data-amc-summary-plan>—</p>
	<dl class="amc-summary-box__lines">
		<div><dt><?php esc_html_e( 'Duration', 'automotive-marketplace-core' ); ?></dt><dd data-amc-summary-months>—</dd></div>
		<div><dt><?php esc_html_e( 'Monthly equivalent', 'automotive-marketplace-core' ); ?></dt><dd data-amc-summary-monthly>—</dd></div>
		<div data-amc-summary-saving-row hidden><dt><?php esc_html_e( 'You save', 'automotive-marketplace-core' ); ?></dt><dd class="amc-saving" data-amc-summary-saving>—</dd></div>
	</dl>
	<p class="amc-summary-box__total"><span><?php esc_html_e( 'Total to pay', 'automotive-marketplace-core' ); ?></span><strong data-amc-summary-total>—</strong></p>
	<p class="amc-summary-box__note"><?php esc_html_e( 'No automatic debit: we remind you before the end of your subscription.', 'automotive-marketplace-core' ); ?></p>
</div>
