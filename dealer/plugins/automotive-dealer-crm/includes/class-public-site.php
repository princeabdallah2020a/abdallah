<?php
/**
 * Public website integration: assets, brand colours, lead forms, SEO
 * (titles, descriptions, canonical, Open Graph, JSON-LD) and sitemap.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Public_Site {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'brand_css' ), 3 );
		add_action( 'wp_head', array( __CLASS__, 'seo' ), 4 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'admin_post_nopriv_adc_lead', array( __CLASS__, 'handle_lead' ) );
		add_action( 'admin_post_adc_lead', array( __CLASS__, 'handle_lead' ) );
		add_action(
			'init',
			static function () {
				if ( function_exists( 'wp_register_sitemap_provider' ) ) {
					wp_register_sitemap_provider( 'adcvehicles', new Sitemap_Provider() );
				}
			}
		);
	}

	public static function assets() {
		if ( ! wp_style_is( 'amds', 'registered' ) ) {
			wp_register_style( 'amds', ADC_URL . 'assets/css/design-system.css', array(), ADC_VERSION );
		}
		if ( Router::is_manager() ) {
			wp_enqueue_style( 'adc-crm', ADC_URL . 'assets/css/crm.css', array( 'amds' ), ADC_VERSION );
			wp_enqueue_script( 'adc-crm', ADC_URL . 'assets/js/crm.js', array(), ADC_VERSION, true );
			wp_localize_script( 'adc-crm', 'adcCrm', array( 'confirm' => __( 'Please confirm this action.', 'automotive-dealer-crm' ) ) );
			return;
		}
		wp_enqueue_style( 'adc-public', ADC_URL . 'assets/css/public.css', array( 'amds' ), ADC_VERSION );
		wp_enqueue_script( 'adc-public', ADC_URL . 'assets/js/public.js', array(), ADC_VERSION, true );
	}

	/** Brand colours chosen in the CRM, applied to the website and the CRM. */
	public static function brand_css() {
		$colors = Settings::colors();
		$accent = sanitize_hex_color( $colors['accent'] );
		$ink    = sanitize_hex_color( $colors['primary'] );
		if ( ! $accent || ! $ink ) {
			return;
		}
		$on_accent = self::contrast( $accent );
		echo '<style id="adc-brand">:root{--amds-ink:' . esc_html( $ink ) . ';--amds-ink-2:' . esc_html( self::shade( $ink, 12 ) ) . ';--amds-accent:' . esc_html( $accent ) . ';--amds-accent-hover:' . esc_html( self::shade( $accent, -12 ) ) . ';--amds-accent-soft:' . esc_html( $accent ) . '1a;--amds-on-accent:' . esc_html( $on_accent ) . ';--amds-focus:0 0 0 3px ' . esc_html( $accent ) . '59}</style>' . "\n";
	}

	/** Lighten (positive) or darken (negative) a hex colour by a percentage. */
	private static function shade( $hex, $percent ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$out = '#';
		foreach ( str_split( $hex, 2 ) as $part ) {
			$value = hexdec( $part );
			$value = $percent > 0 ? $value + ( 255 - $value ) * $percent / 100 : $value * ( 100 + $percent ) / 100;
			$out  .= str_pad( dechex( (int) round( max( 0, min( 255, $value ) ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/** Black or white text depending on the background luminance. */
	private static function contrast( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		list( $r, $g, $b ) = array_map( 'hexdec', str_split( $hex, 2 ) );
		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) > 160 ? '#111111' : '#ffffff';
	}

	/* ------------------------------------------------------------------ */
	/* Lead forms                                                          */
	/* ------------------------------------------------------------------ */

	public static function handle_lead() {
		$back = wp_get_referer();
		$back = $back ? remove_query_arg( 'adc_sent', $back ) : home_url( '/' );
		$send = static function ( $result ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'adc_sent', $result, $back ) . '#adc-lead' );
			exit;
		};
		if ( ! isset( $_POST['_adc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adc_nonce'] ) ), 'adc_public_lead' ) ) {
			$send( 'error' );
		}
		$elapsed = time() - ( isset( $_POST['adc_ts'] ) ? absint( $_POST['adc_ts'] ) : 0 );
		if ( ! empty( $_POST['website'] ) || $elapsed < 3 ) {
			$send( 'ok' );
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'adc_lead_rl_' . md5( $ip . '|' . floor( time() / HOUR_IN_SECONDS ) );
		if ( (int) get_transient( $key ) >= 5 ) {
			$send( 'rate' );
		}
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );

		$in      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$vehicle = ! empty( $in['vehicle_id'] ) ? Vehicles::get( absint( $in['vehicle_id'] ) ) : null;
		if ( $vehicle && ! in_array( $vehicle->status, Vehicles::PUBLIC_STATUSES, true ) ) {
			$vehicle = null;
		}
		$in['vehicle_id'] = $vehicle ? $vehicle->id : 0;
		$in['interest']   = $vehicle ? $vehicle->listing_type : ( isset( $in['interest'] ) ? $in['interest'] : 'sale' );
		$lead_id          = Leads::save( $in, 0, 'website' );
		if ( is_wp_error( $lead_id ) ) {
			$send( 'invalid' );
		}

		$to = Settings::get( 'lead_email' );
		if ( is_email( $to ) ) {
			$lead = Leads::get( $lead_id ) ? Leads::get( $lead_id ) : (object) array( 'name' => '', 'phone' => '', 'email' => '', 'message' => '' );
			wp_mail(
				$to,
				/* translators: %s: customer name */
				sprintf( __( 'New website lead: %s', 'automotive-dealer-crm' ), sanitize_text_field( (string) $in['name'] ) ),
				implode(
					"\n",
					array_filter(
						array(
							__( 'Name', 'automotive-dealer-crm' ) . ': ' . sanitize_text_field( (string) $in['name'] ),
							__( 'Phone', 'automotive-dealer-crm' ) . ': ' . sanitize_text_field( isset( $in['phone'] ) ? (string) $in['phone'] : '' ),
							__( 'Email', 'automotive-dealer-crm' ) . ': ' . sanitize_email( isset( $in['email'] ) ? (string) $in['email'] : '' ),
							$vehicle ? __( 'Vehicle', 'automotive-dealer-crm' ) . ': ' . $vehicle->title : '',
							'',
							sanitize_textarea_field( isset( $in['message'] ) ? (string) $in['message'] : '' ),
							'',
							adc_manager_url( 'leads', $lead_id ),
						)
					)
				)
			);
			unset( $lead );
		}
		$send( 'ok' );
	}

	/* ------------------------------------------------------------------ */
	/* SEO                                                                 */
	/* ------------------------------------------------------------------ */

	private static function meta() {
		$b    = adc_business();
		$view = Router::view();
		switch ( $view ) {
			case 'inventory':
				/* translators: 1: business, 2: city */
				return array( sprintf( __( 'Vehicles for sale — %1$s, %2$s', 'automotive-dealer-crm' ), $b->name, $b->city ), sprintf( /* translators: 1: count, 2: business */ __( '%1$d vehicles available at %2$s. Prices, photos, specifications and direct contact.', 'automotive-dealer-crm' ), Router::$data['total'], $b->name ), adc_inventory_url(), '' );
			case 'rentals':
				/* translators: 1: city, 2: business */
				return array( sprintf( __( 'Car rental in %1$s — %2$s', 'automotive-dealer-crm' ), $b->city, $b->name ), sprintf( /* translators: %s: business */ __( 'Rent a vehicle with %s: daily rates, with or without driver.', 'automotive-dealer-crm' ), $b->name ), adc_inventory_url( array(), true ), '' );
			case 'vehicle':
				$v = Router::$data['vehicle'];
				return array(
					/* translators: 1: vehicle, 2: business */
					sprintf( __( '%1$s — %2$s', 'automotive-dealer-crm' ), $v->title, $b->name ),
					wp_html_excerpt( implode( ' · ', array_filter( array( adc_vehicle_price( $v ), $v->mileage_km ? adc_km( $v->mileage_km ) : '', adc_label( 'fuel_types', $v->fuel_type ), $v->description ) ) ), 158, '…' ),
					adc_vehicle_url( $v ),
					Vehicles::thumbnail_url( $v, 'large' ),
				);
			case 'about':
				/* translators: %s: business */
				return array( sprintf( __( 'About %s', 'automotive-dealer-crm' ), $b->name ), wp_html_excerpt( (string) Settings::get( 'about_text' ), 158, '…' ), adc_page_url( 'about' ), '' );
			case 'contact':
				/* translators: %s: business */
				return array( sprintf( __( 'Contact %s', 'automotive-dealer-crm' ), $b->name ), trim( $b->address . ' ' . $b->city . ' · ' . $b->phone ), adc_page_url( 'contact' ), '' );
			case 'financing':
				/* translators: %s: business */
				return array( sprintf( __( 'Financing — %s', 'automotive-dealer-crm' ), $b->name ), wp_html_excerpt( (string) Settings::get( 'financing_text' ), 158, '…' ), adc_page_url( 'financing' ), '' );
		}
		return null;
	}

	public static function title( $parts ) {
		$meta = self::meta();
		if ( $meta ) {
			$parts = array( 'title' => $meta[0] );
		}
		return $parts;
	}

	public static function robots( $robots ) {
		if ( Router::is_manager() ) {
			return array( 'noindex' => true, 'nofollow' => true );
		}
		$view = Router::view();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filtered = in_array( $view, array( 'inventory', 'rentals' ), true ) && array_diff( array_keys( $_GET ), array( 'adc_path' ) );
		$sold     = 'vehicle' === $view && ! in_array( Router::$data['vehicle']->status, Vehicles::PUBLIC_STATUSES, true );
		if ( $filtered || $sold ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	public static function seo() {
		$b    = adc_business();
		$meta = self::meta();
		if ( $meta ) {
			echo '<meta name="description" content="' . esc_attr( $meta[1] ) . '">' . "\n";
			echo '<link rel="canonical" href="' . esc_url( $meta[2] ) . '">' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $meta[0] ) . '">' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $meta[1] ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $meta[2] ) . '">' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $b->name ) . '">' . "\n";
			if ( $meta[3] ) {
				echo '<meta property="og:image" content="' . esc_url( $meta[3] ) . '">' . "\n";
			}
		} elseif ( is_front_page() ) {
			echo '<meta name="description" content="' . esc_attr( $b->tagline ? $b->tagline : wp_html_excerpt( (string) Settings::get( 'about_text' ), 158, '…' ) ) . '">' . "\n";
		}

		$graphs = array();
		$dealer = array_filter(
			array(
				'@type'     => 'AutoDealer',
				'name'      => $b->name,
				'url'       => home_url( '/' ),
				'telephone' => $b->phone,
				'email'     => $b->email,
				'logo'      => $b->logo,
				'address'   => array_filter(
					array(
						'@type'           => 'PostalAddress',
						'streetAddress'   => $b->address,
						'addressLocality' => $b->city,
						'addressCountry'  => $b->country,
					)
				),
				'sameAs'    => array_values( array_filter( array( $b->facebook, $b->instagram ) ) ),
			)
		);
		if ( is_front_page() || 'contact' === Router::view() ) {
			$graphs[] = array( '@context' => 'https://schema.org' ) + $dealer;
		}
		if ( 'vehicle' === Router::view() ) {
			$v      = Router::$data['vehicle'];
			$images = array_values( array_filter( array_map( static function ( $id ) {
				return wp_get_attachment_image_url( $id, 'large' );
			}, Router::$data['media'] ) ) );
			$car    = array_filter(
				array(
					'@context'            => 'https://schema.org',
					'@type'               => 'Car',
					'name'                => $v->title,
					'url'                 => adc_vehicle_url( $v ),
					'brand'               => array( '@type' => 'Brand', 'name' => $v->brand ),
					'model'               => $v->model,
					'vehicleModelDate'    => $v->year ? (string) $v->year : '',
					'mileageFromOdometer' => $v->mileage_km ? array( '@type' => 'QuantitativeValue', 'value' => $v->mileage_km, 'unitCode' => 'KMT' ) : '',
					'fuelType'            => adc_label( 'fuel_types', $v->fuel_type ),
					'vehicleTransmission' => adc_label( 'transmissions', $v->transmission ),
					'color'               => $v->color,
					'image'               => $images,
					'description'         => wp_html_excerpt( $v->description, 500, '…' ),
				)
			);
			if ( $v->price_amount ) {
				$car['offers'] = array(
					'@type'         => 'Offer',
					'price'         => (string) $v->price_amount,
					'priceCurrency' => $v->currency,
					'availability'  => 'published' === $v->status ? 'https://schema.org/InStock' : ( 'reserved' === $v->status ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/SoldOut' ),
					'seller'        => $dealer,
				);
			}
			$graphs[] = $car;
		}
		foreach ( $graphs as $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/**
 * Vehicle pages in the WordPress XML sitemap.
 */
final class Sitemap_Provider extends \WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'adcvehicles';
		$this->object_type = 'adcvehicles';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		$urls  = array();
		if ( 1 === (int) $page_num ) {
			foreach ( array( 'inventory', 'about', 'contact' ) as $page ) {
				$urls[] = array( 'loc' => adc_page_url( $page ) );
			}
			if ( Settings::get( 'show_rentals' ) ) {
				$urls[] = array( 'loc' => adc_page_url( 'rentals' ) );
			}
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT slug, updated_at FROM $table WHERE status IN ('published','reserved') ORDER BY id ASC LIMIT 1000 OFFSET %d", ( max( 1, (int) $page_num ) - 1 ) * 1000 ) );
		foreach ( (array) $rows as $row ) {
			$urls[] = array(
				'loc'     => adc_url( 'vehicle/' . $row->slug ),
				'lastmod' => gmdate( 'c', strtotime( $row->updated_at . ' UTC' ) ),
			);
		}
		return $urls;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		global $wpdb;
		$table = Database::table( 'vehicles' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return max( 1, (int) ceil( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status IN ('published','reserved')" ) / 1000 ) );
	}
}
