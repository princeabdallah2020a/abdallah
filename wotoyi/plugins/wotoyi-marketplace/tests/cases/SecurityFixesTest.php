<?php
/**
 * Regression tests for SECURITY-REVIEW findings H1, M1, M2, M3, M4, M5, M7, L1 (H2 lives in PrivacyTest).
 * Each test_<id>_* reproduces the review's exploit and FAILS on the code as reviewed (v0.1.0).
 */

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Ip;
use Wotoyi\Marketplace\Support\LeadGuard;
use Wotoyi\Marketplace\Sync\HttpTransport;

final class SecurityFixesTest extends Wotoyi_Test_Case {

	private const EVENTS = '/wotoyi/v1/sync/events';

	private function ev( string $sid = 'v-1', int $v = 1 ): array {
		return Fixtures::event( 'vehicle.published', $sid, $v, Fixtures::vehicle() );
	}

	/** Staff creates a dealer; returns [uuid, pairing code]. */
	private function admin_create( array $profile ): array {
		Fixtures::admin();
		$res = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers', $profile ) ) );
		$this->assertStatus( 201, $res );
		return array( $res->get_data()['dealer_id'], $res->get_data()['pairing_code'] );
	}

	private function pair( string $code, array $extra = array( 'source_dealer_uuid' => 'dp-dealer-0001' ) ): WP_REST_Response {
		return rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/sync/pair', array( 'code' => $code ) + $extra ) );
	}

	/* =============================== H1 =============================== */

	public function test_h1_previous_secret_cannot_rotate_or_disconnect(): void {
		$a   = Fixtures::dealer();
		$rot = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/rotate-key', array() );
		$this->assertStatus( 200, $rot );
		$new = array( 'key_id' => $rot->get_data()['key_id'], 'secret' => $rot->get_data()['secret'] ) + $a;

		// The attacker holds the OLD (leaked) secret, still inside the grace period.
		$steal = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/rotate-key', array() );
		$this->assertStatus( 401, $steal, 'rotate with the previous secret' );
		$this->assertSame( 'wotoyi_secret_superseded', $steal->get_data()['code'] );
		$this->assertStatus( 401, Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/disconnect', array() ), 'disconnect with the previous secret' );
		$this->assertSame( 'active', Fixtures::reload( $a )['status'] );

		// Compatibility: the grace period still serves ordinary content events with the old secret.
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'g1' ) ) );

		// After the grace period the legitimate dealer is still in control, the attacker is out.
		Clock::set( Clock::now() + 601 );
		$this->assertStatus( 200, Fixtures::call_signed( $new, 'GET', '/wotoyi/v1/sync/health', null, Clock::now() ) );
		$this->assertStatus( 401, Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health', null, Clock::now() ) );
		// The current secret can still rotate and disconnect.
		$this->assertStatus( 200, Fixtures::call_signed( $new, 'POST', '/wotoyi/v1/sync/rotate-key', array(), Clock::now() ) );
	}

	public function test_h1_staff_can_revoke_the_previous_secret_immediately(): void {
		$a = Fixtures::dealer();
		Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/rotate-key', array() );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' ), 'old secret works in grace' );
		Fixtures::admin();
		$res = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers/' . $a['row']['uuid'] . '/status', array( 'revoke_previous_secret' => true ) ) ) );
		$this->assertStatus( 200, $res );
		$this->assertStatus( 401, Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' ), 'old secret dead at once' );
	}

	/* =============================== M1 =============================== */

	public function test_m1_request_shim_uses_wordpress_parameter_precedence(): void {
		$r = new WP_REST_Request( 'PUT', '/x' );
		$r->set_header( 'Content-Type', 'application/json' );
		$r->set_body( '{"source_id":"from-json"}' );
		$r->set_query_params( array( 'source_id' => 'from-query', 'only_query' => 'q' ) );
		$r->set_url_params( array( 'source_id' => 'from-url', 'only_url' => 'u' ) );
		$this->assertSame( 'from-json', $r['source_id'], 'JSON > query > URL, like WP core' );
		$this->assertSame( 'q', $r['only_query'] );
		$this->assertSame( 'u', $r['only_url'] );
		$this->assertSame( 'from-url', $r->get_url_params()['source_id'] );
		$r2 = new WP_REST_Request( 'DELETE', '/x' );
		$r2->set_query_params( array( 'source_id' => 'from-query' ) );
		$r2->set_url_params( array( 'source_id' => 'from-url' ) );
		$this->assertSame( 'from-query', $r2['source_id'], 'query > URL' );
	}

	public function test_m1_unsigned_query_string_cannot_redirect_a_signed_delete(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'A' );
		Fixtures::publish( $a, 'B' );
		$req = Fixtures::signed( $a, 'DELETE', '/wotoyi/v1/sync/vehicles/A', array( 'version' => 2 ) );
		$req->set_query_params( array( 'source_id' => 'B' ) ); // Added in transit: not covered by the signature.
		$res = rest_do_request( $req );
		$this->assertStatus( 400, $res );
		$this->assertSame( 'wotoyi_query_not_allowed', $res->get_data()['code'] );
		$id = (int) $a['row']['id'];
		$this->assertSame( 'published', Plugin::vehicles()->find_by_source( $id, 'A' )['status'] );
		$this->assertSame( 'published', Plugin::vehicles()->find_by_source( $id, 'B' )['status'], 'B untouched' );
		// The dealer's genuine DELETE /A (same body) is not poisoned by a stored event_id conflict.
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'DELETE', '/wotoyi/v1/sync/vehicles/A', array( 'version' => 2 ) ) );
		$this->assertSame( 'deleted', Plugin::vehicles()->find_by_source( $id, 'A' )['status'] );
	}

	public function test_m1_query_string_is_refused_on_every_signed_route_but_rest_route_is_allowed(): void {
		$a   = Fixtures::dealer();
		$bad = Fixtures::signed( $a, 'GET', '/wotoyi/v1/sync/health' );
		$bad->set_query_params( array( 'x' => '1' ) );
		$this->assertStatus( 400, rest_do_request( $bad ) );
		$ok = Fixtures::signed( $a, 'GET', '/wotoyi/v1/sync/health' ); // Plain-permalink sites carry ?rest_route=...
		$ok->set_query_params( array( 'rest_route' => '/wotoyi/v1/sync/health' ) );
		$this->assertStatus( 200, rest_do_request( $ok ) );
	}

	public function test_m1_json_body_source_id_cannot_override_the_signed_url(): void {
		$a   = Fixtures::dealer();
		$res = Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/car-77', Fixtures::vehicle( array( 'version' => 1, 'source_id' => 'other' ) ) );
		$this->assertStatus( 422, $res, 'body/URL mismatch check must fire on WordPress parameter order' );
		$this->assertNull( Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'other' ), 'nothing written under the body id' );
		$this->assertNull( Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'car-77' ) );
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/car-77', Fixtures::vehicle( array( 'version' => 1, 'source_id' => 'car-77' ) ) ), 'matching id is fine' );
	}

	/* =============================== M2 =============================== */

	private function vehicle_uuid( array $dealer, string $sid = 'v1' ): string {
		Fixtures::publish( $dealer, $sid );
		return Plugin::vehicles()->find_by_source( (int) $dealer['row']['id'], $sid )['uuid'];
	}

	private function lead( string $vehicle, string $token, string $phone = '+221770000001', array $extra = array() ): WP_REST_Response {
		return rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', array( 'token' => $token, 'vehicle' => $vehicle, 'name' => 'Awa', 'phone' => $phone, 'consent' => true ) + $extra ) );
	}

	/** Issue a token for the current IP, wait for the minimum age. */
	private function token( string $target = '' ): string {
		$t = LeadGuard::issue( $target );
		Fixtures::tick( 5 );
		return $t;
	}

	public function test_m2_lead_token_is_single_use(): void {
		$u   = $this->vehicle_uuid( Fixtures::dealer() );
		$tok = $this->token();
		$this->assertStatus( 201, $this->lead( $u, $tok ) );
		$again = $this->lead( $u, $tok, '+221770000002' );
		$this->assertStatus( 400, $again, 'second lead with the same token' );
		$this->assertSame( 'wotoyi_challenge_failed', $again->get_data()['code'] );
		$this->assertSame( 1, Fixtures::table_count( 'leads' ) );
	}

	public function test_m2_lead_token_is_bound_to_the_requesters_ip(): void {
		$u   = $this->vehicle_uuid( Fixtures::dealer() );
		$tok = $this->token();
		$_SERVER['REMOTE_ADDR'] = '198.51.100.20'; // A botnet node using a token farmed elsewhere.
		$this->assertStatus( 400, $this->lead( $u, $tok ) );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertStatus( 201, $this->lead( $u, $tok ), 'the real owner can still use it' );
	}

	public function test_m2_lead_token_can_be_bound_to_a_listing(): void {
		$a  = Fixtures::dealer();
		$u1 = $this->vehicle_uuid( $a, 'v1' );
		$u2 = $this->vehicle_uuid( $a, 'v2' );
		$tok = $this->token( LeadGuard::target( 'vehicle', $u1 ) );
		$this->assertStatus( 400, $this->lead( $u2, $tok ), 'token issued for vehicle 1 refused for vehicle 2' );
		$this->assertStatus( 201, $this->lead( $u1, $tok ) );
		// Through the HTTP endpoint.
		$res = rest_do_request( Fixtures::request( 'GET', '/wotoyi/v1/leads/token', null, array( 'vehicle' => $u2 ) ) );
		$this->assertTrue( $res->get_data()['bound'] );
		Fixtures::tick( 5 );
		$this->assertStatus( 400, $this->lead( $u1, $res->get_data()['token'], '+221770000003' ) );
		$this->assertStatus( 201, $this->lead( $u2, $res->get_data()['token'], '+221770000003' ) );
	}

	public function test_m2_ipv6_addresses_of_one_slash64_share_the_lead_limit(): void {
		$u = $this->vehicle_uuid( Fixtures::dealer() );
		$this->assertSame( Ip::bucket( '2001:db8:1:2::1' ), Ip::bucket( '2001:db8:1:2:ffff:ffff:ffff:ffff' ) );
		$this->assertTrue( Ip::bucket( '2001:db8:1:2::1' ) !== Ip::bucket( '2001:db8:1:3::1' ) );
		$this->assertSame( '192.0.2.9', Ip::bucket( '::ffff:192.0.2.9' ) );
		$codes = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2::' . dechex( $i ); // 6 "different" addresses, one subscriber.
			$tok     = $this->token();
			$codes[] = $this->lead( $u, $tok, '+22177000010' . $i )->get_status();
		}
		$this->assertSame( array( 201, 201, 201, 201, 201, 429 ), $codes );
	}

	public function test_m2_per_dealer_daily_cap(): void {
		add_filter( 'wotoyi_mp_setting_lead_dealer_daily_cap', static fn() => 2 );
		add_filter( 'wotoyi_mp_setting_lead_rate_limit', static fn() => 100 );
		$u = $this->vehicle_uuid( Fixtures::dealer() );
		$s = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$s[] = $this->lead( $u, $this->token(), '+22177000020' . $i )->get_status();
		}
		$this->assertSame( array( 201, 201, 429 ), $s );
	}

	public function test_m2_per_contact_daily_cap_stops_harassing_a_number_through_dealers(): void {
		add_filter( 'wotoyi_mp_setting_lead_contact_daily_cap', static fn() => 2 );
		add_filter( 'wotoyi_mp_setting_lead_rate_limit', static fn() => 100 );
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		$c = Fixtures::dealer( 'C' );
		$victim = '+221 77 999 99 99';
		$s      = array();
		foreach ( array( $a, $b, $c ) as $d ) {
			$s[] = $this->lead( $this->vehicle_uuid( $d ), $this->token(), $victim )->get_status();
		}
		$this->assertSame( array( 201, 201, 429 ), $s );
		// Same victim by e-mail with different casing counts as the same contact.
		$res = $this->lead( $this->vehicle_uuid( Fixtures::dealer( 'D' ) ), $this->token(), '', array( 'email' => 'Victim@Example.com' ) );
		$this->assertStatus( 201, $res );
	}

	public function test_m2_challenge_provider_hook(): void {
		$u = $this->vehicle_uuid( Fixtures::dealer() );
		add_filter( 'wotoyi_mp_lead_challenge', static fn( $r, $p ) => ( $p['cf-turnstile-response'] ?? '' ) === 'good' );
		$this->assertStatus( 400, $this->lead( $u, $this->token(), '+221770000031' ), 'provider says no' );
		$this->assertStatus( 201, $this->lead( $u, $this->token(), '+221770000032', array( 'cf-turnstile-response' => 'good' ) ) );
	}

	public function test_m2_challenge_can_be_made_mandatory(): void {
		add_filter( 'wotoyi_mp_setting_lead_challenge_required', static fn() => true );
		$u   = $this->vehicle_uuid( Fixtures::dealer() );
		$res = $this->lead( $u, $this->token() );
		$this->assertStatus( 503, $res );
		$this->assertSame( 0, Fixtures::table_count( 'leads' ) );
	}

	/* =============================== M3 =============================== */

	public function test_m3_forged_requests_naming_a_real_key_id_do_not_throttle_the_dealer(): void {
		$a = Fixtures::dealer();
		for ( $i = 0; $i < 300; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = '198.51.100.' . ( 1 + $i % 12 ); // 25 failures per IP: below the per-IP failure limit.
			$this->assertStatus( 401, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'f' . $i ), null, null, 'wrong-secret' ) );
		}
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev( 'legit' ) ), 'the dealer is not locked out' );
	}

	public function test_m3_authenticated_requests_are_still_rate_limited_per_key(): void {
		add_filter( 'wotoyi_mp_setting_sync_rate_limit', static fn() => 3 );
		$a = Fixtures::dealer();
		$s = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$s[] = Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' )->get_status();
		}
		$this->assertSame( array( 200, 200, 200, 429, 429 ), $s );
	}

	public function test_m3_junk_pairing_codes_cannot_lock_out_a_valid_code(): void {
		[ , $code ] = $this->admin_create( array( 'name' => 'Auto Prestige' ) );
		for ( $i = 0; $i < 100; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = '192.0.2.' . ( $i + 1 ); // 100 sources, 1 miss each: global guard saturated.
			$this->assertStatus( 403, $this->pair( 'WTY-AAAA-BBBB-' . strtoupper( substr( md5( (string) $i ), 0, 4 ) ) ) );
		}
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertStatus( 201, $this->pair( $code ), 'a code that exists is never blocked by the global guard' );
		// The distributed-guessing guard still stops further MISSES.
		$_SERVER['REMOTE_ADDR'] = '192.0.2.250';
		$this->assertStatus( 429, $this->pair( 'WTY-AAAA-BBBB-CCCC' ) );
	}

	/* =============================== M4 =============================== */

	public function test_m4_sync_routes_refuse_plain_http(): void {
		$a = Fixtures::dealer();
		$GLOBALS['wotoyi_is_ssl'] = false;
		$res = Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev() );
		$this->assertStatus( 403, $res );
		$this->assertSame( 'wotoyi_https_required', $res->get_data()['code'] );
		$this->assertStatus( 403, Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/rotate-key', array() ), 'rotate-key returns a clear secret: HTTPS only' );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ) );
		$GLOBALS['wotoyi_is_ssl'] = true;
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'POST', self::EVENTS, $this->ev() ) );
	}

	public function test_m4_pairing_refuses_plain_http_and_keeps_the_code(): void {
		[ , $code ] = $this->admin_create( array( 'name' => 'Auto Prestige' ) );
		$GLOBALS['wotoyi_is_ssl'] = false;
		$res = $this->pair( $code );
		$this->assertStatus( 403, $res );
		$this->assertSame( 'wotoyi_https_required', $res->get_data()['code'] );
		$GLOBALS['wotoyi_is_ssl'] = true;
		$this->assertStatus( 201, $this->pair( $code ), 'the code was not consumed over http' );
	}

	public function test_m4_insecure_transport_only_with_explicit_opt_in_and_never_in_production(): void {
		$a = Fixtures::dealer();
		$GLOBALS['wotoyi_is_ssl'] = false;
		add_filter( 'wotoyi_mp_allow_insecure_transport', static fn() => true );
		$GLOBALS['wotoyi_env'] = 'production';
		$this->assertStatus( 403, Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' ), 'ignored in production' );
		$GLOBALS['wotoyi_env'] = 'local';
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' ), 'allowed for local development' );
	}

	public function test_m4_tls_terminating_proxy_can_vouch_for_the_request(): void {
		$a = Fixtures::dealer();
		$GLOBALS['wotoyi_is_ssl'] = false;
		add_filter( 'wotoyi_mp_request_is_secure', static fn( $ssl ) => true ); // Site owner's own trusted proxy logic.
		$this->assertStatus( 200, Fixtures::call_signed( $a, 'GET', '/wotoyi/v1/sync/health' ) );
	}

	public function test_m4_http_transport_refuses_non_https_base_urls(): void {
		$this->assertThrows( static fn() => new HttpTransport( 'http://marketplace.example.com', 'wk_0123456789abcdef', 's' ), 'https' );
		$this->assertThrows( static fn() => new HttpTransport( 'marketplace.example.com', 'wk_0123456789abcdef', 's' ), 'https' );
		$this->assertThrows( static fn() => new HttpTransport( 'ftp://marketplace.example.com', 'wk_0123456789abcdef', 's' ), 'https' );
		$ok = new HttpTransport( 'https://marketplace.example.com', 'wk_0123456789abcdef', 's' );
		$this->assertStringContains( 'https://marketplace.example.com/wp-json/wotoyi/v1/sync/events', $ok->build( 'POST', '/sync/events', array() )['url'] );
		$GLOBALS['wotoyi_env'] = 'development';
		add_filter( 'wotoyi_mp_allow_insecure_transport', static fn() => true );
		$dev = new HttpTransport( 'http://localhost:8080', 'wk_0123456789abcdef', 's' ); // Local development opt-in.
		$this->assertStringContains( 'http://localhost:8080/wp-json', $dev->build( 'GET', '/sync/health', null )['url'] );
	}

	/* =============================== M5 =============================== */

	public function test_m5_vehicle_documents_are_never_public(): void {
		$a = Fixtures::dealer();
		Fixtures::publish( $a, 'v1', array( 'documents' => array( 'https://cdn.example.com/carte-grise-DK-1234.pdf' ), 'gallery' => array( 'https://cdn.example.com/photo1.jpg' ) ) );
		$uuid = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v1' )['uuid'];
		$blob = json_encode( array( Fixtures::get( '/wotoyi/v1/vehicles/' . $uuid )->get_data(), Fixtures::get( '/wotoyi/v1/vehicles' )->get_data(), Plugin::search()->vehicle_detail( $uuid ) ) );
		$this->assertStringNotContains( 'carte-grise', $blob );
		$this->assertStringNotContains( '"documents"', $blob );
		$this->assertStringContains( 'photo1.jpg', $blob, 'public media still shown' );
		global $wpdb;
		$this->assertStringContains( 'carte-grise', (string) $wpdb->get_var( 'SELECT documents FROM wp_wotoyi_vehicles' ), 'still stored for the dealer side' );
	}

	/* =============================== M7 =============================== */

	public function test_m7_staff_and_security_actions_are_audited_without_secrets(): void {
		[ $uuid, $code ] = $this->admin_create( array( 'name' => 'Auto Prestige' ) ); // Staff user id 1.
		Fixtures::login( 9, array( 'wotoyi_manage_marketplace' ) );
		$rest = static fn( string $path, array $body ) => rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/admin/dealers/' . $uuid . $path, $body ) ) );
		$new  = $rest( '/pairing-code', array() )->get_data()['pairing_code'];
		$paired = $this->pair( $new )->get_data();
		$dealer = array( 'key_id' => $paired['key_id'], 'secret' => $paired['secret'] );
		$this->assertStatus( 200, $rest( '/status', array( 'verified' => true ) ) );
		$this->assertStatus( 200, Fixtures::call_signed( $dealer, 'POST', '/wotoyi/v1/sync/rotate-key', array() ) );
		$this->assertStatus( 200, $rest( '/status', array( 'status' => 'suspended' ) ) );

		$rows    = Plugin::audit()->recent( 100 );
		$actions = array_column( $rows, 'action' );
		foreach ( array( 'dealer.create', 'pairing_code.issue', 'pairing.success', 'dealer.verified', 'key.rotate', 'dealer.status' ) as $expected ) {
			$this->assertContains( $expected, $actions, $expected );
		}
		$by = array_column( $rows, null, 'action' );
		$this->assertSame( 'staff', $by['dealer.status']['actor_type'] );
		$this->assertSame( '9', (string) $by['dealer.status']['actor_user_id'] );
		$this->assertSame( array( 'active', 'suspended' ), $by['dealer.status']['details']['status'] );
		$this->assertSame( array( false, true ), $by['dealer.verified']['details']['verified'] );
		$this->assertSame( 'dealer', $by['key.rotate']['actor_type'] );
		$this->assertSame( 64, strlen( (string) $by['dealer.status']['ip_hash'] ) );
		$this->assertTrue( '' !== (string) $by['dealer.status']['correlation_id'] );

		$all = json_encode( $rows );
		foreach ( array( $code, $new, $paired['secret'], $dealer['secret'], '203.0.113.7' ) as $secret ) {
			$this->assertStringNotContains( $secret, $all, 'no secret, code or raw IP in the audit log' );
		}
		$d = Plugin::dealers()->find_by_uuid( $uuid );
		$this->assertSame( 'suspended', $d['status'] );

		Plugin::sync_log(); // Disconnect through the signed API as well.
		$d2 = Fixtures::dealer( 'Other' );
		$this->assertStatus( 200, Fixtures::call_signed( $d2, 'POST', '/wotoyi/v1/sync/disconnect', array() ) );
		$this->assertContains( 'dealer.disconnect', array_column( Plugin::audit()->recent( 100 ), 'action' ) );
	}

	public function test_m7_audit_log_endpoint_needs_the_staff_capability_and_has_no_mutators(): void {
		[ $uuid ] = $this->admin_create( array( 'name' => 'Auto Prestige' ) );
		$res = Fixtures::get( '/wotoyi/v1/admin/audit-log', array( 'dealer' => $uuid ) );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'dealer.create', $res->get_data()['items'][0]['action'] );
		Fixtures::login( 5, array( 'read' ) );
		$this->assertStatus( 403, Fixtures::get( '/wotoyi/v1/admin/audit-log' ) );
		$GLOBALS['wotoyi_user'] = array( 'id' => 0, 'caps' => array() );
		$this->assertStatus( 401, Fixtures::get( '/wotoyi/v1/admin/audit-log' ) );
		$methods = array_map( static fn( ReflectionMethod $m ) => $m->getName(), ( new ReflectionClass( \Wotoyi\Marketplace\Repository\AuditRepository::class ) )->getMethods( ReflectionMethod::IS_PUBLIC ) );
		sort( $methods );
		$this->assertSame( array( 'prune', 'recent', 'record', 'table' ), $methods, 'append-only: no update/delete-by-id API' );
	}

	public function test_m7_audit_details_are_whitelisted(): void {
		Plugin::audit()->record( 'staff', 3, 'dealer.status', null, array( 'status' => 'x', 'secret' => 'S3CR3T', 'pairing_code' => 'WTY-AAAA', 'email' => 'a@b.co', 'verified' => true ) );
		$row = Plugin::audit()->recent( 1 )[0];
		$this->assertSame( array( 'status', 'verified' ), array_keys( $row['details'] ) );
	}

	/* =============================== L1 =============================== */

	public function test_l1_pairing_cannot_omit_the_bound_dealer_identity(): void {
		[ , $code ] = $this->admin_create( array( 'name' => 'Bound', 'source_dealer_uuid' => 'expected-dealer-01' ) );
		$res = rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/sync/pair', array( 'code' => $code, 'website_url' => 'https://phishing.example' ) ) );
		$this->assertStatus( 403, $res, 'a code issued for a bound identity needs that identity' );
		$this->assertSame( 0, Fixtures::table_count( 'dealers', 'key_id IS NOT NULL' ) );
		$this->assertStatus( 201, $this->pair( $code, array( 'source_dealer_uuid' => 'expected-dealer-01' ) ), 'the code was not burned by the failed attempt' );
	}

	public function test_l1_pairing_cannot_overwrite_the_public_profile(): void {
		[ $uuid, $code ] = $this->admin_create( array( 'name' => 'Bound', 'source_dealer_uuid' => 'expected-dealer-01', 'website_url' => 'https://bound.example', 'phone' => '+221 33 111 11 11' ) );
		$res = $this->pair( $code, array( 'source_dealer_uuid' => 'expected-dealer-01', 'website_url' => 'https://phishing.example', 'phone' => '+000', 'logo_url' => 'https://cdn.example/logo.png', 'name' => 'Hijacked' ) );
		$this->assertStatus( 201, $res );
		$d = Plugin::dealers()->find_by_uuid( $uuid );
		$this->assertSame( 'https://bound.example', $d['website_url'], 'existing value kept' );
		$this->assertSame( '+221 33 111 11 11', $d['phone'] );
		$this->assertSame( 'Bound', $d['name'] );
		$this->assertSame( 'https://cdn.example/logo.png', $d['logo_url'], 'empty field may be filled by the platform' );
	}
}
