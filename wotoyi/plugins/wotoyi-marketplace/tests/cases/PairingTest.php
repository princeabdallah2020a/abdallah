<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;

final class PairingTest extends Wotoyi_Test_Case {

	private function admin_create( array $profile = array( 'name' => 'Auto Prestige', 'city' => 'Dakar' ) ): WP_REST_Response {
		Fixtures::admin();
		return rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers', $profile ) ) );
	}

	private function pair( string $code, array $extra = array( 'source_dealer_uuid' => 'dp-dealer-0001' ) ): WP_REST_Response {
		return rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/sync/pair', array( 'code' => $code ) + $extra ) );
	}

	public function test_full_pairing_flow_then_signed_sync(): void {
		$created = $this->admin_create();
		$this->assertStatus( 201, $created );
		$code = $created->get_data()['pairing_code'];
		$this->assertTrue( 1 === preg_match( '/^WTY-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code ), 'code format' );
		$this->assertSame( 'pending', $created->get_data()['status'] );

		$paired = $this->pair( $code );
		$this->assertStatus( 201, $paired );
		$d = $paired->get_data();
		$this->assertTrue( 1 === preg_match( '/^wk_[a-f0-9]{16}$/', $d['key_id'] ) );
		$this->assertGreaterThanOrEqual( 43, strlen( $d['secret'] ) );
		$this->assertSame( 'v1', $d['api_version'] );
		$this->assertSame( 'no-store', $paired->get_headers()['Cache-Control'] );

		$dealer = Plugin::dealers()->find_by_key_id( $d['key_id'] );
		$this->assertSame( 'active', $dealer['status'] );
		$this->assertSame( 'dp-dealer-0001', $dealer['source_dealer_uuid'] );

		// The returned credentials work immediately.
		$res = Fixtures::call_signed( array( 'key_id' => $d['key_id'], 'secret' => $d['secret'] ), 'POST', '/wotoyi/v1/sync/events', Fixtures::event( 'vehicle.published', 'v-1', 1, Fixtures::vehicle() ) );
		$this->assertStatus( 200, $res );
	}

	public function test_pairing_code_is_single_use(): void {
		$code = $this->admin_create()->get_data()['pairing_code'];
		$this->assertStatus( 201, $this->pair( $code ) );
		$second = $this->pair( $code );
		$this->assertStatus( 403, $second );
		$this->assertSame( 'wotoyi_invalid_pairing_code', $second->get_data()['code'] );
		$this->assertSame( 1, Fixtures::table_count( 'dealers', 'key_id IS NOT NULL' ), 'no second credential set' );
	}

	public function test_code_expires(): void {
		$code = $this->admin_create()->get_data()['pairing_code'];
		Clock::set( Clock::now() + 901 );
		$this->assertStatus( 403, $this->pair( $code ) );
	}

	public function test_code_is_case_and_separator_tolerant_and_never_stored_in_clear(): void {
		$code = $this->admin_create()->get_data()['pairing_code'];
		global $wpdb;
		$this->assertStringNotContains( $code, json_encode( $wpdb->get_results( 'SELECT * FROM wp_wotoyi_dealers' ) ) );
		$this->assertStringNotContains( str_replace( '-', '', $code ), json_encode( $wpdb->get_results( 'SELECT * FROM wp_wotoyi_dealers' ) ) );
		$this->assertStatus( 201, $this->pair( strtolower( str_replace( '-', ' ', $code ) ) ) );
	}

	public function test_wrong_codes_are_throttled_per_ip(): void {
		$this->admin_create();
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertStatus( 403, $this->pair( 'WTY-AAAA-BBBB-CC' . $i . $i ) );
		}
		$blocked = $this->pair( 'WTY-AAAA-BBBB-CCDD' );
		$this->assertStatus( 429, $blocked );
	}

	public function test_valid_code_is_also_refused_while_ip_is_throttled(): void {
		$code = $this->admin_create()->get_data()['pairing_code'];
		for ( $i = 0; $i < 5; $i++ ) {
			$this->pair( 'WTY-ZZZZ-ZZZZ-ZZ2' . $i );
		}
		$this->assertStatus( 429, $this->pair( $code ), 'brute-force window blocks even correct codes' );
	}

	public function test_source_identity_is_required_and_must_match(): void {
		$code = $this->admin_create()->get_data()['pairing_code'];
		$this->assertStatus( 422, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/sync/pair', array( 'code' => $code ) ) ) );
		// Code still valid after the 422 (not consumed).
		$this->assertStatus( 201, $this->pair( $code ) );

		$created = $this->admin_create( array( 'name' => 'Bound', 'source_dealer_uuid' => 'expected-dealer-01' ) );
		$code2   = $created->get_data()['pairing_code'];
		$this->assertStatus( 403, $this->pair( $code2, array( 'source_dealer_uuid' => 'someone-else-0001' ) ) );
		$this->assertStatus( 201, $this->pair( $code2, array( 'source_dealer_uuid' => 'expected-dealer-01' ) ) );
	}

	public function test_cannot_pair_two_dealers_to_the_same_source_identity(): void {
		$c1 = $this->admin_create( array( 'name' => 'One' ) )->get_data()['pairing_code'];
		$c2 = $this->admin_create( array( 'name' => 'Two' ) )->get_data()['pairing_code'];
		$this->assertStatus( 201, $this->pair( $c1 ) );
		$this->assertStatus( 409, $this->pair( $c2 ) );
	}

	public function test_suspended_dealer_cannot_pair_and_admin_cannot_reissue(): void {
		$created = $this->admin_create();
		$uuid    = $created->get_data()['dealer_id'];
		$code    = $created->get_data()['pairing_code'];
		Fixtures::admin();
		$st = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', "/wotoyi/v1/admin/dealers/$uuid/status", array( 'status' => 'suspended' ) ) ) );
		$this->assertStatus( 200, $st );
		$this->assertStatus( 403, $this->pair( $code ), 'suspension also kills pending codes' );
		$this->assertStatus( 404, rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', "/wotoyi/v1/admin/dealers/$uuid/pairing-code" ) ) ) );
	}

	public function test_new_pairing_code_replaces_the_old_one(): void {
		$created = $this->admin_create();
		$uuid    = $created->get_data()['dealer_id'];
		$old     = $created->get_data()['pairing_code'];
		$new     = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', "/wotoyi/v1/admin/dealers/$uuid/pairing-code" ) ) )->get_data()['pairing_code'];
		$this->assertStatus( 403, $this->pair( $old ) );
		$this->assertStatus( 201, $this->pair( $new ) );
	}

	public function test_reconnect_after_disconnect_with_fresh_code_rotates_credentials(): void {
		$created = $this->admin_create();
		$uuid    = $created->get_data()['dealer_id'];
		$first   = $this->pair( $created->get_data()['pairing_code'] )->get_data();
		Fixtures::admin();
		rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', "/wotoyi/v1/admin/dealers/$uuid/status", array( 'status' => 'disconnected' ) ) ) );
		$this->assertStatus( 401, Fixtures::call_signed( array( 'key_id' => $first['key_id'], 'secret' => $first['secret'] ), 'GET', '/wotoyi/v1/sync/health' ) );
		$code = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', "/wotoyi/v1/admin/dealers/$uuid/pairing-code" ) ) )->get_data()['pairing_code'];
		$second = $this->pair( $code )->get_data();
		$this->assertTrue( $first['key_id'] !== $second['key_id'] );
		$this->assertStatus( 200, Fixtures::call_signed( array( 'key_id' => $second['key_id'], 'secret' => $second['secret'] ), 'GET', '/wotoyi/v1/sync/health' ) );
	}

	public function test_admin_routes_require_capability_and_nonce(): void {
		$body = array( 'name' => 'X' );
		// Anonymous.
		$this->assertStatus( 401, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers', $body ) ) );
		// Logged in without capability.
		Fixtures::login( 5, array( 'read' ) );
		$this->assertStatus( 403, rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers', $body ) ) ) );
		// Capability but no nonce (CSRF).
		Fixtures::admin();
		$no_nonce = rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers', $body ) );
		$this->assertStatus( 403, $no_nonce );
		$this->assertSame( 'rest_cookie_invalid_nonce', $no_nonce->get_data()['code'] );
		$bad = Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers', $body );
		$bad->set_header( 'X-WP-Nonce', 'forged' );
		$this->assertStatus( 403, rest_do_request( $bad ) );
		$this->assertSame( 0, Fixtures::table_count( 'dealers' ) );
		// Read route needs capability only.
		Fixtures::login( 5, array( 'read' ) );
		$this->assertStatus( 403, Fixtures::get( '/wotoyi/v1/admin/dealers/00000000-0000-7000-8000-000000000000/sync-log' ) );
	}

	public function test_admin_dealer_creation_validates_input(): void {
		$this->assertStatus( 422, $this->admin_create( array( 'city' => 'Dakar' ) ), 'name required' );
		$first = $this->admin_create( array( 'name' => 'Dup', 'source_dealer_uuid' => 'dp-duplicate-01' ) );
		$this->assertStatus( 201, $first );
		$this->assertStatus( 409, $this->admin_create( array( 'name' => 'Dup2', 'source_dealer_uuid' => 'dp-duplicate-01' ) ) );
	}

	public function test_admin_sync_log_endpoint(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1' );
		Fixtures::admin();
		$res = Fixtures::get( '/wotoyi/v1/admin/dealers/' . $a['row']['uuid'] . '/sync-log' );
		$this->assertStatus( 200, $res );
		$this->assertCount( 1, $res->get_data()['items'] );
		$this->assertSame( 'vehicle.published', $res->get_data()['items'][0]['event_type'] );
		$this->assertArrayNotHasKey( 'result', $res->get_data()['items'][0] );
	}

	public function test_consume_is_atomic_only_one_caller_wins(): void {
		$d    = Fixtures::dealer();
		$id   = (int) $d['row']['id'];
		$code = Plugin::dealers()->issue_pairing_code( $id, 900 );
		$this->assertTrue( Plugin::dealers()->consume_pairing_code( $id, $code ) );
		$this->assertFalse( Plugin::dealers()->consume_pairing_code( $id, $code ), 'second concurrent consumer loses' );
		$code2 = Plugin::dealers()->issue_pairing_code( $id, 900 );
		Clock::set( Clock::now() + 901 );
		$this->assertFalse( Plugin::dealers()->consume_pairing_code( $id, $code2 ), 'expired code cannot be consumed' );
	}

	public function test_pairing_code_helpers(): void {
		$this->assertSame( 'WTY-ABCD-EFGH-JKLM', Crypto::normalize_pairing_code( 'wty abcd/efgh jklm' ) );
		$this->assertSame( '', Crypto::normalize_pairing_code( 'ABCD-EFGH-JKLM' ) );
		$this->assertSame( '', Crypto::normalize_pairing_code( 'WTY-ABCD' ) );
		$codes = array();
		for ( $i = 0; $i < 200; $i++ ) {
			$codes[ Crypto::pairing_code() ] = 1;
		}
		$this->assertCount( 200, $codes, 'no collisions in 200 draws' );
	}
}
