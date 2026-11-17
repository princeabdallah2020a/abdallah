<?php
/**
 * UUID v7 (time-ordered) generator.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Support;

defined( 'ABSPATH' ) || exit;

final class Uuid {

	public static function v7(): string {
		$ms  = (int) floor( microtime( true ) * 1000 );
		$r   = random_bytes( 10 );
		$bin = substr( pack( 'J', $ms ), 2, 6 )
			. chr( 0x70 | ( ord( $r[0] ) & 0x0f ) )
			. $r[1]
			. chr( 0x80 | ( ord( $r[2] ) & 0x3f ) )
			. substr( $r, 3, 7 );
		$hex = bin2hex( $bin );
		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20, 12 );
	}

	public static function is_valid( string $value ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value );
	}
}
