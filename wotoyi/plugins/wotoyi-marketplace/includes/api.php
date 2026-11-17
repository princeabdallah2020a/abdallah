<?php
/**
 * Public PHP API for sibling plugins (e.g. the Dealer Platform in single-install demo mode).
 *
 * @package Wotoyi\Marketplace
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wotoyi_marketplace_local_receive' ) ) {
	/**
	 * Deliver one signed-event-shaped array to the marketplace receiver without HTTP.
	 * Works only when local mode is enabled (setting or WOTOYI_MP_LOCAL_MODE); throws otherwise.
	 *
	 * @param string              $dealer_ref Marketplace dealer UUID or the Dealer Platform's dealer id.
	 * @param array<string,mixed> $event      {event_id, event_type, data, ...} (see README).
	 * @return array<string,mixed> Receiver result.
	 */
	function wotoyi_marketplace_local_receive( string $dealer_ref, array $event ): array {
		return \Wotoyi\Marketplace\Plugin::local_transport()->send( $dealer_ref, $event );
	}
}

if ( ! function_exists( 'wotoyi_marketplace_local_register_dealer' ) ) {
	/**
	 * Create or update the dealer record for local (demo) mode. Throws when local mode is disabled.
	 *
	 * @param string              $source_dealer_uuid Dealer Platform dealer id.
	 * @param array<string,mixed> $profile            name, city, phone, ...
	 * @return array<string,mixed> Dealer row (without secrets).
	 */
	function wotoyi_marketplace_local_register_dealer( string $source_dealer_uuid, array $profile ): array {
		return \Wotoyi\Marketplace\Plugin::local_transport()->ensure_dealer( $source_dealer_uuid, $profile );
	}
}
