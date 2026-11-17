<?php
/**
 * Dashboard KPIs. Every number is an aggregate over real rows of the current tenant; nothing is estimated,
 * defaulted or sampled. Ratios are null when their denominator is zero. Money figures need finance.read.
 *
 * @package Wotoyi\DealerPlatform
 */

declare(strict_types=1);

namespace Wotoyi\DealerPlatform\Service;

use Wotoyi\DealerPlatform\Auth\Roles;
use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Sync\Outbox;
use Wotoyi\DealerPlatform\Sync\SyncState;

defined( 'ABSPATH' ) || exit;

final class Dashboard {

	/** @return array<string,mixed> */
	public function kpis( int $days = 30 ): array {
		$days    = max( 1, min( 365, $days ) );
		$now     = Clock::now();
		$since   = Clock::mysql( $now - $days * DAY_IN_SECONDS );
		$now_sql = Clock::mysql( $now );
		$finance = null === TenantContext::role() || Roles::can( TenantContext::role(), 'finance.read' );

		$live = array( 'deleted_at' => array( 'null' ) );

		// Vehicles.
		$by_status = TenantDb::group_count( 'vehicles', 'status', $live );
		$pub       = TenantDb::select( 'vehicles', array( 'status' => 'published', 'published_at' => array( 'notnull' ) ) + $live, array( 'columns' => array( 'published_at' ), 'limit' => 1000 ) );
		$ages      = array_map( static fn( array $r ): float => max( 0, ( $now - (int) strtotime( $r['published_at'] . ' UTC' ) ) / DAY_IN_SECONDS ), $pub );
		$vehicles  = array(
			'total'                => array_sum( $by_status ),
			'by_status'            => array(
				'draft'       => $by_status['draft'] ?? 0,
				'published'   => $by_status['published'] ?? 0,
				'unpublished' => $by_status['unpublished'] ?? 0,
				'sold'        => $by_status['sold'] ?? 0,
			),
			'in_stock'             => ( $by_status['published'] ?? 0 ) + ( $by_status['unpublished'] ?? 0 ),
			'avg_days_published'   => $ages ? round( array_sum( $ages ) / count( $ages ), 1 ) : null,
			'services_published'   => TenantDb::count( 'services', array( 'status' => 'published' ) + $live ),
		);

		// Leads.
		$stage_counts = TenantDb::group_count( 'leads', 'stage', $live );
		$won          = TenantDb::count( 'leads', array( 'stage' => 'won', 'updated_at' => array( '>=', $since ) ) + $live );
		$lost         = TenantDb::count( 'leads', array( 'stage' => 'lost', 'updated_at' => array( '>=', $since ) ) + $live );
		$leads        = array(
			'total'              => array_sum( $stage_counts ),
			'by_stage'           => array_replace( array_fill_keys( \Wotoyi\DealerPlatform\Schema\Entities::LEAD_STAGES, 0 ), $stage_counts ),
			'by_source'          => TenantDb::group_count( 'leads', 'source', $live ),
			'new_in_period'      => TenantDb::count( 'leads', array( 'created_at' => array( '>=', $since ) ) + $live ),
			'won_in_period'      => $won,
			'lost_in_period'     => $lost,
			'win_rate'           => ( $won + $lost ) > 0 ? round( $won / ( $won + $lost ), 4 ) : null,
			'overdue_follow_ups' => TenantDb::count( 'leads', array( 'next_follow_up_at' => array( '<', $now_sql ), 'stage' => array( 'in', array( 'new', 'contacted', 'qualified', 'proposal' ) ) ) + $live ),
		);

		// Sales.
		$completed = array( 'status' => 'completed', 'sold_at' => array( '>=', $since ) );
		$sales     = array( 'completed_in_period' => TenantDb::count( 'deals', $completed ), 'open' => TenantDb::count( 'deals', array( 'status' => array( 'in', array( 'draft', 'confirmed' ) ) ) ) );
		if ( $finance ) {
			$revenue                  = (int) TenantDb::sum( 'deals', 'amount', $completed );
			$sales['revenue']         = $revenue;
			$sales['average_price']   = $sales['completed_in_period'] > 0 ? (int) round( $revenue / $sales['completed_in_period'] ) : null;
			$sales['margin']          = $this->margin( $completed );
			$sales['currency']        = (string) TenantContext::tenant()['currency'];
		}

		// Rentals.
		$rentals = array(
			'active'             => TenantDb::count( 'rentals', array( 'status' => 'active' ) ),
			'reserved'           => TenantDb::count( 'rentals', array( 'status' => 'reserved' ) ),
			'returned_in_period' => TenantDb::count( 'rentals', array( 'status' => 'returned', 'end_date' => array( '>=', substr( $since, 0, 10 ) ) ) ),
		);
		if ( $finance ) {
			$rentals['revenue'] = (int) TenantDb::sum( 'rentals', 'total', array( 'status' => 'returned', 'end_date' => array( '>=', substr( $since, 0, 10 ) ) ) );
		}

		// Tasks and customers.
		$tasks = array(
			'open'    => TenantDb::count( 'tasks', array( 'status' => 'open' ) ),
			'overdue' => TenantDb::count( 'tasks', array( 'status' => 'open', 'due_at' => array( '<', $now_sql ) ) ),
		);
		$customers = array( 'total' => TenantDb::count( 'customers', $live ), 'new_in_period' => TenantDb::count( 'customers', array( 'created_at' => array( '>=', $since ) ) + $live ) );

		// Sync health.
		$stats = Outbox::stats();
		$state = SyncState::get();
		$sync  = array(
			'status'        => $state['status'],
			'transport'     => $state['transport'],
			'pending'       => $stats['pending'] ?? 0,
			'sending'       => $stats['sending'] ?? 0,
			'rejected'      => $stats['rejected'] ?? 0,
			'dead'          => $stats['dead'] ?? 0,
			'sent'          => $stats['sent'] ?? 0,
			'last_push_at'  => $state['last_push_at'],
			'last_error'    => $state['last_error'],
		);

		return array(
			'period'       => array( 'days' => $days, 'since' => $since, 'until' => $now_sql ),
			'vehicles'     => $vehicles,
			'leads'        => $leads,
			'sales'        => $sales,
			'rentals'      => $rentals,
			'tasks'        => $tasks,
			'customers'    => $customers,
			'sync'         => $sync,
			'generated_at' => $now_sql,
		);
	}

	/**
	 * Gross margin of completed deals in the period: sum(amount - purchase_cost) over deals whose vehicle has a
	 * purchase cost. Deals without a known cost are counted separately instead of being guessed.
	 *
	 * @param array<string,mixed> $where
	 * @return array{amount:int,deals_with_cost:int,deals_without_cost:int}
	 */
	private function margin( array $where ): array {
		$deals = TenantDb::select( 'deals', $where, array( 'columns' => array( 'vehicle_uuid', 'amount' ), 'limit' => 1000 ) );
		$sum   = 0;
		$with  = 0;
		$no    = 0;
		foreach ( $deals as $d ) {
			$v = TenantDb::get( 'vehicles', array( 'uuid' => $d['vehicle_uuid'] ) );
			if ( $v && null !== $v['purchase_cost'] ) {
				$sum += (int) $d['amount'] - (int) $v['purchase_cost'];
				++$with;
			} else {
				++$no;
			}
		}
		return array( 'amount' => $sum, 'deals_with_cost' => $with, 'deals_without_cost' => $no );
	}
}
