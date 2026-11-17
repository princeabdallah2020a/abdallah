<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Sync\LocalTransport;

final class LocalTransportTest extends Wotoyi_Test_Case {

	private function enable(): void {
		update_option( \Wotoyi\Marketplace\Settings::OPTION, array( 'local_mode' => true ) );
	}

	public function test_local_mode_is_off_by_default_and_everything_refuses(): void {
		$this->assertFalse( LocalTransport::enabled() );
		$this->assertThrows( fn() => wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Démo' ) ), 'local_mode_disabled' );
		$this->assertThrows( fn() => wotoyi_marketplace_local_receive( 'demo-dealer-1', Fixtures::event( 'vehicle.published', 'v1', 1, Fixtures::vehicle() ) ), 'local_mode_disabled' );
		$this->assertSame( 0, Fixtures::table_count( 'dealers' ) );
	}

	public function test_vehicle_added_locally_appears_in_marketplace_search_without_http(): void {
		$this->enable();
		$d = wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Concessionnaire Démo', 'city' => 'Dakar' ) );
		$this->assertSame( 'active', $d['status'] );
		$this->assertSame( 'local', $d['transport'] );
		$res = wotoyi_marketplace_local_receive( 'demo-dealer-1', Fixtures::event( 'vehicle.published', 'car-1', 1, Fixtures::vehicle() ) );
		$this->assertSame( 'applied', $res['status'] );
		$this->assertSame( 1, Plugin::search()->count( array() ) );
		$this->assertSame( 'Concessionnaire Démo', Plugin::search()->search( array() )['items'][0]['dealer']['name'] );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log', "transport = 'local'" ), 'same ledger as HTTP' );
	}

	public function test_same_code_path_as_http_idempotency_stale_and_validation(): void {
		$this->enable();
		wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Démo' ) );
		$ev  = Fixtures::event( 'vehicle.published', 'car-1', 5, Fixtures::vehicle() );
		$one = wotoyi_marketplace_local_receive( 'demo-dealer-1', $ev );
		$two = wotoyi_marketplace_local_receive( 'demo-dealer-1', $ev );
		$this->assertTrue( $two['duplicate'] );
		$this->assertSame( $one['entity']['id'], $two['entity']['id'] );
		$this->assertSame( 'stale', wotoyi_marketplace_local_receive( 'demo-dealer-1', Fixtures::event( 'vehicle.updated', 'car-1', 4, Fixtures::vehicle() ) )['status'] );
		$this->assertSame( 'rejected', wotoyi_marketplace_local_receive( 'demo-dealer-1', Fixtures::event( 'vehicle.published', 'car-2', 1, array( 'price' => 1 ) ) )['status'] );
		$this->assertSame( 'applied', wotoyi_marketplace_local_receive( 'demo-dealer-1', Fixtures::event( 'vehicle.deleted', 'car-1', 6 ) )['status'] );
		$this->assertSame( 0, Plugin::search()->count( array() ) );
	}

	public function test_http_and_local_produce_identical_stored_rows(): void {
		$this->enable();
		wotoyi_marketplace_local_register_dealer( 'demo-local-01', array( 'name' => 'Local' ) );
		$http = Fixtures::dealer( 'Http' );
		$fields = Fixtures::vehicle( array( 'equipment' => array( 'comfort' => array( 'air_conditioning' ) ), 'description' => '<p>Hello</p>' ) );
		wotoyi_marketplace_local_receive( 'demo-local-01', Fixtures::event( 'vehicle.published', 'same', 1, $fields ) );
		Fixtures::call_signed( $http, 'POST', '/wotoyi/v1/sync/events', Fixtures::event( 'vehicle.published', 'same', 1, $fields ) );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM wp_wotoyi_vehicles ORDER BY id' );
		$this->assertCount( 2, $rows );
		foreach ( array( 'id', 'uuid', 'dealer_id', 'slug', 'created_at', 'updated_at', 'published_at' ) as $k ) {
			unset( $rows[0][ $k ], $rows[1][ $k ] );
		}
		$this->assertSame( $rows[0], $rows[1], 'identical content through both transports' );
	}

	public function test_local_dealer_has_no_credentials_and_cannot_use_http(): void {
		$this->enable();
		wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Démo' ) );
		$row = Plugin::dealers()->find_by_source_uuid( 'demo-dealer-1' );
		$this->assertNull( $row['key_id'] );
		$this->assertNull( $row['secret_enc'] );
		// Pairing a local dealer through HTTP is impossible: no code can be issued/accepted.
		$code = Plugin::dealers()->issue_pairing_code( (int) $row['id'], 900 );
		$this->assertStatus( 403, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/sync/pair', array( 'code' => $code, 'source_dealer_uuid' => 'demo-dealer-1' ) ) ) );
	}

	public function test_local_mode_cannot_take_over_an_http_dealer(): void {
		$this->enable();
		$http = Fixtures::dealer( 'Real' );
		$sid  = $http['row']['source_dealer_uuid'];
		$this->assertThrows( fn() => wotoyi_marketplace_local_register_dealer( $sid, array( 'name' => 'Evil' ) ), 'dealer_is_not_local' );
		$this->assertThrows( fn() => wotoyi_marketplace_local_receive( $sid, Fixtures::event( 'vehicle.published', 'x', 1, Fixtures::vehicle() ) ), 'unknown_local_dealer' );
		$this->assertThrows( fn() => wotoyi_marketplace_local_receive( 'nobody', Fixtures::event( 'vehicle.published', 'x', 1, Fixtures::vehicle() ) ), 'unknown_local_dealer' );
	}

	public function test_suspended_local_dealer_is_refused(): void {
		$this->enable();
		$d = wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Démo' ) );
		Plugin::dealers()->set_status( (int) Plugin::dealers()->find_by_uuid( $d['id'] )['id'], 'suspended' );
		$this->assertThrows( fn() => wotoyi_marketplace_local_receive( 'demo-dealer-1', Fixtures::event( 'vehicle.published', 'x', 1, Fixtures::vehicle() ) ), 'dealer_inactive' );
	}

	public function test_batch_services_and_lead_roundtrip_locally(): void {
		$this->enable();
		wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Démo' ) );
		$t   = Plugin::local_transport();
		$res = $t->send_batch( 'demo-dealer-1', array(
			Fixtures::event( 'vehicle.published', 'b1', 1, Fixtures::vehicle() ),
			Fixtures::event( 'service.published', 's1', 1, array( 'title' => 'Lavage', 'category' => 'detailing' ) ),
		) );
		$this->assertSame( array( 'applied' => 2 ), $res['summary'] );
		$this->assertSame( 1, Plugin::search()->search_services( array() )['total'] );
		$vehicle = Plugin::search()->published_vehicle_ref( Plugin::vehicles()->find_by_source( (int) Plugin::dealers()->find_by_source_uuid( 'demo-dealer-1' )['id'], 'b1' )['uuid'] );
		Plugin::leads()->create( array( 'dealer_id' => $vehicle['dealer_id'], 'vehicle_id' => $vehicle['id'], 'phone' => '+221770000000', 'consent' => true ) );
		$leads = $t->pull_leads( 'demo-dealer-1' );
		$this->assertCount( 1, $leads );
		$this->assertSame( 1, $t->ack_leads( 'demo-dealer-1', array( $leads[0]['uuid'] ) ) );
		$this->assertCount( 0, $t->pull_leads( 'demo-dealer-1' ) );
	}

	public function test_ensure_dealer_is_idempotent_and_validates_ids(): void {
		$this->enable();
		wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'name' => 'Démo', 'city' => 'Dakar' ) );
		wotoyi_marketplace_local_register_dealer( 'demo-dealer-1', array( 'city' => 'Thies' ) );
		$this->assertSame( 1, Fixtures::table_count( 'dealers' ) );
		$this->assertSame( 'Thies', Plugin::dealers()->find_by_source_uuid( 'demo-dealer-1' )['city'] );
		$this->assertSame( 'Démo', Plugin::dealers()->find_by_source_uuid( 'demo-dealer-1' )['name'] );
		$this->assertThrows( fn() => wotoyi_marketplace_local_register_dealer( "bad id'; --", array( 'name' => 'X' ) ), 'invalid_dealer_id' );
	}
}
