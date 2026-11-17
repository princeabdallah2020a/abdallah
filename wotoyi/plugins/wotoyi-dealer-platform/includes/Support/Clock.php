<?php
/**
 * Time source (overridable in tests).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Support;

defined( 'ABSPATH' ) || exit;

final class Clock {

	private static ?int $fixed = null;

	public static function now(): int {
		return self::$fixed ?? time();
	}

	public static function set( ?int $timestamp ): void {
		self::$fixed = $timestamp;
	}

	/** UTC MySQL datetime. */
	public static function mysql( ?int $timestamp = null ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp ?? self::now() );
	}
}
