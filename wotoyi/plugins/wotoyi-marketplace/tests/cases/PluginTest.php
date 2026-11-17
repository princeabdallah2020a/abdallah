<?php

use Wotoyi\Marketplace\Installer;
use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Clock;

final class PluginTest extends Wotoyi_Test_Case {

	private function boot_main_file(): void {
		$GLOBALS['wotoyi_actions'] = array();
		$prev = set_error_handler( static fn() => true ); // The shim already defined the plugin constants.
		try {
			include WOTOYI_MP_DIR . 'wotoyi-marketplace.php';
		} finally {
			restore_error_handler();
		}
	}

	public function test_main_file_registers_hooks_and_activation(): void {
		$this->boot_main_file();
		$this->assertTrue( isset( $GLOBALS['wotoyi_actions']['rest_api_init'] ), 'REST routes hooked' );
		$this->assertTrue( isset( $GLOBALS['wotoyi_actions']['plugins_loaded'] ), 'upgrade check hooked' );
		$this->assertTrue( isset( $GLOBALS['wotoyi_actions'][ Installer::CRON_HOOK ] ), 'prune cron hooked' );
		$this->assertSame( array( Installer::class, 'activate' ), $GLOBALS['wotoyi_activation'] );
	}

	public function test_activation_creates_tables_capability_and_cron(): void {
		global $wpdb;
		$GLOBALS['wpdb'] = $wpdb = new wpdb(); // Empty database.
		$GLOBALS['wotoyi_caps_added'] = array();
		Installer::activate();
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM sqlite_master WHERE name = 'wp_wotoyi_vehicles'" ) );
		$this->assertContains( 'wotoyi_manage_marketplace', $GLOBALS['wotoyi_caps_added'] );
		$this->assertNotNull( wp_next_scheduled( Installer::CRON_HOOK ) );
		$this->assertSame( WOTOYI_MP_DB_VERSION, get_option( Installer::DB_OPTION ) );
		Installer::activate(); // Re-activation is harmless.
		Installer::deactivate();
		$this->assertFalse( wp_next_scheduled( Installer::CRON_HOOK ) );
	}

	public function test_maybe_upgrade_reinstalls_only_when_version_differs(): void {
		global $wpdb;
		$wpdb->pdo->exec( 'DROP TABLE wp_wotoyi_leads' );
		Installer::maybe_upgrade(); // Same version: nothing happens.
		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM sqlite_master WHERE name = 'wp_wotoyi_leads'" ) );
		update_option( Installer::DB_OPTION, '0' );
		Installer::maybe_upgrade();
		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM sqlite_master WHERE name = 'wp_wotoyi_leads'" ) );
	}

	public function test_daily_prune_removes_expired_nonces_and_old_log_rows(): void {
		$a = Fixtures::dealer();
		Fixtures::call_signed( $a, 'POST', '/wotoyi/v1/sync/events', Fixtures::event( 'vehicle.published', 'v1', 1, Fixtures::vehicle() ) );
		$this->assertSame( 1, Fixtures::table_count( 'sync_nonces' ) );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log' ) );
		Clock::set( Clock::now() + 30 * DAY_IN_SECONDS );
		Plugin::prune();
		$this->assertSame( 0, Fixtures::table_count( 'sync_nonces' ) );
		$this->assertSame( 1, Fixtures::table_count( 'sync_log' ), 'log kept within 90 days' );
		Clock::set( Clock::now() + 100 * DAY_IN_SECONDS );
		Plugin::prune();
		$this->assertSame( 0, Fixtures::table_count( 'sync_log' ) );
		$this->assertSame( 1, Fixtures::table_count( 'vehicles' ), 'listings are never pruned' );
	}

	public function test_uninstall_drops_everything_only_when_opted_in(): void {
		global $wpdb;
		$this->assertFalse( Settings::get( 'delete_on_uninstall' ), 'safe default: data kept' );
		Installer::drop_all();
		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'wp_wotoyi_%' AND type = 'table'" ) );
		$this->assertFalse( get_option( Installer::DB_OPTION ) );
	}

	public function test_settings_defaults_and_overrides(): void {
		$this->assertSame( 300, Settings::int( 'clock_skew' ) );
		$this->assertSame( 660, Settings::nonce_ttl(), 'nonce outlives the replay window (2 x skew + margin)' );
		update_option( Settings::OPTION, array( 'clock_skew' => 100 ) );
		$this->assertSame( 100, Settings::int( 'clock_skew' ) );
		$this->assertSame( 260, Settings::nonce_ttl() );
		$this->assertFalse( Settings::get( 'local_mode' ) );
	}

	public function test_uuid_v7_is_valid_unique_and_time_ordered(): void {
		$ids = array();
		for ( $i = 0; $i < 500; $i++ ) {
			$ids[] = \Wotoyi\Marketplace\Support\Uuid::v7();
			$this->assertTrue( \Wotoyi\Marketplace\Support\Uuid::is_valid( $ids[ $i ] ) );
			$this->assertSame( '7', $ids[ $i ][14], 'version nibble' );
			$this->assertTrue( in_array( $ids[ $i ][19], array( '8', '9', 'a', 'b' ), true ), 'variant bits' );
		}
		$this->assertCount( 500, array_unique( $ids ) );
		usleep( 3000 );
		$later = \Wotoyi\Marketplace\Support\Uuid::v7();
		$this->assertTrue( strcmp( substr( $ids[0], 0, 13 ), substr( $later, 0, 13 ) ) < 0, 'timestamp prefix increases' );
	}

	public function test_crypto_roundtrip_and_failure_modes(): void {
		$c = \Wotoyi\Marketplace\Support\Crypto::encrypt( 'top-secret' );
		$this->assertSame( 'top-secret', \Wotoyi\Marketplace\Support\Crypto::decrypt( $c ) );
		$this->assertTrue( $c !== \Wotoyi\Marketplace\Support\Crypto::encrypt( 'top-secret' ), 'random nonce per encryption' );
		$this->assertNull( \Wotoyi\Marketplace\Support\Crypto::decrypt( null ) );
		$this->assertNull( \Wotoyi\Marketplace\Support\Crypto::decrypt( 'v1:' ) );
		$this->assertNull( \Wotoyi\Marketplace\Support\Crypto::decrypt( 'garbage' ) );
		$this->assertTrue( \Wotoyi\Marketplace\Support\Crypto::keyed_hash( 'a', 'x' ) !== \Wotoyi\Marketplace\Support\Crypto::keyed_hash( 'b', 'x' ), 'purpose-separated keys' );
	}
}
