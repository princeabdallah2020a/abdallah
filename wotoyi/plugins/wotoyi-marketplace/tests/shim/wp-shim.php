<?php
/**
 * TEST SHIM ONLY: the small subset of WordPress the plugin uses, enough to run its real code under `php`.
 * It is not WordPress: behaviours are approximations (documented in README "Known limits").
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WOTOYI_MP_VERSION', 'test' );
define( 'WOTOYI_MP_DB_VERSION', '1' );
define( 'WOTOYI_MP_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'WOTOYI_MP_FILE', WOTOYI_MP_DIR . 'wotoyi-marketplace.php' );
define( 'WOTOYI_SECRET_KEY', 'test-master-key-0123456789abcdef0123456789abcdef' );

require_once __DIR__ . '/Sqlite_Wpdb.php';

$GLOBALS['wotoyi_options']    = array();
$GLOBALS['wotoyi_transients'] = array();
$GLOBALS['wotoyi_filters']    = array();
$GLOBALS['wotoyi_actions']    = array();
$GLOBALS['wotoyi_routes']     = array();
$GLOBALS['wotoyi_user']       = array( 'id' => 0, 'caps' => array() );
$GLOBALS['wotoyi_db_errors']  = array();

/* ------------------------------ errors / REST objects ------------------------------ */

class WP_Error { // phpcs:ignore
	public function __construct( private string $code = '', private string $message = '', private $data = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }

class WP_REST_Server { // phpcs:ignore
	public const READABLE  = 'GET';
	public const CREATABLE = 'POST';
	public const EDITABLE  = 'POST, PUT, PATCH';
	public const DELETABLE = 'DELETE';
}

class WP_REST_Response { // phpcs:ignore
	private array $headers = array();
	public function __construct( private $data = null, private int $status = 200 ) {}
	public function get_data() { return $this->data; }
	public function set_data( $d ): void { $this->data = $d; }
	public function get_status(): int { return $this->status; }
	public function set_status( int $s ): void { $this->status = $s; }
	public function header( string $k, string $v ): void { $this->headers[ $k ] = $v; }
	public function get_headers(): array { return $this->headers; }
}

class WP_REST_Request implements ArrayAccess { // phpcs:ignore
	private array $headers = array();
	private string $body = '';
	private array $query = array();
	private array $url_params = array();
	private array $params = array();
	public function __construct( private string $method = 'GET', private string $route = '' ) {}
	public function get_method(): string { return $this->method; }
	public function get_route(): string { return $this->route; }
	public function set_header( string $k, string $v ): void { $this->headers[ strtolower( str_replace( '-', '_', $k ) ) ] = $v; }
	public function get_header( string $k ) { return $this->headers[ strtolower( str_replace( '-', '_', $k ) ) ] ?? null; }
	public function set_body( string $b ): void { $this->body = $b; }
	public function get_body(): string { return $this->body; }
	public function set_query_params( array $q ): void { $this->query = $q; }
	public function get_query_params(): array { return $this->query; }
	public function set_url_params( array $p ): void { $this->url_params = $p; }
	public function get_params(): array { return $this->params + $this->url_params + $this->query; }
	public function get_param( string $k ) { return $this->get_params()[ $k ] ?? null; }
	public function set_param( string $k, $v ): void { $this->params[ $k ] = $v; }
	public function offsetExists( mixed $o ): bool { return null !== $this->get_param( (string) $o ); }
	public function offsetGet( mixed $o ): mixed { return $this->get_param( (string) $o ); }
	public function offsetSet( mixed $o, mixed $v ): void { $this->params[ (string) $o ] = $v; }
	public function offsetUnset( mixed $o ): void { unset( $this->params[ (string) $o ] ); }
}

function register_rest_route( string $ns, string $route, array $args ): void {
	$GLOBALS['wotoyi_routes'][] = array( 'pattern' => '/' . $ns . $route, 'args' => $args );
}

/** Mini dispatcher: first matching route+method wins; permission_callback is mandatory and enforced. */
function rest_do_request( WP_REST_Request $request ): WP_REST_Response {
	foreach ( $GLOBALS['wotoyi_routes'] as $r ) {
		if ( ! preg_match( '@^' . $r['pattern'] . '$@', $request->get_route(), $m ) ) {
			continue;
		}
		$methods = array_map( 'trim', explode( ',', strtoupper( $r['args']['methods'] ) ) );
		if ( ! in_array( $request->get_method(), $methods, true ) ) {
			continue;
		}
		$request->set_url_params( array_filter( $m, 'is_string', ARRAY_FILTER_USE_KEY ) );
		$perm = $r['args']['permission_callback'] ?? null;
		if ( null === $perm ) {
			throw new RuntimeException( 'Route without permission_callback: ' . $r['pattern'] );
		}
		$ok = call_user_func( $perm, $request );
		if ( is_wp_error( $ok ) ) {
			return wotoyi_error_response( $ok );
		}
		if ( true !== $ok ) {
			return new WP_REST_Response( array( 'code' => 'rest_forbidden' ), 403 );
		}
		$res = call_user_func( $r['args']['callback'], $request );
		return is_wp_error( $res ) ? wotoyi_error_response( $res ) : ( $res instanceof WP_REST_Response ? $res : new WP_REST_Response( $res ) );
	}
	return new WP_REST_Response( array( 'code' => 'rest_no_route' ), 404 );
}

function wotoyi_error_response( WP_Error $e ): WP_REST_Response {
	$data = $e->get_error_data();
	return new WP_REST_Response( array( 'code' => $e->get_error_code(), 'message' => $e->get_error_message() ), (int) ( $data['status'] ?? 500 ) );
}

/* ------------------------------ options / transients / hooks ------------------------------ */

function get_option( string $k, $default = false ) { return $GLOBALS['wotoyi_options'][ $k ] ?? $default; }
function update_option( string $k, $v, $autoload = null ): bool { $GLOBALS['wotoyi_options'][ $k ] = $v; return true; }
function delete_option( string $k ): bool { unset( $GLOBALS['wotoyi_options'][ $k ] ); return true; }
function get_transient( string $k ) {
	$t = $GLOBALS['wotoyi_transients'][ $k ] ?? null;
	return ( $t && ( 0 === $t[1] || $t[1] > \Wotoyi\Marketplace\Support\Clock::now() ) ) ? $t[0] : false;
}
function set_transient( string $k, $v, int $ttl = 0 ): bool {
	$GLOBALS['wotoyi_transients'][ $k ] = array( $v, $ttl ? \Wotoyi\Marketplace\Support\Clock::now() + $ttl : 0 );
	return true;
}
function add_filter( string $tag, callable $cb, int $prio = 10, int $args = 1 ): void { $GLOBALS['wotoyi_filters'][ $tag ][] = $cb; }
function apply_filters( string $tag, $value, ...$rest ) {
	foreach ( $GLOBALS['wotoyi_filters'][ $tag ] ?? array() as $cb ) {
		$value = $cb( $value, ...$rest );
	}
	return $value;
}
function add_action( string $tag, $cb, int $prio = 10, int $args = 1 ): void { $GLOBALS['wotoyi_actions'][ $tag ][] = $cb; }
function do_action( string $tag, ...$args ): void {}
function did_action( string $tag ): int { return $GLOBALS['wotoyi_did'][ $tag ] ?? 0; }

/* ------------------------------ users / caps ------------------------------ */

function is_user_logged_in(): bool { return $GLOBALS['wotoyi_user']['id'] > 0; }
function get_current_user_id(): int { return (int) $GLOBALS['wotoyi_user']['id']; }
function current_user_can( string $cap ): bool { return in_array( $cap, $GLOBALS['wotoyi_user']['caps'], true ); }
function wp_verify_nonce( $nonce, $action ) { return ( 'valid-nonce' === $nonce && 'wp_rest' === $action ) ? 1 : false; }
function get_role( string $r ) { return new class() { public function add_cap( string $c ): void { $GLOBALS['wotoyi_caps_added'][] = $c; } }; }
function wp_next_scheduled( string $h ) { return $GLOBALS['wotoyi_cron'][ $h ] ?? false; }
function wp_schedule_event( int $t, string $r, string $h ): void { $GLOBALS['wotoyi_cron'][ $h ] = $t; }
function wp_clear_scheduled_hook( string $h ): void { unset( $GLOBALS['wotoyi_cron'][ $h ] ); }
function register_activation_hook( $f, $cb ): void { $GLOBALS['wotoyi_activation'] = $cb; }
function register_deactivation_hook( $f, $cb ): void {}
function plugin_dir_path( string $f ): string { return dirname( $f ) . '/'; }
function plugin_basename( string $f ): string { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function load_plugin_textdomain(): bool { return true; }

/* ------------------------------ sanitising / escaping ------------------------------ */

function __( string $s, string $d = '' ): string { return $s; }
function esc_html__( string $s, string $d = '' ): string { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( string $s ): string { return htmlspecialchars( $s, ENT_QUOTES ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function sanitize_text_field( $s ): string {
	$s = strip_tags( (string) $s );
	$s = preg_replace( '/[\r\n\t ]+/', ' ', $s );
	$s = preg_replace( '/%[a-f0-9]{2}/i', '', $s );
	return trim( $s );
}
function sanitize_textarea_field( $s ): string {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_title( string $s ): string {
	$s = strtolower( strip_tags( $s ) );
	$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
	return trim( $s, '-' );
}
function sanitize_email( string $s ): string { return preg_replace( '/[^a-zA-Z0-9@._+\-]/', '', $s ); }
function is_email( string $s ) { return false !== filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
function esc_url_raw( string $url, ?array $protocols = null ): string {
	$url = preg_replace( '/[\x00-\x20]+/', '', $url );
	if ( ! preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $url, $m ) ) {
		return '';
	}
	return in_array( strtolower( $m[1] ), $protocols ?? array( 'http', 'https' ), true ) && preg_match( '#^[a-z]+://[^/]+#i', $url ) ? $url : '';
}
function wp_kses( string $s, array $allowed ): string {
	$tags = '<' . implode( '><', array_keys( $allowed ) ) . '>';
	$s    = strip_tags( $s, $tags );
	return preg_replace( '/<(\w+)\s[^>]*>/', '<$1>', $s );
}
function wp_json_encode( $v, int $flags = 0, int $depth = 512 ) { return json_encode( $v, $flags, $depth ); }
function wp_salt( string $scheme = 'auth' ): string { return 'salt-' . $scheme; }
function wp_remote_request( string $url, array $args = array() ) { return $GLOBALS['wotoyi_http_response'] ?? new WP_Error( 'http_unavailable', 'no network in tests' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }

/* ------------------------------ DB ------------------------------ */

function dbDelta( string $sql ): array {
	global $wpdb;
	$wpdb->create_from_mysql( $sql );
	return array();
}
