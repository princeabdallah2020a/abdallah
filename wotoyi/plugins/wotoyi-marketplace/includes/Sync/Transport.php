<?php
/**
 * Delivery channel from the Dealer Platform to the marketplace receiver.
 * HttpTransport (production: signed HTTPS) and LocalTransport (single-install demo) share the same Receiver.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

defined( 'ABSPATH' ) || exit;

interface Transport {

	/**
	 * Deliver one event and return the receiver result (status, http_status, entity, warnings).
	 *
	 * @param string              $dealer_ref Dealer identifier (ignored by HttpTransport: the key identifies the dealer).
	 * @param array<string,mixed> $event      Event envelope.
	 * @return array<string,mixed>
	 */
	public function send( string $dealer_ref, array $event ): array;
}
