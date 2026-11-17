<?php
/**
 * Minimal PSR-4 style autoloader (no Composer dependency).
 * Wotoyi\Marketplace\Foo\Bar  ->  includes/Foo/Bar.php
 *
 * @package Wotoyi\Marketplace
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Wotoyi\\Marketplace\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);

require_once __DIR__ . '/api.php';
