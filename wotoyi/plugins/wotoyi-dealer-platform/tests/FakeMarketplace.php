<?php
/**
 * In-process stand-in for the marketplace HTTP receiver, used by unit tests of the sync engine. It verifies the
 * HMAC signature with an INDEPENDENT implementation of the documented canonical string (not the plugin's Signer),
 * models version ordering / idempotency / tombstones in a few lines, and lets tests script failures.
 * The cross-plugin integration test (tests/integration) runs against the real marketplace code instead.
 */
final class FakeMarketplace {

	public string $key_id = 'wk_0123456789abcdef';
	public string $secret = 'fake-marketplace-secret-0123456789abcdef';
	public array $requests = array();
	/** @var list<array|callable|null> responses consumed in order by the next requests ({code, body}) */
	public array $script = array();
	/** @var array<string,array<string,array{version:int,status:string}>> type => source_id => state */
	public array $remote = array( 'vehicle' => array(), 'service' => array() );
	public array $applied_event_ids = array();
	public array $leads = array();
	public array $acked = array();
	public int $now;
	public string $pair_code = 'WTY-ABCD-EFGH-JKLM';

	public function __construct() {
		$this->now = Wotoyi\DealerPlatform\Support\Clock::now();
		$GLOBALS['wotoyi_http_handler'] = fn( string $url, array $args ) => $this->handle( $url, $args );
	}

	public function handle( string $url, array $args ) {
		$path  = (string) parse_url( $url, PHP_URL_PATH );
		$route = (string) preg_replace( '#^.*?/wp-json#', '', $path );
		$body  = (string) ( $args['body'] ?? '' );
		$h     = $args['headers'] ?? array();
		$this->requests[] = array( 'url' => $url, 'method' => $args['method'] ?? 'GET', 'route' => $route, 'headers' => $h, 'body' => $body, 'args' => $args );

		if ( $this->script ) {
			$next = array_shift( $this->script );
			if ( $next instanceof WP_Error ) {
				return $next;
			}
			if ( is_callable( $next ) ) {
				return $next( $route, $body );
			}
			if ( is_array( $next ) ) {
				return array( 'code' => $next['code'], 'body' => is_string( $next['body'] ) ? $next['body'] : json_encode( $next['body'] ) );
			}
		}
		if ( '/wotoyi/v1/sync/pair' === $route ) {
			$in = json_decode( $body, true );
			if ( ( $in['code'] ?? '' ) !== $this->pair_code ) {
				return $this->json( 403, array( 'code' => 'wotoyi_invalid_pairing_code', 'message' => 'Invalid or expired pairing code.' ) );
			}
			return $this->json( 201, array( 'dealer_id' => '0190aaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee', 'key_id' => $this->key_id, 'secret' => $this->secret, 'api_version' => 'v1', 'server_time' => $this->now ) );
		}
		if ( ! $this->signature_ok( $route, $args ) ) {
			return $this->json( 401, array( 'code' => 'wotoyi_auth_failed', 'message' => 'Invalid credentials.' ) );
		}
		$in = '' === $body ? array() : json_decode( $body, true );
		switch ( $route ) {
			case '/wotoyi/v1/sync/batch':
				$results = array();
				foreach ( $in['events'] as $event ) {
					$results[] = $this->apply( $event );
				}
				return $this->json( 200, array( 'results' => $results, 'summary' => array() ) );
			case '/wotoyi/v1/sync/manifest':
				$items = array();
				foreach ( $this->remote[ $in['type'] ] as $sid => $st ) {
					$items[] = array( 'source_id' => $sid, 'version' => $st['version'], 'status' => $st['status'] );
				}
				$items = array_slice( $items, (int) $in['cursor'], (int) $in['limit'] + 0 );
				return $this->json( 200, array( 'type' => $in['type'], 'items' => $items, 'next_cursor' => null ) );
			case '/wotoyi/v1/sync/leads/pull':
				return $this->json( 200, array( 'leads' => array_slice( array_values( array_filter( $this->leads, fn( $l ) => ! in_array( $l['uuid'], $this->acked, true ) ) ), 0, (int) ( $in['limit'] ?? 50 ) ) ) );
			case '/wotoyi/v1/sync/leads/ack':
				$n = 0;
				foreach ( $in['lead_ids'] as $id ) {
					if ( ! in_array( $id, $this->acked, true ) ) {
						$this->acked[] = $id;
						++$n;
					}
				}
				return $this->json( 200, array( 'acknowledged' => $n ) );
		}
		return $this->json( 404, array( 'code' => 'rest_no_route', 'message' => 'No route' ) );
	}

	/** Independent check of the documented canonical string. */
	private function signature_ok( string $route, array $args ): bool {
		$h = array_change_key_case( $args['headers'] ?? array(), CASE_LOWER );
		foreach ( array( 'x-wotoyi-key', 'x-wotoyi-timestamp', 'x-wotoyi-nonce', 'x-wotoyi-signature' ) as $k ) {
			if ( empty( $h[ $k ] ) ) {
				return false;
			}
		}
		if ( $h['x-wotoyi-key'] !== $this->key_id || abs( $this->now - (int) $h['x-wotoyi-timestamp'] ) > 300 || 1 !== preg_match( '/^[A-Za-z0-9_\-]{16,64}$/', $h['x-wotoyi-nonce'] ) ) {
			return false;
		}
		$canonical = "WOTOYI-HMAC-SHA256\n" . $h['x-wotoyi-timestamp'] . "\n" . $h['x-wotoyi-nonce'] . "\n" . strtoupper( $args['method'] ) . "\n" . $route . "\n" . hash( 'sha256', (string) ( $args['body'] ?? '' ) );
		return hash_equals( hash_hmac( 'sha256', $canonical, $this->secret ), $h['x-wotoyi-signature'] );
	}

	/** @return array<string,mixed> */
	private function apply( array $e ): array {
		$id = $e['event_id'];
		if ( isset( $this->applied_event_ids[ $id ] ) ) {
			return $this->applied_event_ids[ $id ] + array( 'duplicate' => true );
		}
		[ $type, $action ] = explode( '.', $e['event_type'] );
		$sid = $e['data']['source_id'];
		$ver = $e['data']['version'];
		$cur = $this->remote[ $type ][ $sid ] ?? null;
		$res = array( 'status' => 'applied', 'http_status' => 200, 'event_id' => $id, 'entity' => array( 'type' => $type, 'source_id' => $sid, 'version' => $ver ) );
		if ( $cur && $ver < $cur['version'] ) {
			$res = array( 'status' => 'stale', 'http_status' => 409, 'code' => 'stale_version', 'event_id' => $id );
		} elseif ( $cur && $ver === $cur['version'] ) {
			$res['status'] = 'unchanged';
		} else {
			$status = match ( $action ) { 'published' => 'published', 'unpublished' => 'unpublished', 'sold' => 'sold', 'deleted' => 'deleted', default => ( $e['data']['status'] ?? 'published' ) };
			$this->remote[ $type ][ $sid ] = array( 'version' => $ver, 'status' => $status );
		}
		$this->applied_event_ids[ $id ] = $res;
		return $res;
	}

	private function json( int $code, array $body ): array {
		return array( 'code' => $code, 'body' => json_encode( $body ) );
	}

	/** Pair the current tenant through the real client code. */
	public function respond_with( int $code, array $body, int $times = 1 ): void {
		for ( $i = 0; $i < $times; $i++ ) {
			$this->script[] = array( 'code' => $code, 'body' => $body );
		}
	}

	/** Requests whose route matches. @return list<array<string,mixed>> */
	public function requests_to( string $route ): array {
		return array_values( array_filter( $this->requests, fn( $r ) => $r['route'] === $route ) );
	}
}
