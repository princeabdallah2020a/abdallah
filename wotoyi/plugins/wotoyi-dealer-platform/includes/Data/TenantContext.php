<?php
/**
 * The tenant (dealer company) every data-layer call runs for. There is no way to pass a tenant id to
 * TenantDb: the only way to change tenant is TenantContext::run(), called by exactly three entry points:
 * the REST layer (after membership checks), the sync worker (tenant ids come from Directory) and tests.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Data;

defined( 'ABSPATH' ) || exit;

final class TenantContext {

	/** @var array<string,mixed>|null */
	private static ?array $tenant = null;

	/** @var array<string,mixed>|null */
	private static ?array $member = null;

	/**
	 * Run a callback inside a tenant. Nested calls restore the previous context (even on exceptions).
	 *
	 * @param array<string,mixed>      $tenant Tenant row (id, uuid, ...).
	 * @param array<string,mixed>|null $member Acting member row, or null for system work (worker, import).
	 * @return mixed
	 */
	public static function run( array $tenant, callable $fn, ?array $member = null ): mixed {
		if ( empty( $tenant['id'] ) || empty( $tenant['uuid'] ) ) {
			throw new \InvalidArgumentException( 'invalid_tenant' );
		}
		$prev_t       = self::$tenant;
		$prev_m       = self::$member;
		self::$tenant = $tenant;
		self::$member = $member;
		try {
			return $fn();
		} finally {
			self::$tenant = $prev_t;
			self::$member = $prev_m;
		}
	}

	public static function has(): bool {
		return null !== self::$tenant;
	}

	public static function id(): int {
		if ( null === self::$tenant ) {
			throw new \LogicException( 'no_tenant_context' ); // Fail closed: an unscoped query is impossible.
		}
		return (int) self::$tenant['id'];
	}

	public static function uuid(): string {
		if ( null === self::$tenant ) {
			throw new \LogicException( 'no_tenant_context' );
		}
		return (string) self::$tenant['uuid'];
	}

	/** @return array<string,mixed> */
	public static function tenant(): array {
		if ( null === self::$tenant ) {
			throw new \LogicException( 'no_tenant_context' );
		}
		return self::$tenant;
	}

	/** @return array<string,mixed>|null */
	public static function member(): ?array {
		return self::$member;
	}

	/** WordPress user id of the actor (0 = system). */
	public static function user_id(): int {
		return self::$member ? (int) self::$member['user_id'] : 0;
	}

	public static function role(): ?string {
		return self::$member ? (string) self::$member['role'] : null;
	}

	/** Test helper. */
	public static function reset(): void {
		self::$tenant = null;
		self::$member = null;
	}
}
