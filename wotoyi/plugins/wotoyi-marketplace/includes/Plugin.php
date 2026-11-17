<?php
/**
 * Bootstrap and service locator.
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace;

use Wotoyi\Marketplace\Repository\DealerRepository;
use Wotoyi\Marketplace\Repository\FavouriteRepository;
use Wotoyi\Marketplace\Repository\LeadRepository;
use Wotoyi\Marketplace\Repository\NonceRepository;
use Wotoyi\Marketplace\Repository\SavedSearchRepository;
use Wotoyi\Marketplace\Repository\ServiceRepository;
use Wotoyi\Marketplace\Repository\SyncLogRepository;
use Wotoyi\Marketplace\Repository\VehicleRepository;
use Wotoyi\Marketplace\Rest\Routes;
use Wotoyi\Marketplace\Search\SearchService;
use Wotoyi\Marketplace\Sync\Authenticator;
use Wotoyi\Marketplace\Sync\LocalTransport;
use Wotoyi\Marketplace\Sync\Pairing;
use Wotoyi\Marketplace\Sync\Receiver;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var array<string,object> */
	private static array $services = array();

	public static function boot(): void {
		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ) );
		add_action( 'rest_api_init', array( Routes::class, 'register' ) );
		add_action( Installer::CRON_HOOK, array( self::class, 'prune' ) );
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'wotoyi-marketplace', false, dirname( plugin_basename( WOTOYI_MP_FILE ) ) . '/languages' );
			}
		);
	}

	/** Daily housekeeping: expired nonces and old sync log rows. */
	public static function prune(): void {
		self::nonces()->prune();
		self::sync_log()->prune( Settings::int( 'log_retention_days' ) );
	}

	/** Drop cached service objects (tests). */
	public static function reset(): void {
		self::$services = array();
	}

	private static function get( string $key, callable $factory ): object {
		return self::$services[ $key ] ??= $factory();
	}

	public static function dealers(): DealerRepository {
		return self::get( 'dealers', static fn() => new DealerRepository() );
	}

	public static function vehicles(): VehicleRepository {
		return self::get( 'vehicles', static fn() => new VehicleRepository() );
	}

	public static function services(): ServiceRepository {
		return self::get( 'services', static fn() => new ServiceRepository() );
	}

	public static function leads(): LeadRepository {
		return self::get( 'leads', static fn() => new LeadRepository() );
	}

	public static function favourites(): FavouriteRepository {
		return self::get( 'favourites', static fn() => new FavouriteRepository() );
	}

	public static function saved_searches(): SavedSearchRepository {
		return self::get( 'saved_searches', static fn() => new SavedSearchRepository() );
	}

	public static function sync_log(): SyncLogRepository {
		return self::get( 'sync_log', static fn() => new SyncLogRepository() );
	}

	public static function nonces(): NonceRepository {
		return self::get( 'nonces', static fn() => new NonceRepository() );
	}

	public static function search(): SearchService {
		return self::get( 'search', static fn() => new SearchService() );
	}

	public static function receiver(): Receiver {
		return self::get( 'receiver', static fn() => new Receiver( self::dealers(), self::vehicles(), self::services(), self::sync_log(), self::leads() ) );
	}

	public static function authenticator(): Authenticator {
		return self::get( 'authenticator', static fn() => new Authenticator( self::dealers(), self::nonces() ) );
	}

	public static function pairing(): Pairing {
		return self::get( 'pairing', static fn() => new Pairing( self::dealers() ) );
	}

	public static function local_transport(): LocalTransport {
		return self::get( 'local_transport', static fn() => new LocalTransport( self::dealers(), self::receiver() ) );
	}
}
