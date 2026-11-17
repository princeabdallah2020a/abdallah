<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\Clock;

final class ServicesTest extends Wotoyi_Test_Case {

	private array $A;
	private array $B;
	private array $map = array();

	protected function setUp(): void {
		parent::setUp();
		$this->A = Fixtures::dealer( 'Garage A', array(), true );
		$this->B = Fixtures::dealer( 'Garage B' );
		$rows = array(
			array( 'A', 's1', array( 'title' => 'Vidange complète', 'category' => 'maintenance', 'price_from' => 25000, 'currency' => 'XOF', 'city' => 'Dakar', 'latitude' => 14.7167, 'longitude' => -17.4677, 'description' => 'Huile + filtre', 'features' => array( 'Garantie 1 mois' ), 'gallery' => array( 'https://c.test/s1.jpg' ), 'description_i18n' => array( 'fr' => 'Huile', 'wo' => 'Diw' ) ) ),
			array( 'B', 's2', array( 'title' => 'Lavage auto à domicile', 'category' => 'detailing', 'price_from' => 10000, 'city' => 'Dakar', 'mobile_service' => true, 'latitude' => 14.75, 'longitude' => -17.40 ) ),
			array( 'A', 's3', array( 'title' => 'Location avec chauffeur', 'category' => 'rental_with_driver', 'price_from' => 50000, 'price_unit' => 'day', 'city' => 'Thies', 'latitude' => 14.79, 'longitude' => -16.93 ) ),
			array( 'B', 's4', array( 'title' => 'Import de véhicules', 'category' => 'import', 'price_on_request' => true, 'city' => 'Dakar' ) ),
		);
		foreach ( $rows as $i => [ $who, $sid, $f ] ) {
			Clock::set( self::T0 + $i * 3600 );
			Plugin::receiver()->handle_event( 'A' === $who ? $this->A['row'] : $this->B['row'], Fixtures::event( 'service.published', $sid, 1, $f ) );
		}
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'service.published', 's5-hidden', 1, array( 'title' => 'Hidden' ) ) );
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'service.unpublished', 's5-hidden', 2 ) );
		global $wpdb;
		foreach ( $wpdb->get_results( 'SELECT uuid, source_id FROM wp_wotoyi_services' ) as $r ) {
			$this->map[ $r['uuid'] ] = $r['source_id'];
		}
	}

	private function ids( array $p ): array {
		return array_map( fn( $i ) => $this->map[ $i['id'] ], Plugin::search()->search_services( $p + array( 'per_page' => 48 ) )['items'] );
	}

	public function test_default_listing_newest_first_and_hidden_excluded(): void {
		$this->assertSame( array( 's4', 's3', 's2', 's1' ), $this->ids( array() ) );
		$this->assertSame( 4, Plugin::search()->search_services( array() )['total'] );
	}

	public function test_filters(): void {
		$this->assertSame( array( 's1' ), $this->ids( array( 'q' => 'vidange' ) ) );
		$this->assertSame( array( 's3' ), $this->ids( array( 'category' => 'rental_with_driver' ) ) );
		$this->assertSame( array( 's3', 's1' ), $this->ids( array( 'category' => 'rental_with_driver,maintenance' ) ) );
		$this->assertSame( array( 's4', 's2', 's1' ), $this->ids( array( 'city' => 'Dakar' ) ) );
		$this->assertSame( array( 's2', 's1' ), $this->ids( array( 'price_max' => 30000 ) ), 'price_on_request (null) never matches a price cap' );
		$this->assertSame( array( 's2' ), $this->ids( array( 'mobile_service' => '1' ) ) );
		$this->assertSame( array( 's3', 's1' ), $this->ids( array( 'verified_dealer' => '1' ) ) );
		$this->assertSame( array( 's4', 's2' ), $this->ids( array( 'dealer' => $this->B['row']['uuid'] ) ) );
		$this->assertSame( array(), $this->ids( array( 'q' => "'; DROP TABLE x; --" ) ) );
		$this->assertSame( array(), $this->ids( array( 'q' => '%%' ) ) );
		$this->assertCount( 4, $this->ids( array( 'category' => 'bogus' ) ), 'unknown category ignored' );
	}

	public function test_geo_and_distance_sort(): void {
		$p = array( 'lat' => 14.7167, 'lng' => -17.4677, 'radius_km' => 15 );
		$this->assertSame( array( 's1', 's2' ), $this->ids( $p + array( 'sort' => 'distance' ) ) );
		$this->assertSame( array( 's1', 's2', 's3' ), $this->ids( array( 'radius_km' => 100 ) + $p + array( 'sort' => 'distance' ) ) );
		$d = Plugin::search()->search_services( $p + array( 'sort' => 'distance' ) )['items'];
		$this->assertTrue( $d[0]['distance_km'] < $d[1]['distance_km'] );
	}

	public function test_sorting_and_pagination(): void {
		$this->assertSame( array( 's2', 's1', 's3', 's4' ), $this->ids( array( 'sort' => 'price_asc' ) ) );
		$r = Plugin::search()->search_services( array( 'per_page' => 3, 'page' => 2 ) );
		$this->assertCount( 1, $r['items'] );
		$this->assertSame( 2, $r['pages'] );
	}

	public function test_detail_and_visibility(): void {
		$uuid = array_search( 's1', $this->map, true );
		$d    = Plugin::search()->service_detail( $uuid, 'wo' );
		$this->assertSame( 'Diw', $d['description'] );
		$this->assertSame( array( 'Garantie 1 mois' ), $d['features'] );
		$this->assertSame( 'Garage A', $d['dealer']['name'] );
		$list = Plugin::search()->search_services( array( 'q' => 'vidange' ) )['items'][0];
		$this->assertArrayNotHasKey( 'description', $list, 'list payload stays light' );
		$hidden = array_search( 's5-hidden', $this->map, true );
		$this->assertNull( Plugin::search()->service_detail( $hidden ) );
		$this->assertStatus( 200, Fixtures::get( '/wotoyi/v1/services/' . $uuid ) );
		$this->assertStatus( 404, Fixtures::get( '/wotoyi/v1/services/' . $hidden ) );
		$this->assertStringNotContains( 'internal_id', json_encode( Fixtures::get( '/wotoyi/v1/services/' . $uuid )->get_data() ) );
		$res = Fixtures::get( '/wotoyi/v1/services', array( 'city' => 'Dakar' ) );
		$this->assertSame( '3', $res->get_headers()['X-WP-Total'] );
	}

	public function test_suspended_dealer_services_are_hidden(): void {
		Plugin::dealers()->set_status( (int) $this->B['row']['id'], 'suspended' );
		$this->assertSame( array( 's3', 's1' ), $this->ids( array() ) );
	}
}
