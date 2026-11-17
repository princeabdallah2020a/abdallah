<?php
/**
 * Test helpers: users, tenants, members, REST calls, sample records.
 */

use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\Clock;

final class Fixtures {

	private static int $seq = 0;

	public static function user( int $id, string $email = '' ): int {
		$GLOBALS['wotoyi_wp_users'][ $id ] = array( 'email' => '' !== $email ? $email : "user{$id}@example.com", 'role' => 'subscriber' );
		return $id;
	}

	/** Tenant with an owner (user id). @return array<string,mixed> tenant row */
	public static function tenant( string $name, int $owner_user_id, array $extra = array() ): array {
		self::user( $owner_user_id );
		$res = Plugin::tenants()->create( array( 'name' => $name, 'city' => 'Dakar', 'country' => 'SN', 'currency' => 'XOF' ) + $extra, $owner_user_id );
		return (array) Directory::find_by_uuid( $res['id'] );
	}

	/** @return mixed */
	public static function in( array $tenant, callable $fn, ?string $role = 'owner' ) {
		$member = null;
		if ( $role ) {
			$member = array( 'id' => 0, 'uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'user_id' => 1, 'role' => $role, 'tenant_id' => $tenant['id'] );
		}
		return TenantContext::run( $tenant, $fn, $member );
	}

	/** Add a member with a role; returns the presented member. */
	public static function member( array $tenant, int $user_id, string $role ): array {
		self::user( $user_id );
		return self::in( $tenant, fn() => Plugin::members()->add( array( 'user_id' => $user_id, 'role' => $role ) ) );
	}

	public static function login( int $id, array $caps = array() ): void {
		$GLOBALS['wotoyi_user'] = array( 'id' => $id, 'caps' => $caps );
	}

	public static function platform_admin( int $id = 1 ): void {
		self::user( $id );
		self::login( $id, array( 'wotoyi_dp_manage_platform' ) );
	}

	/** REST call. Writes carry a valid nonce unless $nonce is false. */
	public static function call( string $method, string $route, ?array $body = null, array $query = array(), array $headers = array(), bool $nonce = true ): WP_REST_Response {
		$r = new WP_REST_Request( $method, '/wotoyi-pro/v1' . $route );
		$r->set_query_params( $query );
		if ( null !== $body ) {
			$r->set_body( (string) json_encode( $body ) );
		}
		if ( $nonce && 'GET' !== $method ) {
			$r->set_header( 'X-WP-Nonce', 'valid-nonce' );
		}
		foreach ( $headers as $k => $v ) {
			$r->set_header( $k, $v );
		}
		return rest_do_request( $r );
	}

	public static function vehicle( array $over = array() ): array {
		return $over + array(
			'make' => 'Toyota', 'model' => 'RAV4', 'year' => 2021, 'body_type' => 'suv', 'fuel_type' => 'petrol',
			'transmission' => 'automatic', 'drive_type' => 'awd', 'condition_type' => 'used', 'mileage_km' => 42000,
			'price' => 15000000, 'offer_type' => 'sale', 'city' => 'Dakar', 'ext_colour' => 'white', 'seats' => 5,
			'cover_image' => 'https://cdn.example.com/a.jpg', 'vin' => 'JTMBFREV5LD000001', 'purchase_cost' => 12000000, 'internal_notes' => 'secret note',
		);
	}

	public static function tick( int $seconds ): void {
		Clock::set( Clock::now() + $seconds );
	}

	public static function count( string $logical, string $where = '1=1' ): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wotoyi_dp_' . $logical . ' WHERE ' . $where );
	}

	/** Raw SQL for assertions that must bypass the tenant layer (test code only). */
	public static function raw( string $sql ): array {
		global $wpdb;
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function seq(): int {
		return ++self::$seq;
	}
}
