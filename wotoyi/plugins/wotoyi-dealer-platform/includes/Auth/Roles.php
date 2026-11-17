<?php
/**
 * Tenant roles and capabilities (plugin-internal: they are NOT WordPress roles, so a dealer user never gets
 * wp-admin rights; PROJECT.md §11 role model). Default deny: a capability not listed for a role is refused.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Auth;

defined( 'ABSPATH' ) || exit;

final class Roles {

	/** Capability a WordPress administrator needs to create tenants (platform operator, not a tenant role). */
	public const PLATFORM_CAP = 'wotoyi_dp_manage_platform';

	/** @return array<string,list<string>> role => capabilities */
	public static function matrix(): array {
		$crud = static fn( string $e ): array => array( $e . '.read', $e . '.write' );
		$sales = array_merge(
			array( 'vehicles.read', 'vehicles.write', 'vehicles.publish', 'services.read', 'dashboard.read', 'sync.read' ),
			$crud( 'customers' ),
			$crud( 'leads' ),
			$crud( 'deals' ),
			$crud( 'rentals' ),
			$crud( 'tasks' ),
			$crud( 'activities' )
		);
		$manager = array_merge(
			$sales,
			array( 'vehicles.delete', 'services.write', 'services.publish', 'services.delete', 'customers.delete', 'leads.delete', 'employees.read', 'employees.write', 'finance.read', 'sync.manage', 'audit.read', 'members.read' )
		);
		return array(
			'owner'      => array_merge( $manager, array( 'members.manage', 'settings.manage', 'customers.erase', 'employees.delete' ) ),
			'manager'    => $manager,
			'sales'      => $sales,
			'mechanic'   => array( 'vehicles.read', 'services.read', 'services.write', 'services.publish', 'tasks.read', 'tasks.write', 'activities.read', 'activities.write', 'dashboard.read', 'customers.read', 'rentals.read' ),
			'accountant' => array( 'vehicles.read', 'customers.read', 'deals.read', 'rentals.read', 'finance.read', 'dashboard.read', 'activities.read', 'audit.read', 'sync.read' ),
		);
	}

	/** @return list<string> */
	public static function caps( string $role ): array {
		return self::matrix()[ $role ] ?? array();
	}

	public static function can( ?string $role, string $cap ): bool {
		return null !== $role && in_array( $cap, self::caps( $role ), true );
	}

	/** @return list<string> Every capability that exists. */
	public static function all_caps(): array {
		return array_values( array_unique( array_merge( ...array_values( self::matrix() ) ) ) );
	}
}
