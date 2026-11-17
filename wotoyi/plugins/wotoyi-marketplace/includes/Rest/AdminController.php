<?php
/**
 * Marketplace staff endpoints (capability wotoyi_manage_marketplace + REST nonce).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Rest;

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminController {

	private function params( \WP_REST_Request $request ): array {
		$json = json_decode( (string) $request->get_body(), true, 8 );
		return is_array( $json ) ? $json : array();
	}

	/** Create a dealer and issue the first pairing code (shown once). */
	public function dealer_create( \WP_REST_Request $request ) {
		$p      = $this->params( $request );
		$source = isset( $p['source_dealer_uuid'] ) && is_string( $p['source_dealer_uuid'] ) && 1 === preg_match( '/^[A-Za-z0-9_.:\-]{8,64}$/', $p['source_dealer_uuid'] ) ? $p['source_dealer_uuid'] : null;
		if ( $source && Plugin::dealers()->find_by_source_uuid( $source ) ) {
			return new \WP_Error( 'wotoyi_conflict', 'This dealer identity already exists.', array( 'status' => 409 ) );
		}
		$dealer = Plugin::dealers()->create( $p, 'http', $source );
		if ( ! $dealer ) {
			return new \WP_Error( 'wotoyi_invalid_request', 'A dealer name is required.', array( 'status' => 422 ) );
		}
		$code = Plugin::dealers()->issue_pairing_code( (int) $dealer['id'], Settings::int( 'pairing_ttl' ) );
		$this->audit( 'dealer.create', (int) $dealer['id'], array( 'status' => $dealer['status'], 'ttl' => Settings::int( 'pairing_ttl' ), 'source_identity_bound' => null !== $source ) );
		return new \WP_REST_Response(
			array(
				'dealer_id'    => $dealer['uuid'],
				'status'       => $dealer['status'],
				'pairing_code' => $code,
				'expires_in'   => Settings::int( 'pairing_ttl' ),
			),
			201
		);
	}

	public function pairing_code( \WP_REST_Request $request ) {
		$dealer = Plugin::dealers()->find_by_uuid( (string) $request['dealer'] );
		if ( ! $dealer || 'suspended' === $dealer['status'] ) {
			return new \WP_Error( 'wotoyi_not_found', 'Dealer not found or suspended.', array( 'status' => 404 ) );
		}
		$code = Plugin::dealers()->issue_pairing_code( (int) $dealer['id'], Settings::int( 'pairing_ttl' ) );
		$this->audit( 'pairing_code.issue', (int) $dealer['id'], array( 'status' => $dealer['status'], 'ttl' => Settings::int( 'pairing_ttl' ) ) ); // The code itself is never logged.
		return new \WP_REST_Response( array( 'pairing_code' => $code, 'expires_in' => Settings::int( 'pairing_ttl' ) ), 201 );
	}

	/** Change dealer status and/or verified badge. */
	public function dealer_status( \WP_REST_Request $request ) {
		$dealer = Plugin::dealers()->find_by_uuid( (string) $request['dealer'] );
		if ( ! $dealer ) {
			return new \WP_Error( 'wotoyi_not_found', 'Dealer not found.', array( 'status' => 404 ) );
		}
		$p = $this->params( $request );
		if ( isset( $p['status'] ) ) {
			if ( 'disconnected' === $p['status'] || 'suspended' === $p['status'] ) {
				Plugin::dealers()->disconnect( (int) $dealer['id'], (string) $p['status'] ); // Revokes credentials too.
			} elseif ( ! is_string( $p['status'] ) || ! Plugin::dealers()->set_status( (int) $dealer['id'], $p['status'] ) ) {
				return new \WP_Error( 'wotoyi_invalid_request', 'Invalid status.', array( 'status' => 422 ) );
			}
			$this->audit( 'dealer.status', (int) $dealer['id'], array( 'status' => array( $dealer['status'], (string) $p['status'] ) ) );
		}
		if ( isset( $p['verified'] ) ) {
			Plugin::dealers()->set_verified( (int) $dealer['id'], (bool) $p['verified'] );
			$this->audit( 'dealer.verified', (int) $dealer['id'], array( 'verified' => array( (bool) (int) $dealer['verified'], (bool) $p['verified'] ) ) );
		}
		if ( ! empty( $p['revoke_previous_secret'] ) ) { // Incident response: end the rotation grace period now.
			Plugin::dealers()->revoke_previous_secret( (int) $dealer['id'] );
			$this->audit( 'key.revoke_previous', (int) $dealer['id'], array( 'previous_secret_revoked' => true ) );
		}
		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	/** Newest first; optional ?dealer=<uuid>, ?limit, ?before_id. */
	public function audit_log( \WP_REST_Request $request ) {
		$dealer_id = null;
		$ref       = $request->get_param( 'dealer' );
		if ( is_string( $ref ) && '' !== $ref ) {
			$dealer = Plugin::dealers()->find_by_uuid( $ref );
			if ( ! $dealer ) {
				return new \WP_Error( 'wotoyi_not_found', 'Dealer not found.', array( 'status' => 404 ) );
			}
			$dealer_id = (int) $dealer['id'];
		}
		return new \WP_REST_Response( array( 'items' => Plugin::audit()->recent( (int) ( $request->get_param( 'limit' ) ?? 50 ), (int) ( $request->get_param( 'before_id' ) ?? 0 ), $dealer_id ) ) );
	}

	/** @param array<string,mixed> $details */
	private function audit( string $action, int $dealer_id, array $details ): void {
		Plugin::audit()->record( 'staff', get_current_user_id(), $action, $dealer_id, $details );
	}

	public function sync_log( \WP_REST_Request $request ) {
		$dealer = Plugin::dealers()->find_by_uuid( (string) $request['dealer'] );
		if ( ! $dealer ) {
			return new \WP_Error( 'wotoyi_not_found', 'Dealer not found.', array( 'status' => 404 ) );
		}
		$limit  = (int) ( $request->get_param( 'limit' ) ?? 50 );
		$before = (int) ( $request->get_param( 'before_id' ) ?? 0 );
		return new \WP_REST_Response( array( 'items' => Plugin::sync_log()->recent( (int) $dealer['id'], $limit, $before ) ) );
	}
}
