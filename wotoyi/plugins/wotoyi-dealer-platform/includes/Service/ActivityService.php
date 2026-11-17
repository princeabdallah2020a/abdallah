<?php
/**
 * Timeline entries (notes, calls, stage changes, sync notices). Append-only: there is no update or delete.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

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
		return parent::create( $input, array( 'author_id' => TenantContext::user_id() ) + $system );
	}

	public function update( string $uuid, array $input, array $system = array() ): array {
		throw new ApiException( 'wotoyi_method_not_allowed', 'Timeline entries cannot be edited.', 405 );
	}

	public function archive( string $uuid ): void {
		throw new ApiException( 'wotoyi_method_not_allowed', 'Timeline entries cannot be deleted.', 405 );
	}
}
