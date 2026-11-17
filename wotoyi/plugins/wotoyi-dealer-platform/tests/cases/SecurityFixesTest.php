<?php
/**
 * Regression tests for SECURITY-REVIEW.md (H1, H2, M1, M2, M3, M4, M5). Each test name starts with the finding id.
 * They were written against the unfixed code first and fail there (see SECURITY-REVIEW.md, "Proof").
 */

use Wotoyi\DealerPlatform\Data\TenantContext;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Service\AuditLog;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Sync\Pairing;

final class SecurityFixesTest extends Wotoyi_Test_Case {

	private array $a;
	private array $b;
	private FakeMarketplace $mp;

	protected function setUp(): void {
		parent::setUp();
		$this->mp = new FakeMarketplace();
		$this->a  = Fixtures::tenant( 'Dealer A', 11 );
		$this->b  = Fixtures::tenant( 'Dealer B', 12 );
	}

	/* ------------------------------ helpers ------------------------------ */

	/** Every row of every table, as one string (whole-database search). */
	private function dump_db(): string {
		$out = '';
		foreach ( Fixtures::raw( "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'" ) as $t ) {
			$out .= json_encode( Fixtures::raw( 'SELECT * FROM ' . $t['name'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
		}
		return $out;
	}

	private function last_token(): string {
		$mail = end( $GLOBALS['wotoyi_mail_log'] );
		$this->assertTrue( is_array( $mail ), 'an invitation mail was sent' );
		$this->assertTrue( 1 === preg_match( '/#token=([0-9a-f]{64})/', $mail['message'], $m ), 'mail carries a token' );
		return $m[1];
	}

	private function audit( string $action, ?array $tenant = null ): array {
		return Fixtures::in( $tenant ?? $this->a, fn() => AuditLog::list( array( 'action' => $action ) )['items'] );
	}

	private function post( string $route, array $body ): WP_REST_Response {
		return Fixtures::call( 'POST', $route, $body );
	}

	/* ------------------------------ H1: invitations ------------------------------ */

	public function test_h1_owner_cannot_attach_another_tenants_user_or_lock_them_out(): void {
		Fixtures::login( 12 );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/me' ), 'victim works before' );
		Fixtures::login( 11 );
		$this->assertStatus( 202, $this->post( '/members', array( 'email' => 'user12@example.com', 'role' => 'sales' ) ) );
		$this->assertStatus( 422, $this->post( '/members', array( 'user_id' => 12, 'role' => 'sales' ) ), 'user_id is not accepted from a dealer owner' );
		$this->assertSame( 0, Fixtures::count( 'members', 'user_id = 12 AND tenant_id = ' . (int) $this->a['id'] ), 'no membership without acceptance' );
		Fixtures::login( 12 );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/me' ), 'victim is not locked out (still a single membership, no tenant header needed)' );
	}

	public function test_h1_reply_is_identical_whether_or_not_the_email_exists_or_is_a_member(): void {
		Fixtures::login( 11 );
		$existing = $this->post( '/members', array( 'email' => 'user12@example.com', 'role' => 'sales' ) );
		$unknown  = $this->post( '/members', array( 'email' => 'nobody@example.com', 'role' => 'sales' ) );
		$member   = $this->post( '/members', array( 'email' => 'user11@example.com', 'role' => 'sales' ) ); // The owner themself: already a member.
		foreach ( array( $existing, $unknown, $member ) as $r ) {
			$this->assertStatus( 202, $r );
		}
		$this->assertSame( $existing->get_data(), $unknown->get_data() );
		$this->assertSame( $existing->get_data(), $member->get_data() );
		$this->assertCount( 2, $GLOBALS['wotoyi_mail_log'], 'no mail for someone who is already a member' );
		$this->assertStatus( 422, $this->post( '/members', array( 'email' => 'not-an-email', 'role' => 'sales' ) ) );
		$this->assertStatus( 422, $this->post( '/members', array( 'email' => 'x@example.com', 'role' => 'god' ) ) );
	}

	public function test_h1_invitee_must_accept_with_the_invited_email_and_the_token_is_single_use(): void {
		Fixtures::user( 40, 'ana@example.com' );
		Fixtures::user( 41, 'bob@example.com' );
		Fixtures::login( 11 );
		$res = $this->post( '/members', array( 'email' => 'Ana@Example.com', 'role' => 'manager', 'display_name' => 'Ana' ) );
		$token = $this->last_token();
		$this->assertStringNotContains( $token, json_encode( $res->get_data() ), 'the token is only in the email' );
		$this->assertStringNotContains( $token, $this->dump_db(), 'only the hash is stored' );
		$this->assertCount( 1, Fixtures::call( 'GET', '/members/invitations' )->get_data()['items'] );

		Fixtures::login( 0 );
		$this->assertStatus( 401, $this->post( '/members/accept', array( 'token' => $token ) ) );
		Fixtures::login( 41 ); // Wrong person.
		$this->assertStatus( 422, $this->post( '/members/accept', array( 'token' => $token ) ) );
		$this->assertStatus( 422, $this->post( '/members/accept', array( 'token' => str_repeat( 'a', 64 ) ) ), 'unknown token: same answer' );
		Fixtures::login( 40 );
		$this->assertStatus( 403, Fixtures::call( 'POST', '/members/accept', array( 'token' => $token ), array(), array(), false ), 'nonce required' );
		$ok = $this->post( '/members/accept', array( 'token' => $token ) );
		$this->assertStatus( 200, $ok );
		$this->assertSame( 'manager', $ok->get_data()['member']['role'] );
		$this->assertSame( 1, Fixtures::count( 'members', 'user_id = 40 AND status = \'active\'' ) );
		$this->assertStatus( 422, $this->post( '/members/accept', array( 'token' => $token ) ), 'single use' );
		$this->assertSame( 'manager', Fixtures::call( 'GET', '/me' )->get_data()['member']['role'], 'the new member can work' );
		$this->assertContains( 40, array_column( $this->audit( 'members.accept' ), 'user_id' ), 'accepting is audited with the invitee as actor' );
	}

	public function test_h1_expired_and_revoked_invitations_cannot_be_accepted(): void {
		Fixtures::user( 40, 'ana@example.com' );
		Fixtures::login( 11 );
		$this->post( '/members', array( 'email' => 'ana@example.com', 'role' => 'sales' ) );
		$t1 = $this->last_token();
		$id = Fixtures::call( 'GET', '/members/invitations' )->get_data()['items'][0]['id'];
		$this->assertStatus( 200, Fixtures::call( 'DELETE', '/members/invitations/' . $id ) );
		Fixtures::login( 40 );
		$this->assertStatus( 422, $this->post( '/members/accept', array( 'token' => $t1 ) ), 'revoked' );
		Fixtures::login( 11 );
		$this->post( '/members', array( 'email' => 'ana@example.com', 'role' => 'sales' ) );
		$t2 = $this->last_token();
		Fixtures::tick( 8 * 86400 );
		Fixtures::login( 40 );
		$this->assertStatus( 422, $this->post( '/members/accept', array( 'token' => $t2 ) ), 'expired after 7 days' );
		$this->assertSame( 0, Fixtures::count( 'members', 'user_id = 40' ) );
	}

	public function test_h1_invitations_are_rate_limited_per_tenant_and_per_address(): void {
		Fixtures::login( 11 );
		for ( $i = 1; $i <= 10; $i++ ) {
			$this->assertStatus( 202, $this->post( '/members', array( 'email' => "person{$i}@example.com", 'role' => 'sales' ) ) );
		}
		$this->assertStatus( 429, $this->post( '/members', array( 'email' => 'person11@example.com', 'role' => 'sales' ) ), '11th invitation within the hour' );
		Fixtures::login( 12 );
		$GLOBALS['wotoyi_mail_log'] = array();
		for ( $i = 1; $i <= 5; $i++ ) { // The same address, from another tenant.
			$this->assertStatus( 202, $this->post( '/members', array( 'email' => 'person1@example.com', 'role' => 'sales' ) ), 'reply never changes' );
		}
		$this->assertTrue( count( $GLOBALS['wotoyi_mail_log'] ) <= 3, 'at most 3 mails a day to one address, got ' . count( $GLOBALS['wotoyi_mail_log'] ) );
	}

	public function test_h1_invitation_user_cannot_be_forged_by_manager_or_sales(): void {
		Fixtures::member( $this->a, 20, 'manager' );
		Fixtures::member( $this->a, 21, 'sales' );
		foreach ( array( 20, 21 ) as $u ) {
			Fixtures::login( $u );
			$this->assertStatus( 403, $this->post( '/members', array( 'email' => 'x@example.com', 'role' => 'owner' ) ) );
			$this->assertStatus( 403, Fixtures::call( 'GET', '/members/invitations' ) );
		}
	}

	public function test_h1_direct_attachment_exists_only_on_the_platform_route_and_is_audited_with_its_actor(): void {
		Fixtures::user( 50 );
		Fixtures::login( 11 );
		$this->assertStatus( 403, $this->post( '/platform/members', array( 'tenant_id' => $this->a['uuid'], 'user_id' => 50, 'role' => 'sales' ) ) );
		Fixtures::platform_admin( 1 );
		$this->assertStatus( 201, $this->post( '/platform/members', array( 'tenant_id' => $this->a['uuid'], 'user_id' => 50, 'role' => 'sales' ) ) );
		$row = $this->audit( 'members.add' )[0];
		$this->assertSame( 1, $row['user_id'] );
		$this->assertSame( 'platform', $row['actor_type'] );
	}

	/* ------------------------------ H2: erasure ------------------------------ */

	public function test_h2_erasure_leaves_no_trace_of_the_person_in_the_whole_database(): void {
		Fixtures::login( 11 );
		$veh  = Fixtures::call( 'POST', '/vehicles', Fixtures::vehicle() )->get_data()['id'];
		$veh2 = Fixtures::call( 'POST', '/vehicles', Fixtures::vehicle( array( 'vin' => 'JTMBFREV5LD000002' ) ) )->get_data()['id'];
		$cust = $this->post( '/customers', array( 'name' => 'Moussa Diop', 'phone' => '+221771234567', 'whatsapp' => '+221 77 123 45 67', 'email' => 'moussa.diop@example.com', 'address' => '12 Rue Carnot', 'notes' => 'Prefers Toyota Corolla', 'consent' => true ) )->get_data()['id'];
		$lead = $this->post( '/leads', array( 'customer_uuid' => $cust, 'name' => 'Moussa Diop', 'phone' => '+221771234567', 'message' => 'Wants a RAV4 for the Diop family', 'source' => 'walk_in' ) )->get_data()['id'];
		Fixtures::call( 'POST', "/leads/{$lead}/stage", array( 'stage' => 'lost', 'lost_reason' => 'Moussa Diop found a cheaper Hilux' ) );
		$unlinked = $this->post( '/leads', array( 'name' => 'M. Diop', 'phone' => '+221 77 123 45 67', 'email' => 'MOUSSA.DIOP@example.com', 'message' => 'Call me back after Tabaski', 'source' => 'marketplace' ) )->get_data()['id'];
		$text_only = $this->post( '/leads', array( 'name' => 'Fatou Sow', 'phone' => '+221 70 555 55 55', 'message' => 'Referred by Moussa Diop at the garage' ) )->get_data()['id'];
		$act = fn( string $type, string $id, string $body ) => $this->post( '/activities', array( 'entity_type' => $type, 'entity_uuid' => $id, 'body' => $body, 'type' => 'note' ) );
		$act( 'customer', $cust, 'Spoke on the private line about the loan' );
		$act( 'lead', $lead, 'Called Moussa wants credit' );
		$act( 'lead', $unlinked, 'Asked about the Hilux price' );
		$this->post( '/tasks', array( 'title' => 'Call Moussa Diop on +221 77 123 45 67', 'related_type' => 'customer', 'related_uuid' => $cust ) );
		$this->post( '/tasks', array( 'title' => 'Send brochure', 'description' => 'to 771234567 please' ) );
		$this->post( '/rentals', array( 'vehicle_uuid' => $veh2, 'customer_uuid' => $cust, 'start_date' => '2027-02-01', 'end_date' => '2027-02-03', 'notes' => 'ID card copied, licence number 99-123' ) );
		$this->post( '/deals', array( 'vehicle_uuid' => $veh, 'customer_uuid' => $cust, 'amount' => 9000000, 'notes' => 'Pays by Wave in two parts' ) );

		// A bystander who must NOT be touched.
		$other = $this->post( '/customers', array( 'name' => 'Awa Ndiaye', 'phone' => '+221780000001', 'notes' => 'Bystander VIP note' ) )->get_data()['id'];
		$this->post( '/leads', array( 'customer_uuid' => $other, 'name' => 'Awa Ndiaye', 'phone' => '+221780000001', 'message' => 'Bystander lead message' ) );
		$act( 'customer', $other, 'Bystander timeline entry' );
		$this->post( '/tasks', array( 'title' => 'Bystander task', 'related_type' => 'customer', 'related_uuid' => $other ) );

		$needles = array( 'Moussa', 'Diop', 'moussa.diop', '771234567', '77 123 45 67', '12 Rue Carnot', 'Prefers Toyota', 'Call me back after Tabaski', 'Referred by', 'private line', 'wants credit', 'Hilux', 'Send brochure', 'ID card copied', 'licence number', 'Pays by Wave', 'M. Diop', 'Diop family' );
		$before = $this->dump_db();
		foreach ( $needles as $n ) {
			$this->assertStringContains( $n, $before, "seed sanity: {$n}" );
		}

		$res = $this->post( "/customers/{$cust}/erase", array() );
		$this->assertStatus( 200, $res );
		$data = $res->get_data();
		$this->assertTrue( $data['counts']['leads'] >= 3 && $data['counts']['tasks'] >= 2 && $data['counts']['rentals'] >= 1 && $data['counts']['deals'] >= 1 && $data['counts']['activities'] >= 3, 'per-table counts: ' . json_encode( $data['counts'] ) );
		$this->assertSame( 'not_propagated', $data['follow_up']['marketplace'], 'marketplace-side erasure is reported as a manual step' );
		$this->assertSame( $data['counts']['leads'], $data['leads'], 'first-release flat keys kept' );

		$after = $this->dump_db();
		foreach ( $needles as $n ) {
			$this->assertStringNotContains( $n, $after, "'{$n}' must be gone from every table (whole-database search)" );
		}
		foreach ( array( 'Bystander VIP note', 'Bystander lead message', 'Bystander timeline entry', 'Bystander task', 'Awa Ndiaye' ) as $keep ) {
			$this->assertStringContains( $keep, $after, "unrelated data stays: {$keep}" );
		}
		$this->assertStatus( 404, Fixtures::call( 'GET', "/customers/{$cust}" ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', "/leads/{$lead}" ), 'lead row kept for statistics' );
		$entry = $this->audit( 'customers.erase' )[0];
		$this->assertSame( 11, $entry['user_id'] );
		$this->assertTrue( $entry['meta']['counts']['leads'] >= 3, 'counts are in the audit log' );
	}

	public function test_h2_export_returns_everything_held_about_the_person_and_is_audited(): void {
		Fixtures::member( $this->a, 21, 'sales' );
		Fixtures::login( 11 );
		$cust = $this->post( '/customers', array( 'name' => 'Moussa Diop', 'phone' => '+221771234567' ) )->get_data()['id'];
		$this->post( '/leads', array( 'name' => 'M. Diop', 'phone' => '+221 77 123 45 67', 'message' => 'Unlinked but same phone' ) );
		$this->post( '/tasks', array( 'title' => 'Call Moussa Diop', 'related_type' => 'customer', 'related_uuid' => $cust ) );
		$out = Fixtures::call( 'GET', "/customers/{$cust}/export" );
		$this->assertStatus( 200, $out );
		$d = $out->get_data();
		$this->assertSame( 'Moussa Diop', $d['customer']['name'] );
		$this->assertCount( 1, $d['leads'] );
		$this->assertCount( 1, $d['tasks'] );
		$e = $this->audit( 'customers.export' )[0];
		$this->assertSame( 1, $e['meta']['counts']['leads'] );
		$this->assertStringNotContains( 'Moussa', json_encode( $e ), 'the audit entry holds counts, not personal data' );
		Fixtures::login( 21 );
		$this->assertStatus( 403, Fixtures::call( 'GET', "/customers/{$cust}/export" ), 'sales cannot export a subject file' );
	}

	/* ------------------------------ M1: pairing ------------------------------ */

	public function test_m1_only_the_owner_can_pair_or_disconnect_and_a_manager_keeps_run_and_requeue(): void {
		Fixtures::member( $this->a, 20, 'manager' );
		Fixtures::login( 20 );
		$body = array( 'code' => 'WTY-ABCD-EFGH-JKLM', 'base_url' => 'https://attacker.example.net' );
		$this->assertStatus( 403, $this->post( '/sync/pair', $body ) );
		$this->assertStatus( 403, $this->post( '/sync/pair-local', array() ) );
		$this->assertStatus( 403, $this->post( '/sync/disconnect', array() ) );
		$this->assertSame( 0, count( $this->mp->requests ), 'a manager could not make the server contact any host' );
		$this->assertStatus( 200, $this->post( '/sync/run', array() ) );
		$this->assertStatus( 200, $this->post( '/sync/requeue', array() ) );
		Fixtures::login( 11 );
		$this->assertStatus( 201, $this->post( '/sync/pair', array( 'code' => 'WTY-ABCD-EFGH-JKLM', 'base_url' => 'https://mp.example.com' ) ), 'the owner can' );
		$this->assertStatus( 200, $this->post( '/sync/disconnect', array() ) );
	}

	public function test_m1_internal_address_forms_are_rejected_and_public_ones_accepted(): void {
		$bad = array(
			'https://2130706433', 'https://0x7f000001', 'https://127.1', 'https://017700000001', 'https://0177.0.0.1', 'https://127.0.0.1', 'https://10.1.2.3',
			'https://169.254.169.254', 'https://100.64.0.1', 'https://192.168.0.1', 'https://0.0.0.0', 'https://[::1]', 'https://[::ffff:127.0.0.1]',
			'https://[::ffff:7f00:1]', 'https://[fe80::1]', 'https://[fd00::1]', 'https://[64:ff9b::7f00:1]', 'https://[2002:7f00:1::1]',
			'https://localhost', 'https://localhost.', 'https://foo.localhost', 'https://localtest.me', 'https://a.b.localtest.me', 'https://127.0.0.1.nip.io',
			'https://metadata.google.internal', 'https://printer.local', 'https://intranet', 'https://example.com:8443', 'https://example.com:22',
			'https://user@example.com', 'https://example.com/?x=1', 'https://example.com/a/../b', 'https://exa mple.com', "https://example.com\\@evil.com", 'http://example.com', 'ftp://example.com',
		);
		foreach ( $bad as $u ) {
			$this->assertThrows( fn() => Pairing::validate_base_url( $u ), '', $u );
		}
		// A public-looking name that resolves to a private address (DNS rebinding / split horizon) is refused.
		Pairing::set_resolver( static fn( string $h ): array => array( '93.184.216.34', '10.0.0.7' ) );
		$this->assertThrows( fn() => Pairing::validate_base_url( 'https://rebind.example.com' ), '', 'rebind' );
		Pairing::set_resolver( static fn( string $h ): array => array() );
		$this->assertThrows( fn() => Pairing::validate_base_url( 'https://nxdomain.example.com' ), '', 'unresolvable' );
		Pairing::set_resolver( static fn( string $h ): array => array( '93.184.216.34' ) );
		$this->assertSame( 'https://mp.example.com', Pairing::validate_base_url( 'https://MP.example.com/' ) );
		$this->assertSame( 'https://mp.example.com/wp', Pairing::validate_base_url( 'https://mp.example.com:443/wp/' ) );
		$this->assertSame( 'https://93.184.216.34', Pairing::validate_base_url( 'https://93.184.216.34' ) );
		$this->assertSame( 'https://[2606:2800:220:1::1]', Pairing::validate_base_url( 'https://[2606:2800:220:1::1]' ) );
	}

	public function test_m1_a_pinned_marketplace_url_is_the_only_target(): void {
		$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/../scripts/pinned_url.php' ) . ' 2>&1' );
		$r   = json_decode( (string) $out, true );
		$this->assertTrue( is_array( $r ), 'script output: ' . substr( (string) $out, 0, 300 ) );
		$this->assertSame( 'https://wotoyi.example.com', $r['empty_uses_pinned'] );
		$this->assertSame( 'https://wotoyi.example.com', $r['same_origin'] );
		foreach ( $r['rejected'] as $url => $rejected ) {
			$this->assertTrue( $rejected, "{$url} must be refused when the origin is pinned" );
		}
	}

	public function test_m1_every_remote_failure_gives_the_same_answer_and_no_upstream_status(): void {
		Fixtures::login( 11 );
		$answers = array();
		foreach ( array( array( 403, array( 'message' => 'bad code' ) ), array( 429, array() ), array( 500, array( 'message' => 'internal host db-7 down' ) ), array( 404, array() ), array( 201, array( 'key_id' => 'nope' ) ) ) as [ $status, $body ] ) {
			$this->mp->respond_with( $status, $body );
			$r = $this->post( '/sync/pair', array( 'code' => 'WTY-ZZZZ-ZZZZ-ZZZZ', 'base_url' => 'https://mp.example.com' ) );
			$answers[] = array( $r->get_status(), $r->get_data()['code'], $r->get_data()['message'] );
			$this->assertStringNotContains( (string) $status, (string) $r->get_data()['message'] );
			$this->assertStringNotContains( 'db-7', json_encode( $r->get_data() ) );
		}
		Fixtures::tick( 1000 ); // Past the local 5-per-15-minutes pairing throttle.
		$this->mp->script[] = new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect to 10.0.0.9 port 443' );
		$r = $this->post( '/sync/pair', array( 'code' => 'WTY-ZZZZ-ZZZZ-ZZZZ', 'base_url' => 'https://mp.example.com' ) );
		$answers[] = array( $r->get_status(), $r->get_data()['code'], $r->get_data()['message'] );
		$this->assertStringNotContains( '10.0.0.9', json_encode( $r->get_data() ) );
		$this->assertCount( 1, array_unique( array_map( 'json_encode', $answers ) ), 'no oracle: ' . json_encode( $answers ) );
	}

	/* ------------------------------ M2: timeline visibility ------------------------------ */

	public function test_m2_timeline_entries_follow_the_read_capability_of_the_record(): void {
		Fixtures::member( $this->a, 20, 'mechanic' );
		Fixtures::member( $this->a, 21, 'accountant' );
		Fixtures::member( $this->a, 22, 'sales' );
		Fixtures::login( 11 );
		$veh  = Fixtures::call( 'POST', '/vehicles', Fixtures::vehicle() )->get_data()['id'];
		$lead = $this->post( '/leads', array( 'name' => 'Awa', 'phone' => '+221770000009' ) )->get_data()['id'];
		$cust = $this->post( '/customers', array( 'name' => 'Awa', 'phone' => '+221770000009' ) )->get_data()['id'];
		$this->post( '/activities', array( 'entity_type' => 'lead', 'entity_uuid' => $lead, 'body' => 'Called Awa wants credit' ) );
		$this->post( '/activities', array( 'entity_type' => 'vehicle', 'entity_uuid' => $veh, 'body' => 'Oil leak fixed' ) );

		Fixtures::login( 20 ); // Mechanic: no leads.read.
		$this->assertStatus( 403, Fixtures::call( 'GET', '/leads' ) );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/activities', null, array( 'entity_type' => 'lead' ) ), 'asking for lead notes is refused' );
		$this->assertStatus( 403, Fixtures::call( 'GET', '/activities', null, array( 'entity_type' => 'vehicle,lead' ) ) );
		$all = json_encode( Fixtures::call( 'GET', '/activities' )->get_data() );
		$this->assertStringNotContains( 'wants credit', $all, 'no type given: lead notes are filtered out' );
		$this->assertStringContains( 'Oil leak fixed', $all );
		$by_uuid = Fixtures::call( 'GET', '/activities', null, array( 'entity_uuid' => $lead ) )->get_data();
		$this->assertSame( 0, $by_uuid['total'] );
		$this->assertStatus( 403, $this->post( '/activities', array( 'entity_type' => 'lead', 'entity_uuid' => $lead, 'body' => 'probe' ) ), 'cannot annotate a lead either' );
		$this->assertStatus( 201, $this->post( '/activities', array( 'entity_type' => 'vehicle', 'entity_uuid' => $veh, 'body' => 'Tyres checked' ) ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', "/customers/{$cust}/timeline" ), 'mechanic may read customers' );

		Fixtures::login( 21 ); // Accountant: activities.read but no leads.read.
		$this->assertStatus( 403, Fixtures::call( 'GET', '/activities', null, array( 'entity_type' => 'lead' ) ) );
		$this->assertStringNotContains( 'wants credit', json_encode( Fixtures::call( 'GET', '/activities' )->get_data() ) );

		Fixtures::login( 22 ); // Sales may read leads.
		$this->assertStringContains( 'wants credit', json_encode( Fixtures::call( 'GET', '/activities', null, array( 'entity_type' => 'lead' ) )->get_data() ) );
	}

	/* ------------------------------ M3: audit log ------------------------------ */

	public function test_m3_audit_log_is_append_only_and_tamper_evident(): void {
		Fixtures::login( 11 );
		Fixtures::call( 'PATCH', '/tenant', array( 'city' => 'Thies' ) );
		Fixtures::call( 'PATCH', '/tenant', array( 'city' => 'Saint-Louis' ) );
		Fixtures::in( $this->a, function () {
			$this->assertThrows( fn() => TenantDb::update( 'audit_log', array( 'action' => 'tenant.update' ), array( 'action' => 'nothing' ) ), 'append_only' );
			$this->assertThrows( fn() => TenantDb::delete( 'audit_log', array( 'action' => 'tenant.update' ) ), 'delete_not_allowed' );
			$v = AuditLog::verify();
			$this->assertTrue( $v['ok'] && $v['checked'] >= 3, 'chain verifies: ' . json_encode( $v ) );
		} );
		$ids = array_column( Fixtures::raw( 'SELECT id FROM wp_wotoyi_dp_audit_log WHERE tenant_id = ' . (int) $this->a['id'] . ' ORDER BY id' ), 'id' );
		// Edit one row directly in the database.
		$GLOBALS['wpdb']->query( "UPDATE wp_wotoyi_dp_audit_log SET user_id = 99 WHERE id = {$ids[1]}" );
		$v = Fixtures::in( $this->a, fn() => AuditLog::verify() );
		$this->assertFalse( $v['ok'] );
		$this->assertSame( (int) $ids[1], $v['broken_at'] );
		// Restore, then delete a row from the middle.
		$GLOBALS['wpdb']->query( "UPDATE wp_wotoyi_dp_audit_log SET user_id = 11 WHERE id = {$ids[1]}" );
		$this->assertTrue( Fixtures::in( $this->a, fn() => AuditLog::verify() )['ok'] );
		$GLOBALS['wpdb']->query( "DELETE FROM wp_wotoyi_dp_audit_log WHERE id = {$ids[1]}" );
		$this->assertFalse( Fixtures::in( $this->a, fn() => AuditLog::verify() )['ok'], 'a deleted row breaks the chain' );
		// Nulling the hash of a row inside the chain is also detected.
		$GLOBALS['wpdb']->query( "UPDATE wp_wotoyi_dp_audit_log SET hash = NULL WHERE id = {$ids[2]}" );
		$this->assertFalse( Fixtures::in( $this->a, fn() => AuditLog::verify() )['ok'] );
		// The other tenant's chain is independent and intact.
		$this->assertTrue( Fixtures::in( $this->b, fn() => AuditLog::verify() )['ok'] );
	}

	public function test_m3_role_changes_record_the_old_and_the_new_value_and_every_entry_has_an_actor(): void {
		$m = Fixtures::member( $this->a, 21, 'sales' );
		Fixtures::login( 11 );
		$this->assertStatus( 200, Fixtures::call( 'PATCH', '/members/' . $m['id'], array( 'role' => 'manager' ) ) );
		$this->assertStatus( 200, Fixtures::call( 'DELETE', '/members/' . $m['id'] ) );
		$rows = $this->audit( 'members.update' );
		$this->assertCount( 2, $rows );
		$suspend = $rows[0]['meta'];
		$promote = $rows[1]['meta'];
		$this->assertSame( array( 'role' => 'sales' ), $promote['from'] );
		$this->assertSame( array( 'role' => 'manager' ), $promote['to'] );
		$this->assertSame( array( 'status' => 'active' ), $suspend['from'] );
		$this->assertSame( array( 'status' => 'suspended' ), $suspend['to'] );
		$this->assertSame( 21, $promote['target_user'] );
		// Every entry of every tenant names an actor: a user, or an explicit `system` marker.
		foreach ( Fixtures::raw( 'SELECT user_id, actor_type FROM wp_wotoyi_dp_audit_log' ) as $r ) {
			$this->assertTrue( in_array( $r['actor_type'], array( 'user', 'platform', 'system' ), true ) );
			$this->assertTrue( 'system' === $r['actor_type'] ? '0' === (string) $r['user_id'] : (int) $r['user_id'] > 0, 'actor present: ' . json_encode( $r ) );
		}
	}

	public function test_m3_tenant_creation_records_the_platform_administrator(): void {
		Fixtures::platform_admin( 1 );
		Fixtures::user( 77 );
		$res = $this->post( '/platform/tenants', array( 'name' => 'New Dealer', 'owner_user_id' => 77 ) );
		$this->assertStatus( 201, $res );
		$tenant = (array) \Wotoyi\DealerPlatform\Data\Directory::find_by_uuid( $res->get_data()['id'] );
		$row    = $this->audit( 'tenant.create', $tenant )[0];
		$this->assertSame( 1, $row['user_id'], 'the administrator who created the tenant is recorded' );
		$this->assertSame( 'platform', $row['actor_type'] );
	}

	public function test_m3_personal_data_reads_are_audited_without_the_data(): void {
		Fixtures::login( 11 );
		$this->post( '/customers', array( 'name' => 'Secret Name', 'phone' => '+221771111111' ) );
		Fixtures::call( 'GET', '/customers', null, array( 'q' => 'Secret', 'per_page' => '5' ) );
		Fixtures::call( 'GET', '/leads' );
		Fixtures::call( 'GET', '/employees' );
		$e = $this->audit( 'customers.list' )[0];
		$this->assertSame( 11, $e['user_id'] );
		$this->assertSame( 1, $e['meta']['count'] );
		$this->assertSame( array( 'q' ), $e['meta']['filters'], 'filter names, not values' );
		$this->assertStringNotContains( 'Secret', json_encode( $e ) );
		$this->assertCount( 1, $this->audit( 'leads.list' ) );
		$this->assertCount( 1, $this->audit( 'employees.list' ) );
	}

	/* ------------------------------ M4: abuse throttling ------------------------------ */

	public function test_m4_personal_data_reads_have_a_per_user_row_budget_with_an_alert(): void {
		Fixtures::member( $this->a, 20, 'manager' );
		add_filter( 'wotoyi_dp_pii_rows_per_minute', static fn() => 20 );
		Fixtures::in( $this->a, function () {
			for ( $i = 0; $i < 30; $i++ ) {
				Plugin::customers()->create( array( 'name' => "Customer {$i}", 'phone' => '+22177000' . str_pad( (string) $i, 4, '0', STR_PAD_LEFT ) ) );
			}
		} );
		Fixtures::login( 11 );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array( 'per_page' => '10' ) ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array( 'per_page' => '10' ) ) );
		$this->assertStatus( 429, Fixtures::call( 'GET', '/customers', null, array( 'per_page' => '10' ) ), 'budget of 20 rows a minute used up' );
		$this->assertStatus( 429, Fixtures::call( 'GET', '/leads' ), 'the budget covers personal-data lists together' );
		$this->assertCount( 1, $this->audit( 'pii.rate_limited' ), 'one alert per window, not one per refused call' );
		Fixtures::login( 20 );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array( 'per_page' => '10' ) ), 'another user has their own budget' );
		Fixtures::tick( 61 );
		Fixtures::login( 11 );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/customers', null, array( 'per_page' => '10' ) ), 'window reset' );
	}

	/* ------------------------------ M5: the quality gate ------------------------------ */

	public function test_m5_check_scripts_cannot_hide_a_failing_suite(): void {
		$root = dirname( __DIR__, 2 );
		foreach ( array( $root . '/bin/check.sh', dirname( $root ) . '/wotoyi-marketplace/bin/check.sh' ) as $f ) {
			if ( ! is_file( $f ) ) {
				continue;
			}
			$src = (string) file_get_contents( $f );
			$this->assertTrue( 1 === preg_match( '/^set\s+-[a-zA-Z]*o?\s*[^\n]*pipefail/m', $src ), "{$f} must set pipefail" );
		}
		// Behavioural proof of the mechanism: a failing command piped through tail must fail the pipeline.
		$cmd = 'bash -c ' . escapeshellarg( 'set -o pipefail; ( echo "1 failed"; exit 1 ) | tail -n 12; exit ${PIPESTATUS[0]:-$?}' );
		exec( $cmd, $o, $code );
		$this->assertTrue( 0 !== $code, 'pipeline status must be non-zero, got ' . $code );
	}
}
