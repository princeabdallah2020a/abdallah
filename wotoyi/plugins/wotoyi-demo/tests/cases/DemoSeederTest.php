<?php

use Wotoyi\Demo\Admin;
use Wotoyi\Demo\Dataset;
use Wotoyi\Demo\DealerList;
use Wotoyi\Demo\Seeder;
use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Sync\LocalTransport;

final class DemoSeederTest extends Wotoyi_Test_Case {

	private const TABLES = array( 'vehicles', 'vehicle_equipment', 'dealers', 'services', 'leads', 'favourites', 'sync_log', 'sync_nonces' );

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wotoyi_env'] = 'development'; // Base case; production is tested explicitly.
	}

	/** @return array<string,int> */
	private function snapshot(): array {
		$out = array();
		foreach ( self::TABLES as $t ) {
			$out[ $t ] = Fixtures::table_count( $t );
		}
		return $out;
	}

	/** A real dealer (HTTP) + a real local dealer (not demo) with listings, a lead and a favourite. */
	private function real_data(): array {
		$http = Fixtures::dealer( 'Vrai Concessionnaire' );
		Fixtures::publish( $http, 'real-1' );
		update_option( Settings::OPTION, array( 'local_mode' => true ) );
		wotoyi_marketplace_local_register_dealer( 'my-own-local-dealer', array( 'name' => 'Local réel' ) );
		$res = wotoyi_marketplace_local_receive( 'my-own-local-dealer', Fixtures::event( 'vehicle.published', 'loc-1', 1, Fixtures::vehicle() ) );
		$this->assertSame( 'applied', $res['status'] );
		delete_option( Settings::OPTION );
		$vid = (int) $res['entity']['id'] ?? 0;
		return array( 'http' => $http, 'vehicle_id' => $vid );
	}

	public function test_load_creates_dealers_vehicles_services_through_marketplace_api(): void {
		$r = Seeder::load();
		$this->assertSame( array(), $r['errors'], 'no rejected item and no warning from the receiver' );
		$this->assertSame( 4, $r['dealers'] );
		$this->assertSame( 12, $r['vehicles'] );
		$this->assertSame( 4, $r['services'] );
		$this->assertSame( 4, Fixtures::table_count( 'dealers', "status = 'active' AND transport = 'local'" ) );
		$this->assertSame( 16, Fixtures::table_count( 'sync_log', "transport = 'local' AND status = 'applied'" ), 'every item went through the receiver ledger' );
		$this->assertSame( array( 'dealers' => 4, 'vehicles' => 12, 'services' => 4 ), Seeder::counts() );
	}

	public function test_search_api_returns_demo_vehicles_in_french_with_theme_images_and_labels(): void {
		Seeder::load();
		$res = Plugin::search()->search( array( 'per_page' => 48 ) );
		$this->assertSame( 12, $res['total'] );
		$this->assertCount( 12, $res['items'] );
		foreach ( $res['items'] as $it ) {
			$this->assertStringContains( '(démo)', $it['title'] );
			$this->assertStringContains( '(démo)', $it['dealer']['name'] );
		}
		$this->assertSame( 1, Plugin::search()->count( array( 'make' => 'Mercedes-Benz' ) ) );
		$this->assertSame( 2, Plugin::search()->count( array( 'body_type' => 'pickup' ) ) );
		$this->assertSame( 3, Plugin::search()->count( array( 'offer_type' => 'rental' ) ), 'RAV4, 508, X-Trail are also for rent' );
		$this->assertSame( 2, Plugin::search()->count( array( 'fuel' => 'hybrid' ) ) );
		$this->assertGreaterThanOrEqual( 1, Plugin::search()->count( array( 'equipment' => 'sunroof,leather_seats' ) ) );
		$detail = Plugin::search()->vehicle_detail( $res['items'][0]['id'], 'fr' );
		$this->assertNotNull( $detail );
		$json = (string) json_encode( $res, JSON_UNESCAPED_SLASHES );
		$this->assertStringContains( 'https://demo.example.com/wp-content/themes/wotoyi/assets/images/', $json );
		$services = Plugin::search()->search_services( array() );
		$this->assertSame( 4, $services['total'] );
	}

	public function test_dealer_vehicle_distribution_matches_prototype(): void {
		Seeder::load();
		$names = array();
		foreach ( DealerList::page( 1 )['rows'] as $r ) {
			$names[ $r['source_dealer_uuid'] ] = (int) $r['vehicles_published'];
		}
		ksort( $names );
		$expected = array( 'wotoyi-demo-almadies' => 4, 'wotoyi-demo-corniche' => 3, 'wotoyi-demo-petite' => 2, 'wotoyi-demo-thies' => 3 );
		$this->assertSame( $expected, $names );
	}

	public function test_load_twice_is_idempotent(): void {
		Seeder::load();
		$before = $this->snapshot();
		$r      = Seeder::load();
		$this->assertSame( $before, $this->snapshot(), 'no duplicates' );
		$this->assertSame( 12, $r['vehicles'] );
	}

	public function test_remove_deletes_every_demo_row_and_leaves_real_rows_untouched(): void {
		$real   = $this->real_data();
		$before = $this->snapshot();
		Seeder::load();
		// Hang extra personal-data rows on demo listings: a lead, favourites.
		$demo_v = (int) $GLOBALS['wpdb']->get_var( 'SELECT id FROM ' . $GLOBALS['wpdb']->prefix . "wotoyi_vehicles WHERE source_id = 'demo-veh-01'" );
		$demo_d = (int) $GLOBALS['wpdb']->get_var( 'SELECT dealer_id FROM ' . $GLOBALS['wpdb']->prefix . 'wotoyi_vehicles WHERE id = ' . $demo_v );
		$GLOBALS['wpdb']->insert( $GLOBALS['wpdb']->prefix . 'wotoyi_leads', array( 'uuid' => \Wotoyi\Marketplace\Support\Uuid::v7(), 'dealer_id' => $demo_d, 'vehicle_id' => $demo_v, 'created_at' => '2027-01-15 00:00:00' ) );
		$GLOBALS['wpdb']->insert( $GLOBALS['wpdb']->prefix . 'wotoyi_favourites', array( 'user_id' => 9, 'item_type' => 'vehicle', 'item_id' => $demo_v, 'created_at' => '2027-01-15 00:00:00' ) );
		// A favourite on a REAL vehicle must survive.
		$real_v = (int) $GLOBALS['wpdb']->get_var( 'SELECT id FROM ' . $GLOBALS['wpdb']->prefix . "wotoyi_vehicles WHERE source_id = 'real-1'" );
		$GLOBALS['wpdb']->insert( $GLOBALS['wpdb']->prefix . 'wotoyi_favourites', array( 'user_id' => 9, 'item_type' => 'vehicle', 'item_id' => $real_v, 'created_at' => '2027-01-15 00:00:00' ) );
		$real_rows = (string) json_encode( $GLOBALS['wpdb']->get_results( 'SELECT * FROM ' . $GLOBALS['wpdb']->prefix . 'wotoyi_vehicles WHERE id = ' . $real_v ) );

		$deleted = Seeder::remove();
		$this->assertSame( 4, $deleted['dealers'] );
		$this->assertSame( 12, $deleted['vehicles'] );
		$this->assertSame( 4, $deleted['services'] );
		$this->assertSame( 1, $deleted['leads'] );
		$this->assertSame( 1, $deleted['favourites_vehicles'] );

		$after = $this->snapshot();
		$this->assertSame( $before['dealers'], $after['dealers'], 'real dealers kept' );
		$this->assertSame( $before['vehicles'], $after['vehicles'], 'real vehicles kept' );
		$this->assertSame( $before['vehicle_equipment'], $after['vehicle_equipment'] );
		$this->assertSame( $before['sync_log'], $after['sync_log'] );
		$this->assertSame( 1, $after['favourites'], 'only the favourite on the real vehicle remains' );
		$this->assertSame( 0, $after['leads'] );
		$this->assertSame( array( 'dealers' => 0, 'vehicles' => 0, 'services' => 0 ), Seeder::counts() );
		$this->assertSame( 0, Fixtures::table_count( 'dealers', "source_dealer_uuid LIKE 'wotoyi-demo-%'" ) );
		$this->assertSame( $real_rows, (string) json_encode( $GLOBALS['wpdb']->get_results( 'SELECT * FROM ' . $GLOBALS['wpdb']->prefix . 'wotoyi_vehicles WHERE id = ' . $real_v ) ), 'real row byte-identical' );
		$this->assertSame( 2, Plugin::search()->count( array() ), 'search shows only the 2 real vehicles (HTTP dealer + own local dealer)' );
	}

	public function test_remove_without_demo_data_is_a_noop_and_reload_after_remove_works(): void {
		$this->assertSame( array(), Seeder::remove() );
		Seeder::load();
		Seeder::remove();
		$r = Seeder::load();
		$this->assertSame( array(), $r['errors'] );
		$this->assertSame( 12, Plugin::search()->count( array() ) );
		Seeder::remove();
		$this->assertSame( 0, Plugin::search()->count( array() ) );
	}

	public function test_remove_is_atomic_when_a_delete_fails(): void {
		Seeder::load();
		$before = $this->snapshot();
		$GLOBALS['wpdb']->query( 'ALTER TABLE ' . $GLOBALS['wpdb']->prefix . 'wotoyi_sync_log RENAME TO tmp_sync_log' );
		$this->allow_db_errors = true;
		$this->assertThrows( fn() => Seeder::remove(), 'delete_failed_sync_log' );
		$GLOBALS['wpdb']->query( 'ALTER TABLE tmp_sync_log RENAME TO ' . $GLOBALS['wpdb']->prefix . 'wotoyi_sync_log' );
		$GLOBALS['wotoyi_db_errors'] = array();
		$this->assertSame( $before, $this->snapshot(), 'rollback: nothing half-deleted' );
	}

	public function test_production_requires_explicit_confirmation(): void {
		$GLOBALS['wotoyi_env'] = 'production';
		$this->assertThrows( fn() => Seeder::load(), 'production_confirmation_required' );
		$this->assertSame( 0, Fixtures::table_count( 'dealers' ) );
		$this->assertFalse( LocalTransport::enabled() );
		$r = Seeder::load( true );
		$this->assertSame( 12, $r['vehicles'] );
		foreach ( array( 'staging', 'local', 'development' ) as $env ) {
			$GLOBALS['wotoyi_env'] = $env;
			$this->assertSame( 12, Seeder::load( false )['vehicles'], "no confirmation needed on {$env}" );
		}
	}

	public function test_local_mode_is_enabled_only_during_seeding_and_restored_exactly(): void {
		// 1. Option absent before -> absent after.
		$this->assertSame( 'MISSING', get_option( Settings::OPTION, 'MISSING' ) );
		Seeder::load();
		$this->assertSame( 'MISSING', get_option( Settings::OPTION, 'MISSING' ) );
		$this->assertFalse( LocalTransport::enabled() );
		// 2. Existing settings (other keys, local_mode explicitly false) are preserved byte for byte.
		$orig = array( 'local_mode' => false, 'public_rate_limit' => 77, 'delete_on_uninstall' => true );
		update_option( Settings::OPTION, $orig );
		Seeder::load();
		$this->assertSame( $orig, get_option( Settings::OPTION ) );
		$this->assertFalse( LocalTransport::enabled() );
		// 3. Seeding really needs it: it was on while loading (sync_log rows say local) and demo dealers cannot be addressed afterwards.
		$this->assertThrows( fn() => wotoyi_marketplace_local_receive( 'x', array() ), 'local_mode_disabled' );
	}

	public function test_local_mode_restored_when_seeding_fails_midway(): void {
		Plugin::dealers()->create( array( 'name' => 'Squatter' ), 'http', 'wotoyi-demo-corniche' ); // HTTP dealer already owns a demo id.
		$this->assertThrows( fn() => Seeder::load(), 'dealer_is_not_local' );
		$this->assertSame( 'MISSING', get_option( Settings::OPTION, 'MISSING' ) );
		$this->assertFalse( LocalTransport::enabled() );
		$this->assertFalse( (bool) get_transient( 'wotoyi_demo_lock' ), 'lock released' );
		// The HTTP squatter is not a demo row and survives a removal.
		Seeder::remove();
		$this->assertSame( 1, Fixtures::table_count( 'dealers', "name = 'Squatter'" ) );
	}

	public function test_overlapping_run_is_refused(): void {
		set_transient( 'wotoyi_demo_lock', 1, 120 );
		$this->assertThrows( fn() => Seeder::load(), 'demo_busy' );
	}

	public function test_requires_marketplace_function_guard_and_filter_override_is_detected(): void {
		add_filter( 'wotoyi_mp_setting_local_mode', static fn() => false );
		$this->assertThrows( fn() => Seeder::load(), 'local_mode_unavailable' );
		$this->assertSame( 'MISSING', get_option( Settings::OPTION, 'MISSING' ), 'option restored' );
		$this->assertFalse( (bool) get_transient( 'wotoyi_demo_lock' ) );
		$this->assertSame( 0, Fixtures::table_count( 'dealers' ) );
	}

	public function test_admin_actions_need_the_capability_and_return_whitelisted_codes(): void {
		$this->assertSame( array( 'wd' => 'forbidden' ), Admin::run_action( 'load', true ) );
		$this->assertSame( 0, Fixtures::table_count( 'dealers' ) );
		Fixtures::login( 5, array( 'edit_posts' ) );
		$this->assertSame( 'forbidden', Admin::run_action( 'remove', false )['wd'] );
		Fixtures::admin();
		$loaded = Admin::run_action( 'load', false );
		$this->assertSame( array( 'wd' => 'loaded', 'nd' => 4, 'nv' => 12, 'ns' => 4, 'ne' => 0 ), $loaded );
		$GLOBALS['wotoyi_env'] = 'production';
		$this->assertSame( 'need_confirm', Admin::run_action( 'load', false )['wd'] );
		$this->assertSame( 'removed', Admin::run_action( 'remove', false )['wd'] );
		$this->assertSame( 'error', Admin::run_action( 'bogus', true )['wd'] );
		Fixtures::login( 5, array( 'edit_posts' ) );
		$this->assertThrows( fn() => Admin::render_demo(), 'wp_die:403' );
	}

	public function test_screens_are_registered_with_the_marketplace_capability_and_render_escaped(): void {
		Admin::menus();
		$kinds = array_column( $GLOBALS['wotoyi_admin_menus'], 0 );
		$this->assertSame( array( 'menu', 'sub', 'tools' ), $kinds );
		foreach ( $GLOBALS['wotoyi_admin_menus'] as $m ) {
			$this->assertSame( 'wotoyi_manage_marketplace', 'tools' === $m[0] ? $m[1][2] : ( 'sub' === $m[0] ? $m[1][3] : $m[1][2] ) );
		}
		// Not allowed without the capability.
		$this->assertThrows( fn() => Admin::render_dealers(), 'wp_die:403' );
		Fixtures::admin();
		$d = Plugin::dealers()->create( array( 'name' => 'Piège', 'city' => 'Dakar' ), 'http', 'real-xss' );
		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->prefix . 'wotoyi_dealers', array( 'name' => '<script>alert(1)</script>Piège' ), array( 'id' => (int) $d['id'] ) ); // Hostile value straight in the DB.
		Seeder::load();
		ob_start();
		Admin::render_dealers();
		$html = (string) ob_get_clean();
		$this->assertStringNotContains( '<script>', $html );
		$this->assertStringContains( '&lt;script&gt;', $html );
		$this->assertStringContains( 'Almadies Prestige (démo)', $html );
		$this->assertStringContains( 'DÉMO', $html );
		$this->assertStringContains( 'Liste en lecture seule', $html );
		$this->assertStringNotContains( 'name="action"', $html, 'dealers list has no form: read-only' );
		ob_start();
		Admin::render_demo();
		$demo = (string) ob_get_clean();
		$this->assertStringContains( 'DONNÉES FICTIVES', $demo );
		$this->assertStringContains( 'wotoyi_demo_load', $demo );
		$this->assertStringContains( 'wotoyi_demo_remove', $demo );
		$this->assertStringNotContains( 'wotoyi_demo_confirm', $demo, 'no checkbox outside production' );
		$GLOBALS['wotoyi_env'] = 'production';
		ob_start();
		Admin::render_demo();
		$this->assertStringContains( 'wotoyi_demo_confirm', (string) ob_get_clean() );
	}

	public function test_dataset_is_french_fictional_and_inside_enums(): void {
		$all = Dataset::vehicles();
		$this->assertCount( 12, array_merge( ...array_values( $all ) ) );
		foreach ( $all as $items ) {
			foreach ( $items as $it ) {
				$this->assertStringContains( 'DÉMO', $it['data']['description'] );
				$this->assertTrue( 1 === preg_match( '#^https://demo\.example\.com/.+\.svg$#', $it['data']['cover_image'] ) );
			}
		}
		foreach ( Dataset::dealers() as $d ) {
			$this->assertStringContains( '(démo)', $d['profile']['name'] );
			$this->assertStringContains( '@example.com', $d['profile']['email'] );
			$this->assertStringContains( '+221 00 000', $d['profile']['phone'] );
		}
		$this->assertTrue( Dataset::theme_images_present(), 'theme SVG folder exists in the repo' );
		foreach ( array( 'hero-suv', 'pickup-atlantic', 'sedan-obsidian', 'sedan-ruby', 'suv-amber', 'suv-pearl', 'suv-sage' ) as $f ) {
			$this->assertTrue( is_file( dirname( __DIR__, 4 ) . "/themes/wotoyi/assets/images/{$f}.svg" ), $f );
		}
	}

	public function test_source_has_no_unprepared_sql_and_no_secrets(): void {
		foreach ( glob( WOTOYI_DEMO_DIR . 'includes/*.php' ) as $f ) {
			$src = (string) file_get_contents( $f );
			$this->assertFalse( (bool) preg_match( '/->(query|get_var|get_col|get_results|get_row)\(\s*[\'"]/', $src ), basename( $f ) . ': raw SQL literal passed to $wpdb' );
			$this->assertFalse( (bool) preg_match( '/\$_(REQUEST|COOKIE|SERVER)\b/', $src ), basename( $f ) . ': superglobal' );
			$this->assertFalse( (bool) preg_match( '/(password|secret|api[_-]?key)\s*[=:]/i', $src ), basename( $f ) . ': possible secret' );
		}
	}
}
