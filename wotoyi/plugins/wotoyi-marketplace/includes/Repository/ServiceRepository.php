<?php
/**
 * Service persistence.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Schema\ServiceFields;

defined( 'ABSPATH' ) || exit;

final class ServiceRepository extends AbstractListingRepository {

	protected function logical(): string {
		return 'services';
	}

	public function defs(): array {
		return ServiceFields::defs();
	}

	protected function derive( array $clean ): array {
		$text = strtolower( implode( ' ', array_filter( array(
			$clean['title'] ?? null, $clean['category'] ?? null, $clean['city'] ?? null, $clean['area'] ?? null,
		) ) ) );
		return array( 'search_text' => mb_substr( $text, 0, 1000 ) );
	}

	protected function slug_base( array $clean ): string {
		return sanitize_title( (string) ( $clean['title'] ?? '' ) );
	}
}
