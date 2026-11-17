<?php
/**
 * Delivery channel to the marketplace receiver. HttpTransport = signed HTTPS (production);
 * LocalTransport = same-process calls (single-install demo). Both end in the same marketplace Receiver.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

defined( 'ABSPATH' ) || exit;

interface Transport {

	public function name(): string;

	/**
	 * Send events in order. Returns the receiver result of each event keyed by event_id.
	 *
	 * @param list<array<string,mixed>> $events
	 * @return array<string,array<string,mixed>>
	 * @throws SyncException When the whole request failed.
	 */
	public function send_batch( array $events ): array;

	/**
	 * Reconciliation manifest page.
	 *
	 * @return array{items:list<array{source_id:string,version:int,status:string}>,next:int|null}
	 * @throws SyncException
	 */
	public function manifest( string $type, int $cursor, int $limit ): array;

	/** @return list<array<string,mixed>> Pending marketplace leads of this dealer. @throws SyncException */
	public function pull_leads( int $limit ): array;

	/** @param list<string> $lead_uuids @return int Acknowledged count. @throws SyncException */
	public function ack_leads( array $lead_uuids ): int;
}
