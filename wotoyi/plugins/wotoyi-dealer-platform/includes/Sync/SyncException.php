<?php
/**
 * Transport-level failure. `retryable` tells the worker to back off and retry; `auth` means credentials are
 * rejected (the tenant must re-pair), so the worker stops pushing instead of hammering the marketplace.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

defined( 'ABSPATH' ) || exit;

final class SyncException extends \RuntimeException {

	public function __construct( private string $error_code, string $message, private bool $retryable = true, private int $http_status = 0, private bool $auth = false ) {
		parent::__construct( $message );
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function retryable(): bool {
		return $this->retryable;
	}

	public function http_status(): int {
		return $this->http_status;
	}

	public function is_auth(): bool {
		return $this->auth;
	}
}
