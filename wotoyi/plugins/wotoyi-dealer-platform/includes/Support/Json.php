<?php
/**
 * JSON helpers.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Support;

defined( 'ABSPATH' ) || exit;

final class Json {

	/** Canonical JSON (recursively key-sorted) so equal payloads hash equally. */
	public static function canonical( mixed $value ): string {
		return (string) wp_json_encode( self::sort( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	private static function sort( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = self::sort( $v );
		}
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}
		return $value;
	}

	/** Decode to array or null (depth-limited). */
	public static function decode_array( ?string $json ): ?array {
		if ( null === $json || '' === $json ) {
			return null;
		}
		$data = json_decode( $json, true, 32 );
		return is_array( $data ) ? $data : null;
	}
}
