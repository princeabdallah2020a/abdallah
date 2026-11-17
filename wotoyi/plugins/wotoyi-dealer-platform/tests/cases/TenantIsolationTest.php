<?php
use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\ApiException;

final class TenantIsolationTest extends Wotoyi_Test_Case {

	private array $a;
	private array $b;

	protected function setUp(): void {
		parent::setUp();
		$this->a = Fixtures::tenant( 'Dealer A', 11 );
		$this->b = Fixtures::tenant( 'Dealer B', 12 );
	}

	public function test_no_tenant_context_fails_closed(): void {
		$this->assertThrows( fn() => TenantDb::select( 'vehicles' ), 'no_tenant_context' );
		$this->assertThrows( fn() => TenantDb::insert( 'customers', array( 'name' => 'x' ) ), 'no_tenant_context' );
		$this->assertThrows( fn() => TenantDb::count( 'leads' ), 'no_tenant_context' );
		$this->assertThrows( fn() => Plugin::vehicles()->list(), 'no_tenant_context' );
	}

	public function test_callers_cannot_supply_or_override_tenant_id(): void {
		Fixtures::in( $this->a, function () {
			$this->assertThrows( fn() => TenantDb::insert( 'customers', array( 'tenant_id' => 999, 'name' => 'x' ) ), 'tenant_id_not_allowed' );
			$this->assertThrows( fn() => TenantDb::select( 'customers', array( 'tenant_id' => 999 ) ), 'tenant_id_not_allowed' );
			$this->assertThrows( fn() => TenantDb::update( 'customers', array( 'uuid' => 'x' ), array( 'tenant_id' => 999 ) ), 'tenant_id_not_allowed' );
			$this->assertThrows( fn() => TenantDb::update( 'customers', array(), array( 'name' => 'x' ) ), 'update_requires_where_and_data' );
			$this->assertThrows( fn() => TenantDb::delete( 'customers', array( 'uuid' => 'x' ) ), 'delete_not_allowed' );
			$this->assertThrows( fn() => TenantDb::select( 'customers', array( 'name; DROP TABLE x' => 1 ) ), 'unknown_column' );
			$this->assertThrows( fn() => TenantDb::select( 'tenants' ), 'not_a_tenant_table' );
		} );
	}

	public function test_every_statement_on_a_tenant_table_is_scoped(): void {
		global $wpdb;
		$wpdb->queries = array();
		Fixtures::in( $this->a, function () {
			$c = Plugin::customers()->create( array( 'name' => 'Awa', 'phone' => '+221770000001' ) );
			Plugin::customers()->update( $c['id'], array( 'city' => 'Thies' ) );
			Plugin::customers()->list( array( 'q' => 'Aw' ) );
			Plugin::vehicles()->create( Fixtures::vehicle() );
			Plugin::dashboard()->kpis();
		} );
		$tenant_tables = array_map( fn( $n ) => $wpdb->prefix . 'wotoyi_dp_' . $n, \Wotoyi\DealerPlatform\Schema\Schema::scoped_names() );
		$checked = 0;
		foreach ( $wpdb->queries as $sql ) {
			if ( ! preg_match( '/\b(FROM|UPDATE|INTO|DELETE FROM)\s+(\w+)/i', $sql, $m ) || ! in_array( $m[2], $tenant_tables, true ) ) {
				continue;
			}
			++$checked;
			$this->assertTrue( 1 === preg_match( '/tenant_id\s*=\s*\d+|\(.*\btenant_id\b.*\)\s*VALUES|INSERT INTO \w+ \([^)]*tenant_id/i', $sql ), 'unscoped statement: ' . substr( $sql, 0, 160 ) );
		}
		$this->assertGreaterThanOrEqual( 20, $checked );
	}

	public function test_tenant_b_cannot_read_update_or_delete_tenant_a_rows(): void {
		$cust = Fixtures::in( $this->a, fn() => Plugin::customers()->create( array( 'name' => 'Secret Client', 'phone' => '+221770000002' ) ) );
		$veh  = Fixtures::in( $this->a, fn() => Plugin::vehicles()->create( Fixtures::vehicle() ) );
		Fixtures::in( $this->b, function () use ( $cust, $veh ) {
			$this->assertSame( 0, Plugin::customers()->list()['total'] );
			$this->assertSame( 0, Plugin::vehicles()->list()['total'] );
			foreach ( array( fn() => Plugin::customers()->get( $cust['id'] ), fn() => Plugin::customers()->update( $cust['id'], array( 'name' => 'Hacked' ) ), fn() => Plugin::customers()->archive( $cust['id'] ), fn() => Plugin::vehicles()->get( $veh['id'] ), fn() => Plugin::vehicles()->update( $veh['id'], array( 'price' => 1 ) ), fn() => Plugin::vehicles()->archive( $veh['id'] ), fn() => Plugin::customers()->erase( $cust['id'] ), fn() => Plugin::vehicles()->transition( $veh['id'], 'published' ) ) as $i => $attempt ) {
				try {
					$attempt();
					$this->fail( "attempt {$i} must not succeed" );
				} catch ( ApiException $e ) {
					$this->assertSame( 404, $e->http_status(), "attempt {$i}" );
				}
			}
		} );
		Fixtures::in( $this->a, function () use ( $cust, $veh ) {
			$this->assertSame( 'Secret Client', Plugin::customers()->get( $cust['id'] )['name'] );
			$this->assertSame( 15000000, Plugin::vehicles()->get( $veh['id'] )['price'] );
			$this->assertSame( 'draft', Plugin::vehicles()->get( $veh['id'] )['status'] );
		} );
	}

	public function test_references_to_another_tenants_records_are_refused(): void {
		$cust = Fixtures::in( $this->a, fn() => Plugin::customers()->create( array( 'name' => 'A client', 'phone' => '+221770000003' ) ) );
		$veh  = Fixtures::in( $this->a, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		Fixtures::in( $this->b, function () use ( $cust, $veh ) {
			$own = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) );
			foreach ( array(
				fn() => Plugin::leads()->create( array( 'name' => 'x', 'customer_uuid' => $cust['id'] ) ),
				fn() => Plugin::deals()->create( array( 'vehicle_uuid' => $veh['id'], 'amount' => 1, 'status' => 'completed' ) ),
				fn() => Plugin::deals()->create( array( 'vehicle_uuid' => $own['id'], 'customer_uuid' => $cust['id'], 'amount' => 1 ) ),
				fn() => Plugin::rentals()->create( array( 'vehicle_uuid' => $veh['id'], 'customer_uuid' => $cust['id'], 'start_date' => '2027-02-01', 'end_date' => '2027-02-03' ) ),
				fn() => Plugin::activities()->create( array( 'entity_type' => 'customer', 'entity_uuid' => $cust['id'], 'body' => 'spy' ) ),
				fn() => Plugin::tasks()->create( array( 'title' => 't', 'related_type' => 'vehicle', 'related_uuid' => $veh['id'] ) ),
			) as $i => $attempt ) {
				try {
					$attempt();
					$this->fail( "cross-tenant reference {$i} must be refused" );
				} catch ( ApiException $e ) {
					$this->assertTrue( in_array( $e->http_status(), array( 404, 409, 422 ), true ), "attempt {$i}: " . $e->getMessage() );
				}
			}
		} );
		// Tenant A's data is untouched.
		$this->assertSame( 0, Fixtures::count( 'deals' ) + Fixtures::count( 'rentals' ) + Fixtures::count( 'activities', "entity_uuid = '{$cust['id']}' AND type = 'note'" ) );
	}

	public function test_outbox_and_audit_are_per_tenant(): void {
		$v = Fixtures::in( $this->a, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		Fixtures::in( $this->b, function () use ( $v ) {
			$this->assertSame( 0, TenantDb::count( 'outbox' ) );
			$this->assertSame( 0, \Wotoyi\DealerPlatform\Sync\Outbox::list( array() )['total'] );
			foreach ( \Wotoyi\DealerPlatform\Service\AuditLog::list( array() )['items'] as $row ) {
				$this->assertTrue( $row['entity_id'] !== $v['id'], 'audit of another tenant leaked' );
			}
		} );
		$this->assertSame( 1, Fixtures::in( $this->a, fn() => TenantDb::count( 'outbox' ) ) );
		$this->assertSame( 1, Fixtures::in( $this->a, fn() => \Wotoyi\DealerPlatform\Service\AuditLog::list( array( 'entity_uuid' => $v['id'] ) )['total'] ) );
	}

	public function test_context_is_restored_after_nested_run_and_after_exceptions(): void {
		Fixtures::in( $this->a, function () {
			$this->assertSame( $this->a['uuid'], TenantContext::uuid() );
			try {
				Fixtures::in( $this->b, function () {
					$this->assertSame( $this->b['uuid'], TenantContext::uuid() );
					throw new RuntimeException( 'boom' );
				} );
			} catch ( RuntimeException $e ) {
				$this->assertSame( 'boom', $e->getMessage() );
			}
			$this->assertSame( $this->a['uuid'], TenantContext::uuid() );
		} );
		$this->assertFalse( TenantContext::has() );
	}

	public function test_membership_lookup_returns_only_the_callers_own_memberships(): void {
		$mine = Directory::memberships_for_user( 11 );
		$this->assertCount( 1, $mine );
		$this->assertSame( $this->a['uuid'], $mine[0]['tenant']['uuid'] );
		$this->assertCount( 0, Directory::memberships_for_user( 9999 ) );
		$this->assertCount( 0, Directory::memberships_for_user( 0 ) );
	}
}
