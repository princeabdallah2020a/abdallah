<?php
/**
 * Member invitations (SECURITY-REVIEW H1). Global table: the invitee has no membership yet, so a token lookup must
 * work before any tenant context exists. Like Directory, this class never returns tenant business data. Only the
 * SHA-256 hash of the single-use token is stored; the token itself exists only in the invitation email.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Data;

use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class Invitations {

	public const TTL = 604800; // 7 days.

	private static function t(): string {
		return TenantDb::table_name( 'invitations' );
	}

	private static function prep( string $sql, array $args ): string {
		return TenantDb::wpdb()->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Create a pending invitation (any earlier pending one for the same tenant and email is revoked).
	 *
	 * @return array{uuid:string,token:string,expires_at:string}
	 */
	public static function create( int $tenant_id, string $email, string $role, ?string $display_name, int $invited_by ): array {
		$email = strtolower( $email );
		TenantDb::wpdb()->update( self::t(), array( 'status' => 'revoked' ), array( 'tenant_id' => $tenant_id, 'email' => $email, 'status' => 'pending' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$token   = bin2hex( random_bytes( 32 ) );
		$uuid    = Uuid::v7();
		$expires = gmdate( 'Y-m-d H:i:s', Clock::now() + self::TTL );
		$ok      = TenantDb::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::t(),
			array(
				'uuid'         => $uuid,
				'tenant_id'    => $tenant_id,
				'token_hash'   => self::hash_token( $token ),
				'email'        => substr( $email, 0, 190 ),
				'role'         => $role,
				'display_name' => $display_name,
				'invited_by'   => $invited_by,
				'status'       => 'pending',
				'expires_at'   => $expires,
				'created_at'   => Clock::mysql(),
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'invitation_insert_failed' );
		}
		return array( 'uuid' => $uuid, 'token' => $token, 'expires_at' => $expires );
	}

	/** Pending, unexpired invitation for a token, or null. @return array<string,mixed>|null */
	public static function find_pending( string $token ): ?array {
		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/', $token ) ) {
			return null;
		}
		$row = TenantDb::wpdb()->get_row( self::prep( 'SELECT * FROM ' . self::t() . ' WHERE token_hash = %s AND status = %s AND expires_at > %s', array( self::hash_token( $token ), 'pending', Clock::mysql() ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** Atomic single use: true for exactly one caller. */
	public static function consume( int $id, int $user_id ): bool {
		$n = TenantDb::wpdb()->query( self::prep( 'UPDATE ' . self::t() . ' SET status = %s, accepted_at = %s, accepted_by = %d WHERE id = %d AND status = %s', array( 'accepted', Clock::mysql(), $user_id, $id, 'pending' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 1 === (int) $n;
	}

	/** @return list<array<string,mixed>> Pending invitations of one tenant (never the token or its hash). */
	public static function list_for_tenant( int $tenant_id ): array {
		$rows = (array) TenantDb::wpdb()->get_results( self::prep( 'SELECT uuid, email, role, display_name, invited_by, status, expires_at, created_at FROM ' . self::t() . ' WHERE tenant_id = %d AND status = %s AND expires_at > %s ORDER BY id DESC LIMIT 200', array( $tenant_id, 'pending', Clock::mysql() ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $rows;
	}

	public static function revoke( int $tenant_id, string $uuid ): bool {
		$n = TenantDb::wpdb()->query( self::prep( 'UPDATE ' . self::t() . ' SET status = %s WHERE tenant_id = %d AND uuid = %s AND status = %s', array( 'revoked', $tenant_id, $uuid, 'pending' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 1 === (int) $n;
	}

	/** Housekeeping: drop invitations that expired or were closed more than 30 days ago (personal data minimisation). */
	public static function prune(): int {
		$cut = gmdate( 'Y-m-d H:i:s', Clock::now() - 30 * 86400 );
		$n   = TenantDb::wpdb()->query( self::prep( 'DELETE FROM ' . self::t() . ' WHERE created_at < %s AND (status <> %s OR expires_at < %s)', array( $cut, 'pending', $cut ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false === $n ? 0 : (int) $n;
	}
}
