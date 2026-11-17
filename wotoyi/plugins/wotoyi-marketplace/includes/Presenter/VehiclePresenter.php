<?php
/**
 * Public JSON shape of vehicles. Private fields (VIN, plate) are never emitted.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Presenter;

use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Schema\VehicleFields;

defined( 'ABSPATH' ) || exit;

final class VehiclePresenter {

	/** Display tab for each catalogue group. */
	private const TAB_OF_GROUP = array(
		'identity'    => 'identity',
		'powertrain'  => 'engine',
		'consumption' => 'consumption',
		'dimensions'  => 'dimensions',
		'condition'   => 'history',
		'history'     => 'history',
		'pricing'     => 'commercial',
		'location'    => 'commercial',
		'media'       => 'media',
		'trust'       => 'trust',
	);

	/** @param array<string,mixed> $row Search row. @return array<string,mixed> */
	public static function card( array $row ): array {
		$defs = VehicleFields::defs();
		$row  = Fields::cast_row( $defs, $row );
		$out  = array(
			'id'           => (string) $row['uuid'],
			'slug'         => (string) $row['slug'],
			'title'        => self::title( $row ),
			'featured'     => (bool) (int) ( $row['featured'] ?? 0 ),
			'published_at' => $row['published_at'] ?? null,
			'photo_count'  => (int) ( $row['photo_count'] ?? 0 ),
			'has_video'    => (bool) (int) ( $row['has_video'] ?? 0 ),
			'has_360'      => (bool) (int) ( $row['has_360'] ?? 0 ),
		);
		foreach ( VehicleFields::CARD_FIELDS as $name ) {
			if ( 'title' === $name || ! array_key_exists( $name, $row ) || null === $row[ $name ] ) {
				continue;
			}
			$out[ $name ] = self::safe( $defs[ $name ], $row[ $name ] );
		}
		if ( isset( $row['distance_km'] ) ) {
			$out['distance_km'] = round( (float) $row['distance_km'], 1 );
		}
		$out['dealer'] = DealerPresenter::embedded( $row );
		return $out;
	}

	/**
	 * Detail page payload grouped in tabs; empty tabs are omitted.
	 *
	 * @param array<string,mixed>        $row       Full vehicle row joined with dealer aliases.
	 * @param array<string,list<string>> $equipment Equipment by category.
	 * @return array<string,mixed>
	 */
	public static function detail( array $row, array $equipment, string $lang = 'fr' ): array {
		$defs = VehicleFields::defs();
		$row  = Fields::cast_row( $defs, $row );
		$tabs = array();

		foreach ( $defs as $name => $def ) {
			if ( ! empty( $def['private'] ) || 'equipment' === $name || 'description_i18n' === $name ) {
				continue;
			}
			$value = $row[ $name ] ?? null;
			if ( null === $value || array() === $value ) {
				continue;
			}
			$tab                    = self::TAB_OF_GROUP[ $def['group'] ] ?? 'identity';
			$tabs[ $tab ][ $name ]  = self::safe( $def, $value );
		}

		// Localised description with fallback chain: lang -> fr -> en -> base text.
		$i18n = is_array( $row['description_i18n'] ?? null ) ? $row['description_i18n'] : array();
		foreach ( array( $lang, 'fr', 'en' ) as $candidate ) {
			if ( ! empty( $i18n[ $candidate ] ) ) {
				$tabs['identity']['description'] = (string) $i18n[ $candidate ];
				break;
			}
		}
		if ( $i18n ) {
			$tabs['identity']['available_languages'] = array_keys( $i18n );
		}

		if ( $equipment ) {
			$tabs['equipment'] = $equipment;
		}

		$tabs['trust']                      = ( $tabs['trust'] ?? array() ) + array( 'verified_dealer' => (bool) (int) ( $row['dealer_verified'] ?? 0 ), 'views' => (int) ( $row['views'] ?? 0 ) );
		$tabs['trust']['wotoyi_inspected']  = (bool) ( $row['wotoyi_inspected'] ?? false );

		return array(
			'id'           => (string) $row['uuid'],
			'slug'         => (string) $row['slug'],
			'title'        => self::title( $row ),
			'status'       => (string) $row['status'],
			'featured'     => (bool) (int) ( $row['featured'] ?? 0 ),
			'published_at' => $row['published_at'] ?? null,
			'tabs'         => $tabs,
			'dealer'       => DealerPresenter::profile(
				array(
					'uuid'        => $row['dealer_uuid'],
					'name'        => $row['dealer_name'],
					'slug'        => $row['dealer_slug'],
					'verified'    => $row['dealer_verified'],
					'logo_url'    => $row['dealer_logo'] ?? null,
					'website_url' => $row['dealer_website'] ?? null,
					'phone'       => $row['dealer_phone'] ?? null,
					'whatsapp'    => $row['dealer_whatsapp'] ?? null,
					'city'        => $row['dealer_city'] ?? null,
					'area'        => $row['dealer_area'] ?? null,
					'address'     => $row['dealer_address'] ?? null,
					'languages'   => $row['dealer_languages'] ?? null,
				)
			),
		);
	}

	private static function title( array $row ): string {
		if ( ! empty( $row['title'] ) ) {
			return (string) $row['title'];
		}
		return trim( implode( ' ', array_filter( array( $row['make'] ?? null, $row['model'] ?? null, $row['trim_level'] ?? null, isset( $row['year'] ) ? (string) $row['year'] : null ) ) ) );
	}

	/** Output hygiene (defence in depth): URLs re-validated, gallery items too. */
	private static function safe( array $def, mixed $value ): mixed {
		if ( 'url' === $def['type'] ) {
			$u = is_string( $value ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : '';
			return '' === $u ? null : $u;
		}
		if ( 'json_list' === $def['type'] && 'url' === ( $def['item'] ?? '' ) && is_array( $value ) ) {
			return array_values( array_filter( array_map( static fn( $u ) => is_string( $u ) ? esc_url_raw( $u, array( 'http', 'https' ) ) : '', $value ) ) );
		}
		return $value;
	}
}
