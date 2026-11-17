<?php
use Wotoyi\DealerPlatform\Schema\Schema;
use Wotoyi\DealerPlatform\Schema\VehicleFields;

final class SchemaTest extends Wotoyi_Test_Case {

	public function test_all_tables_created_and_scoped_tables_have_tenant_id(): void {
		foreach ( Schema::names() as $name ) {
			$cols = Schema::columns( $name );
			$this->assertContains( 'id', $cols, $name );
			if ( Schema::is_scoped( $name ) ) {
				$this->assertContains( 'tenant_id', $cols, "{$name} must carry tenant_id" );
			} else {
				$this->assertNotContains( 'tenant_id', $cols );
			}
			$rows = Fixtures::raw( 'SELECT COUNT(*) AS n FROM ' . $GLOBALS['wpdb']->prefix . 'wotoyi_dp_' . $name );
			$this->assertSame( '0', $rows[0]['n'], $name );
		}
	}

	public function test_required_tables_exist(): void {
		foreach ( array( 'tenants', 'members', 'vehicles', 'services', 'customers', 'leads', 'deals', 'rentals', 'employees', 'tasks', 'activities', 'outbox', 'audit_log' ) as $t ) {
			$this->assertContains( $t, Schema::names() );
		}
	}

	public function test_every_scoped_table_has_a_tenant_leading_index(): void {
		foreach ( Schema::create_statements() as $name => $sql ) {
			if ( ! Schema::is_scoped( $name ) || 'audit_log' === $name || 'sync_state' === $name ) {
				continue;
			}
			$this->assertTrue( 1 === preg_match( '/KEY \w+ \(tenant_id[,)]/', $sql ), "{$name} needs an index starting with tenant_id" );
		}
	}

	public function test_vehicle_master_model_size_and_dealer_only_fields(): void {
		$cat = VehicleFields::catalogue();
		$this->assertGreaterThanOrEqual( 80, count( $cat ), 'catalogue fields (marketplace has 88 incl. 3 managed trust fields)' );
		$this->assertArrayNotHasKey( 'wotoyi_inspected', $cat );
		foreach ( array( 'purchase_cost', 'internal_notes', 'supplier', 'stock_ref' ) as $f ) {
			$this->assertArrayHasKey( $f, VehicleFields::dealer_only() );
			$this->assertNotContains( $f, VehicleFields::sync_fields(), "{$f} must never be synced" );
		}
		$this->assertNotContains( 'vin', VehicleFields::sync_fields() );
		$this->assertNotContains( 'plate', VehicleFields::sync_fields() );
	}

	public function test_static_dbdelta_format(): void {
		foreach ( Schema::create_statements() as $name => $sql ) {
			$this->assertStringContains( "CREATE TABLE ", $sql );
			$this->assertTrue( 1 === preg_match( '/PRIMARY KEY  \(id\)/', $sql ), "{$name}: two spaces after PRIMARY KEY (dbDelta)" );
			$this->assertStringNotContains( ' INDEX ', $sql );
			$this->assertTrue( 1 !== preg_match( '/KEY \w+ \([^)]*, /', $sql ), "{$name}: no spaces inside key column lists (dbDelta)" );
		}
	}
}
