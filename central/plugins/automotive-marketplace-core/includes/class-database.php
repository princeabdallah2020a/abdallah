<?php
/**
 * Database schema and versioned migrations for the central marketplace.
 *
 * Every schema change is a new numbered migration method. Never edit a
 * migration that has already shipped: add a new one and bump AMC_DB_VERSION.
 *
 * @package AutomotiveMarketplaceCore
 */

namespace AMC;

defined( 'ABSPATH' ) || exit;

final class Database {

	const OPTION = 'amc_db_version';

	/**
	 * Fully-qualified table name.
	 *
	 * @param string $name Short name: dealers, vehicles, vehicle_media, inquiries, audit_log.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'amc_' . $name;
	}

	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) < AMC_DB_VERSION ) {
			self::migrate();
		}
	}

	public static function migrate() {
		$current = (int) get_option( self::OPTION, 0 );
		for ( $version = $current + 1; $version <= AMC_DB_VERSION; $version++ ) {
			$method = 'migration_' . $version;
			if ( method_exists( __CLASS__, $method ) ) {
				self::$method();
			}
			update_option( self::OPTION, $version, false );
		}
	}

	/**
	 * Migration 1 — Phase 1 tables: dealers, vehicles, media, inquiries, audit log.
	 */
	private static function migration_1() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$dealers  = self::table( 'dealers' );
		$vehicles = self::table( 'vehicles' );
		$media    = self::table( 'vehicle_media' );
		$inquiry  = self::table( 'inquiries' );
		$audit    = self::table( 'audit_log' );

		dbDelta(
			"CREATE TABLE $dealers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			slug varchar(191) NOT NULL,
			business_name varchar(191) NOT NULL,
			logo_url varchar(500) NOT NULL DEFAULT '',
			description text NOT NULL,
			address varchar(255) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			city_slug varchar(100) NOT NULL DEFAULT '',
			country_code char(2) NOT NULL DEFAULT 'SN',
			phone varchar(32) NOT NULL DEFAULT '',
			whatsapp varchar(32) NOT NULL DEFAULT '',
			contact_email varchar(191) NOT NULL DEFAULT '',
			website_url varchar(500) NOT NULL DEFAULT '',
			is_verified tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			sync_mode varchar(10) NOT NULL DEFAULT 'api',
			is_demo tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY city_slug (city_slug),
			KEY user_id (user_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $vehicles (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			external_id varchar(64) NOT NULL,
			slug varchar(191) NOT NULL,
			title varchar(191) NOT NULL,
			brand varchar(80) NOT NULL,
			brand_slug varchar(80) NOT NULL,
			model varchar(80) NOT NULL,
			model_slug varchar(80) NOT NULL,
			year smallint(4) unsigned NOT NULL DEFAULT 0,
			mileage_km int(10) unsigned NOT NULL DEFAULT 0,
			fuel_type varchar(20) NOT NULL DEFAULT '',
			transmission varchar(20) NOT NULL DEFAULT '',
			body_type varchar(20) NOT NULL DEFAULT '',
			vehicle_condition varchar(20) NOT NULL DEFAULT '',
			color varchar(40) NOT NULL DEFAULT '',
			listing_type varchar(10) NOT NULL DEFAULT 'sale',
			price_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			price_period varchar(10) NOT NULL DEFAULT 'total',
			currency char(3) NOT NULL DEFAULT 'XOF',
			city varchar(100) NOT NULL DEFAULT '',
			city_slug varchar(100) NOT NULL DEFAULT '',
			country_code char(2) NOT NULL DEFAULT 'SN',
			description longtext NOT NULL,
			features longtext NOT NULL,
			thumbnail_url varchar(500) NOT NULL DEFAULT '',
			source_url varchar(500) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			is_featured tinyint(1) NOT NULL DEFAULT 0,
			featured_until datetime DEFAULT NULL,
			is_demo tinyint(1) NOT NULL DEFAULT 0,
			source_updated_at datetime DEFAULT NULL,
			last_synced_at datetime DEFAULT NULL,
			published_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dealer_external (dealer_id,external_id),
			UNIQUE KEY slug (slug),
			KEY catalog (status,listing_type,published_at),
			KEY brand_model (brand_slug,model_slug),
			KEY price_amount (price_amount),
			KEY year (year),
			KEY mileage_km (mileage_km),
			KEY city_slug (city_slug),
			KEY is_featured (is_featured)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $media (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			url varchar(500) NOT NULL,
			alt varchar(191) NOT NULL DEFAULT '',
			width int(10) unsigned NOT NULL DEFAULT 0,
			height int(10) unsigned NOT NULL DEFAULT 0,
			position smallint(5) unsigned NOT NULL DEFAULT 0,
			source_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vehicle_position (vehicle_id,position)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $inquiry (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'contact',
			name varchar(120) NOT NULL,
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			company varchar(191) NOT NULL DEFAULT '',
			subject varchar(191) NOT NULL DEFAULT '',
			message text NOT NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_created (status,created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(80) NOT NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			context longtext NOT NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action_created (action,created_at),
			KEY object (object_type,object_id)
			) $charset;"
		);
	}

	/**
	 * Migration 2 — AI assistant usage log (cost control and reporting).
	 */
	private static function migration_2() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table( 'ai_usage' );
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope varchar(20) NOT NULL DEFAULT 'visitor',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_hash char(64) NOT NULL DEFAULT '',
			model varchar(60) NOT NULL DEFAULT '',
			input_tokens int(10) unsigned NOT NULL DEFAULT 0,
			output_tokens int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY scope_created (scope,created_at)
			) $charset;"
		);
	}

	/**
	 * Drop every plugin table. Only called from uninstall.php when the
	 * administrator explicitly opted in to data deletion.
	 */
	public static function drop_all() {
		global $wpdb;
		foreach ( array( 'vehicle_media', 'vehicles', 'dealers', 'inquiries', 'audit_log', 'ai_usage' ) as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( self::OPTION );
	}
}
