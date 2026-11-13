<?php
/**
 * Integration tests for the central marketplace (Phase 1).
 *
 * Runs against a real WordPress site with the plugin and theme active.
 * WARNING: it imports and then removes demo data and creates temporary
 * records. Run it on a local/staging site only, never in production.
 *
 * Usage:
 *   php tools/tests/run.php <site-dir> [base-url]
 * With a base URL (site served by a web server) HTTP tests run as well.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
$site = rtrim( $argv[1] ?? '', '/' );
$base = rtrim( $argv[2] ?? '', '/' );
if ( ! is_file( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tools/tests/run.php <site-dir> [base-url]\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = $base ? parse_url( $base, PHP_URL_HOST ) . ( parse_url( $base, PHP_URL_PORT ) ? ':' . parse_url( $base, PHP_URL_PORT ) : '' ) : 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.9';
require $site . '/wp-load.php';
require dirname( __DIR__ ) . '/dev/i18n-extract.php';

use AMC\Vehicles;
use AMC\Dealers;
use AMC\Database;
use AMC\Demo_Data;

$pass = 0;
$fail = 0;
function check( $label, $condition, $detail = '' ) {
	global $pass, $fail;
	if ( $condition ) {
		++$pass;
		echo "  ok    $label\n";
	} else {
		++$fail;
		echo "  FAIL  $label" . ( '' !== $detail ? " — $detail" : '' ) . "\n";
	}
}
function section( $title ) {
	echo "\n== $title ==\n";
}
function http( $url, array $opts = array() ) {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
		) + $opts
	);
	$raw    = curl_exec( $ch );
	$status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$size   = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );
	return array(
		'status'  => $status,
		'headers' => substr( (string) $raw, 0, $size ),
		'body'    => substr( (string) $raw, $size ),
	);
}

global $wpdb;
$root = dirname( __DIR__, 2 );

// ---------------------------------------------------------------------------
section( 'Translations (fr_FR coverage)' );
foreach (
	array(
		array( $root . '/central/plugins/automotive-marketplace-core', 'automotive-marketplace-core', 'languages/automotive-marketplace-core-fr_FR.l10n.php' ),
		array( $root . '/central/themes/autobuzy', 'autobuzy', 'languages/fr_FR.l10n.php' ),
	) as $pkg
) {
	$strings = amc_i18n_extract( $pkg[0], $pkg[1] );
	$fr      = include $pkg[0] . '/' . $pkg[2];
	$missing = array_diff( $strings, array_keys( $fr['messages'] ) );
	$unused  = array_diff( array_keys( $fr['messages'] ), $strings );
	check( $pkg[1] . ': all ' . count( $strings ) . ' strings translated', ! $missing, implode( ' | ', $missing ) );
	check( $pkg[1] . ': no obsolete translation', ! $unused, implode( ' | ', $unused ) );
}
check( 'French is loaded at runtime', 'À vendre' === __( 'For sale', 'automotive-marketplace-core' ), get_locale() );
check( 'Plural form works', '2 véhicules' === sprintf( _n( '%d vehicle', '%d vehicles', 2, 'automotive-marketplace-core' ), 2 ) );

// ---------------------------------------------------------------------------
section( 'Schema and migrations' );
check( 'Schema version stored', (int) get_option( 'amc_db_version' ) === AMC_DB_VERSION );
foreach ( array( 'dealers', 'vehicles', 'vehicle_media', 'inquiries', 'audit_log' ) as $table ) {
	$wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( $table ) );
	check( "Table amc_$table exists", '' === $wpdb->last_error, $wpdb->last_error );
}
Database::migrate();
check( 'Running migrations twice is safe', (int) get_option( 'amc_db_version' ) === AMC_DB_VERSION && '' === $wpdb->last_error );

// ---------------------------------------------------------------------------
section( 'Demo data and upsert' );
$import = Demo_Data::import();
check( 'Demo import: 6 dealers, 34 vehicles', 6 === $import['dealers'] && 34 === $import['vehicles'], wp_json_encode( $import ) );
$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'vehicles' ) );
Demo_Data::import();
$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'vehicles' ) );
check( 'Re-import does not duplicate', $before === $after, "$before vs $after" );

$dealer_id = Dealers::create(
	array(
		'business_name' => 'Test Garage',
		'city'          => 'Dakar',
		'status'        => 'active',
		'contact_email' => 'private@example.test',
		'is_demo'       => 1,
	)
);
$payload = array(
	'external_id'  => 'T-1',
	'brand'        => 'Testbrand',
	'model'        => 'One',
	'year'         => 2020,
	'price_amount' => 5000000,
	'city'         => 'Dakar',
	'is_demo'      => 1,
);
$id1  = Vehicles::upsert( $dealer_id, $payload );
$slug = Vehicles::get_by_id( $id1 )->slug;
$id2  = Vehicles::upsert( $dealer_id, array_merge( $payload, array( 'price_amount' => 4500000 ) ) );
$v    = Vehicles::get_by_id( $id1 );
check( 'Upsert with same external_id updates the same listing', $id1 === $id2 );
check( 'Price updated by upsert', 4500000 === $v->price_amount );
check( 'Slug is stable across updates', $slug === $v->slug );
check( 'Upsert rejects a listing without brand', is_wp_error( Vehicles::upsert( $dealer_id, array( 'external_id' => 'X', 'model' => 'm' ) ) ) );
$bad = Vehicles::get_by_id( Vehicles::upsert( $dealer_id, array( 'external_id' => 'T-2', 'brand' => 'Testbrand', 'model' => 'Two', 'fuel_type' => 'plutonium', 'status' => 'hacked', 'description' => '<script>alert(1)</script>Hello', 'source_url' => 'javascript:alert(1)', 'is_demo' => 1 ) ) );
check( 'Unknown enum values are rejected', '' === $bad->fuel_type && 'active' === $bad->status );
check( 'HTML is stripped from descriptions', false === strpos( $bad->description, '<script' ) );
check( 'Non-http source URLs are rejected', '' === $bad->source_url );

// ---------------------------------------------------------------------------
section( 'Search and filters' );
$all = Vehicles::search( array(), 1, 48 );
check( 'Catalog excludes sold / rented listings', ! array_filter( $all['items'], static function ( $v ) { return ! in_array( $v->status, array( 'active', 'reserved' ), true ); } ) );
$sale = Vehicles::search( Vehicles::sanitize_filters( array( 'type' => 'sale' ) ), 1, 48 );
check( 'type=sale returns only sale listings', $sale['total'] > 0 && ! array_filter( $sale['items'], static function ( $v ) { return 'sale' !== $v->listing_type; } ) );
$rent = Vehicles::search( Vehicles::sanitize_filters( array( 'type' => 'rent' ) ), 1, 48 );
check( 'type=rent returns only rental listings', $rent['total'] > 0 && ! array_filter( $rent['items'], static function ( $v ) { return 'rent' !== $v->listing_type; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'brand' => 'toyota', 'model' => 'corolla' ) ), 1, 48 );
check( 'brand + model filter', $f['total'] >= 2 && ! array_filter( $f['items'], static function ( $v ) { return 'toyota' !== $v->brand_slug || 'corolla' !== $v->model_slug; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'type' => 'sale', 'price_min' => '5 000 000', 'price_max' => '10000000' ) ), 1, 48 );
check( 'price range filter (accepts formatted numbers)', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return $v->price_amount < 5000000 || $v->price_amount > 10000000; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'year_min' => 2021, 'mileage_max' => 40000 ) ), 1, 48 );
check( 'year + mileage filter', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return $v->year < 2021 || $v->mileage_km > 40000; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'fuel' => 'diesel', 'transmission' => 'manual', 'body' => 'pickup' ) ), 1, 48 );
check( 'fuel + transmission + body filter', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return 'diesel' !== $v->fuel_type || 'manual' !== $v->transmission || 'pickup' !== $v->body_type; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'city' => 'thies' ) ), 1, 48 );
check( 'location filter', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return 'thies' !== $v->city_slug; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'dealer' => 'almadies-auto' ) ), 1, 48 );
check( 'dealer filter', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return 'almadies-auto' !== $v->dealer_slug; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'condition' => 'new' ) ), 1, 48 );
check( 'condition filter', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return 'new' !== $v->vehicle_condition; } ) );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'availability' => 'reserved' ) ), 1, 48 );
check( 'availability filter', 1 === $f['total'] && 'reserved' === $f['items'][0]->status );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'q' => 'land cruiser' ) ), 1, 48 );
check( 'keyword search', 2 === $f['total'], (string) $f['total'] );
$f = Vehicles::search( Vehicles::sanitize_filters( array( 'featured' => 1 ) ), 1, 48 );
check( 'featured filter', $f['total'] > 0 && ! array_filter( $f['items'], static function ( $v ) { return ! $v->is_featured; } ) );

$prices = array_map( static function ( $v ) { return $v->price_amount; }, Vehicles::search( array( 'sort' => 'price_asc' ), 1, 48 )['items'] );
$sorted = $prices;
sort( $sorted );
check( 'sort: lowest price', $prices === $sorted );
$prices = array_map( static function ( $v ) { return $v->price_amount; }, Vehicles::search( array( 'sort' => 'price_desc' ), 1, 48 )['items'] );
$sorted = $prices;
rsort( $sorted );
check( 'sort: highest price', $prices === $sorted );
$kms    = array_map( static function ( $v ) { return $v->mileage_km; }, Vehicles::search( array( 'sort' => 'mileage_asc' ), 1, 48 )['items'] );
$sorted = $kms;
sort( $sorted );
check( 'sort: lowest mileage', $kms === $sorted );
$dates  = array_map( static function ( $v ) { return $v->published_at; }, Vehicles::search( array(), 1, 48 )['items'] );
$sorted = $dates;
rsort( $sorted );
check( 'sort: latest (default)', $dates === $sorted );
$first = Vehicles::search( array( 'sort' => 'featured' ), 1, 5 )['items'];
check( 'sort: featured first', 1 === $first[0]->is_featured );

$p1 = Vehicles::search( array(), 1, 10 );
$p2 = Vehicles::search( array(), 2, 10 );
check( 'pagination returns distinct pages', 10 === count( $p1['items'] ) && ! array_intersect( wp_list_pluck( $p1['items'], 'id' ), wp_list_pluck( $p2['items'], 'id' ) ) );

$evil = Vehicles::sanitize_filters( array( 'brand' => "toyota' OR '1'='1", 'sort' => 'id; DROP TABLE x', 'fuel' => array( 'a' ), 'price_min' => '-5', 'q' => "%' UNION SELECT 1 --" ) );
check( 'Malicious filter input is neutralised', ! isset( $evil['sort'] ) && ! isset( $evil['fuel'] ) && ! isset( $evil['price_min'] ) && false === strpos( $evil['brand'], "'" ) );
Vehicles::search( $evil );
check( 'No SQL error with hostile input', '' === $wpdb->last_error, $wpdb->last_error );

// ---------------------------------------------------------------------------
section( 'Visibility rules and data isolation' );
$sold = $wpdb->get_var( 'SELECT slug FROM ' . Database::table( 'vehicles' ) . " WHERE status = 'sold' LIMIT 1" );
check( 'Sold listing still reachable by URL (shown as sold)', (bool) Vehicles::get_by_slug( $sold ) );
$wpdb->update( Database::table( 'vehicles' ), array( 'status' => 'unpublished' ), array( 'id' => $id1 ) );
check( 'Unpublished listing is not reachable', null === Vehicles::get_by_id( $id1 ) );
check( 'Unpublished listing is not in search', 0 === Vehicles::search( array( 'brand' => 'testbrand', 'model' => 'one' ) )['total'] );
$wpdb->update( Database::table( 'vehicles' ), array( 'status' => 'active' ), array( 'id' => $id1 ) );

$wpdb->update( Database::table( 'dealers' ), array( 'status' => 'suspended' ), array( 'id' => $dealer_id ) );
Vehicles::flush_cache();
check( 'Suspended dealer: listings hidden from search', 0 === Vehicles::search( array( 'brand' => 'testbrand' ) )['total'] );
check( 'Suspended dealer: listing page not reachable', null === Vehicles::get_by_id( $id1 ) );
check( 'Suspended dealer: profile not public', null === Dealers::get_by_slug( 'test-garage' ) );
check( 'Suspended dealer: brand removed from filter options', ! isset( Vehicles::facets()['brands']['testbrand'] ) );
$wpdb->update( Database::table( 'dealers' ), array( 'status' => 'active' ), array( 'id' => $dealer_id ) );
Vehicles::flush_cache();

// Freshness policy applies to API-connected dealers ("Test Garage" is sync_mode=api).
$stale_at = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * ( (int) AMC\Settings::get( 'stale_after_hours' ) + 1 ) );
$wpdb->update( Database::table( 'vehicles' ), array( 'last_synced_at' => $stale_at ), array( 'id' => $id1 ) );
$stale = Vehicles::get_by_id( $id1 );
check( 'Listing not confirmed recently is flagged stale but still visible', $stale && true === $stale->is_stale );
$gone_at = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS * ( (int) AMC\Settings::get( 'hide_after_hours' ) + 1 ) );
$wpdb->update( Database::table( 'vehicles' ), array( 'last_synced_at' => $gone_at ), array( 'id' => $id1 ) );
check( 'Listing beyond the freshness limit is hidden', null === Vehicles::get_by_id( $id1 ) && 0 === Vehicles::search( array( 'brand' => 'testbrand', 'model' => 'one' ) )['total'] );
$wpdb->update( Database::table( 'vehicles' ), array( 'last_synced_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id1 ) );

$profile = Dealers::get_by_slug( 'test-garage' );
check( 'Public dealer profile never contains private fields', $profile && ! isset( $profile->contact_email ) && ! isset( $profile->user_id ) && ! isset( $profile->status ) );
$api = AMC\Api::dealer_public( $profile );
check( 'API dealer representation has no private fields', ! isset( $api['contact_email'] ) && ! isset( $api['id'] ) );

// ---------------------------------------------------------------------------
section( 'Formatting helpers' );
check( 'XOF formatting', "12\u{202F}500\u{202F}000\u{00A0}FCFA" === amc_format_money( 12500000, 'XOF' ), amc_format_money( 12500000, 'XOF' ) );
check( 'EUR formatting uses minor units', "1\u{202F}234,50\u{00A0}€" === amc_format_money( 123450, 'EUR' ), amc_format_money( 123450, 'EUR' ) );
check( 'WhatsApp link', 'https://wa.me/221000000001?text=Bonjour%20%21' === amc_whatsapp_url( '+221 00 000 00 01', 'Bonjour !' ) );
check( 'Phone normalisation', '+221771234567' === amc_normalize_phone( '+221 77 123-45-67' ) );
$sanitized = AMC\Settings::sanitize( array( 'default_currency' => 'zzz', 'per_page' => 9999, 'stale_after_hours' => 100, 'hide_after_hours' => 10, 'canonical_policy' => 'evil' ) );
check( 'Settings are validated', 'XOF' === $sanitized['default_currency'] && 48 === $sanitized['per_page'] && $sanitized['hide_after_hours'] >= $sanitized['stale_after_hours'] && 'self' === $sanitized['canonical_policy'] );

// ---------------------------------------------------------------------------
if ( $base ) {
	section( 'HTTP: pages' );
	$vehicle = Vehicles::search( array( 'brand' => 'toyota', 'model' => 'rav4' ) )['items'][0];
	$pages   = array(
		'/'                              => 'Home',
		'/cars/'                         => 'All vehicles',
		'/cars-for-sale/'                => 'Cars for sale',
		'/cars-for-rent/'                => 'Cars for rent',
		'/featured/'                     => 'Featured',
		'/cars/?brand=toyota&price_max=20000000' => 'Search results',
		'/car/' . $vehicle->slug . '/'   => 'Vehicle details',
		'/dealers/'                      => 'Dealers directory',
		'/dealer/teranga-motors/'        => 'Dealer profile',
		'/about/'                        => 'About',
		'/services/'                     => 'Services',
		'/how-it-works/'                 => 'How it works',
		'/contact/'                      => 'Contact',
		'/faq/'                          => 'FAQ',
		'/join-dealer/'                  => 'Become a dealer',
	);
	$html    = array();
	foreach ( $pages as $path => $label ) {
		$r             = http( $base . $path );
		$html[ $path ] = $r['body'];
		check( "$label ($path) → 200", 200 === $r['status'] && false !== strpos( $r['body'], '</html>' ), 'status ' . $r['status'] );
		check( "$label: no PHP error in output", ! preg_match( '/(Warning|Notice|Fatal error|Deprecated)<\/b>:|Stack trace/', $r['body'] ) );
	}
	check( 'Unknown vehicle → 404', 404 === http( $base . '/car/does-not-exist/' )['status'] );
	check( 'Unknown dealer → 404', 404 === http( $base . '/dealer/does-not-exist/' )['status'] );
	check( 'Draft legal pages are not public', 404 === http( $base . '/terms/' )['status'] );

	section( 'HTTP: SEO' );
	$page = $html[ '/car/' . $vehicle->slug . '/' ];
	check( 'Vehicle: <title> contains the vehicle name', (bool) preg_match( '/<title>[^<]*Toyota RAV4[^<]*<\/title>/', $page ) );
	check( 'Vehicle: canonical URL', false !== strpos( $page, '<link rel="canonical" href="' . $base . '/car/' . $vehicle->slug . '/">' ) );
	check( 'Vehicle: meta description', (bool) preg_match( '/<meta name="description" content="[^"]{30,}">/', $page ) );
	check( 'Vehicle: Open Graph image', false !== strpos( $page, 'property="og:image"' ) );
	preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $page, $m );
	$types = array();
	foreach ( $m[1] as $json ) {
		$data = json_decode( $json, true );
		if ( is_array( $data ) ) {
			$types[ $data['@type'] ] = $data;
		}
	}
	check( 'Vehicle: valid JSON-LD Car + BreadcrumbList', isset( $types['Car'], $types['BreadcrumbList'] ), implode( ',', array_keys( $types ) ) );
	check( 'Vehicle: JSON-LD offer has price, currency and seller', isset( $types['Car']['offers']['price'] ) && 'XOF' === $types['Car']['offers']['priceCurrency'] && 'AutoDealer' === $types['Car']['offers']['seller']['@type'] );
	check( 'Vehicle: exactly one <h1>', 1 === substr_count( $page, '<h1' ) );
	check( 'Vehicle: WhatsApp and phone contact links', false !== strpos( $page, 'https://wa.me/221000000001' ) && false !== strpos( $page, 'href="tel:+221000000001"' ) );
	check( 'Vehicle: similar vehicles section', false !== strpos( $page, 'id="amc-similar"' ) );
	check( 'Filtered catalog is noindex', (bool) preg_match( '/<meta name=.robots. content=.[^>]*noindex/', $html['/cars/?brand=toyota&price_max=20000000'] ) );
	check( 'Unfiltered catalog is indexable', ! preg_match( '/<meta name=.robots. content=.[^>]*noindex/', $html['/cars/'] ) );
	check( 'Home: WebSite + Organization JSON-LD', false !== strpos( $html['/'], '"@type":"WebSite"' ) && false !== strpos( $html['/'], '"@type":"Organization"' ) );
	check( 'Home: exactly one <h1>', 1 === substr_count( $html['/'], '<h1' ) );
	check( 'Dealer: AutoDealer JSON-LD', false !== strpos( $html['/dealer/teranga-motors/'], '"@type":"AutoDealer"' ) );
	check( 'Pages are served in French', false !== strpos( $html['/'], '<html lang="fr-FR"' ) && false !== strpos( $html['/cars/'], 'Appliquer les filtres' ) );
	$sold_page = http( $base . '/car/' . $sold . '/' );
	check( 'Sold vehicle page: 200, noindex, no contact buttons', 200 === $sold_page['status'] && false !== strpos( $sold_page['body'], 'noindex' ) && false === strpos( $sold_page['body'], 'amc-summary__actions' ) );
	$index = http( $base . '/wp-sitemap.xml' );
	check( 'Sitemap index lists marketplace sitemaps', 200 === $index['status'] && false !== strpos( $index['body'], 'wp-sitemap-amcvehicles-1.xml' ) && false !== strpos( $index['body'], 'wp-sitemap-amcdealers-1.xml' ) );
	$map = http( $base . '/wp-sitemap-amcvehicles-1.xml' );
	check( 'Vehicle sitemap contains listing URLs but not sold ones', false !== strpos( $map['body'], '/car/' . $vehicle->slug . '/' ) && false === strpos( $map['body'], '/car/' . $sold . '/' ) );

	section( 'HTTP: REST API' );
	$r    = http( $base . '/wp-json/marketplace/v1/vehicles?type=rent&per_page=5' );
	$data = json_decode( $r['body'], true );
	check( 'GET /vehicles', 200 === $r['status'] && 5 === count( $data['items'] ) && 'rent' === $data['items'][0]['listing_type'] );
	check( 'GET /vehicles exposes only the public allow-list', ! array_diff( array_keys( $data['items'][0] ), array( 'id', 'slug', 'url', 'title', 'brand', 'model', 'year', 'mileage_km', 'fuel_type', 'transmission', 'body_type', 'condition', 'color', 'listing_type', 'price', 'city', 'country_code', 'status', 'is_featured', 'is_stale', 'thumbnail_url', 'dealer', 'published_at' ) ) );
	$r = http( $base . '/wp-json/marketplace/v1/vehicles/' . $vehicle->id );
	$d = json_decode( $r['body'], true );
	check( 'GET /vehicles/{id}', 200 === $r['status'] && 3 === count( $d['media'] ) && 'teranga-motors' === $d['dealer']['slug'] );
	check( 'GET /vehicles/{id} unknown → 404', 404 === http( $base . '/wp-json/marketplace/v1/vehicles/99999999' )['status'] );
	$r = http( $base . '/wp-json/marketplace/v1/facets' );
	$d = json_decode( $r['body'], true );
	check( 'GET /facets', 200 === $r['status'] && isset( $d['brands']['toyota']['models']['corolla'] ) );
	$r = http( $base . '/wp-json/marketplace/v1/dealers' );
	$d = json_decode( $r['body'], true );
	check( 'GET /dealers has no private data', 200 === $r['status'] && $d['total'] >= 6 && false === strpos( $r['body'], 'private@example.test' ) && false === strpos( $r['body'], 'contact_email' ) );
	$r = http( $base . '/wp-json/marketplace/v1/catalog?preset=sale&brand=toyota&sort=price_asc' );
	$d = json_decode( $r['body'], true );
	check( 'GET /catalog returns rendered results and a clean URL', 200 === $r['status'] && false !== strpos( $d['html'], 'amc-card' ) && $base . '/cars-for-sale/?brand=toyota&sort=price_asc' === $d['url'], $d['url'] ?? '' );
	$r = http( $base . '/wp-json/marketplace/v1/catalog?preset=sale&type=rent' );
	$d = json_decode( $r['body'], true );
	check( 'Preset cannot be overridden by a query parameter', false === strpos( $d['html'], 'amds-badge--info' ) );

	section( 'HTTP: contact form' );
	$count  = static function () use ( $wpdb ) {
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'inquiries' ) );
	};
	$form   = $html['/contact/'];
	preg_match( '/name="_amc_nonce" value="([a-f0-9]+)"/', $form, $nonce );
	$fields = array(
		'action'     => 'amc_submit_inquiry',
		'type'       => 'contact',
		'_amc_nonce' => $nonce[1] ?? '',
		'amc_ts'     => time() - 10,
		'name'       => 'Awa Test <b>x</b>',
		'email'      => 'awa@example.test',
		'phone'      => '+221 70 000 00 00',
		'message'    => "Bonjour,\n<script>alert(1)</script>test.",
		'website'    => '',
	);
	$post   = static function ( array $fields ) use ( $base ) {
		return http(
			$base . '/wp-admin/admin-post.php',
			array(
				CURLOPT_POST       => true,
				CURLOPT_POSTFIELDS => http_build_query( $fields ),
				CURLOPT_HTTPHEADER => array( 'Referer: ' . $base . '/contact/' ),
			)
		);
	};
	$n = $count();
	$r = $post( array_merge( $fields, array( '_amc_nonce' => 'invalid' ) ) );
	check( 'Invalid nonce is rejected', $count() === $n && false !== strpos( $r['headers'], 'amc_inquiry=error' ) );
	$r = $post( array_merge( $fields, array( 'website' => 'http://spam.example' ) ) );
	check( 'Honeypot submission is silently dropped', $count() === $n && false !== strpos( $r['headers'], 'amc_inquiry=sent' ) );
	$r = $post( array_merge( $fields, array( 'email' => '', 'phone' => '' ) ) );
	check( 'Missing contact details → validation error', $count() === $n && false !== strpos( $r['headers'], 'amc_inquiry=invalid' ) );
	$r = $post( $fields );
	check( 'Valid submission is stored', $count() === $n + 1 && false !== strpos( $r['headers'], 'amc_inquiry=sent' ), $r['headers'] );
	$row = $wpdb->get_row( 'SELECT * FROM ' . Database::table( 'inquiries' ) . ' ORDER BY id DESC LIMIT 1' );
	check( 'Stored message is sanitised', $row && false === strpos( $row->name . $row->message, '<' ) && '+221700000000' === $row->phone );
	check( 'IP address is stored hashed, not in clear', $row && 64 === strlen( $row->ip_hash ) && false === strpos( $row->ip_hash, '127.0.0.1' ) );
	for ( $i = 0; $i < 6; $i++ ) {
		$r = $post( $fields );
	}
	check( 'Rate limit after 5 messages per hour', false !== strpos( $r['headers'], 'amc_inquiry=rate' ) && $count() <= $n + 5 );
	$wpdb->query( 'DELETE FROM ' . Database::table( 'inquiries' ) . " WHERE email = 'awa@example.test'" );

	section( 'HTTP: admin protection' );
	$r = http( $base . '/wp-admin/admin-post.php', array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'action=amc_demo_remove' ) );
	check( 'Anonymous user cannot trigger admin actions', (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'vehicles' ) . ' WHERE is_demo = 1' ) > 0 );

	section( 'HTTP: API rate limit' );
	$limit = (int) AMC\Settings::get( 'api_rate_limit' );
	update_option( AMC\Settings::OPTION, array_merge( AMC\Settings::all(), array( 'api_rate_limit' => 10 ) ) );
	$last = 200;
	for ( $i = 0; $i < 14; $i++ ) {
		$last = http( $base . '/wp-json/marketplace/v1/facets' )['status'];
	}
	check( 'Public API returns 429 when the limit is exceeded', 429 === $last, (string) $last );
	update_option( AMC\Settings::OPTION, array_merge( AMC\Settings::all(), array( 'api_rate_limit' => $limit ) ) );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%amc_rl_%'" );
}

// ---------------------------------------------------------------------------
section( 'Cleanup' );
if ( in_array( '--keep-demo', $argv, true ) ) {
	$wpdb->delete( Database::table( 'vehicles' ), array( 'dealer_id' => $dealer_id ) );
	$wpdb->delete( Database::table( 'dealers' ), array( 'id' => $dealer_id ) );
	Vehicles::flush_cache();
	echo "  demo data kept (--keep-demo)\n";
} else {
	Demo_Data::remove();
	check( 'Demo removal leaves no demo record', 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'vehicles' ) . ' WHERE is_demo = 1' ) && 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'dealers' ) . ' WHERE is_demo = 1' ) && 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'vehicle_media' ) ) );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
