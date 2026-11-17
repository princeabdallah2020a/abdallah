<?php
/**
 * Replay protection: each (dealer, nonce) may be consumed once within its validity window.
 * Atomic through the primary key: the INSERT either succeeds (first use) or fails (replay).
 *
 * @package Wotoyi\Marketplace
 */

declare(strict_types=1);

namespace Wotoyi\Marketplace\Repository;

use Wotoyi\Marketplace\Settings;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\Db;

defined( 'ABSPATH' ) || exit;

final class NonceRepository {

	public function table(): string {
		return Db::table( 'sync_nonces' );
	}

	/** @return bool True when the nonce was fresh and is now consumed. */
	public function consume( int $dealer_id, string $nonce ): bool {
		$suppress = Db::wpdb()->suppress_errors( true );
		$ok       = Db::wpdb()->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'dealer_id'  => $dealer_id,
				'nonce'      => $nonce,
				'expires_at' => Clock::mysql( Clock::now() + Settings::nonce_ttl() ),
			)
		);
		Db::wpdb()->suppress_errors( $suppress );
		return false !== $ok && 1 === (int) $ok;
	}

	public function prune(): void {
		Db::wpdb()->query( Db::prepare( "DELETE FROM {$this->table()} WHERE expires_at < %s", array( Clock::mysql() ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
