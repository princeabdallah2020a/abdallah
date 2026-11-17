<?php
/**
 * Customers (CRM contacts). Includes duplicate matching by phone/email and personal-data erasure.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class CustomerService extends EntityService {

	public function __construct() {
		parent::__construct( 'customers', Entities::get( 'customers' ) );
	}

	protected function defaults(): array {
		return array( 'country' => \Wotoyi\DealerPlatform\Data\TenantContext::tenant()['country'] ?? null );
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( empty( $merged['phone'] ) && empty( $merged['email'] ) && empty( $merged['whatsapp'] ) ) {
			throw ApiException::invalid( 'A customer needs a phone, WhatsApp number or email.', array( 'fields' => array( 'phone', 'email' ) ) );
		}
		return null;
	}

	/** Existing live customer of this tenant with the same phone or email, or null. @return array<string,mixed>|null */
	public function find_match( ?string $phone, ?string $email ): ?array {
		if ( $phone ) {
			$row = TenantDb::get( 'customers', array( 'phone' => $phone, 'deleted_at' => array( 'null' ) ) );
			if ( $row ) {
				return $row;
			}
		}
		if ( $email ) {
			return TenantDb::get( 'customers', array( 'email' => strtolower( $email ), 'deleted_at' => array( 'null' ) ) );
		}
		return null;
	}

	/** Digits-only comparison key of a phone number (last 9 digits when longer, so +221 77 000 00 50 equals 770000050). */
	private static function phone_key( ?string $phone ): ?string {
		$d = preg_replace( '/\D/', '', (string) $phone ) ?? '';
		return strlen( $d ) >= 6 ? ( strlen( $d ) > 9 ? substr( $d, -9 ) : $d ) : null;
	}

	/**
	 * What identifies the data subject: phone keys, emails and (when distinctive) the name.
	 *
	 * @param array<string,mixed> $c
	 * @return array{phones:list<string>,emails:list<string>,name:string}
	 */
	private function subject( array $c ): array {
		$phones = array_values( array_unique( array_filter( array( self::phone_key( $c['phone'] ?? null ), self::phone_key( $c['whatsapp'] ?? null ) ) ) ) );
		$emails = array_values( array_filter( array( isset( $c['email'] ) ? strtolower( (string) $c['email'] ) : null ) ) );
		$name   = trim( (string) ( $c['name'] ?? '' ) );
		return array( 'phones' => $phones, 'emails' => $emails, 'name' => mb_strlen( $name ) >= 5 ? $name : '' );
	}

	/** @param array{phones:list<string>,emails:list<string>,name:string} $s */
	private static function mentions( ?string $text, array $s ): bool {
		if ( null === $text || '' === $text || '[erased]' === $text ) {
			return false;
		}
		if ( '' !== $s['name'] && false !== mb_stripos( $text, $s['name'] ) ) {
			return true;
		}
		foreach ( $s['emails'] as $e ) {
			if ( false !== stripos( $text, $e ) ) {
				return true;
			}
		}
		$digits = preg_replace( '/\D/', '', $text ) ?? '';
		foreach ( $s['phones'] as $p ) {
			if ( '' !== $digits && false !== strpos( $digits, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param callable(array<string,mixed>):void $fn */
	private function each_row( string $table, callable $fn ): void {
		for ( $page = 0; $page < 1000; $page++ ) {
			$rows = TenantDb::select( $table, array(), array( 'order' => array( array( 'id', 'asc' ) ), 'limit' => 1000, 'offset' => $page * 1000 ) );
			foreach ( $rows as $r ) {
				$fn( $r );
			}
			if ( count( $rows ) < 1000 ) {
				return;
			}
		}
	}

	/**
	 * Every row of this tenant that holds the subject's personal data (SECURITY-REVIEW H2): linked by id (customer_uuid,
	 * lead_uuid, related_uuid, entity_uuid) OR sharing the phone/email (leads) OR mentioning the phone, email or name in
	 * free text (notes, task titles, activity bodies, lost reason). Archived rows included. O(rows of the tenant): erasure
	 * is a rare, deliberate operation.
	 *
	 * @param array<string,mixed> $c Customer row.
	 * @return array{leads:list<array>,lead_text:list<array>,deals:list<array>,rentals:list<array>,tasks:list<array>,activities:list<array>}
	 */
	private function collect( array $c ): array {
		$uuid = (string) $c['uuid'];
		$s    = $this->subject( $c );
		$out  = array( 'leads' => array(), 'lead_text' => array(), 'deals' => array(), 'rentals' => array(), 'tasks' => array(), 'activities' => array() );
		$ids  = array( $uuid => true );
		$this->each_row(
			'leads',
			function ( array $l ) use ( $uuid, $s, &$out, &$ids ): void {
				$by_id = $l['customer_uuid'] === $uuid || ( null !== self::phone_key( $l['phone'] ) && in_array( self::phone_key( $l['phone'] ), $s['phones'], true ) ) || ( null !== $l['email'] && in_array( strtolower( (string) $l['email'] ), $s['emails'], true ) );
				if ( $by_id ) {
					$out['leads'][]       = $l;
					$ids[ $l['uuid'] ] = true;
				} elseif ( self::mentions( $l['message'], $s ) || self::mentions( $l['lost_reason'], $s ) ) {
					$out['lead_text'][] = $l;
					$ids[ $l['uuid'] ]  = true;
				}
			}
		);
		$this->each_row(
			'deals',
			function ( array $d ) use ( $uuid, $s, &$out, &$ids ): void {
				if ( $d['customer_uuid'] === $uuid || isset( $ids[ (string) $d['lead_uuid'] ] ) || self::mentions( $d['notes'], $s ) ) {
					$out['deals'][]    = $d;
					$ids[ $d['uuid'] ] = true;
				}
			}
		);
		$this->each_row(
			'rentals',
			function ( array $r ) use ( $uuid, $s, &$out, &$ids ): void {
				if ( $r['customer_uuid'] === $uuid || self::mentions( $r['notes'], $s ) ) {
					$out['rentals'][]  = $r;
					$ids[ $r['uuid'] ] = true;
				}
			}
		);
		$this->each_row(
			'tasks',
			function ( array $t ) use ( $s, &$out, &$ids ): void {
				if ( isset( $ids[ (string) $t['related_uuid'] ] ) || self::mentions( $t['title'], $s ) || self::mentions( $t['description'], $s ) ) {
					$out['tasks'][] = $t;
				}
			}
		);
		$this->each_row(
			'activities',
			function ( array $a ) use ( $s, &$out, &$ids ): void {
				if ( isset( $ids[ (string) $a['entity_uuid'] ] ) || self::mentions( $a['body'], $s ) ) {
					$out['activities'][] = $a;
				}
			}
		);
		return $out;
	}

	/** @param list<array> $rows @param array<string,mixed> $data */
	private function clear( string $table, array $rows, array $data ): int {
		$uuids = array_values( array_unique( array_column( $rows, 'uuid' ) ) );
		$n     = 0;
		foreach ( array_chunk( $uuids, 200 ) as $chunk ) {
			$n += TenantDb::update( $table, array( 'uuid' => array( 'in', $chunk ) ), $data + array( 'updated_at' => Clock::mysql(), 'version' => array( '@incr' => 1 ) ) );
		}
		return $n;
	}

	/**
	 * Right to erasure (Law 2008-12, GDPR Art. 17): anonymise the customer AND every other trace of the same person in
	 * this dealer's data: leads (also unlinked ones with the same phone or email), lead/deal/rental/customer timeline
	 * entries, tasks, deal and rental notes, lost reasons. Rows are kept so statistics and accounting stay consistent
	 * but no name, contact, note or message survives. Returns per-table counts (also written to the audit log).
	 *
	 * NOT covered, by design of the sync protocol: the marketplace keeps its own copy of marketplace leads; it has no
	 * erase endpoint on the receiver. The data subject (or the dealer on their behalf) must use the marketplace privacy
	 * eraser. The response says so under `follow_up`.
	 *
	 * @return array<string,mixed>
	 */
	public function erase( string $uuid ): array {
		return TenantDb::transaction(
			function () use ( $uuid ): array {
				$c    = $this->row( $uuid );
				$set  = $this->collect( $c );
				$now  = Clock::mysql();
				$lead = $this->clear( 'leads', $set['leads'], array( 'name' => null, 'phone' => null, 'email' => null, 'message' => null, 'lost_reason' => null, 'consent' => 0 ) )
					+ $this->clear( 'leads', $set['lead_text'], array( 'message' => null, 'lost_reason' => null ) );
				$counts = array(
					'customers'  => 0,
					'leads'      => $lead,
					'deals'      => $this->clear( 'deals', $set['deals'], array( 'notes' => null ) ),
					'rentals'    => $this->clear( 'rentals', $set['rentals'], array( 'notes' => null ) ),
					'tasks'      => $this->clear( 'tasks', $set['tasks'], array( 'title' => '[erased]', 'description' => null ) ),
					'activities' => $this->clear( 'activities', $set['activities'], array( 'body' => '[erased]' ) ),
				);
				$counts['customers'] = TenantDb::update( 'customers', array( 'uuid' => $uuid ), array( 'name' => 'Erased', 'phone' => null, 'whatsapp' => null, 'email' => null, 'address' => null, 'city' => null, 'notes' => null, 'tags' => null, 'consent' => 0, 'deleted_at' => $now, 'updated_at' => $now, 'version' => array( '@incr' => 1 ) ) );
				AuditLog::record( 'customers.erase', 'customers', $uuid, array( 'counts' => $counts ) );
				return array(
					'counts'    => $counts,
					'leads'     => $counts['leads'], // Backward compatible with the first release (flat counts).
					'activities' => $counts['activities'],
					'customers' => $counts['customers'],
					'follow_up' => array(
						'marketplace' => 'not_propagated',
						'action'      => 'If this person contacted the dealer through the Wotoyi marketplace, the marketplace still holds its own copy. Ask them to use the marketplace privacy eraser (Settings > Tools > Erase Personal Data on the marketplace site), or ask the marketplace operator to run it for their email address.',
					),
				);
			}
		);
	}

	/**
	 * Right of access (Law 2008-12, GDPR Art. 15/20): everything this dealer holds about the customer, as JSON. Audited.
	 *
	 * @return array<string,mixed>
	 */
	public function export( string $uuid ): array {
		return TenantDb::transaction(
			function () use ( $uuid ): array {
				$c   = $this->row( $uuid );
				$set = $this->collect( $c );
				$pick = static function ( array $rows, array $cols ): array {
					return array_map( static fn( array $r ): array => array_intersect_key( $r, array_flip( $cols ) ), $rows );
				};
				$out = array(
					'customer'   => $this->present( $c ),
					'leads'      => $pick( array_merge( $set['leads'], $set['lead_text'] ), array( 'uuid', 'source', 'name', 'phone', 'email', 'message', 'stage', 'lost_reason', 'created_at' ) ),
					'deals'      => $pick( $set['deals'], array( 'uuid', 'amount', 'currency', 'status', 'notes', 'created_at' ) ),
					'rentals'    => $pick( $set['rentals'], array( 'uuid', 'start_date', 'end_date', 'total', 'status', 'notes' ) ),
					'tasks'      => $pick( $set['tasks'], array( 'uuid', 'title', 'description', 'status', 'created_at' ) ),
					'activities' => $pick( $set['activities'], array( 'uuid', 'entity_type', 'type', 'body', 'created_at' ) ),
				);
				AuditLog::record( 'customers.export', 'customers', $uuid, array( 'counts' => array( 'leads' => count( $out['leads'] ), 'deals' => count( $out['deals'] ), 'rentals' => count( $out['rentals'] ), 'tasks' => count( $out['tasks'] ), 'activities' => count( $out['activities'] ) ) ) );
				return $out;
			}
		);
	}

	public function archive( string $uuid ): void {
		parent::archive( $uuid );
	}
}
