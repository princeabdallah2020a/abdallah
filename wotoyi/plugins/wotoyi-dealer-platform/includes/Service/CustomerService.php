<?php
/**
 * Customers (CRM contacts). Includes duplicate matching by phone/email and personal-data erasure.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Entities;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class CustomerService extends EntityService {

	public function __construct() {
		parent::__construct( 'customers', Entities::get( 'customers' ) );
	}

	protected function defaults(): array {
		return array( 'country' => \Wotoyi\DealerPlatform\Data\TenantContext::tenant()['country'] ?? null );
	}

	protected function finalize( array $merged, ?array $old ): ?array {
		if ( empty( $merged['phone'] ) && empty( $merged['email'] ) && empty( $merged['whatsapp'] ) ) {
			throw ApiException::invalid( 'A customer needs a phone, WhatsApp number or email.', array( 'fields' => array( 'phone', 'email' ) ) );
		}
		return null;
	}

	/** Existing live customer of this tenant with the same phone or email, or null. @return array<string,mixed>|null */
	public function find_match( ?string $phone, ?string $email ): ?array {
		if ( $phone ) {
			$row = TenantDb::get( 'customers', array( 'phone' => $phone, 'deleted_at' => array( 'null' ) ) );
			if ( $row ) {
				return $row;
			}
		}
		if ( $email ) {
			return TenantDb::get( 'customers', array( 'email' => strtolower( $email ), 'deleted_at' => array( 'null' ) ) );
		}
		return null;
	}

	/**
	 * Right to erasure: anonymise the customer and the personal data copied into their leads. Rows are kept (deals and
	 * statistics stay consistent) but no name, contact, address, note or message survives. Returns what was touched.
	 *
	 * @return array<string,int>
	 */
	public function erase( string $uuid ): array {
		$counts = TenantDb::transaction(
			function () use ( $uuid ): array {
				$this->row( $uuid );
				$now = Clock::mysql();
				TenantDb::update( 'customers', array( 'uuid' => $uuid ), array( 'name' => 'Erased', 'phone' => null, 'whatsapp' => null, 'email' => null, 'address' => null, 'city' => null, 'notes' => null, 'tags' => null, 'consent' => 0, 'deleted_at' => $now, 'updated_at' => $now, 'version' => array( '@incr' => 1 ) ) );
				$leads = TenantDb::update( 'leads', array( 'customer_uuid' => $uuid ), array( 'name' => null, 'phone' => null, 'email' => null, 'message' => null, 'updated_at' => $now, 'version' => array( '@incr' => 1 ) ) );
				$acts  = TenantDb::update( 'activities', array( 'entity_type' => 'customer', 'entity_uuid' => $uuid ), array( 'body' => '[erased]' ) );
				AuditLog::record( 'customers.erase', 'customers', $uuid );
				return array( 'customers' => 1, 'leads' => $leads, 'activities' => $acts );
			}
		);
		return $counts;
	}

	public function archive( string $uuid ): void {
		parent::archive( $uuid );
	}
}
