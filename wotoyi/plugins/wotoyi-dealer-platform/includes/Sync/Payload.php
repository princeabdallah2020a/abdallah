<?php
/**
 * Builds the marketplace event `data` from a local row (PROJECT.md §13.4 / marketplace README "Sync protocol").
 * Only catalogue fields are sent: dealer-only fields (purchase cost, internal notes, supplier, stock ref) and the
 * private identifiers (VIN, plate) NEVER leave the dealer platform.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Schema\Fields;
use Wotoyi\DealerPlatform\Schema\ServiceFields;
use Wotoyi\DealerPlatform\Schema\VehicleFields;

defined( 'ABSPATH' ) || exit;

final class Payload {

	/**
	 * Full snapshot (absent field = cleared on the marketplace).
	 *
	 * @param array<string,mixed> $row Raw DB row.
	 * @return array<string,mixed>
	 */
	public static function snapshot( string $entity_type, array $row ): array {
		$defs   = 'vehicle' === $entity_type ? VehicleFields::catalogue() : ServiceFields::defs();
		$fields = 'vehicle' === $entity_type ? VehicleFields::sync_fields() : array_keys( $defs );
		$cast   = Fields::cast_row( $defs, $row );
		$data   = array(
			'source_id' => (string) $row['uuid'],
			'version'   => (int) $row['version'],
			'status'    => (string) $row['status'],
		);
		foreach ( $fields as $f ) {
			if ( isset( $cast[ $f ] ) && '' !== $cast[ $f ] ) {
				$data[ $f ] = $cast[ $f ];
			}
		}
		$tenant = TenantContext::tenant();
		if ( ! isset( $data['currency'] ) && ( isset( $data['price'] ) || isset( $data['rental_price_day'] ) || isset( $data['rental_price_month'] ) || isset( $data['price_from'] ) ) ) {
			$data['currency'] = (string) $tenant['currency'];
		}
		if ( ! isset( $data['country'] ) && ! empty( $tenant['country'] ) ) {
			$data['country'] = (string) $tenant['country'];
		}
		if ( ! isset( $data['city'] ) && ! empty( $tenant['city'] ) ) {
			$data['city'] = (string) $tenant['city'];
		}
		return $data;
	}

	/**
	 * Lifecycle event data (unpublished / sold / deleted): identity + version only.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	public static function lifecycle( array $row ): array {
		return array( 'source_id' => (string) $row['uuid'], 'version' => (int) $row['version'] );
	}
}
