<?php
/**
 * Domain error carrying an HTTP status; the REST layer turns it into a WP_Error. Services throw it, never WP_Error.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Support;

defined( 'ABSPATH' ) || exit;

final class ApiException extends \RuntimeException {

	/** @param array<string,mixed> $data Extra public data (field names, never secrets). */
	public function __construct( private string $error_code, string $message, private int $http_status = 400, private array $data = array() ) {
		parent::__construct( $message );
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function http_status(): int {
		return $this->http_status;
	}

	/** @return array<string,mixed> */
	public function data(): array {
		return $this->data;
	}

	public static function not_found( string $what = 'Resource' ): self {
		return new self( 'wotoyi_not_found', $what . ' not found.', 404 );
	}

	public static function invalid( string $message, array $data = array() ): self {
		return new self( 'wotoyi_invalid_request', $message, 422, $data );
	}

	public static function conflict( string $code, string $message, array $data = array() ): self {
		return new self( $code, $message, 409, $data );
	}

	public static function forbidden( string $message = 'Insufficient permissions.' ): self {
		return new self( 'wotoyi_forbidden', $message, 403 );
	}
}
