<?php
/**
 * AutoBuzy theme bootstrap. Business logic lives in the
 * "Automotive Marketplace Core" plugin; this file only loads theme modules.
 *
 * @package AutoBuzy
 */

defined( 'ABSPATH' ) || exit;

define( 'AUTOBUZY_VERSION', '0.1.0' );

require get_template_directory() . '/inc/setup.php';
require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/template-tags.php';
