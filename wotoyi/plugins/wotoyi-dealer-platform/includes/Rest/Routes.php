<?php
/**
 * REST API `wotoyi-pro/v1`. Every route has a permission_callback that (1) requires a logged-in user, (2) resolves
 * the tenant from the user's OWN membership, (3) checks the role's capability, (4) requires the REST nonce on
 * writes. Handlers then run inside TenantContext, so every query is scoped (Data\TenantDb).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Rest;

use Wotoyi\DealerPlatform\Auth\Access;
use Wotoyi\DealerPlatform\Auth\Roles;
use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Service\AuditLog;
use Wotoyi\DealerPlatform\Service\EntityService;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\RateLimiter;
use Wotoyi\DealerPlatform\Sync\Outbox;
use Wotoyi\DealerPlatform\Sync\Pairing;
use Wotoyi\DealerPlatform\Sync\Reconciler;
use Wotoyi\DealerPlatform\Sync\SyncState;
use Wotoyi\DealerPlatform\Sync\Worker;

defined( 'ABSPATH' ) || exit;

final class Routes {

	public const API_NAMESPACE = 'wotoyi-pro/v1';

	private const ID = '(?P<id>[0-9a-f\-]{36})';

	public static function register(): void {
		// ---- Session ----
		self::route( '/me', 'GET', 'dashboard.read', false, static fn( $r ) => self::me() );
		self::route( '/tenant', 'GET', 'dashboard.read', false, static fn( $r ) => Plugin::tenants()->current() );
		self::route( '/tenant', 'PUT, PATCH', 'settings.manage', true, static fn( $r ) => Plugin::tenants()->update_current( Handler::body( $r ) ) );

		// ---- Platform operator (WordPress administrators; tenant rows only, never business data) ----
		self::platform( '/platform/tenants', 'GET', false, static fn( $r ) => array( 'items' => array_map( array( Plugin::tenants(), 'present' ), Directory::list_tenants() ) ) );
		self::platform(
			'/platform/tenants',
			'POST',
			true,
			static function ( $r ) {
				$b = Handler::body( $r );
				return new \WP_REST_Response( Plugin::tenants()->create( $b, (int) ( $b['owner_user_id'] ?? 0 ), get_current_user_id() ), 201 );
			}
		);
		// Direct attachment of a WordPress user to a tenant exists ONLY here (platform operator). Dealer owners invite by email (H1).
		self::platform(
			'/platform/members',
			'POST',
			true,
			static function ( $r ) {
				$b      = Handler::body( $r );
				$tenant = Directory::find_by_uuid( (string) ( $b['tenant_id'] ?? '' ) );
				if ( ! $tenant ) {
					throw ApiException::not_found( 'Tenant' );
				}
				$actor = get_current_user_id();
				return new \WP_REST_Response( TenantContext::run( $tenant, static fn() => Plugin::members()->add_direct( (int) ( $b['user_id'] ?? 0 ), (string) ( $b['role'] ?? '' ), isset( $b['display_name'] ) ? (string) $b['display_name'] : null, $actor ) ), 201 );
			}
		);

		// ---- Members ----
		self::route( '/members', 'GET', 'members.read', false, static fn( $r ) => Plugin::members()->list() );
		// H1: invitation, never direct attachment. Same 202 reply whether or not the address exists or is already a member.
		self::route( '/members', 'POST', 'members.manage', true, static fn( $r ) => new \WP_REST_Response( Plugin::members()->invite( Handler::body( $r ) ), 202 ) );
		self::route( '/members/invitations', 'GET', 'members.manage', false, static fn( $r ) => Plugin::members()->invitations() );
		self::route( '/members/invitations/' . self::ID, 'DELETE', 'members.manage', true, static fn( $r ) => Plugin::members()->revoke_invitation( (string) $r['id'] ) );
		self::user_route(
			'/members/accept',
			'POST',
			static function ( $r ) {
				$b = Handler::body( $r );
				return Plugin::members()->accept( (string) ( $b['token'] ?? '' ), get_current_user_id() );
			}
		);
		self::route( '/members/' . self::ID, 'PUT, PATCH', 'members.manage', true, static fn( $r ) => Plugin::members()->update( (string) $r['id'], Handler::body( $r ) ) );
		self::route( '/members/' . self::ID, 'DELETE', 'members.manage', true, static fn( $r ) => Plugin::members()->remove( (string) $r['id'] ) );

		// ---- Vehicles and services (synced entities) ----
		self::synced( '/vehicles', 'vehicles', static fn() => Plugin::vehicles() );
		self::route( '/vehicles/' . self::ID . '/publish', 'POST', 'vehicles.publish', true, static fn( $r ) => Plugin::vehicles()->transition( (string) $r['id'], 'published' ) );
		self::route( '/vehicles/' . self::ID . '/unpublish', 'POST', 'vehicles.publish', true, static fn( $r ) => Plugin::vehicles()->transition( (string) $r['id'], 'unpublished' ) );
		self::route( '/vehicles/' . self::ID . '/sold', 'POST', 'deals.write', true, static fn( $r ) => new \WP_REST_Response( Plugin::deals()->sell_vehicle( (string) $r['id'], Handler::body( $r ) ), 201 ) );
		self::synced( '/services', 'services', static fn() => Plugin::services() );
		self::route( '/services/' . self::ID . '/publish', 'POST', 'services.publish', true, static fn( $r ) => Plugin::services()->transition( (string) $r['id'], 'published' ) );
		self::route( '/services/' . self::ID . '/unpublish', 'POST', 'services.publish', true, static fn( $r ) => Plugin::services()->transition( (string) $r['id'], 'unpublished' ) );

		// ---- CRM ----
		self::crud( '/customers', 'customers', static fn() => Plugin::customers(), true );
		self::route( '/customers/' . self::ID . '/timeline', 'GET', 'activities.read', false, static fn( $r ) => Plugin::activities()->list( array( 'entity_type' => 'customer', 'entity_uuid' => (string) $r['id'] ) + $r->get_query_params() ) );
		self::route( '/customers/' . self::ID . '/erase', 'POST', 'customers.erase', true, static fn( $r ) => Plugin::customers()->erase( (string) $r['id'] ) );
		self::route(
			'/customers/' . self::ID . '/export',
			'GET',
			'customers.export',
			false,
			static function ( $r ) {
				self::pii_guard( 'customers' );
				$out = Plugin::customers()->export( (string) $r['id'] );
				self::pii_consume( 1 + count( $out['leads'] ) + count( $out['deals'] ) + count( $out['rentals'] ) + count( $out['tasks'] ) + count( $out['activities'] ) );
				$resp = new \WP_REST_Response( $out, 200 );
				$resp->header( 'Cache-Control', 'no-store' );
				return $resp;
			}
		);

		self::route( '/leads/pipeline', 'GET', 'leads.read', false, static fn( $r ) => Plugin::leads()->pipeline() );
		self::crud( '/leads', 'leads', static fn() => Plugin::leads(), true );
		self::route( '/leads/' . self::ID . '/stage', 'POST', 'leads.write', true, static function ( $r ) {
			$b = Handler::body( $r );
			return Plugin::leads()->change_stage( (string) $r['id'], (string) ( $b['stage'] ?? '' ), isset( $b['lost_reason'] ) ? (string) $b['lost_reason'] : null );
		} );
		self::route( '/leads/' . self::ID . '/convert', 'POST', 'customers.write', true, static fn( $r ) => Plugin::leads()->convert( (string) $r['id'] ) );

		self::crud( '/deals', 'deals', static fn() => Plugin::deals(), false );
		self::route( '/rentals/calendar', 'GET', 'rentals.read', false, static fn( $r ) => Plugin::rentals()->calendar( (string) $r->get_param( 'from' ), (string) $r->get_param( 'to' ) ) );
		self::crud( '/rentals', 'rentals', static fn() => Plugin::rentals(), false );
		self::crud( '/employees', 'employees', static fn() => Plugin::employees(), true );
		self::crud( '/tasks', 'tasks', static fn() => Plugin::tasks(), false );
		self::route( '/tasks/' . self::ID . '/complete', 'POST', 'tasks.write', true, static fn( $r ) => Plugin::tasks()->complete( (string) $r['id'] ) );
		self::route( '/activities', 'GET', 'activities.read', false, static fn( $r ) => Plugin::activities()->list( $r->get_query_params() ) );
		self::route( '/activities', 'POST', 'activities.write', true, static fn( $r ) => new \WP_REST_Response( Plugin::activities()->create( Handler::body( $r ) ), 201 ) );

		// ---- Dashboard, audit ----
		self::route( '/dashboard', 'GET', 'dashboard.read', false, static fn( $r ) => Plugin::dashboard()->kpis( (int) ( $r->get_param( 'days' ) ?? 30 ) ) );
		self::route( '/audit', 'GET', 'audit.read', false, static fn( $r ) => AuditLog::list( $r->get_query_params() ) );

		// ---- Sync ----
		self::route( '/sync/status', 'GET', 'sync.read', false, static fn( $r ) => SyncState::present() );
		self::route( '/sync/outbox', 'GET', 'sync.read', false, static fn( $r ) => Outbox::list( $r->get_query_params() ) );
		self::route( '/sync/pair', 'POST', 'settings.manage', true, static function ( $r ) {
			self::throttle( 'pair', 5, 900 );
			$b = Handler::body( $r );
			$state = Pairing::pair_http( (string) ( $b['code'] ?? '' ), (string) ( $b['base_url'] ?? '' ) );
			$resp  = new \WP_REST_Response( $state + array( 'initial_sync' => self::safe( static fn() => Reconciler::run_for_current() ) ), 201 );
			$resp->header( 'Cache-Control', 'no-store' );
			return $resp;
		} );
		self::route( '/sync/pair-local', 'POST', 'settings.manage', true, static function ( $r ) {
			self::throttle( 'pair', 5, 900 );
			$state = Pairing::pair_local();
			return new \WP_REST_Response( $state + array( 'initial_sync' => self::safe( static fn() => Reconciler::run_for_current() ) ), 201 );
		} );
		self::route( '/sync/run', 'POST', 'sync.manage', true, static function ( $r ) {
			self::throttle( 'run', 30, 60 );
			AuditLog::record( 'sync.run' );
			return Worker::run_now();
		} );
		self::route( '/sync/reconcile', 'POST', 'sync.manage', true, static function ( $r ) {
			self::throttle( 'reconcile', 6, 3600 );
			return Reconciler::run_for_current();
		} );
		self::route( '/sync/requeue', 'POST', 'sync.manage', true, static function ( $r ) {
			AuditLog::record( 'sync.requeue' );
			return array( 'requeued' => Outbox::requeue() );
		} );
		self::route( '/sync/disconnect', 'POST', 'settings.manage', true, static function ( $r ) {
			Pairing::disconnect();
			return SyncState::present();
		} );
	}

	/** Rows of personal data one user may read per minute (M4); filterable for large dealers. */
	private static function pii_limit(): int {
		return max( 10, (int) apply_filters( 'wotoyi_dp_pii_rows_per_minute', 600 ) );
	}

	private static function pii_bucket(): string {
		return 'pii:' . TenantContext::uuid() . ':' . TenantContext::user_id();
	}

	/** Refuse (429 + one audit event per window) when the user already read the per-minute budget. */
	private static function pii_guard( string $entity ): void {
		if ( RateLimiter::blocked( self::pii_bucket(), self::pii_limit(), 60 ) ) {
			if ( RateLimiter::allow( 'pii-alert:' . self::pii_bucket(), 1, 60 ) ) {
				AuditLog::record( 'pii.rate_limited', $entity, null, array( 'limit_per_minute' => self::pii_limit() ) );
			}
			throw new ApiException( 'wotoyi_rate_limited', 'Too many records read. Try again in a minute.', 429 );
		}
	}

	private static function pii_consume( int $rows ): void {
		RateLimiter::consume( self::pii_bucket(), $rows, self::pii_limit(), 60 );
	}

	/**
	 * List of a personal-data entity: per-user row budget and an audit entry (actor, filter names, count) per read (M3, M4).
	 *
	 * @param array<string,mixed> $q
	 * @return array<string,mixed>
	 */
	private static function pii_list( string $entity, EntityService $svc, array $q ): array {
		self::pii_guard( $entity );
		$out = $svc->list( $q );
		self::pii_consume( count( $out['items'] ) );
		AuditLog::record(
			$entity . '.list',
			$entity,
			null,
			array(
				'count'    => count( $out['items'] ),
				'total'    => $out['total'],
				'page'     => $out['page'],
				'per_page' => $out['per_page'],
				'filters'  => array_values( array_intersect( array_keys( $q ), array( 'q', 'source', 'assigned_to', 'city', 'country', 'stage', 'vehicle_uuid', 'customer_uuid', 'status', 'job_title' ) ) ),
			)
		);
		return $out;
	}

	/** Rate limit per tenant for sensitive actions. */
	private static function throttle( string $bucket, int $limit, int $window ): void {
		if ( ! RateLimiter::allow( 'api:' . $bucket . ':' . TenantContext::uuid(), $limit, $window ) ) {
			throw new ApiException( 'wotoyi_rate_limited', 'Too many requests. Try again later.', 429 );
		}
	}

	/** Run a best-effort step; its failure must not fail the main action. @return array<string,mixed> */
	private static function safe( callable $fn ): array {
		try {
			return $fn();
		} catch ( \Throwable $e ) {
			error_log( 'wotoyi-dealer-platform step failed: ' . get_class( $e ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return array( 'status' => 'error' );
		}
	}

	/** @return array<string,mixed> */
	private static function me(): array {
		$m = TenantContext::member();
		return array(
			'user_id' => TenantContext::user_id(),
			'tenant'  => Plugin::tenants()->current(),
			'member'  => $m ? array( 'id' => $m['uuid'], 'role' => $m['role'], 'display_name' => $m['display_name'] ) : null,
			'caps'    => Roles::caps( (string) TenantContext::role() ),
		);
	}

	/**
	 * Standard CRUD for an entity.
	 *
	 * @param callable():EntityService $svc
	 */
	private static function crud( string $base, string $caps_prefix, callable $svc, bool $deletable ): void {
		$entity = $caps_prefix;
		$pii    = in_array( $entity, array( 'customers', 'leads', 'employees' ), true );
		self::route( $base, 'GET', $entity . '.read', false, static fn( $r ) => $pii ? self::pii_list( $entity, $svc(), $r->get_query_params() ) : $svc()->list( $r->get_query_params() ) );
		self::route( $base, 'POST', $entity . '.write', true, static fn( $r ) => self::created( $svc(), Handler::body( $r ) ) );
		self::route( $base . '/' . self::ID, 'GET', $entity . '.read', false, static fn( $r ) => $svc()->get( (string) $r['id'] ) );
		self::route( $base . '/' . self::ID, 'PUT, PATCH', $entity . '.write', true, static fn( $r ) => self::with_warnings( $svc(), $svc()->update( (string) $r['id'], Handler::body( $r ) ) ) );
		if ( $deletable ) {
			self::route( $base . '/' . self::ID, 'DELETE', $entity . '.delete', true, static function ( $r ) use ( $svc ) {
				$svc()->archive( (string) $r['id'] );
				return array( 'deleted' => true );
			} );
		}
	}

	/** CRUD for synced entities: status changes through PATCH need the matching capability. @param callable():EntityService $svc */
	private static function synced( string $base, string $caps, callable $svc ): void {
		self::route( $base, 'GET', $caps . '.read', false, static fn( $r ) => $svc()->list( $r->get_query_params() ) );
		self::route( $base, 'POST', $caps . '.write', true, static function ( $r ) use ( $caps, $svc ) {
			$b = Handler::body( $r );
			if ( 'published' === ( $b['status'] ?? null ) ) {
				self::require_cap( $caps . '.publish' );
			}
			return self::created( $svc(), $b );
		} );
		self::route( $base . '/' . self::ID, 'GET', $caps . '.read', false, static fn( $r ) => $svc()->get( (string) $r['id'] ) );
		self::route( $base . '/' . self::ID, 'PUT, PATCH', $caps . '.write', true, static function ( $r ) use ( $caps, $svc ) {
			$b = Handler::body( $r );
			if ( isset( $b['status'] ) ) {
				$needs = match ( (string) $b['status'] ) {
					'published', 'unpublished' => $caps . '.publish',
					'sold' => 'deals.write',
					'deleted' => $caps . '.delete',
					default => $caps . '.write',
				};
				self::require_cap( $needs );
			}
			return self::with_warnings( $svc(), $svc()->update( (string) $r['id'], $b ) );
		} );
		self::route( $base . '/' . self::ID, 'DELETE', $caps . '.delete', true, static function ( $r ) use ( $svc ) {
			$svc()->archive( (string) $r['id'] );
			return array( 'deleted' => true );
		} );
	}

	private static function require_cap( string $cap ): void {
		if ( ! Roles::can( TenantContext::role(), $cap ) ) {
			throw ApiException::forbidden();
		}
	}

	/** @param array<string,mixed> $body */
	private static function created( EntityService $svc, array $body ): \WP_REST_Response {
		return new \WP_REST_Response( self::with_warnings( $svc, $svc->create( $body ) ), 201 );
	}

	/** @param array<string,mixed> $out @return array<string,mixed> */
	private static function with_warnings( EntityService $svc, array $out ): array {
		$w = $svc->warnings();
		return $w ? $out + array( '_warnings' => $w ) : $out;
	}

	/** Tenant route: permission = membership + capability (+ nonce on writes). @param callable(\WP_REST_Request):mixed $fn */
	private static function route( string $path, string $methods, string $cap, bool $write, callable $fn ): void {
		register_rest_route(
			self::API_NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => static fn( \WP_REST_Request $request ) => Handler::run( $request, $fn ),
				'permission_callback' => static fn( \WP_REST_Request $request ) => Access::authorize( $request, $cap, $write ),
			)
		);
	}

	/** Route for any logged-in user without tenant context (invitation acceptance); nonce on writes. */
	private static function user_route( string $path, string $methods, callable $fn ): void {
		register_rest_route(
			self::API_NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => static fn( \WP_REST_Request $request ) => Handler::run_user( $request, $fn ),
				'permission_callback' => static fn( \WP_REST_Request $request ) => Access::authorize_user( $request, true ),
			)
		);
	}

	/** Platform route: WordPress capability + nonce on writes. */
	private static function platform( string $path, string $methods, bool $write, callable $fn ): void {
		register_rest_route(
			self::API_NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => static fn( \WP_REST_Request $request ) => Handler::run_platform( $request, $fn ),
				'permission_callback' => static fn( \WP_REST_Request $request ) => Access::authorize_platform( $request, $write ),
			)
		);
	}
}
