<?php
/**
 * Append-only audit trail of staff and security actions (PROJECT.md §14.5, SECURITY-REVIEW M7).
 *
 * Who (actor type + WP user id), what (action), which dealer, when, from where (keyed IP hash), correlation id,
 * and a before/after summary restricted to a WHITELIST of non-secret keys: secrets, pairing codes, tokens and
 * personal data can never be written here. There is deliberately no update or delete-by-id method; the only
 * removal is the age-based retention prune.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\Json;

defined( 'ABSPATH' ) || exit;

final class AuditRepository {

	public const ACTOR_TYPES = array( 'staff', 'dealer', 'system' );

	/** Detail keys that may be stored. Values must be scalars, or [before, after] scalar pairs, or lists of short strings. */
	private const DETAIL_KEYS = array( 'status', 'verified', 'key_grace', 'ttl', 'previous_secret_revoked', 'profile_fields', 'source_identity_bound', 'created', 'items_removed', 'items_anonymized', 'items_retained', 'user_id', 'leads_deleted', 'leads_anonymized', 'reason' );

	public function table(): string {
		return Db::table( 'audit_log' );
	}

	/**
	 * @param string              $actor_type    staff | dealer | system
	 * @param int                 $actor_user_id WP user id for staff (0 = none)
	 * @param array<string,mixed> $details       Only whitelisted keys are kept.
	 */
	public function record( string $actor_type, int $actor_user_id, string $action, ?int $dealer_id, array $details = array(), string $correlation_id = '' ): void {
		$actor_type = in_array( $actor_type, self::ACTOR_TYPES, true ) ? $actor_type : 'system';
		$clean      = array();
		foreach ( self::DETAIL_KEYS as $key ) {
			if ( ! array_key_exists( $key, $details ) ) {
				continue;
			}
			$v = $details[ $key ];
			if ( is_array( $v ) ) {
				$v = array_slice( array_map( static fn( $x ) => is_scalar( $x ) || null === $x ? ( is_string( $x ) ? mb_substr( $x, 0, 64 ) : $x ) : null, array_values( $v ) ), 0, 20 );
			} elseif ( is_string( $v ) ) {
				$v = mb_substr( $v, 0, 64 );
			} elseif ( ! is_scalar( $v ) ) {
				$v = null;
			}
			$clean[ $key ] = $v;
		}
		Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'created_at'     => Clock::mysql(),
				'actor_type'     => $actor_type,
				'actor_user_id'  => $actor_user_id > 0 ? $actor_user_id : null,
				'action'         => mb_substr( $action, 0, 40 ),
				'dealer_id'      => $dealer_id,
				'details'        => $clean ? Json::canonical( $clean ) : null,
				'ip_hash'        => Ip::hash( Ip::client() ),
				'correlation_id' => '' !== $correlation_id ? mb_substr( $correlation_id, 0, 64 ) : Crypto::token( 9 ),
			)
		);
	}

	/**
	 * Newest first.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function recent( int $limit = 50, int $before_id = 0, ?int $dealer_id = null ): array {
		$limit = max( 1, min( 200, $limit ) );
		$where = array( 'id < %d' );
		$args  = array( $before_id > 0 ? $before_id : PHP_INT_MAX );
		if ( null !== $dealer_id ) {
			$where[] = 'dealer_id = %d';
			$args[]  = $dealer_id;
		}
		$args[] = $limit;
		$sql    = Db::prepare( "SELECT id, created_at, actor_type, actor_user_id, action, dealer_id, details, ip_hash, correlation_id FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d', $args );
		$rows   = (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as &$r ) {
			$r['details'] = Json::decode_array( $r['details'] ?? null ) ?? array();
		}
		return $rows;
	}

	/** Age-based retention only (never by id). */
	public function prune( int $days ): void {
		if ( $days <= 0 ) {
			return;
		}
		$cutoff = Clock::mysql( Clock::now() - $days * DAY_IN_SECONDS );
		Db::wpdb()->query( Db::prepare( "DELETE FROM {$this->table()} WHERE created_at < %s", array( $cutoff ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
