<?php
/**
 * Uninstall: the dealer's business data is never deleted automatically.
 * To erase it, the platform team removes the tables manually after an export.
 *
 * @package AutomotiveDealerCrm
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'adc_owner', 'adc_manager', 'adc_sales', 'adc_rental', 'adc_viewer' ) as $adc_role ) {
	remove_role( $adc_role );
}
