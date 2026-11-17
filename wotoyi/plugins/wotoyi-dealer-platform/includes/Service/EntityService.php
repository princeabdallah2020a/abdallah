<?php
/**
 * Generic tenant-scoped CRUD service driven by an entity definition (Schema\Entities). Everything goes through
 * Data\TenantDb, so a service cannot read or write another tenant's rows. Business rules live in subclasses
 * (hooks: finalize, after_create, after_update, after_archive).
 *
 * Contract: input is untrusted. Only fields declared in the definition are written; readonly fields and unknown
 * keys are reported as warnings; `ref` fields must point to a live row of the SAME tenant (else 422 invalid_ref).
 * Optimistic concurrency: an optional `version` in the input must match the stored version (else 409).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Auth\Roles;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Fields;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Uuid;
use Wotoyi\DealerPlatform\Sync\Outbox;

defined( 'ABSPATH' ) || exit;

class EntityService {

	protected const ENVELOPE = array( 'id', 'uuid', 'version', 'tenant_id', 'created_at', 'updated_at', 'deleted_at', 'status_changed' );

	/** @var list<string> Field names changed by the last update (names only). */
	protected array $last_changed = array();

	/** @var list<string> Warnings of the last create/update. */
	protected array $last_warnings = array();

	/** @param array<string,mixed> $def fields, filters, search, sortable, soft_delete */
	public function __construct( protected string $entity, protected array $def ) {}

	public function entity(): string {
		return $this->entity;
	}

	/** @return list<string> */
	public function warnings(): array {
		return $this->last_warnings;
	}

	/* ------------------------------------------------------------------ read */

	/**
	 * @param array<string,mixed> $q
	 * @return array<string,mixed>
	 */
	public function list( array $q = array() ): array {
		$where = $this->build_where( $q );
		$per   = max( 1, min( 100, (int) ( $q['per_page'] ?? 25 ) ) );
		$page  = max( 1, (int) ( $q['page'] ?? 1 ) );
		$sort  = isset( $q['orderby'] ) && in_array( $q['orderby'], $this->def['sortable'] ?? array(), true ) ? (string) $q['orderby'] : 'created_at';
		$dir   = isset( $q['order'] ) && 'asc' === strtolower( (string) $q['order'] ) ? 'asc' : 'desc';
		$total = TenantDb::count( $this->entity, $where );
		$rows  = TenantDb::select( $this->entity, $where, array( 'order' => array( array( $sort, $dir ), array( 'id', $dir ) ), 'limit' => $per, 'offset' => ( $page - 1 ) * $per ) );
		return array(
			'items'    => array_map( fn( array $r ): array => $this->present( $r ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per,
			'pages'    => (int) ceil( $total / $per ),
		);
	}

	/** @return array<string,mixed> */
	public function get( string $uuid ): array {
		return $this->present( $this->row( $uuid ) );
	}

	/** Raw live row (not deleted) of the current tenant or 404. @return array<string,mixed> */
	public function row( string $uuid ): array {
		$where = array( 'uuid' => $uuid );
		if ( $this->soft_delete() ) {
			$where['deleted_at'] = array( 'null' );
		}
		$row = 1 === preg_match( '/^[0-9a-f\-]{36}$/', $uuid ) ? TenantDb::get( $this->entity, $where ) : null;
		if ( ! $row ) {
			throw ApiException::not_found( ucfirst( rtrim( $this->entity, 's' ) ) );
		}
		return $row;
	}

	protected function soft_delete(): bool {
		return ! empty( $this->def['soft_delete'] );
	}

	/**
	 * @param array<string,mixed> $q
	 * @return array<string,mixed>
	 */
	protected function build_where( array $q ): array {
		$where = array();
		if ( $this->soft_delete() ) {
			$where['deleted_at'] = array( 'null' );
		}
		foreach ( $this->def['filters'] ?? array() as $f ) {
			if ( ! isset( $q[ $f ] ) || ! is_string( $q[ $f ] ) || '' === $q[ $f ] ) {
				continue;
			}
			$vals = array_slice( array_filter( array_map( 'trim', explode( ',', $q[ $f ] ) ), static fn( string $v ): bool => '' !== $v ), 0, 20 );
			if ( count( $vals ) > 1 ) {
				$where[ $f ] = array( 'in', $vals );
			} elseif ( $vals ) {
				$where[ $f ] = $vals[0];
			}
		}
		if ( isset( $q['q'] ) && is_string( $q['q'] ) && '' !== trim( $q['q'] ) && ! empty( $this->def['search'] ) ) {
			$where['_search'] = array( $this->def['search'], $q['q'] );
		}
		return $where;
	}

	/**
	 * Public JSON shape: uuid as `id`, typed values, no internal columns.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	public function present( array $row ): array {
		$row = Fields::cast_row( $this->def['fields'], $row );
		$out = array( 'id' => $row['uuid'], 'version' => (int) $row['version'] );
		if ( isset( $row['status'] ) ) {
			$out['status'] = $row['status'];
		}
		foreach ( $this->def['fields'] as $name => $fd ) {
			if ( ! empty( $fd['finance'] ) && ! $this->can_finance() ) {
				continue;
			}
			$out[ $name ] = $row[ $name ] ?? null;
		}
		foreach ( array( 'created_at', 'updated_at' ) as $c ) {
			$out[ $c ] = $row[ $c ] ?? null;
		}
		return $out;
	}

	protected function can_finance(): bool {
		return null === TenantContext::role() || Roles::can( TenantContext::role(), 'finance.read' );
	}

	/* ----------------------------------------------------------------- write */

	/**
	 * @param array<string,mixed> $input  Untrusted API input.
	 * @param array<string,mixed> $system Trusted internal column values (readonly fields, status...).
	 * @return array<string,mixed> Presented row.
	 */
	public function create( array $input, array $system = array() ): array {
		$res = $this->tx(
			function () use ( $input, $system ): array {
				$san = Fields::sanitize_partial( $this->def['fields'], $input, self::ENVELOPE + array( 'status' ) );
				if ( $san['invalid'] ) {
					throw ApiException::invalid( 'Invalid values.', array( 'fields' => $san['invalid'] ) );
				}
				$this->last_warnings = $san['warnings'];
				$clean               = $san['clean'];
				$this->check_required( $clean );
				$this->check_refs( $clean );
				$defaults = $this->defaults();
				$merged   = array_merge( $defaults, $clean, $system );
				foreach ( $defaults as $k => $v ) {
					if ( null === $merged[ $k ] ) {
						$merged[ $k ] = $v; // Explicit null on create means "use the default".
					}
				}
				$merged = $this->finalize( $merged, null ) ?? $merged;
				$now    = Clock::mysql();
				$data   = Fields::to_db( $this->all_defs(), $merged ) + $this->system_columns( $merged );
				$uuid   = Uuid::v7();
				TenantDb::insert(
					$this->entity,
					array(
						'uuid'       => $uuid,
						'version'    => 1,
						'created_by' => TenantContext::user_id() ?: null,
						'created_at' => $now,
						'updated_at' => $now,
					) + $data
				);
				$row = $this->row_any( $uuid );
				$this->after_create( $row );
				AuditLog::record( $this->entity . '.create', $this->entity, $uuid, array( 'fields' => array_keys( $clean ) ) );
				return $row;
			}
		);
		Outbox::after_commit();
		return $this->present( $res );
	}

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $system Trusted internal columns to set in the same UPDATE (status, timestamps...).
	 * @return array<string,mixed>
	 */
	public function update( string $uuid, array $input, array $system = array() ): array {
		$new = $this->tx(
			function () use ( $uuid, $input, $system ): array {
				$old = $this->row( $uuid );
				$new = $this->do_update( $old, $input, $system );
				return $new;
			}
		);
		Outbox::after_commit();
		return $this->present( $new );
	}

	/**
	 * @param array<string,mixed> $old
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $system
	 * @return array<string,mixed> New raw row.
	 */
	protected function do_update( array $old, array $input, array $system ): array {
		if ( array_key_exists( 'version', $input ) && (int) $input['version'] !== (int) $old['version'] ) {
			throw ApiException::conflict( 'wotoyi_version_conflict', 'The record was changed by someone else. Reload and retry.', array( 'current_version' => (int) $old['version'] ) );
		}
		$san = Fields::sanitize_partial( $this->def['fields'], $input, self::ENVELOPE + array( 'status' ) );
		if ( $san['invalid'] ) {
			throw ApiException::invalid( 'Invalid values.', array( 'fields' => $san['invalid'] ) );
		}
		$this->last_warnings = $san['warnings'];
		$clean               = $san['clean'];
		foreach ( $this->def['fields'] as $name => $fd ) {
			if ( ! empty( $fd['required'] ) && array_key_exists( $name, $clean ) && null === $clean[ $name ] ) {
				throw ApiException::invalid( 'Invalid values.', array( 'fields' => array( $name ) ) );
			}
		}
		foreach ( $this->defaults() as $name => $default ) {
			if ( null !== $default && array_key_exists( $name, $clean ) && null === $clean[ $name ] && 'enum' === ( $this->def['fields'][ $name ]['type'] ?? '' ) ) {
				throw ApiException::invalid( 'This field cannot be cleared.', array( 'fields' => array( $name ) ) );
			}
		}
		$this->check_refs( $clean );

		$old_cast = Fields::cast_row( $this->all_defs(), $old );
		$merged   = array_merge( $old_cast, $clean, $system );
		$merged   = $this->finalize( $merged, $old_cast ) ?? $merged;

		// Only columns that really change.
		$changed = array();
		foreach ( $clean as $k => $v ) {
			if ( ( $old_cast[ $k ] ?? null ) != $v ) { // phpcs:ignore Universal.Operators.StrictComparisons -- DB strings vs typed values.
				$changed[] = $k;
			}
		}
		$sys_changed = array();
		foreach ( $system as $k => $v ) {
			if ( ( $old[ $k ] ?? null ) != $v ) { // phpcs:ignore Universal.Operators.StrictComparisons
				$sys_changed[ $k ] = $v;
			}
		}
		$this->last_changed = $changed;
		if ( ! $changed && ! $sys_changed ) {
			return $old;
		}
		$data = Fields::to_db( $this->all_defs(), array_intersect_key( $merged, array_flip( $changed ) ) ) + $sys_changed;
		$data = $data + $this->derived_columns( $merged, $changed );
		$data['updated_at'] = Clock::mysql();
		$data['version']    = array( '@incr' => 1 );
		$n = TenantDb::update( $this->entity, array( 'uuid' => $old['uuid'], 'version' => (int) $old['version'] ), $data );
		if ( 1 !== $n ) {
			throw ApiException::conflict( 'wotoyi_version_conflict', 'The record was changed by someone else. Reload and retry.' );
		}
		$new = $this->row_any( (string) $old['uuid'] );
		$this->after_update( $old, $new );
		AuditLog::record( $this->entity . '.update', $this->entity, (string) $old['uuid'], array( 'fields' => array_merge( $changed, array_keys( $sys_changed ) ) ) );
		return $new;
	}

	/** Soft delete (or status=deleted for synced entities). */
	public function archive( string $uuid ): void {
		$this->tx(
			function () use ( $uuid ): void {
				$row = $this->row( $uuid );
				$n   = TenantDb::update( $this->entity, array( 'uuid' => $uuid, 'deleted_at' => array( 'null' ) ), array( 'deleted_at' => Clock::mysql(), 'updated_at' => Clock::mysql(), 'version' => array( '@incr' => 1 ) ) );
				if ( 1 !== $n ) {
					throw ApiException::not_found();
				}
				$this->after_archive( $row );
				AuditLog::record( $this->entity . '.delete', $this->entity, $uuid );
			}
		);
		Outbox::after_commit();
	}

	/* ----------------------------------------------------------------- hooks */

	/** @return array<string,mixed> Default values applied on create. */
	protected function defaults(): array {
		return array();
	}

	/**
	 * Business validation / derived values on the final state. May return a modified array.
	 *
	 * @param array<string,mixed>      $merged Final values (typed).
	 * @param array<string,mixed>|null $old    Previous values (typed) or null on create.
	 * @return array<string,mixed>|null
	 */
	protected function finalize( array $merged, ?array $old ): ?array {
		return null;
	}

	/** @param array<string,mixed> $merged @return array<string,mixed> Non-field columns for INSERT. */
	protected function system_columns( array $merged ): array {
		return array();
	}

	/** @param array<string,mixed> $merged @param list<string> $changed @return array<string,mixed> Non-field columns for UPDATE. */
	protected function derived_columns( array $merged, array $changed ): array {
		return array();
	}

	/** @param array<string,mixed> $row */
	protected function after_create( array $row ): void {}

	/** @param array<string,mixed> $old @param array<string,mixed> $new */
	protected function after_update( array $old, array $new ): void {}

	/** @param array<string,mixed> $row */
	protected function after_archive( array $row ): void {}

	/* --------------------------------------------------------------- helpers */

	/** @return array<string,array> Field definitions used for column conversion. */
	protected function all_defs(): array {
		return $this->def['fields'];
	}

	/** Row by uuid including soft-deleted. @return array<string,mixed> */
	protected function row_any( string $uuid ): array {
		$row = TenantDb::find_uuid( $this->entity, $uuid );
		if ( ! $row ) {
			throw new \RuntimeException( 'row_vanished' );
		}
		return $row;
	}

	/** @param array<string,mixed> $clean */
	protected function check_required( array $clean ): void {
		$missing = array();
		foreach ( $this->def['fields'] as $name => $fd ) {
			if ( ! empty( $fd['required'] ) && ( ! array_key_exists( $name, $clean ) || null === $clean[ $name ] ) ) {
				$missing[] = $name;
			}
		}
		if ( $missing ) {
			throw ApiException::invalid( 'Missing required fields.', array( 'fields' => $missing ) );
		}
	}

	/**
	 * Every ref must exist in the CURRENT tenant (this is what stops "attach tenant B's customer to my deal").
	 *
	 * @param array<string,mixed> $clean
	 */
	protected function check_refs( array $clean ): void {
		$bad = array();
		foreach ( $this->def['fields'] as $name => $fd ) {
			if ( 'ref' !== $fd['type'] || empty( $fd['entity'] ) || empty( $clean[ $name ] ) ) {
				continue;
			}
			$where = array( 'uuid' => $clean[ $name ] );
			if ( in_array( $fd['entity'], array( 'customers', 'leads', 'employees', 'vehicles', 'services' ), true ) ) {
				$where['deleted_at'] = array( 'null' );
			}
			if ( null === TenantDb::get( $fd['entity'], $where ) ) {
				$bad[] = $name;
			}
		}
		if ( $bad ) {
			throw new ApiException( 'wotoyi_invalid_ref', 'Referenced record not found.', 422, array( 'fields' => $bad ) );
		}
	}

	/** Run in a transaction, then flush the sync hint after the outermost commit. */
	protected function tx( callable $fn ): mixed {
		return TenantDb::transaction( $fn );
	}
}
