<?php
/**
 * Demo data: fictional dealers, vehicles and testimonials for previewing the
 * marketplace. Every record is flagged (is_demo / _amc_demo) and labelled
 * "Demo" on the public site, and can be removed in one click.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Demo_Data {

	/**
	 * Import (idempotent: existing demo data is replaced).
	 *
	 * @return array { dealers: int, vehicles: int }
	 */
	public static function import() {
		self::remove();

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'amc-demo';
		$url    = trailingslashit( $upload['baseurl'] ) . 'amc-demo';
		wp_mkdir_p( $dir );

		$dealer_ids = array();
		foreach ( self::dealers() as $key => $dealer ) {
			$logo = 'logo-' . $key . '.svg';
			file_put_contents( $dir . '/' . $logo, self::logo_svg( $dealer['business_name'], $dealer['color'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$dealer['logo_url']  = $url . '/' . $logo;
			$dealer['status']    = 'active';
			$dealer['sync_mode'] = 'manual';
			$dealer['is_demo']   = 1;
			$id                  = Dealers::create( $dealer );
			if ( ! is_wp_error( $id ) ) {
				$dealer_ids[ $key ] = $id;
			}
		}

		$count = 0;
		foreach ( self::vehicles() as $index => $v ) {
			list( $dealer_key, $brand, $model, $year, $km, $fuel, $gearbox, $body, $condition, $color_name, $hex, $type, $price, $period, $status, $featured ) = $v;
			if ( ! isset( $dealer_ids[ $dealer_key ] ) ) {
				continue;
			}
			$dealer = self::dealers()[ $dealer_key ];
			$media  = array();
			foreach ( array( 'studio', 'city', 'detail' ) as $n => $scene ) {
				$file = sprintf( 'vehicle-%02d-%d.svg', $index + 1, $n + 1 );
				file_put_contents( $dir . '/' . $file, self::vehicle_svg( $body, $hex, $scene ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$media[] = array(
					'url'    => $url . '/' . $file,
					/* translators: 1: brand, 2: model, 3: view number */
					'alt'    => sprintf( __( '%1$s %2$s — view %3$d (demo illustration)', 'automotive-marketplace-core' ), $brand, $model, $n + 1 ),
					'width'  => 1200,
					'height' => 800,
				);
			}

			$id = Vehicles::upsert(
				$dealer_ids[ $dealer_key ],
				array(
					'external_id'       => 'demo-' . ( $index + 1 ),
					'brand'             => $brand,
					'model'             => $model,
					'year'              => $year,
					'mileage_km'        => $km,
					'fuel_type'         => $fuel,
					'transmission'      => $gearbox,
					'body_type'         => $body,
					'vehicle_condition' => $condition,
					'color'             => $color_name,
					'listing_type'      => $type,
					'price_amount'      => $price,
					'price_period'      => $period,
					'currency'          => 'XOF',
					'city'              => $dealer['city'],
					'country_code'      => 'SN',
					'description'       => self::description( $brand, $model, $type ),
					'features'          => self::features( $index ),
					'thumbnail_url'     => $media[0]['url'],
					'status'            => $status,
					'is_featured'       => $featured,
					'is_demo'           => 1,
					'published_at'      => gmdate( 'Y-m-d H:i:s', time() - $index * 7 * HOUR_IN_SECONDS ),
					'media'             => $media,
				)
			);
			if ( ! is_wp_error( $id ) ) {
				++$count;
			}
		}

		foreach ( self::testimonials() as $testimonial ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'amc_testimonial',
					'post_status'  => 'publish',
					'post_title'   => $testimonial[0],
					'post_content' => $testimonial[2],
				)
			);
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_amc_role', $testimonial[1] );
				update_post_meta( $post_id, '_amc_demo', 1 );
			}
		}

		Vehicles::flush_cache();
		return array(
			'dealers'  => count( $dealer_ids ),
			'vehicles' => $count,
		);
	}

	/** Remove every demo record and generated file. */
	public static function remove() {
		global $wpdb;
		$v = Database::table( 'vehicles' );
		$m = Database::table( 'vehicle_media' );
		$d = Database::table( 'dealers' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM $m WHERE vehicle_id IN (SELECT id FROM $v WHERE is_demo = 1)" );
		$wpdb->query( "DELETE FROM $v WHERE is_demo = 1" );
		$wpdb->query( "DELETE FROM $d WHERE is_demo = 1" );
		// phpcs:enable

		$posts = get_posts(
			array(
				'post_type'      => 'amc_testimonial',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_amc_demo', // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'amc-demo';
		if ( is_dir( $dir ) ) {
			foreach ( (array) glob( $dir . '/*.svg' ) as $file ) {
				wp_delete_file( $file );
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		}
		Vehicles::flush_cache();
	}

	private static function dealers() {
		// Phone numbers are deliberately invalid placeholders.
		return array(
			'teranga'  => array(
				'business_name' => 'Teranga Motors',
				'description'   => 'Concessionnaire fictif utilisé pour la démonstration. Spécialiste des berlines et SUV récents, importés et contrôlés.',
				'address'       => 'Route de l’Aéroport, Yoff',
				'city'          => 'Dakar',
				'phone'         => '+221000000001',
				'whatsapp'      => '+221000000001',
				'website_url'   => 'https://teranga-motors.example',
				'is_verified'   => 1,
				'color'         => '#0E1726',
			),
			'almadies' => array(
				'business_name' => 'Almadies Auto',
				'description'   => 'Concessionnaire fictif utilisé pour la démonstration. Véhicules premium et 4x4, vente et reprise.',
				'address'       => 'Route des Almadies',
				'city'          => 'Dakar',
				'phone'         => '+221000000002',
				'whatsapp'      => '+221000000002',
				'website_url'   => 'https://almadies-auto.example',
				'is_verified'   => 1,
				'color'         => '#CF3E1A',
			),
			'plateau'  => array(
				'business_name' => 'Plateau Car Center',
				'description'   => 'Concessionnaire fictif utilisé pour la démonstration. Citadines et berlines économiques au cœur de Dakar.',
				'address'       => 'Avenue Lamine Guèye, Plateau',
				'city'          => 'Dakar',
				'phone'         => '+221000000003',
				'whatsapp'      => '+221000000003',
				'website_url'   => 'https://plateau-car-center.example',
				'is_verified'   => 1,
				'color'         => '#1F6F5C',
			),
			'ndakaaru' => array(
				'business_name' => 'Ndakaaru Location',
				'description'   => 'Agence fictive utilisée pour la démonstration. Location de véhicules avec ou sans chauffeur, courte et longue durée.',
				'address'       => 'VDN, Sacré-Cœur 3',
				'city'          => 'Dakar',
				'phone'         => '+221000000004',
				'whatsapp'      => '+221000000004',
				'website_url'   => 'https://ndakaaru-location.example',
				'is_verified'   => 1,
				'color'         => '#27467A',
			),
			'thies'    => array(
				'business_name' => 'Thiès Auto Services',
				'description'   => 'Concessionnaire fictif utilisé pour la démonstration. Pickups et utilitaires pour les professionnels.',
				'address'       => 'Avenue Léopold Sédar Senghor',
				'city'          => 'Thiès',
				'phone'         => '+221000000005',
				'whatsapp'      => '+221000000005',
				'website_url'   => '',
				'is_verified'   => 0,
				'color'         => '#6B4E16',
			),
			'saly'     => array(
				'business_name' => 'Saly Drive',
				'description'   => 'Agence fictive utilisée pour la démonstration. Location de véhicules sur la Petite-Côte.',
				'address'       => 'Route de Saly',
				'city'          => 'Mbour',
				'phone'         => '+221000000006',
				'whatsapp'      => '+221000000006',
				'website_url'   => '',
				'is_verified'   => 0,
				'color'         => '#5B2A6E',
			),
		);
	}

	/**
	 * Dealer, brand, model, year, km, fuel, gearbox, body, condition, colour name, hex,
	 * listing type, price (XOF), period, status, featured.
	 */
	private static function vehicles() {
		return array(
			array( 'teranga', 'Toyota', 'Corolla', 2019, 62000, 'petrol', 'automatic', 'sedan', 'imported', 'Gris', '#8A9099', 'sale', 9500000, 'total', 'active', 1 ),
			array( 'teranga', 'Toyota', 'RAV4', 2020, 48000, 'hybrid', 'automatic', 'suv', 'imported', 'Blanc', '#E9EBEE', 'sale', 18500000, 'total', 'active', 1 ),
			array( 'teranga', 'Hyundai', 'Tucson', 2021, 35000, 'petrol', 'automatic', 'suv', 'imported', 'Bleu', '#27467A', 'sale', 16900000, 'total', 'active', 0 ),
			array( 'teranga', 'Kia', 'Sportage', 2018, 89000, 'diesel', 'automatic', 'suv', 'used', 'Noir', '#1B1E24', 'sale', 11200000, 'total', 'reserved', 0 ),
			array( 'teranga', 'Toyota', 'Camry', 2020, 54000, 'petrol', 'automatic', 'sedan', 'imported', 'Noir', '#1B1E24', 'sale', 14800000, 'total', 'active', 0 ),
			array( 'teranga', 'Hyundai', 'Elantra', 2019, 71000, 'petrol', 'automatic', 'sedan', 'imported', 'Rouge', '#A3261E', 'sale', 8200000, 'total', 'active', 0 ),
			array( 'almadies', 'Mercedes-Benz', 'Classe C', 2020, 41000, 'petrol', 'automatic', 'sedan', 'imported', 'Noir', '#1B1E24', 'sale', 24500000, 'total', 'active', 1 ),
			array( 'almadies', 'BMW', 'X5', 2019, 67000, 'diesel', 'automatic', 'suv', 'imported', 'Gris', '#5E646D', 'sale', 32000000, 'total', 'active', 1 ),
			array( 'almadies', 'Toyota', 'Land Cruiser Prado', 2021, 29000, 'diesel', 'automatic', 'suv', 'imported', 'Blanc', '#E9EBEE', 'sale', 38500000, 'total', 'active', 1 ),
			array( 'almadies', 'Range Rover', 'Evoque', 2018, 78000, 'diesel', 'automatic', 'suv', 'imported', 'Blanc', '#E9EBEE', 'sale', 21000000, 'total', 'active', 0 ),
			array( 'almadies', 'Audi', 'A4', 2019, 58000, 'petrol', 'automatic', 'sedan', 'imported', 'Bleu', '#27467A', 'sale', 17500000, 'total', 'active', 0 ),
			array( 'almadies', 'Mercedes-Benz', 'GLE', 2022, 18000, 'hybrid', 'automatic', 'suv', 'new', 'Gris', '#8A9099', 'sale', 52000000, 'total', 'active', 0 ),
			array( 'almadies', 'BMW', 'Série 3', 2017, 96000, 'diesel', 'automatic', 'sedan', 'used', 'Gris', '#5E646D', 'sale', 12500000, 'total', 'sold', 0 ),
			array( 'plateau', 'Peugeot', '208', 2020, 43000, 'petrol', 'manual', 'hatchback', 'imported', 'Rouge', '#A3261E', 'sale', 6900000, 'total', 'active', 0 ),
			array( 'plateau', 'Renault', 'Clio', 2019, 66000, 'diesel', 'manual', 'hatchback', 'imported', 'Bleu', '#27467A', 'sale', 5800000, 'total', 'active', 0 ),
			array( 'plateau', 'Dacia', 'Sandero', 2021, 31000, 'petrol', 'manual', 'hatchback', 'used', 'Gris', '#8A9099', 'sale', 5400000, 'total', 'active', 0 ),
			array( 'plateau', 'Peugeot', '301', 2018, 92000, 'diesel', 'manual', 'sedan', 'used', 'Blanc', '#E9EBEE', 'sale', 4900000, 'total', 'active', 0 ),
			array( 'plateau', 'Suzuki', 'Swift', 2022, 15000, 'petrol', 'automatic', 'hatchback', 'new', 'Rouge', '#A3261E', 'sale', 8900000, 'total', 'active', 1 ),
			array( 'plateau', 'Kia', 'Picanto', 2020, 38000, 'petrol', 'manual', 'hatchback', 'imported', 'Blanc', '#E9EBEE', 'sale', 4600000, 'total', 'active', 0 ),
			array( 'plateau', 'Renault', 'Duster', 2019, 74000, 'diesel', 'manual', 'suv', 'used', 'Marron', '#6B4E16', 'sale', 7800000, 'total', 'active', 0 ),
			array( 'thies', 'Toyota', 'Hilux', 2020, 81000, 'diesel', 'manual', 'pickup', 'used', 'Blanc', '#E9EBEE', 'sale', 17500000, 'total', 'active', 1 ),
			array( 'thies', 'Mitsubishi', 'L200', 2019, 97000, 'diesel', 'manual', 'pickup', 'used', 'Gris', '#5E646D', 'sale', 13900000, 'total', 'active', 0 ),
			array( 'thies', 'Ford', 'Ranger', 2021, 52000, 'diesel', 'automatic', 'pickup', 'imported', 'Bleu', '#27467A', 'sale', 21500000, 'total', 'active', 0 ),
			array( 'thies', 'Toyota', 'Hiace', 2018, 142000, 'diesel', 'manual', 'van', 'used', 'Blanc', '#E9EBEE', 'sale', 11800000, 'total', 'active', 0 ),
			array( 'thies', 'Nissan', 'Navara', 2018, 118000, 'diesel', 'manual', 'pickup', 'used', 'Noir', '#1B1E24', 'sale', 12400000, 'total', 'active', 0 ),
			array( 'ndakaaru', 'Toyota', 'Corolla', 2021, 39000, 'petrol', 'automatic', 'sedan', 'used', 'Blanc', '#E9EBEE', 'rent', 30000, 'day', 'active', 1 ),
			array( 'ndakaaru', 'Hyundai', 'Tucson', 2022, 27000, 'petrol', 'automatic', 'suv', 'used', 'Gris', '#8A9099', 'rent', 45000, 'day', 'active', 1 ),
			array( 'ndakaaru', 'Kia', 'Picanto', 2021, 44000, 'petrol', 'manual', 'hatchback', 'used', 'Rouge', '#A3261E', 'rent', 20000, 'day', 'active', 0 ),
			array( 'ndakaaru', 'Toyota', 'Land Cruiser Prado', 2020, 61000, 'diesel', 'automatic', 'suv', 'used', 'Noir', '#1B1E24', 'rent', 75000, 'day', 'active', 0 ),
			array( 'ndakaaru', 'Toyota', 'Hiace', 2019, 88000, 'diesel', 'manual', 'van', 'used', 'Blanc', '#E9EBEE', 'rent', 60000, 'day', 'rented', 0 ),
			array( 'ndakaaru', 'Mercedes-Benz', 'Classe E', 2020, 47000, 'diesel', 'automatic', 'sedan', 'used', 'Noir', '#1B1E24', 'rent', 90000, 'day', 'active', 0 ),
			array( 'saly', 'Renault', 'Duster', 2021, 36000, 'diesel', 'manual', 'suv', 'used', 'Marron', '#6B4E16', 'rent', 35000, 'day', 'active', 0 ),
			array( 'saly', 'Suzuki', 'Jimny', 2022, 19000, 'petrol', 'manual', 'suv', 'used', 'Bleu', '#27467A', 'rent', 40000, 'day', 'active', 1 ),
			array( 'saly', 'Dacia', 'Sandero', 2020, 58000, 'petrol', 'manual', 'hatchback', 'used', 'Gris', '#8A9099', 'rent', 22000, 'day', 'active', 0 ),
		);
	}

	private static function description( $brand, $model, $type ) {
		if ( 'rent' === $type ) {
			return "Annonce de démonstration. $brand $model disponible à la location, à la journée ou sur une plus longue durée. Véhicule entretenu régulièrement, climatisation, assurance incluse. Possibilité de location avec chauffeur sur demande.";
		}
		return "Annonce de démonstration. $brand $model en très bon état, entretien suivi, contrôle technique à jour. Climatisation, intérieur soigné. Visible sur rendez-vous chez le concessionnaire. Possibilité de reprise de votre ancien véhicule.";
	}

	private static function features( $index ) {
		$all = array( 'Climatisation', 'Bluetooth', 'Caméra de recul', 'Régulateur de vitesse', 'Vitres électriques', 'Radar de recul', 'Jantes alliage', 'Sièges en cuir', 'Écran tactile', 'ABS', 'Airbags', 'Direction assistée', 'Démarrage sans clé', 'Toit ouvrant' );
		$out = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$out[] = $all[ ( $index * 3 + $i * 2 ) % count( $all ) ];
		}
		return array_values( array_unique( $out ) );
	}

	private static function testimonials() {
		return array(
			array( 'Témoignage de démonstration', 'Concessionnaire, Dakar', 'Texte d’exemple : remplacez ce témoignage par celui d’un vrai client depuis Marketplace → Témoignages.' ),
			array( 'Témoignage de démonstration', 'Acheteur, Thiès', 'Texte d’exemple : les témoignages publiés ici apparaissent sur la page d’accueil.' ),
			array( 'Témoignage de démonstration', 'Agence de location, Mbour', 'Texte d’exemple : ne publiez que des témoignages authentiques, avec l’accord de leur auteur.' ),
		);
	}

	/** Square logo with the dealer initials. */
	private static function logo_svg( $name, $color ) {
		$initials = esc_html( amc_initials( $name ) );
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200"><rect width="200" height="200" rx="28" fill="' . esc_attr( $color ) . '"/><text x="100" y="124" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="76" font-weight="700" fill="#fff">' . $initials . '</text></svg>';
	}

	/**
	 * Simple side-view vehicle illustration (1200×800).
	 *
	 * @param string $body  Body type.
	 * @param string $color Paint colour (hex).
	 * @param string $scene studio|city|detail.
	 */
	private static function vehicle_svg( $body, $color, $scene ) {
		// Cabin outline per body type (the lower body is shared).
		$cabins = array(
			'sedan'     => 'M330 430 L430 330 Q445 318 470 318 L690 318 Q715 318 735 332 L850 430 Z',
			'hatchback' => 'M350 430 L440 325 Q452 314 475 314 L720 314 Q745 314 760 335 L800 430 Z',
			'suv'       => 'M300 430 L385 300 Q395 286 420 286 L800 286 Q828 286 838 306 L890 430 Z',
			'pickup'    => 'M300 430 L375 305 Q385 290 410 290 L610 290 Q635 290 640 312 L655 430 Z',
			'van'       => 'M235 430 L290 290 Q298 270 325 270 L900 270 Q930 270 935 300 L945 430 Z',
		);
		$windows = array(
			'sedan'     => 'M375 420 L450 345 Q458 338 472 338 L575 338 L575 420 Z M595 338 L685 338 Q702 338 714 347 L800 420 L595 420 Z',
			'hatchback' => 'M392 420 L455 345 Q463 334 478 334 L585 334 L585 420 Z M605 334 L712 334 Q730 334 738 348 L765 420 L605 420 Z',
			'suv'       => 'M345 420 L402 322 Q410 306 428 306 L580 306 L580 420 Z M600 306 L790 306 Q810 306 818 322 L855 420 L600 420 Z',
			'pickup'    => 'M345 420 L392 325 Q400 310 418 310 L500 310 L500 420 Z M520 310 L600 310 Q618 310 621 326 L632 420 L520 420 Z',
			'van'       => 'M280 410 L310 305 Q316 290 334 290 L470 290 L470 410 Z M490 290 L690 290 L690 410 L490 410 Z M710 290 L890 290 Q910 290 913 306 L920 410 L710 410 Z',
		);
		$body_key = isset( $cabins[ $body ] ) ? $body : 'sedan';

		$scenes = array(
			'studio' => array( '#EEF0F3', '#DDE1E6', '#C9CED6' ),
			'city'   => array( '#E6ECEF', '#D3DCE2', '#B9C5CE' ),
			'detail' => array( '#F1EEEA', '#E3DED7', '#CFC8BE' ),
		);
		list( $sky, $wall, $floor ) = isset( $scenes[ $scene ] ) ? $scenes[ $scene ] : $scenes['studio'];

		// Zoom on the front of the car for the "detail" view.
		$view_box = 'detail' === $scene ? '520 200 600 400' : '0 0 1200 800';
		$color    = esc_attr( $color );

		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $view_box . '" width="1200" height="800" role="img">';
		$svg .= '<rect x="0" y="0" width="1200" height="800" fill="' . $sky . '"/>';
		if ( 'city' === $scene ) {
			$svg .= '<g fill="' . $wall . '"><rect x="60" y="150" width="150" height="390"/><rect x="230" y="210" width="110" height="330"/><rect x="820" y="120" width="170" height="420"/><rect x="1010" y="230" width="130" height="310"/><rect x="430" y="250" width="200" height="290"/></g>';
		} else {
			$svg .= '<rect x="0" y="120" width="1200" height="420" fill="' . $wall . '" opacity=".55"/>';
		}
		$svg .= '<rect x="0" y="540" width="1200" height="260" fill="' . $floor . '"/>';
		$svg .= '<ellipse cx="600" cy="560" rx="430" ry="26" fill="#000" opacity=".16"/>';
		// Cabin, lower body, windows.
		$svg .= '<path d="' . $cabins[ $body_key ] . '" fill="' . $color . '"/>';
		$svg .= '<path d="M190 540 Q175 540 175 520 L185 470 Q190 440 230 432 L300 425 L930 425 Q1000 430 1020 470 L1030 520 Q1030 540 1010 540 Z" fill="' . $color . '"/>';
		$svg .= '<path d="M175 505 L1030 505 L1030 520 Q1030 540 1010 540 L190 540 Q175 540 175 520 Z" fill="#000" opacity=".22"/>';
		$svg .= '<path d="' . $windows[ $body_key ] . '" fill="#1D2530" opacity=".88"/>';
		if ( 'pickup' === $body_key ) {
			$svg .= '<rect x="668" y="395" width="300" height="34" fill="#000" opacity=".18"/>';
		}
		// Lights.
		$svg .= '<rect x="985" y="452" width="40" height="16" rx="6" fill="#FFF3C4"/><rect x="178" y="452" width="30" height="16" rx="6" fill="#C8372D"/>';
		// Wheels.
		foreach ( array( 345, 850 ) as $cx ) {
			$svg .= '<circle cx="' . $cx . '" cy="532" r="74" fill="' . $floor . '"/><circle cx="' . $cx . '" cy="532" r="62" fill="#15181D"/><circle cx="' . $cx . '" cy="532" r="34" fill="#C5CAD1"/><circle cx="' . $cx . '" cy="532" r="10" fill="#15181D"/>';
		}
		$svg .= '</svg>';
		return $svg;
	}
}
