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
	 * Migration 3 — Phase 2: plans, applications, subscriptions, payments,
	 * private documents, website provisioning jobs, support tickets.
	 */
	private static function migration_3() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = array();
		foreach ( array( 'dealers', 'plans', 'applications', 'subscriptions', 'payments', 'documents', 'provisioning_jobs', 'tickets', 'ticket_messages' ) as $name ) {
			$t[ $name ] = self::table( $name );
		}

		// Dealers: two new columns (dbDelta needs the full definition).
		dbDelta(
			"CREATE TABLE {$t['dealers']} (
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
			visibility_boost tinyint(1) NOT NULL DEFAULT 0,
			status_reason varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY city_slug (city_slug),
			KEY user_id (user_id)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['plans']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(40) NOT NULL,
			name varchar(80) NOT NULL,
			tagline varchar(191) NOT NULL DEFAULT '',
			price_amount bigint(20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'XOF',
			vehicle_limit int(10) unsigned NOT NULL DEFAULT 0,
			ai_quota int(10) unsigned NOT NULL DEFAULT 0,
			team_members int(10) unsigned NOT NULL DEFAULT 1,
			features longtext NOT NULL,
			visibility_boost tinyint(1) NOT NULL DEFAULT 0,
			is_highlighted tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(10) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['applications']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_months smallint(5) unsigned NOT NULL DEFAULT 1,
			legal_name varchar(191) NOT NULL DEFAULT '',
			registration_number varchar(80) NOT NULL DEFAULT '',
			tax_id varchar(80) NOT NULL DEFAULT '',
			contact_name varchar(120) NOT NULL DEFAULT '',
			contact_phone varchar(32) NOT NULL DEFAULT '',
			activity varchar(10) NOT NULL DEFAULT 'sale',
			fleet_size varchar(20) NOT NULL DEFAULT '',
			website_option varchar(20) NOT NULL DEFAULT 'later',
			domain varchar(191) NOT NULL DEFAULT '',
			notes text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'submitted',
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewed_at datetime DEFAULT NULL,
			decision_reason varchar(500) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_id (dealer_id),
			KEY status (status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['subscriptions']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			plan_id bigint(20) unsigned NOT NULL,
			application_id bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_months smallint(5) unsigned NOT NULL DEFAULT 1,
			amount bigint(20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'XOF',
			status varchar(20) NOT NULL DEFAULT 'pending_payment',
			starts_at datetime DEFAULT NULL,
			ends_at datetime DEFAULT NULL,
			grace_ends_at datetime DEFAULT NULL,
			reminder_stage tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_status (dealer_id,status),
			KEY status_ends (status,ends_at)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['payments']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			method varchar(30) NOT NULL DEFAULT '',
			amount bigint(20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'XOF',
			reference varchar(40) NOT NULL DEFAULT '',
			payer_reference varchar(120) NOT NULL DEFAULT '',
			proof_document_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			note varchar(500) NOT NULL DEFAULT '',
			verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
			verified_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_id (dealer_id),
			KEY status (status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['documents']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			application_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(30) NOT NULL DEFAULT 'other',
			original_name varchar(191) NOT NULL DEFAULT '',
			path varchar(255) NOT NULL,
			mime varchar(60) NOT NULL DEFAULT '',
			size int(10) unsigned NOT NULL DEFAULT 0,
			uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_id (dealer_id)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['provisioning_jobs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			provider varchar(40) NOT NULL DEFAULT 'manual',
			website_option varchar(20) NOT NULL DEFAULT 'later',
			domain varchar(191) NOT NULL DEFAULT '',
			steps longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			site_url varchar(500) NOT NULL DEFAULT '',
			admin_url varchar(500) NOT NULL DEFAULT '',
			notes text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY dealer_id (dealer_id),
			KEY status (status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['tickets']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			subject varchar(191) NOT NULL,
			category varchar(20) NOT NULL DEFAULT 'other',
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_id (dealer_id),
			KEY status (status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$t['ticket_messages']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is_staff tinyint(1) NOT NULL DEFAULT 0,
			body text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id)
			) $c;"
		);

		Plans::seed();
		Permissions::install_roles();
		update_option( 'amc_flush_rewrites', 1, false );
	}

	/**
	 * Migration 4 — Phase 4: dealer API credentials, sync event log
	 * (idempotency), outbound webhooks, marketplace leads.
	 */
	private static function migration_4() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = static function ( $name ) {
			return self::table( $name );
		};
		dbDelta(
			"CREATE TABLE {$t('api_credentials')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			key_id varchar(40) NOT NULL,
			secret_enc text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			site_url varchar(500) NOT NULL DEFAULT '',
			connector_version varchar(20) NOT NULL DEFAULT '',
			last_handshake_at datetime DEFAULT NULL,
			last_heartbeat_at datetime DEFAULT NULL,
			last_success_at datetime DEFAULT NULL,
			last_error varchar(255) NOT NULL DEFAULT '',
			failures int(10) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			revoked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY key_id (key_id),
			KEY dealer_status (dealer_id,status)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('sync_events')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			idempotency_key varchar(64) NOT NULL DEFAULT '',
			method varchar(10) NOT NULL DEFAULT '',
			route varchar(191) NOT NULL DEFAULT '',
			external_id varchar(64) NOT NULL DEFAULT '',
			status_code smallint(5) unsigned NOT NULL DEFAULT 0,
			response longtext NOT NULL,
			error varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_key (dealer_id,idempotency_key),
			KEY dealer_created (dealer_id,created_at)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('webhook_deliveries')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			delivery_id char(36) NOT NULL,
			event varchar(40) NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			last_error varchar(255) NOT NULL DEFAULT '',
			delivered_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_next (status,next_attempt_at),
			KEY dealer_id (dealer_id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('marketplace_leads')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			vehicle_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(150) NOT NULL,
			phone varchar(32) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			message text NOT NULL,
			channel varchar(20) NOT NULL DEFAULT 'webhook',
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY dealer_created (dealer_id,created_at)
			) $c;"
		);
		update_option( 'amc_flush_rewrites', 1, false );
	}

	/**
	 * Migration 5 — Phases 5 and 6: WhatsApp assistant (contacts, conversations,
	 * messages) and telephony call log.
	 */
	private static function migration_5() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = static function ( $name ) {
			return self::table( $name );
		};
		dbDelta(
			"CREATE TABLE {$t('ai_contacts')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dealer_id bigint(20) unsigned NOT NULL,
			phone varchar(20) NOT NULL,
			user_ref bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(150) NOT NULL DEFAULT '',
			role varchar(60) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			KEY dealer_id (dealer_id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('wa_conversations')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			phone varchar(20) NOT NULL,
			dealer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			history longtext NOT NULL,
			pending_action longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('wa_messages')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wamid varchar(191) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL,
			dealer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			direction varchar(3) NOT NULL DEFAULT 'in',
			kind varchar(20) NOT NULL DEFAULT 'text',
			body text NOT NULL,
			media_id varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'received',
			error varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY wamid (wamid),
			KEY phone_created (phone,created_at),
			KEY dealer_created (dealer_id,created_at)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t('call_logs')} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(30) NOT NULL DEFAULT '',
			call_ref varchar(191) NOT NULL DEFAULT '',
			direction varchar(10) NOT NULL DEFAULT 'inbound',
			from_number varchar(32) NOT NULL DEFAULT '',
			to_number varchar(32) NOT NULL DEFAULT '',
			dealer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT '',
			duration_seconds int(10) unsigned NOT NULL DEFAULT 0,
			transcript longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY provider_ref (provider,call_ref),
			KEY created_at (created_at)
			) $c;"
		);
	}

	/**
	 * Drop every plugin table. Only called from uninstall.php when the
	 * administrator explicitly opted in to data deletion.
	 */
	public static function drop_all() {
		global $wpdb;
		foreach ( array( 'vehicle_media', 'vehicles', 'dealers', 'inquiries', 'audit_log', 'ai_usage', 'plans', 'applications', 'subscriptions', 'payments', 'documents', 'provisioning_jobs', 'tickets', 'ticket_messages', 'api_credentials', 'sync_events', 'webhook_deliveries', 'marketplace_leads', 'ai_contacts', 'wa_conversations', 'wa_messages', 'call_logs' ) as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( self::OPTION );
	}
}
