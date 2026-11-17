<?php
/**
 * Signed HTTPS client for the Dealer Platform side (reference implementation; also used by tests).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Sync;

defined( 'ABSPATH' ) || exit;

final class HttpTransport implements Transport {

	public function __construct( private string $base_url, private string $key_id, private string $secret ) {}

	/**
	 * Build the signed request (no I/O).
	 *
	 * @return array{url:string,args:array<string,mixed>}
	 */
	public function build( string $method, string $route, ?array $payload, ?int $timestamp = null, ?string $nonce = null ): array {
		$body = null === $payload ? '' : (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$path = '/wotoyi/v1' . $route;
		return array(
			'url'  => rtrim( $this->base_url, '/' ) . '/wp-json' . $path,
			'args' => array(
				'method'      => strtoupper( $method ),
				'timeout'     => 15,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => Signer::headers( $this->key_id, $this->secret, $method, $path, $body, $timestamp, $nonce ),
				'body'        => $body,
			),
		);
	}

	public function send( string $dealer_ref, array $event ): array {
		$req = $this->build( 'POST', '/sync/events', $event );
		$res = wp_remote_request( $req['url'], $req['args'] );
		if ( is_wp_error( $res ) ) {
			return array( 'status' => 'failed', 'http_status' => 0, 'message' => $res->get_error_message(), 'retryable' => true );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$data = is_array( $data ) ? $data : array();
		return $data + array(
			'status'      => $code < 300 ? 'applied' : 'failed',
			'http_status' => $code,
			'retryable'   => $code >= 500 || 429 === $code,
		);
	}
}
