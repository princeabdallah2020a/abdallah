<?php
use Wotoyi\DealerPlatform\Data\Directory;
use Wotoyi\DealerPlatform\Data\TenantDb;
use Wotoyi\DealerPlatform\Plugin;
use Wotoyi\DealerPlatform\Support\ApiException;
use Wotoyi\DealerPlatform\Support\Clock;
use Wotoyi\DealerPlatform\Support\Crypto;
use Wotoyi\DealerPlatform\Sync\HttpTransport;
use Wotoyi\DealerPlatform\Sync\LeadPuller;
use Wotoyi\DealerPlatform\Sync\Outbox;
use Wotoyi\DealerPlatform\Sync\Pairing;
use Wotoyi\DealerPlatform\Sync\Reconciler;
use Wotoyi\DealerPlatform\Sync\SyncState;
use Wotoyi\DealerPlatform\Sync\Worker;

final class SyncTest extends Wotoyi_Test_Case {

	private array $t;
	private FakeMarketplace $mp;

	protected function setUp(): void {
		parent::setUp();
		$this->t  = Fixtures::tenant( 'Dealer A', 11 );
		$this->mp = new FakeMarketplace();
	}

	private function in( callable $fn ) {
		return Fixtures::in( $this->t, $fn );
	}

	private function pair(): array {
		return $this->in( fn() => Pairing::pair_http( 'wty-abcd-efgh-jklm', 'https://mp.example.com/' ) );
	}

	private function publish( int $n = 1, array $over = array() ): array {
		return $this->in( function () use ( $n, $over ) {
			$ids = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$ids[] = Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published', 'model' => 'M' . $i ) + $over ) )['id'];
			}
			return $ids;
		} );
	}

	private function outbox( string $where = '' ): array {
		return $this->in( fn() => TenantDb::select( 'outbox', $where ? array( 'status' => $where ) : array(), array( 'order' => array( array( 'id', 'asc' ) ) ) ) );
	}

	private function run_worker(): array {
		return $this->in( fn() => Worker::run_for_current() );
	}

	/* ---------------------------------------------------------------- pairing */

	public function test_pairing_stores_the_secret_encrypted_and_never_returns_it(): void {
		$state = $this->pair();
		$this->assertSame( 'paired', $state['status'] );
		$this->assertSame( 'http', $state['transport'] );
		$this->assertSame( 'https://mp.example.com', $state['base_url'] );
		$this->assertSame( $this->mp->key_id, $state['key_id'] );
		$this->assertStringNotContains( $this->mp->secret, json_encode( $state ) );
		$this->assertArrayNotHasKey( 'secret_enc', $state );
		$row = Fixtures::raw( 'SELECT * FROM wp_wotoyi_dp_sync_state' )[0];
		$this->assertStringContains( 'v1:', $row['secret_enc'] );
		$this->assertStringNotContains( $this->mp->secret, json_encode( $row ) );
		$this->assertSame( $this->mp->secret, $this->in( fn() => SyncState::secret() ), 'round trip' );
		// The pairing request: unsigned, only the code and the tenant identity.
		$req = $this->mp->requests_to( '/wotoyi/v1/sync/pair' )[0];
		$this->assertSame( 'https://mp.example.com/wp-json/wotoyi/v1/sync/pair', $req['url'] );
		$body = json_decode( $req['body'], true );
		$this->assertSame( 'WTY-ABCD-EFGH-JKLM', $body['code'] );
		$this->assertSame( $this->t['uuid'], $body['source_dealer_uuid'] );
		$this->assertArrayNotHasKey( 'X-Wotoyi-Signature', $req['headers'] );
		$this->assertSame( 0, $req['args']['redirection'] );
		$this->assertTrue( $req['args']['sslverify'] );
	}

	public function test_pairing_refuses_bad_codes_bad_urls_and_wrong_codes_without_touching_state(): void {
		foreach ( array( 'garbage', '', 'ABC-1234', 'XXX-ABCD-EFGH-JKLM' ) as $code ) {
			$this->assertThrows( fn() => $this->in( fn() => Pairing::pair_http( $code, 'https://mp.example.com' ) ), 'WTY-XXXX' );
		}
		foreach ( array( 'http://mp.example.com', 'ftp://x.com', 'https://user:pw@mp.example.com', 'https://mp.example.com/?a=1', 'https://localhost', 'https://127.0.0.1', 'https://10.0.0.5', 'https://192.168.1.1', 'https://[::1]', 'https://intranet.local', 'not a url', 'javascript:alert(1)' ) as $url ) {
			$this->assertThrows( fn() => $this->in( fn() => Pairing::pair_http( 'WTY-ABCD-EFGH-JKLM', $url ) ), '', $url );
		}
		$this->assertSame( 0, count( $this->mp->requests ), 'nothing was sent for invalid input' );
		try {
			$this->in( fn() => Pairing::pair_http( 'WTY-ZZZZ-ZZZZ-ZZZZ', 'https://mp.example.com' ) );
			$this->fail( 'wrong code' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'wotoyi_pairing_failed', $e->error_code() );
			$this->assertSame( 422, $e->http_status() );
		}
		$this->assertSame( 0, Fixtures::count( 'sync_state' ) );
		// Malformed marketplace answers are refused, nothing stored.
		$this->mp->respond_with( 201, array( 'key_id' => 'bad', 'secret' => 'short' ) );
		$this->assertThrows( fn() => $this->in( fn() => Pairing::pair_http( 'WTY-ABCD-EFGH-JKLM', 'https://mp.example.com' ) ), 'Pairing failed' ); // M1: one generic answer for every remote failure.
		$this->mp->script[] = new WP_Error( 'http_request_failed', 'cURL error 6' );
		$this->assertThrows( fn() => $this->in( fn() => Pairing::pair_http( 'WTY-ABCD-EFGH-JKLM', 'https://mp.example.com' ) ), 'Pairing failed' );
		$this->assertSame( 0, Fixtures::count( 'sync_state' ) );
	}

	public function test_pairing_requires_secret_key_in_a_clean_process(): void {
		$out = shell_exec( 'WOTOYI_TEST_NO_KEY=1 ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/../scripts/no_key.php' ) . ' 2>&1' );
		$r   = json_decode( (string) $out, true );
		$this->assertTrue( is_array( $r ), 'script output: ' . $out );
		$this->assertFalse( $r['key_available'] );
		$this->assertSame( 'secret_key_missing', $r['encrypt'] );
		$this->assertSame( 'wotoyi_secret_key_missing:503', $r['pair'] );
		$this->assertSame( 0, $r['http_calls'], 'no network call without a key' );
		$this->assertSame( 0, $r['state_rows'] );
	}

	/* ---------------------------------------------------------------- signing */

	public function test_signature_matches_the_documented_canonical_string(): void {
		$secret = 'unit-test-secret-0123456789';
		$body   = '{"events":[]}';
		$ts     = 1800000000;
		$nonce  = 'abcDEF0123456789_-xyzQ';
		$http   = new HttpTransport( 'https://mp.example.com/sub', 'wk_0123456789abcdef', $secret );
		$req    = $http->build( 'POST', '/sync/batch', array( 'events' => array() ), $ts, $nonce );
		$this->assertSame( 'https://mp.example.com/sub/wp-json/wotoyi/v1/sync/batch', $req['url'] );
		$this->assertSame( $body, $req['args']['body'] );
		$canonical = "WOTOYI-HMAC-SHA256\n1800000000\n{$nonce}\nPOST\n/wotoyi/v1/sync/batch\n" . hash( 'sha256', $body );
		$this->assertSame( hash_hmac( 'sha256', $canonical, $secret ), $req['args']['headers']['X-Wotoyi-Signature'] );
		$this->assertSame( 'wk_0123456789abcdef', $req['args']['headers']['X-Wotoyi-Key'] );
		$this->assertSame( '1800000000', $req['args']['headers']['X-Wotoyi-Timestamp'] );
		$this->assertSame( 0, $req['args']['redirection'] );
		$this->assertStringNotContains( '?', $req['url'], 'no query string on signed requests' );
		// Body bytes are signed exactly as sent (unicode + slashes unescaped).
		$req2 = $http->build( 'POST', '/sync/events', array( 'a' => 'é/ü' ), $ts, $nonce );
		$this->assertSame( '{"a":"é/ü"}', $req2['args']['body'] );
		$this->assertSame( hash_hmac( 'sha256', "WOTOYI-HMAC-SHA256\n1800000000\n{$nonce}\nPOST\n/wotoyi/v1/sync/events\n" . hash( 'sha256', '{"a":"é/ü"}' ), $secret ), $req2['args']['headers']['X-Wotoyi-Signature'] );
		// Fresh nonce each call, valid shape.
		$n1 = $http->build( 'GET', '/sync/health', null )['args']['headers']['X-Wotoyi-Nonce'];
		$n2 = $http->build( 'GET', '/sync/health', null )['args']['headers']['X-Wotoyi-Nonce'];
		$this->assertTrue( $n1 !== $n2 && 1 === preg_match( '/^[A-Za-z0-9_\-]{16,64}$/', $n1 ) );
	}

	/* ------------------------------------------------------------------ push */

	public function test_worker_pushes_signed_batches_and_marks_events_sent(): void {
		$ids = $this->publish( 3 );
		$this->assertSame( 'not_paired', $this->run_worker()['status'] );
		$this->assertCount( 3, $this->outbox( 'pending' ), 'nothing is lost while unpaired' );
		$this->pair();
		$res = $this->run_worker();
		$this->assertSame( 3, $res['sent'] );
		$reqs = $this->mp->requests_to( '/wotoyi/v1/sync/batch' );
		$this->assertCount( 1, $reqs );
		$this->assertSame( 'POST', $reqs[0]['method'] );
		$sent = json_decode( $reqs[0]['body'], true )['events'];
		$this->assertCount( 3, $sent );
		foreach ( $sent as $e ) {
			$this->assertSame( $this->t['uuid'], $e['dealer_uuid'] );
			$this->assertTrue( in_array( $e['data']['source_id'], $ids, true ) );
		}
		$this->assertCount( 3, $this->outbox( 'sent' ) );
		$this->assertCount( 3, $this->mp->remote['vehicle'] );
		$state = $this->in( fn() => SyncState::get() );
		$this->assertNotNull( $state['last_push_at'] );
		$this->assertNull( $state['last_error'] );
		// Nothing more to send.
		$before = count( $this->mp->requests );
		$this->run_worker();
		$this->assertSame( $before, count( $this->mp->requests ), 'no empty requests' );
	}

	public function test_edit_and_delete_propagate_in_order(): void {
		$this->pair();
		[ $id ] = $this->publish( 1 );
		$this->run_worker();
		$this->assertSame( array( 'version' => 1, 'status' => 'published' ), $this->mp->remote['vehicle'][ $id ] );
		$this->in( fn() => Plugin::vehicles()->update( $id, array( 'price' => 1 ) ) );
		$this->run_worker();
		$this->assertSame( 2, $this->mp->remote['vehicle'][ $id ]['version'] );
		$this->in( fn() => Plugin::vehicles()->archive( $id ) );
		$this->run_worker();
		$this->assertSame( array( 'version' => 3, 'status' => 'deleted' ), $this->mp->remote['vehicle'][ $id ] );
	}

	public function test_transient_failure_retries_with_backoff_and_the_same_event_id(): void {
		$this->pair();
		$this->publish( 2 );
		$this->mp->respond_with( 503, array( 'code' => 'wotoyi_unavailable', 'message' => 'maintenance' ) );
		$r = $this->run_worker();
		$this->assertSame( 2, $r['retried'] );
		$this->assertSame( 'error', $r['status'] );
		$rows = $this->outbox( 'pending' );
		$this->assertCount( 2, $rows );
		foreach ( $rows as $row ) {
			$this->assertSame( '1', $row['attempts'] );
			$this->assertTrue( strtotime( $row['next_attempt_at'] . ' UTC' ) >= Clock::now() + 30, 'backoff >= 30 s' );
			$this->assertTrue( strtotime( $row['next_attempt_at'] . ' UTC' ) <= Clock::now() + 40, 'jitter <= 20%' );
			$this->assertStringContains( '503', (string) $row['last_http_status'] );
		}
		$first_ids = array_map( fn( $r ) => $r['event_id'], json_decode( $this->mp->requests_to( '/wotoyi/v1/sync/batch' )[0]['body'], true )['events'] );
		$this->assertSame( 0, $this->run_worker()['sent'], 'not due yet: nothing is sent early' );
		Fixtures::tick( 45 );
		$this->mp->now = Clock::now();
		$r = $this->run_worker();
		$this->assertSame( 2, $r['sent'] );
		$second_ids = array_map( fn( $r ) => $r['event_id'], json_decode( $this->mp->requests_to( '/wotoyi/v1/sync/batch' )[1]['body'], true )['events'] );
		$this->assertSame( $first_ids, $second_ids, 'a retry re-sends the identical event ids (idempotency key)' );
		$this->assertCount( 2, $this->outbox( 'sent' ) );
	}

	public function test_network_error_dead_letter_after_max_attempts_and_requeue(): void {
		$this->pair();
		$this->publish( 1 );
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->mp->script[] = new WP_Error( 'http_request_failed', 'timeout' );
			$this->run_worker();
			Fixtures::tick( 4400 ); // Longer than the 1 h cap plus 20% jitter.
			$this->mp->now = Clock::now();
		}
		$row = $this->outbox()[0];
		$this->assertSame( 'dead', $row['status'] );
		$this->assertSame( '12', $row['attempts'] );
		$before = count( $this->mp->requests );
		$this->run_worker();
		$this->assertSame( $before, count( $this->mp->requests ), 'dead events are not retried automatically' );
		$this->assertSame( 1, $this->in( fn() => Outbox::requeue() ) );
		$this->assertSame( 1, $this->run_worker()['sent'] );
		$this->assertSame( 'sent', $this->outbox()[0]['status'] );
	}

	public function test_backoff_curve(): void {
		$prev = 0;
		foreach ( array( 1, 2, 3, 4, 5, 6, 7, 8, 12 ) as $n ) {
			$d = Outbox::backoff( $n );
			$base = min( 3600, 30 * ( 2 ** ( $n - 1 ) ) );
			$this->assertTrue( $d >= $base && $d <= $base * 1.2 + 1, "attempt {$n}: {$d}" );
		}
		$this->assertTrue( Outbox::backoff( 30 ) <= 3600 * 1.2 );
	}

	public function test_per_event_results_partial_failure_rejection_and_stale(): void {
		$this->pair();
		$ids = $this->publish( 4 );
		$events = array_map( fn( $r ) => json_decode( $r['payload'], true ), $this->outbox() );
		$this->mp->respond_with(
			200,
			array(
				'results' => array(
					array( 'status' => 'applied', 'http_status' => 200, 'event_id' => $events[0]['event_id'] ),
					array( 'status' => 'rejected', 'http_status' => 422, 'event_id' => $events[1]['event_id'], 'code' => 'quality_rules', 'message' => 'A vehicle needs a title or make and model.' ),
					array( 'status' => 'failed', 'http_status' => 500, 'event_id' => $events[2]['event_id'], 'code' => 'internal_error', 'message' => 'Internal error' ),
					array( 'status' => 'stale', 'http_status' => 409, 'event_id' => $events[3]['event_id'], 'code' => 'stale_version' ),
				),
			)
		);
		$r = $this->run_worker();
		$this->assertSame( 1, $r['sent'] );
		$this->assertSame( 1, $r['rejected'] );
		$this->assertSame( 1, $r['retried'] );
		$this->assertSame( 1, $r['stale'] );
		$by = array();
		foreach ( $this->outbox() as $row ) {
			$by[ $row['event_id'] ] = $row;
		}
		$this->assertSame( 'sent', $by[ $events[0]['event_id'] ]['status'] );
		$this->assertSame( 'rejected', $by[ $events[1]['event_id'] ]['status'] );
		$this->assertStringContains( 'quality_rules', $by[ $events[1]['event_id'] ]['last_error'] );
		$this->assertSame( 'pending', $by[ $events[2]['event_id'] ]['status'] );
		$this->assertSame( 'sent', $by[ $events[3]['event_id'] ]['status'] );
		$this->assertSame( 'stale', $by[ $events[3]['event_id'] ]['result_status'] );
		$this->assertStringContains( 'internal_error', (string) $this->in( fn() => SyncState::get() )['last_error'], 'state shows the most recent problem' );
		Fixtures::tick( 3600 );
		$this->mp->now = Clock::now();
		$this->run_worker();
		$this->assertSame( 'rejected', $this->in( fn() => TenantDb::get( 'outbox', array( 'event_id' => $events[1]['event_id'] ) ) )['status'], 'rejections are never retried automatically' );
	}

	public function test_missing_result_for_an_event_is_retried(): void {
		$this->pair();
		$this->publish( 2 );
		$e = json_decode( $this->outbox()[0]['payload'], true );
		$this->mp->respond_with( 200, array( 'results' => array( array( 'status' => 'applied', 'http_status' => 200, 'event_id' => $e['event_id'] ) ) ) );
		$r = $this->run_worker();
		$this->assertSame( 1, $r['sent'] );
		$this->assertSame( 1, $r['retried'] );
		$this->assertCount( 1, $this->outbox( 'pending' ) );
	}

	public function test_credential_rejection_stops_pushing_until_repaired(): void {
		$this->pair();
		$this->publish( 1 );
		$this->mp->respond_with( 401, array( 'code' => 'wotoyi_auth_failed', 'message' => 'Invalid credentials.' ) );
		$r = $this->run_worker();
		$this->assertSame( 'auth_failed', $r['status'] );
		$this->assertSame( 'auth_failed', $this->in( fn() => SyncState::get() )['status'] );
		$row = $this->outbox()[0];
		$this->assertSame( 'pending', $row['status'] );
		$this->assertTrue( strtotime( $row['next_attempt_at'] . ' UTC' ) >= Clock::now() + 900 );
		$n = count( $this->mp->requests );
		$this->assertSame( 'not_paired', $this->run_worker()['status'] );
		$this->assertSame( $n, count( $this->mp->requests ), 'no hammering with bad credentials' );
		$this->pair();
		Fixtures::tick( 1000 );
		$this->mp->now = Clock::now();
		$this->assertSame( 1, $this->run_worker()['sent'] );
	}

	public function test_batches_respect_size_and_byte_limits(): void {
		$this->pair();
		$this->publish( 120 );
		$this->in( fn() => Worker::run_for_current( 10 ) );
		$reqs = $this->mp->requests_to( '/wotoyi/v1/sync/batch' );
		$this->assertCount( 3, $reqs );
		$sizes = array_map( fn( $r ) => count( json_decode( $r['body'], true )['events'] ), $reqs );
		$this->assertSame( array( 50, 50, 20 ), $sizes );
		$this->assertCount( 120, $this->outbox( 'sent' ) );
		// Byte limit: huge payloads go in smaller batches and none is lost.
		update_option( 'wotoyi_dp_settings', array( 'batch_max_bytes' => 5000 ) );
		$big = $this->in( fn() => array_map( fn( $i ) => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published', 'model' => 'B' . $i, 'description' => str_repeat( 'x', 2500 ) ) ) )['id'], range( 1, 6 ) ) );
		$this->in( fn() => Worker::run_for_current( 20 ) );
		$this->assertCount( 126, $this->outbox( 'sent' ) );
		foreach ( array_slice( $this->mp->requests_to( '/wotoyi/v1/sync/batch' ), 3 ) as $r ) {
			$this->assertTrue( count( json_decode( $r['body'], true )['events'] ) <= 2 );
		}
	}

	public function test_claims_are_exclusive_and_dead_workers_release_their_claims(): void {
		$this->publish( 3 );
		$a = $this->in( fn() => Outbox::claim( 2, 120 ) );
		$b = $this->in( fn() => Outbox::claim( 10, 120 ) );
		$this->assertCount( 2, $a );
		$this->assertCount( 1, $b, 'a second worker only gets what is left' );
		$this->assertSame( array(), array_intersect( array_column( $a, 'id' ), array_column( $b, 'id' ) ) );
		$this->assertCount( 0, $this->in( fn() => Outbox::claim( 10, 120 ) ) );
		Fixtures::tick( 121 );
		$c = $this->in( fn() => Outbox::claim( 10, 120 ) );
		$this->assertCount( 3, $c, 'claims of a crashed worker expire and are re-claimed' );
	}

	public function test_kick_schedules_cron_in_http_mode_and_does_nothing_when_unpaired(): void {
		$this->publish( 1 );
		$this->assertSame( array(), $GLOBALS['wotoyi_cron_single'] );
		$this->pair();
		$this->publish( 1 );
		$this->assertArrayHasKey( Worker::HOOK_TICK, $GLOBALS['wotoyi_cron_single'] );
		$this->assertSame( 0, count( $this->mp->requests_to( '/wotoyi/v1/sync/batch' ) ), 'HTTP mode never pushes inside the user request' );
	}

	public function test_tick_serves_every_tenant_with_its_own_credentials_and_only_its_own_events(): void {
		$b = Fixtures::tenant( 'Dealer B', 12 );
		$secrets = array( 'wk_aaaaaaaaaaaaaaaa' => 'secret-for-a-0123456789', 'wk_bbbbbbbbbbbbbbbb' => 'secret-for-b-0123456789' );
		foreach ( array( array( $this->t, 'wk_aaaaaaaaaaaaaaaa' ), array( $b, 'wk_bbbbbbbbbbbbbbbb' ) ) as [ $tenant, $key ] ) {
			Fixtures::in( $tenant, fn() => SyncState::save( array( 'status' => 'paired', 'transport' => 'http', 'base_url' => 'https://mp.example.com', 'key_id' => $key, 'secret_enc' => Crypto::encrypt( $secrets[ $key ] ), 'paired_at' => Clock::mysql() ) ) );
			Fixtures::in( $tenant, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		}
		$seen = array();
		$GLOBALS['wotoyi_http_handler'] = function ( string $url, array $args ) use ( &$seen, $secrets ) {
			$key = $args['headers']['X-Wotoyi-Key'];
			foreach ( json_decode( $args['body'], true )['events'] ?? array() as $e ) {
				$seen[ $key ][] = $e['dealer_uuid'];
			}
			$sig = hash_hmac( 'sha256', "WOTOYI-HMAC-SHA256\n{$args['headers']['X-Wotoyi-Timestamp']}\n{$args['headers']['X-Wotoyi-Nonce']}\nPOST\n/wotoyi/v1/sync/batch\n" . hash( 'sha256', $args['body'] ), $secrets[ $key ] );
			if ( ! hash_equals( $sig, $args['headers']['X-Wotoyi-Signature'] ) ) {
				return array( 'code' => 401, 'body' => '{"code":"wotoyi_auth_failed"}' );
			}
			$results = array_map( fn( $e ) => array( 'status' => 'applied', 'http_status' => 200, 'event_id' => $e['event_id'] ), json_decode( $args['body'], true )['events'] );
			return array( 'code' => 200, 'body' => json_encode( array( 'results' => $results ) ) );
		};
		$out = Worker::tick();
		$this->assertCount( 2, $out );
		$this->assertSame( array( $this->t['uuid'] ), array_unique( $seen['wk_aaaaaaaaaaaaaaaa'] ), 'tenant A key carried only tenant A events' );
		$this->assertSame( array( $b['uuid'] ), array_unique( $seen['wk_bbbbbbbbbbbbbbbb'] ) );
		$this->assertCount( 1, $this->outbox( 'sent' ) );
		$this->assertSame( 1, Fixtures::in( $b, fn() => TenantDb::count( 'outbox', array( 'status' => 'sent' ) ) ) );
	}

	public function test_one_failing_tenant_does_not_block_another(): void {
		$b = Fixtures::tenant( 'Dealer B', 12 );
		foreach ( array( $this->t, $b ) as $tenant ) {
			Fixtures::in( $tenant, fn() => SyncState::save( array( 'status' => 'paired', 'transport' => 'http', 'base_url' => 'https://mp.example.com', 'key_id' => 'wk_aaaaaaaaaaaaaaaa', 'secret_enc' => Crypto::encrypt( 'x' ), 'paired_at' => Clock::mysql() ) ) );
			Fixtures::in( $tenant, fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		}
		$calls = 0;
		$GLOBALS['wotoyi_http_handler'] = function ( string $url, array $args ) use ( &$calls ) {
			++$calls;
			if ( 1 === $calls ) {
				throw new RuntimeException( 'boom in transport' );
			}
			$results = array_map( fn( $e ) => array( 'status' => 'applied', 'http_status' => 200, 'event_id' => $e['event_id'] ), json_decode( $args['body'], true )['events'] );
			return array( 'code' => 200, 'body' => json_encode( array( 'results' => $results ) ) );
		};
		$out = Worker::tick();
		$this->assertSame( 'error', $out[ (int) $this->t['id'] ]['status'] );
		$this->assertSame( 1, Fixtures::in( $b, fn() => TenantDb::count( 'outbox', array( 'status' => 'sent' ) ) ), 'tenant B was still served' );
	}

	/* ----------------------------------------------------------- reconcile */

	public function test_reconciliation_repairs_drift_and_reports_orphans(): void {
		$this->pair();
		$ids = $this->publish( 5 );
		$this->run_worker();
		$this->assertCount( 5, $this->mp->remote['vehicle'] );
		// Drift: [0] lost remotely, [1] remote behind, [2] remote status changed at equal version, [3] remote ahead (e.g. our DB restored from backup), [4] fine; plus an orphan.
		unset( $this->mp->remote['vehicle'][ $ids[0] ] );
		$this->mp->remote['vehicle'][ $ids[1] ]['version'] = 0;
		$this->mp->remote['vehicle'][ $ids[2] ]['status'] = 'unpublished';
		$this->mp->remote['vehicle'][ $ids[3] ]['version'] = 9;
		$this->mp->remote['vehicle']['orphan-1'] = array( 'version' => 3, 'status' => 'published' );
		$this->mp->remote['vehicle']['orphan-2'] = array( 'version' => 3, 'status' => 'deleted' );
		$sum = $this->in( fn() => Reconciler::run_for_current() );
		$v   = $sum['types']['vehicle'];
		$this->assertSame( 5, $v['checked'] );
		$this->assertSame( 1, $v['missing_remote'] );
		$this->assertSame( 1, $v['outdated'] );
		$this->assertSame( 2, $v['version_bumped'] );
		$this->assertSame( 1, $v['ok'] );
		$this->assertSame( 1, $v['orphans'], 'tombstones are not orphans' );
		$this->assertSame( 'ok', $sum['status'] );
		$this->run_worker();
		foreach ( $ids as $i => $id ) {
			$local = $this->in( fn() => Plugin::vehicles()->get( $id ) );
			$this->assertSame( array( $local['version'], 'published' ), array( $this->mp->remote['vehicle'][ $id ]['version'], $this->mp->remote['vehicle'][ $id ]['status'] ), "vehicle {$i} converged" );
		}
		$this->assertSame( 10, $this->in( fn() => Plugin::vehicles()->get( $ids[3] ) )['version'], 'local version moved past the remote one' );
		$this->assertSame( 'published', $this->mp->remote['vehicle']['orphan-1']['status'], 'orphans are reported, never deleted' );
		$state = $this->in( fn() => SyncState::present() );
		$this->assertNotNull( $state['last_reconcile_at'] );
		$this->assertSame( 1, $state['last_reconcile_summary']['types']['vehicle']['orphans'] );
		// Second run: nothing left to repair.
		$sum2 = $this->in( fn() => Reconciler::run_for_current() );
		$this->assertSame( 5, $sum2['types']['vehicle']['ok'] );
		$this->assertSame( 0, $this->in( fn() => TenantDb::count( 'outbox', array( 'status' => 'pending' ) ) ) );
		$audit = $this->in( fn() => \Wotoyi\DealerPlatform\Service\AuditLog::list( array( 'action' => 'sync.version_bumped' ) ) );
		$this->assertSame( 2, $audit['total'] );
	}

	public function test_initial_sync_after_pairing_is_a_reconciliation_of_an_empty_remote(): void {
		$ids = $this->publish( 3 );
		$this->in( fn() => Plugin::vehicles()->transition( $ids[2], 'unpublished' ) );
		$this->in( fn() => Plugin::services()->create( array( 'title' => 'Vidange', 'category' => 'maintenance', 'status' => 'published' ) ) );
		$this->in( fn() => Plugin::vehicles()->create( Fixtures::vehicle() ) ); // draft: never synced
		$this->pair();
		$this->in( fn() => Reconciler::run_for_current() );
		$this->run_worker();
		$this->assertCount( 3, $this->mp->remote['vehicle'] );
		$this->assertSame( 'unpublished', $this->mp->remote['vehicle'][ $ids[2] ]['status'] );
		$this->assertCount( 1, $this->mp->remote['service'] );
	}

	public function test_reconcile_deleted_locally_but_alive_remotely_pushes_a_tombstone(): void {
		$this->pair();
		[ $id ] = $this->publish( 1 );
		$this->run_worker();
		$this->in( fn() => Plugin::vehicles()->archive( $id ) );
		$this->in( fn() => TenantDb::update( 'outbox', array( 'status' => 'pending' ), array( 'status' => 'dead' ) ) ); // The delete event is lost.
		$this->assertSame( 'published', $this->mp->remote['vehicle'][ $id ]['status'] );
		$sum = $this->in( fn() => Reconciler::run_for_current() );
		$this->assertSame( 1, $sum['types']['vehicle']['tombstones_pushed'] );
		$this->run_worker();
		$this->assertSame( 'deleted', $this->mp->remote['vehicle'][ $id ]['status'] );
	}

	/* ----------------------------------------------------------------- leads */

	private function lead( array $over = array() ): array {
		return $over + array( 'uuid' => '0190' . str_pad( dechex( Fixtures::seq() ), 4, '0', STR_PAD_LEFT ) . '-aaaa-7bbb-8ccc-dddddddddddd', 'name' => 'Buyer', 'phone' => '+221770001111', 'email' => null, 'message' => 'Still available?', 'channel' => 'web', 'locale' => 'fr', 'consent' => true, 'created_at' => '2027-01-15 09:00:00', 'vehicle_uuid' => null, 'vehicle_source_id' => null, 'service_uuid' => null, 'service_source_id' => null );
	}

	public function test_lead_pull_imports_idempotently_and_acknowledges(): void {
		$this->pair();
		[ $vid ] = $this->publish( 1 );
		$other = Fixtures::tenant( 'Dealer B', 12 );
		$foreign = Fixtures::in( $other, fn() => Plugin::vehicles()->create( Fixtures::vehicle() )['id'] );
		$l1 = $this->lead( array( 'vehicle_source_id' => $vid, 'email' => 'Buyer@Example.com' ) );
		$l2 = $this->lead( array( 'name' => '<b>Evil</b><script>x</script>', 'phone' => '+221770002222', 'message' => '<img src=x onerror=alert(1)>hello', 'vehicle_source_id' => $foreign ) );
		$l3 = $this->lead( array( 'name' => null, 'phone' => null, 'email' => null ) ); // Unusable.
		$this->mp->leads = array( $l1, $l2, $l3 );
		$r = $this->in( fn() => LeadPuller::run_for_current() );
		$this->assertSame( 'ok', $r['status'] );
		$this->assertSame( 2, $r['imported'] );
		$this->assertSame( 1, $r['invalid'] );
		$this->assertSame( 3, $r['acknowledged'], 'bad records are acknowledged too so they cannot block the queue' );
		$leads = $this->in( fn() => TenantDb::select( 'leads', array(), array( 'order' => array( array( 'id', 'asc' ) ) ) ) );
		$this->assertCount( 2, $leads );
		$this->assertSame( 'marketplace', $leads[0]['source'] );
		$this->assertSame( $l1['uuid'], $leads[0]['external_id'] );
		$this->assertSame( $vid, $leads[0]['vehicle_uuid'] );
		$this->assertSame( 'buyer@example.com', $leads[0]['email'] );
		$this->assertNull( $leads[1]['vehicle_uuid'], 'another dealer\'s vehicle id is never linked' );
		$this->assertStringNotContains( '<', (string) $leads[1]['name'] . $leads[1]['message'] );
		$this->assertStringNotContains( 'script', (string) $leads[1]['name'] );
		$this->assertSame( 'new', $leads[0]['stage'] );
		$this->assertSame( 1, Fixtures::count( 'activities', "entity_uuid = '{$leads[0]['uuid']}'" ) );
		$ack = json_decode( $this->mp->requests_to( '/wotoyi/v1/sync/leads/ack' )[0]['body'], true )['lead_ids'];
		$this->assertEquals( array( $l1['uuid'], $l2['uuid'], $l3['uuid'] ), $ack );
		// Marketplace redelivers (ack lost): no duplicates.
		$this->mp->acked = array();
		$r = $this->in( fn() => LeadPuller::run_for_current() );
		$this->assertSame( 0, $r['imported'] );
		$this->assertSame( 2, $r['duplicates'] );
		$this->assertSame( 2, Fixtures::count( 'leads' ) );
		$this->assertNotNull( $this->in( fn() => SyncState::get() )['last_pull_at'] );
	}

	public function test_lead_ack_failure_loses_nothing_and_duplicates_nothing(): void {
		$this->pair();
		$this->mp->leads = array( $this->lead(), $this->lead( array( 'phone' => '+221770003333' ) ) );
		$this->mp->script = array( null, array( 'code' => 500, 'body' => '{"code":"internal"}' ) ); // pull ok (null => default), ack fails.
		$r = $this->in( fn() => LeadPuller::run_for_current() );
		$this->assertSame( 'error', $r['status'] );
		$this->assertSame( 2, Fixtures::count( 'leads' ) );
		$this->assertSame( array(), $this->mp->acked );
		$r = $this->in( fn() => LeadPuller::run_for_current() );
		$this->assertSame( 'ok', $r['status'] );
		$this->assertSame( 2, $r['duplicates'] );
		$this->assertSame( 2, Fixtures::count( 'leads' ), 'redelivery created nothing new' );
		$this->assertCount( 2, $this->mp->acked );
	}

	public function test_tick_pulls_leads_on_interval(): void {
		$this->pair();
		$this->mp->leads = array( $this->lead() );
		Worker::tick();
		$this->assertSame( 1, Fixtures::count( 'leads' ) );
		$n = count( $this->mp->requests_to( '/wotoyi/v1/sync/leads/pull' ) );
		Worker::tick();
		$this->assertSame( $n, count( $this->mp->requests_to( '/wotoyi/v1/sync/leads/pull' ) ), 'not pulled again before the interval' );
		Fixtures::tick( 130 );
		$this->mp->now = Clock::now();
		Worker::tick();
		$this->assertSame( $n + 1, count( $this->mp->requests_to( '/wotoyi/v1/sync/leads/pull' ) ) );
	}

	/* ---------------------------------------------------------------- safety */

	public function test_disconnect_forgets_credentials_but_keeps_queued_events(): void {
		$this->pair();
		$this->publish( 2 );
		$this->in( fn() => Pairing::disconnect() );
		$row = Fixtures::raw( 'SELECT * FROM wp_wotoyi_dp_sync_state' )[0];
		$this->assertSame( 'disconnected', $row['status'] );
		$this->assertNull( $row['secret_enc'] );
		$this->assertNull( $row['key_id'] );
		$this->assertCount( 2, $this->outbox( 'pending' ) );
		$this->assertSame( 'not_paired', $this->run_worker()['status'] );
	}

	public function test_secret_never_reaches_logs_audit_or_api(): void {
		$this->pair();
		$this->publish( 1 );
		$this->mp->respond_with( 503, array( 'code' => 'x', 'message' => 'down' ) );
		$this->run_worker();
		$blob = json_encode( array(
			$this->in( fn() => \Wotoyi\DealerPlatform\Service\AuditLog::list( array() ) ),
			$this->in( fn() => Outbox::list( array() ) ),
			$this->in( fn() => SyncState::present() ),
			Fixtures::raw( 'SELECT * FROM wp_wotoyi_dp_audit_log' ),
			Fixtures::raw( 'SELECT payload, last_error FROM wp_wotoyi_dp_outbox' ),
		) );
		$this->assertStringNotContains( $this->mp->secret, $blob );
		$log = (string) @file_get_contents( (string) ini_get( 'error_log' ) );
		$this->assertStringNotContains( $this->mp->secret, $log );
		Fixtures::platform_admin();
		$this->assertStringNotContains( $this->mp->secret, json_encode( Fixtures::call( 'GET', '/platform/tenants' )->get_data() ) );
	}

	public function test_sync_rest_endpoints(): void {
		Fixtures::login( 11 );
		$res = Fixtures::call( 'POST', '/sync/pair', array( 'code' => 'WTY-ABCD-EFGH-JKLM', 'base_url' => 'https://mp.example.com' ) );
		$this->assertStatus( 201, $res );
		$this->assertSame( 'paired', $res->get_data()['status'] );
		$this->assertArrayHasKey( 'initial_sync', $res->get_data() );
		$this->assertStringNotContains( $this->mp->secret, json_encode( $res->get_data() ) );
		$this->assertStatus( 200, Fixtures::call( 'GET', '/sync/status' ) );
		$this->in( fn() => Plugin::vehicles()->create( Fixtures::vehicle( array( 'status' => 'published' ) ) ) );
		$run = Fixtures::call( 'POST', '/sync/run' );
		$this->assertStatus( 200, $run );
		$this->assertSame( 1, $run->get_data()['push']['sent'] );
		$this->assertSame( 1, Fixtures::call( 'GET', '/sync/outbox', null, array( 'status' => 'sent' ) )->get_data()['total'] );
		$this->assertStatus( 200, Fixtures::call( 'POST', '/sync/reconcile' ) );
		$this->assertStatus( 200, Fixtures::call( 'POST', '/sync/requeue' ) );
		$this->assertStatus( 422, Fixtures::call( 'POST', '/sync/pair', array( 'code' => 'bad', 'base_url' => 'https://mp.example.com' ) ) );
		// Throttled after 5 pairing attempts.
		for ( $i = 0; $i < 6; $i++ ) {
			$last = Fixtures::call( 'POST', '/sync/pair', array( 'code' => 'WTY-ZZZZ-ZZZZ-ZZZZ', 'base_url' => 'https://mp.example.com' ) );
		}
		$this->assertStatus( 429, $last );
		$this->assertStatus( 200, Fixtures::call( 'POST', '/sync/disconnect' ) );
	}
}
