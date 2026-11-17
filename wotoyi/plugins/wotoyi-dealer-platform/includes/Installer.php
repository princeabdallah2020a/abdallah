<?php
/**
 * Activation, upgrades, capability, cron.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform;

use Wotoyi\DealerPlatform\Auth\Roles;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Schema\Schema;
use Wotoyi\DealerPlatform\Sync\Worker;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public const DB_OPTION  = 'wotoyi_dp_db_version';
	public const HOOK_PRUNE = 'wotoyi_dp_prune';

	public static function activate(): void {
		self::install_tables();
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( Roles::PLATFORM_CAP );
		}
		if ( ! wp_next_scheduled( Worker::HOOK_TICK ) ) {
			wp_schedule_event( time() + 60, 'wotoyi_dp_minute', Worker::HOOK_TICK );
		}
		if ( ! wp_next_scheduled( Worker::HOOK_RECONCILE ) ) {
			wp_schedule_event( strtotime( 'tomorrow 02:30 UTC' ) ?: time() + DAY_IN_SECONDS, 'daily', Worker::HOOK_RECONCILE );
		}
		if ( ! wp_next_scheduled( self::HOOK_PRUNE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_PRUNE );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( Worker::HOOK_TICK );
		wp_clear_scheduled_hook( Worker::HOOK_RECONCILE );
		wp_clear_scheduled_hook( self::HOOK_PRUNE );
	}

	public static function install_tables(): void {
		if ( defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		foreach ( Schema::create_statements() as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::DB_OPTION, WOTOYI_DP_DB_VERSION, false );
	}

	/** Cheap version compare on every load. */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_OPTION ) !== WOTOYI_DP_DB_VERSION ) {
			self::install_tables();
		}
	}

	/** Destructive: only from uninstall.php when the admin opted in. */
	public static function drop_all(): void {
		foreach ( Schema::names() as $name ) {
			TenantDb::wpdb()->query( 'DROP TABLE IF EXISTS ' . TenantDb::table_name( $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		}
		delete_option( self::DB_OPTION );
		delete_option( Settings::OPTION );
	}
}
