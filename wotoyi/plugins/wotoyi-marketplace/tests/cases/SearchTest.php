<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\Clock;

final class SearchTest extends Wotoyi_Test_Case {

	private array $A;
	private array $B;
	private array $map = array(); // uuid => source id

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
	}

	private function seed(): void {
		$this->A = Fixtures::dealer( 'Dealer A', array(), true );
		$this->B = Fixtures::dealer( 'Dealer B', array( 'city' => 'Thies' ) );
		$t0      = self::T0;

		$cars = array(
			array( 'A', 'v1', $t0 - 10 * DAY_IN_SECONDS, array( 'make' => 'Toyota', 'model' => 'RAV4', 'year' => 2021, 'body_type' => 'suv', 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'drive_type' => 'awd', 'condition_type' => 'used', 'mileage_km' => 42000, 'price' => 15000000, 'city' => 'Dakar', 'area' => 'Almadies', 'ext_colour' => 'white', 'int_colour' => 'black', 'upholstery' => 'fabric', 'seats' => 5, 'doors' => 5, 'engine_cc' => 2500, 'power_hp' => 200, 'consumption_combined' => 6.5, 'origin_country' => 'JP', 'customs_status' => 'cleared', 'registration_country' => 'SN', 'owners_count' => 1, 'accident_free' => true, 'service_book' => 'full', 'warranty' => true, 'delivery_available' => true, 'financing_available' => true, 'trade_in_accepted' => true, 'latitude' => 14.7167, 'longitude' => -17.4677, 'gallery' => array( 'https://c.test/1.jpg', 'https://c.test/2.jpg' ), 'video_url' => 'https://c.test/v.mp4', 'equipment' => array( 'comfort' => array( 'air_conditioning', 'cruise_control' ), 'safety' => array( 'abs' ), 'multimedia' => array( 'bluetooth' ) ) ) ),
			array( 'A', 'v2', $t0 + 3600, array( 'make' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'body_type' => 'sedan', 'transmission' => 'manual', 'drive_type' => 'fwd', 'mileage_km' => 90000, 'price' => 7000000, 'area' => 'Plateau', 'ext_colour' => 'white', 'doors' => 4, 'engine_cc' => 1800, 'power_hp' => 140, 'consumption_combined' => 6.0, 'origin_country' => 'JP', 'customs_status' => 'cleared', 'owners_count' => 3, 'service_book' => 'partial', 'latitude' => 14.69, 'longitude' => -17.44, 'equipment' => array( 'comfort' => array( 'air_conditioning' ), 'safety' => array( 'abs' ) ) ) ),
			array( 'B', 'v3', $t0 + 2 * 3600, array( 'make' => 'Hyundai', 'model' => 'Tucson', 'year' => 2022, 'body_type' => 'suv', 'fuel_type' => 'diesel', 'drive_type' => 'fwd', 'condition_type' => 'imported', 'mileage_km' => 30000, 'price' => 11000000, 'city' => 'Thies', 'ext_colour' => 'grey', 'engine_cc' => 1600, 'power_hp' => 136, 'consumption_combined' => 5.5, 'origin_country' => 'KR', 'customs_status' => 'in_transit', 'owners_count' => 1, 'accident_free' => true, 'service_book' => 'none', 'latitude' => 14.79, 'longitude' => -16.93, 'cover_image' => null, 'equipment' => array( 'comfort' => array( 'air_conditioning', 'keyless_entry' ), 'driver_assistance' => array( 'rear_camera' ) ) ) ),
			array( 'A', 'v4', $t0 + 3 * 3600, array( 'make' => 'Tesla', 'model' => 'Model 3', 'year' => 2023, 'body_type' => 'sedan', 'fuel_type' => 'electric', 'drive_type' => 'rwd', 'condition_type' => 'new', 'mileage_km' => 0, 'price' => 25000000, 'ext_colour' => 'black', 'doors' => 4, 'power_hp' => 283, 'electric_range_km' => 500, 'battery_kwh' => 60.0, 'origin_country' => 'US', 'customs_status' => 'cleared', 'owners_count' => 0, 'accident_free' => true, 'warranty' => true, 'warranty_months' => 48, 'view360_url' => 'https://c.test/360', 'latitude' => 14.72, 'longitude' => -17.47, 'equipment' => array( 'driver_assistance' => array( 'rear_camera', 'camera_360', 'lane_assist' ), 'multimedia' => array( 'gps', 'bluetooth' ) ) ) ),
			array( 'B', 'v5', $t0 + 4 * 3600, array( 'make' => 'Peugeot', 'model' => '3008', 'year' => 2019, 'body_type' => 'suv', 'fuel_type' => 'diesel', 'drive_type' => 'fwd', 'condition_type' => 'imported', 'mileage_km' => 80000, 'price' => 8500000, 'city' => 'Mbour', 'ext_colour' => 'grey', 'engine_cc' => 1500, 'power_hp' => 130, 'consumption_combined' => 4.8, 'origin_country' => 'FR', 'customs_status' => 'cleared', 'owners_count' => 2, 'service_book' => 'full', 'latitude' => 14.42, 'longitude' => -16.96, 'cover_image' => null ) ),
			array( 'B', 'v6', $t0 + 5 * 3600, array( 'make' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'body_type' => 'suv', 'mileage_km' => 60000, 'price' => 9000000, 'offer_type' => 'both', 'rental_price_day' => 40000, 'rental_price_month' => 900000, 'rental_min_days' => 3, 'ext_colour' => 'silver', 'seats' => 7, 'latitude' => 14.70, 'longitude' => -17.45, 'cover_image' => null, 'equipment' => array( 'interior' => array( 'seven_seats' ), 'comfort' => array( 'air_conditioning' ) ) ) ),
			array( 'A', 'v7', $t0 + 6 * 3600, array( 'make' => 'Toyota', 'model' => 'Hilux', 'year' => 2020, 'body_type' => 'pickup', 'fuel_type' => 'diesel', 'transmission' => 'manual', 'drive_type' => '4x4', 'mileage_km' => 120000, 'price' => 12000000, 'city' => 'Thies', 'doors' => 4, 'engine_cc' => 2400, 'power_hp' => 150, 'origin_country' => 'JP', 'customs_status' => 'cleared', 'owners_count' => 2, 'latitude' => 14.79, 'longitude' => -16.93, 'cover_image' => null ) ),
			array( 'B', 'v8', $t0 + 7 * 3600, array( 'make' => 'Renault', 'model' => 'Kangoo', 'year' => 2017, 'body_type' => 'van', 'fuel_type' => 'diesel', 'transmission' => 'manual', 'drive_type' => 'fwd', 'mileage_km' => 150000, 'price' => null, 'offer_type' => 'rental', 'rental_price_day' => 30000, 'rental_min_days' => 1, 'ext_colour' => 'white', 'seats' => 2, 'doors' => 4, 'latitude' => 14.75, 'longitude' => -17.40, 'cover_image' => null ) ),
			array( 'A', 'v9', $t0 + 8 * 3600, array( 'make' => 'BMW', 'model' => 'X5', 'year' => 2019, 'body_type' => 'suv', 'fuel_type' => 'diesel', 'transmission' => 'automatic', 'drive_type' => 'awd', 'mileage_km' => 70000, 'price' => 28000000, 'ext_colour' => 'black', 'int_colour' => 'beige', 'upholstery' => 'leather', 'engine_cc' => 3000, 'power_hp' => 265, 'consumption_combined' => 7.5, 'origin_country' => 'DE', 'customs_status' => 'cleared', 'owners_count' => 2, 'accident_free' => false, 'service_book' => 'full', 'latitude' => 14.72, 'longitude' => -17.46, 'equipment' => array( 'interior' => array( 'leather_seats' ), 'comfort' => array( 'air_conditioning', 'cruise_control', 'keyless_entry' ), 'safety' => array( 'abs' ) ) ) ),
		);
		foreach ( $cars as [ $who, $sid, $time, $fields ] ) {
			Clock::set( $time );
			$base = Fixtures::vehicle( array( 'city' => 'Dakar', 'country' => 'SN', 'cover_image' => 'https://c.test/cover-' . $sid . '.jpg' ) );
			Fixtures::publish( 'A' === $who ? $this->A : $this->B, $sid, array_merge( $base, $fields ), 1 );
		}
		Clock::set( $t0 + 10 * 3600 );

		// Extra listings that must NEVER show in search.
		Fixtures::publish( $this->A, 'v10-unpublished', array( 'make' => 'Toyota' ), 1 );
		Fixtures::publish( $this->A, 'v11-sold', array( 'make' => 'Toyota' ), 1 );
		Fixtures::publish( $this->A, 'v12-deleted', array( 'make' => 'Toyota' ), 1 );
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'vehicle.unpublished', 'v10-unpublished', 2 ) );
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'vehicle.sold', 'v11-sold', 2 ) );
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'vehicle.deleted', 'v12-deleted', 2 ) );
		$C = Fixtures::dealer( 'Dealer C' );
		Fixtures::publish( $C, 'v13-suspended-dealer', array( 'make' => 'Toyota' ), 1 );
		Plugin::dealers()->set_status( (int) $C['row']['id'], 'suspended' );

		global $wpdb;
		$wpdb->query( "UPDATE wp_wotoyi_vehicles SET featured = 1 WHERE source_id = 'v9'" );
		Plugin::vehicles()->set_inspection( (int) Plugin::vehicles()->find_by_source( (int) $this->B['row']['id'], 'v5' )['id'], true, '2026-11-01', 'https://c.test/report.pdf' );
		foreach ( $wpdb->get_results( 'SELECT uuid, source_id FROM wp_wotoyi_vehicles' ) as $r ) {
			$this->map[ $r['uuid'] ] = $r['source_id'];
		}
		\Wotoyi\Marketplace\Support\Cache::bump();
	}

	/** Source ids of results (ordered as returned). */
	private function ids( array $params ): array {
		$params += array( 'per_page' => 48 );
		return array_map( fn( $i ) => $this->map[ $i['id'] ], Plugin::search()->search( $params )['items'] );
	}

	/** Source ids sorted, for set comparisons. */
	private function set( array $params ): array {
		$ids = $this->ids( $params );
		sort( $ids );
		return $ids;
	}

	public function test_default_results_exclude_unpublished_sold_deleted_and_suspended_dealers(): void {
		$r = Plugin::search()->search( array() );
		$this->assertSame( 9, $r['total'] );
		$this->assertSame( 1, $r['page'] );
		$this->assertSame( 12, $r['per_page'] );
		$this->assertCount( 9, $r['items'] );
		$this->assertSame( 10, Fixtures::table_count( 'vehicles', "status = 'published'" ), 'v1-v9 plus the suspended dealer\'s listing are stored as published' );
		$this->assertSame( 13, Fixtures::table_count( 'vehicles' ), 'unpublished, sold and deleted rows exist but are hidden' );
	}

	public function test_keyword_search(): void {
		$this->assertSame( array( 'v1', 'v2', 'v7' ), $this->set( array( 'q' => 'toyota' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'q' => 'toyota suv' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'q' => 'rav' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v4', 'v6', 'v8', 'v9' ), $this->set( array( 'q' => 'dakar' ) ) );
		$this->assertCount( 9, $this->ids( array( 'q' => 'a' ) ), 'single-character terms are ignored' );
	}

	public function test_like_wildcards_are_escaped_and_sql_injection_is_inert(): void {
		$this->assertSame( array(), $this->ids( array( 'q' => '%%' ) ) );
		$this->assertSame( array(), $this->ids( array( 'q' => '__' ) ) );
		$this->assertSame( array(), $this->ids( array( 'q' => "' OR 1=1 --" ) ) );
		$this->assertSame( array(), $this->ids( array( 'q' => "x'; DROP TABLE wp_wotoyi_vehicles; --" ) ) );
		$this->assertSame( array(), $this->ids( array( 'make' => "Toyota' OR '1'='1" ) ) );
		$this->assertSame( array(), $this->ids( array( 'make' => array( "' OR 1=1 --" ) ) ) );
		$this->assertSame( array(), $this->ids( array( 'city' => "Dakar' UNION SELECT 1 --" ) ) );
		$this->assertCount( 9, $this->ids( array( 'sort' => 'price; DROP TABLE x' ) ), 'unknown sort falls back' );
		$this->assertSame( 9, Plugin::search()->count( array() ), 'table still intact' );
	}

	public function test_unknown_or_malicious_parameters_cannot_widen_results(): void {
		foreach ( array( 'status' => 'unpublished', 'v.status' => 'draft', 'dealer_id' => '999', 'd.status' => 'suspended', 'where' => '1=1', 'deleted_at' => 'x' ) as $k => $v ) {
			$this->assertCount( 9, $this->ids( array( $k => $v ) ), $k );
		}
		$this->assertCount( 9, $this->ids( array( 'price_min' => 'abc', 'year_min' => array( 1 ), 'seats_min' => '1e3' ) ), 'invalid numbers ignored' );
	}

	public function test_make_model_trim_and_multi_select(): void {
		$this->assertSame( array( 'v1', 'v2', 'v7' ), $this->set( array( 'make' => 'Toyota' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v6', 'v7' ), $this->set( array( 'make' => 'Toyota,Kia' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v6', 'v7' ), $this->set( array( 'make' => array( 'Toyota', 'Kia' ) ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'make' => 'Toyota', 'model' => 'RAV4' ) ) );
		$this->assertSame( array( 'v1', 'v2' ), $this->set( array( 'make' => 'Toyota', 'model' => 'RAV4,Corolla' ) ) );
	}

	public function test_enum_filters(): void {
		$this->assertSame( array( 'v1', 'v3', 'v5', 'v6', 'v9' ), $this->set( array( 'body_type' => 'suv' ) ) );
		$this->assertSame( array( 'v1', 'v3', 'v5', 'v6', 'v7', 'v9' ), $this->set( array( 'body_type' => 'suv,pickup' ) ) );
		$this->assertSame( array( 'v3', 'v5', 'v7', 'v8', 'v9' ), $this->set( array( 'fuel' => 'diesel' ) ) );
		$this->assertSame( array( 'v4' ), $this->set( array( 'fuel' => 'electric' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v4', 'v6' ), $this->set( array( 'fuel' => 'petrol,electric' ) ), 'fixture default petrol' );
		$this->assertSame( array( 'v2', 'v7', 'v8' ), $this->set( array( 'transmission' => 'manual' ) ) );
		$this->assertSame( array( 'v1', 'v6', 'v9' ), $this->set( array( 'drive' => 'awd' ) ), 'v6 inherits the fixture default awd' );
		$this->assertSame( array( 'v7' ), $this->set( array( 'drive' => '4x4' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v7', 'v8' ), $this->set( array( 'ext_colour' => 'white' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'int_colour' => 'black' ) ) );
		$this->assertSame( array( 'v9' ), $this->set( array( 'upholstery' => 'leather' ) ) );
		$this->assertSame( array( 'v3', 'v5' ), $this->set( array( 'condition' => 'imported' ) ) );
		$this->assertSame( array( 'v4' ), $this->set( array( 'condition' => 'new' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v7' ), $this->set( array( 'origin_country' => 'jp' ) ) );
		$this->assertSame( array( 'v3' ), $this->set( array( 'customs_status' => 'in_transit' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'registration_country' => 'SN' ) ) );
		$this->assertCount( 9, $this->ids( array( 'fuel' => 'steam' ) ), 'invalid enum value is ignored, not "no results"' );
	}

	public function test_numeric_range_filters(): void {
		$this->assertSame( array( 'v1', 'v3', 'v4', 'v7', 'v9' ), $this->set( array( 'price_min' => 10000000 ) ) );
		$this->assertSame( array( 'v2', 'v5', 'v6' ), $this->set( array( 'price_max' => 9000000 ) ), 'null price never matches a price range' );
		$this->assertSame( array( 'v1', 'v3', 'v7' ), $this->set( array( 'price_min' => 10000000, 'price_max' => 20000000 ) ) );
		$this->assertSame( array( 'v1', 'v3', 'v4' ), $this->set( array( 'year_min' => 2021 ) ) );
		$this->assertSame( array( 'v2', 'v8' ), $this->set( array( 'year_max' => 2018 ) ) );
		$this->assertSame( array( 'v1', 'v3', 'v4' ), $this->set( array( 'mileage_max' => 50000 ) ), 'zero mileage counts' );
		$this->assertSame( array( 'v7', 'v8' ), $this->set( array( 'mileage_min' => 100000 ) ) );
		$this->assertSame( array( 'v1', 'v7', 'v9' ), $this->set( array( 'engine_min' => 2000 ) ) );
		$this->assertSame( array( 'v3', 'v5' ), $this->set( array( 'engine_max' => 1600 ) ) );
		$this->assertSame( array( 'v1', 'v4', 'v9' ), $this->set( array( 'power_min' => 200 ) ) );
		$this->assertSame( array( 'v3', 'v5' ), $this->set( array( 'power_max' => 136 ) ) );
		$this->assertSame( array( 'v3', 'v5' ), $this->set( array( 'consumption_max' => '5.5' ) ) );
		$this->assertSame( array( 'v4' ), $this->set( array( 'range_min' => 400 ) ) );
		$this->assertSame( array( 'v6' ), $this->set( array( 'seats_min' => 7 ) ) );
		$this->assertSame( array( 'v2', 'v4', 'v7', 'v8' ), $this->set( array( 'doors' => 4 ) ) );
		$this->assertCount( 9, $this->ids( array( 'doors' => '4,5' ) ) );
		$this->assertSame( array( 'v1', 'v3', 'v4' ), $this->set( array( 'owners_max' => 1 ) ) );
		$this->assertSame( array( 'v6' ), $this->set( array( 'monthly_price_max' => 1000000 ) ) );
		$this->assertSame( array( 'v5', 'v9' ), $this->set( array( 'year_min' => 2019, 'year_max' => 2019 ) ) );
	}

	public function test_range_values_are_clamped(): void {
		$this->assertCount( 9, $this->ids( array( 'price_max' => '99999999999999999' ) ) );
		$this->assertCount( 0, $this->ids( array( 'year_min' => '99999' ) ), 'clamped to 2100: nothing newer' );
		$this->assertSame( array( 'v4' ), $this->ids( array( 'mileage_max' => -50 ) ), 'negative bound clamped to 0 (only the zero-mileage car)' );
		$this->assertCount( 9, $this->ids( array( 'mileage_min' => -50 ) ) );
	}

	public function test_flag_filters(): void {
		$this->assertSame( array( 'v1', 'v3', 'v4' ), $this->set( array( 'accident_free' => '1' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v5', 'v9' ), $this->set( array( 'service_history' => 'true' ) ) );
		$this->assertSame( array( 'v1', 'v4' ), $this->set( array( 'warranty' => 'yes' ) ) );
		$this->assertSame( array( 'v5' ), $this->set( array( 'inspected' => '1' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v4', 'v7', 'v9' ), $this->set( array( 'verified_dealer' => '1' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'delivery' => '1' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'financing' => '1' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'trade_in' => '1' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v4', 'v9' ), $this->set( array( 'has_photos' => '1' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'has_video' => '1' ) ) );
		$this->assertSame( array( 'v4' ), $this->set( array( 'has_360' => '1' ) ) );
		$this->assertCount( 9, $this->ids( array( 'accident_free' => '0' ) ), 'false never narrows' );
	}

	public function test_dealer_city_area_and_location_text_filters(): void {
		$this->assertSame( array( 'v3', 'v5', 'v6', 'v8' ), $this->set( array( 'dealer' => $this->B['row']['uuid'] ) ) );
		$this->assertSame( array( 'v3', 'v7' ), $this->set( array( 'city' => 'Thies' ) ) );
		$this->assertSame( array( 'v3', 'v5', 'v7' ), $this->set( array( 'city' => 'Thies,Mbour' ) ) );
		$this->assertSame( array( 'v1' ), $this->set( array( 'area' => 'Almadies' ) ) );
		$this->assertCount( 9, $this->ids( array( 'dealer' => 'not-a-uuid' ) ), 'invalid dealer id ignored' );
	}

	public function test_equipment_multi_select_requires_all_codes(): void {
		$this->assertSame( array( 'v1', 'v2', 'v3', 'v6', 'v9' ), $this->set( array( 'equipment' => 'air_conditioning' ) ) );
		$this->assertSame( array( 'v1', 'v2', 'v9' ), $this->set( array( 'equipment' => 'air_conditioning,abs' ) ) );
		$this->assertSame( array( 'v1', 'v9' ), $this->set( array( 'equipment' => array( 'air_conditioning', 'abs', 'cruise_control' ) ) ) );
		$this->assertSame( array( 'v4' ), $this->set( array( 'equipment' => 'rear_camera,camera_360' ) ) );
		$this->assertSame( array( 'v6' ), $this->set( array( 'equipment' => 'seven_seats' ) ) );
		$this->assertCount( 9, $this->ids( array( 'equipment' => 'laser_cannon' ) ), 'unknown code ignored' );
	}

	public function test_offer_type_and_rental_duration(): void {
		$this->assertSame( array( 'v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v9' ), $this->set( array( 'offer_type' => 'sale' ) ), 'sale includes "both"' );
		$this->assertSame( array( 'v6', 'v8' ), $this->set( array( 'offer_type' => 'rental' ) ) );
		$this->assertSame( array( 'v6' ), $this->set( array( 'offer_type' => 'both' ) ) );
		$this->assertSame( array( 'v8' ), $this->set( array( 'offer_type' => 'rental', 'rental_duration' => 2 ) ) );
		$this->assertSame( array( 'v6', 'v8' ), $this->set( array( 'offer_type' => 'rental', 'rental_duration' => 3 ) ) );
	}

	public function test_posted_since(): void {
		$this->assertSame( array( 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'v9' ), $this->set( array( 'posted_since' => 7 ) ) );
		$this->assertCount( 9, $this->ids( array( 'posted_since' => 30 ) ) );
	}

	public function test_geo_radius_and_distance_sort(): void {
		$p = array( 'lat' => 14.7167, 'lng' => -17.4677, 'radius_km' => 5 );
		$this->assertSame( array( 'v1', 'v2', 'v4', 'v6', 'v9' ), $this->set( $p ) );
		$this->assertSame( array( 'v1', 'v2', 'v4', 'v6', 'v8', 'v9' ), $this->set( array( 'radius_km' => 10 ) + $p ) );
		$res = Plugin::search()->search( $p + array( 'sort' => 'distance' ) );
		$this->assertSame( 'distance', $res['sort'] );
		$this->assertSame( 'v1', $this->map[ $res['items'][0]['id'] ] );
		$d = array_column( $res['items'], 'distance_km' );
		$sorted = $d;
		sort( $sorted );
		$this->assertSame( $sorted, $d, 'ascending distance' );
		$this->assertTrue( max( $d ) <= 5.0 );
		$this->assertSame( 'relevance', Plugin::search()->search( array( 'sort' => 'distance' ) )['sort'], 'distance sort needs coordinates' );
		$this->assertCount( 9, $this->ids( array( 'lat' => 14.7 ) ), 'lat alone is ignored' );
	}

	public function test_sorting(): void {
		$this->assertSame( array( 'v2', 'v5', 'v6', 'v3', 'v7', 'v1', 'v4', 'v9', 'v8' ), $this->ids( array( 'sort' => 'price_asc' ) ), 'null prices last' );
		$this->assertSame( array( 'v9', 'v4', 'v1', 'v7', 'v3', 'v6', 'v5', 'v2', 'v8' ), $this->ids( array( 'sort' => 'price_desc' ) ) );
		$this->assertSame( array( 'v4', 'v3', 'v1', 'v7', 'v6', 'v9', 'v5', 'v2', 'v8' ), $this->ids( array( 'sort' => 'year_desc' ) ) );
		$this->assertSame( array( 'v8', 'v2', 'v9', 'v5', 'v7', 'v6', 'v1', 'v3', 'v4' ), $this->ids( array( 'sort' => 'year_asc' ) ) );
		$this->assertSame( array( 'v4', 'v3', 'v1', 'v6', 'v9', 'v5', 'v2', 'v7', 'v8' ), $this->ids( array( 'sort' => 'mileage_asc' ) ) );
		$this->assertSame( array( 'v8', 'v7', 'v2', 'v5', 'v9', 'v6', 'v1', 'v3', 'v4' ), $this->ids( array( 'sort' => 'mileage_desc' ) ) );
		$this->assertSame( array( 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1' ), $this->ids( array( 'sort' => 'newest' ) ) );
		$rel = $this->ids( array( 'sort' => 'relevance' ) );
		$this->assertSame( 'v9', $rel[0], 'featured first' );
		$this->assertSame( array( 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1' ), $rel );
		global $wpdb;
		$wpdb->query( "UPDATE wp_wotoyi_vehicles SET views = 50 WHERE source_id = 'v3'" );
		$this->assertSame( 'v3', $this->ids( array( 'sort' => 'views' ) )[0] );
	}

	public function test_pagination_and_limits(): void {
		$p1 = Plugin::search()->search( array( 'per_page' => 4, 'page' => 1, 'sort' => 'newest' ) );
		$this->assertSame( 9, $p1['total'] );
		$this->assertSame( 3, $p1['pages'] );
		$this->assertCount( 4, $p1['items'] );
		$p3 = Plugin::search()->search( array( 'per_page' => 4, 'page' => 3, 'sort' => 'newest' ) );
		$this->assertCount( 1, $p3['items'] );
		$this->assertSame( 'v1', $this->map[ $p3['items'][0]['id'] ] );
		$all = array_merge(
			array_column( $p1['items'], 'id' ),
			array_column( Plugin::search()->search( array( 'per_page' => 4, 'page' => 2, 'sort' => 'newest' ) )['items'], 'id' ),
			array_column( $p3['items'], 'id' )
		);
		$this->assertCount( 9, array_unique( $all ), 'pages are disjoint and complete' );
		$this->assertSame( 48, Plugin::search()->search( array( 'per_page' => 1000 ) )['per_page'], 'per_page capped' );
		$this->assertSame( 1, Plugin::search()->search( array( 'per_page' => 0 ) )['per_page'] );
		$this->assertSame( array(), Plugin::search()->search( array( 'per_page' => 4, 'page' => 99 ) )['items'], 'out-of-range page is empty' );
		$this->assertSame( 12, Plugin::search()->search( array( 'per_page' => 'abc' ) )['per_page'] );
		$deep = Plugin::search()->search( array( 'per_page' => 48, 'page' => 999999 ) );
		$this->assertTrue( $deep['page'] <= 208, 'offset is bounded' );
	}

	public function test_live_count_matches_search_total_and_updates_after_changes(): void {
		$this->assertSame( 5, Plugin::search()->count( array( 'body_type' => 'suv' ) ) );
		$this->assertSame( Plugin::search()->search( array( 'body_type' => 'suv' ) )['total'], Plugin::search()->count( array( 'body_type' => 'suv' ) ) );
		Plugin::receiver()->handle_event( $this->B['row'], Fixtures::event( 'vehicle.unpublished', 'v3', 2 ) );
		$this->assertSame( 4, Plugin::search()->count( array( 'body_type' => 'suv' ) ), 'cache invalidated by write' );
		$res = Fixtures::get( '/wotoyi/v1/vehicles/count', array( 'body_type' => 'suv' ) );
		$this->assertStatus( 200, $res );
		$this->assertSame( array( 'count' => 4 ), $res->get_data() );
	}

	public function test_facets_exclude_their_own_filter_and_respect_others(): void {
		$f = Plugin::search()->facets( array() );
		$makes = array_column( $f['facets']['make'], 'count', 'value' );
		$this->assertSame( 3, $makes['Toyota'] );
		$this->assertSame( 1, $makes['Kia'] );
		$this->assertArrayNotHasKey( 'model', $f['facets'], 'model facet needs a make' );

		$f = Plugin::search()->facets( array( 'fuel' => 'diesel' ) );
		$fuel = array_column( $f['facets']['fuel'], 'count', 'value' );
		$this->assertSame( 5, $fuel['diesel'] );
		$this->assertSame( 3, $fuel['petrol'], 'own filter excluded: users still see alternatives' );
		$this->assertSame( 1, $fuel['electric'] );
		$body = array_column( $f['facets']['body_type'], 'count', 'value' );
		$this->assertSame( 3, $body['suv'], 'other facets respect fuel=diesel' );
		$this->assertSame( 1, $body['van'] );
		$this->assertArrayNotHasKey( 'sedan', $body );

		$f = Plugin::search()->facets( array( 'make' => 'Toyota' ) );
		$models = array_column( $f['facets']['model'], 'count', 'value' );
		$this->assertSame( array( 'Corolla' => 1, 'Hilux' => 1, 'RAV4' => 1 ), $models );
		$this->assertSame( 3, array_column( $f['facets']['make'], 'count', 'value' )['Toyota'] );
		$this->assertTrue( count( $f['facets']['make'] ) > 1, 'make facet ignores the make filter' );
	}

	public function test_facet_ranges_and_equipment_counts_and_selection(): void {
		$f = Plugin::search()->facets( array( 'price_max' => 10000000 ) );
		$this->assertSame( array( 'min' => 7000000, 'max' => 28000000 ), $f['ranges']['price'], 'price range ignores the price filter itself' );
		$this->assertSame( array( 'min' => 2018, 'max' => 2020 ), $f['ranges']['year'], 'year range respects the price filter (v2, v5, v6 only)' );
		$eq = array_column( Plugin::search()->facets( array() )['facets']['equipment'], 'count', 'value' );
		$this->assertSame( 5, $eq['air_conditioning'] );
		$this->assertSame( 3, $eq['abs'] );
		$only = Plugin::search()->facets( array( 'facets' => 'make,fuel' ) );
		$this->assertSame( array( 'make', 'fuel' ), array_keys( $only['facets'] ) );
	}

	public function test_vehicle_detail_by_uuid_and_slug_with_grouped_tabs(): void {
		$uuid = array_search( 'v1', $this->map, true );
		$d    = Plugin::search()->vehicle_detail( $uuid );
		$this->assertSame( $uuid, $d['id'] );
		$by_slug = Plugin::search()->vehicle_detail( $d['slug'] );
		$this->assertSame( $uuid, $by_slug['id'] );
		foreach ( array( 'identity', 'engine', 'consumption', 'history', 'commercial', 'media', 'equipment', 'trust' ) as $tab ) {
			$this->assertArrayHasKey( $tab, $d['tabs'], $tab );
		}
		$this->assertSame( 'Toyota', $d['tabs']['identity']['make'] );
		$this->assertSame( 2500, $d['tabs']['engine']['engine_cc'] );
		$this->assertSame( 6.5, $d['tabs']['consumption']['consumption_combined'] );
		$this->assertSame( 15000000, $d['tabs']['commercial']['price'] );
		$this->assertSame( array( 'air_conditioning', 'cruise_control' ), $d['tabs']['equipment']['comfort'] );
		$this->assertTrue( $d['tabs']['history']['accident_free'] );
		$this->assertSame( 'Dealer A', $d['dealer']['name'] );
		$this->assertTrue( $d['tabs']['trust']['verified_dealer'] );
		$this->assertFalse( $d['tabs']['trust']['wotoyi_inspected'] );
		$this->assertTrue( Plugin::search()->vehicle_detail( 'does-not-exist' ) === null );
	}

	public function test_empty_groups_are_hidden(): void {
		$d = Plugin::search()->vehicle_detail( array_search( 'v8', $this->map, true ) );
		$this->assertArrayNotHasKey( 'consumption', $d['tabs'] );
		$this->assertArrayNotHasKey( 'media', $d['tabs'] );
		$this->assertArrayNotHasKey( 'equipment', $d['tabs'] );
		$this->assertArrayHasKey( 'identity', $d['tabs'] );
	}

	public function test_private_fields_never_leave_the_server(): void {
		Fixtures::publish( $this->A, 'v-priv', array( 'vin' => 'VF1ABCDEFGH123456', 'plate' => 'DK-1234-AB', 'make' => 'Secret', 'model' => 'Car' ), 1 );
		$row = Plugin::vehicles()->find_by_source( (int) $this->A['row']['id'], 'v-priv' );
		$this->assertSame( 'VF1ABCDEFGH123456', $row['vin'], 'stored for the dealer record' );
		$detail = Plugin::search()->vehicle_detail( $row['uuid'] );
		$list   = Plugin::search()->search( array( 'q' => 'secret' ) );
		foreach ( array( json_encode( $detail ), json_encode( $list ), json_encode( Fixtures::get( '/wotoyi/v1/vehicles/' . $row['uuid'] )->get_data() ) ) as $blob ) {
			$this->assertStringNotContains( 'VF1ABCDEFGH123456', $blob );
			$this->assertStringNotContains( 'DK-1234-AB', $blob );
			$this->assertStringNotContains( '"vin"', $blob );
			$this->assertStringNotContains( '"plate"', $blob );
		}
		$this->assertStringNotContains( 'internal_id', json_encode( Fixtures::get( '/wotoyi/v1/vehicles/' . $row['uuid'] )->get_data() ) );
		$this->assertStringNotContains( 'email', json_encode( $detail['dealer'] ), 'dealer email not public' );
	}

	public function test_detail_language_fallback_chain(): void {
		Fixtures::publish( $this->A, 'v-i18n', array( 'make' => 'Lang', 'model' => 'Test', 'description_i18n' => array( 'fr' => 'Bonjour', 'wo' => 'Nanga def', 'en' => 'Hello' ) ), 1 );
		$uuid = Plugin::vehicles()->find_by_source( (int) $this->A['row']['id'], 'v-i18n' )['uuid'];
		$this->assertSame( 'Nanga def', Plugin::search()->vehicle_detail( $uuid, 'wo' )['tabs']['identity']['description'] );
		$this->assertSame( 'Hello', Plugin::search()->vehicle_detail( $uuid, 'en' )['tabs']['identity']['description'] );
		$this->assertSame( 'Bonjour', Plugin::search()->vehicle_detail( $uuid, 'ar' )['tabs']['identity']['description'], 'ar falls back to fr' );
		$this->assertSame( array( 'fr', 'en', 'wo' ), Plugin::search()->vehicle_detail( $uuid, 'fr' )['tabs']['identity']['available_languages'] );
		$rest = Fixtures::get( '/wotoyi/v1/vehicles/' . $uuid, array( 'lang' => 'wo' ) );
		$this->assertSame( 'Nanga def', $rest->get_data()['tabs']['identity']['description'] );
	}

	public function test_detail_visibility_rules(): void {
		$uuid = static fn( string $sid, array $d ) => Plugin::vehicles()->find_by_source( (int) $d['row']['id'], $sid )['uuid'];
		$this->assertSame( 'sold', Plugin::search()->vehicle_detail( $uuid( 'v11-sold', $this->A ) )['status'], 'sold stays viewable' );
		$this->assertNull( Plugin::search()->vehicle_detail( $uuid( 'v10-unpublished', $this->A ) ) );
		$this->assertNull( Plugin::search()->vehicle_detail( $uuid( 'v12-deleted', $this->A ) ) );
		global $wpdb;
		$c = $wpdb->get_var( "SELECT uuid FROM wp_wotoyi_vehicles WHERE source_id = 'v13-suspended-dealer'" );
		$this->assertNull( Plugin::search()->vehicle_detail( $c ), 'suspended dealer listing hidden' );
		$this->assertStatus( 404, Fixtures::get( '/wotoyi/v1/vehicles/' . $c ) );
		$this->assertStatus( 404, Fixtures::get( '/wotoyi/v1/vehicles/nope-nope' ) );
	}

	public function test_rest_search_endpoint_shape_headers_and_caching(): void {
		$res = Fixtures::get( '/wotoyi/v1/vehicles', array( 'make' => 'Toyota', 'per_page' => '2', 'sort' => 'price_asc' ) );
		$this->assertStatus( 200, $res );
		$d = $res->get_data();
		$this->assertSame( 3, $d['total'] );
		$this->assertSame( 2, $d['pages'] );
		$this->assertCount( 2, $d['items'] );
		$this->assertSame( array( 'make' => array( 'Toyota' ) ), $d['filters'], 'applied (normalised) filters echoed' );
		$h = $res->get_headers();
		$this->assertSame( '3', $h['X-WP-Total'] );
		$this->assertSame( '2', $h['X-WP-TotalPages'] );
		$this->assertStringContains( 'public', $h['Cache-Control'] );
		$card = $d['items'][0];
		foreach ( array( 'id', 'slug', 'title', 'make', 'model', 'year', 'price', 'currency', 'city', 'cover_image', 'dealer' ) as $k ) {
			$this->assertArrayHasKey( $k, $card, $k );
		}
		$this->assertSame( 'Toyota Corolla 2018', $card['title'], 'title composed when absent' );
		$this->assertArrayNotHasKey( 'description', $card );
		$this->assertTrue( $card['dealer']['verified'] );
	}

	public function test_rest_facets_and_meta_endpoints(): void {
		$f = Fixtures::get( '/wotoyi/v1/facets', array( 'facets' => 'make' ) );
		$this->assertStatus( 200, $f );
		$this->assertArrayHasKey( 'ranges', $f->get_data() );
		$m = Fixtures::get( '/wotoyi/v1/meta' );
		$this->assertStatus( 200, $m );
		$this->assertContains( 'price_min', $m->get_data()['filters'] );
		$this->assertContains( 'camera_360', $m->get_data()['equipment']['driver_assistance'] );
	}

	public function test_public_rate_limit(): void {
		\Wotoyi\Marketplace\Settings::defaults();
		for ( $i = 0; $i < 120; $i++ ) {
			$this->assertStatus( 200, Fixtures::get( '/wotoyi/v1/vehicles/count' ) );
		}
		$this->assertStatus( 429, Fixtures::get( '/wotoyi/v1/vehicles/count' ) );
	}

	public function test_view_counter_is_deduplicated_per_visitor(): void {
		$uuid = array_search( 'v1', $this->map, true );
		$call = fn() => rest_do_request( Fixtures::request( 'POST', "/wotoyi/v1/vehicles/$uuid/view" ) );
		$this->assertStatus( 200, $call() );
		$this->assertStatus( 200, $call() );
		$this->assertSame( '1', Plugin::vehicles()->find_by_source( (int) $this->A['row']['id'], 'v1' )['views'] );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
		$this->assertStatus( 200, $call() );
		$this->assertSame( '2', Plugin::vehicles()->find_by_source( (int) $this->A['row']['id'], 'v1' )['views'] );
		$this->assertStatus( 404, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/vehicles/' . array_search( 'v10-unpublished', $this->map, true ) . '/view' ) ) );
	}

	public function test_dealers_directory_hides_inactive_and_private_data(): void {
		$res = Fixtures::get( '/wotoyi/v1/dealers' );
		$this->assertStatus( 200, $res );
		$names = array_column( $res->get_data()['items'], 'name' );
		$this->assertSame( array( 'Dealer A', 'Dealer B' ), $names, 'verified first, suspended C hidden' );
		$blob = json_encode( $res->get_data() );
		foreach ( array( 'key_id', 'secret', 'pairing', 'email', 'wk_' ) as $leak ) {
			$this->assertStringNotContains( $leak, $blob );
		}
		$this->assertStatus( 200, Fixtures::get( '/wotoyi/v1/dealers/' . $this->A['row']['uuid'] ) );
		$this->assertStatus( 200, Fixtures::get( '/wotoyi/v1/dealers/' . $this->A['row']['slug'] ) );
		$this->assertCount( 1, Fixtures::get( '/wotoyi/v1/dealers', array( 'verified' => '1' ) )->get_data()['items'] );
	}

	public function test_combined_filters(): void {
		$this->assertSame( array( 'v1' ), $this->set( array( 'q' => 'suv', 'fuel' => 'petrol', 'drive' => 'awd', 'price_max' => 20000000, 'equipment' => 'abs', 'verified_dealer' => 1 ) ) );
		$this->assertSame( array(), $this->set( array( 'make' => 'Toyota', 'fuel' => 'electric' ) ) );
	}
}
