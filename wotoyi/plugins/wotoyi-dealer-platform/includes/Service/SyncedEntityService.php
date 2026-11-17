<?php
/**
 * Base for entities that are published to the marketplace (vehicles, services): status machine, publish rules,
 * and the outbox event written in the same transaction as the change.
 *
 * Event rules (marketplace README "Event envelope"):
 *   status becomes published (from anything)      -> <type>.published  (full snapshot)
 *   status becomes unpublished / sold / deleted   -> <type>.unpublished | vehicle.sold | <type>.deleted  (lifecycle)
 *       (if catalogue fields changed in the same update: one `.updated` snapshot carrying the new status instead)
 *   fields change while published/unpublished/sold-> <type>.updated  (full snapshot incl. status)
 *   drafts and never-published rows               -> no event (nothing exists on the marketplace)
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Sync\Outbox;
use Wotoyi\DealerPlatform\Sync\Payload;

defined( 'ABSPATH' ) || exit;

abstract class SyncedEntityService extends EntityService {

	/** @return string 'vehicle' | 'service' */
	abstract protected function event_entity(): string;

	/** @return array<string,list<string>> status => allowed next statuses */
	abstract protected function transitions(): array;

	/**
	 * Return the list of missing/invalid things that block publishing (empty = publishable).
	 *
	 * @param array<string,mixed> $merged
	 * @return list<string>
	 */
	abstract protected function publish_problems( array $merged ): array;

	protected function soft_delete(): bool {
		return true;
	}

	/** @return list<string> Catalogue fields that are sent to the marketplace. */
	abstract protected function sync_field_names(): array;

	protected function system_columns( array $merged ): array {
		$status = (string) ( $merged['status'] ?? 'draft' );
		return array(
			'status'       => $status,
			'published_at' => 'published' === $status ? Clock::mysql() : null,
		) + ( isset( $merged['sold_at'] ) ? array( 'sold_at' => $merged['sold_at'] ) : array() )
		  + ( isset( $merged['sold_price'] ) ? array( 'sold_price' => $merged['sold_price'] ) : array() );
	}

	protected function defaults(): array {
		return array( 'status' => 'draft' );
	}

	/**
	 * API create: `status` may be draft (default) or published.
	 *
	 * @param array<string,mixed> $input
	 */
	public function create( array $input, array $system = array() ): array {
		$status = $input['status'] ?? 'draft';
		if ( ! is_string( $status ) || ! in_array( $status, array( 'draft', 'published' ), true ) ) {
			throw ApiException::invalid( 'A new record can only be created as draft or published.', array( 'fields' => array( 'status' ) ) );
		}
		return parent::create( $input, array( 'status' => $status ) + $system );
	}

	/**
	 * API update: field changes and an optional `status` change in ONE transaction, one version bump, one event.
	 *
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $system
	 */
	public function update( string $uuid, array $input, array $system = array() ): array {
		$new = $this->tx(
			function () use ( $uuid, $input, $system ): array {
				$old    = $this->row( $uuid );
				$target = $input['status'] ?? null;
				unset( $input['status'] );
				if ( null !== $target ) {
					if ( ! is_string( $target ) || ! array_key_exists( $target, $this->transitions() ) ) {
						throw ApiException::invalid( 'Unknown status.', array( 'fields' => array( 'status' ) ) );
					}
					if ( $target !== $old['status'] ) {
						$this->assert_transition( (string) $old['status'], $target );
						$system = $this->status_columns( $old, $target ) + $system;
					}
				}
				return $this->do_update( $old, $input, $system );
			}
		);
		Outbox::after_commit();
		return $this->present( $new );
	}

	public function archive( string $uuid ): void {
		$this->tx(
			function () use ( $uuid ): void {
				$old = $this->row( $uuid );
				$this->assert_can_delete( $old );
				$this->assert_transition( (string) $old['status'], 'deleted' );
				$this->do_update( $old, array(), $this->status_columns( $old, 'deleted' ) );
				AuditLog::record( $this->entity . '.delete', $this->entity, $uuid );
			}
		);
		Outbox::after_commit();
	}

	/** Convenience used by actions and other services. @return array<string,mixed> presented */
	public function transition( string $uuid, string $status, array $system = array() ): array {
		return $this->update( $uuid, array( 'status' => $status ), $system );
	}

	/** Hook: block deleting rows still in use. @param array<string,mixed> $row */
	protected function assert_can_delete( array $row ): void {}

	protected function assert_transition( string $from, string $to ): void {
		if ( ! in_array( $to, $this->transitions()[ $from ] ?? array(), true ) ) {
			throw ApiException::conflict( 'wotoyi_invalid_transition', "Cannot change status from {$from} to {$to}.", array( 'from' => $from, 'to' => $to ) );
		}
	}

	/**
	 * @param array<string,mixed> $old
	 * @return array<string,mixed> Columns to set with the status change.
	 */
	protected function status_columns( array $old, string $target ): array {
		$cols = array( 'status' => $target );
		if ( 'published' === $target && empty( $old['published_at'] ) ) {
			$cols['published_at'] = Clock::mysql();
		}
		if ( 'deleted' === $target ) {
			$cols['deleted_at'] = Clock::mysql();
		}
		return $cols;
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( 'published' === ( $merged['status'] ?? 'draft' ) ) {
			$problems = $this->publish_problems( $merged );
			if ( $problems ) {
				throw new ApiException( 'wotoyi_not_publishable', 'The record is not complete enough to publish.', 422, array( 'missing' => $problems ) );
			}
		}
		return null;
	}

	protected function after_create( array $row ): void {
		$this->emit( null, $row );
	}

	protected function after_update( array $old, array $new ): void {
		$this->emit( $old, $new );
	}

	/**
	 * @param array<string,mixed>|null $old
	 * @param array<string,mixed>      $new
	 */
	private function emit( ?array $old, array $new ): void {
		$type    = $this->event_entity();
		$status  = (string) $new['status'];
		$version = (int) $new['version'];
		$uuid    = (string) $new['uuid'];
		$was     = $old['status'] ?? null;
		$ever    = ! empty( $new['published_at'] );
		$fields  = array_intersect( $this->last_changed, $this->sync_field_names() );

		if ( 'draft' === $status || ! $ever ) {
			return; // Nothing exists on the marketplace yet.
		}
		if ( $was === $status && ! $fields && null !== $old ) {
			return; // Only dealer-only fields changed: nothing to tell the marketplace.
		}
		if ( 'deleted' === $status ) {
			Outbox::enqueue( $type, $uuid, $type . '.deleted', $version, Payload::lifecycle( $new ) );
			return;
		}
		if ( 'published' === $status && $was !== 'published' ) {
			Outbox::enqueue( $type, $uuid, $type . '.published', $version, Payload::snapshot( $type, $new ) );
			return;
		}
		if ( $was !== $status && null !== $old && ! $fields && in_array( $status, array( 'unpublished', 'sold' ), true ) ) {
			Outbox::enqueue( $type, $uuid, $type . '.' . $status, $version, Payload::lifecycle( $new ) );
			return;
		}
		Outbox::enqueue( $type, $uuid, $type . '.updated', $version, Payload::snapshot( $type, $new ) );
	}
}
