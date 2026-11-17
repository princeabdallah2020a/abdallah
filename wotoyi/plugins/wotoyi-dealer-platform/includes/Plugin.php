<?php
/**
 * Bootstrap and service locator.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform;

use Wotoyi\DealerPlatform\Rest\Routes;
use Wotoyi\DealerPlatform\Service\ActivityService;
use Wotoyi\DealerPlatform\Service\CustomerService;
use Wotoyi\DealerPlatform\Service\Dashboard;
use Wotoyi\DealerPlatform\Service\DealService;
use Wotoyi\DealerPlatform\Service\EmployeeService;
use Wotoyi\DealerPlatform\Service\LeadService;
use Wotoyi\DealerPlatform\Service\MemberService;
use Wotoyi\DealerPlatform\Service\RentalService;
use Wotoyi\DealerPlatform\Service\ServiceCatalog;
use Wotoyi\DealerPlatform\Service\TaskService;
use Wotoyi\DealerPlatform\Service\TenantService;
use Wotoyi\DealerPlatform\Service\VehicleService;
use Wotoyi\DealerPlatform\Support\Crypto;
use Wotoyi\DealerPlatform\Sync\Outbox;
use Wotoyi\DealerPlatform\Sync\Worker;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var array<string,object> */
	private static array $services = array();

	public static function boot(): void {
		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ) );
		add_action( 'rest_api_init', array( Routes::class, 'register' ) );
		add_filter( 'cron_schedules', array( self::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( Worker::HOOK_TICK, array( Worker::class, 'tick' ) );
		add_action( Worker::HOOK_RECONCILE, array( Worker::class, 'reconcile_all' ) );
		add_action( Installer::HOOK_PRUNE, array( self::class, 'prune' ) );
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'wotoyi-dealer-platform', false, dirname( plugin_basename( WOTOYI_DP_FILE ) ) . '/languages' );
			}
		);
	}

	/** @param array<string,array<string,mixed>> $schedules */
	public static function cron_schedules( array $schedules ): array {
		$schedules['wotoyi_dp_minute'] = array( 'interval' => 60, 'display' => 'Every minute (Wotoyi sync)' );
		return $schedules;
	}

	public static function admin_notice(): void {
		if ( Crypto::key_available() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Wotoyi Dealer Platform: define WOTOYI_SECRET_KEY (32+ random characters) in wp-config.php. Marketplace pairing and sync are disabled until then.', 'wotoyi-dealer-platform' )
			. '</p></div>';
	}

	/** Daily housekeeping: delivered outbox events older than the retention window, for every tenant. */
	public static function prune(): void {
		\Wotoyi\DealerPlatform\Data\Invitations::prune();
		foreach ( \Wotoyi\DealerPlatform\Data\Directory::list_tenants( 500 ) as $tenant ) {
			\Wotoyi\DealerPlatform\Data\TenantContext::run( $tenant, static fn() => Outbox::prune( Settings::int( 'outbox_retention_days' ) ) );
		}
	}

	/** Drop cached service objects (tests). */
	public static function reset(): void {
		self::$services = array();
		Outbox::reset();
		\Wotoyi\DealerPlatform\Data\TenantContext::reset();
	}

	private static function get( string $key, callable $factory ): object {
		return self::$services[ $key ] ??= $factory();
	}

	public static function vehicles(): VehicleService {
		return self::get( 'vehicles', static fn() => new VehicleService() );
	}

	public static function services(): ServiceCatalog {
		return self::get( 'services', static fn() => new ServiceCatalog() );
	}

	public static function customers(): CustomerService {
		return self::get( 'customers', static fn() => new CustomerService() );
	}

	public static function activities(): ActivityService {
		return self::get( 'activities', static fn() => new ActivityService() );
	}

	public static function leads(): LeadService {
		return self::get( 'leads', static fn() => new LeadService( self::activities(), self::customers() ) );
	}

	public static function deals(): DealService {
		return self::get( 'deals', static fn() => new DealService( self::vehicles(), self::leads() ) );
	}

	public static function rentals(): RentalService {
		return self::get( 'rentals', static fn() => new RentalService( self::vehicles() ) );
	}

	public static function employees(): EmployeeService {
		return self::get( 'employees', static fn() => new EmployeeService() );
	}

	public static function tasks(): TaskService {
		return self::get( 'tasks', static fn() => new TaskService() );
	}

	public static function members(): MemberService {
		return self::get( 'members', static fn() => new MemberService() );
	}

	public static function tenants(): TenantService {
		return self::get( 'tenants', static fn() => new TenantService( self::members() ) );
	}

	public static function dashboard(): Dashboard {
		return self::get( 'dashboard', static fn() => new Dashboard() );
	}
}
