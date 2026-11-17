<?php
/**
 * Buyer leads captured on the marketplace. The marketplace is the outbox: the dealer platform pulls
 * pending leads over the signed API and acknowledges them (no lead is lost if delivery fails).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class LeadRepository {

	public const CHANNELS = array( 'web', 'whatsapp', 'call', 'ai', 'form' );

	public function table(): string {
		return Db::table( 'leads' );
	}

	/**
	 * @param array<string,mixed> $lead Already sanitised: dealer_id, vehicle_id?, service_id?, user_id?, name?, phone?, email?, message?, channel, locale?, consent, consent_version?, consent_purpose?, ip_hash?
	 * @return array{id:int,uuid:string}
	 */
	public function create( array $lead ): array {
		$uuid = Uuid::v7();
		$ok   = Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'uuid'       => $uuid,
				'dealer_id'  => (int) $lead['dealer_id'],
				'vehicle_id' => $lead['vehicle_id'] ?? null,
				'service_id' => $lead['service_id'] ?? null,
				'user_id'    => $lead['user_id'] ?? null,
				'name'       => $lead['name'] ?? null,
				'phone'      => $lead['phone'] ?? null,
				'email'      => $lead['email'] ?? null,
				'message'    => $lead['message'] ?? null,
				'channel'    => $lead['channel'] ?? 'web',
				'locale'     => $lead['locale'] ?? null,
				'consent'    => empty( $lead['consent'] ) ? 0 : 1,
				'consent_at' => empty( $lead['consent'] ) ? null : Clock::mysql(),
				'consent_version' => empty( $lead['consent'] ) ? null : ( $lead['consent_version'] ?? null ),
				'consent_purpose' => empty( $lead['consent'] ) ? null : ( $lead['consent_purpose'] ?? 'transmit_to_dealer' ),
				'ip_hash'    => $lead['ip_hash'] ?? null,
				'created_at' => Clock::mysql(),
			)
		);
		if ( false === $ok ) {
			throw new \RuntimeException( 'lead_insert_failed' );
		}
		return array( 'id' => (int) Db::wpdb()->insert_id, 'uuid' => $uuid );
	}

	/**
	 * Pending leads of ONE dealer (tenant scoped).
	 *
	 * @return list<array<string,mixed>>
	 */
	public function pending_for_dealer( int $dealer_id, int $limit ): array {
		$limit = max( 1, min( 100, $limit ) );
		$sql   = Db::prepare( "SELECT l.uuid, l.name, l.phone, l.email, l.message, l.channel, l.locale, l.consent, l.consent_at, l.consent_version, l.created_at, v.uuid AS vehicle_uuid, v.source_id AS vehicle_source_id, s.uuid AS service_uuid, s.source_id AS service_source_id FROM {$this->table()} l LEFT JOIN " . Db::table( 'vehicles' ) . ' v ON v.id = l.vehicle_id LEFT JOIN ' . Db::table( 'services' ) . " s ON s.id = l.service_id WHERE l.dealer_id = %d AND l.delivery_status = %s ORDER BY l.id ASC LIMIT %d", array( $dealer_id, 'pending', $limit ) );
		$rows  = Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as &$r ) {
			$r['consent'] = (bool) (int) $r['consent'];
		}
		return (array) $rows;
	}

	/**
	 * Mark leads delivered. Only rows that belong to the dealer are touched.
	 *
	 * @param list<string> $uuids
	 */
	public function acknowledge( int $dealer_id, array $uuids ): int {
		$uuids = array_values( array_filter( array_slice( $uuids, 0, 100 ), static fn( $u ): bool => is_string( $u ) && Uuid::is_valid( $u ) ) );
		if ( ! $uuids ) {
			return 0;
		}
		$sql = Db::prepare(
			"UPDATE {$this->table()} SET delivery_status = %s, delivered_at = %s, delivery_attempts = delivery_attempts + 1 WHERE dealer_id = %d AND delivery_status = %s AND uuid IN (" . Db::placeholders( count( $uuids ) ) . ')',
			array_merge( array( 'delivered', Clock::mysql(), $dealer_id, 'pending' ), $uuids )
		);
		Db::wpdb()->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) Db::wpdb()->rows_affected;
	}

	public function count_for_dealer( int $dealer_id ): int {
		return (int) Db::wpdb()->get_var( Db::prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE dealer_id = %d", array( $dealer_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/* ------------------------------------------------------------------
	 * Retention and data-subject rights (SECURITY-REVIEW H2)
	 * ------------------------------------------------------------------ */

	private const BATCH = 500;
	private const MAX_BATCHES = 20; // Per rule per run: bounded work for shared hosting.

	/** Clear every personal column of the given leads (the row stays for statistics: channel, target, dates). @param list<int> $ids */
	private function anonymize_ids( array $ids ): int {
		if ( ! $ids ) {
			return 0;
		}
		$sql = Db::prepare(
			"UPDATE {$this->table()} SET name = NULL, phone = NULL, email = NULL, message = NULL, ip_hash = NULL, user_id = NULL, anonymized_at = %s WHERE id IN (" . Db::placeholders( count( $ids ), '%d' ) . ')',
			array_merge( array( Clock::mysql() ), $ids )
		);
		Db::wpdb()->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) Db::wpdb()->rows_affected;
	}

	/** @param list<int> $ids */
	private function delete_ids( array $ids ): int {
		if ( ! $ids ) {
			return 0;
		}
		$sql = Db::prepare( "DELETE FROM {$this->table()} WHERE id IN (" . Db::placeholders( count( $ids ), '%d' ) . ')', $ids );
		Db::wpdb()->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) Db::wpdb()->rows_affected;
	}

	/**
	 * @param array<mixed> $args
	 * @return list<int>
	 */
	private function ids( string $query, array $args ): array {
		return array_map( 'intval', (array) Db::wpdb()->get_col( Db::prepare( $query, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Daily retention job. A value of 0 disables that rule.
	 *
	 * 1. Leads of a dealer that has been disconnected for more than $disconnected_days: deleted.
	 * 2. Delivered leads older than $delivered_days (since delivery): personal columns erased, row kept.
	 * 3. Leads still undelivered after $undelivered_days (since creation): deleted.
	 *
	 * @return array{anonymized:int,deleted:int}
	 */
	public function apply_retention( int $delivered_days, int $undelivered_days, int $disconnected_days ): array {
		$anon = 0;
		$del  = 0;
		$now  = Clock::now();
		$dt   = Db::table( 'dealers' );

		if ( $disconnected_days > 0 ) {
			$cutoff = Clock::mysql( $now - $disconnected_days * DAY_IN_SECONDS );
			for ( $i = 0; $i < self::MAX_BATCHES; $i++ ) {
				$ids = $this->ids( "SELECT l.id FROM {$this->table()} l INNER JOIN {$dt} d ON d.id = l.dealer_id WHERE d.status = %s AND COALESCE(d.disconnected_at, d.updated_at) < %s ORDER BY l.id ASC LIMIT %d", array( 'disconnected', $cutoff, self::BATCH ) );
				$del += $this->delete_ids( $ids );
				if ( count( $ids ) < self::BATCH ) {
					break;
				}
			}
		}
		if ( $delivered_days > 0 ) {
			$cutoff = Clock::mysql( $now - $delivered_days * DAY_IN_SECONDS );
			for ( $i = 0; $i < self::MAX_BATCHES; $i++ ) {
				$ids   = $this->ids( "SELECT id FROM {$this->table()} WHERE delivery_status = %s AND delivered_at < %s AND anonymized_at IS NULL ORDER BY id ASC LIMIT %d", array( 'delivered', $cutoff, self::BATCH ) );
				$anon += $this->anonymize_ids( $ids );
				if ( count( $ids ) < self::BATCH ) {
					break;
				}
			}
		}
		if ( $undelivered_days > 0 ) {
			$cutoff = Clock::mysql( $now - $undelivered_days * DAY_IN_SECONDS );
			for ( $i = 0; $i < self::MAX_BATCHES; $i++ ) {
				$ids  = $this->ids( "SELECT id FROM {$this->table()} WHERE delivery_status = %s AND created_at < %s ORDER BY id ASC LIMIT %d", array( 'pending', $cutoff, self::BATCH ) );
				$del += $this->delete_ids( $ids );
				if ( count( $ids ) < self::BATCH ) {
					break;
				}
			}
		}
		return array( 'anonymized' => $anon, 'deleted' => $del );
	}

	/**
	 * Leads belonging to a data subject: same e-mail (case-insensitive) or same WordPress user.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function find_for_subject( string $email, int $user_id, int $limit, int $offset = 0 ): array {
		$email = strtolower( trim( $email ) );
		$where = array();
		$args  = array();
		if ( '' !== $email ) {
			$where[] = 'LOWER(email) = %s';
			$args[]  = $email;
		}
		if ( $user_id > 0 ) {
			$where[] = 'user_id = %d';
			$args[]  = $user_id;
		}
		if ( ! $where ) {
			return array();
		}
		$args[] = max( 1, $limit );
		$args[] = max( 0, $offset );
		$sql    = Db::prepare( "SELECT id, uuid, name, phone, email, message, channel, locale, consent, consent_at, consent_version, consent_purpose, delivery_status, delivered_at, created_at FROM {$this->table()} WHERE (" . implode( ' OR ', $where ) . ') ORDER BY id ASC LIMIT %d OFFSET %d', $args );
		return (array) Db::wpdb()->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Erase up to $limit leads of a data subject. Undelivered leads are deleted (nothing to keep); delivered ones are
	 * anonymised (the dealer already holds its own copy: the marketplace cannot erase that).
	 *
	 * @return array{deleted:int,anonymized:int,more:bool}
	 */
	public function erase_subject( string $email, int $user_id, int $limit ): array {
		$rows = $this->find_for_subject( $email, $user_id, $limit + 1 );
		$more = count( $rows ) > $limit;
		$rows = array_slice( $rows, 0, $limit );
		$del  = array();
		$anon = array();
		foreach ( $rows as $r ) {
			if ( 'pending' === $r['delivery_status'] ) {
				$del[] = (int) $r['id'];
			} else {
				$anon[] = (int) $r['id'];
			}
		}
		return array( 'deleted' => $this->delete_ids( $del ), 'anonymized' => $this->anonymize_ids( $anon ), 'more' => $more );
	}

	/** WordPress user deleted: unlink their leads (the lead itself follows the retention rules). */
	public function detach_user( int $user_id ): int {
		Db::wpdb()->query( Db::prepare( "UPDATE {$this->table()} SET user_id = NULL WHERE user_id = %d", array( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) Db::wpdb()->rows_affected;
	}
}
