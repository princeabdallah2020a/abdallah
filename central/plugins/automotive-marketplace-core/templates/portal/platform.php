<?php
/**
 * Platform administration (/platform-admin/).
 *
 * @package AutomotiveMarketplaceCore
 */

defined( 'ABSPATH' ) || exit;

AMC\Portal::shell_open( 'platform' );
amc_get_template( 'portal/platform/' . AMC\Portal::section(), array( 'item' => AMC\Portal::item() ) );
AMC\Portal::shell_close();
