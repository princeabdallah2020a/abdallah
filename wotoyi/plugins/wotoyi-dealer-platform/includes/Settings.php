<?php
/**
 * Plugin settings (option `wotoyi_dp_settings`); security-relevant switches can be pinned by constants.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'wotoyi_dp_settings';

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'local_mode'            => false,  // Single-install demo: push to the marketplace in the same PHP process.
			'inline_local_push'     => true,   // In local mode, push right after the DB commit (instant demo).
			'batch_size'            => 50,     // Events per signed batch request (receiver maximum is 50).
			'batch_max_bytes'       => 800000, // Receiver limit is 1 MB; stay below it.
			'max_attempts'          => 12,     // Then the event becomes `dead` until someone requeues it.
			'lock_ttl'              => 120,    // Seconds an event stays claimed by a worker.
			'lead_pull_interval'    => 120,    // Seconds between automatic lead pulls per tenant.
			'outbox_retention_days' => 30,     // Sent events older than this are pruned.
			'per_page_max'          => 100,
			'delete_on_uninstall'   => false,
		);
	}

	public static function get( string $key ): mixed {
		if ( 'local_mode' === $key && defined( 'WOTOYI_DP_LOCAL_MODE' ) ) {
			return (bool) WOTOYI_DP_LOCAL_MODE;
		}
		$stored   = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		$value    = is_array( $stored ) && array_key_exists( $key, $stored ) ? $stored[ $key ] : ( $defaults[ $key ] ?? null );
		return apply_filters( 'wotoyi_dp_setting_' . $key, $value );
	}

	public static function int( string $key ): int {
		return (int) self::get( $key );
	}

	/** Test/HTTP escape hatch: allow plain http or private hosts for the marketplace URL (development only). */
	public static function allow_insecure_marketplace(): bool {
		return defined( 'WOTOYI_DP_ALLOW_INSECURE_MARKETPLACE' ) && true === WOTOYI_DP_ALLOW_INSECURE_MARKETPLACE;
	}

	/**
	 * Optional fixed marketplace origin (wp-config.php: define( 'WOTOYI_DP_MARKETPLACE_URL', 'https://wotoyi.com' )).
	 * When set, pairing can only ever target this origin (SECURITY-REVIEW M1); the dealer need not type a URL.
	 */
	public static function pinned_marketplace_url(): ?string {
		return defined( 'WOTOYI_DP_MARKETPLACE_URL' ) && is_string( WOTOYI_DP_MARKETPLACE_URL ) && '' !== WOTOYI_DP_MARKETPLACE_URL ? rtrim( WOTOYI_DP_MARKETPLACE_URL, '/' ) : null;
	}
}
