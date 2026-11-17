<?php
/**
 * Runs a REST callback inside the tenant context established by the permission callback, and converts domain
 * exceptions to WP_Error. Unexpected exceptions are logged (class + message, no payload) and answered with a
 * generic 500 so internals never leak.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Rest;

use Wotoyi\DealerPlatform\Auth\Access;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Support\ApiException;

defined( 'ABSPATH' ) || exit;

final class Handler {

	public const MAX_BODY = 1048576;

	/**
	 * @param callable(\WP_REST_Request):mixed $fn
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function run( \WP_REST_Request $request, callable $fn ) {
		$ctx = Access::context( $request );
		if ( ! $ctx ) {
			return new \WP_Error( 'wotoyi_unauthorized', 'Authentication required.', array( 'status' => 401 ) ); // Fail closed.
		}
		return self::guard( static fn() => TenantContext::run( $ctx['tenant'], static fn() => $fn( $request ), $ctx['member'] ) );
	}

	/** For platform-operator routes (no tenant context). @return \WP_REST_Response|\WP_Error */
	public static function run_platform( \WP_REST_Request $request, callable $fn ) {
		return self::guard( static fn() => $fn( $request ) );
	}

	private static function guard( callable $call ) {
		try {
			$out = $call();
			return $out instanceof \WP_REST_Response ? $out : new \WP_REST_Response( $out, 200 );
		} catch ( ApiException $e ) {
			return new \WP_Error( $e->error_code(), $e->getMessage(), array( 'status' => $e->http_status(), 'details' => $e->data() ) );
		} catch ( \Throwable $e ) {
			error_log( 'wotoyi-dealer-platform REST error: ' . get_class( $e ) . ' ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_Error( 'wotoyi_server_error', 'Internal error.', array( 'status' => 500 ) );
		}
	}

	/**
	 * JSON object body (max 1 MB, depth 16).
	 *
	 * @return array<string,mixed>
	 */
	public static function body( \WP_REST_Request $request ): array {
		$raw = (string) $request->get_body();
		if ( '' === trim( $raw ) ) {
			return array();
		}
		if ( strlen( $raw ) > self::MAX_BODY ) {
			throw new ApiException( 'wotoyi_body_too_large', 'Request body too large.', 413 );
		}
		$data = json_decode( $raw, true, 16 );
		if ( ! is_array( $data ) || ( array() !== $data && array_is_list( $data ) ) ) {
			throw new ApiException( 'wotoyi_invalid_json', 'Body must be a JSON object.', 400 );
		}
		return $data;
	}
}
