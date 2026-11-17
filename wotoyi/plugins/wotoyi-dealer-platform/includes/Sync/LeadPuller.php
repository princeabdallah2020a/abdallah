<?php
/**
 * Pulls the dealer's pending marketplace leads into the CRM and acknowledges them.
 * Order: import (own DB transaction per lead) -> acknowledge. If the ack is lost the marketplace redelivers and
 * the import is de-duplicated on external_id, so a lead is never lost and never duplicated.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Sync;

use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Service\AuditLog;
use Wotoyi\DealerPlatform\Support\Clock;

defined( 'ABSPATH' ) || exit;

final class LeadPuller {

	/** @return array<string,mixed> {status, pulled, imported, duplicates, invalid, acknowledged} */
	public static function run_for_current( int $max_pages = 5 ): array {
		$res   = array( 'status' => 'ok', 'pulled' => 0, 'imported' => 0, 'duplicates' => 0, 'invalid' => 0, 'acknowledged' => 0 );
		$state = SyncState::get();
		if ( 'paired' !== $state['status'] ) {
			$res['status'] = 'not_paired';
			return $res;
		}
		try {
			$transport = TransportFactory::make( $state );
			for ( $page = 0; $page < $max_pages; $page++ ) {
				$leads = $transport->pull_leads( 50 );
				if ( ! $leads ) {
					break;
				}
				$res['pulled'] += count( $leads );
				$to_ack         = array();
				foreach ( $leads as $lead ) {
					if ( ! is_array( $lead ) ) {
						continue;
					}
					$outcome = Plugin::leads()->import_marketplace( $lead );
					if ( 'imported' === $outcome ) {
						++$res['imported'];
					} elseif ( 'duplicate' === $outcome ) {
						++$res['duplicates'];
					} else {
						++$res['invalid']; // Acknowledged anyway so one bad record cannot block the queue; counted and audited.
					}
					if ( isset( $lead['uuid'] ) && is_string( $lead['uuid'] ) ) {
						$to_ack[] = $lead['uuid'];
					}
				}
				$acked = $to_ack ? $transport->ack_leads( $to_ack ) : 0;
				$res['acknowledged'] += $acked;
				if ( 0 === $acked ) {
					break; // No progress: do not loop on the same page.
				}
			}
			SyncState::save( array( 'last_pull_at' => Clock::mysql() ) );
			if ( $res['invalid'] > 0 ) {
				AuditLog::record( 'sync.leads_invalid', 'sync', null, array( 'count' => $res['invalid'] ) );
			}
		} catch ( SyncException $e ) {
			$res['status'] = $e->is_auth() ? 'auth_failed' : 'error';
			SyncState::save( array( 'last_error' => 'leads: ' . $e->error_code() . ': ' . $e->getMessage(), 'last_pull_at' => Clock::mysql() ) + ( $e->is_auth() ? array( 'status' => 'auth_failed' ) : array() ) );
		}
		return $res;
	}
}
