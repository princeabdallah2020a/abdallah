<?php

use Wotoyi\Marketplace\Plugin;

final class ReceiverTest extends Wotoyi_Test_Case {

	private function recv( array $dealer, array $event ): array {
		return Plugin::receiver()->handle_event( $dealer['row'] ?? $dealer, $event, 'http' );
	}

	public function test_publish_creates_vehicle_and_logs(): void {
		$a   = Fixtures::dealer();
		$res = $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle() ) );
		$this->assertSame( 'applied', $res['status'] );
		$this->assertSame( 200, $res['http_status'] );
		$this->assertTrue( $res['created'] );
		$this->assertSame( 'published', $res['entity']['status'] );
		$this->assertArrayNotHasKey( 'internal_id', $res['entity'], 'internal ids never leave the server' );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ) );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log', "status='applied'" ) );
	}

	public function test_update_replaces_snapshot_and_absent_fields_are_cleared(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1', array( 'mileage_km' => 1000, 'city' => 'Dakar' ), 1 );
		$res = $this->recv( $a, Fixtures::event( 'vehicle.updated', 'v-1', 2, Fixtures::vehicle( array( 'city' => 'Thies', 'mileage_km' => null ) ) ) );
		$this->assertSame( 'applied', $res['status'] );
		$this->assertFalse( $res['created'] );
		$row = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v-1' );
		$this->assertSame( 'Thies', $row['city'] );
		$this->assertNull( $row['mileage_km'] );
		$this->assertSame( '2', $row['version'] );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ), 'upsert, no duplicate row' );
	}

	public function test_idempotent_replay_returns_stored_result_without_reprocessing(): void {
		$a   = Fixtures::dealer();
		$ev  = Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle() );
		$one = $this->recv( $a, $ev );
		$two = $this->recv( $a, $ev );
		$this->assertTrue( $two['duplicate'] );
		$this->assertSame( $one['entity']['id'], $two['entity']['id'] );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ) );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log' ), 'one log row per event id' );
		$this->assertArrayNotHasKey( 'internal_id', $two['entity'] );
	}

	public function test_same_event_id_with_different_payload_conflicts(): void {
		$a   = Fixtures::dealer();
		$id  = Fixtures::event_id();
		$this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle(), $id ) );
		$res = $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 2, Fixtures::vehicle( array( 'price' => 1 ) ), $id ) );
		$this->assertSame( 409, $res['http_status'] );
		$this->assertSame( 'event_id_conflict', $res['code'] );
	}

	public function test_event_ids_are_scoped_per_dealer(): void {
		$a  = Fixtures::dealer( 'A' );
		$b  = Fixtures::dealer( 'B' );
		$id = Fixtures::event_id();
		$ra = $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle(), $id ) );
		$rb = $this->recv( $b, Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle(), $id ) );
		$this->assertSame( 'applied', $ra['status'] );
		$this->assertSame( 'applied', $rb['status'], 'same event_id from another dealer is independent' );
		$this->assertSame( 2, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_stale_versions_are_rejected_and_equal_versions_are_noops(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1', array( 'price' => 100 ), 5 );
		$stale = $this->recv( $a, Fixtures::event( 'vehicle.updated', 'v-1', 4, Fixtures::vehicle( array( 'price' => 1 ) ) ) );
		$this->assertSame( 'stale', $stale['status'] );
		$this->assertSame( 409, $stale['http_status'] );
		$same = $this->recv( $a, Fixtures::event( 'vehicle.updated', 'v-1', 5, Fixtures::vehicle( array( 'price' => 2 ) ) ) );
		$this->assertSame( 'unchanged', $same['status'] );
		$row = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v-1' );
		$this->assertSame( '100', $row['price'], 'older/equal versions never overwrite' );
	}

	public function test_unpublish_sold_and_republish(): void {
		$a  = Fixtures::dealer();
		$id = (int) $a['row']['id'];
		Fixtures::publish( $a, 'v-1', array(), 1 );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'vehicle.unpublished', 'v-1', 2 ) )['status'] );
		$this->assertSame( 'unpublished', Plugin::vehicles()->find_by_source( $id, 'v-1' )['status'] );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 3, Fixtures::vehicle() ) )['status'] );
		$this->assertSame( 'published', Plugin::vehicles()->find_by_source( $id, 'v-1' )['status'] );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'vehicle.sold', 'v-1', 4 ) )['status'] );
		$this->assertSame( 'sold', Plugin::vehicles()->find_by_source( $id, 'v-1' )['status'] );
	}

	public function test_unpublish_of_unknown_entity_is_a_harmless_noop(): void {
		$a   = Fixtures::dealer();
		$res = $this->recv( $a, Fixtures::event( 'vehicle.unpublished', 'ghost', 3 ) );
		$this->assertSame( 'unchanged', $res['status'] );
		$this->assertContains( 'not_found', $res['warnings'] );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_delete_leaves_a_tombstone_that_blocks_older_snapshots(): void {
		$a  = Fixtures::dealer();
		$id = (int) $a['row']['id'];
		Fixtures::publish( $a, 'v-1', array(), 3 );
		$del = $this->recv( $a, Fixtures::event( 'vehicle.deleted', 'v-1', 4 ) );
		$this->assertSame( 'applied', $del['status'] );
		$row = Plugin::vehicles()->find_by_source( $id, 'v-1' );
		$this->assertSame( 'deleted', $row['status'] );
		$this->assertNotNull( $row['deleted_at'] );
		$old = $this->recv( $a, Fixtures::event( 'vehicle.updated', 'v-1', 3, Fixtures::vehicle() ) );
		$this->assertSame( 'stale', $old['status'], 'late out-of-order event cannot resurrect' );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 9, Fixtures::vehicle() ) )['status'], 'higher version can re-publish' );
		$this->assertSame( 'published', Plugin::vehicles()->find_by_source( $id, 'v-1' )['status'] );
	}

	public function test_delete_before_publish_creates_tombstone_that_blocks_late_publish(): void {
		$a = Fixtures::dealer();
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'vehicle.deleted', 'v-9', 7 ) )['status'] );
		$this->assertSame( 'stale', $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-9', 6, Fixtures::vehicle() ) )['status'] );
	}

	public function test_tenant_isolation_same_source_id_different_dealers(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		Fixtures::publish( $a, 'car-1', array( 'price' => 111 ), 1 );
		Fixtures::publish( $b, 'car-1', array( 'price' => 222 ), 1 );
		$this->assertSame( 2, Fixtures::table_count( 'vehicles' ) );
		// A deletes and unpublishes "car-1": B's row must be untouched.
		$this->recv( $a, Fixtures::event( 'vehicle.deleted', 'car-1', 2 ) );
		$rb = Plugin::vehicles()->find_by_source( (int) $b['row']['id'], 'car-1' );
		$this->assertSame( 'published', $rb['status'] );
		$this->assertSame( '222', $rb['price'] );
		// A updates "car-1": B unaffected.
		$this->recv( $a, Fixtures::event( 'vehicle.published', 'car-1', 3, Fixtures::vehicle( array( 'price' => 999 ) ) ) );
		$this->assertSame( '222', Plugin::vehicles()->find_by_source( (int) $b['row']['id'], 'car-1' )['price'] );
		$this->assertSame( '1', Plugin::vehicles()->find_by_source( (int) $b['row']['id'], 'car-1' )['version'] );
	}

	public function test_payload_naming_another_dealer_is_rejected(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		foreach ( array( 'dealer_uuid' => $b['row']['uuid'], 'dealer_id' => $b['row']['source_dealer_uuid'] ) as $field => $value ) {
			$ev = Fixtures::event( 'vehicle.published', 'v-' . $field, 1, Fixtures::vehicle( array( $field => $value ) ) );
			$r  = $this->recv( $a, $ev );
			$this->assertSame( 403, $r['http_status'], $field );
			$this->assertSame( 'tenant_mismatch', $r['code'] );
		}
		$ev            = Fixtures::event( 'vehicle.published', 'v-env', 1, Fixtures::vehicle() );
		$ev['dealer_uuid'] = $b['row']['uuid'];
		$this->assertSame( 403, $this->recv( $a, $ev )['http_status'], 'envelope dealer_uuid' );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
		// Own identity (either form) is accepted.
		$ok = Fixtures::event( 'vehicle.published', 'v-own', 1, Fixtures::vehicle( array( 'dealer_id' => $a['row']['source_dealer_uuid'] ) ) );
		$ok['dealer_uuid'] = $a['row']['uuid'];
		$this->assertSame( 'applied', $this->recv( $a, $ok )['status'] );
	}

	public function test_quality_rules_reject_listings_without_identity(): void {
		$a   = Fixtures::dealer();
		$res = $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 1, array( 'price' => 5 ) ) );
		$this->assertSame( 'rejected', $res['status'] );
		$this->assertSame( 422, $res['http_status'] );
		$this->assertSame( 'quality_rules', $res['code'] );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
		$ok = $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-2', 1, array( 'title' => 'Mystery car' ) ) );
		$this->assertSame( 'applied', $ok['status'], 'title alone is enough' );
	}

	public function test_envelope_validation(): void {
		$a = Fixtures::dealer();
		$cases = array(
			'bad event id'   => array( array( 'event_id' => 'x', 'event_type' => 'vehicle.published', 'data' => array() ), 422, 'invalid_event' ),
			'bad type'       => array( array( 'event_id' => 'evt-aaaaaaaa', 'event_type' => 'vehicle.exploded', 'data' => array() ), 422, 'unsupported_event_type' ),
			'bad api'        => array( array( 'event_id' => 'evt-bbbbbbbb', 'event_type' => 'vehicle.published', 'api_version' => 'v9', 'data' => array() ), 422, 'unsupported_api_version' ),
			'data not object' => array( array( 'event_id' => 'evt-cccccccc', 'event_type' => 'vehicle.published', 'data' => array( 1, 2 ) ), 422, 'invalid_event' ),
			'no source id'   => array( array( 'event_id' => 'evt-dddddddd', 'event_type' => 'vehicle.published', 'data' => array( 'version' => 1, 'make' => 'A', 'model' => 'B' ) ), 422, 'invalid_source_id' ),
			'evil source id' => array( array( 'event_id' => 'evt-eeeeeeee', 'event_type' => 'vehicle.published', 'data' => array( 'source_id' => "x' OR 1=1 --", 'version' => 1, 'make' => 'A', 'model' => 'B' ) ), 422, 'invalid_source_id' ),
			'no version'     => array( array( 'event_id' => 'evt-ffffffff', 'event_type' => 'vehicle.published', 'data' => array( 'source_id' => 's1', 'make' => 'A', 'model' => 'B' ) ), 422, 'invalid_version' ),
			'zero version'   => array( array( 'event_id' => 'evt-gggggggg', 'event_type' => 'vehicle.published', 'data' => array( 'source_id' => 's1', 'version' => 0, 'make' => 'A', 'model' => 'B' ) ), 422, 'invalid_version' ),
			'text version'   => array( array( 'event_id' => 'evt-hhhhhhhh', 'event_type' => 'vehicle.published', 'data' => array( 'source_id' => 's1', 'version' => 'abc', 'make' => 'A', 'model' => 'B' ) ), 422, 'invalid_version' ),
		);
		foreach ( $cases as $name => [ $event, $http, $code ] ) {
			$r = $this->recv( $a, $event );
			$this->assertSame( $http, $r['http_status'], $name );
			$this->assertSame( $code, $r['code'], $name );
		}
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_dealer_cannot_set_managed_trust_fields_or_internal_columns(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1', array( 'wotoyi_inspected' => true, 'featured' => 1, 'views' => 99999, 'status' => 'published', 'slug' => 'hacked', 'uuid' => 'x' ), 1 );
		$row = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v-1' );
		$this->assertSame( '0', $row['wotoyi_inspected'] ?? '0' );
		$this->assertSame( '0', $row['featured'] );
		$this->assertSame( '0', $row['views'] );
		$this->assertTrue( 'hacked' !== $row['slug'] );
	}

	public function test_equipment_is_normalised_into_filterable_table_and_replaced_on_update(): void {
		$a  = Fixtures::dealer();
		$id = (int) $a['row']['id'];
		Fixtures::publish( $a, 'v-1', array( 'equipment' => array( 'comfort' => array( 'air_conditioning', 'cruise_control' ), 'safety' => array( 'abs' ) ) ), 1 );
		$vid = (int) Plugin::vehicles()->find_by_source( $id, 'v-1' )['id'];
		$this->assertSame( 3, Fixtures::table_count( 'vehicle_equipment', "vehicle_id = $vid" ) );
		Fixtures::publish( $a, 'v-1', array( 'equipment' => array( 'safety' => array( 'abs', 'esp' ) ) ), 2, 'vehicle.updated' );
		$this->assertSame( array( 'safety' => array( 'abs', 'esp' ) ), Plugin::vehicles()->equipment_for( $vid ) );
	}

	public function test_derived_columns(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1', array( 'gallery' => array( 'https://a.test/1.jpg', 'https://a.test/2.jpg' ), 'cover_image' => 'https://a.test/0.jpg', 'video_url' => 'https://a.test/v.mp4' ), 1 );
		$row = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v-1' );
		$this->assertSame( '3', $row['photo_count'] );
		$this->assertSame( '1', $row['has_video'] );
		$this->assertSame( '0', $row['has_360'] );
		$this->assertStringContains( 'toyota rav4', $row['search_text'] );
		$this->assertStringContains( 'toyota-rav4-2021-', $row['slug'] );
	}

	public function test_plan_limit_hook_can_block_publication(): void {
		$a = Fixtures::dealer();
		add_filter( 'wotoyi_mp_can_publish', static fn( $ok ) => new WP_Error( 'plan', 'Plan limit reached' ), 10, 4 );
		$res = $this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle() ) );
		$this->assertSame( 403, $res['http_status'] );
		$this->assertSame( 'publish_not_allowed', $res['code'] );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_batch_processes_events_independently(): void {
		$a = Fixtures::dealer();
		$events = array(
			Fixtures::event( 'vehicle.published', 'b-1', 1, Fixtures::vehicle() ),
			Fixtures::event( 'vehicle.published', 'b-2', 1, array( 'price' => 1 ) ), // rejected
			Fixtures::event( 'service.published', 's-1', 1, array( 'title' => 'Vidange', 'category' => 'maintenance' ) ),
			'not-an-object',
		);
		$res = Plugin::receiver()->handle_batch( $a['row'], $events );
		$this->assertSame( array( 'applied' => 2, 'rejected' => 2 ), $res['summary'] );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ) );
		$this->assertSame( 1, Fixtures::table_count( 'services' ) );
	}

	public function test_batch_is_capped(): void {
		$a = Fixtures::dealer();
		$events = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$events[] = Fixtures::event( 'vehicle.published', "c-$i", 1, Fixtures::vehicle() );
		}
		$res = Plugin::receiver()->handle_batch( $a['row'], $events );
		$this->assertCount( 50, $res['results'] );
		$this->assertSame( 10, $res['summary']['skipped_over_limit'] );
	}

	public function test_services_lifecycle(): void {
		$a  = Fixtures::dealer();
		$id = (int) $a['row']['id'];
		$s  = array( 'title' => 'Vidange complète', 'category' => 'maintenance', 'price_from' => 25000, 'currency' => 'XOF', 'city' => 'Dakar', 'mobile_service' => true );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'service.published', 's-1', 1, $s ) )['status'] );
		$row = Plugin::services()->find_by_source( $id, 's-1' );
		$this->assertSame( 'published', $row['status'] );
		$this->assertSame( '1', $row['mobile_service'] );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'service.unpublished', 's-1', 2 ) )['status'] );
		$this->assertSame( 'unpublished', Plugin::services()->find_by_source( $id, 's-1' )['status'] );
		$this->assertSame( 'applied', $this->recv( $a, Fixtures::event( 'service.deleted', 's-1', 3 ) )['status'] );
		$this->assertSame( 'deleted', Plugin::services()->find_by_source( $id, 's-1' )['status'] );
		$this->assertSame( 'rejected', $this->recv( $a, Fixtures::event( 'service.published', 's-2', 1, array( 'category' => 'repair' ) ) )['status'], 'service needs a title' );
	}

	public function test_services_tenant_isolation(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		$s = array( 'title' => 'Detailing', 'category' => 'detailing' );
		$this->recv( $a, Fixtures::event( 'service.published', 'same', 1, $s ) );
		$this->recv( $b, Fixtures::event( 'service.published', 'same', 1, $s ) );
		$this->recv( $a, Fixtures::event( 'service.deleted', 'same', 2 ) );
		$this->assertSame( 'published', Plugin::services()->find_by_source( (int) $b['row']['id'], 'same' )['status'] );
	}

	public function test_sync_log_never_contains_secrets_or_raw_payload(): void {
		$a = Fixtures::dealer();
		$this->recv( $a, Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle( array( 'vin' => 'SECRETVIN1234567', 'description' => 'private text xyz' ) ) ) );
		global $wpdb;
		$dump = json_encode( $wpdb->get_results( 'SELECT * FROM wp_wotoyi_sync_log' ) );
		foreach ( array( $a['secret'], 'SECRETVIN1234567', 'private text xyz' ) as $needle ) {
			$this->assertStringNotContains( $needle, $dump );
		}
	}

	public function test_failed_processing_is_logged_and_retryable_with_same_event_id(): void {
		$a  = Fixtures::dealer();
		$ev = Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle() );
		global $wpdb;
		// Simulate a crash after claiming: row stuck in "received".
		Plugin::sync_log()->claim( (int) $a['row']['id'], $ev['event_id'], hash( 'sha256', \Wotoyi\Marketplace\Support\Json::canonical( $ev ) ), 'vehicle.published', 'http', '' );
		$res = $this->recv( $a, $ev );
		$this->assertSame( 'applied', $res['status'], 'received/failed claims are re-processed' );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log' ) );
	}

	public function test_lead_pull_and_ack_are_tenant_scoped(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		Fixtures::publish( $a, 'va', array(), 1 );
		Fixtures::publish( $b, 'vb', array(), 1 );
		$va = Plugin::search()->published_vehicle_ref( Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'va' )['uuid'] );
		$vb = Plugin::search()->published_vehicle_ref( Plugin::vehicles()->find_by_source( (int) $b['row']['id'], 'vb' )['uuid'] );
		$la = Plugin::leads()->create( array( 'dealer_id' => $va['dealer_id'], 'vehicle_id' => $va['id'], 'phone' => '+221770000000', 'consent' => true ) );
		$lb = Plugin::leads()->create( array( 'dealer_id' => $vb['dealer_id'], 'vehicle_id' => $vb['id'], 'phone' => '+221771111111', 'consent' => true ) );
		$pulled = Plugin::receiver()->pull_leads( $a['row'], 50 );
		$this->assertCount( 1, $pulled );
		$this->assertSame( $la['uuid'], $pulled[0]['uuid'] );
		$this->assertSame( 'va', $pulled[0]['vehicle_source_id'] );
		// A tries to acknowledge B's lead: nothing happens.
		$this->assertSame( 0, Plugin::receiver()->ack_leads( $a['row'], array( $lb['uuid'] ) ) );
		$this->assertCount( 1, Plugin::receiver()->pull_leads( $b['row'], 50 ) );
		$this->assertSame( 1, Plugin::receiver()->ack_leads( $a['row'], array( $la['uuid'], 'not-a-uuid' ) ) );
		$this->assertCount( 0, Plugin::receiver()->pull_leads( $a['row'], 50 ) );
		$this->assertSame( 0, Plugin::receiver()->ack_leads( $a['row'], array( $la['uuid'] ) ), 'ack is idempotent' );
	}
}
