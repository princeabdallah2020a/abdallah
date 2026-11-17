<?php
/**
 * Public JSON shape of services.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Presenter;

use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Schema\ServiceFields;

defined( 'ABSPATH' ) || exit;

final class ServicePresenter {

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	public static function item( array $row, bool $full = false, string $lang = 'fr' ): array {
		$defs = ServiceFields::defs();
		$row  = Fields::cast_row( $defs, $row );
		$out  = array(
			'id'           => (string) $row['uuid'],
			'slug'         => (string) $row['slug'],
			'featured'     => (bool) (int) ( $row['featured'] ?? 0 ),
			'published_at' => $row['published_at'] ?? null,
		);
		foreach ( $defs as $name => $def ) {
			if ( ( ! $full && in_array( $name, array( 'description', 'description_i18n', 'features', 'gallery' ), true ) ) || 'description_i18n' === $name ) {
				continue;
			}
			$value = $row[ $name ] ?? null;
			if ( null === $value ) {
				continue;
			}
			if ( 'url' === $def['type'] ) {
				$value = esc_url_raw( (string) $value, array( 'http', 'https' ) );
				if ( '' === $value ) {
					continue;
				}
			}
			if ( 'json_list' === $def['type'] && 'url' === ( $def['item'] ?? '' ) && is_array( $value ) ) {
				$value = array_values( array_filter( array_map( static fn( $u ) => is_string( $u ) ? esc_url_raw( $u, array( 'http', 'https' ) ) : '', $value ) ) );
			}
			$out[ $name ] = $value;
		}
		if ( $full ) {
			$i18n = is_array( $row['description_i18n'] ?? null ) ? $row['description_i18n'] : array();
			foreach ( array( $lang, 'fr', 'en' ) as $candidate ) {
				if ( ! empty( $i18n[ $candidate ] ) ) {
					$out['description'] = (string) $i18n[ $candidate ];
					break;
				}
			}
		}
		if ( isset( $row['distance_km'] ) ) {
			$out['distance_km'] = round( (float) $row['distance_km'], 1 );
		}
		$out['dealer'] = DealerPresenter::embedded( $row );
		return $out;
	}
}
