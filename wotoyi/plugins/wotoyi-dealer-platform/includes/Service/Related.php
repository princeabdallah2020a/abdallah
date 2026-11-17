<?php
/**
 * Resolves "type + uuid" references (activities, tasks) to a live row of the CURRENT tenant.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantDb;

defined( 'ABSPATH' ) || exit;

final class Related {

	public const TABLES = array(
		'customer' => 'customers',
		'lead'     => 'leads',
		'vehicle'  => 'vehicles',
		'service'  => 'services',
		'deal'     => 'deals',
		'rental'   => 'rentals',
	);

	public static function exists( string $type, string $uuid ): bool {
		if ( ! isset( self::TABLES[ $type ] ) ) {
			return false;
		}
		$where = array( 'uuid' => $uuid );
		if ( in_array( $type, array( 'customer', 'lead', 'vehicle', 'service' ), true ) ) {
			$where['deleted_at'] = array( 'null' );
		}
		return null !== TenantDb::get( self::TABLES[ $type ], $where );
	}
}
