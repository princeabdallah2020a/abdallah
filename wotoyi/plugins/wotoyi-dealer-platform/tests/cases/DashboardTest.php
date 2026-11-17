<?php
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\Clock;

final class DashboardTest extends Wotoyi_Test_Case {

	private array $t;

	protected function setUp(): void {
		parent::setUp();
		$this->t = Fixtures::tenant( 'Dealer A', 11 );
	}

	private function kpis( ?string $role = 'owner', int $days = 30 ): array {
		return Fixtures::in( $this->t, fn() => Plugin::dashboard()->kpis( $days ), $role );
	}

	public function test_empty_tenant_reports_zeros_and_nulls_never_invented_numbers(): void {
		$k = $this->kpis();
		$this->assertSame( 0, $k['vehicles']['total'] );
		$this->assertSame( 0, $k['vehicles']['in_stock'] );
		$this->assertNull( $k['vehicles']['avg_days_published'] );
		$this->assertSame( 0, $k['leads']['total'] );
		$this->assertNull( $k['leads']['win_rate'] );
		$this->assertSame( 0, $k['sales']['revenue'] );
		$this->assertNull( $k['sales']['average_price'] );
		$this->assertSame( array( 'amount' => 0, 'deals_with_cost' => 0, 'deals_without_cost' => 0 ), $k['sales']['margin'] );
		$this->assertSame( 0, $k['rentals']['active'] );
		$this->assertSame( 0, $k['tasks']['open'] );
		$this->assertSame( 'unpaired', $k['sync']['status'] );
		$this->assertSame( 0, $k['sync']['pending'] );
	}

	public function test_kpis_match_the_rows_exactly(): void {
		Fixtures::in( $this->t, function () {
			$pub1 = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published', 'purchase_cost' => 10000000 ) ) );
			Fixtures::tick( 2 * 86400 );
			$pub2 = Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Kia', 'model' => 'Rio', 'status' => 'published', 'purchase_cost' => 5000000 ) ) );
			Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Nissan', 'model' => 'Qashqai' ) ) ); // draft
			$sold = Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Peugeot', 'model' => '208', 'status' => 'published', 'purchase_cost' => 6000000 ) ) );
			$nocost = Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Dacia', 'model' => 'Duster', 'status' => 'published', 'purchase_cost' => null ) ) );
			Plugin::deals()->sell_vehicle( $sold['id'], array( 'amount' => 8000000 ) );
			Plugin::deals()->sell_vehicle( $nocost['id'], array( 'amount' => 9000000 ) );
			Plugin::deals()->create( array( 'vehicle_uuid' => $pub1['id'], 'amount' => 1 ) ); // open draft deal

			Plugin::leads()->create( array( 'name' => 'a', 'phone' => '+221770000001', 'source' => 'marketplace' ) );
			$won = Plugin::leads()->create( array( 'name' => 'b', 'phone' => '+221770000002', 'source' => 'walk_in' ) );
			$lost = Plugin::leads()->create( array( 'name' => 'c', 'phone' => '+221770000003', 'source' => 'walk_in', 'next_follow_up_at' => '2027-01-01 00:00:00' ) );
			Plugin::leads()->change_stage( $won['id'], 'won' );
			Plugin::leads()->change_stage( $lost['id'], 'lost' );
			Plugin::leads()->create( array( 'name' => 'd', 'phone' => '+221770000004', 'next_follow_up_at' => '2027-01-01 00:00:00' ) ); // overdue, still new

			Plugin::tasks()->create( array( 'title' => 'overdue', 'due_at' => '2027-01-01 00:00:00' ) );
			Plugin::tasks()->create( array( 'title' => 'future', 'due_at' => '2030-01-01 00:00:00' ) );
			Plugin::tasks()->complete( Plugin::tasks()->create( array( 'title' => 'done' ) )['id'] );

			$c = Plugin::customers()->create( array( 'name' => 'R', 'phone' => '+221770000005' ) );
			$rv = Plugin::vehicles()->create( Fixtures::vehicle( array( 'make' => 'Renault', 'model' => 'Clio', 'status' => 'published' ) ) );
			$r1 = Plugin::rentals()->create( array( 'vehicle_uuid' => $rv['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-01-20', 'end_date' => '2027-01-22', 'daily_rate' => 30000 ) );
			Plugin::rentals()->update( $r1['id'], array( 'status' => 'active' ) );
			Plugin::rentals()->update( $r1['id'], array( 'status' => 'returned' ) );
			Plugin::rentals()->create( array( 'vehicle_uuid' => $rv['id'], 'customer_uuid' => $c['id'], 'start_date' => '2027-03-01', 'end_date' => '2027-03-03' ) );
		} );
		$k = $this->kpis();
		$v = $k['vehicles'];
		$this->assertSame( 6, $v['total'], '3 published + 2 sold + 1 draft' );
		$this->assertSame( 2, $v['by_status']['sold'] );
		$this->assertSame( 0, $v['by_status']['unpublished'] );
		$this->assertSame( 1, $v['by_status']['draft'] );
		$this->assertSame( 3, $v['by_status']['published'] );
		$this->assertSame( 3, $v['in_stock'] );
		$this->assertSame( 2, $k['sales']['completed_in_period'] );
		$this->assertSame( 17000000, $k['sales']['revenue'] );
		$this->assertSame( 8500000, $k['sales']['average_price'] );
		$this->assertSame( array( 'amount' => 2000000, 'deals_with_cost' => 1, 'deals_without_cost' => 1 ), $k['sales']['margin'], 'margin only from known costs; unknown counted, not guessed' );
		$this->assertSame( 1, $k['sales']['open'] );
		$this->assertSame( 4, $k['leads']['total'] );
		$this->assertSame( 1, $k['leads']['by_stage']['won'] );
		$this->assertSame( 1, $k['leads']['by_stage']['lost'] );
		$this->assertSame( 0.5, $k['leads']['win_rate'] );
		$this->assertSame( 1, $k['leads']['overdue_follow_ups'], 'only the open lead with a past follow-up counts' );
		$this->assertEquals( array( 'marketplace' => 1, 'walk_in' => 2, 'other' => 1 ), $k['leads']['by_source'] );
		$this->assertSame( 2, $k['tasks']['open'] );
		$this->assertSame( 1, $k['tasks']['overdue'] );
		$this->assertSame( 0, $k['rentals']['active'] );
		$this->assertSame( 1, $k['rentals']['reserved'] );
		$this->assertSame( 1, $k['rentals']['returned_in_period'] );
		$this->assertSame( 60000, $k['rentals']['revenue'] );
		$this->assertSame( 1, $k['customers']['total'] );
		$this->assertSame( 'XOF', $k['sales']['currency'] );
		$this->assertTrue( $k['sync']['pending'] > 0 );
		$this->assertTrue( $k['vehicles']['avg_days_published'] >= 0 );
	}

	public function test_period_window_excludes_old_rows(): void {
		Fixtures::in( $this->t, function () {
			$v = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) );
			Plugin::deals()->sell_vehicle( $v['id'], array( 'amount' => 7000000 ) );
		} );
		$this->assertSame( 1, $this->kpis( 'owner', 30 )['sales']['completed_in_period'] );
		Fixtures::tick( 45 * 86400 );
		$this->assertSame( 0, $this->kpis( 'owner', 30 )['sales']['completed_in_period'] );
		$this->assertSame( 7000000, $this->kpis( 'owner', 90 )['sales']['revenue'] );
		$this->assertSame( 365, $this->kpis( 'owner', 99999 )['period']['days'] );
	}

	public function test_dashboard_is_tenant_scoped(): void {
		Fixtures::in( $this->t, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		$b = Fixtures::tenant( 'Dealer B', 12 );
		$kb = Fixtures::in( $b, fn() => Plugin::dashboard()->kpis() );
		$this->assertSame( 0, $kb['vehicles']['total'] );
		$this->assertSame( 0, $kb['sync']['pending'] );
	}
}
