<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Sync\HttpTransport;
use Wotoyi\Marketplace\Sync\Signer;

final class SyncAuthTest extends Wotoyi_Test_Case {

	private const EVENTS = '/wotoyi/v1/sync/events';

	private function ev( string $sid = 'v-1', int $v = 1 ): array {
		return Fixtures::event( 'vehicle.published', $sid, $v, Fixtures::vehicle() );
	}

	public function test_valid_signed_request_is_accepted_end_to_end(): void {
		$a   = Fixtures::dealer();
		$res = Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev() );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'applied', $res->get_data()['status'] );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_missing_or_malformed_headers_are_401(): void {
		$a = Fixtures::dealer();
		$r = new WP_REST_Request( 'POST', self::EVENTS );
		$r->set_body( '{}' );
		$res = rest_do_request( $r );
		$this->assertStatus( 401, $res );
		$this->assertSame( 'wotoyi_auth_missing', $res->get_data()['code'] );

		$bad = Fixtures::signed( $a, 'POST', self::EVENTS, $this->ev() );
		$bad->set_header( 'X-Wotoyi-Signature', 'zzz' );
		$this->assertSame( 'wotoyi_auth_malformed', rest_do_request( $bad )->get_data()['code'] );
	}

	public function test_wrong_secret_and_unknown_key_give_the_same_answer(): void {
		$a   = Fixtures::dealer();
		$one = Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev(), null, null, 'wrong-secret' );
		$two = Fixtures::call_signed( array( 'key_id' => 'wk_0123456789abcdef', 'secret' => 'x' ) + $a, 'POST', self::EVENTS, $this->ev() );
		$this->assertStatus( 401, $one );
		$this->assertStatus( 401, $two );
		$this->assertSame( $one->get_data(), $two->get_data(), 'no key-existence oracle' );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_tampered_body_is_rejected(): void {
		$a   = Fixtures::dealer();
		$req = Fixtures::signed( $a, 'POST', self::EVENTS, $this->ev() );
		$req->set_body( str_replace( 'Toyota', 'Hacked', $req->get_body() ) );
		$this->assertStatus( 401, rest_do_request( $req ) );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_signature_is_bound_to_method_and_path(): void {
		$a   = Fixtures::dealer();
		$ev  = $this->ev();
		// Signed for /sync/events, sent to /sync/batch.
		$req = new WP_REST_Request( 'POST', '/wotoyi/v1/sync/batch' );
		$req->set_body( (string) json_encode( array( 'events' => array( $ev ) ) ) );
		foreach ( Signer::headers( $a['key_id'], $a['secret'], 'POST', self::EVENTS, $req->get_body() ) as $k => $v ) {
			$req->set_header( $k, $v );
		}
		$this->assertStatus( 401, rest_do_request( $req ) );
		// Signed as PUT for vehicle A, replayed against vehicle B's URL.
		$put = Fixtures::signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/aaa', Fixtures::vehicle( array( 'version' => 1 ) ) );
		$other = new WP_REST_Request( 'PUT', '/wotoyi/v1/sync/vehicles/bbb' );
		$other->set_body( $put->get_body() );
		foreach ( array( 'X-Wotoyi-Key', 'X-Wotoyi-Timestamp', 'X-Wotoyi-Nonce', 'X-Wotoyi-Signature' ) as $h ) {
			$other->set_header( $h, (string) $put->get_header( $h ) );
		}
		$this->assertStatus( 401, rest_do_request( $other ) );
	}

	public function test_timestamp_window(): void {
		$a = Fixtures::dealer();
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'a' ), Clock::now() - 299 ) );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'b' ), Clock::now() + 299 ) );
		$old = Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'c' ), Clock::now() - 301 );
		$this->assertStatus( 401, $old );
		$this->assertSame( 'wotoyi_timestamp_skew', $old->get_data()['code'] );
		$this->assertStatus( 401, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'd' ), Clock::now() + 301 ) );
	}

	public function test_nonce_replay_is_rejected_even_with_valid_signature(): void {
		$a   = Fixtures::dealer();
		$req = Fixtures::signed( $a, 'POST', self::EVENTS, $this->ev() );
		$this->assertStatus( 200, rest_do_request( $req ) );
		$again = rest_do_request( $req ); // Exact same bytes, headers and nonce.
		$this->assertStatus( 401, $again );
		$this->assertSame( 'wotoyi_replay', $again->get_data()['code'] );
	}

	public function test_nonce_is_scoped_per_dealer_and_pruned_after_expiry(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		$n = 'shared-nonce-0123456789';
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'x' ), null, $n ) );
		$this->assertStatus( 200, Fixtures::call_signed( $b, 'POST', self::EVENTS, $this->ev( 'x' ), null, $n ) );
		$this->assertSame( 2, Fixtures::table_count( 'sync_nonces' ) );
		Clock::set( Clock::now() + 2000 );
		Plugin::nonces()->prune();
		$this->assertSame( 0, Fixtures::table_count( 'sync_nonces' ) );
	}

	public function test_failed_signature_does_not_burn_the_nonce(): void {
		$a = Fixtures::dealer();
		$n = 'attacker-nonce-0123456789';
		Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev(), null, $n, 'wrong' );
		$this->assertSame( 0, Fixtures::table_count( 'sync_nonces' ), 'only authenticated nonces are stored' );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev(), null, $n ) );
	}

	public function test_suspended_dealer_gets_403_and_listings_vanish(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1' );
		$this->assertSame( 1, Plugin::search()->count( array() ) );
		Plugin::dealers()->set_status( (int) $a['row']['id'], 'suspended' );
		$res = Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'v-2' ) );
		$this->assertStatus( 403, $res );
		$this->assertSame( 0, Plugin::search()->count( array() ), 'cache invalidated and suspended dealer hidden' );
	}

	public function test_oversized_body_is_413(): void {
		$a   = Fixtures::dealer();
		$big = Fixtures::signed( $a, 'POST', self::EVENTS, null, null, null, null, str_repeat( 'x', 1048577 ) );
		$this->assertStatus( 413, rest_do_request( $big ) );
	}

	public function test_secret_rotation_with_grace_period(): void {
		$a   = Fixtures::dealer();
		$rot = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/rotate-key', array() );
		$this->assertStatus( 200, $rot );
		$new = array( 'key_id' => $rot->get_data()['key_id'], 'secret' => $rot->get_data()['secret'] );
		$this->assertSame( $a['key_id'], $new['key_id'] );
		$this->assertTrue( $new['secret'] !== $a['secret'] );
		$this->assertStatus( 200, Fixtures::call_signed( $new + $a, 'POST', self::EVENTS, $this->ev( 'n1' ) ), 'new secret works' );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'n2' ) ), 'old secret still works during grace' );
		Clock::set( Clock::now() + 601 );
		$this->assertStatus( 401, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'n3' ), Clock::now() ), 'old secret dead after grace' );
		$this->assertStatus( 200, Fixtures::call_signed( $new + $a, 'POST', self::EVENTS, $this->ev( 'n4' ), Clock::now() ) );
	}

	public function test_disconnect_revokes_credentials_and_hides_listings(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v-1' );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/disconnect', array() ) );
		$this->assertStatus( 401, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'v-2' ) ) );
		$row = Fixtures::reload( $a );
		$this->assertSame( 'disconnected', $row['status'] );
		$this->assertNull( $row['secret_enc'] );
		$this->assertSame( 0, Plugin::search()->count( array() ) );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ), 'data kept for reconnection' );
	}

	public function test_secrets_are_encrypted_at_rest(): void {
		$a   = Fixtures::dealer();
		$row = Fixtures::reload( $a );
		$this->assertStringNotContains( $a['secret'], (string) $row['secret_enc'] );
		$this->assertTrue( str_starts_with( (string) $row['secret_enc'], 'v1:' ) );
		global $wpdb;
		$this->assertStringNotContains( $a['secret'], json_encode( $wpdb->get_results( 'SELECT * FROM wp_wotoyi_dealers' ) ) );
		$this->assertSame( array( $a['secret'] ), Plugin::dealers()->accepted_secrets( $row ) );
		// Tampered ciphertext does not decrypt.
		$tampered = substr( (string) $row['secret_enc'], 0, -4 ) . 'AAAA';
		$this->assertNull( \Wotoyi\Marketplace\Support\Crypto::decrypt( $tampered ) );
	}

	public function test_failed_auth_attempts_are_throttled_per_ip(): void {
		$a = Fixtures::dealer();
		for ( $i = 0; $i < 30; $i++ ) {
			Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev(), null, null, 'wrong' );
		}
		$res = Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev() );
		$this->assertStatus( 429, $res, 'even a valid request is blocked while the IP is in the penalty window' );
	}

	public function test_health_endpoint_is_signed_and_tenant_scoped(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		Fixtures::publish( $a, 'v-1' );
		Fixtures::publish( $a, 'v-2' );
		Fixtures::publish( $b, 'v-1' );
		$res = Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' );
		$this->assertStatus( 200, $res );
		$this->assertSame( 2, $res->get_data()['vehicles'] );
		$this->assertSame( $a['row']['uuid'], $res->get_data()['dealer_id'] );
		$this->assertStatus( 401, Fixtures::get( '/wotoyi/v1/sync/health' ) );
	}

	public function test_rest_style_put_and_delete_wrappers(): void {
		$a   = Fixtures::dealer();
		$put = Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/car-77', Fixtures::vehicle( array( 'version' => 1 ) ) );
		$this->assertStatus( 200, $put );
		$this->assertSame( 'car-77', $put->get_data()['entity']['source_id'] );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ) );
		$mismatch = Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/car-77', Fixtures::vehicle( array( 'version' => 2, 'source_id' => 'other' ) ) );
		$this->assertStatus( 422, $mismatch );
		$del = Fixtures::call_signed( $a, 'DELETE', '/wotoyi/v1/sync/vehicles/car-77', array( 'version' => 2 ) );
		$this->assertStatus( 200, $del );
		$this->assertSame( 'deleted', Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'car-77' )['status'] );
		$svc = Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/services/svc-1', array( 'version' => 1, 'title' => 'Lavage', 'category' => 'detailing' ) );
		$this->assertStatus( 200, $svc );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'DELETE', '/wotoyi/v1/sync/services/svc-1' ) );
	}

	public function test_put_wrapper_is_idempotent_for_identical_retries(): void {
		$a = Fixtures::dealer();
		$one = Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/car-1', Fixtures::vehicle( array( 'version' => 1 ) ) );
		$two = Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/car-1', Fixtures::vehicle( array( 'version' => 1 ) ) );
		$this->assertStatus( 200, $two );
		$this->assertTrue( $two->get_data()['duplicate'] );
		$this->assertSame( $one->get_data()['entity']['id'], $two->get_data()['entity']['id'] );
	}

	public function test_manifest_for_reconciliation_is_scoped_and_paginated(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		for ( $i = 1; $i <= 5; $i++ ) {
			Fixtures::publish( $a, "a$i", array(), $i );
		}
		Fixtures::publish( $b, 'b1', array(), 9 );
		Plugin::receiver()->handle_event( $a['row'], Fixtures::event( 'vehicle.deleted', 'a2', 10 ) );
		$p1 = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/manifest', array( 'type' => 'vehicle', 'limit' => 3 ) );
		$this->assertStatus( 200, $p1 );
		$this->assertCount( 3, $p1->get_data()['items'] );
		$this->assertNotNull( $p1->get_data()['next_cursor'] );
		$p2 = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/manifest', array( 'type' => 'vehicle', 'limit' => 3, 'cursor' => $p1->get_data()['next_cursor'] ) );
		$this->assertCount( 2, $p2->get_data()['items'] );
		$this->assertNull( $p2->get_data()['next_cursor'] );
		$all = array_merge( $p1->get_data()['items'], $p2->get_data()['items'] );
		$this->assertSame( array( 'a1', 'a2', 'a3', 'a4', 'a5' ), array_column( $all, 'source_id' ), 'only dealer A rows, in order' );
		$this->assertSame( array( 'version' => 10, 'status' => 'deleted' ), array( 'version' => $all[1]['version'], 'status' => $all[1]['status'] ), 'tombstones are listed' );
		$this->assertStatus( 422, Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/manifest', array( 'type' => 'bogus' ) ) );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/manifest', array( 'type' => 'service' ) ) );
	}

	public function test_invalid_json_and_batch_shape(): void {
		$a   = Fixtures::dealer();
		$bad = Fixtures::signed( $a, 'POST', self::EVENTS, null, null, null, null, '{not json' );
		$this->assertStatus( 400, rest_do_request( $bad ) );
		$list = Fixtures::signed( $a, 'POST', self::EVENTS, null, null, null, null, '[1,2,3]' );
		$this->assertStatus( 400, rest_do_request( $list ) );
		$this->assertStatus( 422, Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/batch', array( 'events' => 'nope' ) ) );
		$ok = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/batch', array( 'events' => array( $this->ev( 'q1' ), $this->ev( 'q2' ) ) ) );
		$this->assertStatus( 200, $ok );
		$this->assertSame( array( 'applied' => 2 ), $ok->get_data()['summary'] );
	}

	public function test_http_transport_builds_requests_the_receiver_accepts(): void {
		$a   = Fixtures::dealer();
		$t   = new HttpTransport( 'https://marketplace.example.com', $a['key_id'], $a['secret'] );
		$req = $t->build( 'POST', '/sync/events', $this->ev( 'ht-1' ) );
		$this->assertSame( 'https://marketplace.example.com/wp-json/wotoyi/v1/sync/events', $req['url'] );
		$r = new WP_REST_Request( 'POST', '/wotoyi/v1/sync/events' );
		$r->set_body( $req['args']['body'] );
		foreach ( $req['args']['headers'] as $k => $v ) {
			$r->set_header( $k, $v );
		}
		$res = rest_do_request( $r );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'applied', $res->get_data()['status'] );
		$this->assertSame( 0, $req['args']['redirection'], 'never follow redirects with signed requests' );
		$this->assertTrue( $req['args']['sslverify'] );
	}

	public function test_signature_canonical_string_is_stable(): void {
		$c = Signer::canonical( '1800000000', 'abcdefghijklmnop', 'post', '/wotoyi/v1/sync/events', '{"a":1}' );
		$this->assertSame( "WOTOYI-HMAC-SHA256\n1800000000\nabcdefghijklmnop\nPOST\n/wotoyi/v1/sync/events\n" . hash( 'sha256', '{"a":1}' ), $c );
		// Known-answer vector, computable with any HMAC tool: key "secret".
		$this->assertSame( hash_hmac( 'sha256', $c, 'secret' ), Signer::signature( 'secret', '1800000000', 'abcdefghijklmnop', 'POST', '/wotoyi/v1/sync/events', '{"a":1}' ) );
	}
}
