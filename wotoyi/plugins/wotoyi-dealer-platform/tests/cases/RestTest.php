<?php
use Wotoyi\DealerPlatform\Auth\Roles;
use Wotoyi\DealerPlatform\Plugin;

final class RestTest extends Wotoyi_Test_Case {

	private array $a;
	private array $b;
	private array $users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->a = Fixtures::tenant( 'Dealer A', 11 );
		$this->b = Fixtures::tenant( 'Dealer B', 12 );
		$n = 20;
		foreach ( \Wotoyi\DealerPlatform\Schema\Entities::ROLES as $role ) {
			if ( 'owner' === $role ) {
				$this->users[ $role ] = 11;
				continue;
			}
			Fixtures::member( $this->a, $n, $role );
			$this->users[ $role ] = $n++;
		}
	}

	private function as( string $role ): void {
		Fixtures::login( $this->users[ $role ] );
	}

	public function test_every_route_has_a_real_permission_callback_and_rejects_anonymous_callers(): void {
		$seen = 0;
		foreach ( $GLOBALS['wotoyi_routes'] as $r ) {
			if ( ! str_starts_with( $r['pattern'], '/wotoyi-pro/v1' ) ) {
				continue;
			}
			++$seen;
			$perm = $r['args']['permission_callback'] ?? null;
			$this->assertTrue( is_callable( $perm ), $r['pattern'] );
			$this->assertTrue( 'core::__return_true' !== $perm && '__return_true' !== $perm, $r['pattern'] );
			Fixtures::login( 0 );
			foreach ( array_map( 'trim', explode( ',', $r['args']['methods'] ) ) as $m ) {
				$req = new WP_REST_Request( $m, '/x' );
				$res = $perm( $req );
				$this->assertTrue( is_wp_error( $res ) && 401 === $res->get_error_data()['status'], "{$m} {$r['pattern']} must answer 401 to anonymous callers" );
			}
		}
		$this->assertGreaterThanOrEqual( 70, $seen );
	}

	public function test_user_without_membership_gets_403_and_platform_admin_sees_no_tenant_data(): void {
		Fixtures::user( 99 );
		Fixtures::login( 99 );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/vehicles' ) );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/dashboard' ) );
		Fixtures::platform_admin( 1 );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/vehicles' ), 'a WordPress administrator has no automatic access to dealer data' );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/platform/tenants' ) );
		$list = Fixtures::call( 'GET', '/platform/tenants' )->get_data()['items'];
		$this->assertCount( 2, $list );
		$this->assertArrayNotHasKey( 'settings', $list[0] );
	}

	public function test_platform_routes_need_the_platform_capability_and_nonce(): void {
		Fixtures::login( 11 ); // A dealer owner.
		$this->assertStatus( 403, Fixtures::call( 'POST', '/platform/tenants', array( 'name' => 'X', 'owner_user_id' => 11 ) ) );
		Fixtures::platform_admin( 1 );
		Fixtures::user( 77 );
		$this->assertStatus( 403, Fixtures::call( 'POST', '/platform/tenants', array( 'name' => 'New Dealer', 'owner_user_id' => 77 ), array(), array(), false ), 'nonce required' );
		$res = Fixtures::call( 'POST', '/platform/tenants', array( 'name' => 'New Dealer', 'owner_user_id' => 77 ) );
		$this->assertStatus( 201, $res );
		$this->assertSame( 'new-dealer', $res->get_data()['slug'] );
		$this->assertStatus( 422, Fixtures::call( 'POST', '/platform/tenants', array( 'name' => 'Ghost', 'owner_user_id' => 4242 ) ) );
		$this->assertStatus( 422, Fixtures::call( 'POST', '/platform/tenants', array( 'owner_user_id' => 77 ) ) );
	}

	public function test_writes_require_the_rest_nonce(): void {
		$this->as( 'owner' );
		$this->assertStatus( 403, Fixtures::call( 'POST', '/customers', array( 'name' => 'x', 'phone' => '+221770000001' ), array(), array(), false ) );
		$this->assertStatus( 201, Fixtures::call( 'POST', '/customers', array( 'name' => 'x', 'phone' => '+221770000001' ) ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array(), array(), false ), 'reads need no nonce' );
	}

	/** @return array<string,array<string,int>> expected status per role for representative operations */
	public function test_role_capability_matrix_over_http(): void {
		$this->as( 'owner' );
		$veh  = Fixtures::call( 'POST', '/vehicles', Fixtures::vehicle( array( 'status' => 'published' ) ) )->get_data();
		$cust = Fixtures::call( 'POST', '/customers', array( 'name' => 'C', 'phone' => '+221770000070' ) )->get_data();
		$vid  = $veh['id'];
		$ops  = array(
			'vehicles.list'      => array( 'GET', '/vehicles', null ),
			'vehicles.create'    => array( 'POST', '/vehicles', array( 'make' => 'Kia', 'model' => 'Rio' ) ),
			'vehicles.publish'   => array( 'POST', "/vehicles/{$vid}/unpublish", null ),
			'vehicles.delete'    => array( 'DELETE', '/vehicles/00000000-0000-0000-0000-000000000000', null ),
			'customers.list'     => array( 'GET', '/customers', null ),
			'customers.create'   => array( 'POST', '/customers', array( 'name' => 'N', 'phone' => '+221770000071' ) ),
			'customers.erase'    => array( 'POST', "/customers/{$cust['id']}/erase", null ),
			'leads.create'       => array( 'POST', '/leads', array( 'name' => 'L' ) ),
			'deals.list'         => array( 'GET', '/deals', null ),
			'employees.list'     => array( 'GET', '/employees', null ),
			'members.list'       => array( 'GET', '/members', null ),
			'members.manage'     => array( 'POST', '/members', array( 'user_id' => 1, 'role' => 'sales' ) ),
			'audit'              => array( 'GET', '/audit', null ),
			'sync.status'        => array( 'GET', '/sync/status', null ),
			'sync.run'           => array( 'POST', '/sync/run', null ),
			'tenant.update'      => array( 'PATCH', '/tenant', array( 'city' => 'Thies' ) ),
			'services.create'    => array( 'POST', '/services', array( 'title' => 'Wash' ) ),
			'dashboard'          => array( 'GET', '/dashboard', null ),
		);
		// Expectation: allowed => not 401/403; denied => 403.
		$allowed = array(
			'owner'      => array_keys( $ops ),
			'manager'    => array( 'vehicles.list', 'vehicles.create', 'vehicles.publish', 'vehicles.delete', 'customers.list', 'customers.create', 'leads.create', 'deals.list', 'employees.list', 'members.list', 'audit', 'sync.status', 'sync.run', 'services.create', 'dashboard' ),
			'sales'      => array( 'vehicles.list', 'vehicles.create', 'vehicles.publish', 'customers.list', 'customers.create', 'leads.create', 'deals.list', 'sync.status', 'dashboard' ),
			'mechanic'   => array( 'vehicles.list', 'customers.list', 'services.create', 'dashboard' ),
			'accountant' => array( 'vehicles.list', 'customers.list', 'deals.list', 'audit', 'sync.status', 'dashboard' ),
		);
		foreach ( $allowed as $role => $ok ) {
			$this->as( $role );
			foreach ( $ops as $name => [ $method, $route, $body ] ) {
				if ( 'owner' === $role && in_array( $name, array( 'vehicles.delete', 'members.manage', 'customers.erase', 'sync.run' ), true ) ) {
					continue; // Covered elsewhere (404/422/side effects); the denial side is what this matrix asserts for owners.
				}
				$res = Fixtures::call( $method, $route, $body );
				if ( in_array( $name, $ok, true ) ) {
					$this->assertTrue( ! in_array( $res->get_status(), array( 401, 403 ), true ), "{$role} should be allowed {$name}, got {$res->get_status()}" );
				} else {
					$this->assertStatus( 403, $res, "{$role} must be denied {$name}" );
				}
			}
		}
	}

	public function test_status_changes_through_patch_need_the_matching_capability(): void {
		$this->as( 'owner' );
		$v = Fixtures::call( 'POST', '/vehicles', Fixtures::vehicle( array( 'status' => 'published' ) ) )->get_data();
		$this->as( 'mechanic' );
		$this->assertStatus( 403, Fixtures::call( 'PATCH', "/vehicles/{$v['id']}", array( 'price' => 1 ) ) );
		$this->as( 'sales' );
		$this->assertStatus( 200, Fixtures::call( 'PATCH', "/vehicles/{$v['id']}", array( 'price' => 14000000 ) ) );
		$this->assertStatus( 403, Fixtures::call( 'PATCH', "/vehicles/{$v['id']}", array( 'status' => 'deleted' ) ), 'sales cannot delete' );
		$this->assertStatus( 200, Fixtures::call( 'PATCH', "/vehicles/{$v['id']}", array( 'status' => 'unpublished' ) ) );
		$res = Fixtures::call( 'POST', "/vehicles/{$v['id']}/sold", array( 'amount' => 13000000 ) );
		$this->assertStatus( 201, $res, json_encode( $res->get_data() ) );
		$this->assertStatus( 409, Fixtures::call( 'POST', "/vehicles/{$v['id']}/sold", array( 'amount' => 1 ) ), 'already sold' );
	}

	public function test_two_memberships_need_the_tenant_header_and_foreign_tenants_are_refused(): void {
		Fixtures::user( 11 );
		Fixtures::member( $this->b, 11, 'sales' ); // User 11 owns A and is sales at B.
		Fixtures::login( 11 );
		$this->assertStatus( 400, Fixtures::call( 'GET', '/customers' ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array(), array( 'X-Wotoyi-Tenant' => $this->a['uuid'] ) ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array(), array( 'X-Wotoyi-Tenant' => $this->b['uuid'] ) ) );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/members', null, array(), array( 'X-Wotoyi-Tenant' => $this->b['uuid'] ) ), 'role is per tenant: only sales at B' );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/members', null, array(), array( 'X-Wotoyi-Tenant' => $this->a['uuid'] ) ) );
		// A user of A only: naming B in the header does not help.
		Fixtures::login( 12 );
		$c = Fixtures::call( 'POST', '/customers', array( 'name' => 'B client', 'phone' => '+221770000080' ), array(), array( 'X-Wotoyi-Tenant' => $this->b['uuid'] ) );
		$this->assertStatus( 201, $c );
		Fixtures::login( 11 );
		$this->assertStatus( 404, Fixtures::call( 'GET', '/customers/' . $c->get_data()['id'], null, array(), array( 'X-Wotoyi-Tenant' => $this->a['uuid'] ) ), 'B id through A scope is 404' );
		$res = Fixtures::call( 'GET', '/customers', null, array(), array( 'X-Wotoyi-Tenant' => 'zzzz' ) );
		$this->assertStatus( 403, $res );
		// Tenant switching header must not leak existence: unknown and foreign give the same answer.
		Fixtures::login( 20 );
		$foreign = Fixtures::call( 'GET', '/customers', null, array(), array( 'X-Wotoyi-Tenant' => $this->b['uuid'] ) );
		$unknown = Fixtures::call( 'GET', '/customers', null, array(), array( 'X-Wotoyi-Tenant' => '00000000-0000-0000-0000-000000000000' ) );
		$this->assertSame( $foreign->get_data(), $unknown->get_data() );
	}

	public function test_suspended_member_and_suspended_tenant_lose_access(): void {
		$this->as( 'sales' );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers' ) );
		$this->as( 'owner' );
		$members = Fixtures::call( 'GET', '/members' )->get_data()['items'];
		$sales = array_values( array_filter( $members, fn( $m ) => 'sales' === $m['role'] ) )[0];
		$this->assertStatus( 200, Fixtures::call( 'DELETE', '/members/' . $sales['id'] ) );
		$this->as( 'sales' );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/customers' ) );
		\Wotoyi\DealerPlatform\Data\Directory::set_tenant_status( (int) $this->a['id'], 'suspended' );
		$this->as( 'owner' );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/customers' ) );
	}

	public function test_responses_never_expose_internal_columns(): void {
		$this->as( 'owner' );
		$v = Fixtures::call( 'POST', '/vehicles', Fixtures::vehicle() );
		$this->assertStatus( 201, $v );
		$json = json_encode( array( $v->get_data(), Fixtures::call( 'GET', '/vehicles' )->get_data(), Fixtures::call( 'GET', '/me' )->get_data(), Fixtures::call( 'GET', '/members' )->get_data() ) );
		foreach ( array( 'tenant_id', 'created_by', 'secret_enc', 'deleted_at', '"uuid"' ) as $k ) {
			$this->assertStringNotContains( $k, $json, $k );
		}
		$this->assertTrue( 1 === preg_match( '/^[0-9a-f\-]{36}$/', $v->get_data()['id'] ) );
	}

	public function test_invalid_input_and_unknown_resources(): void {
		$this->as( 'owner' );
		$bad = new WP_REST_Request( 'POST', '/wotoyi-pro/v1/customers' );
		$bad->set_header( 'X-WP-Nonce', 'valid-nonce' );
		$bad->set_body( '{not json' );
		$this->assertStatus( 400, rest_do_request( $bad ) );
		$bad->set_body( '[1,2]' );
		$this->assertStatus( 400, rest_do_request( $bad ) );
		$this->assertStatus( 404, Fixtures::call( 'GET', '/vehicles/11111111-1111-1111-1111-111111111111' ) );
		$this->assertStatus( 404, Fixtures::call( 'GET', '/vehicles/not-a-uuid' ) );
		$this->assertStatus( 422, Fixtures::call( 'POST', '/vehicles', array( 'make' => 'Kia', 'model' => 'Rio', 'year' => 'abc' ) ) );
		$res = Fixtures::call( 'POST', '/vehicles', array( 'make' => 'Kia', 'model' => 'Rio', 'mystery' => 1 ) );
		$this->assertStatus( 201, $res );
		$this->assertContains( 'unknown_field:mystery', $res->get_data()['_warnings'] );
		$big = new WP_REST_Request( 'POST', '/wotoyi-pro/v1/customers' );
		$big->set_header( 'X-WP-Nonce', 'valid-nonce' );
		$big->set_body( json_encode( array( 'name' => str_repeat( 'x', 1100000 ) ) ) );
		$this->assertStatus( 413, rest_do_request( $big ) );
	}

	public function test_internal_errors_are_generic(): void {
		$this->allow_db_errors = true;
		$this->as( 'owner' );
		// Break the schema under the plugin: the answer must not leak SQL or class names.
		global $wpdb;
		$wpdb->pdo->exec( 'DROP TABLE wp_wotoyi_dp_customers' );
		$wpdb->suppress_errors( true );
		$res = Fixtures::call( 'GET', '/customers' );
		$this->assertStatus( 500, $res );
		$this->assertSame( 'Internal error.', $res->get_data()['message'] );
		$this->assertStringNotContains( 'wotoyi_dp', json_encode( $res->get_data() ) );
	}

	public function test_dashboard_money_needs_finance_capability(): void {
		$this->as( 'sales' );
		$d = Fixtures::call( 'GET', '/dashboard' )->get_data();
		$this->assertArrayNotHasKey( 'revenue', $d['sales'] );
		$this->assertArrayNotHasKey( 'margin', $d['sales'] );
		$this->as( 'accountant' );
		$d = Fixtures::call( 'GET', '/dashboard' )->get_data();
		$this->assertSame( 0, $d['sales']['revenue'] );
	}

	public function test_me_endpoint(): void {
		$this->as( 'mechanic' );
		$me = Fixtures::call( 'GET', '/me' )->get_data();
		$this->assertSame( 'mechanic', $me['member']['role'] );
		$this->assertSame( $this->a['uuid'], $me['tenant']['id'] );
		$this->assertContains( 'services.write', $me['caps'] );
		$this->assertNotContains( 'vehicles.write', $me['caps'] );
	}

	public function test_role_matrix_is_default_deny_and_consistent(): void {
		$this->assertFalse( Roles::can( null, 'vehicles.read' ) );
		$this->assertFalse( Roles::can( 'owner', 'nope' ) );
		$this->assertFalse( Roles::can( 'nope', 'vehicles.read' ) );
		foreach ( Roles::matrix() as $role => $caps ) {
			if ( 'owner' !== $role ) {
				$this->assertNotContains( 'members.manage', $caps, $role );
				$this->assertNotContains( 'customers.erase', $caps, $role );
			}
		}
		$this->assertNotContains( 'finance.read', Roles::caps( 'sales' ) );
		$this->assertContains( 'finance.read', Roles::caps( 'accountant' ) );
		$this->assertNotContains( 'vehicles.write', Roles::caps( 'accountant' ) );
	}
}
