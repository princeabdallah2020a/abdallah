<?php
/**
 * Dealer records, pairing codes and per-dealer HMAC credentials.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Schema\Enums;
use Wotoyi\Marketplace\Schema\Fields;
use Wotoyi\Marketplace\Support\Cache;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;
use Wotoyi\Marketplace\Support\Db;
use Wotoyi\Marketplace\Support\Uuid;

defined( 'ABSPATH' ) || exit;

final class DealerRepository {

	public function table(): string {
		return Db::table( 'dealers' );
	}

	/** @return array<string,array> Profile field definitions (editable by admins / pairing). */
	public static function profile_defs(): array {
		return array(
			'name'        => array( 'group' => 'profile', 'type' => 'string', 'len' => 160 ),
			'phone'       => array( 'group' => 'profile', 'type' => 'string', 'len' => 40 ),
			'whatsapp'    => array( 'group' => 'profile', 'type' => 'string', 'len' => 40 ),
			'email'       => array( 'group' => 'profile', 'type' => 'string', 'len' => 190 ),
			'website_url' => array( 'group' => 'profile', 'type' => 'url' ),
			'logo_url'    => array( 'group' => 'profile', 'type' => 'url' ),
			'description' => array( 'group' => 'profile', 'type' => 'text' ),
			'city'        => array( 'group' => 'profile', 'type' => 'string', 'len' => 80 ),
			'area'        => array( 'group' => 'profile', 'type' => 'string', 'len' => 80 ),
			'country'     => array( 'group' => 'profile', 'type' => 'country' ),
			'address'     => array( 'group' => 'profile', 'type' => 'string', 'len' => 255 ),
			'latitude'    => array( 'group' => 'profile', 'type' => 'decimal', 'precision' => 9, 'scale' => 6, 'min' => -90, 'max' => 90 ),
			'longitude'   => array( 'group' => 'profile', 'type' => 'decimal', 'precision' => 9, 'scale' => 6, 'min' => -180, 'max' => 180 ),
			'languages'   => array( 'group' => 'profile', 'type' => 'string', 'len' => 40 ),
		);
	}

	/**
	 * Sanitise a dealer profile payload (partial: absent keys are not returned).
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function sanitize_profile( array $input ): array {
		$out = array();
		foreach ( self::profile_defs() as $name => $def ) {
			if ( ! array_key_exists( $name, $input ) ) {
				continue;
			}
			$value = Fields::sanitize_value( $def, $input[ $name ] );
			if ( 'email' === $name && null !== $value ) {
				$value = is_email( sanitize_email( (string) $value ) ) ? sanitize_email( (string) $value ) : null;
			}
			if ( in_array( $name, array( 'phone', 'whatsapp' ), true ) && null !== $value ) {
				$value = preg_replace( '/[^0-9+()\- ]/', '', (string) $value );
				$value = '' === $value ? null : $value;
			}
			if ( 'languages' === $name && null !== $value ) {
				$langs = array_values( array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $value ) ) ), Enums::LANGS ) );
				$value = $langs ? implode( ',', $langs ) : null;
			}
			if ( 'decimal' === $def['type'] && null !== $value ) {
				$value = number_format( (float) $value, 6, '.', '' );
			}
			$out[ $name ] = $value;
		}
		return $out;
	}

	/**
	 * Create a dealer in `pending` state (no credentials until paired).
	 *
	 * @param array<string,mixed> $profile Raw profile (sanitised here). `name` is required.
	 * @return array<string,mixed>|null Dealer row, or null when the name is missing.
	 */
	public function create( array $profile, string $transport = 'http', ?string $source_dealer_uuid = null ): ?array {
		$clean = $this->sanitize_profile( $profile );
		if ( empty( $clean['name'] ) ) {
			return null;
		}
		$uuid = Uuid::v7();
		$now  = Clock::mysql();
		$data = $clean + array(
			'uuid'               => $uuid,
			'source_dealer_uuid' => $source_dealer_uuid,
			'slug'               => sanitize_title( (string) $clean['name'] ) . '-' . substr( str_replace( '-', '', $uuid ), -6 ),
			'status'             => 'pending',
			'verified'           => 0,
			'transport'          => 'local' === $transport ? 'local' : 'http',
			'created_at'         => $now,
			'updated_at'         => $now,
		);
		if ( false === Db::wpdb()->insert( $this->table(), $data ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return null;
		}
		return $this->find_by_id( (int) Db::wpdb()->insert_id );
	}

	/** @param array<string,mixed> $profile */
	public function update_profile( int $id, array $profile ): void {
		$clean = $this->sanitize_profile( $profile );
		unset( $clean['name'] ); // Name cannot be blanked through partial updates.
		if ( isset( $profile['name'] ) ) {
			$name = $this->sanitize_profile( array( 'name' => $profile['name'] ) );
			if ( ! empty( $name['name'] ) ) {
				$clean['name'] = $name['name'];
			}
		}
		if ( $clean ) {
			$clean['updated_at'] = Clock::mysql();
			Db::wpdb()->update( $this->table(), $clean, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Cache::bump();
		}
	}

	/** @return array<string,mixed>|null */
	private function find_where( string $column, string|int $value ): ?array {
		$placeholder = is_int( $value ) ? '%d' : '%s';
		$sql         = Db::prepare( "SELECT * FROM {$this->table()} WHERE {$column} = {$placeholder} LIMIT 1", array( $value ) ); // $column is a hard-coded literal from the callers below.
		$row         = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	public function find_by_id( int $id ): ?array {
		return $this->find_where( 'id', $id );
	}

	public function find_by_uuid( string $uuid ): ?array {
		return $this->find_where( 'uuid', $uuid );
	}

	public function find_by_key_id( string $key_id ): ?array {
		return $this->find_where( 'key_id', $key_id );
	}

	public function find_by_source_uuid( string $source_uuid ): ?array {
		return $this->find_where( 'source_dealer_uuid', $source_uuid );
	}

	/** Marketplace UUID first, then the Dealer Platform's own id. */
	public function find_by_ref( string $ref ): ?array {
		return $this->find_by_uuid( $ref ) ?? $this->find_by_source_uuid( $ref );
	}

	/* ---------------- Pairing ---------------- */

	/** Issue a single-use pairing code (replaces any pending one). Returns the clear code ONCE. */
	public function issue_pairing_code( int $dealer_id, int $ttl ): string {
		$code = Crypto::pairing_code();
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'pairing_hash'       => Crypto::keyed_hash( 'pairing', $code ),
				'pairing_expires_at' => Clock::mysql( Clock::now() + $ttl ),
				'updated_at'         => Clock::mysql(),
			),
			array( 'id' => $dealer_id )
		);
		return $code;
	}

	/** @return array<string,mixed>|null Dealer whose unexpired pairing code matches. */
	public function find_by_pairing_code( string $normalized_code ): ?array {
		$hash = Crypto::keyed_hash( 'pairing', $normalized_code );
		$sql  = Db::prepare( "SELECT * FROM {$this->table()} WHERE pairing_hash = %s AND pairing_expires_at > %s LIMIT 1", array( $hash, Clock::mysql() ) );
		$row  = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}

	/** Atomically consume the code; true only for the one caller that wins. */
	public function consume_pairing_code( int $dealer_id, string $normalized_code ): bool {
		$hash = Crypto::keyed_hash( 'pairing', $normalized_code );
		$sql  = Db::prepare( "UPDATE {$this->table()} SET pairing_hash = NULL, pairing_expires_at = NULL WHERE id = %d AND pairing_hash = %s AND pairing_expires_at > %s", array( $dealer_id, $hash, Clock::mysql() ) );
		Db::wpdb()->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return 1 === (int) Db::wpdb()->rows_affected;
	}

	/**
	 * Store fresh credentials and activate. Returns the clear secret (shown once to the dealer platform).
	 *
	 * @return array{key_id:string,secret:string}
	 */
	public function issue_credentials( int $dealer_id, ?string $source_dealer_uuid ): array {
		$key_id = 'wk_' . bin2hex( random_bytes( 8 ) );
		$secret = Crypto::token( 32 );
		$now    = Clock::mysql();
		$data   = array(
			'key_id'            => $key_id,
			'secret_enc'        => Crypto::encrypt( $secret ),
			'prev_secret_enc'   => null,
			'prev_valid_until'  => null,
			'secret_rotated_at' => $now,
			'status'            => 'active',
			'paired_at'         => $now,
			'disconnected_at'   => null,
			'updated_at'        => $now,
		);
		if ( null !== $source_dealer_uuid ) {
			$data['source_dealer_uuid'] = $source_dealer_uuid;
		}
		Db::wpdb()->update( $this->table(), $data, array( 'id' => $dealer_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Cache::bump();
		return array( 'key_id' => $key_id, 'secret' => $secret );
	}

	/** Rotate the secret; the old one stays valid for the grace period. @return array{key_id:string,secret:string} */
	public function rotate_secret( array $dealer, int $grace ): array {
		$secret = Crypto::token( 32 );
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'prev_secret_enc'   => $dealer['secret_enc'],
				'prev_valid_until'  => Clock::mysql( Clock::now() + $grace ),
				'secret_enc'        => Crypto::encrypt( $secret ),
				'secret_rotated_at' => Clock::mysql(),
				'updated_at'        => Clock::mysql(),
			),
			array( 'id' => (int) $dealer['id'] )
		);
		return array( 'key_id' => (string) $dealer['key_id'], 'secret' => $secret );
	}

	/**
	 * Secrets currently accepted for signature checks.
	 *
	 * @return list<string>
	 */
	public function accepted_secrets( array $dealer ): array {
		return array_values( $this->accepted_secrets_by_slot( $dealer ) );
	}

	/**
	 * Same as accepted_secrets() but keyed by slot ('current' first, then 'previous' during the grace period),
	 * so the Authenticator knows which one matched (H1).
	 *
	 * @return array<string,string>
	 */
	public function accepted_secrets_by_slot( array $dealer ): array {
		$out     = array();
		$current = Crypto::decrypt( $dealer['secret_enc'] ?? null );
		if ( null !== $current ) {
			$out['current'] = $current;
		}
		if ( ! empty( $dealer['prev_secret_enc'] ) && ! empty( $dealer['prev_valid_until'] ) && strtotime( $dealer['prev_valid_until'] . ' UTC' ) >= Clock::now() ) {
			$prev = Crypto::decrypt( $dealer['prev_secret_enc'] );
			if ( null !== $prev ) {
				$out['previous'] = $prev;
			}
		}
		return $out;
	}

	/** Incident response: kill the grace-period secret immediately. */
	public function revoke_previous_secret( int $dealer_id ): void {
		Db::wpdb()->update( $this->table(), array( 'prev_secret_enc' => null, 'prev_valid_until' => null, 'updated_at' => Clock::mysql() ), array( 'id' => $dealer_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Revoke credentials; listings disappear from public views (dealer no longer active) but stay stored. */
	public function disconnect( int $dealer_id, string $status = 'disconnected' ): void {
		Db::wpdb()->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'             => $status,
				'key_id'             => null,
				'secret_enc'         => null,
				'prev_secret_enc'    => null,
				'prev_valid_until'   => null,
				'pairing_hash'       => null,
				'pairing_expires_at' => null,
				'disconnected_at'    => Clock::mysql(),
				'updated_at'         => Clock::mysql(),
			),
			array( 'id' => $dealer_id )
		);
		Cache::bump();
	}

	public function set_status( int $dealer_id, string $status ): bool {
		if ( ! in_array( $status, Enums::DEALER_STATUS, true ) ) {
			return false;
		}
		Db::wpdb()->update( $this->table(), array( 'status' => $status, 'updated_at' => Clock::mysql() ), array( 'id' => $dealer_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Cache::bump();
		return true;
	}

	public function set_verified( int $dealer_id, bool $verified ): void {
		Db::wpdb()->update( $this->table(), array( 'verified' => $verified ? 1 : 0, 'updated_at' => Clock::mysql() ), array( 'id' => $dealer_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		Cache::bump();
	}

	/** Update last_seen_at at most once a minute. */
	public function touch_seen( array $dealer ): void {
		$last = ! empty( $dealer['last_seen_at'] ) ? strtotime( $dealer['last_seen_at'] . ' UTC' ) : 0;
		if ( Clock::now() - (int) $last >= 60 ) {
			Db::wpdb()->update( $this->table(), array( 'last_seen_at' => Clock::mysql() ), array( 'id' => (int) $dealer['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/* ---------------- Public directory ---------------- */

	/**
	 * @param array{q?:string,city?:string,verified?:bool,page?:int,per_page?:int} $f
	 * @return array{items:list<array<string,mixed>>,total:int}
	 */
	public function public_list( array $f ): array {
		$where = array( 'status = %s' );
		$args  = array( 'active' );
		if ( ! empty( $f['city'] ) ) {
			$where[] = 'city = %s';
			$args[]  = (string) $f['city'];
		}
		if ( ! empty( $f['verified'] ) ) {
			$where[] = 'verified = 1';
		}
		if ( ! empty( $f['q'] ) ) {
			$where[] = 'name LIKE %s';
			$args[]  = '%' . Db::wpdb()->esc_like( (string) $f['q'] ) . '%';
		}
		$w        = implode( ' AND ', $where );
		$per_page = max( 1, min( 48, (int) ( $f['per_page'] ?? 12 ) ) );
		$page     = max( 1, min( 500, (int) ( $f['page'] ?? 1 ) ) );
		$total    = (int) Db::wpdb()->get_var( Db::prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE {$w}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows     = Db::wpdb()->get_results( Db::prepare( "SELECT * FROM {$this->table()} WHERE {$w} ORDER BY verified DESC, name ASC LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return array( 'items' => (array) $rows, 'total' => $total );
	}

	/** @return array<string,mixed>|null Active dealer by marketplace uuid or slug. */
	public function public_by_ref( string $ref ): ?array {
		$sql = Db::prepare( "SELECT * FROM {$this->table()} WHERE status = %s AND (uuid = %s OR slug = %s) LIMIT 1", array( 'active', $ref, $ref ) );
		$row = Db::wpdb()->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ?: null;
	}
}
