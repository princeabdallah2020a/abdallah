<?php
use Wotoyi\Marketplace\Plugin as MP;
use Wotoyi\Marketplace\Support\Clock as MpClock;

/** Base class: both plugins installed, clocks in step, HTTP bridged to the real marketplace REST dispatcher. */
abstract class Integration_Case extends Wotoyi_Test_Case {

	protected function setUp(): void {
		parent::setUp(); // Resets state, installs Dealer Platform tables + routes.
		MP::reset();
		MpClock::set( self::T0 );
		\Wotoyi\Marketplace\Installer::install_tables();
		\Wotoyi\Marketplace\Rest\Routes::register();
		$GLOBALS['wotoyi_http_handler'] = array( $this, 'bridge' );
		$GLOBALS['wotoyi_bridge_log']   = array();
	}

	protected function tearDown(): void {
		MpClock::set( null );
		parent::tearDown();
	}

	protected function assertNotSame( $a, $b, string $msg = '' ): void {
		$this->assertTrue( $a !== $b, $msg ?: 'values should differ' );
	}

	/** Advance both plugins' clocks together. */
	protected function advance( int $seconds ): void {
		Fixtures::tick( $seconds );
		MpClock::set( \Wotoyi\DealerPlatform\Support\Clock::now() );
	}

	/** wp_safe_remote_request() replacement: dispatch to the marketplace REST routes in-process. */
	public function bridge( string $url, array $args ): array {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		$path = (string) preg_replace( '#^.*?/wp-json#', '', $path );
		$req  = new WP_REST_Request( strtoupper( (string) ( $args['method'] ?? 'GET' ) ), $path );
		if ( isset( $args['body'] ) && '' !== $args['body'] ) {
			$req->set_body( (string) $args['body'] );
		}
		foreach ( (array) ( $args['headers'] ?? array() ) as $k => $v ) {
			$req->set_header( (string) $k, (string) $v );
		}
		$res = rest_do_request( $req );
		$GLOBALS['wotoyi_bridge_log'][] = array( $req->get_method(), $path, $res->get_status() );
		return array( 'code' => $res->get_status(), 'body' => (string) json_encode( $res->get_data() ) );
	}

	/** Marketplace staff creates a dealer bound to the tenant and returns a pairing code. */
	protected function mp_dealer_for( array $tenant ): array {
		$prev = $GLOBALS['wotoyi_user'];
		$GLOBALS['wotoyi_user'] = array( 'id' => 900, 'caps' => array( \Wotoyi\Marketplace\Installer::CAP ) );
		$r = new WP_REST_Request( 'POST', '/wotoyi/v1/admin/dealers' );
		$r->set_header( 'X-WP-Nonce', 'valid-nonce' );
		$r->set_body( (string) json_encode( array( 'name' => $tenant['name'], 'city' => 'Dakar', 'country' => 'SN', 'source_dealer_uuid' => $tenant['uuid'] ) ) );
		$res = rest_do_request( $r );
		$GLOBALS['wotoyi_user'] = $prev;
		$this->assertSame( 201, $res->get_status(), json_encode( $res->get_data() ) );
		return $res->get_data();
	}

	protected function paired_tenant( string $name, int $owner ): array {
		$t    = Fixtures::tenant( $name, $owner );
		$mp   = $this->mp_dealer_for( $t );
		$st   = Fixtures::in( $t, fn() => \Wotoyi\DealerPlatform\Sync\Pairing::pair_http( $mp['pairing_code'], 'https://marketplace.example.com' ) );
		$this->assertSame( 'paired', $st['status'] );
		return array( 'tenant' => $t, 'mp' => $mp );
	}

	/** Public marketplace search through the REST layer. @return array<string,mixed> */
	protected function search( array $query = array() ): array {
		$r = new WP_REST_Request( 'GET', '/wotoyi/v1/vehicles' );
		$r->set_query_params( $query );
		$res = rest_do_request( $r );
		$this->assertSame( 200, $res->get_status(), json_encode( $res->get_data() ) );
		return $res->get_data();
	}

	protected function push( array $tenant ): array {
		return Fixtures::in( $tenant, fn() => \Wotoyi\DealerPlatform\Sync\Worker::run_for_current() );
	}
}
