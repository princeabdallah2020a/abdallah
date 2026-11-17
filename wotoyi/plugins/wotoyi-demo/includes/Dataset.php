<?php
/**
 * Fictional demo content (French). Source of truth for the demo: mirrors the prototype (prototype/js/data.js),
 * mapped to the marketplace field catalogue. Nothing here is a real dealer, vehicle, price or contact.
 *
 * @package Wotoyi\Demo
 */

declare(strict_types=1);

namespace Wotoyi\Demo;

defined( 'ABSPATH' ) || exit;

final class Dataset {

	public const LABEL = 'démo';

	/** Prototype code => marketplace enum. */
	private const BODY   = array( 'suv' => 'suv', 'berline' => 'sedan', '4x4' => 'suv', 'pickup' => 'pickup' );
	private const FUEL   = array( 'essence' => 'petrol', 'diesel' => 'diesel', 'hybride' => 'hybrid' );
	private const TRANS  = array( 'auto' => 'automatic', 'manuelle' => 'manual' );
	private const COND   = array( 'occasion' => 'used', 'venant' => 'imported', 'neuf' => 'new' );
	private const CUSTOM = array( 'cleared' => 'cleared', 'transit' => 'in_transit' );
	private const COLOUR = array( 'gris' => 'grey', 'bleuatl' => 'blue', 'blancnacre' => 'white', 'ambre' => 'orange', 'vertsauge' => 'green', 'noirobs' => 'black', 'rougerubis' => 'red' );
	private const ORIGIN = array( 'Émirats arabes unis' => 'AE', 'Japon' => 'JP', 'France' => 'FR', 'Corée du Sud' => 'KR', 'Afrique du Sud' => 'ZA', 'Allemagne' => 'DE', 'Maroc' => 'MA' );
	/** City-level approximate coordinates (enables distance search). */
	private const CITY = array(
		'dakar'      => array( 'Dakar', 14.7167, -17.4677 ),
		'thies'      => array( 'Thiès', 14.7886, -16.9260 ),
		'mbour'      => array( 'Mbour', 14.4198, -16.9650 ),
		'saintlouis' => array( 'Saint-Louis', 16.0179, -16.4896 ),
	);
	private const EQ = array(
		'ac' => array( 'comfort', 'air_conditioning' ), 'heated' => array( 'comfort', 'heated_seats' ), 'sunroof' => array( 'exterior', 'sunroof' ),
		'cruise' => array( 'comfort', 'cruise_control' ), 'keyless' => array( 'comfort', 'keyless_entry' ), 'airbags' => array( 'safety', 'airbags_front' ),
		'abs' => array( 'safety', 'abs' ), 'esp' => array( 'safety', 'esp' ), 'camera' => array( 'driver_assistance', 'rear_camera' ),
		'sensors' => array( 'driver_assistance', 'parking_sensors_rear' ), 'lane' => array( 'driver_assistance', 'lane_assist' ),
		'carplay' => array( 'multimedia', 'carplay_android_auto' ), 'bt' => array( 'multimedia', 'bluetooth' ), 'gps' => array( 'multimedia', 'gps' ),
		'alloy' => array( 'exterior', 'alloy_wheels' ), 'led' => array( 'lighting', 'led_headlights' ), 'roofbars' => array( 'exterior', 'roof_rails' ),
		'leather' => array( 'interior', 'leather_seats' ), 'seats7' => array( 'interior', 'seven_seats' ),
	);

	/** Base URL of the theme's SVG illustrations (theme folder `wotoyi`). Filterable. */
	public static function image_base(): string {
		$base = rtrim( (string) get_theme_root_uri( 'wotoyi' ), '/' ) . '/wotoyi/assets/images/';
		return (string) apply_filters( 'wotoyi_demo_image_base_url', $base );
	}

	public static function theme_images_present(): bool {
		return is_dir( rtrim( (string) get_theme_root( 'wotoyi' ), '/' ) . '/wotoyi/assets/images' );
	}

	/** @return list<array{key:string,verified:bool,profile:array<string,mixed>}> */
	public static function dealers(): array {
		$mk = static fn( string $key, string $name, string $city, string $area, bool $verified, int $n, string $about ): array => array(
			'key'      => $key,
			'verified' => $verified,
			'profile'  => array(
				'name'        => $name . ' (' . self::LABEL . ')',
				'city'        => self::CITY[ $city ][0],
				'area'        => $area,
				'country'     => 'SN',
				'latitude'    => self::CITY[ $city ][1],
				'longitude'   => self::CITY[ $city ][2],
				'languages'   => 'fr,en,wo',
				'phone'       => '+221 00 000 00 0' . $n, // Obviously fake.
				'whatsapp'    => '+221 00 000 00 0' . $n,
				'email'       => 'demo-' . $key . '@example.com', // Reserved example domain.
				'description' => 'DÉMO — concessionnaire fictif. ' . $about,
			),
		);
		return array(
			$mk( 'almadies', 'Almadies Prestige', 'dakar', 'Almadies', true, 1, 'Véhicules premium et 4x4, inspectés avant mise en vente.' ),
			$mk( 'corniche', 'Corniche Auto Plus', 'dakar', 'Corniche', true, 2, 'SUV et hybrides, entretien et import.' ),
			$mk( 'thies', 'Thiès Mobilité', 'thies', 'Centre-ville', true, 3, 'Pick-up, berlines et véhicules venant de France.' ),
			$mk( 'petite', 'Petite-Côte Occasions', 'mbour', 'Mbour', false, 4, 'Occasions économiques et un véhicule neuf.' ),
		);
	}

	/** @return list<array<string,mixed>> Prototype vehicles (compact). */
	private static function raw_vehicles(): array {
		// id, make, model, trim, year, body, fuel, trans, drive, km, city, price, cond, customs, origin, owners, seats, doors, colour, image, dealer, neg, rent[day,month], eq, specs[cc,hp,torque,gears,cyl,length,width,height,wheelbase,tank], service(bool|null), accident-free, warranty, fin, trade, deliv, description
		return array(
			array( 1, 'Toyota', 'Land Cruiser Prado', '2.8 D-4D VX', 2021, '4x4', 'diesel', 'auto', '4x4', 68000, 'dakar', 38500000, 'occasion', 'cleared', 'Émirats arabes unis', 2, 7, 5, 'gris', 'hero-suv', 'almadies', true, null, array( 'ac', 'heated', 'sunroof', 'cruise', 'keyless', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'carplay', 'bt', 'gps', 'alloy', 'led', 'leather', 'seats7' ), array( 2755, 177, 450, 6, 4, 4780, 1885, 1845, 2790, 87 ), true, true, true, true, true, true, 'Quatre-roues-motrices de référence, entretenu en concession, deux propriétaires. Idéal pour la ville comme pour la piste.' ),
			array( 2, 'Toyota', 'Hilux', 'Double cabine 2.4 D-4D', 2022, 'pickup', 'diesel', 'manuelle', '4x4', 41000, 'dakar', 24900000, 'occasion', 'cleared', 'Japon', 1, 5, 4, 'bleuatl', 'pickup-atlantic', 'almadies', true, null, array( 'ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'bt', 'alloy', 'roofbars' ), array( 2393, 150, null, 6, null, null, null, null, null, null ), true, true, false, true, false, true, 'Pick-up double cabine, première main, benne protégée. Disponible pour essai à Dakar.' ),
			array( 3, 'Hyundai', 'Tucson', '1.6 CRDi Executive', 2020, 'suv', 'diesel', 'auto', 'fwd', 74000, 'thies', 15800000, 'venant', 'transit', 'France', 1, 5, 5, 'blancnacre', 'suv-pearl', 'thies', true, null, array( 'ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'carplay', 'bt', 'alloy', 'led' ), array( 1598, 136, null, null, null, null, null, null, null, null ), true, false, false, false, true, false, 'Véhicule venant de France, en cours de dédouanement. Carnet d’entretien disponible.' ),
			array( 4, 'Kia', 'Sportage', '2.0 Essence', 2019, 'suv', 'essence', 'auto', 'fwd', 88000, 'dakar', 13400000, 'occasion', 'cleared', 'Corée du Sud', 2, 5, 5, 'ambre', 'suv-amber', 'corniche', true, null, array( 'ac', 'airbags', 'abs', 'camera', 'bt', 'alloy' ), array( null, null, null, null, null, null, null, null, null, null ), false, false, false, false, true, true, 'SUV compact confortable, climatisation efficace, idéal pour un usage familial.' ),
			array( 5, 'Toyota', 'RAV4', 'Hybride 2.5 Dynamic', 2022, 'suv', 'hybride', 'auto', 'awd', 39000, 'dakar', 29500000, 'occasion', 'cleared', 'Japon', 1, 5, 5, 'vertsauge', 'suv-sage', 'corniche', false, array( 65000, 1500000 ), array( 'ac', 'heated', 'cruise', 'keyless', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'lane', 'carplay', 'bt', 'gps', 'alloy', 'led' ), array( 2487, 222, null, null, null, 4600, 1855, 1685, 2690, null ), true, true, true, true, true, true, 'Hybride essence-électrique, transmission intégrale, première main. Disponible à la vente et à la location.' ),
			array( 6, 'Toyota', 'Corolla', '1.8 Hybride', 2021, 'berline', 'hybride', 'auto', 'fwd', 52000, 'dakar', 16900000, 'occasion', 'cleared', 'Japon', 1, 5, 4, 'noirobs', 'sedan-obsidian', 'almadies', true, null, array( 'ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'carplay', 'bt', 'alloy', 'led', 'keyless' ), array( 1798, 122, null, null, null, null, null, null, null, null ), true, true, false, true, true, false, 'Berline hybride sobre en carburant, très demandée à Dakar. Entretien à jour.' ),
			array( 7, 'Hyundai', 'Accent', '1.4 Essence', 2018, 'berline', 'essence', 'manuelle', 'fwd', 97000, 'mbour', 6200000, 'occasion', 'cleared', 'Corée du Sud', 3, 5, 4, 'rougerubis', 'sedan-ruby', 'petite', true, null, array( 'ac', 'airbags', 'abs', 'bt' ), array( null, null, null, null, null, null, null, null, null, null ), null, false, false, false, false, true, 'Berline économique, simple et robuste. Visible à Mbour.' ),
			array( 8, 'Peugeot', '508', '2.0 BlueHDi GT Line', 2019, 'berline', 'diesel', 'auto', 'fwd', 81000, 'saintlouis', 14900000, 'venant', 'transit', 'France', 1, 5, 5, 'noirobs', 'sedan-obsidian', 'thies', true, array( 45000, 1100000 ), array( 'ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'lane', 'carplay', 'bt', 'gps', 'alloy', 'led', 'leather', 'keyless' ), array( 1997, 177, null, 8, null, null, null, null, null, null ), true, false, false, true, false, false, 'Berline de direction au design soigné, venant de France. Location possible avec ou sans chauffeur.' ),
			array( 9, 'Nissan', 'X-Trail', '2.5 Essence', 2020, 'suv', 'essence', 'auto', 'awd', 63000, 'dakar', 14200000, 'occasion', 'cleared', 'Japon', 2, 7, 5, 'ambre', 'suv-amber', 'corniche', true, array( 50000, 1200000 ), array( 'ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'bt', 'alloy', 'seats7' ), array( 2488, 171, null, null, null, null, null, null, null, null ), true, false, false, false, true, true, 'SUV 7 places, transmission intégrale. Pratique pour les familles et les déplacements en groupe.' ),
			array( 10, 'Ford', 'Ranger', 'Wildtrak 3.2 TDCi', 2020, 'pickup', 'diesel', 'auto', '4x4', 92000, 'thies', 22500000, 'occasion', 'cleared', 'Afrique du Sud', 2, 5, 4, 'bleuatl', 'pickup-atlantic', 'thies', true, null, array( 'ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'carplay', 'bt', 'gps', 'alloy', 'leather', 'roofbars' ), array( 3198, 200, null, null, null, null, null, null, null, null ), true, false, false, true, false, true, 'Pick-up haut de gamme Wildtrak, prêt pour le travail comme pour le week-end.' ),
			array( 11, 'Mercedes-Benz', 'GLC', '220 d 4MATIC', 2019, 'suv', 'diesel', 'auto', 'awd', 77000, 'dakar', 31800000, 'occasion', 'cleared', 'Allemagne', 1, 5, 5, 'blancnacre', 'suv-pearl', 'almadies', true, null, array( 'ac', 'heated', 'sunroof', 'cruise', 'keyless', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'lane', 'carplay', 'bt', 'gps', 'alloy', 'led', 'leather' ), array( 1950, 194, null, 9, null, null, null, null, null, null ), true, true, true, true, true, true, 'SUV premium, sellerie cuir, toit ouvrant. Présenté comme exemple de véhicule haut de gamme.' ),
			array( 12, 'Renault', 'Duster', '1.5 dCi Prestige', 2024, 'suv', 'diesel', 'manuelle', 'fwd', 10, 'mbour', 12400000, 'neuf', 'cleared', 'Maroc', 0, 5, 5, 'vertsauge', 'suv-sage', 'petite', false, null, array( 'ac', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'bt', 'alloy' ), array( 1461, 115, null, null, null, null, null, null, null, null ), null, false, true, true, false, true, 'Véhicule neuf, garantie constructeur selon conditions du concessionnaire.' ),
		);
	}

	/**
	 * Marketplace event payloads (`data` part) per dealer key.
	 *
	 * @return array<string,list<array{source_id:string,data:array<string,mixed>}>>
	 */
	public static function vehicles(): array {
		$img = self::image_base();
		$out = array();
		foreach ( self::raw_vehicles() as $r ) {
			[ $id, $make, $model, $trim, $year, $body, $fuel, $trans, $drive, $km, $city, $price, $cond, $customs, $origin, $owners, $seats, $doors, $colour, $image, $dealer, $neg, $rent, $eq, $sp, $service, $accident_free, $warranty, $fin, $trade, $deliv, $desc ] = $r;
			$equipment = array();
			foreach ( $eq as $code ) {
				$equipment[ self::EQ[ $code ][0] ][] = self::EQ[ $code ][1];
			}
			$data = array(
				'title'                => "{$make} {$model} {$year} (" . self::LABEL . ')',
				'make'                 => $make,
				'model'                => $model,
				'trim_level'           => $trim,
				'year'                 => $year,
				'body_type'            => self::BODY[ $body ],
				'fuel_type'            => self::FUEL[ $fuel ],
				'transmission'         => self::TRANS[ $trans ],
				'drive_type'           => $drive,
				'mileage_km'           => $km,
				'condition_type'       => self::COND[ $cond ],
				'customs_status'       => self::CUSTOM[ $customs ],
				'origin_country'       => self::ORIGIN[ $origin ],
				'owners_count'         => $owners,
				'seats'                => $seats,
				'doors'                => $doors,
				'ext_colour'           => self::COLOUR[ $colour ],
				'offer_type'           => $rent ? 'both' : 'sale',
				'price'                => $price,
				'currency'             => 'XOF',
				'price_negotiable'     => $neg,
				'financing_available'  => $fin,
				'trade_in_accepted'    => $trade,
				'delivery_available'   => $deliv,
				'availability'         => 'available',
				'city'                 => self::CITY[ $city ][0],
				'country'              => 'SN',
				'latitude'             => self::CITY[ $city ][1],
				'longitude'            => self::CITY[ $city ][2],
				'equipment'            => $equipment,
				'cover_image'          => $img . $image . '.svg',
				'gallery'              => array( $img . $image . '.svg' ),
				'description'          => 'DÉMO — annonce fictive. ' . $desc,
			);
			if ( $rent ) {
				$data['rental_price_day']   = $rent[0];
				$data['rental_price_month'] = $rent[1];
				$data['rental_min_days']    = 1;
			}
			foreach ( array( 'engine_cc', 'power_hp', 'torque_nm', 'gears', 'cylinders', 'length_mm', 'width_mm', 'height_mm', 'wheelbase_mm', 'tank_l' ) as $i => $col ) {
				if ( null !== $sp[ $i ] ) {
					$data[ $col ] = $sp[ $i ];
				}
			}
			if ( null !== $service ) {
				$data['service_book'] = $service ? 'full' : 'none';
			}
			if ( $accident_free ) {
				$data['accident_free'] = true;
			}
			if ( $warranty ) {
				$data['warranty'] = true;
			}
			$out[ $dealer ][] = array( 'source_id' => sprintf( 'demo-veh-%02d', $id ), 'data' => $data );
		}
		return $out;
	}

	/** @return array<string,list<array{source_id:string,data:array<string,mixed>}>> */
	public static function services(): array {
		$img  = self::image_base();
		$tag  = ' (' . self::LABEL . ')';
		$base = array( 'currency' => 'XOF', 'country' => 'SN', 'languages' => 'fr,en' );
		$loc  = static fn( string $c ): array => array( 'city' => self::CITY[ $c ][0], 'latitude' => self::CITY[ $c ][1], 'longitude' => self::CITY[ $c ][2] );
		return array(
			'corniche' => array(
				array( 'source_id' => 'demo-svc-01', 'data' => $base + $loc( 'dakar' ) + array( 'title' => 'Entretien et vidange complète' . $tag, 'category' => 'maintenance', 'description' => 'DÉMO — service fictif. Vidange, filtres, contrôle des niveaux et diagnostic de base.', 'features' => array( 'Diagnostic de base', 'Pièces d’origine ou équivalentes', 'Devis avant intervention' ), 'price_from' => 35000, 'price_unit' => 'fixed', 'duration_minutes' => 90, 'cover_image' => $img . 'suv-amber.svg' ) ),
				array( 'source_id' => 'demo-svc-03', 'data' => $base + $loc( 'dakar' ) + array( 'title' => 'Accompagnement import et dédouanement' . $tag, 'category' => 'import', 'description' => 'DÉMO — service fictif. Aide à l’import d’un véhicule : choix, transport et formalités.', 'features' => array( 'Sélection du véhicule', 'Suivi du transport', 'Formalités de dédouanement' ), 'price_on_request' => true, 'cover_image' => $img . 'suv-pearl.svg' ) ),
			),
			'almadies' => array(
				array( 'source_id' => 'demo-svc-02', 'data' => $base + $loc( 'dakar' ) + array( 'title' => 'Location avec chauffeur à Dakar' . $tag, 'category' => 'rental_with_driver', 'description' => 'DÉMO — service fictif. Chauffeur professionnel pour vos déplacements en ville et vers l’aéroport.', 'features' => array( 'Chauffeur francophone', 'Véhicule climatisé', 'Réservation par WhatsApp' ), 'price_from' => 60000, 'price_unit' => 'day', 'mobile_service' => true, 'cover_image' => $img . 'sedan-obsidian.svg' ) ),
			),
			'thies'    => array(
				array( 'source_id' => 'demo-svc-04', 'data' => $base + $loc( 'thies' ) + array( 'title' => 'Lavage et detailing intérieur et extérieur' . $tag, 'category' => 'detailing', 'description' => 'DÉMO — service fictif. Nettoyage complet, lustrage et protection de la carrosserie.', 'features' => array( 'Nettoyage intérieur', 'Lustrage', 'Protection carrosserie' ), 'price_from' => 15000, 'price_unit' => 'fixed', 'duration_minutes' => 120, 'cover_image' => $img . 'sedan-ruby.svg' ) ),
			),
		);
	}
}
