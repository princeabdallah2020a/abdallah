<?php
/**
 * Timeline entries (notes, calls, stage changes, sync notices). Append-only: there is no update or delete.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Auth\Roles;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;

defined( 'ABSPATH' ) || exit;

final class ActivityService extends EntityService {

	public function __construct() {
		parent::__construct( 'activities', Entities::get( 'activities' ) );
	}

	protected function defaults(): array {
		return array( 'type' => 'note' );
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( ! Related::exists( (string) $merged['entity_type'], (string) $merged['entity_uuid'] ) ) {
			throw new ApiException( 'wotoyi_invalid_ref', 'Referenced record not found.', 422, array( 'fields' => array( 'entity_uuid' ) ) );
		}
		if ( empty( $merged['body'] ) && 'note' === ( $merged['type'] ?? 'note' ) ) {
			throw ApiException::invalid( 'A note needs a body.', array( 'fields' => array( 'body' ) ) );
		}
		return null;
	}

	/**
	 * M2: a timeline entry is only visible to a caller who may read the record it belongs to. A request for a type the
	 * caller cannot read is refused (403); with no type given the query is restricted to the readable types.
	 * System work (no acting member: worker, internal calls) is not restricted.
	 *
	 * @param array<string,mixed> $q
	 * @return array<string,mixed>
	 */
	protected function build_where( array $q ): array {
		$where = parent::build_where( $q );
		$role  = TenantContext::role();
		if ( null === $role ) {
			return $where;
		}
		$readable = array_keys( array_filter( Related::TABLES, static fn( string $table ): bool => Roles::can( $role, $table . '.read' ) ) );
		if ( isset( $where['entity_type'] ) ) {
			$wanted = is_array( $where['entity_type'] ) ? (array) $where['entity_type'][1] : array( $where['entity_type'] );
			foreach ( $wanted as $type ) {
				if ( ! in_array( $type, $readable, true ) ) {
					throw ApiException::forbidden();
				}
			}
			return $where;
		}
		$where['entity_type'] = array( 'in', $readable );
		return $where;
	}

	/** Internal: write a system/stage_change/sync entry (type allowed). @return array<string,mixed> */
	public function log( string $entity_type, string $entity_uuid, string $type, string $body ): array {
		return parent::create( array( 'entity_type' => $entity_type, 'entity_uuid' => $entity_uuid, 'type' => $type, 'body' => $body ), array( 'author_id' => TenantContext::user_id() ) );
	}

	/** API create (type limited to what a human can log). The author is always the caller. */
	public function create( array $input, array $system = array() ): array {
		$type = $input['type'] ?? 'note';
		if ( ! in_array( $type, array( 'note', 'call', 'whatsapp', 'email', 'visit' ), true ) ) {
			throw ApiException::invalid( 'Unsupported activity type.', array( 'fields' => array( 'type' ) ) );
		}
		$role = TenantContext::role();
		$kind = isset( $input['entity_type'] ) && is_string( $input['entity_type'] ) ? ( Related::TABLES[ $input['entity_type'] ] ?? null ) : null;
		if ( null !== $role && null !== $kind && ! Roles::can( $role, $kind . '.read' ) ) {
			throw ApiException::forbidden(); // Cannot annotate (or probe the existence of) a record the role cannot read.
		}
		return parent::create( $input, array( 'author_id' => TenantContext::user_id() ) + $system );
	}

	public function update( string $uuid, array $input, array $system = array() ): array {
		throw new ApiException( 'wotoyi_method_not_allowed', 'Timeline entries cannot be edited.', 405 );
	}

	public function archive( string $uuid ): void {
		throw new ApiException( 'wotoyi_method_not_allowed', 'Timeline entries cannot be deleted.', 405 );
	}
}
