<?php
/**
 * Shared helpers for tools/tests/run-phase2-6.php (cases, HTTP sessions,
 * subprocess evaluation on the dealer site). Test code only.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$GLOBALS['QA'] = array(
	'results' => array(),
	'phase'   => '',
	'pass'    => 0,
	'fail'    => 0,
	'skip'    => 0,
);

function qa_phase( $name ) {
	$GLOBALS['QA']['phase'] = $name;
	echo "\n######## $name ########\n";
}

function qa_section( $title ) {
	$GLOBALS['QA']['section'] = $title;
	echo "\n== $title ==\n";
}

/** Record a case. $expected/$actual are free text shown in the report. */
function qa( $case, $ok, $expected = '', $actual = '' ) {
	$q = &$GLOBALS['QA'];
	$q[ $ok ? 'pass' : 'fail' ]++;
	$q['results'][] = array( 'phase' => $q['phase'], 'section' => $q['section'] ?? '', 'case' => $case, 'status' => $ok ? 'PASS' : 'FAIL', 'expected' => $expected, 'actual' => $actual );
	echo ( $ok ? '  ok    ' : '  FAIL  ' ) . $case . ( $ok || '' === $actual ? '' : ' — ' . substr( (string) $actual, 0, 300 ) ) . "\n";
	return $ok;
}

/** Case that cannot be run here (needs credentials, native speakers, etc.). */
function qa_skip( $case, $reason ) {
	$q = &$GLOBALS['QA'];
	$q['skip']++;
	$q['results'][] = array( 'phase' => $q['phase'], 'section' => $q['section'] ?? '', 'case' => $case, 'status' => 'SKIP', 'expected' => '', 'actual' => $reason );
	echo "  skip  $case — $reason\n";
}

function qa_summary( $json_path = '' ) {
	$q = $GLOBALS['QA'];
	echo "\n{$q['pass']} passed, {$q['fail']} failed, {$q['skip']} not testable here\n";
	if ( $json_path ) {
		file_put_contents( $json_path, json_encode( $q['results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}
	return $q['fail'];
}

/** HTTP session with its own cookie jar. */
class QA_Session {
	public $jar;
	public $base;
	public $last = array();

	public function __construct( $base ) {
		$this->base = rtrim( $base, '/' );
		$this->jar  = tempnam( sys_get_temp_dir(), 'qa-jar' );
	}

	public function request( $method, $url, $data = null, array $headers = array(), $follow = false ) {
		if ( 0 === strpos( $url, '/' ) ) {
			$url = $this->base . $url;
		}
		$ch   = curl_init( $url );
		$opts = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => $follow,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_COOKIEJAR      => $this->jar,
			CURLOPT_COOKIEFILE     => $this->jar,
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_HTTPHEADER     => $headers,
		);
		if ( null !== $data ) {
			$opts[ CURLOPT_POSTFIELDS ] = $data;
		}
		curl_setopt_array( $ch, $opts );
		$raw  = curl_exec( $ch );
		$size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$res  = array(
			'status'   => (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
			'headers'  => substr( (string) $raw, 0, $size ),
			'body'     => substr( (string) $raw, $size ),
			'location' => (string) curl_getinfo( $ch, CURLINFO_REDIRECT_URL ),
			'url'      => (string) curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL ),
		);
		curl_close( $ch );
		$this->last = $res;
		return $res;
	}

	public function get( $url, $follow = false ) {
		return $this->request( 'GET', $url, null, array(), $follow );
	}

	/** $data may contain CURLFile values (multipart). */
	public function post( $url, array $data, $follow = false ) {
		$has_file = false;
		foreach ( $data as $v ) {
			if ( $v instanceof CURLFile ) {
				$has_file = true;
			}
		}
		return $this->request( 'POST', $url, $has_file ? $data : http_build_query( $data ), array(), $follow );
	}

	public function login( $user, $pass ) {
		$this->get( '/wp-login.php' );
		$r = $this->post( '/wp-login.php', array( 'log' => $user, 'pwd' => $pass, 'wp-submit' => 'Log In', 'testcookie' => 1 ) );
		return 302 === $r['status'] && false !== strpos( $r['headers'], 'wordpress_logged_in' );
	}

	/** Find the nonce of the form that carries amc_action=$action. */
	public function action_nonce( $html, $action ) {
		if ( preg_match_all( '#<form\b.*?</form>#s', $html, $forms ) ) {
			foreach ( $forms[0] as $form ) {
				if ( preg_match( '#name="amc_action" value="' . preg_quote( $action, '#' ) . '"#', $form ) && preg_match( '#name="_amc_nonce"[^>]*value="([a-z0-9]+)"#', $form . ' ', $m ) ) {
					return $m[1];
				}
				if ( preg_match( '#name="amc_action" value="' . preg_quote( $action, '#' ) . '"#', $form ) && preg_match( '#value="([a-z0-9]{10})"[^>]*name="_amc_nonce"#', $form, $m ) ) {
					return $m[1];
				}
			}
		}
		return '';
	}

	/** Dealer CRM action (adc_action / _adc_nonce); nonce minted. $page is any /manager/ URL. */
	public function crm( $page_url, $action, array $fields = array(), $follow = false ) {
		$nonce = $this->mint( 'adc_' . $action );
		return $this->post( $page_url, array_merge( array( 'adc_action' => $action, '_adc_nonce' => $nonce ), $fields ), $follow );
	}

	/** Nonce for an action, minted by the QA mu-plugin (qa-env only). */
	public function mint( $full_action ) {
		return trim( $this->get( '/?qa_nonce=' . rawurlencode( $full_action ) )['body'] );
	}

	/** POST a portal action; the nonce is scraped from the page when the form exists, else minted. */
	public function portal_action( $page_url, $action, array $fields = array(), $nonce_page_html = null ) {
		$html  = null === $nonce_page_html ? $this->get( $page_url )['body'] : $nonce_page_html;
		$nonce = $this->action_nonce( $html, $action );
		if ( '' === $nonce ) {
			$nonce = $this->mint( 'amc_' . $action );
		}
		$data  = array_merge( array( 'amc_action' => $action, '_amc_nonce' => $nonce ), $fields );
		return $this->post( $page_url, $data );
	}
}

/** Evaluate PHP on the dealer site in a subprocess; returns stdout. */
function qa_dealer_eval( $dealer_dir, $dealer_url, $code ) {
	$helper = __DIR__ . '/wp-eval.php';
	$cmd    = 'php ' . escapeshellarg( $helper ) . ' ' . escapeshellarg( $dealer_dir ) . ' ' . escapeshellarg( $dealer_url ) . ' ' . escapeshellarg( $code ) . ' 2>&1';
	return trim( (string) shell_exec( $cmd ) );
}

/** Reset hit counters used by the rate limiter so tests do not interfere. */
function qa_reset_rate_limits() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_amc\_rl%' OR option_name LIKE '\_transient\_timeout\_amc\_rl%'" ); // phpcs:ignore
}

function qa_tmp_file( $name, $content ) {
	$path = sys_get_temp_dir() . '/' . $name;
	file_put_contents( $path, $content );
	return $path;
}

/** Remove everything earlier QA runs created on the central site (keeps demo data and the admin). */
function qa_reset_central() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}amc_dealers WHERE is_demo = 0" ); // phpcs:ignore
	if ( $ids ) {
		$in = implode( ',', array_map( 'intval', $ids ) );
		foreach ( array( 'vehicles', 'applications', 'subscriptions', 'payments', 'documents', 'provisioning_jobs', 'tickets', 'api_credentials', 'sync_events', 'webhook_deliveries', 'marketplace_leads', 'ai_contacts' ) as $t ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}amc_$t WHERE dealer_id IN ($in)" ); // phpcs:ignore
		}
		$wpdb->query( "DELETE FROM {$wpdb->prefix}amc_dealers WHERE id IN ($in)" ); // phpcs:ignore
	}
	foreach ( array( 'ticket_messages', 'audit_log', 'wa_conversations', 'wa_messages', 'call_logs', 'inquiries' ) as $t ) {
		$wpdb->query( "DELETE FROM {$wpdb->prefix}amc_$t" ); // phpcs:ignore
	}
	foreach ( get_users( array( 'search' => '*@example.test', 'search_columns' => array( 'user_email' ), 'fields' => 'ID', 'exclude' => array( 1 ) ) ) as $uid ) {
		wp_delete_user( (int) $uid, 1 ); // Content (if any) goes to the admin; the admin account itself is never deleted.
	}
	delete_option( 'amc_settings' ); // channels (WhatsApp, voice), freshness windows… back to defaults
	@unlink( WP_CONTENT_DIR . '/qa-mail.log' ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%amc\_%'" ); // phpcs:ignore
	wp_cache_flush();
}

function qa_log_offset( $site_dir ) {
	$f = $site_dir . '/wp-content/debug.log';
	return is_file( $f ) ? filesize( $f ) : 0;
}

/** New PHP warnings/notices/fatals in debug.log since $offset (WordPress.org update-check noise excluded: sandbox has no internet). */
function qa_log_new_errors( $site_dir, $offset ) {
	$f = $site_dir . '/wp-content/debug.log';
	if ( ! is_file( $f ) ) {
		return array();
	}
	$fh = fopen( $f, 'r' );
	fseek( $fh, $offset );
	$txt = (string) stream_get_contents( $fh );
	fclose( $fh );
	$out = array();
	foreach ( explode( "\n", $txt ) as $line ) {
		if ( '' !== trim( $line ) && preg_match( '/PHP (Warning|Notice|Deprecated|Fatal|Parse)/', $line ) && ! preg_match( '/wp_version_check|wp_update_plugins|wp_update_themes|WordPress\.org|api\.wordpress/', $line ) ) {
			$out[] = substr( $line, 0, 260 );
		}
	}
	return $out;
}


/** Small valid JPEG written to the temp dir (GD). */
function qa_jpeg( $name, $w = 640, $h = 400, $rgb = array( 200, 60, 30 ) ) {
	$im = imagecreatetruecolor( $w, $h );
	imagefilledrectangle( $im, 0, 0, $w, $h, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
	$path = sys_get_temp_dir() . '/' . $name;
	imagejpeg( $im, $path, 80 );
	imagedestroy( $im );
	return $path;
}

/** Wipe CRM + connector data and non-admin users on the dealer site (subprocess). */
function qa_reset_dealer( $dir, $url ) {
	$code = <<<'PHP'
global $wpdb;
foreach ( $wpdb->get_col( "SELECT name FROM sqlite_master WHERE type='table'" ) as $t ) {
	if ( 0 === strpos( $t, $wpdb->prefix . 'adc_' ) || 0 === strpos( $t, $wpdb->prefix . 'acx_' ) ) {
		$wpdb->query( "DELETE FROM $t" );
	}
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( get_users( array( 'exclude' => array( 1 ), 'fields' => 'ID' ) ) as $u ) {
	wp_delete_user( (int) $u, 1 );
}
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" ) as $o ) {
	if ( preg_match( '/^_transient(_timeout)?_(adc|acx)/', $o ) ) {
		delete_option( $o );
	}
}
foreach ( array( 'adc_settings', 'acx_connection', 'acx_remote' ) as $o ) {
	delete_option( $o );
}
update_option( 'blogname', 'Dealer Test' );
@unlink( WP_CONTENT_DIR . '/qa-mail.log' );
wp_cache_flush();
echo 'reset';
PHP;
	return qa_dealer_eval( $dir, $url, $code );
}

/** Create an ACTIVE dealer (user + dealer + paid subscription) on a plan; returns dealer id. Central WP must be loaded. */
function qa_make_dealer( $name, $plan_code, $email ) {
	global $wpdb;
	$tbl = static function ( $n ) { return AMC\Database::table( $n ); };
	$uid = wp_insert_user( array( 'user_login' => sanitize_user( strstr( $email, '@', true ) ), 'user_pass' => 'Pass-1234567', 'user_email' => $email, 'role' => 'amc_dealer_owner' ) );
	$did = AMC\Dealers::create( array( 'business_name' => $name, 'city' => 'Dakar', 'phone' => '+221770000000', 'contact_email' => $email, 'status' => 'pending', 'sync_mode' => 'api' ) );
	$wpdb->update( $tbl( 'dealers' ), array( 'user_id' => $uid ), array( 'id' => $did ) );
	$plan = AMC\Plans::get_by_code( $plan_code );
	$now  = current_time( 'mysql', true );
	$wpdb->insert( $tbl( 'applications' ), array( 'dealer_id' => $did, 'user_id' => $uid, 'plan_id' => $plan->id, 'duration_months' => 3, 'contact_name' => $name, 'status' => 'submitted', 'website_option' => 'later', 'created_at' => $now, 'updated_at' => $now ) );
	$app = (int) $wpdb->insert_id;
	$sub = AMC\Subscriptions::create( $did, $plan->id, 3, $app );
	AMC\Payments::create( $did, $sub, 'manual', 'qa', 0, 'verified' );
	AMC\Subscriptions::on_paid( $sub );
	wp_set_current_user( 1 );
	AMC\Memberships::approve( $app );
	wp_set_current_user( 0 );
	wp_cache_flush();
	return $did;
}
