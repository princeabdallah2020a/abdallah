<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\LeadGuard;

final class SecurityTest extends Wotoyi_Test_Case {

	private const PAYLOADS = array(
		"' OR '1'='1",
		"'; DROP TABLE wp_wotoyi_vehicles; --",
		'" OR 1=1 --',
		'1 UNION SELECT secret_enc FROM wp_wotoyi_dealers',
		"\\' OR 1=1 #",
		"%' AND SLEEP(5) --",
		"x') OR 1=1 --",
	);

	/** Remove quoted string literals; whatever remains is SQL structure. */
	private function structure( string $sql ): string {
		return preg_replace( "/'(?:[^']|'')*'/", "''", $sql );
	}

	private function assert_no_injection( string $where ): void {
		global $wpdb;
		foreach ( $wpdb->queries as $q ) {
			$s = $this->structure( $q );
			$this->assertFalse( (bool) preg_match( '/\b(DROP|UNION|SLEEP|BENCHMARK|INFORMATION_SCHEMA|secret_enc)\b/i', $s ), "$where: injected keyword in: " . substr( $q, 0, 160 ) );
			$this->assertFalse( (bool) preg_match( '/\bOR\s+1\s*=\s*1/i', $s ), "$where: tautology in: " . substr( $q, 0, 160 ) );
			$this->assertFalse( (bool) preg_match( '/--|#/', $s ), "$where: comment marker outside literal in: " . substr( $q, 0, 160 ) );
		}
	}

	public function test_no_user_value_ever_becomes_sql_structure(): void {
		global $wpdb;
		$a = Fixtures::dealer( 'A', array(), true );
		Fixtures::publish( $a, 'v1', array(), 1 );
		Fixtures::publish( $a, 'v2', array( 'title' => "Inj'ected", 'make' => "O'Brien", 'city' => "L'Aquila" ), 1 );
		Fixtures::publish( $a, 's1', array(), 1 ); // sanity
		$wpdb->queries = array();

		foreach ( self::PAYLOADS as $p ) {
			$params = array( 'q' => $p, 'make' => $p, 'model' => $p, 'trim' => $p, 'city' => $p, 'area' => $p, 'dealer' => $p, 'equipment' => $p, 'sort' => $p, 'lat' => $p, 'lng' => $p, 'page' => $p, 'per_page' => $p, 'category' => $p, 'price_max' => $p, 'doors' => $p, 'fuel' => $p, 'facets' => $p );
			Plugin::search()->search( $params );
			Plugin::search()->count( $params );
			Plugin::search()->facets( $params );
			Plugin::search()->search_services( $params );
			Plugin::search()->vehicle_detail( $p );
			Plugin::search()->service_detail( $p );
			Plugin::dealers()->public_by_ref( $p );
			Plugin::dealers()->public_list( array( 'q' => $p, 'city' => $p ) );
			Plugin::dealers()->find_by_ref( $p );
			Plugin::dealers()->find_by_key_id( $p );
			Plugin::dealers()->find_by_pairing_code( $p );
			Plugin::sync_log()->find( 1, $p );
			Plugin::receiver()->handle_event( $a['row'], Fixtures::event( 'vehicle.published', 'inj-' . substr( md5( $p ), 0, 8 ), 1, Fixtures::vehicle( array( 'make' => $p, 'model' => $p, 'city' => $p, 'title' => $p, 'description' => $p ) ) ) );
			Plugin::receiver()->handle_event( $a['row'], array( 'event_id' => $p, 'event_type' => $p, 'data' => array( 'source_id' => $p ) ) );
			Plugin::receiver()->ack_leads( $a['row'], array( $p ) );
			foreach ( array( '/wotoyi/v1/vehicles', '/wotoyi/v1/services', '/wotoyi/v1/dealers', '/wotoyi/v1/facets', '/wotoyi/v1/vehicles/count' ) as $route ) {
				rest_do_request( Fixtures::request( 'GET', $route, null, $params + array( 'verified' => $p ) ) );
			}
			rest_do_request( Fixtures::request( 'GET', '/wotoyi/v1/vehicles/' . rawurlencode( $p ) ) ); // Route regex rejects it.
		}
		$this->assert_no_injection( 'public/receiver flows' );
		$this->assertGreaterThanOrEqual( 100, count( $wpdb->queries ), 'the check actually exercised many queries' );
		$this->assertGreaterThanOrEqual( 2, Fixtures::table_count( 'vehicles' ), 'tables intact' );
		$this->assertSame( 1, Fixtures::table_count( 'dealers' ) );
	}

	public function test_account_and_lead_flows_are_injection_safe(): void {
		global $wpdb;
		$a = Fixtures::dealer( 'A' );
		Fixtures::publish( $a, 'v1', array(), 1 );
		$uuid = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v1' )['uuid'];
		Fixtures::login( 7 );
		$wpdb->queries = array();
		foreach ( self::PAYLOADS as $p ) {
			rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/saved-searches', array( 'name' => $p, 'filters' => array( 'make' => $p, 'q' => $p ) ) ) ) );
			rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => $p, 'type' => $p ) ) ) );
			Fixtures::tick( 700 );
			rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', array( 'token' => LeadGuard::issue(), 'vehicle' => $uuid, 'name' => $p, 'phone' => '+221770000000', 'email' => 'a@b.co', 'message' => $p, 'channel' => $p, 'locale' => $p, 'consent' => true ) ) );
			Fixtures::tick( 5 );
		}
		$this->assert_no_injection( 'account/lead flows' );
		$this->assertGreaterThanOrEqual( 1, Fixtures::table_count( 'saved_searches' ) );
	}

	public function test_stored_xss_payloads_never_come_back_as_markup(): void {
		$a = Fixtures::dealer( 'A' );
		$x = '<script>alert(1)</script><img src=x onerror=alert(2)><svg onload=alert(3)>';
		Fixtures::publish( $a, 'v1', array( 'title' => $x, 'make' => $x, 'model' => $x . 'M', 'city' => $x, 'description' => $x . '<p>ok</p>', 'accident_notes' => $x, 'charge_ports' => $x, 'cover_image' => 'javascript:alert(1)', 'gallery' => array( 'javascript:alert(1)', 'https://ok.test/a.jpg' ), 'description_i18n' => array( 'fr' => $x ) ), 1 );
		$uuid = Plugin::vehicles()->find_by_source( (int) $a['row']['id'], 'v1' )['uuid'];
		$blob = json_encode( array( Plugin::search()->search( array() ), Plugin::search()->vehicle_detail( $uuid ), Fixtures::get( '/wotoyi/v1/vehicles/' . $uuid )->get_data(), Plugin::search()->facets( array() ) ) );
		foreach ( array( '<script', '<img', '<svg', 'onerror', 'onload', 'javascript:' ) as $bad ) {
			$this->assertStringNotContains( $bad, $blob, $bad );
		}
		$this->assertStringContains( 'ok', $blob );
	}

	public function test_internal_errors_do_not_leak_sql_and_are_retryable(): void {
		global $wpdb;
		ini_set( 'error_log', '/dev/null' );
		$a  = Fixtures::dealer();
		$ev = Fixtures::event( 'vehicle.published', 'v1', 1, Fixtures::vehicle() );
		$wpdb->pdo->exec( 'ALTER TABLE wp_wotoyi_vehicles RENAME TO wp_wotoyi_vehicles_gone' );
		$this->allow_db_errors = true;
		$res = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/events', $ev );
		$this->assertStatus( 500, $res );
		$blob = json_encode( $res->get_data() );
		$this->assertStringNotContains( 'wp_wotoyi', $blob );
		$this->assertStringNotContains( 'SQL', $blob );
		$this->assertSame( 'internal_error', $res->get_data()['code'] );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log', "status = 'failed'" ) );
		$wpdb->pdo->exec( 'ALTER TABLE wp_wotoyi_vehicles_gone RENAME TO wp_wotoyi_vehicles' );
		$retry = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/events', $ev );
		$this->assertStatus( 200, $retry, 'same event id succeeds after the fault is fixed' );
		$this->assertSame( 'applied', $retry->get_data()['status'] );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log' ) );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ) );
	}

	public function test_upsert_failure_rolls_back_atomically(): void {
		global $wpdb;
		ini_set( 'error_log', '/dev/null' );
		$a = Fixtures::dealer();
		$this->allow_db_errors = true;
		// Break only the equipment child table: the vehicle insert must roll back with it.
		$wpdb->pdo->exec( 'DROP TABLE wp_wotoyi_vehicle_equipment' );
		$res = Plugin::receiver()->handle_event( $a['row'], Fixtures::event( 'vehicle.published', 'v1', 1, Fixtures::vehicle( array( 'equipment' => array( 'comfort' => array( 'air_conditioning' ) ) ) ) ) );
		$this->assertSame( 'failed', $res['status'] );
		$this->assertSame( 0, Fixtures::table_count( 'vehicles' ), 'no half-written listing' );
	}

	public function test_hmac_comparison_uses_constant_time_function_and_no_weak_primitives(): void {
		$auth = file_get_contents( WOTOYI_MP_DIR . 'includes/Sync/Authenticator.php' );
		$this->assertStringContains( 'hash_equals', $auth );
		$all = '';
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WOTOYI_MP_DIR . 'includes' ) ) as $f ) {
			if ( str_ends_with( (string) $f, '.php' ) ) {
				$all .= file_get_contents( (string) $f );
			}
		}
		foreach ( array( 'md5(', 'sha1(', 'mt_rand(', ' rand(', 'uniqid(' ) as $weak ) {
			$this->assertStringNotContains( $weak, $all, "weak primitive $weak" );
		}
	}

	public function test_tenant_isolation_matrix_a_cannot_touch_b_through_any_channel(): void {
		$a = Fixtures::dealer( 'A' );
		$b = Fixtures::dealer( 'B' );
		Fixtures::publish( $b, 'secret-car', array( 'price' => 777 ), 1 );
		Plugin::receiver()->handle_event( $b['row'], Fixtures::event( 'service.published', 'secret-svc', 1, array( 'title' => 'B service' ) ) );
		$bid  = (int) $b['row']['id'];
		$snap = fn() => json_encode( array( Plugin::vehicles()->find_by_source( $bid, 'secret-car' ), Plugin::services()->find_by_source( $bid, 'secret-svc' ) ) );
		$before = $snap();

		// 1. HTTP events/wrappers with the same source ids.
		foreach ( array( 'vehicle.updated', 'vehicle.unpublished', 'vehicle.sold', 'vehicle.deleted' ) as $t ) {
			Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/events', Fixtures::event( $t, 'secret-car', 99, Fixtures::vehicle( array( 'price' => 1 ) ) ) );
		}
		foreach ( array( 'service.updated', 'service.unpublished', 'service.deleted' ) as $t ) {
			Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/events', Fixtures::event( $t, 'secret-svc', 99, array( 'title' => 'x' ) ) );
		}
		Fixtures::call_signed( $a, 'PUT', '/wotoyi/v1/sync/vehicles/secret-car', Fixtures::vehicle( array( 'version' => 100 ) ) );
		Fixtures::call_signed( $a, 'DELETE', '/wotoyi/v1/sync/vehicles/secret-car', array( 'version' => 101 ) );
		Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/batch', array( 'events' => array( Fixtures::event( 'vehicle.deleted', 'secret-car', 102 ) ) ) );
		// 2. Spoofed identity in payload and headers.
		Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/events', Fixtures::event( 'vehicle.deleted', 'secret-car', 103 ) + array( 'dealer_uuid' => $b['row']['uuid'] ) );
		$spoof = Fixtures::signed( $a, 'POST', '/wotoyi/v1/sync/events', Fixtures::event( 'vehicle.deleted', 'secret-car', 104 ) );
		$spoof->set_header( 'X-Wotoyi-Dealer', $b['row']['uuid'] );
		$spoof->set_param( '_wotoyi_dealer', $b['row'] );
		$spoof->set_param( 'dealer_id', $bid );
		rest_do_request( $spoof );

		$this->assertSame( $before, $snap(), 'B rows byte-identical after every attack by A' );
		// 3. Leads of B are invisible to A.
		$bv = Plugin::search()->published_vehicle_ref( Plugin::vehicles()->find_by_source( $bid, 'secret-car' )['uuid'] );
		Plugin::leads()->create( array( 'dealer_id' => $bv['dealer_id'], 'vehicle_id' => $bv['id'], 'email' => 'x@y.zz', 'consent' => true ) );
		$leads = Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/leads/pull', array( 'limit' => 100 ) );
		$this->assertSame( array(), $leads->get_data()['leads'] );
		// 4. A's sync log only shows A's events.
		$this->assertSame( 0, Fixtures::table_count( 'sync_log', 'dealer_id = ' . $bid . " AND event_type <> 'service.published' AND event_type <> 'vehicle.published'" ) );
	}
}
