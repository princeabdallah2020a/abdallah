<?php
/**
 * SEO for marketplace pages: titles, meta descriptions, canonical URLs,
 * robots directives, Open Graph and schema.org structured data.
 *
 * If a dedicated SEO plugin is installed later, disable this output with:
 *   add_filter( 'amc_seo_enabled', '__return_false' );
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Seo {

	public static function init() {
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	private static function enabled() {
		return (bool) apply_filters( 'amc_seo_enabled', true );
	}

	/**
	 * Title, description, canonical and image for the current view.
	 *
	 * @return array|null Null on non-marketplace pages.
	 */
	public static function meta() {
		$view = Marketplace::view();
		$city = Settings::get( 'default_city' );
		$meta = array(
			'title'       => '',
			'description' => '',
			'canonical'   => '',
			'image'       => '',
			'type'        => 'website',
		);

		switch ( $view ) {
			case 'catalog':
				$preset = Marketplace::context( 'preset' );
				$titles = array(
					/* translators: %s: city */
					'all'      => __( 'Cars for sale and for rent in %s', 'automotive-marketplace-core' ),
					/* translators: %s: city */
					'sale'     => __( 'Cars for sale in %s', 'automotive-marketplace-core' ),
					/* translators: %s: city */
					'rent'     => __( 'Car rental in %s', 'automotive-marketplace-core' ),
					/* translators: %s: city */
					'featured' => __( 'Featured vehicles in %s', 'automotive-marketplace-core' ),
				);
				$meta['title'] = sprintf( $titles[ $preset ], $city );
				/* translators: 1: number of listings, 2: city */
				$meta['description'] = sprintf( __( 'Browse %1$d vehicles from verified dealerships in %2$s. Filter by brand, price, year, mileage and more.', 'automotive-marketplace-core' ), Marketplace::context( 'results' )['total'], $city );
				$meta['canonical']   = amc_catalog_url( $preset );
				break;

			case 'vehicle':
				$vehicle = Marketplace::context( 'vehicle' );
				/* translators: 1: vehicle title, 2: listing type label, 3: city */
				$meta['title']       = sprintf( __( '%1$s — %2$s in %3$s', 'automotive-marketplace-core' ), $vehicle->title, amc_label( 'listing_types', $vehicle->listing_type ), $vehicle->city ? $vehicle->city : $city );
				$meta['description'] = wp_html_excerpt(
					implode(
						' · ',
						array_filter(
							array(
								amc_vehicle_price( $vehicle ),
								$vehicle->year ? (string) $vehicle->year : '',
								$vehicle->mileage_km ? amc_format_mileage( $vehicle->mileage_km ) : '',
								amc_label( 'fuel_types', $vehicle->fuel_type ),
								amc_label( 'transmissions', $vehicle->transmission ),
								$vehicle->description,
							)
						)
					),
					158,
					'…'
				);
				$meta['canonical']   = amc_vehicle_url( $vehicle );
				if ( 'dealer' === Settings::get( 'canonical_policy' ) && $vehicle->source_url ) {
					$meta['canonical'] = $vehicle->source_url;
				}
				$meta['image'] = $vehicle->thumbnail_url;
				$meta['type']  = 'product';
				break;

			case 'dealers':
				/* translators: %s: city */
				$meta['title']       = sprintf( __( 'Car dealerships in %s', 'automotive-marketplace-core' ), $city );
				/* translators: %s: city */
				$meta['description'] = sprintf( __( 'Directory of verified car dealerships in %s: contact details, websites and vehicles in stock.', 'automotive-marketplace-core' ), $city );
				$meta['canonical']   = amc_dealers_url();
				break;

			case 'dealer':
				$dealer = Marketplace::context( 'dealer' );
				/* translators: 1: dealer name, 2: city */
				$meta['title']       = sprintf( __( '%1$s — Car dealership in %2$s', 'automotive-marketplace-core' ), $dealer->business_name, $dealer->city ? $dealer->city : $city );
				$meta['description'] = wp_html_excerpt( $dealer->description, 158, '…' );
				$meta['canonical']   = amc_dealer_url( $dealer->slug );
				$meta['image']       = $dealer->logo_url;
				break;

			default:
				return null;
		}

		$page = (int) Marketplace::context( 'page' );
		if ( $page > 1 && 'vehicle' !== $view ) {
			$meta['canonical'] = add_query_arg( 'pg', $page, $meta['canonical'] );
			/* translators: %d: page number */
			$meta['title'] .= ' – ' . sprintf( __( 'Page %d', 'automotive-marketplace-core' ), $page );
		}

		return apply_filters( 'amc_seo_meta', $meta, $view );
	}

	public static function title_parts( $parts ) {
		$meta = self::enabled() ? self::meta() : null;
		if ( $meta ) {
			$parts['title'] = $meta['title'];
			unset( $parts['tagline'] );
		}
		return $parts;
	}

	/**
	 * Filtered result pages (any query argument other than pagination) and
	 * unavailable vehicles are not indexed, but their links are followed.
	 *
	 * @param array $robots Robots directives.
	 */
	public static function robots( $robots ) {
		$view = Marketplace::view();
		if ( ! $view || ! self::enabled() ) {
			return $robots;
		}
		$noindex = false;
		if ( 'catalog' === $view ) {
			$user_filters = amc_filters_to_query( (array) Marketplace::context( 'filters' ), Marketplace::context( 'preset' ) );
			$noindex      = ! empty( $user_filters );
		} elseif ( 'vehicle' === $view ) {
			$noindex = ! in_array( Marketplace::context( 'vehicle' )->status, array( 'active', 'reserved' ), true );
		}
		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	public static function head() {
		if ( ! self::enabled() ) {
			return;
		}
		$meta = self::meta();

		if ( $meta ) {
			echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
			echo '<link rel="canonical" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";
			echo '<meta property="og:type" content="' . esc_attr( $meta['type'] ) . '">' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $meta['title'] ) . '">' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $meta['canonical'] ) . '">' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
			echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
			if ( $meta['image'] ) {
				echo '<meta property="og:image" content="' . esc_url( $meta['image'] ) . '">' . "\n";
				echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
			}
		}

		foreach ( self::structured_data() as $graph ) {
			// JSON_HEX_TAG prevents a "</script>" inside a value from closing the element.
			echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * schema.org graphs for the current page.
	 *
	 * @return array[]
	 */
	public static function structured_data() {
		$graphs = array();
		$view   = Marketplace::view();

		if ( is_front_page() && ! $view ) {
			$graphs[] = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Organization',
				'name'     => get_bloginfo( 'name' ),
				'url'      => home_url( '/' ),
			);
			$graphs[] = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'name'            => get_bloginfo( 'name' ),
				'url'             => home_url( '/' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => add_query_arg( 'q', '{search_term_string}', amc_catalog_url() ),
					'query-input' => 'required name=search_term_string',
				),
			);
		}

		if ( 'vehicle' === $view ) {
			$vehicle  = Marketplace::context( 'vehicle' );
			$dealer   = Marketplace::context( 'dealer' );
			$graphs[] = self::vehicle_schema( $vehicle, $dealer );
			$graphs[] = self::breadcrumbs(
				array(
					array( __( 'Vehicles', 'automotive-marketplace-core' ), amc_catalog_url() ),
					array( $vehicle->brand, amc_catalog_url( 'all', array( 'brand' => $vehicle->brand_slug ) ) ),
					array( $vehicle->title, amc_vehicle_url( $vehicle ) ),
				)
			);
		}

		if ( 'dealer' === $view ) {
			$dealer   = Marketplace::context( 'dealer' );
			$graphs[] = array_merge( array( '@context' => 'https://schema.org' ), self::dealer_schema( $dealer ) );
			$graphs[] = self::breadcrumbs(
				array(
					array( __( 'Dealers', 'automotive-marketplace-core' ), amc_dealers_url() ),
					array( $dealer->business_name, amc_dealer_url( $dealer->slug ) ),
				)
			);
		}

		if ( 'catalog' === $view ) {
			$items = array();
			foreach ( Marketplace::context( 'results' )['items'] as $index => $vehicle ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'url'      => amc_vehicle_url( $vehicle ),
					'name'     => $vehicle->title,
				);
			}
			if ( $items ) {
				$graphs[] = array(
					'@context'        => 'https://schema.org',
					'@type'           => 'ItemList',
					'itemListElement' => $items,
				);
			}
		}

		return $graphs;
	}

	private static function vehicle_schema( $vehicle, $dealer ) {
		$availability = array(
			'active'   => 'https://schema.org/InStock',
			'reserved' => 'https://schema.org/LimitedAvailability',
			'sold'     => 'https://schema.org/SoldOut',
			'rented'   => 'https://schema.org/OutOfStock',
		);
		$currencies   = Catalog::currencies();
		$decimals     = isset( $currencies[ $vehicle->currency ] ) ? $currencies[ $vehicle->currency ]['decimals'] : 0;

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Car',
			'name'     => $vehicle->title,
			'url'      => amc_vehicle_url( $vehicle ),
			'brand'    => array(
				'@type' => 'Brand',
				'name'  => $vehicle->brand,
			),
			'model'    => $vehicle->model,
		);
		if ( $vehicle->description ) {
			$schema['description'] = wp_html_excerpt( $vehicle->description, 500, '…' );
		}
		if ( $vehicle->year ) {
			$schema['vehicleModelDate'] = (string) $vehicle->year;
		}
		if ( $vehicle->mileage_km ) {
			$schema['mileageFromOdometer'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $vehicle->mileage_km,
				'unitCode' => 'KMT',
			);
		}
		if ( $vehicle->fuel_type ) {
			$schema['fuelType'] = amc_label( 'fuel_types', $vehicle->fuel_type );
		}
		if ( $vehicle->transmission ) {
			$schema['vehicleTransmission'] = amc_label( 'transmissions', $vehicle->transmission );
		}
		if ( $vehicle->body_type ) {
			$schema['bodyType'] = amc_label( 'body_types', $vehicle->body_type );
		}
		if ( $vehicle->color ) {
			$schema['color'] = $vehicle->color;
		}
		if ( $vehicle->vehicle_condition ) {
			$schema['itemCondition'] = 'new' === $vehicle->vehicle_condition ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition';
		}
		$images = array();
		foreach ( (array) $vehicle->media as $media ) {
			$images[] = $media->url;
		}
		if ( $images ) {
			$schema['image'] = $images;
		}
		if ( $vehicle->price_amount > 0 ) {
			$offer = array(
				'@type'         => 'Offer',
				'price'         => $decimals ? number_format( $vehicle->price_amount / pow( 10, $decimals ), $decimals, '.', '' ) : (string) $vehicle->price_amount,
				'priceCurrency' => $vehicle->currency,
				'availability'  => isset( $availability[ $vehicle->status ] ) ? $availability[ $vehicle->status ] : 'https://schema.org/OutOfStock',
				'url'           => amc_vehicle_url( $vehicle ),
			);
			if ( 'rent' === $vehicle->listing_type ) {
				$offer['businessFunction'] = 'http://purl.org/goodrelations/v1#LeaseOut';
			}
			if ( $dealer ) {
				$offer['seller'] = self::dealer_schema( $dealer );
			}
			$schema['offers'] = $offer;
		}
		return $schema;
	}

	private static function dealer_schema( $dealer ) {
		$schema = array(
			'@type' => 'AutoDealer',
			'name'  => $dealer->business_name,
			'url'   => amc_dealer_url( $dealer->slug ),
		);
		if ( $dealer->logo_url ) {
			$schema['logo'] = $dealer->logo_url;
		}
		if ( $dealer->phone ) {
			$schema['telephone'] = $dealer->phone;
		}
		if ( $dealer->address || $dealer->city ) {
			$schema['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $dealer->address,
					'addressLocality' => $dealer->city,
					'addressCountry'  => $dealer->country_code,
				)
			);
		}
		if ( $dealer->website_url ) {
			$schema['sameAs'] = array( $dealer->website_url );
		}
		return $schema;
	}

	private static function breadcrumbs( array $trail ) {
		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'automotive-marketplace-core' ),
				'item'     => home_url( '/' ),
			),
		);
		foreach ( $trail as $index => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 2,
				'name'     => $crumb[0],
				'item'     => $crumb[1],
			);
		}
		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
}
