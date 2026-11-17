<?php
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\ApiException;

final class CrmTest extends Wotoyi_Test_Case {

	private array $t;

	protected function setUp(): void {
		parent::setUp();
		$this->t = Fixtures::tenant( 'Dealer A', 11 );
	}

	private function in( callable $fn, ?string $role = 'owner' ) {
		return Fixtures::in( $this->t, $fn, $role );
	}

	private function code( callable $fn ): string {
		try {
			$fn();
		} catch ( ApiException $e ) {
			return $e->error_code();
		}
		return 'no_error';
	}

	public function test_customers_crud_validation_and_duplicates(): void {
		$c = $this->in( fn() => Plugin::customers()->create( array( 'name' => 'Awa Diop', 'phone' => '+221 77 000 00 01', 'email' => 'AWA@Example.com', 'tags' => array( 'vip', 'diesel' ) ) ) );
		$this->assertSame( 'awa@example.com', $c['email'] );
		$this->assertSame( array( 'vip', 'diesel' ), $c['tags'] );
		$this->assertSame( 'SN', $c['country'] );
		$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => $this->in( fn() => Plugin::customers()->create( array( 'name' => 'No contact' ) ) ) ) );
		$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => $this->in( fn() => Plugin::customers()->create( array( 'phone' => '+221770000009' ) ) ) ), 'name required' );
		$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => $this->in( fn() => Plugin::customers()->create( array( 'name' => 'x', 'email' => 'not-an-email' ) ) ) ) );
		$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => $this->in( fn() => Plugin::customers()->update( $c['id'], array( 'name' => null ) ) ) ) );
		$m = $this->in( fn() => Plugin::customers()->find_match( '+221 77 000 00 01', null ) );
		$this->assertNotNull( $m );
		$this->in( fn() => Plugin::customers()->archive( $c['id'] ) );
		$this->assertSame( 'wotoyi_not_found', $this->code( fn() => $this->in( fn() => Plugin::customers()->get( $c['id'] ) ) ) );
		$this->assertSame( 0, $this->in( fn() => Plugin::customers()->list()['total'] ) );
		$this->assertSame( 1, Fixtures::count( 'customers' ), 'soft delete keeps the row' );
	}

	public function test_leads_pipeline_stage_changes_and_convert(): void {
		$this->in( function () {
			$l = Plugin::leads()->create( array( 'name' => 'Moussa', 'phone' => '+221770000010', 'source' => 'walk_in', 'value' => 9000000 ) );
			$this->assertSame( 'new', $l['stage'] );
			$this->assertSame( 'XOF', $l['currency'] );
			$l2 = Plugin::leads()->change_stage( $l['id'], 'qualified' );
			$this->assertSame( 'qualified', $l2['stage'] );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::leads()->change_stage( $l['id'], 'bogus' ) ) );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::leads()->update( $l['id'], array( 'stage' => null ) ) ) );
			Plugin::leads()->create( array( 'name' => 'Fatou', 'email' => 'f@example.com' ) );
			Plugin::leads()->change_stage( Plugin::leads()->create( array( 'name' => 'Lost', 'phone' => '+221770000011' ) )['id'], 'lost', 'Too expensive' );
			$p = Plugin::leads()->pipeline();
			$this->assertSame( 3, $p['total'] );
			$this->assertSame( 1, $p['stages']['qualified']['count'] );
			$this->assertSame( 1, $p['stages']['new']['count'] );
			$this->assertSame( 1, $p['stages']['lost']['count'] );
			$this->assertSame( 'Too expensive', $p['stages']['lost']['items'][0]['lost_reason'] );
			$tl = Plugin::activities()->list( array( 'entity_type' => 'lead', 'entity_uuid' => $l['id'] ) );
			$this->assertSame( 'stage_change', $tl['items'][0]['type'] );
			// Convert creates a customer once, then links.
			$conv = Plugin::leads()->convert( $l['id'] );
			$this->assertNotNull( $conv['customer_uuid'] );
			$this->assertSame( $conv['customer_uuid'], Plugin::leads()->convert( $l['id'] )['customer_uuid'] );
			$this->assertSame( 1, Plugin::customers()->list()['total'] );
			// A second lead with the same phone links to the same customer.
			$again = Plugin::leads()->create( array( 'name' => 'Moussa again', 'phone' => '+221770000010' ) );
			$this->assertSame( $conv['customer_uuid'], Plugin::leads()->convert( $again['id'] )['customer_uuid'] );
			$this->assertSame( 1, Plugin::customers()->list()['total'] );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::leads()->create( array( 'message' => 'only a message' ) ) ) );
		} );
	}

	public function test_sale_completes_vehicle_lead_and_emits_sold_event_once(): void {
		$this->in( function () {
			$v    = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) );
			$cust = Plugin::customers()->create( array( 'name' => 'Buyer', 'phone' => '+221770000020' ) );
			$lead = Plugin::leads()->create( array( 'name' => 'Buyer', 'phone' => '+221770000020', 'vehicle_uuid' => $v['id'] ) );
			$deal = Plugin::deals()->sell_vehicle( $v['id'], array( 'amount' => 14000000, 'customer_uuid' => $cust['id'], 'lead_uuid' => $lead['id'], 'payment_method' => 'transfer' ) );
			$this->assertSame( 'completed', $deal['status'] );
			$this->assertNotNull( $deal['sold_at'] );
			$veh = Plugin::vehicles()->get( $v['id'] );
			$this->assertSame( 'sold', $veh['status'] );
			$this->assertSame( 14000000, $veh['sold_price'] );
			$this->assertSame( 'won', Plugin::leads()->get( $lead['id'] )['stage'] );
			$types = array_map( fn( $r ) => $r['event_type'] . ':' . $r['status'], TenantDb::select( 'outbox', array( 'entity_uuid' => $v['id'] ), array( 'order' => array( array( 'id', 'asc' ) ) ) ) );
			$this->assertSame( array( 'vehicle.published:superseded', 'vehicle.sold:pending' ), $types );
			// Second sale of the same vehicle is refused; completed deals are final.
			$this->assertSame( 'wotoyi_vehicle_not_available', $this->code( fn() => Plugin::deals()->sell_vehicle( $v['id'], array( 'amount' => 1 ) ) ) );
			$this->assertSame( 'wotoyi_deal_final', $this->code( fn() => Plugin::deals()->update( $deal['id'], array( 'amount' => 1 ) ) ) );
			$this->assertSame( 'wotoyi_deal_final', $this->code( fn() => Plugin::deals()->update( $deal['id'], array( 'status' => 'cancelled' ) ) ) );
		} );
	}

	public function test_deal_state_machine_and_guards(): void {
		$this->in( function () {
			$v1 = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) );
			$v2 = Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Kia', 'model' => 'Rio' ) ) ); // draft
			$d  = Plugin::deals()->create( array( 'vehicle_uuid' => $v1['id'], 'amount' => 100 ) );
			$this->assertSame( 'draft', $d['status'] );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::deals()->update( $d['id'], array( 'discount' => 500 ) ) ) );
			$c = Plugin::deals()->update( $d['id'], array( 'status' => 'confirmed' ) );
			$this->assertSame( 'confirmed', $c['status'] );
			$this->assertSame( 'wotoyi_vehicle_in_use', $this->code( fn() => Plugin::vehicles()->archive( $v1['id'] ) ) );
			$this->assertSame( 'wotoyi_vehicle_already_sold', $this->code( fn() => Plugin::deals()->create( array( 'vehicle_uuid' => $v1['id'], 'amount' => 1, 'status' => 'confirmed' ) ) ) );
			$this->assertSame( 'wotoyi_vehicle_not_available', $this->code( fn() => Plugin::deals()->create( array( 'vehicle_uuid' => $v2['id'], 'amount' => 1, 'status' => 'completed' ) ) ), 'draft vehicle cannot be sold' );
			$x = Plugin::deals()->update( $d['id'], array( 'status' => 'cancelled' ) );
			$this->assertSame( 'cancelled', $x['status'] );
			$this->assertSame( 'wotoyi_invalid_transition', $this->code( fn() => Plugin::deals()->update( $d['id'], array( 'status' => 'completed' ) ) ) );
			Plugin::vehicles()->archive( $v1['id'] ); // No active deal any more.
		} );
	}

	public function test_rentals_overlap_totals_and_calendar(): void {
		$this->in( function () {
			$v = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published', 'offer_type' => 'both', 'rental_price_day' => 40000 ) ) );
			$c = Plugin::customers()->create( array( 'name' => 'Renter', 'phone' => '+221770000030' ) );
			$r = Plugin::rentals()->create( array( 'vehicle_uuid' => $v['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-02-01', 'end_date' => '2027-02-04', 'daily_rate' => 40000 ) );
			$this->assertSame( 120000, $r['total'], '3 nights x 40000' );
			$this->assertSame( 'reserved', $r['status'] );
			$this->assertSame( 'wotoyi_rental_overlap', $this->code( fn() => Plugin::rentals()->create( array( 'vehicle_uuid' => $v['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-02-03', 'end_date' => '2027-02-06' ) ) ) );
			$this->assertSame( 'wotoyi_rental_overlap', $this->code( fn() => Plugin::rentals()->create( array( 'vehicle_uuid' => $v['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-01-30', 'end_date' => '2027-02-01' ) ) ), 'touching dates count as overlap (same-day handover is not supported)' );
			$ok = Plugin::rentals()->create( array( 'vehicle_uuid' => $v['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-02-05', 'end_date' => '2027-02-07', 'daily_rate' => 40000 ) );
			$this->assertSame( 80000, $ok['total'] );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::rentals()->create( array( 'vehicle_uuid' => $v['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-03-05', 'end_date' => '2027-03-01' ) ) ) );
			// Editing a rental does not clash with itself; moving into another booking does.
			$moved = Plugin::rentals()->update( $r['id'], array( 'end_date' => '2027-02-03' ) );
			$this->assertSame( 80000, $moved['total'], 'total follows the dates' );
			$this->assertSame( 'wotoyi_rental_overlap', $this->code( fn() => Plugin::rentals()->update( $r['id'], array( 'end_date' => '2027-02-06' ) ) ) );
			// Lifecycle.
			Plugin::rentals()->update( $r['id'], array( 'status' => 'active' ) );
			$this->assertSame( 'wotoyi_vehicle_in_use', $this->code( fn() => Plugin::vehicles()->archive( $v['id'] ) ) );
			$this->assertSame( 'wotoyi_invalid_transition', $this->code( fn() => Plugin::rentals()->update( $r['id'], array( 'status' => 'reserved' ) ) ) );
			Plugin::rentals()->update( $r['id'], array( 'status' => 'returned' ) );
			// Cancelled bookings free the dates.
			Plugin::rentals()->update( $ok['id'], array( 'status' => 'cancelled' ) );
			Plugin::rentals()->create( array( 'vehicle_uuid' => $v['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-02-05', 'end_date' => '2027-02-06' ) );
			$cal = Plugin::rentals()->calendar( '2027-02-01', '2027-02-28' );
			$this->assertCount( 2, $cal['items'], 'returned + reserved shown, cancelled hidden' );
			$this->assertArrayNotHasKey( 'name', $cal['items'][0] );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::rentals()->calendar( '2027-01-01', '2029-01-01' ) ) );
		} );
	}

	public function test_tasks_activities_and_append_only_timeline(): void {
		$this->in( function () {
			$c = Plugin::customers()->create( array( 'name' => 'T', 'phone' => '+221770000040' ) );
			$t = Plugin::tasks()->create( array( 'title' => 'Call back', 'due_at' => '2027-01-14T10:00:00Z', 'related_type' => 'customer', 'related_uuid' => $c['id'] ) );
			$this->assertSame( 'open', $t['status'] );
			$this->assertSame( '2027-01-14 10:00:00', $t['due_at'] );
			$d = Plugin::tasks()->complete( $t['id'] );
			$this->assertSame( 'done', $d['status'] );
			$this->assertNotNull( $d['completed_at'] );
			$this->assertSame( 'wotoyi_invalid_ref', $this->code( fn() => Plugin::tasks()->create( array( 'title' => 'x', 'related_type' => 'customer', 'related_uuid' => '11111111-1111-1111-1111-111111111111' ) ) ) );
			$a = Plugin::activities()->create( array( 'entity_type' => 'customer', 'entity_uuid' => $c['id'], 'type' => 'call', 'body' => 'Called, will come Monday' ) );
			$this->assertSame( 'call', $a['type'] );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::activities()->create( array( 'entity_type' => 'customer', 'entity_uuid' => $c['id'], 'type' => 'sync', 'body' => 'forged' ) ) ), 'humans cannot forge system entries' );
			$this->assertSame( 'wotoyi_method_not_allowed', $this->code( fn() => Plugin::activities()->update( $a['id'], array( 'body' => 'edit' ) ) ) );
			$this->assertSame( 'wotoyi_method_not_allowed', $this->code( fn() => Plugin::activities()->archive( $a['id'] ) ) );
		} );
	}

	public function test_erasure_anonymises_customer_leads_and_notes_but_keeps_statistics(): void {
		$cid = $this->in( function () {
			$c = Plugin::customers()->create( array( 'name' => 'Erase Me', 'phone' => '+221770000050', 'email' => 'erase@example.com', 'notes' => 'private' ) );
			$l = Plugin::leads()->create( array( 'name' => 'Erase Me', 'phone' => '+221770000050', 'email' => 'erase@example.com', 'message' => 'I want a car', 'customer_uuid' => $c['id'] ) );
			Plugin::activities()->create( array( 'entity_type' => 'customer', 'entity_uuid' => $c['id'], 'body' => 'private chat' ) );
			return $c['id'];
		} );
		$counts = $this->in( fn() => Plugin::customers()->erase( $cid ) );
		$this->assertSame( 1, $counts['leads'] );
		foreach ( array( 'erase@example.com', '770000050', 'private', 'I want a car', 'Erase Me' ) as $pii ) {
			$all = json_encode( array( Fixtures::raw( 'SELECT * FROM wp_wotoyi_dp_customers' ), Fixtures::raw( 'SELECT * FROM wp_wotoyi_dp_leads' ), Fixtures::raw( 'SELECT body FROM wp_wotoyi_dp_activities' ) ) );
			$this->assertStringNotContains( $pii, $all, "{$pii} must be gone" );
		}
		$this->assertSame( 1, Fixtures::count( 'leads' ), 'lead row kept for statistics' );
	}

	public function test_members_guard_last_owner_and_roles(): void {
		$this->in( function () {
			$list = Plugin::members()->list()['items'];
			$this->assertCount( 1, $list );
			$owner = $list[0];
			$this->assertSame( 'wotoyi_last_owner', $this->code( fn() => Plugin::members()->update( $owner['id'], array( 'role' => 'sales' ) ) ) );
			$this->assertSame( 'wotoyi_last_owner', $this->code( fn() => Plugin::members()->remove( $owner['id'] ) ) );
			Fixtures::user( 55, 'sales55@example.com' );
			$m = Plugin::members()->add_direct( 55, 'sales' ); // Platform-operator path (H1: dealer owners can only invite).
			$this->assertSame( 55, $m['user_id'] );
			$this->assertSame( 'wotoyi_member_exists', $this->code( fn() => Plugin::members()->add_direct( 55, 'manager' ) ) );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::members()->add_direct( 55, 'god' ) ) );
			$this->assertSame( 'wotoyi_invalid_request', $this->code( fn() => Plugin::members()->add_direct( 9999, 'sales' ) ) );
			// A second owner makes the first demotable.
			Plugin::members()->update( $m['id'], array( 'role' => 'owner' ) );
			$this->assertSame( 'sales', Plugin::members()->update( $owner['id'], array( 'role' => 'sales' ) )['role'] );
		} );
	}

	public function test_audit_log_records_actions_without_values_or_secrets(): void {
		$this->in( function () {
			Plugin::customers()->create( array( 'name' => 'Audit Subject', 'phone' => '+221770000060' ) );
			$items = \Wotoyi\DealerPlatform\Service\AuditLog::list( array( 'action' => 'customers.create' ) )['items'];
			$this->assertCount( 1, $items );
			$this->assertStringNotContains( 'Audit Subject', json_encode( $items ) );
			$this->assertStringNotContains( '770000060', json_encode( $items ) );
			$this->assertEquals( array( 'name', 'phone' ), $items[0]['meta']['fields'] );
			$s = \Wotoyi\DealerPlatform\Service\AuditLog::scrub( array( 'secret' => 'abc', 'nested' => array( 'api_key' => 'k', 'ok' => 1 ), 'pairing_code' => 'WTY', 'signature' => 'x', 'key_id' => 'wk_1' ) );
			$this->assertSame( '[redacted]', $s['secret'] );
			$this->assertSame( '[redacted]', $s['nested']['api_key'] );
			$this->assertSame( 1, $s['nested']['ok'] );
			$this->assertSame( '[redacted]', $s['pairing_code'] );
			$this->assertSame( '[redacted]', $s['signature'] );
		} );
	}

	public function test_finance_fields_hidden_without_finance_capability(): void {
		$id = $this->in( fn() => Plugin::vehicles()->create( Fixtures::vehicle() )['id'] );
		$sales = $this->in( fn() => Plugin::vehicles()->get( $id ), 'sales' );
		$this->assertArrayNotHasKey( 'purchase_cost', $sales );
		$this->assertArrayNotHasKey( 'sold_price', $sales );
		$acct = $this->in( fn() => Plugin::vehicles()->get( $id ), 'accountant' );
		$this->assertSame( 12000000, $acct['purchase_cost'] );
		$list = $this->in( fn() => Plugin::vehicles()->list(), 'mechanic' );
		$this->assertArrayNotHasKey( 'purchase_cost', $list['items'][0] );
	}
}
