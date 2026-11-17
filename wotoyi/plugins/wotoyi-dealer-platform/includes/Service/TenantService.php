<?php
/**
 * Tenant (dealer company) lifecycle: create with its first owner, read/update the profile.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Support\ApiException;

defined( 'ABSPATH' ) || exit;

final class TenantService {

	public function __construct( private MemberService $members ) {}

	/**
	 * Platform operator action: create a dealer account and its first owner.
	 *
	 * @param array<string,mixed> $data name, slug?, country?, city?, currency?, locale?
	 * @return array<string,mixed>
	 */
	public function create( array $data, int $owner_user_id ): array {
		if ( $owner_user_id <= 0 || null === get_userdata( $owner_user_id ) || false === get_userdata( $owner_user_id ) ) {
			throw ApiException::invalid( 'owner_user_id must be an existing user.', array( 'fields' => array( 'owner_user_id' ) ) );
		}
		try {
			$tenant = Directory::create_tenant( $data );
		} catch ( \InvalidArgumentException $e ) {
			throw ApiException::invalid( 'A dealer name is required.', array( 'fields' => array( 'name' ) ) );
		}
		TenantContext::run(
			$tenant,
			function () use ( $owner_user_id ): void {
				$m = $this->members->insert( $owner_user_id, 'owner', null );
				AuditLog::record( 'tenant.create', 'tenants', TenantContext::uuid(), array( 'owner' => $m['uuid'] ) );
			}
		);
		return $this->present( $tenant );
	}

	/** @return array<string,mixed> */
	public function present( array $t ): array {
		return array(
			'id'       => $t['uuid'],
			'name'     => $t['name'],
			'slug'     => $t['slug'],
			'status'   => $t['status'],
			'plan'     => $t['plan'],
			'country'  => $t['country'],
			'city'     => $t['city'],
			'currency' => $t['currency'],
			'locale'   => $t['locale'],
		);
	}

	/**
	 * Owner action: edit the dealer profile (name, city, country, currency, locale).
	 *
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	public function update_current( array $data ): array {
		Directory::update_profile( TenantContext::id(), $data );
		AuditLog::record( 'tenant.update', 'tenants', TenantContext::uuid(), array( 'fields' => array_keys( array_intersect_key( $data, array_flip( array( 'name', 'city', 'country', 'currency', 'locale' ) ) ) ) ) );
		return $this->present( (array) Directory::find_by_id( TenantContext::id() ) );
	}

	/** @return array<string,mixed> Current tenant. */
	public function current(): array {
		return $this->present( TenantContext::tenant() );
	}
}
