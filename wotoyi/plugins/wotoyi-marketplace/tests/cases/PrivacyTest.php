<?php
/**
 * Regression tests for SECURITY-REVIEW H2: retention, consent proof, WordPress privacy exporter/eraser,
 * account-deletion cleanup. test_h2_retention_* reproduces the review's probe P8 (email survived 400 days).
 */

use Wotoyi\Marketplace\Plugin;
use Wotoyi\Marketplace\Privacy\Privacy;
use Wotoyi\Marketplace\Support\Clock;
use Wotoyi\Marketplace\Support\LeadGuard;

final class PrivacyTest extends Wotoyi_Test_Case {

	private const DAY = 86400;

	private array $A;

	protected function setUp(): void {
		parent::setUp();
		$this->A = Fixtures::dealer( 'Dealer A' );
	}

	private function lead( array $over = array(), ?array $dealer = null ): string {
		$d = $dealer ?? $this->A;
		$r = Plugin::leads()->create( $over + array( 'dealer_id' => (int) $d['row']['id'], 'name' => 'Awa Diop', 'phone' => '+221770000000', 'email' => 'awa@example.com', 'message' => 'Disponible ?', 'consent' => true, 'ip_hash' => str_repeat( 'a', 64 ), 'user_id' => null ) );
		return $r['uuid'];
	}

	private function deliver( string $uuid, ?array $dealer = null ): void {
		$this->assertSame( 1, Plugin::leads()->acknowledge( (int) ( $dealer ?? $this->A )['row']['id'], array( $uuid ) ) );
	}

	private function row( string $uuid ): ?array {
		global $wpdb;
		return $wpdb->get_row( "SELECT * FROM wp_wotoyi_leads WHERE uuid = '" . $uuid . "'" );
	}

	/* ---------------------------- retention ---------------------------- */

	public function test_h2_retention_erases_personal_data_of_delivered_leads_after_the_delay(): void {
		$old = $this->lead();
		$this->deliver( $old );
		Clock::set( Clock::now() + 20 * self::DAY );
		$fresh = $this->lead( array( 'email' => 'fresh@example.com' ) );
		$this->deliver( $fresh );
		Clock::set( Clock::now() + 11 * self::DAY ); // old: delivered 31 days ago; fresh: 11 days ago.
		Plugin::prune();
		$o = $this->row( $old );
		foreach ( array( 'name', 'phone', 'email', 'message', 'ip_hash', 'user_id' ) as $col ) {
			$this->assertNull( $o[ $col ], "$col erased" );
		}
		$this->assertNotNull( $o['anonymized_at'] );
		$this->assertSame( 'delivered', $o['delivery_status'], 'statistics survive' );
		$this->assertSame( 'fresh@example.com', $this->row( $fresh )['email'], 'inside the retention window' );
	}

	public function test_h2_review_probe_email_does_not_survive_400_days(): void {
		$uuid = $this->lead();
		$this->deliver( $uuid );
		Clock::set( Clock::now() + 400 * self::DAY );
		Plugin::prune();
		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_wotoyi_leads WHERE email IS NOT NULL' ) );
	}

	public function test_h2_retention_deletes_leads_the_dealer_never_collected(): void {
		$stale = $this->lead();
		Clock::set( Clock::now() + 60 * self::DAY );
		$young = $this->lead( array( 'email' => 'young@example.com' ) );
		Clock::set( Clock::now() + 31 * self::DAY ); // stale: 91 days; young: 31 days.
		Plugin::prune();
		$this->assertNull( $this->row( $stale ), 'undelivered > 90 days deleted' );
		$this->assertNotNull( $this->row( $young ) );
	}

	public function test_h2_retention_deletes_leads_of_a_disconnected_dealer_after_the_grace_period(): void {
		$b  = Fixtures::dealer( 'Dealer B' );
		$la = $this->lead();
		$lb = $this->lead( array(), $b );
		Plugin::dealers()->disconnect( (int) $b['row']['id'] );
		Clock::set( Clock::now() + 10 * self::DAY );
		Plugin::prune();
		$this->assertNotNull( $this->row( $lb ), 'inside the grace period' );
		Clock::set( Clock::now() + 21 * self::DAY );
		Plugin::prune();
		$this->assertNull( $this->row( $lb ), 'disconnected dealer: deleted' );
		$this->assertNotNull( $this->row( $la ), 'other dealers untouched' );
	}

	public function test_h2_retention_periods_are_configurable_and_zero_disables(): void {
		add_filter( 'wotoyi_mp_setting_lead_retention_delivered_days', static fn() => 0 );
		add_filter( 'wotoyi_mp_setting_lead_retention_undelivered_days', static fn() => 0 );
		$u = $this->lead();
		$d = $this->lead();
		$this->deliver( $d );
		Clock::set( Clock::now() + 1000 * self::DAY );
		Plugin::prune();
		$this->assertNotNull( $this->row( $u ) );
		$this->assertSame( 'awa@example.com', $this->row( $d )['email'] );
		$this->assertSame( 30, \Wotoyi\Marketplace\Settings::defaults()['lead_retention_delivered_days'], 'documented defaults: 30 / 90 / 30' );
		$this->assertSame( 90, \Wotoyi\Marketplace\Settings::defaults()['lead_retention_undelivered_days'] );
		$this->assertSame( 30, \Wotoyi\Marketplace\Settings::defaults()['lead_retention_disconnected_days'] );
	}

	public function test_h2_retention_run_is_audited_with_counts_only(): void {
		$d = $this->lead();
		$this->deliver( $d );
		Clock::set( Clock::now() + 40 * self::DAY );
		Plugin::prune();
		$row = Plugin::audit()->recent( 1 )[0];
		$this->assertSame( 'leads.retention', $row['action'] );
		$this->assertSame( 1, $row['details']['leads_anonymized'] );
		$this->assertStringNotContains( 'awa@example.com', json_encode( $row ) );
	}

	/* ------------------------------ consent ------------------------------ */

	public function test_h2_consent_proof_is_stored_with_the_lead(): void {
		$u = Fixtures::publish( $this->A, 'v1' )['entity']['id'];
		$tok = LeadGuard::issue();
		Fixtures::tick( 5 );
		$res = rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', array( 'token' => $tok, 'vehicle' => $u, 'phone' => '+221770000000', 'consent' => true, 'consent_version' => 'cgu-2026-11' ) ) );
		$this->assertStatus( 201, $res );
		$row = $this->row( $res->get_data()['id'] );
		$this->assertSame( 1, (int) $row['consent'] );
		$this->assertSame( 'cgu-2026-11', $row['consent_version'] );
		$this->assertSame( 'transmit_to_dealer', $row['consent_purpose'] );
		$this->assertSame( Clock::mysql(), $row['consent_at'] );
		// Default version label when the client sends none; the dealer receives the proof.
		$tok = LeadGuard::issue();
		Fixtures::tick( 5 );
		rest_do_request( Fixtures::request( 'POST', '/wotoyi/v1/leads', array( 'token' => $tok, 'vehicle' => $u, 'phone' => '+221770000009', 'consent' => true ) ) );
		$pending = Plugin::leads()->pending_for_dealer( (int) $this->A['row']['id'], 10 );
		$this->assertSame( 'v1', $pending[1]['consent_version'] );
		$this->assertTrue( '' !== (string) $pending[1]['consent_at'] );
	}

	/* ------------------------- exporter / eraser ------------------------- */

	private function seed_subject(): void {
		$GLOBALS['wotoyi_users'] = array( array( 'ID' => 42, 'email' => 'awa@example.com' ), array( 'ID' => 43, 'email' => 'other@example.com' ) );
		$uuid_v = Fixtures::publish( $this->A, 'v1' )['entity']['id'];
		$vid    = (int) Plugin::vehicles()->find_by_source( (int) $this->A['row']['id'], 'v1' )['id'];
		Plugin::favourites()->add( 42, 'vehicle', $vid );
		Plugin::favourites()->add( 43, 'vehicle', $vid );
		Plugin::saved_searches()->create( 42, 'My SUVs', array( 'make' => 'toyota' ), true );
		Plugin::saved_searches()->create( 43, 'Theirs', array( 'make' => 'honda' ), false );
	}

	public function test_h2_privacy_exporter_and_eraser_are_registered_with_wordpress(): void {
		Plugin::boot();
		$exp = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$era = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( 'wotoyi-marketplace', $exp );
		$this->assertArrayHasKey( 'wotoyi-marketplace', $era );
		$this->assertTrue( is_callable( $exp['wotoyi-marketplace']['callback'] ) );
		$this->assertTrue( is_callable( $era['wotoyi-marketplace']['callback'] ) );
	}

	public function test_h2_exporter_returns_the_subjects_data_only(): void {
		$this->seed_subject();
		$mine   = $this->lead();
		$theirs = $this->lead( array( 'email' => 'other@example.com', 'name' => 'Not Awa' ) );
		$byuser = $this->lead( array( 'email' => null, 'name' => 'Awa by account', 'user_id' => 42 ) );
		$out    = Privacy::export( 'Awa@Example.com', 1 );
		$this->assertTrue( $out['done'] );
		$blob = json_encode( $out['data'] );
		$this->assertStringContains( 'Awa Diop', $blob );
		$this->assertStringContains( 'Awa by account', $blob );
		$this->assertStringContains( 'My SUVs', $blob );
		$this->assertStringContains( 'wotoyi-favourites', $blob );
		$this->assertStringNotContains( 'Not Awa', $blob );
		$this->assertStringNotContains( 'Theirs', $blob );
		$groups = array_unique( array_column( $out['data'], 'group_id' ) );
		sort( $groups );
		$this->assertSame( array( 'wotoyi-favourites', 'wotoyi-leads', 'wotoyi-saved-searches' ), $groups );
		foreach ( $out['data'] as $item ) {
			$this->assertArrayHasKey( 'item_id', $item );
			$this->assertTrue( is_array( $item['data'] ) && $item['data'] );
		}
	}

	public function test_h2_exporter_is_paginated(): void {
		for ( $i = 0; $i < 51; $i++ ) {
			$this->lead();
		}
		$p1 = Privacy::export( 'awa@example.com', 1 );
		$p2 = Privacy::export( 'awa@example.com', 2 );
		$this->assertFalse( $p1['done'] );
		$this->assertTrue( $p2['done'] );
		$this->assertCount( 50, $p1['data'] );
		$this->assertCount( 1, $p2['data'] );
	}

	public function test_h2_eraser_removes_everything_the_marketplace_holds_for_the_subject(): void {
		$this->seed_subject();
		$delivered = $this->lead();
		$this->deliver( $delivered );
		$pending   = $this->lead();
		$other     = $this->lead( array( 'email' => 'other@example.com' ) );
		$res       = Privacy::erase( 'awa@example.com', 1 );
		$this->assertTrue( $res['items_removed'] );
		$this->assertFalse( $res['items_retained'] );
		$this->assertTrue( $res['done'] );
		$this->assertStringContains( 'dealer', implode( ' ', $res['messages'] ), 'the data subject is told the dealer keeps a copy' );
		$this->assertNull( $this->row( $pending ), 'undelivered lead deleted' );
		$d = $this->row( $delivered );
		$this->assertNull( $d['email'] );
		$this->assertNull( $d['name'] );
		$this->assertNull( $d['message'] );
		$this->assertSame( 'other@example.com', $this->row( $other )['email'], 'someone else untouched' );
		$this->assertSame( 0, Plugin::favourites()->count( 42 ) );
		$this->assertCount( 0, Plugin::saved_searches()->list_for_user( 42 ) );
		$this->assertSame( 1, Plugin::favourites()->count( 43 ), 'other user favourites kept' );
		$this->assertCount( 1, Plugin::saved_searches()->list_for_user( 43 ) );
		$this->assertContains( 'privacy.erase', array_column( Plugin::audit()->recent( 5 ), 'action' ) );
		$this->assertStringNotContains( 'awa@example.com', json_encode( Plugin::audit()->recent( 5 ) ) );
		$again = Privacy::erase( 'awa@example.com', 1 );
		$this->assertFalse( $again['items_removed'], 'idempotent' );
	}

	public function test_h2_eraser_works_in_pages(): void {
		for ( $i = 0; $i < 120; $i++ ) {
			$this->lead();
		}
		$calls = 0;
		do {
			$res = Privacy::erase( 'awa@example.com', ++$calls );
		} while ( ! $res['done'] && $calls < 10 );
		$this->assertSame( 3, $calls );
		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_wotoyi_leads WHERE email IS NOT NULL' ) );
	}

	/* --------------------------- user deletion --------------------------- */

	public function test_h2_deleting_a_wordpress_user_removes_their_favourites_and_searches_and_unlinks_leads(): void {
		$this->seed_subject();
		$lead = $this->lead( array( 'user_id' => 42 ) );
		Plugin::boot();
		do_action( 'delete_user', 42 ); // What wp_delete_user() fires.
		$this->assertSame( 0, Plugin::favourites()->count( 42 ) );
		$this->assertCount( 0, Plugin::saved_searches()->list_for_user( 42 ) );
		$this->assertNull( $this->row( $lead )['user_id'], 'lead no longer points at the deleted account' );
		$this->assertSame( 1, Plugin::favourites()->count( 43 ), 'others untouched' );
	}

	public function test_h2_privacy_policy_text_states_the_retention_periods(): void {
		$GLOBALS['wotoyi_policy'] = '';
		eval( 'function wp_add_privacy_policy_content( $name, $text ) { $GLOBALS["wotoyi_policy"] = $text; }' ); // phpcs:ignore Squiz.PHP.Eval
		Privacy::add_policy_content();
		$this->assertStringContains( '30 days', $GLOBALS['wotoyi_policy'] );
		$this->assertStringContains( '90 days', $GLOBALS['wotoyi_policy'] );
	}
}
