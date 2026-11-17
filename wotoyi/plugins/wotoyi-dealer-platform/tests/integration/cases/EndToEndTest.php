<?php
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Sync\Reconciler;
use Wotoyi\DealerPlatform\Sync\LeadPuller;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\Marketplace\Plugin as MP;

final class EndToEndTest extends Integration_Case {

	private function models( array $result ): array {
		$m = array_map( fn( $i ) => (string) ( $i['model'] ?? '' ), $result['items'] );
		sort( $m );
		return $m;
	}

	public function test_vehicle_lifecycle_dealer_to_marketplace_over_signed_http(): void {
		$p = $this->paired_tenant( 'Dakar Motors', 11 );
		$t = $p['tenant'];
		$this->assertSame( 0, $this->search()['total'], 'marketplace starts empty' );

		$v = Fixtures::in( $t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'Zebra9', 'status' => 'published' ) ) ) );
		$this->assertSame( 0, $this->search()['total'], 'nothing is visible before the worker pushes' );
		$this->assertSame( 1, Fixtures::in( $t, fn() => TenantDb::count( 'outbox', array( 'status' => 'pending' ) ) ) );

		$r = $this->push( $t );
		$this->assertSame( 1, $r['sent'], json_encode( $r ) );
		$found = $this->search( array( 'q' => 'Zebra9' ) );
		$this->assertSame( 1, $found['total'], 'published vehicle appears in marketplace search' );
		$item = $found['items'][0];
		$this->assertSame( 'Zebra9', $item['model'] );
		$this->assertSame( 15000000, (int) $item['price'] );
		$blob = json_encode( $found );
		foreach ( array( 'JTMBFREV5LD000001', 'secret note', '12000000', 'purchase_cost', 'internal_notes' ) as $leak ) {
			$this->assertFalse( str_contains( $blob, $leak ), "dealer-only data '{$leak}' reached the public marketplace" );
		}
		$this->assertTrue( count( array_filter( $GLOBALS['wotoyi_bridge_log'], fn( $l ) => '/wotoyi/v1/sync/batch' === $l[1] && 200 === $l[2] ) ) >= 1, 'went through the signed batch endpoint' );

		// Edit propagates.
		$this->advance( 5 );
		Fixtures::in( $t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 9990000 ) ) );
		$this->push( $t );
		$this->assertSame( 9990000, (int) $this->search( array( 'q' => 'Zebra9' ) )['items'][0]['price'] );

		// Unpublish hides, republish shows.
		$this->advance( 5 );
		Fixtures::in( $t, fn() => Plugin::vehicles()->transition( $v['id'], 'unpublished' ) );
		$this->push( $t );
		$this->assertSame( 0, $this->search( array( 'q' => 'Zebra9' ) )['total'] );
		$this->advance( 5 );
		Fixtures::in( $t, fn() => Plugin::vehicles()->transition( $v['id'], 'published' ) );
		$this->push( $t );
		$this->assertSame( 1, $this->search( array( 'q' => 'Zebra9' ) )['total'] );

		// Delete propagates.
		$this->advance( 5 );
		Fixtures::in( $t, fn() => Plugin::vehicles()->archive( $v['id'] ) );
		$res = $this->push( $t );
		$this->assertSame( 1, $res['sent'], json_encode( $res ) );
		$this->assertSame( 0, $this->search( array( 'q' => 'Zebra9' ) )['total'], 'deleted vehicle disappears from search' );
		$this->assertSame( 0, Fixtures::in( $t, fn() => TenantDb::count( 'outbox', array( 'status' => 'pending' ) ) ) );
		$this->assertSame( 0, Fixtures::in( $t, fn() => TenantDb::count( 'outbox', array( 'status' => 'rejected' ) ) ) );
	}

	public function test_sold_vehicle_leaves_the_published_listing(): void {
		$p = $this->paired_tenant( 'Dakar Motors', 11 );
		$t = $p['tenant'];
		$v = Fixtures::in( $t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'Yak7', 'status' => 'published' ) ) ) );
		$this->push( $t );
		$this->assertSame( 1, $this->search( array( 'q' => 'Yak7' ) )['total'] );
		$this->advance( 5 );
		Fixtures::in( $t, fn() => Plugin::vehicles()->transition( $v['id'], 'sold' ) );
		$this->push( $t );
		$this->assertSame( 0, Fixtures::in( $t, fn() => TenantDb::count( 'outbox', array( 'status' => 'rejected' ) ) ) );
		$this->assertSame( 0, $this->search( array( 'q' => 'Yak7' ) )['total'], 'a sold vehicle is not offered as available' );
	}

	public function test_a_wrong_secret_is_rejected_by_the_real_receiver_and_nothing_is_published(): void {
		$p = $this->paired_tenant( 'Dakar Motors', 11 );
		$t = $p['tenant'];
		Fixtures::in( $t, fn() => \Wotoyi\DealerPlatform\Sync\SyncState::save( array( 'secret_enc' => \Wotoyi\DealerPlatform\Support\Crypto::encrypt( 'not-the-real-secret-000000000' ) ) ) );
		Fixtures::in( $t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'Nope1', 'status' => 'published' ) ) ) );
		$r = $this->push( $t );
		$this->assertSame( 'auth_failed', $r['status'], json_encode( $r ) );
		$this->assertSame( 0, $this->search( array( 'q' => 'Nope1' ) )['total'] );
	}

	public function test_tenant_isolation_across_both_plugins(): void {
		$a = $this->paired_tenant( 'Dealer A', 11 );
		$b = $this->paired_tenant( 'Dealer B', 12 );
		$va = Fixtures::in( $a['tenant'], fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'AlphaCar', 'status' => 'published', 'vin' => 'AAAAAAAAAAAAAAAAA' ) ) ) );
		$vb = Fixtures::in( $b['tenant'], fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'BetaCar', 'status' => 'published', 'vin' => 'BBBBBBBBBBBBBBBBB' ) ) ) );
		$this->assertSame( 1, $this->push( $a['tenant'] )['sent'] );
		$this->assertSame( 1, $this->push( $b['tenant'] )['sent'] );
		$this->assertSame( array( 'AlphaCar', 'BetaCar' ), $this->models( $this->search() ) );

		// Every marketplace listing belongs to the right marketplace dealer.
		$da = MP::dealers()->find_by_uuid( $a['mp']['dealer_id'] );
		$db = MP::dealers()->find_by_uuid( $b['mp']['dealer_id'] );
		$this->assertNotSame( $da['id'], $db['id'] );
		$this->assertSame( $a['tenant']['uuid'], $da['source_dealer_uuid'] );

		// Dealer Platform API: user of B cannot see or touch A's vehicle, and lists only its own.
		Fixtures::login( 12 );
		$list = Fixtures::call( 'GET', '/vehicles' )->get_data();
		$this->assertSame( array( $vb['id'] ), array_column( $list['items'], 'id' ) );
		$this->assertStatus( 404, Fixtures::call( 'GET', '/vehicles/' . $va['id'] ) );
		$this->assertStatus( 404, Fixtures::call( 'PATCH', '/vehicles/' . $va['id'], array( 'price' => 1 ) ) );
		$this->assertStatus( 404, Fixtures::call( 'POST', '/vehicles/' . $va['id'] . '/unpublish' ) );
		$this->assertFalse( str_contains( json_encode( Fixtures::call( 'GET', '/sync/outbox' )->get_data() ), $va['id'] ), 'outbox of B has no trace of A' );
		$this->assertFalse( str_contains( json_encode( Fixtures::call( 'GET', '/dashboard' )->get_data() ), 'AlphaCar' ) );
		// A user of neither tenant has no access at all.
		Fixtures::user( 77 );
		Fixtures::login( 77 );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/vehicles' ) );
		// Nothing of A was altered by B's activity.
		$this->assertSame( 'published', Fixtures::in( $a['tenant'], fn() => Plugin::vehicles()->get( $va['id'] ) )['status'] );
	}

	public function test_reconciliation_against_the_real_manifest_repairs_remote_loss(): void {
		$p = $this->paired_tenant( 'Dakar Motors', 11 );
		$t = $p['tenant'];
		$ids = array();
		foreach ( array( 'Rec1', 'Rec2', 'Rec3' ) as $m ) {
			$ids[ $m ] = Fixtures::in( $t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => $m, 'status' => 'published' ) ) ) )['id'];
		}
		$this->push( $t );
		$this->assertSame( 3, $this->search()['total'] );
		// Simulate marketplace data loss for one vehicle (direct DB delete) + one tombstoned by mistake.
		$dealer = MP::dealers()->find_by_uuid( $p['mp']['dealer_id'] );
		$row    = MP::vehicles()->find_by_source( (int) $dealer['id'], $ids['Rec1'] );
		$this->assertTrue( is_array( $row ), 'marketplace has the vehicle' );
		$GLOBALS['wpdb']->query( 'DELETE FROM ' . MP::vehicles()->table() . ' WHERE id = ' . (int) $row['id'] );
		\Wotoyi\Marketplace\Support\Cache::bump(); // The direct DELETE bypassed the marketplace's own cache invalidation.
		$this->assertSame( 2, $this->search()['total'] );
		$sum = Fixtures::in( $t, fn() => Reconciler::run_for_current() );
		$this->assertSame( 'ok', $sum['status'], json_encode( $sum ) );
		$this->assertSame( 1, $sum['types']['vehicle']['missing_remote'], json_encode( $sum ) );
		$this->push( $t );
		$this->assertSame( 3, $this->search()['total'], 'the lost vehicle was restored from the dealer platform' );
		$sum2 = Fixtures::in( $t, fn() => Reconciler::run_for_current() );
		$this->assertSame( 3, $sum2['types']['vehicle']['ok'], json_encode( $sum2 ) );
	}

	public function test_leads_flow_from_marketplace_to_crm_once(): void {
		$p = $this->paired_tenant( 'Dakar Motors', 11 );
		$t = $p['tenant'];
		$v = Fixtures::in( $t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'Lead1', 'status' => 'published' ) ) ) );
		$this->push( $t );
		$dealer = MP::dealers()->find_by_uuid( $p['mp']['dealer_id'] );
		$mpv    = MP::vehicles()->find_by_source( (int) $dealer['id'], $v['id'] );
		MP::leads()->create( array( 'dealer_id' => $dealer['id'], 'vehicle_id' => $mpv['id'], 'name' => 'Awa', 'phone' => '+221770000000', 'message' => 'Is it available?', 'consent' => true ) );
		$r = Fixtures::in( $t, fn() => LeadPuller::run_for_current() );
		$this->assertSame( 1, $r['imported'], json_encode( $r ) );
		$lead = Fixtures::in( $t, fn() => TenantDb::select( 'leads' ) )[0];
		$this->assertSame( 'marketplace', $lead['source'] );
		$this->assertSame( $v['id'], $lead['vehicle_uuid'], 'lead is linked to the dealer\'s own vehicle' );
		$this->assertSame( 'new', $lead['stage'] );
		$this->assertSame( 0, count( MP::leads()->pending_for_dealer( (int) $dealer['id'], 50 ) ), 'acknowledged on the marketplace' );
		$r = Fixtures::in( $t, fn() => LeadPuller::run_for_current() );
		$this->assertSame( 0, $r['imported'] );
		$this->assertSame( 1, Fixtures::in( $t, fn() => TenantDb::count( 'leads' ) ) );
	}

	public function test_local_transport_demo_mode_pushes_without_http(): void {
		update_option( 'wotoyi_mp_settings', array( 'local_mode' => true ) );
		update_option( 'wotoyi_dp_settings', array( 'local_mode' => true ) );
		$t = Fixtures::tenant( 'Demo Motors', 11 );
		$st = Fixtures::in( $t, fn() => \Wotoyi\DealerPlatform\Sync\Pairing::pair_local() );
		$this->assertSame( 'local', $st['transport'] );
		$GLOBALS['wotoyi_bridge_log'] = array();
		$v = Fixtures::in( $t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'model' => 'Local1', 'status' => 'published' ) ) ) );
		// Local mode pushes inline (kick), no worker run needed.
		$this->assertSame( 1, $this->search( array( 'q' => 'Local1' ) )['total'], 'inline push in local mode' );
		Fixtures::in( $t, fn() => Plugin::vehicles()->update( $v['id'], array( 'price' => 5550000 ) ) );
		$this->assertSame( 5550000, (int) $this->search( array( 'q' => 'Local1' ) )['items'][0]['price'] );
		Fixtures::in( $t, fn() => Plugin::vehicles()->archive( $v['id'] ) );
		$this->assertSame( 0, $this->search( array( 'q' => 'Local1' ) )['total'] );
		$this->assertSame( array(), $GLOBALS['wotoyi_bridge_log'], 'no HTTP was used' );
		$this->assertSame( 0, Fixtures::in( $t, fn() => TenantDb::count( 'outbox', array( 'status' => 'pending' ) ) ) );
	}
}
