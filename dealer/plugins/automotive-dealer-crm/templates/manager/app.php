<?php
/**
 * CRM application (/manager/).
 *
 * @package AutomotiveDealerCrm
 */

defined( 'ABSPATH' ) || exit;

use ADC\Router;

$section = Router::section();
$item    = Router::item();
$routes  = array(
	'vehicles'  => $item ? 'vehicle-form' : 'vehicles',
	'leads'     => 'new' === $item ? 'lead-form' : ( $item ? 'lead' : 'leads' ),
	'customers' => 'new' === $item ? 'customer-form' : ( $item ? 'customer' : 'customers' ),
	'sales'     => 'new' === $item ? 'sale-form' : ( $item ? 'sale' : 'sales' ),
	'rentals'   => 'new' === $item ? 'rental-form' : ( $item ? 'rental' : 'rentals' ),
);
$template = isset( $routes[ $section ] ) ? $routes[ $section ] : $section;

ADC\Manager::shell_open();
adc_template( 'manager/' . $template, array( 'item' => 'new' === $item ? 0 : absint( $item ) ) );
ADC\Manager::shell_close();
