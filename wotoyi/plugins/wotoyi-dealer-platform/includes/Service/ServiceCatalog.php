<?php
/**
 * Dealer services (maintenance, repair, rental with driver, import, detailing...). Published to the marketplace
 * as `service.*` events, same engine as vehicles.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Schema\ServiceFields;

defined( 'ABSPATH' ) || exit;

final class ServiceCatalog extends SyncedEntityService {

	/** @return array<string,mixed> */
	public static function definition(): array {
		return array(
			'fields'      => ServiceFields::defs(),
			'soft_delete' => true,
			'filters'     => array( 'status', 'category', 'city', 'mobile_service' ),
			'search'      => array( 'title', 'description' ),
			'sortable'    => array( 'created_at', 'updated_at', 'price_from', 'published_at' ),
		);
	}

	public function __construct() {
		parent::__construct( 'services', self::definition() );
	}

	protected function event_entity(): string {
		return 'service';
	}

	protected function sync_field_names(): array {
		return array_keys( ServiceFields::defs() );
	}

	protected function transitions(): array {
		return array(
			'draft'       => array( 'published', 'deleted' ),
			'published'   => array( 'unpublished', 'deleted' ),
			'unpublished' => array( 'published', 'deleted' ),
			'deleted'     => array(),
		);
	}

	protected function publish_problems( array $m ): array {
		$missing = array();
		if ( empty( $m['title'] ) ) {
			$missing[] = 'title';
		}
		if ( empty( $m['category'] ) ) {
			$missing[] = 'category';
		}
		return $missing;
	}

	public function present( array $row ): array {
		$out                 = parent::present( $row );
		$out['published_at'] = $row['published_at'] ?? null;
		return $out;
	}
}
