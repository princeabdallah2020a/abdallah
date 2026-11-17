<?php
/**
 * Append-only audit trail (tenant scoped, SECURITY-REVIEW M3).
 *
 *  - Append-only: TenantDb refuses update() on this table and delete() is restricted to the outbox.
 *  - Tamper-evident: every row carries `hash = HMAC(prev_hash | row)` (key derived from WOTOYI_SECRET_KEY), so an
 *    edit or deletion made directly in the database breaks the chain and AuditLog::verify() reports where.
 *  - Every entry has an actor: a user id (`actor_type` user or platform) or 0 + `system` for worker/cron work.
 *  - Permission changes record `from` and `to`. Personal data (names, phones, emails, note text) is never written:
 *    only field names, counts and ids, so the log cannot become a second copy of personal data.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Crypto;
use Wotoyi\DealerPlatform\Support\Ip;

defined( 'ABSPATH' ) || exit;

final class AuditLog {

	/**
	 * @param array<string,mixed> $meta
	 * @param int|null            $actor      WordPress user id; null = the acting member of the tenant context (0 = system).
	 * @param string|null         $actor_type one of user, platform, system; derived when null.
	 */
	public static function record( string $action, ?string $entity_type = null, ?string $entity_uuid = null, array $meta = array(), ?int $actor = null, ?string $actor_type = null ): void {
		$meta  = self::scrub( $meta );
		$user  = null === $actor ? TenantContext::user_id() : max( 0, $actor );
		$type  = $user > 0 ? ( 'platform' === $actor_type ? 'platform' : 'user' ) : 'system';
		$row   = array(
			'user_id'     => $user,
			'actor_type'  => $type,
			'action'      => substr( $action, 0, 60 ),
			'entity_type' => null === $entity_type ? null : substr( $entity_type, 0, 24 ),
			'entity_uuid' => $entity_uuid,
			'meta'        => $meta ? mb_substr( (string) wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 2000 ) : null,
			'ip_hash'     => $user > 0 ? Ip::hash( Ip::client() ) : null,
			'created_at'  => Clock::mysql(),
		);
		TenantDb::transaction(
			static function () use ( $row ): void {
				$prev = self::last_hash();
				TenantDb::insert( 'audit_log', $row + array( 'prev_hash' => $prev, 'hash' => self::hash_row( $prev, $row ) ) );
			}
		);
	}

	private static function last_hash(): string {
		$last = TenantDb::select( 'audit_log', array( 'hash' => array( 'notnull' ) ), array( 'columns' => array( 'hash' ), 'order' => array( array( 'id', 'desc' ) ), 'limit' => 1 ) );
		return $last ? (string) $last[0]['hash'] : '';
	}

	/** @param array<string,mixed> $row */
	private static function hash_row( string $prev, array $row ): string {
		$payload = $prev . '|' . TenantContext::id() . '|' . implode( '|', array_map( static fn( $v ): string => null === $v ? '' : (string) $v, array( $row['user_id'], $row['actor_type'], $row['action'], $row['entity_type'], $row['entity_uuid'], $row['meta'], $row['ip_hash'], $row['created_at'] ) ) );
		return Crypto::keyed_hash( 'audit', $payload );
	}

	/**
	 * Walk the hash chain of the current tenant.
	 *
	 * @return array{ok:bool,checked:int,broken_at:int|null} `broken_at` = id of the first row that does not verify.
	 */
	public static function verify(): array {
		$prev    = '';
		$checked = 0;
		$offset  = 0;
		$chained = false; // Rows written before the chain existed (hash NULL) are tolerated only before the first chained row.
		while ( true ) {
			$rows = TenantDb::select( 'audit_log', array(), array( 'order' => array( array( 'id', 'asc' ) ), 'limit' => 500, 'offset' => $offset ) );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $r ) {
				if ( null === $r['hash'] && ! $chained ) {
					continue;
				}
				$chained = true;
				if ( null === $r['hash'] || (string) $r['prev_hash'] !== $prev || ! hash_equals( self::hash_row( $prev, $r ), (string) $r['hash'] ) ) {
					return array( 'ok' => false, 'checked' => $checked, 'broken_at' => (int) $r['id'] );
				}
				$prev = (string) $r['hash'];
				++$checked;
			}
			$offset += 500;
		}
		return array( 'ok' => true, 'checked' => $checked, 'broken_at' => null );
	}

	/**
	 * Drop anything that looks like a secret, recursively.
	 *
	 * @param array<string,mixed> $meta
	 * @return array<string,mixed>
	 */
	public static function scrub( array $meta ): array {
		$out = array();
		foreach ( $meta as $k => $v ) {
			if ( is_string( $k ) && 1 === preg_match( '/secret|password|passwd|token|api[_-]?key|authorization|signature|pairing|code$/i', $k ) ) {
				$out[ $k ] = '[redacted]';
			} elseif ( is_array( $v ) ) {
				$out[ $k ] = self::scrub( $v );
			} else {
				$out[ $k ] = is_scalar( $v ) || null === $v ? $v : '[object]';
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $q page, per_page, action, entity_type, entity_uuid
	 * @return array<string,mixed>
	 */
	public static function list( array $q ): array {
		$where = array();
		foreach ( array( 'action', 'entity_type', 'entity_uuid' ) as $f ) {
			if ( isset( $q[ $f ] ) && is_string( $q[ $f ] ) && '' !== $q[ $f ] ) {
				$where[ $f ] = $q[ $f ];
			}
		}
		$per   = max( 1, min( 100, (int) ( $q['per_page'] ?? 50 ) ) );
		$page  = max( 1, (int) ( $q['page'] ?? 1 ) );
		$total = TenantDb::count( 'audit_log', $where );
		$rows  = TenantDb::select( 'audit_log', $where, array( 'order' => array( array( 'id', 'desc' ) ), 'limit' => $per, 'offset' => ( $page - 1 ) * $per ) );
		$items = array_map(
			static fn( array $r ): array => array(
				'id'          => (int) $r['id'],
				'user_id'     => (int) $r['user_id'],
				'actor_type'  => $r['actor_type'],
				'action'      => $r['action'],
				'entity_type' => $r['entity_type'],
				'entity_id'   => $r['entity_uuid'],
				'meta'        => $r['meta'] ? json_decode( (string) $r['meta'], true ) : null,
				'created_at'  => $r['created_at'],
			),
			$rows
		);
		return array( 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per );
	}
}
