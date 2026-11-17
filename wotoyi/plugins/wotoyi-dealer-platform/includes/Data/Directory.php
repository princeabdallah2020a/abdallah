<?php
/**
 * The ONLY cross-tenant gateway: tenant records (global table) and the few lookups that must happen before a
 * tenant context exists (which tenants does this WordPress user belong to; which tenants have work for the sync
 * worker). It never returns tenant business data: only tenant rows, membership rows of ONE user, and tenant ids.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Data;

use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class Directory {

	private static function t( string $name ): string {
		return TenantDb::table_name( $name );
	}

	private static function prep( string $sql, array $args ): string {
		return $args ? TenantDb::wpdb()->prepare( $sql, $args ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param array<string,mixed> $data name (required), slug?, country?, city?, currency?, locale?, plan?
	 * @return array<string,mixed> Tenant row.
	 */
	public static function create_tenant( array $data ): array {
		$name = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'tenant_name_required' );
		}
		$base = sanitize_title( (string) ( $data['slug'] ?? $name ) );
		$base = '' === $base ? 'dealer' : substr( $base, 0, 100 );
		$uuid = Uuid::v7();
		$slug = $base;
		for ( $i = 2; $i < 50 && null !== self::find_by_slug( $slug ); $i++ ) {
			$slug = $base . '-' . $i;
		}
		$now = Clock::mysql();
		$ok  = TenantDb::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::t( 'tenants' ),
			array(
				'uuid'       => $uuid,
				'name'       => mb_substr( $name, 0, 160 ),
				'slug'       => $slug,
				'status'     => 'active',
				'plan'       => isset( $data['plan'] ) ? substr( sanitize_text_field( (string) $data['plan'] ), 0, 32 ) : null,
				'country'    => isset( $data['country'] ) && 1 === preg_match( '/^[A-Za-z]{2}$/', (string) $data['country'] ) ? strtoupper( (string) $data['country'] ) : 'SN',
				'city'       => isset( $data['city'] ) ? mb_substr( sanitize_text_field( (string) $data['city'] ), 0, 80 ) : null,
				'currency'   => isset( $data['currency'] ) && 1 === preg_match( '/^[A-Za-z]{3}$/', (string) $data['currency'] ) ? strtoupper( (string) $data['currency'] ) : 'XOF',
				'locale'     => isset( $data['locale'] ) && in_array( $data['locale'], array( 'fr', 'en', 'wo', 'ar' ), true ) ? $data['locale'] : 'fr',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'tenant_insert_failed' );
		}
		return (array) self::find_by_id( (int) TenantDb::wpdb()->insert_id );
	}

	/** @return array<string,mixed>|null */
	public static function find_by_id( int $id ): ?array {
		$row = TenantDb::wpdb()->get_row( self::prep( 'SELECT * FROM ' . self::t( 'tenants' ) . ' WHERE id = %d', array( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_uuid( string $uuid ): ?array {
		$row = TenantDb::wpdb()->get_row( self::prep( 'SELECT * FROM ' . self::t( 'tenants' ) . ' WHERE uuid = %s', array( $uuid ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_slug( string $slug ): ?array {
		$row = TenantDb::wpdb()->get_row( self::prep( 'SELECT * FROM ' . self::t( 'tenants' ) . ' WHERE slug = %s', array( $slug ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** @return list<array<string,mixed>> Platform operator view: tenant rows only (no business data). */
	public static function list_tenants( int $limit = 100 ): array {
		return (array) TenantDb::wpdb()->get_results( self::prep( 'SELECT * FROM ' . self::t( 'tenants' ) . ' ORDER BY id ASC LIMIT %d', array( max( 1, min( 500, $limit ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function set_tenant_status( int $tenant_id, string $status ): void {
		TenantDb::wpdb()->update( self::t( 'tenants' ), array( 'status' => 'suspended' === $status ? 'suspended' : 'active', 'updated_at' => Clock::mysql() ), array( 'id' => $tenant_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Active memberships of one WordPress user (the caller's own): [{member row, tenant row}, ...].
	 *
	 * @return list<array{member:array<string,mixed>,tenant:array<string,mixed>}>
	 */
	public static function memberships_for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$sql  = 'SELECT m.id AS m_id, m.tenant_id AS m_tenant_id, m.uuid AS m_uuid, m.user_id AS m_user_id, m.role AS m_role, m.display_name AS m_display_name, m.status AS m_status FROM '
			. self::t( 'members' ) . ' m WHERE m.user_id = %d AND m.status = %s ORDER BY m.id ASC LIMIT 50';
		$rows = (array) TenantDb::wpdb()->get_results( self::prep( $sql, array( $user_id, 'active' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( $rows as $r ) {
			$tenant = self::find_by_id( (int) $r['m_tenant_id'] );
			if ( ! $tenant || 'active' !== $tenant['status'] ) {
				continue;
			}
			$out[] = array(
				'member' => array(
					'id'           => (int) $r['m_id'],
					'tenant_id'    => (int) $r['m_tenant_id'],
					'uuid'         => $r['m_uuid'],
					'user_id'      => (int) $r['m_user_id'],
					'role'         => $r['m_role'],
					'display_name' => $r['m_display_name'],
					'status'       => $r['m_status'],
				),
				'tenant' => $tenant,
			);
		}
		return $out;
	}

	/**
	 * Tenant ids with outbox work due now (pending and due, or claimed by a worker that died).
	 *
	 * @return list<int>
	 */
	public static function tenant_ids_with_due_outbox( int $limit = 100 ): array {
		$now = Clock::mysql();
		$sql = 'SELECT DISTINCT tenant_id FROM ' . self::t( 'outbox' ) . ' WHERE (status = %s AND next_attempt_at <= %s) OR (status = %s AND locked_until < %s) LIMIT %d';
		$ids = TenantDb::wpdb()->get_results( self::prep( $sql, array( 'pending', $now, 'sending', $now, max( 1, min( 500, $limit ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( static fn( array $r ): int => (int) $r['tenant_id'], (array) $ids );
	}

	/** @return list<int> Tenants whose sync is paired (for lead pulls and nightly reconciliation). */
	public static function paired_tenant_ids( int $limit = 500 ): array {
		$sql = 'SELECT tenant_id FROM ' . self::t( 'sync_state' ) . ' WHERE status = %s ORDER BY tenant_id ASC LIMIT %d';
		$ids = TenantDb::wpdb()->get_results( self::prep( $sql, array( 'paired', max( 1, min( 1000, $limit ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( static fn( array $r ): int => (int) $r['tenant_id'], (array) $ids );
	}
}
