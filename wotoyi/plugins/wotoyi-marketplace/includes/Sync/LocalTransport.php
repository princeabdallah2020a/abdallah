<?php
/**
 * Local transport for the single-install demo (InfinityFree blocks server-to-server HTTP): the Dealer Platform
 * calls the receiver directly in PHP. Same Receiver code path as HTTP (validation, versioning, idempotency,
 * sync_log) — only the HMAC layer is replaced by "same PHP process" trust, so it is OFF by default and
 * refuses to run unless local mode is explicitly enabled.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

use Wotoyi\Marketplace\Repository\DealerRepository;
use Wotoyi\Marketplace\Settings;

defined( 'ABSPATH' ) || exit;

final class LocalTransport implements Transport {

	public function __construct( private DealerRepository $dealers, private Receiver $receiver ) {}

	public static function enabled(): bool {
		return (bool) Settings::get( 'local_mode' );
	}

	private function guard(): void {
		if ( ! self::enabled() ) {
			throw new \RuntimeException( 'local_mode_disabled' );
		}
	}

	/**
	 * Create or update the dealer used in demo mode (active immediately, no credentials, transport = local).
	 *
	 * @param array<string,mixed> $profile
	 * @return array<string,mixed> Safe dealer summary.
	 */
	public function ensure_dealer( string $source_dealer_uuid, array $profile ): array {
		$this->guard();
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.:\-]{1,64}$/', $source_dealer_uuid ) ) {
			throw new \InvalidArgumentException( 'invalid_dealer_id' );
		}
		$dealer = $this->dealers->find_by_source_uuid( $source_dealer_uuid );
		if ( $dealer && 'local' !== $dealer['transport'] ) {
			throw new \RuntimeException( 'dealer_is_not_local' ); // Never let local mode take over a paired HTTP dealer.
		}
		if ( ! $dealer ) {
			$dealer = $this->dealers->create( $profile + array( 'name' => $profile['name'] ?? 'Demo dealer' ), 'local', $source_dealer_uuid );
			if ( ! $dealer ) {
				throw new \RuntimeException( 'dealer_create_failed' );
			}
			$this->dealers->set_status( (int) $dealer['id'], 'active' );
		} else {
			$this->dealers->update_profile( (int) $dealer['id'], $profile );
		}
		$dealer = $this->dealers->find_by_id( (int) $dealer['id'] );
		return array(
			'id'                 => $dealer['uuid'],
			'source_dealer_uuid' => $dealer['source_dealer_uuid'],
			'name'               => $dealer['name'],
			'status'             => $dealer['status'],
			'transport'          => $dealer['transport'],
		);
	}

	public function send( string $dealer_ref, array $event ): array {
		return $this->receiver->handle_event( $this->resolve( $dealer_ref ), $event, 'local' );
	}

	/** @param list<array<string,mixed>> $events */
	public function send_batch( string $dealer_ref, array $events ): array {
		return $this->receiver->handle_batch( $this->resolve( $dealer_ref ), $events, 'local' );
	}

	/** @return list<array<string,mixed>> */
	public function pull_leads( string $dealer_ref, int $limit = 50 ): array {
		return $this->receiver->pull_leads( $this->resolve( $dealer_ref ), $limit );
	}

	/** @param list<string> $lead_uuids */
	public function ack_leads( string $dealer_ref, array $lead_uuids ): int {
		return $this->receiver->ack_leads( $this->resolve( $dealer_ref ), $lead_uuids );
	}

	/** @return array<string,mixed> */
	private function resolve( string $dealer_ref ): array {
		$this->guard();
		$dealer = $this->dealers->find_by_ref( $dealer_ref );
		if ( ! $dealer || 'local' !== $dealer['transport'] ) {
			throw new \RuntimeException( 'unknown_local_dealer' );
		}
		if ( 'active' !== $dealer['status'] ) {
			throw new \RuntimeException( 'dealer_inactive' );
		}
		return $dealer;
	}
}
