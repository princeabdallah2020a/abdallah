<?php

use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Schema\Schema;
use Wotoyi\Marketplace\Schema\ServiceFields;
use Wotoyi\Marketplace\Schema\VehicleFields;
use Wotoyi\Marketplace\Search\Filters;

final class SchemaTest extends Wotoyi_Test_Case {

	public function test_vehicle_catalogue_has_about_eighty_fields_in_all_groups(): void {
		$defs = VehicleFields::defs();
		$this->assertGreaterThanOrEqual( 80, count( $defs ), 'vehicle fields' );
		$groups = array_unique( array_column( $defs, 'group' ) );
		foreach ( array( 'identity', 'powertrain', 'consumption', 'dimensions', 'equipment', 'condition', 'history', 'pricing', 'location', 'media', 'trust' ) as $g ) {
			$this->assertContains( $g, $groups );
		}
	}

	public function test_search_exposes_at_least_45_filter_parameters(): void {
		$this->assertGreaterThanOrEqual( 45, count( Filters::parameter_names() ) );
	}

	public function test_vin_and_plate_are_private_and_trust_fields_are_managed(): void {
		$defs = VehicleFields::defs();
		$this->assertTrue( $defs['vin']['private'] );
		$this->assertTrue( $defs['plate']['private'] );
		$this->assertNotContains( 'vin', VehicleFields::public_fields() );
		foreach ( array( 'wotoyi_inspected', 'inspection_date', 'inspection_report_url' ) as $f ) {
			$this->assertTrue( $defs[ $f ]['managed'], $f );
		}
	}

	public function test_all_tables_are_created_with_expected_names(): void {
		global $wpdb;
		$names = array_column( $wpdb->get_results( "SELECT name FROM sqlite_master WHERE type='table'" ), 'name' );
		foreach ( array( 'vehicles', 'vehicle_equipment', 'dealers', 'services', 'leads', 'favourites', 'saved_searches', 'sync_log', 'sync_nonces' ) as $t ) {
			$this->assertContains( 'wp_wotoyi_' . $t, $names );
		}
	}

	public function test_dealer_owned_tables_carry_dealer_id(): void {
		foreach ( array( 'vehicles', 'services', 'leads', 'sync_log', 'sync_nonces' ) as $t ) {
			$sql = Schema::create_statements()[ $t ];
			$this->assertStringContains( 'dealer_id BIGINT(20) UNSIGNED NOT NULL', $sql, $t );
		}
	}

	public function test_dbdelta_formatting_rules(): void {
		foreach ( Schema::create_statements() as $name => $sql ) {
			$this->assertTrue( 1 === preg_match( '/^CREATE TABLE \S+ \(/', $sql ), "$name starts with CREATE TABLE" );
			$this->assertStringContains( "PRIMARY KEY  (", $sql, "$name two spaces after PRIMARY KEY" );
			$this->assertStringNotContains( 'IF NOT EXISTS', $sql );
			$this->assertStringNotContains( '`', $sql );
			$this->assertFalse( (bool) preg_match( '/\bINDEX\b/', $sql ), "$name uses KEY not INDEX" );
			foreach ( preg_split( '/\R/', $sql ) as $line ) {
				if ( preg_match( '/KEY \S+ \(([^)]*)\)/', $line, $m ) ) {
					$this->assertStringNotContains( ', ', $m[1], "$name: no spaces inside key column list" );
				}
			}
			// One column per line: no line may define two columns.
			foreach ( preg_split( '/\R/', $sql ) as $line ) {
				$this->assertTrue( substr_count( $line, ' DEFAULT NULL,' ) <= 1, "$name one column per line" );
			}
		}
	}

	public function test_every_index_references_existing_columns_and_stays_under_mysql_limit(): void {
		foreach ( Schema::create_statements() as $name => $sql ) {
			preg_match_all( '/^\s+([a-z_0-9]+) (?:BIGINT|INT|TINYINT|SMALLINT|VARCHAR|CHAR|TEXT|LONGTEXT|DATETIME|DATE|DECIMAL)/m', $sql, $cols );
			$cols = $cols[1];
			$this->assertGreaterThanOrEqual( 3, count( $cols ), "$name columns parsed" );
			preg_match_all( '/^\s+(?:UNIQUE |PRIMARY )?KEY\s+(?:\S+ )?\(([^)]+)\)/m', $sql, $keys );
			$this->assertTrue( count( $keys[1] ) < 64, "$name < 64 indexes" );
			foreach ( $keys[1] as $keycols ) {
				foreach ( explode( ',', $keycols ) as $c ) {
					$this->assertContains( $c, $cols, "$name index column $c exists" );
				}
			}
		}
	}

	public function test_search_filter_columns_are_indexed_where_selective(): void {
		$sql = Schema::create_statements()['vehicles'];
		foreach ( array( 'st_make (status,make,model)', 'st_price (status,price)', 'st_year (status,year)', 'st_mileage (status,mileage_km)', 'st_fuel', 'st_body', 'st_trans', 'st_drive', 'st_city', 'st_geo', 'st_pub', 'dealer_source (dealer_id,source_id)' ) as $needle ) {
			$this->assertStringContains( $needle, $sql );
		}
		$this->assertStringContains( 'KEY code_vehicle (code,vehicle_id)', Schema::create_statements()['vehicle_equipment'] );
	}

	public function test_field_columns_are_all_nullable_optional(): void {
		foreach ( array_merge( VehicleFields::defs(), ServiceFields::defs() ) as $name => $def ) {
			$this->assertStringContains( 'DEFAULT NULL', Fields::column_sql( $name, $def ), $name );
		}
	}

	public function test_unique_tenant_scoped_source_key(): void {
		$this->assertStringContains( 'UNIQUE KEY dealer_source (dealer_id,source_id)', Schema::create_statements()['vehicles'] );
		$this->assertStringContains( 'UNIQUE KEY dealer_source (dealer_id,source_id)', Schema::create_statements()['services'] );
		$this->assertStringContains( 'UNIQUE KEY dealer_event (dealer_id,event_id)', Schema::create_statements()['sync_log'] );
	}

	/** Byte width of a column type under utf8mb4 (4 bytes/char), used for MySQL limit checks. */
	private function width( string $type ): int {
		if ( preg_match( '/^(VARCHAR|CHAR)\((\d+)\)/', $type, $m ) ) {
			return (int) $m[2] * 4;
		}
		$fixed = array( 'BIGINT' => 8, 'INT' => 4, 'SMALLINT' => 2, 'TINYINT' => 1, 'DATETIME' => 8, 'DATE' => 3, 'DECIMAL' => 8 );
		foreach ( $fixed as $t => $w ) {
			if ( str_starts_with( $type, $t ) ) {
				return $w;
			}
		}
		return 12; // TEXT / LONGTEXT live off-page (pointer only).
	}

	public function test_row_size_and_index_key_length_fit_mysql_limits(): void {
		foreach ( Schema::create_statements() as $name => $sql ) {
			$cols = array();
			foreach ( preg_split( '/\R/', $sql ) as $line ) {
				if ( preg_match( '/^\s+([a-z_0-9]+) ((?:BIGINT|INT|TINYINT|SMALLINT|VARCHAR|CHAR|TEXT|LONGTEXT|DATETIME|DATE|DECIMAL)(?:\(\d+(?:,\d+)?\))?)/', $line, $m ) ) {
					$cols[ $m[1] ] = $this->width( $m[2] );
				}
			}
			$this->assertTrue( array_sum( $cols ) < 65535, "$name row width " . array_sum( $cols ) . ' < 65535 bytes' );
			preg_match_all( '/^\s+(?:UNIQUE |PRIMARY )?KEY\s+(?:\S+ )?\(([^)]+)\)/m', $sql, $keys );
			foreach ( $keys[1] as $keycols ) {
				$bytes = array_sum( array_map( fn( $c ) => $cols[ $c ], explode( ',', $keycols ) ) );
				$this->assertTrue( $bytes <= 767, "$name index ($keycols) is $bytes bytes, must be <= 767 for COMPACT row format" );
			}
		}
	}

	public function test_vehicle_table_column_count_is_reported(): void {
		$sql = Schema::create_statements()['vehicles'];
		preg_match_all( '/^\s+[a-z_0-9]+ (?:BIGINT|INT|TINYINT|SMALLINT|VARCHAR|CHAR|TEXT|LONGTEXT|DATETIME|DATE|DECIMAL)/m', $sql, $m );
		fwrite( STDOUT, '        [info] vehicles: ' . count( VehicleFields::defs() ) . ' catalogue fields, ' . count( $m[0] ) . ' columns, ' . substr_count( $sql, "\n  KEY " ) . ' secondary + 3 unique indexes; filters: ' . count( Filters::parameter_names() ) . "\n" );
		$this->assertGreaterThanOrEqual( 90, count( $m[0] ) );
	}
}
