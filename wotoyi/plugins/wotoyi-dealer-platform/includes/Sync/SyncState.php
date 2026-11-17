<?php
/**
 * Per-tenant sync/pairing state (one row). The marketplace secret is stored encrypted and is never returned by
 * any API (present() exposes only non-secret fields).
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class SyncState {

	/** @return array<string,mixed> */
	public static function get(): array {
		$row = TenantDb::get( 'sync_state', array() );
		return $row ?: array(
			'status'                 => 'unpaired',
			'transport'              => null,
			'base_url'               => null,
			'key_id'                 => null,
			'secret_enc'             => null,
			'marketplace_dealer_id'  => null,
			'paired_at'              => null,
			'last_push_at'           => null,
			'last_pull_at'           => null,
			'last_reconcile_at'      => null,
			'last_reconcile_summary' => null,
			'last_error'             => null,
		);
	}

	/** @param array<string,mixed> $data */
	public static function save( array $data ): void {
		$data['updated_at'] = Clock::mysql();
		if ( isset( $data['last_error'] ) ) {
			$data['last_error'] = mb_substr( (string) $data['last_error'], 0, 255 );
		}
		if ( TenantDb::get( 'sync_state', array() ) ) {
			TenantDb::update( 'sync_state', array( 'id' => array( '>', 0 ) ), $data );
		} else {
			TenantDb::insert( 'sync_state', $data + array( 'status' => 'unpaired' ) );
		}
	}

	/** Decrypted marketplace secret (internal use only). */
	public static function secret(): ?string {
		return Crypto::decrypt( self::get()['secret_enc'] ?? null );
	}

	/** @return array<string,mixed> Safe view for the API (no secret, no ciphertext). */
	public static function present(): array {
		$s = self::get();
		$stats = Outbox::stats();
		return array(
			'status'                => $s['status'],
			'transport'             => $s['transport'],
			'base_url'              => $s['base_url'],
			'key_id'                => $s['key_id'],
			'marketplace_dealer_id' => $s['marketplace_dealer_id'],
			'paired_at'             => $s['paired_at'],
			'last_push_at'          => $s['last_push_at'],
			'last_pull_at'          => $s['last_pull_at'],
			'last_reconcile_at'     => $s['last_reconcile_at'],
			'last_reconcile_summary' => $s['last_reconcile_summary'] ? json_decode( (string) $s['last_reconcile_summary'], true ) : null,
			'last_error'            => $s['last_error'],
			'outbox'                => array(
				'pending'  => $stats['pending'] ?? 0,
				'sending'  => $stats['sending'] ?? 0,
				'sent'     => $stats['sent'] ?? 0,
				'rejected' => $stats['rejected'] ?? 0,
				'dead'     => $stats['dead'] ?? 0,
			),
			'secret_key_configured' => Crypto::key_available(),
		);
	}
}
