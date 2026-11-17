<?php
/**
 * Minimal PSR-4 style autoloader (no Composer dependency).
 * Wotoyi\DealerPlatform\Foo\Bar  ->  includes/Foo/Bar.php
 *
 * @package Wotoyi\DealerPlatform
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Wotoyi\\DealerPlatform\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);
