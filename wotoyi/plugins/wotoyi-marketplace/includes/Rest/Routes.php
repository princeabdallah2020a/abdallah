<?php
/**
 * Route registration for namespace wotoyi/v1.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Rest;

use Wotoyi\Marketplace\Plugin;

defined( 'ABSPATH' ) || exit;

final class Routes {

	public const API_NAMESPACE = 'wotoyi/v1';

	private const REF = '(?P<ref>[A-Za-z0-9][A-Za-z0-9\-]{0,189})';
	private const SID = '(?P<source_id>[A-Za-z0-9_.:\-]{1,64})';

	public static function register(): void {
		$pub   = new PublicController();
		$acct  = new AccountController();
		$admin = new AdminController();
		$sync  = new SyncController();
		$auth  = static fn( \WP_REST_Request $r ) => Plugin::authenticator()->permission( $r );

		$read  = array( Permissions::class, 'public_read' );
		$write = array( Permissions::class, 'public_write' );

		// ---- Public catalogue (read) ----
		self::route( '/vehicles', \WP_REST_Server::READABLE, array( $pub, 'vehicles' ), $read );
		self::route( '/vehicles/count', \WP_REST_Server::READABLE, array( $pub, 'vehicles_count' ), $read );
		self::route( '/facets', \WP_REST_Server::READABLE, array( $pub, 'facets' ), $read );
		self::route( '/meta', \WP_REST_Server::READABLE, array( $pub, 'meta' ), $read );
		self::route( '/vehicles/' . self::REF, \WP_REST_Server::READABLE, array( $pub, 'vehicle' ), $read );
		self::route( '/vehicles/' . self::REF . '/view', \WP_REST_Server::CREATABLE, array( $pub, 'vehicle_view' ), $write );
		self::route( '/services', \WP_REST_Server::READABLE, array( $pub, 'services' ), $read );
		self::route( '/services/' . self::REF, \WP_REST_Server::READABLE, array( $pub, 'service' ), $read );
		self::route( '/dealers', \WP_REST_Server::READABLE, array( $pub, 'dealers' ), $read );
		self::route( '/dealers/' . self::REF, \WP_REST_Server::READABLE, array( $pub, 'dealer' ), $read );

		// ---- Public lead capture (write, anti-spam) ----
		self::route( '/leads/token', \WP_REST_Server::READABLE, array( $pub, 'lead_token' ), $read );
		self::route( '/leads', \WP_REST_Server::CREATABLE, array( $pub, 'lead_create' ), $write );

		// ---- Buyer account ----
		$acct_read  = array( Permissions::class, 'account_read' );
		$acct_write = array( Permissions::class, 'account_write' );
		self::route( '/me/favourites', \WP_REST_Server::READABLE, array( $acct, 'favourites' ), $acct_read );
		self::route( '/me/favourites', \WP_REST_Server::CREATABLE, array( $acct, 'favourite_add' ), $acct_write );
		self::route( '/me/favourites/(?P<type>vehicle|service)/' . self::REF, \WP_REST_Server::DELETABLE, array( $acct, 'favourite_remove' ), $acct_write );
		self::route( '/me/saved-searches', \WP_REST_Server::READABLE, array( $acct, 'saved_searches' ), $acct_read );
		self::route( '/me/saved-searches', \WP_REST_Server::CREATABLE, array( $acct, 'saved_search_add' ), $acct_write );
		self::route( '/me/saved-searches/(?P<id>\d{1,12})', \WP_REST_Server::DELETABLE, array( $acct, 'saved_search_delete' ), $acct_write );

		// ---- Marketplace administration (capability + nonce) ----
		$adm_read  = array( Permissions::class, 'admin_read' );
		$adm_write = array( Permissions::class, 'admin_write' );
		self::route( '/admin/dealers', \WP_REST_Server::CREATABLE, array( $admin, 'dealer_create' ), $adm_write );
		self::route( '/admin/dealers/(?P<dealer>[0-9a-f\-]{36})/pairing-code', \WP_REST_Server::CREATABLE, array( $admin, 'pairing_code' ), $adm_write );
		self::route( '/admin/dealers/(?P<dealer>[0-9a-f\-]{36})/status', \WP_REST_Server::CREATABLE, array( $admin, 'dealer_status' ), $adm_write );
		self::route( '/admin/dealers/(?P<dealer>[0-9a-f\-]{36})/sync-log', \WP_REST_Server::READABLE, array( $admin, 'sync_log' ), $adm_read );

		self::route( '/admin/audit-log', \WP_REST_Server::READABLE, array( $admin, 'audit_log' ), $adm_read );

		// ---- Sync receiver (signed by the dealer's HMAC key) ----
		self::route( '/sync/pair', \WP_REST_Server::CREATABLE, array( $sync, 'pair' ), array( Permissions::class, 'pairing_gate' ) );
		self::route( '/sync/health', \WP_REST_Server::READABLE, array( $sync, 'health' ), $auth );
		self::route( '/sync/events', \WP_REST_Server::CREATABLE, array( $sync, 'event' ), $auth );
		self::route( '/sync/batch', \WP_REST_Server::CREATABLE, array( $sync, 'batch' ), $auth );
		self::route( '/sync/vehicles/' . self::SID, 'PUT', array( $sync, 'vehicle_put' ), $auth );
		self::route( '/sync/vehicles/' . self::SID, \WP_REST_Server::DELETABLE, array( $sync, 'vehicle_delete' ), $auth );
		self::route( '/sync/services/' . self::SID, 'PUT', array( $sync, 'service_put' ), $auth );
		self::route( '/sync/services/' . self::SID, \WP_REST_Server::DELETABLE, array( $sync, 'service_delete' ), $auth );
		self::route( '/sync/leads/pull', \WP_REST_Server::CREATABLE, array( $sync, 'leads_pull' ), $auth );
		self::route( '/sync/leads/ack', \WP_REST_Server::CREATABLE, array( $sync, 'leads_ack' ), $auth );
		self::route( '/sync/manifest', \WP_REST_Server::CREATABLE, array( $sync, 'manifest' ), $auth );
		self::route( '/sync/rotate-key', \WP_REST_Server::CREATABLE, array( $sync, 'rotate_key' ), $auth );
		self::route( '/sync/disconnect', \WP_REST_Server::CREATABLE, array( $sync, 'disconnect' ), $auth );
	}

	/**
	 * @param callable $callback
	 * @param callable $permission
	 */
	private static function route( string $path, string $methods, $callback, $permission ): void {
		register_rest_route(
			self::API_NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => $callback,
				'permission_callback' => $permission,
			)
		);
	}
}
