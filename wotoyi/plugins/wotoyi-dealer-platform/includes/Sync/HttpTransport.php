<?php
/**
 * Signed HTTPS client for the marketplace receiver. Uses wp_safe_remote_request (blocks private/loopback targets
 * unless the host is explicitly allowed), never follows redirects, verifies TLS, caps the response size.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

defined( 'ABSPATH' ) || exit;

final class HttpTransport implements Transport {

	private const ROUTE_PREFIX = '/wotoyi/v1';

	public function __construct( private string $base_url, private string $key_id, private string $secret ) {}

	public function name(): string {
		return 'http';
	}

	/**
	 * Build a signed request (no I/O).
	 *
	 * @param array<string,mixed>|null $payload
	 * @return array{url:string,args:array<string,mixed>}
	 */
	public function build( string $method, string $route, ?array $payload, ?int $timestamp = null, ?string $nonce = null ): array {
		$body = null === $payload ? '' : (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$path = self::ROUTE_PREFIX . $route;
		return array(
			'url'  => rtrim( $this->base_url, '/' ) . '/wp-json' . $path,
			'args' => array(
				'method'              => strtoupper( $method ),
				'timeout'             => 15,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => 2097152,
				'headers'             => Signer::headers( $this->key_id, $this->secret, $method, $path, $body, $timestamp, $nonce ),
				'body'                => $body,
			),
		);
	}

	/**
	 * @param array<string,mixed>|null $payload
	 * @return array<string,mixed> Decoded JSON object of a 2xx response.
	 * @throws SyncException
	 */
	private function request( string $method, string $route, ?array $payload ): array {
		$req = $this->build( $method, $route, $payload );
		$res = wp_safe_remote_request( $req['url'], $req['args'] );
		if ( is_wp_error( $res ) ) {
			throw new SyncException( 'http_error', 'Marketplace unreachable: ' . $res->get_error_message(), true );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$data = is_array( $data ) ? $data : array();
		if ( $code >= 200 && $code < 300 ) {
			return $data;
		}
		$err = (string) ( $data['code'] ?? 'http_' . $code );
		$msg = (string) ( $data['message'] ?? 'HTTP ' . $code );
		// Credentials rejected: stop and ask for a new pairing. Everything else at HTTP level (rate limit, clock skew,
		// HTTPS/config problems, 5xx) is retried with backoff; per-event rejections come back inside a 200 batch result.
		$auth = in_array( $err, array( 'wotoyi_auth_failed', 'wotoyi_dealer_inactive', 'wotoyi_auth_malformed', 'wotoyi_secret_superseded' ), true );
		throw new SyncException( $err, $msg, ! $auth, $code, $auth );
	}

	public function send_batch( array $events ): array {
		$data    = $this->request( 'POST', '/sync/batch', array( 'events' => array_values( $events ) ) );
		$results = $data['results'] ?? null;
		if ( ! is_array( $results ) ) {
			throw new SyncException( 'bad_response', 'Unexpected marketplace response.', true );
		}
		$by_id = array();
		foreach ( $results as $r ) {
			if ( is_array( $r ) && isset( $r['event_id'] ) && is_string( $r['event_id'] ) ) {
				$by_id[ $r['event_id'] ] = $r;
			}
		}
		return $by_id;
	}

	public function manifest( string $type, int $cursor, int $limit ): array {
		$data = $this->request( 'POST', '/sync/manifest', array( 'type' => $type, 'cursor' => $cursor, 'limit' => $limit ) );
		return array( 'items' => (array) ( $data['items'] ?? array() ), 'next' => isset( $data['next_cursor'] ) ? (int) $data['next_cursor'] : null );
	}

	public function pull_leads( int $limit ): array {
		$data = $this->request( 'POST', '/sync/leads/pull', array( 'limit' => $limit ) );
		return array_values( (array) ( $data['leads'] ?? array() ) );
	}

	public function ack_leads( array $lead_uuids ): int {
		$data = $this->request( 'POST', '/sync/leads/ack', array( 'lead_ids' => array_values( $lead_uuids ) ) );
		return (int) ( $data['acknowledged'] ?? 0 );
	}

	/** Signed health check (used by the "test connection" button). @return array<string,mixed> @throws SyncException */
	public function health(): array {
		return $this->request( 'GET', '/sync/health', null );
	}
}
