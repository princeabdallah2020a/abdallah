<?php
/**
 * Tasks and follow-up reminders. `completed_at` is set by the system when a task is marked done.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class TaskService extends EntityService {

	public function __construct() {
		parent::__construct( 'tasks', Entities::get( 'tasks' ) );
	}

	protected function defaults(): array {
		return array( 'status' => 'open', 'priority' => 'normal' );
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( ! empty( $merged['related_uuid'] ) || ! empty( $merged['related_type'] ) ) {
			if ( empty( $merged['related_type'] ) || empty( $merged['related_uuid'] ) || ! Related::exists( (string) $merged['related_type'], (string) $merged['related_uuid'] ) ) {
				throw new ApiException( 'wotoyi_invalid_ref', 'Referenced record not found.', 422, array( 'fields' => array( 'related_uuid' ) ) );
			}
		}
		return null;
	}

	public function update( string $uuid, array $input, array $system = array() ): array {
		$row = $this->row( $uuid );
		if ( isset( $input['status'] ) && 'done' === $input['status'] && 'done' !== $row['status'] ) {
			$system['completed_at'] = Clock::mysql();
		} elseif ( isset( $input['status'] ) && 'open' === $input['status'] && 'done' === $row['status'] ) {
			$system['completed_at'] = null;
		}
		return parent::update( $uuid, $input, $system );
	}

	/** @return array<string,mixed> */
	public function complete( string $uuid ): array {
		return $this->update( $uuid, array( 'status' => 'done' ) );
	}
}
