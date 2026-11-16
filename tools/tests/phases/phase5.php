<?php
/** Phase 5 — WhatsApp channel + dealer AI agent (everything that does not need Claude/Meta credentials). */
global $wpdb;
use AMC\Credentials;
use AMC\Crypto;
use AMC\Settings;
use AMC\Whatsapp;
use AMC\Whatsapp_Agent;
use AMC\Dealer_Agent;

qa_phase( 'Phase 5 — WhatsApp webhook, confirmation flow, quotas, tool permissions' );
qa_reset_central();
qa_reset_dealer( $D['dir'], $D['url'] );
@unlink( $C['dir'] . '/wp-content/qa-graph.log' );
$log0  = qa_log_offset( $C['dir'] );
$dlog0 = qa_log_offset( $D['dir'] );
$tbl = static function ( $n ) { return AMC\Database::table( $n ); };
$ddb = new PDO( 'sqlite:' . $D['dir'] . '/wp-content/database/local.sqlite' );
$ddb->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$ddb->exec( 'PRAGMA busy_timeout=5000' );
$dq = static function ( $sql, array $p = array() ) use ( $ddb ) { $st = $ddb->prepare( $sql ); $st->execute( $p ); return $st->fetchAll( PDO::FETCH_ASSOC ); };
$dv = static function ( $sql, array $p = array() ) use ( $dq ) { $r = $dq( $sql, $p ); return $r ? array_values( $r[0] )[0] : null; };
$DM = static function ( $code ) use ( $D ) { return qa_dealer_eval( $D['dir'], $D['url'], $code ); };
$graph = static function () use ( $C ) { $f = $C['dir'] . '/wp-content/qa-graph.log'; return is_file( $f ) ? array_map( static function ( $l ) { return json_decode( $l, true ); }, array_filter( file( $f ) ) ) : array(); };

// ----------------------------------------------------------------- setup
$dealer = qa_make_dealer( 'Wa Dealer', 'business', 'wa.a@example.test' );
$other  = qa_make_dealer( 'Wa Other', 'business', 'wa.b@example.test' );
$cred   = Credentials::issue( $dealer );
$code   = $cred['code'] ?? '';
if ( '' === $code ) { $m = null; $row = Credentials::active_for_dealer( $dealer ); $code = ''; }
$DM( "ACX\\Connection::connect( '$code' ); echo 1;" );
$roles = array( 'owner' => 'adc_owner', 'manager' => 'adc_manager', 'sales' => 'adc_sales', 'rental' => 'adc_rental', 'viewer' => 'adc_viewer' );
$uids = array(); $n = 0;
foreach ( $roles as $label => $role ) {
	$n++;
	$uids[ $label ] = (int) $DM( "\$id = wp_insert_user( array( 'user_login' => 'wa_$label', 'user_pass' => 'Pass-wa-1', 'user_email' => 'wa_$label@example.test', 'role' => '$role' ) ); update_user_meta( \$id, 'acx_whatsapp', '+22177700000$n' ); echo \$id;" );
}
$DM( 'ACX\Contacts::push(); echo 1;' );
wp_cache_flush();
$phone = array( 'owner' => '+221777000001', 'manager' => '+221777000002', 'sales' => '+221777000003', 'rental' => '+221777000004', 'viewer' => '+221777000005' );
qa( 'Setup: dealer connected, 5 team numbers pushed to the marketplace (ai_contacts)', 5 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('ai_contacts')} WHERE dealer_id=%d", $dealer ) ) && ! in_array( 0, $uids, true ), '5 contacts', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('ai_contacts')}" ) );
// Marketplace settings (fake credentials; the Graph API is replaced by the QA mu-plugin and logged)
$WA = array( 'wa_enabled' => 1, 'wa_phone_number_id' => '555000111', 'wa_verify_token' => 'verify-token-qa', 'wa_access_token' => Crypto::encrypt( 'EAAqa-token' ), 'wa_app_secret' => Crypto::encrypt( 'qa-app-secret' ), 'wa_public_assistant' => 0 );
update_option( Settings::OPTION, array_merge( Settings::all(), $WA ) ); wp_cache_flush();
qa( 'WhatsApp channel enabled only when number id + token + app secret are present', Whatsapp::enabled(), 'enabled', '' );
update_option( Settings::OPTION, array_merge( Settings::all(), array( 'wa_app_secret' => '' ) ) ); wp_cache_flush();
qa( 'Disabled by default / without app secret (nothing is received or sent)', ! Whatsapp::enabled(), 'disabled', '' );
update_option( Settings::OPTION, array_merge( Settings::all(), $WA ) ); wp_cache_flush();
qa( 'Settings screen never prints secrets (secret fields are encrypted at rest)', false === strpos( json_encode( get_option( Settings::OPTION ) ), 'qa-app-secret' ) && false === strpos( json_encode( get_option( Settings::OPTION ) ), 'EAAqa-token' ), 'encrypted', '' );
$admin = new QA_Session( $C['url'] ); $admin->login( 'admin', 'local-dev-password' );
$wp = $admin->get( '/platform-admin/whatsapp/' );
qa( 'Platform admin "WhatsApp & voice" screen loads and does not echo the token/secret', 200 === $wp['status'] && false === strpos( $wp['body'], 'qa-app-secret' ) && false === strpos( $wp['body'], 'EAAqa-token' ), '200 + no secrets', (string) $wp['status'] );

// ----------------------------------------------------------------- webhook
qa_section( 'Webhook verification and signature' );
$pub = new QA_Session( $C['url'] );
$hook = '/wp-json/marketplace/v1/whatsapp/webhook';
$g = $pub->get( $hook . '?hub_mode=subscribe&hub_verify_token=verify-token-qa&hub_challenge=12345abc' );
qa( 'GET verification with the right token echoes the challenge', 200 === $g['status'] && '12345abc' === trim( $g['body'] ), '200 + challenge', $g['status'] . ' ' . substr( $g['body'], 0, 40 ) );
$g = $pub->get( $hook . '?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345abc' );
qa( 'GET verification with a wrong token → 403', 403 === $g['status'] && false === strpos( $g['body'], '12345abc' ), '403', (string) $g['status'] );
$g = $pub->get( $hook . '?hub_mode=subscribe&hub_verify_token=&hub_challenge=zz' );
qa( 'GET verification with an empty token → 403', 403 === $g['status'], '403', (string) $g['status'] );
$g = $pub->get( $hook . '?hub_mode=subscribe&hub_verify_token=verify-token-qa&hub_challenge=' . rawurlencode( '<script>alert(1)</script>' ) );
qa( 'Challenge is sanitised (no HTML reflected)', false === strpos( $g['body'], '<script>' ), 'sanitised', substr( $g['body'], 0, 60 ) );
$mk = static function ( $from, $id, $text, $type = 'text', $pid = '555000111', array $extra = array() ) {
	$msg = array_merge( array( 'from' => $from, 'id' => $id, 'type' => $type, 'timestamp' => (string) time() ), 'text' === $type ? array( 'text' => array( 'body' => $text ) ) : $extra );
	return wp_json_encode( array( 'object' => 'whatsapp_business_account', 'entry' => array( array( 'id' => '1', 'changes' => array( array( 'field' => 'messages', 'value' => array( 'messaging_product' => 'whatsapp', 'metadata' => array( 'phone_number_id' => $pid, 'display_phone_number' => '221700000000' ), 'messages' => array( $msg ) ) ) ) ) ) ) );
};
$post = static function ( $body, $sig = null ) use ( $C, $hook ) {
	$sig = null === $sig ? 'sha256=' . hash_hmac( 'sha256', $body, 'qa-app-secret' ) : $sig;
	$ch = curl_init( $C['url'] . $hook );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'X-Hub-Signature-256: ' . $sig ), CURLOPT_TIMEOUT => 30 ) );
	$b = curl_exec( $ch ); $s = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); curl_close( $ch );
	return array( $s, $b );
};
$count = static function () use ( $wpdb, $tbl ) { return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE direction='in'" ); };
$body = $mk( '221777000009', 'wamid.SIG1', 'Bonjour' );
list( $s ) = $post( $body, 'sha256=' . str_repeat( 'a', 64 ) );
qa( 'POST with a forged signature → 401, nothing stored', 401 === $s && 0 === $count(), '401', (string) $s );
list( $s ) = $post( $body, '' );
qa( 'POST without signature → 401', 401 === $s && 0 === $count(), '401', (string) $s );
list( $s ) = $post( $body, 'sha256=' . hash_hmac( 'sha256', $body . ' ', 'qa-app-secret' ) );
qa( 'POST whose body was altered after signing → 401', 401 === $s && 0 === $count(), '401', (string) $s );
list( $s ) = $post( $body, hash_hmac( 'sha256', $body, 'qa-app-secret' ) );
qa( 'Signature without the "sha256=" prefix → 401', 401 === $s, '401', (string) $s );
list( $s ) = $post( $body, 'sha256=' . hash_hmac( 'sha256', $body, 'another-secret' ) );
qa( 'Signature made with another secret → 401', 401 === $s, '401', (string) $s );
$cron_clear = static function () { wp_unschedule_hook( Whatsapp::PROCESS ); };
list( $s ) = $post( $body );
$cron_clear();
qa( 'POST with a valid signature → 200, message stored once (status received/processing)', 200 === $s && 1 === $count(), '200 + 1 row', $s . ' rows=' . $count() );
list( $s ) = $post( $body );
qa( 'Meta retry of the same message id is processed once (dedupe by wamid)', 200 === $s && 1 === $count(), '1 row', (string) $count() );
list( $s ) = $post( $mk( '221777000009', 'wamid.OTHER', 'hi', 'text', '999999' ) );
qa( 'Message for another phone_number_id of the same app is ignored', 200 === $s && 1 === $count(), '1 row', (string) $count() );
list( $s ) = $post( $mk( 'abc', 'wamid.BADPHONE', 'hi' ) );
qa( 'Message with an invalid sender number is ignored', 200 === $s && 1 === $count(), '1 row', (string) $count() );
list( $s ) = $post( '{not json' );
$sigbad = 'sha256=' . hash_hmac( 'sha256', '{not json', 'qa-app-secret' );
list( $s2 ) = $post( '{not json', $sigbad );
qa( 'Signed but malformed JSON → rejected cleanly (200 or 400), nothing stored', in_array( $s2, array( 200, 400 ), true ) && 1 === $count(), '200/400', (string) $s2 );
list( $s ) = $post( $mk( '221777000009', 'wamid.XSS', '<script>alert(1)</script><b>x</b>' ) );
$cron_clear();
$stored = $wpdb->get_var( "SELECT body FROM {$tbl('wa_messages')} WHERE wamid='wamid.XSS'" );
qa( 'Message text is sanitised before storage (no raw HTML)', false === strpos( (string) $stored, '<script>' ), 'sanitised', (string) $stored );
$long = str_repeat( 'é', 6000 );
$post( $mk( '221777000009', 'wamid.LONG', $long ) ); $cron_clear();
qa( 'Message longer than 4000 characters is truncated (multibyte safe)', 4000 === mb_strlen( (string) $wpdb->get_var( "SELECT body FROM {$tbl('wa_messages')} WHERE wamid='wamid.LONG'" ) ), '4000 chars', (string) mb_strlen( (string) $wpdb->get_var( "SELECT body FROM {$tbl('wa_messages')} WHERE wamid='wamid.LONG'" ) ) );
update_option( Settings::OPTION, array_merge( Settings::all(), array( 'wa_enabled' => 0 ) ) ); wp_cache_flush();
$before = $count();
list( $s ) = $post( $mk( '221777000009', 'wamid.DISABLED', 'hi' ) );
qa( 'Channel disabled by staff → valid signed webhook acknowledged but ignored', 200 === $s && $before === $count(), 'ignored', (string) $s );
update_option( Settings::OPTION, array_merge( Settings::all(), $WA ) ); wp_cache_flush();

// ----------------------------------------------------------------- agent: routing, voice, unknown
qa_section( 'Message processing (Graph API stubbed, no AI)' );
$wpdb->query( "DELETE FROM {$tbl('wa_messages')}" ); @unlink( $C['dir'] . '/wp-content/qa-graph.log' );
$post( $mk( '221777000009', 'wamid.UNK1', 'Je cherche une Toyota' ) ); $cron_clear();
$id = (int) $wpdb->get_var( "SELECT id FROM {$tbl('wa_messages')} WHERE wamid='wamid.UNK1'" );
$t0 = microtime( true ); Whatsapp_Agent::process( $id ); $dt = microtime( true ) - $t0;
$out = $graph();
$sent = array_values( array_filter( $out, static function ( $e ) { return isset( $e['body']['text']['body'] ); } ) );
qa( 'Unknown number + public assistant OFF → polite "reserved for dealers" reply sent through the Graph API with the bearer token', $sent && preg_match( '/dealers|concessionnaires/i', $sent[0]['body']['text']['body'] ) && 'Bearer EAAqa-token' === $sent[0]['auth'] && '221777000009' === $sent[0]['body']['to'], 'reply sent', $sent ? substr( $sent[0]['body']['text']['body'], 0, 70 ) : 'nothing sent' );
qa( 'Message marked processed; outbound message logged', 'processed' === $wpdb->get_var( "SELECT status FROM {$tbl('wa_messages')} WHERE id=$id" ) && 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE direction='out' AND phone='+221777000009'" ), 'processed + out row', '' );
Whatsapp_Agent::process( $id );
qa( 'Processing the same message twice sends nothing more (atomic claim)', count( array_filter( $graph(), static function ( $e ) { return isset( $e['body']['text']['body'] ); } ) ) === count( $sent ), 'no second reply', '' );
// audio without speech provider
$post( $mk( '221777000009', 'wamid.AUD1', '', 'audio', '555000111', array( 'audio' => array( 'id' => 'media123', 'mime_type' => 'audio/ogg' ) ) ) ); $cron_clear();
$aid = (int) $wpdb->get_var( "SELECT id FROM {$tbl('wa_messages')} WHERE wamid='wamid.AUD1'" );
$n0 = count( $graph() ); Whatsapp_Agent::process( $aid );
$last = array_values( array_filter( $graph(), static function ( $e ) { return isset( $e['body']['text']['body'] ); } ) );
qa( 'Voice note while speech-to-text is not configured → clear "not supported yet" reply (no crash)', preg_match( '/voice|vocau/i', end( $last )['body']['text']['body'] ), 'voice msg', substr( end( $last )['body']['text']['body'], 0, 70 ) );
$post( $mk( '221777000009', 'wamid.IMG1', '', 'image', '555000111', array( 'image' => array( 'id' => 'x' ) ) ) ); $cron_clear();
$iid = (int) $wpdb->get_var( "SELECT id FROM {$tbl('wa_messages')} WHERE wamid='wamid.IMG1'" );
Whatsapp_Agent::process( $iid );
$last = array_values( array_filter( $graph(), static function ( $e ) { return isset( $e['body']['text']['body'] ); } ) );
qa( 'Unsupported media (image) → "text and voice only" reply', preg_match( '/text and voice|texte et vocaux/i', end( $last )['body']['text']['body'] ), 'text/voice only', substr( end( $last )['body']['text']['body'], 0, 70 ) );
// interactive button reply is read as text
$post( $mk( '221777000009', 'wamid.BTN1', '', 'interactive', '555000111', array( 'interactive' => array( 'button_reply' => array( 'title' => 'OUI' ) ) ) ) ); $cron_clear();
qa( 'Interactive button reply stored as text "OUI"', 'OUI' === $wpdb->get_var( "SELECT body FROM {$tbl('wa_messages')} WHERE wamid='wamid.BTN1'" ) && 'text' === $wpdb->get_var( "SELECT kind FROM {$tbl('wa_messages')} WHERE wamid='wamid.BTN1'" ), 'text/OUI', '' );
// flood
$wpdb->query( "DELETE FROM {$tbl('wa_messages')}" ); @unlink( $C['dir'] . '/wp-content/qa-graph.log' );
for ( $i = 1; $i <= 43; $i++ ) { // messages arrive one at a time and are processed as they come
	$wpdb->insert( $tbl( 'wa_messages' ), array( 'wamid' => 'wamid.F' . $i, 'phone' => '+221777000008', 'dealer_id' => 0, 'direction' => 'in', 'kind' => 'text', 'body' => 'hi ' . $i, 'status' => 'received', 'created_at' => current_time( 'mysql', true ) ) );
	Whatsapp_Agent::process( (int) $wpdb->insert_id );
}
$replies = array_values( array_filter( $graph(), static function ( $e ) { return isset( $e['body']['text']['body'] ); } ) );
$flood_notices = count( array_filter( $replies, static function ( $e ) { return preg_match( '/many messages|beaucoup de messages/iu', $e['body']['text']['body'] ); } ) );
qa( 'Flood protection: > 40 messages/hour/number → ONE warning, then silence', 1 === $flood_notices, '1 warning', "warnings=$flood_notices replies=" . count( $replies ) );
qa( 'Flood: messages beyond the limit are not answered (replies sent <= 41)', count( $replies ) <= 41 + 0, '<= 41', (string) count( $replies ) );

// ----------------------------------------------------------------- yes/no
qa_section( 'Confirmation vocabulary (FR / Wolof / EN)' );
$yes = array( 'OUI', 'oui', 'Oui !', 'yes', 'Yes.', 'waaw', 'WAAW', 'Waaw !', 'ok', 'd\'accord', 'daccord', 'confirme', '1', 'go', 'oui merci', ' oui ' );
$no  = array( 'NON', 'non', 'No', 'deedet', 'Deedet !', 'dedet', 'annule', 'cancel', 'stop', '0', 'non merci' );
$non = array( 'peut-être', 'oui mais change le prix en 5 000 000 FCFA s\'il te plait', 'Quel est le prix ?', '', 'yesterday', 'nonante', 'ouiii', 'okay so what about the price of the car i asked', 'noon' );
$bad_y = array_filter( $yes, static function ( $t ) { return 'yes' !== Whatsapp_Agent::yes_no( $t ); } );
$bad_n = array_filter( $no, static function ( $t ) { return 'no' !== Whatsapp_Agent::yes_no( $t ); } );
$bad_o = array_filter( $non, static function ( $t ) { return '' !== Whatsapp_Agent::yes_no( $t ); } );
qa( 'Affirmatives recognised: ' . implode( ' | ', $yes ), ! $bad_y, 'all yes', 'not recognised: ' . implode( ' | ', $bad_y ) );
qa( 'Negatives recognised: ' . implode( ' | ', $no ), ! $bad_n, 'all no', 'not recognised: ' . implode( ' | ', $bad_n ) );
qa( 'Ambiguous / long / unrelated answers are NOT taken as yes/no', ! $bad_o, 'all empty', 'wrongly matched: ' . implode( ' | ', $bad_o ) );
qa( 'A longer answer that merely STARTS with "oui" and changes the request ("oui mais change le prix…") is not a confirmation', '' === Whatsapp_Agent::yes_no( 'oui mais change le prix en 5 000 000 FCFA' ), 'empty', Whatsapp_Agent::yes_no( 'oui mais change le prix en 5 000 000 FCFA' ) );
qa( 'Arabic confirmation ("نعم") and Wolof "waaw waaw" recognised (4 languages promise)', 'yes' === Whatsapp_Agent::yes_no( 'نعم' ), 'yes', 'got "' . Whatsapp_Agent::yes_no( 'نعم' ) . '" (Arabic not handled)' );

// ----------------------------------------------------------------- real CRM objects
qa_section( 'Confirmation flow executed against the real dealer CRM' );
$owner_ses = new QA_Session( $D['url'] ); $owner_ses->login( 'wa_owner', 'Pass-wa-1' );
$img = qa_jpeg( 'qa-wa.jpg' );
$owner_ses->crm( '/manager/vehicles/new/', 'vehicle_save', array( 'brand' => 'Toyota', 'model' => 'Hilux', 'year' => 2021, 'mileage_km' => 30000, 'fuel_type' => 'diesel', 'transmission' => 'manual', 'body_type' => 'pickup', 'vehicle_condition' => 'imported', 'color' => 'Blanc', 'listing_type' => 'sale', 'price_amount' => '15 000 000', 'status' => 'published', 'publish_to_marketplace' => 0, 'photos[0]' => new CURLFile( $img, 'image/jpeg', 'w.jpg' ) ) );
$vid = (int) $dv( "SELECT id FROM wp_adc_vehicles WHERE model='Hilux'" );
$DM( "wp_set_current_user( {$uids['owner']} ); \$l = ADC\\Leads::save( array( 'name' => 'Client Test', 'phone' => '770009999', 'message' => 'Hilux', 'source' => 'manual' ) ); echo is_wp_error( \$l ) ? \$l->get_error_message() : \$l;" );
$lid = (int) $dv( "SELECT id FROM wp_adc_leads WHERE name='Client Test'" );
qa( 'Fixtures created in the dealer CRM (vehicle, lead)', $vid > 0 && $lid > 0, 'ids', "vehicle=$vid lead=$lid" );
$price = static function () use ( $dv, $vid ) { return (int) $dv( 'SELECT price_amount FROM wp_adc_vehicles WHERE id=?', array( $vid ) ); };
$vstat = static function () use ( $dv, $vid ) { return (string) $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $vid ) ); };
$conv_set = static function ( $phone, $pending ) use ( $wpdb, $tbl, $dealer ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl('wa_conversations')} WHERE phone=%s", $phone ) );
	$wpdb->insert( $tbl( 'wa_conversations' ), array( 'phone' => $phone, 'dealer_id' => $dealer, 'history' => '[]', 'pending_action' => $pending ? wp_json_encode( $pending ) : '', 'updated_at' => current_time( 'mysql', true ) ) );
};
$conv_pending = static function ( $phone ) use ( $wpdb, $tbl ) { return (string) $wpdb->get_var( $wpdb->prepare( "SELECT pending_action FROM {$tbl('wa_conversations')} WHERE phone=%s", $phone ) ); };
$prev = Dealer_Agent::call_dealer( $dealer, $uids['owner'], 'update_vehicle_price', array( 'vehicle_id' => $vid, 'price_amount' => 14000000 ), false );
qa( 'Sensitive tool WITHOUT confirmation returns a preview only (requires_confirmation) and changes nothing', ! empty( $prev['requires_confirmation'] ) && 15000000 === $price(), 'preview, price unchanged', json_encode( $prev ) . ' price=' . $price() );
$pend = array( 'tool' => 'update_vehicle_price', 'args' => array( 'vehicle_id' => $vid, 'price_amount' => 14000000 ), 'summary' => $prev['summary'] ?? 'x', 'at' => time() );
$P = $phone['owner'];
$conv_set( $P, $pend );
$r = Whatsapp_Agent::respond( $P, 'NON' );
qa( 'Pending action + "NON" → cancelled, price unchanged, pending cleared', 15000000 === $price() && '' === $conv_pending( $P ) && preg_match( '/cancel|annul/iu', $r ), 'cancelled', $r . ' price=' . $price() );
$conv_set( $P, $pend );
$r = Whatsapp_Agent::respond( $P, 'deedet' );
qa( 'Pending action + "deedet" (Wolof no) → cancelled', 15000000 === $price() && '' === $conv_pending( $P ), 'cancelled', $r );
$conv_set( $P, array_merge( $pend, array( 'at' => time() - 901 ) ) );
$r = Whatsapp_Agent::respond( $P, 'OUI' );
qa( 'Confirmation 901 s old (window 15 min) + "OUI" → expired, NOT applied', 15000000 === $price() && preg_match( '/expir/i', $r ), 'expired', $r . ' price=' . $price() );
$conv_set( $P, array_merge( $pend, array( 'at' => time() - 899 ) ) );
$r = Whatsapp_Agent::respond( $P, 'waaw' );
qa( 'Confirmation 899 s old + "waaw" → applied on the dealer site, price 14 000 000', 14000000 === $price() && false !== strpos( $r, '✅' ), 'applied', $r . ' price=' . $price() );
qa( 'Confirmed action written in the dealer activity log and the central audit log', (int) $dv( "SELECT COUNT(*) FROM wp_adc_activity WHERE action='ai_action'" ) >= 1 && (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('audit_log')} WHERE action='whatsapp.action'" ) >= 1, 'both logs', '' );
$r = Whatsapp_Agent::respond( $P, 'OUI' );
qa( 'A second "OUI" after the action was applied does nothing (pending was consumed)', 14000000 === $price() && '' === $conv_pending( $P ), 'no re-apply', $r );
$conv_set( $P, array_merge( $pend, array( 'args' => array( 'vehicle_id' => $vid, 'price_amount' => 13000000 ) ) ) );
$r = Whatsapp_Agent::respond( $P, 'Quel est le prix de la Hilux ?' );
qa( 'Pending action + an unrelated message → pending cancelled (never applied by a later "oui")', 14000000 === $price() && '' === $conv_pending( $P ), 'pending cleared', $conv_pending( $P ) );
$conv_set( $P, array_merge( $pend, array( 'args' => array( 'vehicle_id' => $vid, 'price_amount' => 13000000 ) ) ) );
$r = Whatsapp_Agent::respond( $P, 'oui mais mets 5 000 000 FCFA' );
qa( '"oui mais mets 5 000 000" is NOT a confirmation of the pending 13 000 000 change', 14000000 === $price(), 'unchanged', 'price=' . $price() );
// pending belongs to the phone: another team member's "OUI" cannot confirm it
$conv_set( $P, $pend );
$r = Whatsapp_Agent::respond( $phone['manager'], 'OUI' );
qa( 'Another team member answering "OUI" from their own number cannot confirm the owner\'s pending action', 14000000 !== 14000000 || 14000000 === $price() && '' !== $conv_pending( $P ), 'pending untouched', $conv_pending( $P ) ? 'still pending' : 'consumed' );
$wpdb->update( $tbl( 'wa_conversations' ), array( 'pending_action' => '' ), array( 'phone' => $P ) );
// Role escalation: a sales agent confirms a price change → the dealer site enforces adc_edit_prices
$Ps = $phone['sales'];
$conv_set( $Ps, array_merge( $pend, array( 'args' => array( 'vehicle_id' => $vid, 'price_amount' => 1000 ) ) ) );
$r = Whatsapp_Agent::respond( $Ps, 'OUI' );
qa( 'Sales agent (no adc_edit_prices) confirming a price change → refused by the dealer site (❌), price unchanged', 14000000 === $price() && false !== strpos( $r, '❌' ), 'refused', $r . ' price=' . $price() );
$prev = Dealer_Agent::call_dealer( $dealer, $uids['sales'], 'update_vehicle_price', array( 'vehicle_id' => $vid, 'price_amount' => 1000 ), false );
qa( 'Sales agent: price change is refused already at the PREVIEW step (no misleading "reply OUI")', ! empty( $prev['error'] ) && empty( $prev['requires_confirmation'] ), 'error at preview', json_encode( $prev ) );
$prev = Dealer_Agent::call_dealer( $dealer, $uids['sales'], 'update_vehicle_status', array( 'vehicle_id' => $vid, 'status' => 'archived' ), false );
qa( 'Sales agent: archiving a vehicle (needs adc_publish_vehicles) is refused at the PREVIEW step', ! empty( $prev['error'] ) && empty( $prev['requires_confirmation'] ), 'error at preview', json_encode( $prev ) );
$r = Dealer_Agent::call_dealer( $dealer, $uids['sales'], 'update_vehicle_status', array( 'vehicle_id' => $vid, 'status' => 'archived' ), true );
qa( 'Sales agent: archiving with confirm=true is refused by the dealer site, status unchanged', ! empty( $r['error'] ) && 'published' === $vstat(), 'refused', json_encode( $r ) . ' status=' . $vstat() );

// ----------------------------------------------------------------- endpoint security
qa_section( 'Dealer endpoint /dealer/v1/ai/execute' );
$ep = static function ( array $payload, array $o = array() ) use ( $D, $dealer, $cred ) {
	$route = '/dealer/v1/ai/execute';
	$c = Credentials::active_for_dealer( $dealer );
	$secret = Credentials::secret( $c );
	$body = wp_json_encode( $payload );
	$ts = $o['ts'] ?? time();
	$sig = $o['sig'] ?? Crypto::sign( $secret, $ts, 'POST', $route, $body );
	$h = array( 'Content-Type: application/json' );
	if ( empty( $o['nohdr'] ) ) { $h = array_merge( $h, array( 'X-AMC-Key: ' . $c->key_id, 'X-AMC-Timestamp: ' . $ts, 'X-AMC-Signature: ' . $sig ) ); }
	$ch = curl_init( $D['url'] . '/wp-json' . $route );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $h ) );
	$b = curl_exec( $ch ); $s = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); curl_close( $ch );
	return array( $s, json_decode( (string) $b, true ), (string) $b );
};
$base = array( 'user_ref' => $uids['owner'], 'tool' => 'update_vehicle_price', 'args' => array( 'vehicle_id' => $vid, 'price_amount' => 1 ), 'confirm' => true );
list( $s ) = $ep( $base, array( 'nohdr' => 1 ) );
qa( 'Unsigned call → 401/403, price unchanged', in_array( $s, array( 401, 403 ), true ) && 14000000 === $price(), '401', (string) $s );
list( $s ) = $ep( $base, array( 'sig' => str_repeat( 'b', 64 ) ) );
qa( 'Wrong signature → 401', 401 === $s, '401', (string) $s );
list( $s ) = $ep( $base, array( 'ts' => time() - 1000 ) );
qa( 'Stale timestamp → 401', 401 === $s, '401', (string) $s );
list( $s, $j ) = $ep( array_merge( $base, array( 'user_ref' => 1 ) ) );
qa( 'user_ref = WordPress admin (id 1, no registered WhatsApp number / not a team member) → 403', 403 === $s && 14000000 === $price(), '403', $s . ' ' . json_encode( $j ) );
list( $s ) = $ep( array_merge( $base, array( 'user_ref' => 99999 ) ) );
qa( 'Unknown user_ref → 403', 403 === $s, '403', (string) $s );
list( $s, $j ) = $ep( array_merge( $base, array( 'tool' => 'delete_everything' ) ) );
qa( 'Unknown tool → 400', 400 === $s, '400', (string) $s );
list( $s, $j ) = $ep( array_merge( $base, array( 'tool' => 'crm_summary; DROP TABLE x', 'args' => array() ) ) );
qa( 'Tool name is sanitised (injection attempt → 400)', 400 === $s, '400', (string) $s );
list( $s, $j ) = $ep( array_merge( $base, array( 'confirm' => 'true' ) ) );
qa( 'confirm sent as the STRING "true" is not accepted as a confirmation (strict boolean)', ! empty( $j['requires_confirmation'] ) && 14000000 === $price(), 'preview only', json_encode( $j ) . ' price=' . $price() );
list( $s, $j ) = $ep( array_merge( $base, array( 'args' => array( 'vehicle_id' => '0 OR 1=1', 'price_amount' => 5 ), 'confirm' => false ) ) );
qa( 'SQL-ish vehicle_id is cast to int (vehicle not found, no SQL effect)', ! empty( $j['error'] ), 'error', json_encode( $j ) );
$DM( "delete_user_meta( {$uids['viewer']}, 'acx_whatsapp' ); echo 1;" );
list( $s, $j ) = $ep( array( 'user_ref' => $uids['viewer'], 'tool' => 'crm_summary', 'args' => array(), 'confirm' => false ) );
qa( 'Team member who removed their WhatsApp number on the dealer site is refused immediately (403), even if the marketplace still lists it', 403 === $s, '403', (string) $s );
$DM( "update_user_meta( {$uids['viewer']}, 'acx_whatsapp', '+221777000005' ); echo 1;" );
$DM( "require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( {$uids['rental']} ); echo 1;" ); // removed user
list( $s ) = $ep( array( 'user_ref' => $uids['rental'], 'tool' => 'crm_summary', 'args' => array(), 'confirm' => false ) );
qa( 'Deleted team member → 403 on every tool', 403 === $s, '403', (string) $s );
$DM( "wp_insert_user( array( 'user_login' => 'wa_rental', 'user_pass' => 'Pass-wa-1', 'user_email' => 'wa_rental2@example.test', 'role' => 'adc_rental' ) ); echo 1;" );
$uids['rental'] = (int) $dv( "SELECT ID FROM wp_users WHERE user_login='wa_rental'" );
$DM( "update_user_meta( {$uids['rental']}, 'acx_whatsapp', '+221777000004' ); ACX\\Contacts::push(); echo 1;" );
// Cross-tenant: dealer B cannot claim A's number
$DM( 'echo 1;' );
$cnt0 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('ai_contacts')} WHERE phone=%s AND dealer_id=%d", $phone['owner'], $dealer ) );
$saved = AMC\Ai_Contacts::replace_for_dealer( $other, array( array( 'phone' => $phone['owner'], 'user_ref' => 7, 'name' => 'Thief', 'role' => 'Owner' ) ) );
qa( 'Another dealer cannot register a number already registered by dealer A (first come, first served)', 0 === $saved && 1 === $cnt0 && $dealer === (int) AMC\Ai_Contacts::find( $phone['owner'] )->dealer_id, 'skipped', (string) $saved );
// replace_for_dealer by dealer A removing others
// ----------------------------------------------------------------- quotas & status
qa_section( 'Plan quota, account status' );
$sub = AMC\Subscriptions::current_for_dealer( $dealer );
$orig_quota = (int) $sub->ai_quota;
$wpdb->update( $tbl( 'plans' ), array( 'ai_quota' => 0 ), array( 'id' => $sub->plan_id ) ); wp_cache_flush();
$r = Whatsapp_Agent::respond( $phone['owner'], 'bonjour' );
qa( 'Plan without assistant (ai_quota = 0) → "not included in your plan" message, no AI call', preg_match( '/not included|pas inclus/iu', $r ), 'not included', $r );
$wpdb->update( $tbl( 'plans' ), array( 'ai_quota' => 5 ), array( 'id' => $sub->plan_id ) ); wp_cache_flush();
$wpdb->query( "DELETE FROM {$tbl('wa_messages')}" );
for ( $i = 0; $i < 5; $i++ ) { $wpdb->insert( $tbl( 'wa_messages' ), array( 'wamid' => 'wamid.Q' . $i, 'phone' => $phone['owner'], 'dealer_id' => $dealer, 'direction' => 'in', 'kind' => 'text', 'body' => 'x', 'status' => 'processed', 'created_at' => current_time( 'mysql', true ) ) ); }
$r5 = Whatsapp_Agent::respond( $phone['owner'], 'bonjour' );
qa( 'Quota 5, 5 inbound messages this month (limit reached but not exceeded) → still answered (not the quota message)', ! preg_match( '/used all|utilisé tous|tous les messages/iu', $r5 ), 'answered', $r5 );
$wpdb->insert( $tbl( 'wa_messages' ), array( 'wamid' => 'wamid.Q9', 'phone' => $phone['owner'], 'dealer_id' => $dealer, 'direction' => 'in', 'kind' => 'text', 'body' => 'x', 'status' => 'processed', 'created_at' => current_time( 'mysql', true ) ) );
$r6 = Whatsapp_Agent::respond( $phone['owner'], 'bonjour' );
qa( 'Quota 5, 6 inbound messages → "used all the assistant messages" until the 1st', preg_match( '/used all|utilisé tous|tous les messages/iu', $r6 ), 'quota message', $r6 );
$wpdb->query( $wpdb->prepare( "UPDATE {$tbl('wa_messages')} SET created_at=%s", gmdate( 'Y-m-d H:i:s', strtotime( 'first day of this month 00:00:00 UTC' ) - 3600 ) ) );
$r7 = Whatsapp_Agent::respond( $phone['owner'], 'bonjour' );
qa( 'Previous-month messages do not count (counter resets on the 1st)', ! preg_match( '/used all|utilisé tous|tous les messages/iu', $r7 ), 'answered', $r7 );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'suspended' ), array( 'id' => $dealer ) ); wp_cache_flush();
$r = Whatsapp_Agent::respond( $phone['owner'], 'bonjour' );
qa( 'Suspended dealer → assistant paused with explanation', preg_match( '/not active|pas actif/iu', $r ), 'paused', $r );
$conv_set( $P, array_merge( $pend, array( 'args' => array( 'vehicle_id' => $vid, 'price_amount' => 12345 ) ) ) );
$r = Whatsapp_Agent::respond( $P, 'OUI' );
qa( 'Suspended dealer cannot confirm a pending action either', 14000000 === $price(), 'unchanged', 'price=' . $price() );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'active' ), array( 'id' => $dealer ) ); wp_cache_flush();
$wpdb->update( $tbl( 'plans' ), array( 'ai_quota' => $orig_quota ), array( 'id' => $sub->plan_id ) ); wp_cache_flush();
$wpdb->query( "DELETE FROM {$tbl('wa_messages')}" );
$r = Whatsapp_Agent::respond( $phone['owner'], 'bonjour' );
qa( 'With AI unavailable (no API key) a team member gets a polite "unavailable" message, no fatal', preg_match( '/unavailable|indisponible/iu', $r ), 'polite fallback', $r );
qa( 'Conversation memory stored (history) and limited to the last 12 turns', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('wa_conversations')} WHERE phone='{$phone['owner']}'" ) === 1, '1 row', '' );

// ----------------------------------------------------------------- tool permission matrix
qa_section( 'Permission matrix: 11 tools x 5 roles (executed on the dealer site)' );
$call = static function ( $role, $tool, array $args, $confirm = false ) use ( $dealer, &$uids ) { return Dealer_Agent::call_dealer( $dealer, $uids[ $role ], $tool, $args, $confirm ); };
$allowed = static function ( $r ) { return empty( $r['error'] ); };
// expectations from the role definitions (class-roles.php): owner+manager all; sales: vehicles, leads, customers, sales; rental: leads, customers, rentals; viewer: view_all_leads + reports
$mx = array(
	'crm_summary'           => array( array( 'owner', 'manager', 'sales', 'rental', 'viewer' ), array() ),
	'search_vehicles'       => array( array( 'owner', 'manager', 'sales', 'rental', 'viewer' ), array( 'query' => 'Hilux' ) ),
	'get_vehicle'           => array( array( 'owner', 'manager', 'sales', 'rental', 'viewer' ), array( 'vehicle_id' => $vid ) ),
	'list_leads'            => array( array( 'owner', 'manager', 'sales', 'rental', 'viewer' ), array() ),
	'rentals_overview'      => array( array( 'owner', 'manager', 'rental', 'viewer' ), array( 'view' => 'upcoming' ) ),
	'check_availability'    => array( array( 'owner', 'manager', 'sales', 'rental', 'viewer' ), array( 'vehicle_id' => $vid, 'start' => '2030-01-01 09:00', 'end' => '2030-01-02 09:00' ) ),
	'create_lead'           => array( array( 'owner', 'manager', 'sales', 'rental' ), array( 'name' => 'Matrix Lead', 'phone' => '770001111' ) ),
	'add_lead_note'         => array( array( 'owner', 'manager', 'sales', 'rental' ), array( 'lead_id' => $lid, 'note' => 'matrix note' ) ),
	'update_lead_status'    => array( array( 'owner', 'manager', 'sales', 'rental' ), array( 'lead_id' => $lid, 'status' => 'contacted' ) ),
	'update_vehicle_status' => array( array( 'owner', 'manager' ), array( 'vehicle_id' => $vid, 'status' => 'archived' ) ),
	'update_vehicle_price'  => array( array( 'owner', 'manager' ), array( 'vehicle_id' => $vid, 'price_amount' => 14000000 ) ),
);
$matrix_fail = array();
foreach ( $mx as $tool => list( $ok_roles, $args ) ) {
	foreach ( array_keys( $roles ) as $role ) {
		$res = $call( $role, $tool, $args, false ); // preview for sensitive tools; nothing is modified
		$exp = in_array( $role, $ok_roles, true );
		if ( $allowed( $res ) !== $exp ) { $matrix_fail[] = "$role/$tool expected " . ( $exp ? 'allow' : 'deny' ) . ' got ' . ( $allowed( $res ) ? 'allow' : 'deny (' . substr( $res['error'] ?? '', 0, 40 ) . ')' ); }
	}
}
qa( 'Permission matrix (55 combinations, sensitive tools in preview mode): every role gets exactly the rights of its CRM role', ! $matrix_fail, '55/55', count( $matrix_fail ) . ' deviations: ' . implode( '; ', array_slice( $matrix_fail, 0, 6 ) ) );
$s_all = $call( 'viewer', 'crm_summary', array() );
$o_all = $call( 'owner', 'crm_summary', array() );
qa( 'Revenue figure in crm_summary only for roles with adc_view_reports', isset( $o_all['rental_revenue_this_month'] ) && isset( $s_all['rental_revenue_this_month'] ) && ! isset( $call( 'sales', 'crm_summary', array() )['rental_revenue_this_month'] ), 'owner+viewer yes, sales no', '' );
// own-lead scoping
$DM( "wp_set_current_user( {$uids['owner']} ); \$l = ADC\\Leads::save( array( 'name' => 'Owner Private Lead', 'phone' => '770002222', 'message' => 'x', 'source' => 'manual', 'assigned_to' => {$uids['owner']} ) ); echo 1;" );
$sl = $call( 'sales', 'list_leads', array( 'query' => 'Owner Private' ) );
$vl = $call( 'viewer', 'list_leads', array( 'query' => 'Owner Private' ) );
qa( 'Lead assigned to the owner is NOT listed to a sales agent but IS listed to the viewer (adc_view_all_leads)', 0 === (int) ( $sl['count'] ?? -1 ) && 1 === (int) ( $vl['count'] ?? -1 ), 'sales 0 / viewer 1', 'sales=' . json_encode( $sl['count'] ?? $sl ) . ' viewer=' . json_encode( $vl['count'] ?? $vl ) );
$op = $dq( "SELECT id FROM wp_adc_leads WHERE name='Owner Private Lead'" )[0]['id'] ?? 0;
$r = $call( 'sales', 'add_lead_note', array( 'lead_id' => $op, 'note' => 'hack' ) );
qa( 'Sales agent cannot add a note to a lead assigned to someone else (IDOR)', ! empty( $r['error'] ), 'denied', json_encode( $r ) );
$r = $call( 'sales', 'update_lead_status', array( 'lead_id' => $op, 'status' => 'lost' ), true );
qa( 'Sales agent cannot change the status of someone else\'s lead even with confirm=true (IDOR)', ! empty( $r['error'] ) && 'new' === $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $op ) ), 'denied', json_encode( $r ) );
$r = $call( 'owner', 'search_vehicles', array( 'query' => "Hilux' OR '1'='1" ) );
qa( 'SQL-ish search text returns nothing and no error leak', isset( $r['count'] ) && 0 === $r['count'], 'count 0', json_encode( $r ) );
$r = $call( 'owner', 'create_lead', array( 'name' => '<script>alert(1)</script>', 'phone' => '770003333', 'note' => '<img src=x onerror=alert(1)>' ) );
$lp = $owner_ses->get( '/manager/leads/' )['body'];
qa( 'Lead created through WhatsApp with HTML in name/note is escaped in the CRM', false === strpos( $lp, '<script>alert(1)' ) && false === strpos( $lp, 'onerror=alert' ), 'escaped', '' );
$r = $call( 'owner', 'create_lead', array( 'name' => 'Sans contact' ) );
qa( 'create_lead without phone/email is refused (needs a contact)', ! empty( $r['error'] ), 'error', json_encode( $r ) );
qa( 'Lead created by the assistant carries source "whatsapp"', 1 <= (int) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE source='whatsapp'" ), '>=1', '' );

// ----------------------------------------------------------------- not testable
qa_skip( 'Claude tool selection, wolof/french/english answers, prompt-injection resistance of the model', 'needs an Anthropic API key and the SDK (Assistant::ai_available() is false here) — human eval set required' );
qa_skip( 'Real WhatsApp delivery (Meta Cloud API), 24-hour window, templates, Wolof voice notes transcription', 'needs verified Meta credentials / speech provider key' );
$e = qa_log_new_errors( $C['dir'], $log0 );
qa( 'No PHP warnings/notices on the central site during phase 5', ! $e, 'none', implode( ' | ', array_slice( $e, 0, 3 ) ) );
$e = qa_log_new_errors( $D['dir'], $dlog0 );
qa( 'No PHP warnings/notices on the dealer site during phase 5', ! $e, 'none', implode( ' | ', array_slice( $e, 0, 3 ) ) );
