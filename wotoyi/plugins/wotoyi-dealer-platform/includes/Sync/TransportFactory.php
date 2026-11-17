<?php
/**
 * Builds the transport for the current tenant from its sync state.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class TransportFactory {

	/** @throws SyncException */
	public static function make( ?array $state = null ): Transport {
		$state ??= SyncState::get();
		if ( 'local' === $state['transport'] ) {
			return new LocalTransport( (string) ( $state['marketplace_dealer_id'] ?: TenantContext::uuid() ) );
		}
		if ( 'http' !== $state['transport'] || empty( $state['base_url'] ) || empty( $state['key_id'] ) ) {
			throw new SyncException( 'not_paired', 'Not paired with a marketplace.', false, 0, true );
		}
		if ( ! Crypto::key_available() ) {
			throw new SyncException( 'secret_key_missing', 'WOTOYI_SECRET_KEY is not defined; the stored secret cannot be decrypted.', false, 0, true );
		}
		$secret = Crypto::decrypt( $state['secret_enc'] ?? null );
		if ( null === $secret || '' === $secret ) {
			throw new SyncException( 'secret_unreadable', 'The stored marketplace secret cannot be decrypted (key changed?). Pair again.', false, 0, true );
		}
		return new HttpTransport( (string) $state['base_url'], (string) $state['key_id'], $secret );
	}
}
