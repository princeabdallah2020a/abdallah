<?php
/**
 * Table definitions (dbDelta format). Every table except `tenants` is TENANT-SCOPED: it has a tenant_id column
 * and is only reachable through Data\TenantDb, which adds `tenant_id = <current tenant>` to every statement.
 * Requires InnoDB (transactions): this is the default engine of current MySQL/MariaDB.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Schema;

use Wotoyi\DealerPlatform\Data\TenantDb;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/** @var array<string,list<string>>|null */
	private static ?array $columns = null;

	/** @return list<string> Tables that are NOT tenant scoped (only Data\Directory touches them). */
	public static function global_names(): array {
		return array( 'tenants' );
	}

	/** @return list<string> */
	public static function scoped_names(): array {
		return array( 'members', 'vehicles', 'services', 'customers', 'leads', 'deals', 'rentals', 'employees', 'tasks', 'activities', 'outbox', 'audit_log', 'sync_state' );
	}

	/** @return list<string> */
	public static function names(): array {
		return array_merge( self::global_names(), self::scoped_names() );
	}

	public static function is_scoped( string $name ): bool {
		return in_array( $name, self::scoped_names(), true );
	}

	/** @return list<string> Column names of a table (parsed from the generated DDL: single source of truth). */
	public static function columns( string $name ): array {
		if ( null === self::$columns ) {
			self::$columns = array();
			foreach ( self::definitions() as $table => $sql ) {
				preg_match_all( '/^\s{2}(\w+) [A-Z]/m', $sql, $m );
				self::$columns[ $table ] = array_values( array_filter( $m[1], static fn( string $c ): bool => ! in_array( $c, array( 'PRIMARY', 'UNIQUE', 'KEY' ), true ) ) );
			}
		}
		if ( ! isset( self::$columns[ $name ] ) ) {
			throw new \InvalidArgumentException( 'unknown_table:' . $name );
		}
		return self::$columns[ $name ];
	}

	/** @return array<string,string> logical name => CREATE TABLE SQL. */
	public static function create_statements(): array {
		return self::definitions();
	}

	/** @param array<string,array> $defs */
	private static function field_columns( array $defs ): string {
		$out = '';
		foreach ( $defs as $name => $def ) {
			$out .= '  ' . Fields::column_sql( $name, $def ) . ",\n";
		}
		return $out;
	}

	/** Standard columns of a tenant entity table. */
	private static function head( bool $versioned = true ): string {
		return "  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n  tenant_id BIGINT(20) UNSIGNED NOT NULL,\n  uuid CHAR(36) NOT NULL,\n"
			. ( $versioned ? "  version BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,\n" : '' );
	}

	/** @return array<string,string> */
	private static function definitions(): array {
		$charset = TenantDb::charset_collate();
		$t       = static fn( string $n ): string => TenantDb::table_name( $n );
		$sql     = array();

		$sql['tenants'] = "CREATE TABLE {$t('tenants')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  plan VARCHAR(32) DEFAULT NULL,
  country CHAR(2) DEFAULT NULL,
  city VARCHAR(80) DEFAULT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'XOF',
  locale VARCHAR(8) NOT NULL DEFAULT 'fr',
  settings LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY slug (slug),
  KEY status (status)
) $charset;";

		$sql['members'] = "CREATE TABLE {$t('members')} (
" . self::head() . "  user_id BIGINT(20) UNSIGNED NOT NULL,
  role VARCHAR(16) NOT NULL,
  display_name VARCHAR(120) DEFAULT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tenant_uuid (tenant_id,uuid),
  UNIQUE KEY tenant_user (tenant_id,user_id),
  KEY user_id (user_id)
) $charset;";

		$sql['vehicles'] = "CREATE TABLE {$t('vehicles')} (
" . self::head() . "  status VARCHAR(16) NOT NULL DEFAULT 'draft',
" . self::field_columns( VehicleFields::defs() ) . "  created_by BIGINT(20) UNSIGNED DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  sold_at DATETIME DEFAULT NULL,
  sold_price BIGINT(20) UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tenant_uuid (tenant_id,uuid),
  KEY tenant_status (tenant_id,status),
  KEY tenant_make (tenant_id,make,model),
  KEY tenant_created (tenant_id,created_at),
  KEY tenant_stock (tenant_id,stock_ref)
) $charset;";

		$sql['services'] = "CREATE TABLE {$t('services')} (
" . self::head() . "  status VARCHAR(16) NOT NULL DEFAULT 'draft',
" . self::field_columns( ServiceFields::defs() ) . "  created_by BIGINT(20) UNSIGNED DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tenant_uuid (tenant_id,uuid),
  KEY tenant_status (tenant_id,status),
  KEY tenant_category (tenant_id,category)
) $charset;";

		foreach ( Entities::all() as $name => $e ) {
			$fields = $e['fields'];
			if ( 'deals' === $name || 'rentals' === $name || 'employees' === $name || 'tasks' === $name || 'leads' === $name ) {
				// Entities with their own `status`/`stage` field already declare it in fields; defaults are applied by services.
			}
			$cols  = self::head();
			$cols .= self::field_columns( $fields );
			$cols .= "  created_by BIGINT(20) UNSIGNED DEFAULT NULL,\n  created_at DATETIME NOT NULL,\n  updated_at DATETIME NOT NULL,\n";
			if ( ! empty( $e['soft_delete'] ) ) {
				$cols .= "  deleted_at DATETIME DEFAULT NULL,\n";
			}
			$keys = "  PRIMARY KEY  (id),\n  UNIQUE KEY tenant_uuid (tenant_id,uuid)";
			foreach ( $e['keys'] ?? array() as $k ) {
				$keys .= ",\n  " . $k;
			}
			$sql[ $name ] = "CREATE TABLE {$t($name)} (\n{$cols}{$keys}\n) $charset;";
		}

		$sql['outbox'] = "CREATE TABLE {$t('outbox')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT(20) UNSIGNED NOT NULL,
  event_id VARCHAR(64) NOT NULL,
  entity_type VARCHAR(16) NOT NULL,
  entity_uuid CHAR(36) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  entity_version BIGINT(20) UNSIGNED NOT NULL,
  payload LONGTEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  locked_until DATETIME DEFAULT NULL,
  lock_token CHAR(32) DEFAULT NULL,
  result_status VARCHAR(16) DEFAULT NULL,
  last_http_status SMALLINT(5) UNSIGNED DEFAULT NULL,
  last_error VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tenant_event (tenant_id,event_id),
  KEY tenant_due (tenant_id,status,next_attempt_at),
  KEY tenant_entity (tenant_id,entity_uuid),
  KEY due_global (status,next_attempt_at)
) $charset;";

		$sql['audit_log'] = "CREATE TABLE {$t('audit_log')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT(20) UNSIGNED NOT NULL,
  user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(24) DEFAULT NULL,
  entity_uuid CHAR(36) DEFAULT NULL,
  meta TEXT DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY tenant_created (tenant_id,created_at),
  KEY tenant_entity (tenant_id,entity_type,entity_uuid),
  KEY tenant_action (tenant_id,action)
) $charset;";

		$sql['sync_state'] = "CREATE TABLE {$t('sync_state')} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT(20) UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'unpaired',
  transport VARCHAR(8) DEFAULT NULL,
  base_url VARCHAR(255) DEFAULT NULL,
  key_id VARCHAR(40) DEFAULT NULL,
  secret_enc TEXT DEFAULT NULL,
  marketplace_dealer_id CHAR(36) DEFAULT NULL,
  paired_at DATETIME DEFAULT NULL,
  last_push_at DATETIME DEFAULT NULL,
  last_pull_at DATETIME DEFAULT NULL,
  last_reconcile_at DATETIME DEFAULT NULL,
  last_reconcile_summary TEXT DEFAULT NULL,
  last_error VARCHAR(255) DEFAULT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tenant_id (tenant_id),
  KEY status (status)
) $charset;";

		return $sql;
	}
}
