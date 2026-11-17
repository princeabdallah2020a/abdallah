<?php
/**
 * Public shape of a dealer. Never exposes email, credentials, pairing data or internal ids.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Presenter;

defined( 'ABSPATH' ) || exit;

final class DealerPresenter {

	/** @param array<string,mixed> $row Dealer row. @return array<string,mixed> */
	public static function profile( array $row ): array {
		return array(
			'id'          => (string) $row['uuid'],
			'name'        => (string) $row['name'],
			'slug'        => (string) $row['slug'],
			'verified'    => (bool) (int) $row['verified'],
			'logo_url'    => self::url( $row['logo_url'] ?? null ),
			'website_url' => self::url( $row['website_url'] ?? null ),
			'phone'       => $row['phone'] ?? null,
			'whatsapp'    => $row['whatsapp'] ?? null,
			'description' => $row['description'] ?? null,
			'city'        => $row['city'] ?? null,
			'area'        => $row['area'] ?? null,
			'country'     => $row['country'] ?? null,
			'address'     => $row['address'] ?? null,
			'latitude'    => isset( $row['latitude'] ) ? (float) $row['latitude'] : null,
			'longitude'   => isset( $row['longitude'] ) ? (float) $row['longitude'] : null,
			'languages'   => ! empty( $row['languages'] ) ? explode( ',', (string) $row['languages'] ) : array(),
		);
	}

	/** Compact block embedded in listing cards (columns aliased dealer_* by the search query). */
	public static function embedded( array $row ): array {
		return array(
			'id'       => (string) ( $row['dealer_uuid'] ?? '' ),
			'name'     => (string) ( $row['dealer_name'] ?? '' ),
			'slug'     => (string) ( $row['dealer_slug'] ?? '' ),
			'verified' => (bool) (int) ( $row['dealer_verified'] ?? 0 ),
			'logo_url' => self::url( $row['dealer_logo'] ?? null ),
			'city'     => $row['dealer_city'] ?? null,
		);
	}

	private static function url( mixed $value ): ?string {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		$clean = esc_url_raw( $value, array( 'http', 'https' ) );
		return '' === $clean ? null : $clean;
	}
}
