<?php

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\LeadGuard;

final class AccountTest extends Wotoyi_Test_Case {

	private array $A;
	private string $uuid;

	protected function setUp(): void {
		parent::setUp();
		$this->A = Fixtures::dealer( 'Dealer A' );
		Fixtures::publish( $this->A, 'v1', array(), 1 );
		$this->uuid = Plugin::vehicles()->find_by_source( (int) $this->A['row']['id'], 'v1' )['uuid'];
	}

	private function lead( array $over = array(), bool $token = true ): WP_REST_Response {
		$body = $over + array( 'vehicle' => $this->uuid, 'name' => 'Awa', 'phone' => '+221 77 000 00 00', 'consent' => true, 'message' => 'Disponible ?' );
		if ( $token && ! array_key_exists( 'token', $body ) ) {
			$body['token'] = LeadGuard::issue();
			Fixtures::tick( 5 );
		}
		return rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', $body ) );
	}

	/* ---------------------------- leads ---------------------------- */

	public function test_lead_is_stored_for_the_vehicles_dealer_without_raw_ip(): void {
		$res = $this->lead();
		$this->assertStatus( 201, $res );
		$this->assertSame( 1, Fixtures::table_count( 'leads', 'dealer_id = ' . (int) $this->A['row']['id'] ) );
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT * FROM wp_wotoyi_leads' );
		$this->assertSame( '+221 77 000 00 00', $row['phone'] );
		$this->assertSame( 'pending', $row['delivery_status'] );
		$this->assertStringNotContains( '203.0.113.7', json_encode( $row ), 'raw IP is not stored' );
		$this->assertSame( 64, strlen( $row['ip_hash'] ) );
	}

	public function test_lead_requires_token_consent_target_and_contact(): void {
		$this->assertStatus( 400, $this->lead( array(), false ), 'no token' );
		$this->assertStatus( 400, $this->lead( array( 'token' => 'forged.' . str_repeat( 'a', 32 ) ), false ) );
		$tok = LeadGuard::issue();
		$this->assertStatus( 400, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', array( 'token' => $tok, 'vehicle' => $this->uuid, 'phone' => '+221770000000', 'consent' => true ) ) ), 'token too fresh (bot speed)' );
		Fixtures::tick( 10000 );
		$this->assertStatus( 400, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', array( 'token' => $tok, 'vehicle' => $this->uuid, 'phone' => '+221770000000', 'consent' => true ) ) ), 'token expired' );
		$this->assertStatus( 422, $this->lead( array( 'consent' => false ) ), 'consent' );
		$this->assertStatus( 422, $this->lead( array( 'vehicle' => 'nope-nope' ) ), 'unknown vehicle' );
		$this->assertStatus( 422, $this->lead( array( 'phone' => '', 'email' => '' ) ), 'no contact' );
		$this->assertStatus( 422, $this->lead( array( 'phone' => '12' ) ), 'short phone' );
		$this->assertStatus( 422, $this->lead( array( 'phone' => '', 'email' => 'not-an-email' ) ), 'bad email' );
		$this->assertSame( 0, Fixtures::table_count( 'leads' ) );
		Fixtures::tick( 1300 ); // Rejected attempts also count toward the per-IP limit; wait for a fresh window.
		$this->assertStatus( 201, $this->lead( array( 'phone' => '', 'email' => 'buyer@example.com' ) ) );
	}

	public function test_lead_honeypot_silently_discards_bots(): void {
		$res = $this->lead( array( 'website' => 'http://spam.example' ) );
		$this->assertStatus( 201, $res );
		$this->assertSame( 0, Fixtures::table_count( 'leads' ), 'bot gets success, nothing stored' );
	}

	public function test_lead_rate_limit(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertStatus( 201, $this->lead() );
		}
		$this->assertStatus( 429, $this->lead() );
		$this->assertSame( 5, Fixtures::table_count( 'leads' ) );
	}

	public function test_lead_for_unpublished_vehicle_or_suspended_dealer_is_refused(): void {
		Plugin::dealers()->set_status( (int) $this->A['row']['id'], 'suspended' );
		$this->assertStatus( 422, $this->lead() );
	}

	public function test_lead_text_is_sanitised(): void {
		$this->assertStatus( 201, $this->lead( array( 'name' => '<script>x</script>Awa', 'message' => '<img src=x onerror=alert(1)>Hello' ) ) );
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT * FROM wp_wotoyi_leads' );
		$this->assertStringNotContains( '<', $row['name'] . $row['message'] );
	}

	public function test_lead_for_service_goes_to_the_service_dealer(): void {
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'service.published', 's1', 1, array( 'title' => 'Vidange', 'category' => 'maintenance' ) ) );
		$suuid = Plugin::services()->find_by_source( (int) $this->A['row']['id'], 's1' )['uuid'];
		$this->assertStatus( 201, $this->lead( array( 'vehicle' => null, 'service' => $suuid ) ) );
		global $wpdb;
		$this->assertNotNull( $wpdb->get_var( 'SELECT service_id FROM wp_wotoyi_leads' ) );
	}

	public function test_lead_token_endpoint_is_not_cacheable(): void {
		$res = Fixtures::get( '/wotoyi/v1/leads/token' );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'no-store', $res->get_headers()['Cache-Control'] );
		$this->assertTrue( LeadGuard::issue() !== '' );
	}

	/* -------------------------- favourites -------------------------- */

	public function test_account_routes_require_login(): void {
		$this->assertStatus( 401, Fixtures::get( '/wotoyi/v1/me/favourites' ) );
		$this->assertStatus( 401, Fixtures::get( '/wotoyi/v1/me/saved-searches' ) );
		$this->assertStatus( 401, rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => $this->uuid ) ) ) ) );
	}

	public function test_account_writes_require_a_valid_rest_nonce(): void {
		Fixtures::login( 7 );
		$res = rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => $this->uuid ) ) );
		$this->assertStatus( 403, $res );
		$this->assertSame( 'rest_cookie_invalid_nonce', $res->get_data()['code'] );
		$this->assertSame( 0, Fixtures::table_count( 'favourites' ) );
		$GLOBALS['wotoyi_did']['application_password_did_authenticate'] = 1;
		$this->assertStatus( 201, rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => $this->uuid ) ) ), 'app-password auth needs no cookie nonce' );
	}

	public function test_favourites_add_list_remove_and_idempotency(): void {
		Fixtures::login( 7 );
		$add = fn() => rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => $this->uuid ) ) ) );
		$this->assertStatus( 201, $add() );
		$this->assertStatus( 201, $add() );
		$this->assertSame( 1, Fixtures::table_count( 'favourites' ) );
		$list = Fixtures::get( '/wotoyi/v1/me/favourites' )->get_data()['vehicles'];
		$this->assertCount( 1, $list );
		$this->assertSame( $this->uuid, $list[0]['uuid'] );
		$del = fn() => rest_do_request( Fixtures::with_nonce( Fixtures::request( 'DELETE', '/wotoyi/v1/me/favourites/vehicle/' . $this->uuid ) ) );
		$this->assertStatus( 200, $del() );
		$this->assertStatus( 200, $del() );
		$this->assertSame( 0, Fixtures::table_count( 'favourites' ) );
		$this->assertStatus( 404, rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => 'ghost-ghost' ) ) ) ) );
	}

	public function test_favourites_are_per_user_and_hidden_when_vehicle_unpublished(): void {
		Fixtures::login( 7 );
		rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/favourites', array( 'ref' => $this->uuid ) ) ) );
		Fixtures::login( 8 );
		$this->assertCount( 0, Fixtures::get( '/wotoyi/v1/me/favourites' )->get_data()['vehicles'] );
		Fixtures::login( 7 );
		$this->assertCount( 1, Fixtures::get( '/wotoyi/v1/me/favourites' )->get_data()['vehicles'] );
		Plugin::receiver()->handle_event( $this->A['row'], Fixtures::event( 'vehicle.unpublished', 'v1', 2 ) );
		$this->assertCount( 0, Fixtures::get( '/wotoyi/v1/me/favourites' )->get_data()['vehicles'] );
	}

	public function test_favourites_limit(): void {
		Fixtures::login( 7 );
		$repo = Plugin::favourites();
		for ( $i = 1; $i <= 500; $i++ ) {
			$repo->add( 7, 'vehicle', $i );
		}
		$this->assertFalse( $repo->add( 7, 'vehicle', 501 ) );
		$this->assertSame( 500, $repo->count( 7 ) );
		$this->assertFalse( $repo->add( 7, 'hacker', 1 ), 'unknown item type' );
	}

	/* ------------------------ saved searches ------------------------ */

	public function test_saved_searches_store_normalised_filters_only(): void {
		Fixtures::login( 7 );
		$res = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/saved-searches', array(
			'name'    => 'SUV diesel <b>Dakar</b>',
			'filters' => array( 'body_type' => 'suv', 'fuel' => 'diesel', 'price_max' => '15000000', 'evil' => 'x', 'page' => 3 ),
			'notify'  => true,
		) ) ) );
		$this->assertStatus( 201, $res );
		$this->assertSame( array( 'body_type' => array( 'suv' ), 'fuel' => array( 'diesel' ), 'price_max' => 15000000 ), $res->get_data()['filters'] );
		$list = Fixtures::get( '/wotoyi/v1/me/saved-searches' )->get_data()['items'];
		$this->assertCount( 1, $list );
		$this->assertSame( 'SUV diesel Dakar', $list[0]['name'] );
		$this->assertTrue( $list[0]['notify'] );
		$this->assertSame( array( 'suv' ), $list[0]['filters']['body_type'] );
		// A stored search can be replayed against the engine (no vehicle matches SUV diesel under 15M here).
		$this->assertSame( 0, Plugin::search()->count( $list[0]['filters'] ) );
	}

	public function test_saved_search_validation_duplicates_and_limit(): void {
		Fixtures::login( 7 );
		$post = fn( array $b ) => rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/saved-searches', $b ) ) );
		$this->assertStatus( 422, $post( array( 'filters' => array( 'make' => 'Toyota' ) ) ), 'name required' );
		$this->assertStatus( 422, $post( array( 'name' => 'Empty', 'filters' => array( 'bogus' => 1 ) ) ), 'filters must contain something valid' );
		$this->assertStatus( 201, $post( array( 'name' => 'Toyota', 'filters' => array( 'make' => 'Toyota' ) ) ) );
		$this->assertStatus( 409, $post( array( 'name' => 'Toyota again', 'filters' => array( 'make' => 'Toyota' ) ) ), 'same filters twice' );
		for ( $i = 1; $i < 20; $i++ ) {
			$this->assertStatus( 201, $post( array( 'name' => "S$i", 'filters' => array( 'price_max' => $i * 1000000 ) ) ) );
		}
		$this->assertStatus( 409, $post( array( 'name' => 'Too many', 'filters' => array( 'price_max' => 99000000 ) ) ), '20 per user' );
	}

	public function test_users_cannot_delete_each_others_saved_searches(): void {
		Fixtures::login( 7 );
		$id = rest_do_request( Fixtures::with_nonce( Fixtures::request( 'POST', '/wotoyi/v1/me/saved-searches', array( 'name' => 'Mine', 'filters' => array( 'make' => 'Kia' ) ) ) ) )->get_data()['id'];
		Fixtures::login( 8 );
		$this->assertStatus( 404, rest_do_request( Fixtures::with_nonce( Fixtures::request( 'DELETE', "/wotoyi/v1/me/saved-searches/$id" ) ) ) );
		$this->assertSame( 1, Fixtures::table_count( 'saved_searches' ) );
		Fixtures::login( 7 );
		$this->assertStatus( 200, rest_do_request( Fixtures::with_nonce( Fixtures::request( 'DELETE', "/wotoyi/v1/me/saved-searches/$id" ) ) ) );
		$this->assertSame( 0, Fixtures::table_count( 'saved_searches' ) );
	}
}
