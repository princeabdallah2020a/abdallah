<?php
/**
 * Bootstrap.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot() {
		add_action(
			'init',
			static function () {
				load_plugin_textdomain( 'automotive-dealer-crm', false, dirname( plugin_basename( ADC_FILE ) ) . '/languages' );
			},
			1
		);
		Database::maybe_migrate();
		Roles::init();
		Router::init();
		Public_Site::init();
		Rest_Api::init();
		Files::init();
		Privacy::init();
	}

	public static function activate() {
		Database::migrate();
		Roles::install();
		Router::register_rewrites();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
