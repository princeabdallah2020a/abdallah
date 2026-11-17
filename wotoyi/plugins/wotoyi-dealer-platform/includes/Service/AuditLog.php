<?php
/**
 * Append-only audit trail (tenant scoped). Records who did what to which entity; never secrets and never
 * field values (only field names), so the log cannot become a second copy of personal data.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Ip;

defined( 'ABSPATH' ) || exit;

final class AuditLog {

	/** @param array<string,mixed> $meta */
	public static function record( string $action, ?string $entity_type = null, ?string $entity_uuid = null, array $meta = array() ): void {
		$meta = self::scrub( $meta );
		TenantDb::insert(
			'audit_log',
			array(
				'user_id'     => TenantContext::user_id(),
				'action'      => substr( $action, 0, 60 ),
				'entity_type' => null === $entity_type ? null : substr( $entity_type, 0, 24 ),
				'entity_uuid' => $entity_uuid,
				'meta'        => $meta ? mb_substr( (string) wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 2000 ) : null,
				'ip_hash'     => TenantContext::user_id() > 0 ? Ip::hash( Ip::client() ) : null,
				'created_at'  => Clock::mysql(),
			)
		);
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
