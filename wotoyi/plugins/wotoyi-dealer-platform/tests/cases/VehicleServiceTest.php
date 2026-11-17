<?php
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\ApiException;

final class VehicleServiceTest extends Wotoyi_Test_Case {

	private array $t;

	protected function setUp(): void {
		parent::setUp();
		$this->t = Fixtures::tenant( 'Dealer A', 11 );
	}

	/** @return list<array<string,mixed>> outbox events (decoded) for the tenant, oldest first. */
	private function events( ?string $entity_uuid = null ): array {
		return Fixtures::in( $this->t, function () use ( $entity_uuid ) {
			$where = $entity_uuid ? array( 'entity_uuid' => $entity_uuid ) : array();
			return array_map( fn( $r ) => array( 'row' => $r, 'env' => json_decode( $r['payload'], true ) ), TenantDb::select( 'outbox', $where, array( 'order' => array( array( 'id', 'asc' ) ) ) ) );
		} );
	}

	private function types( ?string $uuid = null ): array {
		return array_map( fn( $e ) => $e['row']['event_type'] . ':' . $e['row']['status'], $this->events( $uuid ) );
	}

	public function test_create_draft_has_no_outbox_event_and_hides_nothing_for_owner(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle() ) );
		$this->assertSame( 'draft', $v['status'] );
		$this->assertSame( 1, $v['version'] );
		$this->assertSame( 'Toyota RAV4 2021', $v['title'] );
		$this->assertSame( 12000000, $v['purchase_cost'] );
		$this->assertCount( 0, $this->events() );
	}

	public function test_publish_update_unpublish_sold_delete_produce_the_right_events_and_versions(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		$id = $v['id'];
		$this->assertSame( array( 'vehicle.published:pending' ), $this->types( $id ) );
		$env = $this->events( $id )[0]['env'];
		$this->assertSame( 'v1', $env['api_version'] );
		$this->assertSame( $this->t['uuid'], $env['dealer_uuid'] );
		$this->assertSame( $id, $env['data']['source_id'] );
		$this->assertSame( 1, $env['data']['version'] );
		$this->assertSame( 'published', $env['data']['status'] );

		$u = Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $id, array( 'price' => 14500000 ) ) );
		$this->assertSame( 2, $u['version'] );
		// The earlier, never-delivered snapshot is superseded by the newer one.
		$this->assertSame( array( 'vehicle.published:superseded', 'vehicle.updated:pending' ), $this->types( $id ) );
		$this->assertSame( 14500000, $this->events( $id )[1]['env']['data']['price'] );

		Fixtures::in( $this->t, fn() => Plugin::vehicles()->transition( $id, 'unpublished' ) );
		$last = array_slice( $this->events( $id ), -1 )[0];
		$this->assertSame( 'vehicle.unpublished', $last['row']['event_type'] );
		$this->assertSame( 3, $last['env']['data']['version'] );
		$this->assertSame( array( 'source_id', 'version' ), array_keys( $last['env']['data'] ) );

		Fixtures::in( $this->t, fn() => Plugin::vehicles()->transition( $id, 'published' ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->transition( $id, 'sold', array( 'sold_price' => 14000000 ) ) );
		$last = array_slice( $this->events( $id ), -1 )[0];
		$this->assertSame( 'vehicle.sold', $last['row']['event_type'] );
		$this->assertSame( 5, $last['env']['data']['version'] );

		Fixtures::in( $this->t, fn() => Plugin::vehicles()->archive( $id ) );
		$last = array_slice( $this->events( $id ), -1 )[0];
		$this->assertSame( 'vehicle.deleted', $last['row']['event_type'] );
		$this->assertSame( 6, $last['env']['data']['version'] );
		$this->assertThrows( fn() => Fixtures::in( $this->t, fn() => Plugin::vehicles()->get( $id ) ), 'not found' );
	}

	protected function assertNotSame( $a, $b, string $m = '' ): void {
		++$this->assertions;
		if ( $a === $b ) {
			$this->fail( $m ?: 'values should differ' );
		}
	}

	public function test_event_ids_are_deterministic_unique_and_valid_for_the_receiver(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 1 ) ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 2 ) ) );
		$ids = array_map( fn( $e ) => $e['row']['event_id'], $this->events() );
		$this->assertCount( 3, array_unique( $ids ) );
		foreach ( $ids as $id ) {
			$this->assertTrue( 1 === preg_match( '/^[A-Za-z0-9_.:\-]{8,64}$/', $id ), $id );
		}
		$this->assertSame( $ids[2], Fixtures::in( $this->t, fn() => \Wotoyi\DealerPlatform\Sync\Outbox::event_id( 'vehicle', $v['id'], 3, 'vehicle.updated' ) ), 'same change => same event id (idempotent)' );
		$this->assertNotSame( $ids[2], Fixtures::in( $this->t, fn() => \Wotoyi\DealerPlatform\Sync\Outbox::event_id( 'vehicle', $v['id'], 4, 'vehicle.updated' ) ) );
	}

	public function test_dealer_only_and_private_fields_never_enter_the_payload(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published', 'supplier' => 'Acme Imports', 'stock_ref' => 'S-1', 'plate' => 'DK-1234-AB' ) ) ) );
		$json = $this->events( $v['id'] )[0]['row']['payload'];
		foreach ( array( 'purchase_cost', 'internal_notes', 'secret note', 'supplier', 'Acme', 'stock_ref', 'vin', 'JTMBFREV', 'plate', 'DK-1234' ) as $leak ) {
			$this->assertStringNotContains( $leak, $json, "{$leak} must not be synced" );
		}
		$data = $this->events( $v['id'] )[0]['env']['data'];
		$this->assertSame( 'XOF', $data['currency'] ); // Defaulted from the tenant.
		$this->assertSame( 'SN', $data['country'] );
	}

	public function test_dealer_only_edit_bumps_version_but_sends_nothing(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'internal_notes' => 'checked tyres', 'purchase_cost' => 11000000 ) ) );
		$this->assertSame( array( 'vehicle.published:pending' ), $this->types() );
		$this->assertSame( 2, Fixtures::in( $this->t, fn() => Plugin::vehicles()->get( $v['id'] ) )['version'] );
	}

	public function test_publish_rules_and_status_machine(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( array( 'make' => 'Kia', 'model' => 'Rio' ) ) );
		try {
			Fixtures::in( $this->t, fn() => Plugin::vehicles()->transition( $v['id'], 'published' ) );
			$this->fail( 'must refuse to publish without a price' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'wotoyi_not_publishable', $e->error_code() );
			$this->assertContains( 'price', $e->data()['missing'] );
		}
		$this->assertCount( 0, $this->events() );
		foreach ( array( 'sold', 'unpublished', 'bogus' ) as $bad ) {
			$this->assertThrows( fn() => Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'status' => $bad ) ) ) );
		}
		$this->assertThrows( fn() => Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( array( 'make' => 'Kia', 'model' => 'Rio', 'status' => 'sold' ) ) ), 'draft or published' );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 5000000, 'status' => 'published' ) ) );
		$this->assertSame( array( 'vehicle.published:pending' ), $this->types() );
	}

	public function test_deleting_a_never_published_vehicle_sends_no_tombstone(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle() ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->archive( $v['id'] ) );
		$this->assertCount( 0, $this->events() );
	}

	public function test_update_with_fields_and_status_in_one_call_makes_one_event(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'status' => 'sold', 'price' => 13000000 ) ) );
		$types = $this->types( $v['id'] );
		$this->assertSame( array( 'vehicle.published:superseded', 'vehicle.updated:pending' ), $types, 'a status change with field changes is one snapshot carrying the new status' );
		$env = $this->events( $v['id'] )[1]['env'];
		$this->assertSame( 'sold', $env['data']['status'] );
		$this->assertSame( 13000000, $env['data']['price'] );
	}

	public function test_optimistic_concurrency(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle() ) );
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 1000, 'version' => 1 ) ) );
		try {
			Fixtures::in( $this->t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 2000, 'version' => 1 ) ) );
			$this->fail( 'stale version must conflict' );
		} catch ( ApiException $e ) {
			$this->assertSame( 409, $e->http_status() );
			$this->assertSame( 2, $e->data()['current_version'] );
		}
		$this->assertSame( 1000, Fixtures::in( $this->t, fn() => Plugin::vehicles()->get( $v['id'] ) )['price'] );
	}

	public function test_invalid_values_are_rejected_and_unknown_fields_warn(): void {
		try {
			Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( array( 'make' => 'Kia', 'model' => 'Rio', 'year' => 1700, 'fuel_type' => 'steam', 'cover_image' => 'javascript:alert(1)' ) ) );
			$this->fail( 'invalid values' );
		} catch ( ApiException $e ) {
			$this->assertSame( 422, $e->http_status() );
			$this->assertEquals( array( 'year', 'fuel_type', 'cover_image' ), $e->data()['fields'] );
		}
		$svc = Plugin::vehicles();
		Fixtures::in( $this->t, fn() => $svc->create( array( 'make' => 'Kia', 'model' => 'Rio', 'bogus' => 1, 'wotoyi_inspected' => true, 'tenant_id' => 99, 'uuid' => 'x', 'deleted_at' => 'x' ) ) );
		$this->assertContains( 'unknown_field:wotoyi_inspected', $svc->warnings() );
		$this->assertContains( 'unknown_field:bogus', $svc->warnings() );
		$row = Fixtures::raw( 'SELECT tenant_id, uuid, deleted_at FROM wp_wotoyi_dp_vehicles' )[0];
		$this->assertSame( (string) $this->t['id'], $row['tenant_id'] );
		$this->assertNull( $row['deleted_at'] );
		$this->assertTrue( 36 === strlen( $row['uuid'] ) );
	}

	public function test_html_is_stripped_from_descriptions_and_notes(): void {
		$v = Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'description' => '<p onclick="x()">Belle <script>alert(1)</script><strong>voiture</strong></p>', 'internal_notes' => '<b>x</b>' ) ) ) );
		$this->assertStringNotContains( 'script', (string) $v['description'] );
		$this->assertStringNotContains( 'onclick', (string) $v['description'] );
		$this->assertStringContains( 'voiture', (string) $v['description'] );
		$this->assertSame( 'x', $v['internal_notes'] );
	}

	public function test_services_publish_and_sync(): void {
		$s = Fixtures::in( $this->t, fn() => Plugin::services()->create( array( 'title' => 'Vidange complète', 'category' => 'maintenance', 'price_from' => 25000, 'status' => 'published' ) ) );
		$this->assertSame( array( 'service.published:pending' ), $this->types( $s['id'] ) );
		Fixtures::in( $this->t, fn() => Plugin::services()->update( $s['id'], array( 'price_from' => 30000 ) ) );
		Fixtures::in( $this->t, fn() => Plugin::services()->transition( $s['id'], 'unpublished' ) );
		Fixtures::in( $this->t, fn() => Plugin::services()->archive( $s['id'] ) );
		$this->assertSame( array( 'service.published:superseded', 'service.updated:superseded', 'service.unpublished:superseded', 'service.deleted:pending' ), $this->types( $s['id'] ) );
		$bad = Fixtures::in( $this->t, fn() => Plugin::services()->create( array( 'title' => 'x' ) ) );
		$this->assertThrows( fn() => Fixtures::in( $this->t, fn() => Plugin::services()->transition( $bad['id'], 'published' ) ) );
		$this->assertThrows( fn() => Fixtures::in( $this->t, fn() => Plugin::services()->update( $bad['id'], array( 'status' => 'sold' ) ) ) );
	}

	public function test_a_failure_inside_the_transaction_leaves_no_row_and_no_event(): void {
		global $wpdb;
		Fixtures::in( $this->t, function () {
			$this->assertThrows(
				fn() => TenantDb::transaction( function () {
					Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) );
					throw new RuntimeException( 'later step failed' );
				} ),
				'later step failed'
			);
		} );
		$this->assertSame( 0, Fixtures::count( 'vehicles' ) );
		$this->assertSame( 0, Fixtures::count( 'outbox' ) );
		$this->assertSame( 0, Fixtures::count( 'audit_log', "action = 'vehicles.create'" ) );
	}

	public function test_listing_filters_search_pagination(): void {
		Fixtures::in( $this->t, function () {
			Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Toyota', 'model' => 'Corolla', 'price' => 9000000, 'year' => 2018 ) ) );
			Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Kia', 'model' => 'Rio', 'price' => 6000000, 'year' => 2020, 'status' => 'published' ) ) );
			Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Kia', 'model' => 'Sportage', 'price' => 18000000, 'year' => 2023 ) ) );
			$this->assertSame( 3, Plugin::vehicles()->list()['total'] );
			$this->assertSame( 2, Plugin::vehicles()->list( array( 'make' => 'Kia' ) )['total'] );
			$this->assertSame( 1, Plugin::vehicles()->list( array( 'status' => 'published' ) )['total'] );
			$this->assertSame( 2, Plugin::vehicles()->list( array( 'price_min' => '8000000', 'price_max' => '20000000' ) )['total'] );
			$this->assertSame( 1, Plugin::vehicles()->list( array( 'q' => 'sport' ) )['total'] );
			$this->assertSame( 2, Plugin::vehicles()->list( array( 'year_min' => '2020' ) )['total'] );
			$p = Plugin::vehicles()->list( array( 'per_page' => '2', 'page' => '2', 'orderby' => 'price', 'order' => 'asc' ) );
			$this->assertSame( 1, count( $p['items'] ) );
			$this->assertSame( 18000000, $p['items'][0]['price'] );
			$this->assertSame( 2, $p['pages'] );
			// Injection attempts are inert.
			$this->assertSame( 0, Plugin::vehicles()->list( array( 'q' => "' OR 1=1 --" ) )['total'] );
			$this->assertSame( 3, Plugin::vehicles()->list( array( 'orderby' => 'price; DROP TABLE x' ) )['total'] );
			$this->assertSame( 3, Plugin::vehicles()->list( array( 'make' => array( 'x' ) ) )['total'] );
		} );
	}
}
