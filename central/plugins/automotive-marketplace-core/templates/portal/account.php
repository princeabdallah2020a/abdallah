<?php
/**
 * Dealer space (/dealer-dashboard/).
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

$dealer = AMC\Memberships::current_dealer();
AMC\Portal::shell_open( 'account' );
amc_get_template(
	'portal/account/' . AMC\Portal::section(),
	array(
		'dealer' => $dealer,
		'item'   => AMC\Portal::item(),
	)
);
AMC\Portal::shell_close();
