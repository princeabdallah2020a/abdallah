<?php
/**
 * Test helpers: dealers, signed requests, events, sample vehicles.
 */

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Crypto;
use Wotoyi\Marketplace\Sync\Signer;

final class Fixtures {

	private static int $seq = 0;

	/** Active, paired HTTP dealer with credentials. @return array{row:array,key_id:string,secret:string} */
	public static function dealer( string $name = 'Dealer A', array $extra = array(), bool $verified = false ): array {
		$row = Plugin::dealers()->create( array( 'name' => $name, 'city' => 'Dakar', 'country' => 'SN' ) + $extra, 'http', 'src-' . strtolower( preg_replace( '/\W/', '', $name ) ) . '-' . ( ++self::$seq ) . '-xxxx' );
		$cred = Plugin::dealers()->issue_credentials( (int) $row['id'], null );
		if ( $verified ) {
			Plugin::dealers()->set_verified( (int) $row['id'], true );
		}
		return array( 'row' => Plugin::dealers()->find_by_id( (int) $row['id'] ), 'key_id' => $cred['key_id'], 'secret' => $cred['secret'] );
	}

	public static function reload( array $dealer ): array {
		return Plugin::dealers()->find_by_id( (int) $dealer['row']['id'] );
	}

	public static function request( string $method, string $route, ?array $body = null, array $query = array() ): WP_REST_Request {
		$r = new WP_REST_Request( $method, $route );
		$r->set_query_params( $query );
		if ( null !== $body ) {
			$r->set_body( (string) json_encode( $body ) );
		}
		return $r;
	}

	/** Signed request for a dealer fixture (override pieces to attack it). */
	public static function signed( array $dealer, string $method, string $route, ?array $body = null, ?int $ts = null, ?string $nonce = null, ?string $secret = null, ?string $raw_body = null ): WP_REST_Request {
		$raw = $raw_body ?? ( null === $body ? '' : (string) json_encode( $body ) );
		$r   = new WP_REST_Request( $method, $route );
		$r->set_body( $raw );
		foreach ( Signer::headers( $dealer['key_id'], $secret ?? $dealer['secret'], $method, $route, $raw, $ts, $nonce ) as $k => $v ) {
			$r->set_header( $k, $v );
		}
		return $r;
	}

	public static function call_signed( array $dealer, string $method, string $route, ?array $body = null, ?int $ts = null, ?string $nonce = null, ?string $secret = null ): WP_REST_Response {
		return rest_do_request( self::signed( $dealer, $method, $route, $body, $ts, $nonce, $secret ) );
	}

	public static function event_id(): string {
		return 'evt-' . str_pad( (string) ( ++self::$seq ), 8, '0', STR_PAD_LEFT ) . '-' . bin2hex( random_bytes( 4 ) );
	}

	public static function event( string $type, string $source_id, int $version, array $fields = array(), ?string $event_id = null ): array {
		return array(
			'event_id'    => $event_id ?? self::event_id(),
			'event_type'  => $type,
			'occurred_at' => gmdate( 'c', Clock::now() ),
			'api_version' => 'v1',
			'data'        => array( 'source_id' => $source_id, 'version' => $version ) + $fields,
		);
	}

	/** Sample vehicle snapshot. */
	public static function vehicle( array $over = array() ): array {
		return $over + array(
			'make' => 'Toyota', 'model' => 'RAV4', 'year' => 2021, 'body_type' => 'suv', 'fuel_type' => 'petrol',
			'transmission' => 'automatic', 'drive_type' => 'awd', 'condition_type' => 'used', 'mileage_km' => 42000,
			'price' => 15000000, 'currency' => 'XOF', 'offer_type' => 'sale', 'city' => 'Dakar', 'country' => 'SN',
			'ext_colour' => 'white', 'seats' => 5, 'doors' => 5, 'cover_image' => 'https://cdn.example.com/a.jpg',
		);
	}

	/** Publish through the receiver directly (no HTTP). @return array<string,mixed> receiver result */
	public static function publish( array $dealer, string $source_id, array $fields = array(), int $version = 1, string $type = 'vehicle.published' ): array {
		$res = Plugin::receiver()->handle_event( $dealer['row'] ?? $dealer, self::event( $type, $source_id, $version, self::vehicle( $fields ) ), 'local' );
		if ( 'applied' !== $res['status'] && 'unchanged' !== $res['status'] ) {
			throw new RuntimeException( 'fixture publish failed: ' . json_encode( $res ) );
		}
		return $res;
	}

	public static function login( int $id, array $caps = array() ): void {
		$GLOBALS['wotoyi_user'] = array( 'id' => $id, 'caps' => $caps );
	}

	public static function admin(): void {
		self::login( 1, array( 'wotoyi_manage_marketplace' ) );
	}

	public static function get( string $route, array $query = array() ): WP_REST_Response {
		return rest_do_request( self::request( 'GET', $route, null, $query ) );
	}

	public static function with_nonce( WP_REST_Request $r ): WP_REST_Request {
		$r->set_header( 'X-WP-Nonce', 'valid-nonce' );
		return $r;
	}

	/** Ensure the clock moves between requests so time-token checks pass. */
	public static function tick( int $seconds ): void {
		Clock::set( Clock::now() + $seconds );
	}

	public static function table_count( string $logical, string $where = '1=1' ): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'wotoyi_' . $logical . ' WHERE ' . $where );
	}
}
