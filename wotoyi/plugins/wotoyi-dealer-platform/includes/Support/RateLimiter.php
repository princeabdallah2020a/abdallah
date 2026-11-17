<?php
/**
 * Fixed-window rate limiter on transients (uses the object cache when one is installed).
 * Not atomic on a plain-DB setup: under heavy concurrency it may let a few extra requests through.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Support;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {

	private static function key( string $bucket, int $window ): string {
		return 'wotoyi_rl_' . substr( hash( 'sha256', $bucket . '|' . (int) floor( Clock::now() / $window ) ), 0, 32 );
	}

	/** Count one hit; true when still within the limit. */
	public static function allow( string $bucket, int $limit, int $window ): bool {
		$key   = self::key( $bucket, $window );
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, $window + 5 );
		return $count <= $limit;
	}

	/** True when the bucket is already over the limit (does not count a hit). */
	public static function blocked( string $bucket, int $limit, int $window ): bool {
		return (int) get_transient( self::key( $bucket, $window ) ) >= $limit;
	}

	/** Count one hit without checking. */
	public static function record( string $bucket, int $window ): void {
		$key = self::key( $bucket, $window );
		set_transient( $key, (int) get_transient( $key ) + 1, $window + 5 );
	}

	/** Count $n units (for example rows read) in the window; true while the running total stays within the limit. */
	public static function consume( string $bucket, int $n, int $limit, int $window ): bool {
		$key   = self::key( $bucket, $window );
		$count = (int) get_transient( $key ) + max( 0, $n );
		set_transient( $key, $count, $window + 5 );
		return $count <= $limit;
	}
}
