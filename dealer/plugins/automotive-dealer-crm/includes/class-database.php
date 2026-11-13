<?php
/**
 * Schema and versioned migrations. Each dealer website has its own database:
 * nothing here is shared with other dealers or with the marketplace.
 *
 * @package AutomotiveDealerCrm
 */

namespace ADC;

defined( 'ABSPATH' ) || exit;

final class Database {

	const OPTION = 'adc_db_version';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'adc_' . $name;
	}

	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) < ADC_DB_VERSION ) {
			self::migrate();
		}
	}

	public static function migrate() {
		$current = (int) get_option( self::OPTION, 0 );
		for ( $v = $current + 1; $v <= ADC_DB_VERSION; $v++ ) {
			$method = 'migration_' . $v;
			if ( method_exists( __CLASS__, $method ) ) {
				self::$method();
			}
			update_option( self::OPTION, $v, false );
		}
	}

	private static function migration_1() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = static function ( $name ) {
			return self::table( $name );
		};

		dbDelta(
			"CREATE TABLE {$t('vehicles')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uid char(36) NOT NULL,
			slug varchar(191) NOT NULL,
			title varchar(191) NOT NULL,
			brand varchar(80) NOT NULL,
			model varchar(80) NOT NULL,
			year smallint(4) unsigned NOT NULL DEFAULT 0,
			mileage_km int(10) unsigned NOT NULL DEFAULT 0,
			fuel_type varchar(20) NOT NULL DEFAULT '',
			transmission varchar(20) NOT NULL DEFAULT '',
			body_type varchar(20) NOT NULL DEFAULT '',
			vehicle_condition varchar(20) NOT NULL DEFAULT '',
			color varchar(40) NOT NULL DEFAULT '',
			vin varchar(40) NOT NULL DEFAULT '',
			listing_type varchar(10) NOT NULL DEFAULT 'sale',
			price_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			price_period varchar(10) NOT NULL DEFAULT 'total',
			currency char(3) NOT NULL DEFAULT 'XOF',
			purchase_price bigint(20) unsigned NOT NULL DEFAULT 0,
			description longtext NOT NULL,
			features longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			is_featured tinyint(1) NOT NULL DEFAULT 0,
			publish_to_marketplace tinyint(1) NOT NULL DEFAULT 1,
			thumbnail_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			published_at datetime DEFAULT NULL,
			sold_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uid (uid),
			UNIQUE KEY slug (slug),
			KEY status_type (status,listing_type),
			KEY brand (brand)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('vehicle_media')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL,
			position smallint(5) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY vehicle_position (vehicle_id,position)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('customers')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'individual',
			name varchar(150) NOT NULL,
			phone varchar(32) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			id_number varchar(80) NOT NULL DEFAULT '',
			notes text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY name (name)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('files')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sale_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rental_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(120) NOT NULL DEFAULT '',
			original_name varchar(191) NOT NULL DEFAULT '',
			path varchar(255) NOT NULL,
			mime varchar(60) NOT NULL DEFAULT '',
			size int(10) unsigned NOT NULL DEFAULT 0,
			uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('leads')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL,
			phone varchar(32) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			vehicle_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			budget_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			interest varchar(10) NOT NULL DEFAULT 'sale',
			source varchar(20) NOT NULL DEFAULT 'manual',
			status varchar(20) NOT NULL DEFAULT 'new',
			assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
			follow_up_at datetime DEFAULT NULL,
			lost_reason varchar(255) NOT NULL DEFAULT '',
			message text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY assigned_to (assigned_to),
			KEY follow_up_at (follow_up_at)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('lead_notes')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'note',
			body text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('sales')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			salesperson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sale_price bigint(20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'XOF',
			sold_on date NOT NULL,
			payment_method varchar(30) NOT NULL DEFAULT '',
			payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
			amount_paid bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			notes text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vehicle_id (vehicle_id),
			KEY customer_id (customer_id),
			KEY status_date (status,sold_on)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('rentals')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vehicle_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			starts_at datetime NOT NULL,
			ends_at datetime NOT NULL,
			rate_unit varchar(10) NOT NULL DEFAULT 'day',
			rate_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			units int(10) unsigned NOT NULL DEFAULT 1,
			total_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			deposit_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'XOF',
			payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
			status varchar(20) NOT NULL DEFAULT 'quote',
			with_driver tinyint(1) NOT NULL DEFAULT 0,
			pickup_mileage int(10) unsigned NOT NULL DEFAULT 0,
			return_mileage int(10) unsigned NOT NULL DEFAULT 0,
			return_notes text NOT NULL,
			returned_at datetime DEFAULT NULL,
			notes text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vehicle_status (vehicle_id,status),
			KEY period (starts_at,ends_at)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('appointments')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			vehicle_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			starts_at datetime NOT NULL,
			title varchar(191) NOT NULL DEFAULT '',
			notes text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY starts_at (starts_at),
			KEY lead_id (lead_id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('invites')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(191) NOT NULL,
			name varchar(120) NOT NULL DEFAULT '',
			role varchar(40) NOT NULL,
			caps text NOT NULL,
			token_hash char(64) NOT NULL,
			invited_by bigint(20) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			accepted_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY token_hash (token_hash)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('activity')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(60) NOT NULL,
			object_type varchar(30) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			summary varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY object (object_type,object_id)
			) $c;"
		);
		update_option( 'adc_flush_rewrites', 1, false );
	}
}
