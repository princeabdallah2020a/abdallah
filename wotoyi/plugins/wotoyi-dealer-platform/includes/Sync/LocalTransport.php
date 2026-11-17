<?php
/**
 * Same-process delivery for the single-install demo (InfinityFree blocks server-to-server HTTP). Calls the
 * marketplace plugin's public PHP API, so the SAME Receiver validation/versioning/idempotency runs; only the HMAC
 * layer is replaced by "same PHP process" trust. The marketplace refuses this unless its own local mode is on.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

defined( 'ABSPATH' ) || exit;

final class LocalTransport implements Transport {

	public function __construct( private string $dealer_ref ) {}

	public function name(): string {
		return 'local';
	}

	/** Is the marketplace plugin loaded in this PHP process? */
	public static function available(): bool {
		return function_exists( 'wotoyi_marketplace_local_receive' ) && class_exists( '\\Wotoyi\\Marketplace\\Plugin' );
	}

	private function guard(): void {
		if ( ! self::available() ) {
			throw new SyncException( 'marketplace_missing', 'The marketplace plugin is not active on this site.', true );
		}
	}

	/**
	 * @template T
	 * @param callable():T $fn
	 * @return T
	 */
	private function call( callable $fn ): mixed {
		$this->guard();
		try {
			return $fn();
		} catch ( SyncException $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			throw new SyncException( 'local_error', 'Local marketplace call failed: ' . $e->getMessage(), true );
		}
	}

	public function send_batch( array $events ): array {
		return $this->call(
			function () use ( $events ): array {
				$out = array();
				foreach ( $events as $event ) {
					$res = \wotoyi_marketplace_local_receive( $this->dealer_ref, $event );
					if ( isset( $event['event_id'] ) ) {
						$out[ (string) $event['event_id'] ] = $res;
					}
				}
				return $out;
			}
		);
	}

	public function manifest( string $type, int $cursor, int $limit ): array {
		return $this->call(
			function () use ( $type, $cursor, $limit ): array {
				$mp     = '\\Wotoyi\\Marketplace\\Plugin';
				$dealer = $mp::dealers()->find_by_ref( $this->dealer_ref );
				if ( ! $dealer ) {
					throw new SyncException( 'unknown_local_dealer', 'Dealer is not registered on the marketplace.', false, 0, true );
				}
				$repo = 'service' === $type ? $mp::services() : $mp::vehicles();
				$res  = $repo->manifest( (int) $dealer['id'], $cursor, $limit );
				return array( 'items' => $res['items'], 'next' => $res['next'] );
			}
		);
	}

	public function pull_leads( int $limit ): array {
		return $this->call( fn(): array => array_values( (array) \Wotoyi\Marketplace\Plugin::local_transport()->pull_leads( $this->dealer_ref, $limit ) ) );
	}

	public function ack_leads( array $lead_uuids ): int {
		return $this->call( fn(): int => (int) \Wotoyi\Marketplace\Plugin::local_transport()->ack_leads( $this->dealer_ref, array_values( $lead_uuids ) ) );
	}
}
