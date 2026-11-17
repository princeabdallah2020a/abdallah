<?php
/**
 * Short-lived read cache with version-bump invalidation (any catalogue write bumps the version).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Support;

defined( 'ABSPATH' ) || exit;

final class Cache {

	private const VERSION_OPTION = 'wotoyi_mp_cache_ver';

	public static function version(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	public static function bump(): void {
		update_option( self::VERSION_OPTION, self::version() + 1, false );
	}

	private static function key( string $ns, mixed $params ): string {
		return 'wotoyi_c_' . substr( hash( 'sha256', $ns . '|' . self::version() . '|' . Json::canonical( $params ) ), 0, 40 );
	}

	public static function get( string $ns, mixed $params ): mixed {
		$hit = get_transient( self::key( $ns, $params ) );
		return false === $hit ? null : $hit;
	}

	public static function set( string $ns, mixed $params, mixed $value, int $ttl = 60 ): void {
		set_transient( self::key( $ns, $params ), $value, $ttl );
	}
}
