<?php
/**
 * Activation, upgrades, capability, cron.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace;

use Wotoyi\Marketplace\Schema\Schema;
use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public const CAP        = 'wotoyi_manage_marketplace';
	public const DB_OPTION  = 'wotoyi_mp_db_version';
	public const CRON_HOOK  = 'wotoyi_mp_prune';

	public static function activate(): void {
		self::install_tables();
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( self::CAP );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function install_tables(): void {
		if ( defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		foreach ( Schema::create_statements() as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::DB_OPTION, WOTOYI_MP_DB_VERSION, false );
	}

	/** Run on every load: cheap version compare. */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_OPTION ) !== WOTOYI_MP_DB_VERSION ) {
			self::install_tables();
		}
	}

	/** Destructive: only from uninstall.php when the admin opted in. */
	public static function drop_all(): void {
		foreach ( Schema::names() as $name ) {
			Db::wpdb()->query( 'DROP TABLE IF EXISTS ' . Db::table( $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		}
		delete_option( self::DB_OPTION );
		delete_option( Settings::OPTION );
		delete_option( 'wotoyi_mp_cache_ver' );
	}
}
