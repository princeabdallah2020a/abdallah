<?php
/**
 * Table definitions (dbDelta format: one column per line, two spaces after PRIMARY KEY, KEY not INDEX).
 * Tenant scoping: every dealer-owned table carries dealer_id and is only reached through repositories
 * that always filter on it.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Schema;

use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/** @return list<string> Logical table names. */
	public static function names(): array {
		return array( 'vehicles', 'vehicle_equipment', 'dealers', 'services', 'leads', 'favourites', 'saved_searches', 'sync_log', 'sync_nonces' );
	}

	/** @return array<string,string> logical name => CREATE TABLE SQL. */
	public static function create_statements(): array {
		$charset = Db::wpdb()->get_charset_collate();
		$t       = static fn( string $n ): string => Db::table( $n );

		$vehicle_cols = self::field_columns( VehicleFields::defs() );
		$service_cols = self::field_columns( ServiceFields::defs() );

		$sql = array();

		$sql['dealers'] = "CREATE TABLE {$t('dealers')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  source_dealer_uuid VARCHAR(64) DEFAULT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  verified TINYINT(1) NOT NULL DEFAULT 0,
  plan VARCHAR(32) DEFAULT NULL,
  transport VARCHAR(8) NOT NULL DEFAULT 'http',
  email VARCHAR(190) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  whatsapp VARCHAR(40) DEFAULT NULL,
  website_url VARCHAR(500) DEFAULT NULL,
  logo_url VARCHAR(500) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  city VARCHAR(80) DEFAULT NULL,
  area VARCHAR(80) DEFAULT NULL,
  country CHAR(2) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  latitude DECIMAL(9,6) DEFAULT NULL,
  longitude DECIMAL(9,6) DEFAULT NULL,
  languages VARCHAR(40) DEFAULT NULL,
  key_id VARCHAR(40) DEFAULT NULL,
  secret_enc TEXT DEFAULT NULL,
  prev_secret_enc TEXT DEFAULT NULL,
  prev_valid_until DATETIME DEFAULT NULL,
  secret_rotated_at DATETIME DEFAULT NULL,
  pairing_hash CHAR(64) DEFAULT NULL,
  pairing_expires_at DATETIME DEFAULT NULL,
  paired_at DATETIME DEFAULT NULL,
  last_seen_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY slug (slug),
  UNIQUE KEY source_dealer_uuid (source_dealer_uuid),
  UNIQUE KEY key_id (key_id),
  KEY pairing_hash (pairing_hash),
  KEY status_verified (status,verified),
  KEY city (city)
) $charset;";

		$sql['vehicles'] = "CREATE TABLE {$t('vehicles')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  dealer_id BIGINT(20) UNSIGNED NOT NULL,
  source_id VARCHAR(64) NOT NULL,
  version BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  slug VARCHAR(190) NOT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  views INT(10) UNSIGNED NOT NULL DEFAULT 0,
  photo_count SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  has_video TINYINT(1) NOT NULL DEFAULT 0,
  has_360 TINYINT(1) NOT NULL DEFAULT 0,
  search_text TEXT DEFAULT NULL,
$vehicle_cols  published_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY slug (slug),
  UNIQUE KEY dealer_source (dealer_id,source_id),
  KEY st_pub (status,published_at),
  KEY st_dealer (status,dealer_id),
  KEY st_make (status,make,model),
  KEY st_price (status,price),
  KEY st_year (status,year),
  KEY st_mileage (status,mileage_km),
  KEY st_fuel (status,fuel_type),
  KEY st_body (status,body_type),
  KEY st_trans (status,transmission),
  KEY st_drive (status,drive_type),
  KEY st_cond (status,condition_type),
  KEY st_offer (status,offer_type),
  KEY st_city (status,city),
  KEY st_geo (status,latitude,longitude),
  KEY st_views (status,views),
  KEY st_feat (status,featured,published_at),
  KEY st_origin (status,origin_country),
  KEY st_power (status,power_hp),
  KEY st_seats (status,seats),
  KEY st_colour (status,ext_colour),
  KEY st_rental (status,rental_price_month),
  KEY st_customs (status,customs_status)
) $charset;";

		$sql['vehicle_equipment'] = "CREATE TABLE {$t('vehicle_equipment')} (
  vehicle_id BIGINT(20) UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  category VARCHAR(24) NOT NULL,
  PRIMARY KEY  (vehicle_id,code),
  KEY code_vehicle (code,vehicle_id)
) $charset;";

		$sql['services'] = "CREATE TABLE {$t('services')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  dealer_id BIGINT(20) UNSIGNED NOT NULL,
  source_id VARCHAR(64) NOT NULL,
  version BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  slug VARCHAR(190) NOT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  views INT(10) UNSIGNED NOT NULL DEFAULT 0,
  search_text TEXT DEFAULT NULL,
$service_cols  published_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY slug (slug),
  UNIQUE KEY dealer_source (dealer_id,source_id),
  KEY st_pub (status,published_at),
  KEY st_cat (status,category),
  KEY st_city (status,city),
  KEY st_dealer (status,dealer_id),
  KEY st_price (status,price_from)
) $charset;";

		$sql['leads'] = "CREATE TABLE {$t('leads')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  dealer_id BIGINT(20) UNSIGNED NOT NULL,
  vehicle_id BIGINT(20) UNSIGNED DEFAULT NULL,
  service_id BIGINT(20) UNSIGNED DEFAULT NULL,
  user_id BIGINT(20) UNSIGNED DEFAULT NULL,
  name VARCHAR(120) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  channel VARCHAR(16) NOT NULL DEFAULT 'web',
  locale VARCHAR(8) DEFAULT NULL,
  consent TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'new',
  delivery_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  delivery_attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  delivered_at DATETIME DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY dealer_delivery (dealer_id,delivery_status,id),
  KEY vehicle_id (vehicle_id),
  KEY user_id (user_id)
) $charset;";

		$sql['favourites'] = "CREATE TABLE {$t('favourites')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) UNSIGNED NOT NULL,
  item_type VARCHAR(10) NOT NULL,
  item_id BIGINT(20) UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_item (user_id,item_type,item_id),
  KEY item (item_type,item_id)
) $charset;";

		$sql['saved_searches'] = "CREATE TABLE {$t('saved_searches')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  filters LONGTEXT NOT NULL,
  filters_hash CHAR(64) NOT NULL,
  notify TINYINT(1) NOT NULL DEFAULT 0,
  last_notified_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_hash (user_id,filters_hash),
  KEY user_id (user_id)
) $charset;";

		$sql['sync_log'] = "CREATE TABLE {$t('sync_log')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  dealer_id BIGINT(20) UNSIGNED NOT NULL,
  event_id VARCHAR(64) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  entity_type VARCHAR(16) DEFAULT NULL,
  entity_source_id VARCHAR(64) DEFAULT NULL,
  entity_id BIGINT(20) UNSIGNED DEFAULT NULL,
  version BIGINT(20) UNSIGNED DEFAULT NULL,
  transport VARCHAR(8) NOT NULL DEFAULT 'http',
  status VARCHAR(16) NOT NULL DEFAULT 'received',
  http_status SMALLINT(5) UNSIGNED DEFAULT NULL,
  result LONGTEXT DEFAULT NULL,
  message VARCHAR(255) DEFAULT NULL,
  request_id VARCHAR(64) DEFAULT NULL,
  received_at DATETIME NOT NULL,
  processed_at DATETIME DEFAULT NULL,
  duration_ms INT(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY dealer_event (dealer_id,event_id),
  KEY dealer_id_id (dealer_id,id),
  KEY status_received (status,received_at)
) $charset;";

		$sql['sync_nonces'] = "CREATE TABLE {$t('sync_nonces')} (
  dealer_id BIGINT(20) UNSIGNED NOT NULL,
  nonce VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY  (dealer_id,nonce),
  KEY expires_at (expires_at)
) $charset;";

		return $sql;
	}

	/** One column per line, already indented, ending with a comma + newline. */
	private static function field_columns( array $defs ): string {
		$out = '';
		foreach ( $defs as $name => $def ) {
			$out .= '  ' . Fields::column_sql( $name, $def ) . ",\n";
		}
		return $out;
	}
}
