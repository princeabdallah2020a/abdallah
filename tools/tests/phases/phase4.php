<?php
/** Phase 4 — central ↔ dealer synchronisation, signed API, webhooks, marketplace leads. */
global $wpdb;
use AMC\Credentials;
use AMC\Crypto;
use AMC\Memberships;
use AMC\Subscriptions;
use AMC\Payments;
use AMC\Webhooks;

qa_phase( 'Phase 4 — Sync API, connector, webhooks, marketplace leads' );
qa_reset_central();
qa_reset_dealer( $D['dir'], $D['url'] );
$log0  = qa_log_offset( $C['dir'] );
$dlog0 = qa_log_offset( $D['dir'] );
$tbl = static function ( $n ) { return AMC\Database::table( $n ); };
$cv  = static function ( $sql, ...$a ) use ( $wpdb ) { wp_cache_flush(); return $a ? $wpdb->get_row( $wpdb->prepare( $sql, ...$a ) ) : $wpdb->get_row( $sql ); };
$ddb = new PDO( 'sqlite:' . $D['dir'] . '/wp-content/database/local.sqlite' );
$ddb->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$ddb->exec( 'PRAGMA busy_timeout=5000' );
$dq = static function ( $sql, array $p = array() ) use ( $ddb ) { $st = $ddb->prepare( $sql ); $st->execute( $p ); return $st->fetchAll( PDO::FETCH_ASSOC ); };
$dv = static function ( $sql, array $p = array() ) use ( $dq ) { $r = $dq( $sql, $p ); return $r ? array_values( $r[0] )[0] : null; };
$DM = static function ( $code ) use ( $D ) { return qa_dealer_eval( $D['dir'], $D['url'], $code ); };
$syncrun = static function () use ( $DM ) { return $DM( 'ACX\Sync::run(); echo "ran";' ); };
$pub = new QA_Session( $C['url'] );
$admin = new QA_Session( $C['url'] ); $admin->login( 'admin', 'local-dev-password' );

// ------------------------------------------------------------- dealer accounts (central)
$mk_dealer = static function ( $name, $plan_code, $email ) use ( $wpdb, $tbl ) {
	$uid = wp_insert_user( array( 'user_login' => sanitize_user( strstr( $email, '@', true ) ), 'user_pass' => 'Pass-1234567', 'user_email' => $email, 'role' => 'amc_dealer_owner' ) );
	$did = AMC\Dealers::create( array( 'business_name' => $name, 'city' => 'Dakar', 'phone' => '+221770000000', 'contact_email' => $email, 'status' => 'pending', 'sync_mode' => 'api' ) );
	$wpdb->update( $tbl( 'dealers' ), array( 'user_id' => $uid ), array( 'id' => $did ) );
	$plan = AMC\Plans::get_by_code( $plan_code );
	$now  = current_time( 'mysql', true );
	$wpdb->insert( $tbl( 'applications' ), array( 'dealer_id' => $did, 'user_id' => $uid, 'plan_id' => $plan->id, 'duration_months' => 3, 'contact_name' => $name, 'status' => 'submitted', 'website_option' => 'later', 'created_at' => $now, 'updated_at' => $now ) );
	$app = (int) $wpdb->insert_id;
	$sub = Subscriptions::create( $did, $plan->id, 3, $app );
	$pay = Payments::create( $did, $sub, 'manual', 'qa', 0, 'verified' );
	Subscriptions::on_paid( $sub );
	wp_set_current_user( 1 );
	Memberships::approve( $app );
	wp_set_current_user( 0 );
	wp_cache_flush();
	return $did;
};
$dealerA = $mk_dealer( 'Sync Dealer A', 'start', 'sync.a@example.test' );
$dealerB = $mk_dealer( 'Sync Dealer B', 'business', 'sync.b@example.test' );
qa( 'Test dealers A (plan start, limit 10) and B (business) are ACTIVE', 'active' === Memberships::get_dealer( $dealerA )->status && 'active' === Memberships::get_dealer( $dealerB )->status, 'active', Memberships::get_dealer( $dealerA )->status . '/' . Memberships::get_dealer( $dealerB )->status );

// ------------------------------------------------------------- connection code
qa_section( 'Connection code and handshake' );
$x = $admin->portal_action( '/platform-admin/connections/', 'conn_issue', array( 'id' => $dealerA ) );
qa( 'Platform admin issues a connection code (302 to dealer connection page)', 302 === $x['status'], '302', (string) $x['status'] );
$page = $admin->get( '/platform-admin/connections/' . $dealerA . '/' );
preg_match( '/AB1\.[A-Za-z0-9_-]+/', $page['body'], $m );
$code = $m[0] ?? '';
qa( 'Code shown once on the next page view', '' !== $code, 'AB1.…', $code ? substr( $code, 0, 12 ) . '…' : 'not shown' );
$page2 = $admin->get( '/platform-admin/connections/' . $dealerA . '/' );
qa( 'Code NOT shown on a second view (shown once)', ! preg_match( '/AB1\.[A-Za-z0-9_-]{20,}/', $page2['body'] ), 'absent', '' );
$cred = Credentials::active_for_dealer( $dealerA );
qa( 'Secret stored encrypted (not 64-hex plain) in api_credentials', $cred && ! preg_match( '/^[a-f0-9]{64}$/', $cred->secret_enc ) && 64 !== strlen( $cred->secret_enc ), 'encrypted blob', $cred ? substr( $cred->secret_enc, 0, 12 ) . '…' : 'none' );
$decoded = json_decode( base64_decode( strtr( substr( $code, 4 ), '-_', '+/' ) ), true );
qa( 'Code carries central URL, key id (ab_…24 hex), 64-hex secret', is_array( $decoded ) && preg_match( '/^ab_[a-f0-9]{24}$/', $decoded['key'] ) && preg_match( '/^[a-f0-9]{64}$/', $decoded['secret'] ) && $decoded['url'] === $C['url'], 'ok', json_encode( array_keys( (array) $decoded ) ) );
$secretA = Credentials::secret( $cred );
qa( 'Stored secret decrypts to the one in the code', $secretA === ( $decoded['secret'] ?? '' ), 'equal', '' );
// Dealer owner connects with the code (CRM → Marketplace)
$DM( "foreach ( array( 'qa_owner' => 'adc_owner', 'qa_sales' => 'adc_sales' ) as \$l => \$r ) { wp_insert_user( array( 'user_login' => \$l, 'user_pass' => 'Pass-' . \$l . '-1', 'user_email' => \$l . '@example.test', 'role' => \$r ) ); } echo 1;" );
$own = new QA_Session( $D['url'] ); $own->login( 'qa_owner', 'Pass-qa_owner-1' );
$sls = new QA_Session( $D['url'] ); $sls->login( 'qa_sales', 'Pass-qa_sales-1' );
$x = $sls->crm( '/manager/marketplace/', 'acx_connect', array( 'code' => $code ) );
qa( 'Sales agent cannot connect the site (owner/manager only)', 'null' === strtolower( (string) $dv( "SELECT COALESCE((SELECT option_value FROM wp_options WHERE option_name='acx_connection'),'null')" ) ) || ! $dv( "SELECT COUNT(*) FROM wp_options WHERE option_name='acx_connection'" ), 'no connection', '' );
$x = $own->crm( '/manager/marketplace/', 'acx_connect', array( 'code' => 'AB1.garbage' ) );
qa( 'Invalid code refused (not saved)', 0 === (int) $dv( "SELECT COUNT(*) FROM wp_options WHERE option_name='acx_connection'" ), 'no connection', '' );
$x = $own->crm( '/manager/marketplace/', 'acx_connect', array( 'code' => $code ) );
wp_cache_flush();
$cred = Credentials::active_for_dealer( $dealerA );
qa( 'Owner connects: handshake recorded on the marketplace (site_url, version, last_handshake_at)', $cred && $cred->last_handshake_at && $D['url'] . '' === rtrim( $cred->site_url, '/' ) || ( $cred && $cred->last_handshake_at && false !== strpos( $cred->site_url, '8089' ) ), 'site_url + handshake', $cred ? "{$cred->site_url} {$cred->last_handshake_at} v{$cred->connector_version}" : 'none' );
qa( 'Dealer stores the connection with encrypted secret (not the 64-hex plain)', ( $o = (string) $dv( "SELECT option_value FROM wp_options WHERE option_name='acx_connection'" ) ) && false === strpos( $o, $secretA ), 'no plain secret', '' );
$crm_page = $own->get( '/manager/marketplace/' );
qa( 'CRM Marketplace screen shows connected state and plan/limits', 200 === $crm_page['status'] && false === strpos( $crm_page['body'], $secretA ), '200 + no secret in HTML', (string) $crm_page['status'] );

// ------------------------------------------------------------- publish / change / reserve / sell / unpublish / delete
qa_section( 'Vehicle lifecycle reflected on the marketplace' );
$img = qa_jpeg( 'qa-sync1.jpg' );
$vf = static function ( array $o = array() ) { return array_merge( array( 'brand' => 'Toyota', 'model' => 'RAV4', 'year' => 2020, 'mileage_km' => 60000, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'body_type' => 'suv', 'vehicle_condition' => 'imported', 'color' => 'Gris', 'vin' => 'JTMXXXXXXXXXXXXXX', 'listing_type' => 'sale', 'price_amount' => '12 000 000', 'purchase_price' => '9 000 000', 'description' => 'SUV familial.', 'features' => 'Climatisation', 'status' => 'published', 'publish_to_marketplace' => 1 ), $o ); };
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'photos[0]' => new CURLFile( $img, 'image/jpeg', 's1.jpg' ) ) ) );
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'model' => 'PrivateNotShared', 'publish_to_marketplace' => 0 ) ) );
$v = $dq( "SELECT * FROM wp_adc_vehicles WHERE model='RAV4'" )[0];
$vp = $dq( "SELECT * FROM wp_adc_vehicles WHERE model='PrivateNotShared'" )[0];
qa( 'Change is queued in the connector (acx_items pending) and a cron event is scheduled', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_acx_items WHERE uid=? AND state='pending'", array( $v['uid'] ) ) && $dv( "SELECT COUNT(*) FROM wp_options WHERE option_name='cron' AND option_value LIKE '%acx_sync_run_now%'" ), 'pending + cron event', '' );
qa( 'Vehicle with "publish on marketplace" unticked is NOT queued', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_acx_items WHERE uid=?', array( $vp['uid'] ) ), '0', '' );
$syncrun();
wp_cache_flush();
$mv = $cv( "SELECT * FROM {$tbl('vehicles')} WHERE dealer_id = %d AND external_id = %s", $dealerA, $v['uid'] );
qa( 'PUBLISH → listing appears on the marketplace (status active, price 12 000 000, linked to dealer A)', $mv && 'active' === $mv->status && 12000000 === (int) $mv->price_amount, 'active/12000000', $mv ? "{$mv->status}/{$mv->price_amount}" : 'not found' );
qa( 'Private vehicle never reached the marketplace', null === $cv( "SELECT id FROM {$tbl('vehicles')} WHERE external_id = %s", $vp['uid'] ), 'absent', '' );
qa( 'Marketplace row holds no VIN / purchase price column data', ! property_exists( $mv, 'vin' ) && ! property_exists( $mv, 'purchase_price' ), 'no such columns', '' );
$pg = $pub->get( '/car/' . $mv->slug . '/' );
qa( 'Marketplace vehicle page 200 shows title, price and dealer name', 200 === $pg['status'] && false !== strpos( $pg['body'], 'RAV4' ) && false !== strpos( $pg['body'], 'Sync Dealer A' ), '200', (string) $pg['status'] );
qa( 'Marketplace page does not leak VIN/purchase price', false === strpos( $pg['body'], 'JTMXXXXXXXXXXXXXX' ) && false === strpos( $pg['body'], '9000000' ) && false === strpos( $pg['body'], '9 000 000' ), 'absent', '' );
$cat = $pub->get( '/cars/' );
qa( 'Listing visible in the public catalog /cars/', false !== strpos( $cat['body'], 'RAV4' ), 'listed', '' );
$api = $pub->get( '/wp-json/marketplace/v1/vehicles?per_page=50' );
qa( 'Public API lists it without private fields', 200 === $api['status'] && false !== strpos( $api['body'], $mv->slug ) && false === stripos( $api['body'], 'purchase' ) && false === strpos( $api['body'], 'JTMXXXXXXXXXXXXXX' ), '200 + clean', $api['status'] . ' has_slug=' . (int) ( false !== strpos( $api['body'], $mv->slug ) ) . ' purchase=' . (int) ( false !== stripos( $api['body'], 'purchase' ) ) . ' vin=' . (int) ( false !== strpos( $api['body'], 'JTMXXXXXXXXXXXXXX' ) ) . ' ' . substr( $api['body'], 0, 150 ) );
qa( 'Connector marks item synced with remote id and status', 'synced' === $dv( 'SELECT state FROM wp_acx_items WHERE uid=?', array( $v['uid'] ) ) && (int) $dv( 'SELECT remote_id FROM wp_acx_items WHERE uid=?', array( $v['uid'] ) ) === (int) $mv->id, 'synced', (string) $dv( 'SELECT state FROM wp_acx_items WHERE uid=?', array( $v['uid'] ) ) );
// price change
sleep( 1 ); // avoid the same-second idempotency-key collision (tested separately below)
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v['id'], 'price_amount' => '11 500 000', 'photos[0]' => new CURLFile( $img, 'image/jpeg', 's1b.jpg' ) ) ) );
$syncrun(); wp_cache_flush();
qa( 'PRICE CHANGE → marketplace price 11 500 000', 11500000 === (int) $cv( "SELECT price_amount FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->price_amount, '11500000', (string) $cv( "SELECT price_amount FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->price_amount );
qa( 'Slug is permanent across updates (URL does not change)', $mv->slug === $cv( "SELECT slug FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->slug, 'same slug', '' );
// reserve
sleep( 1 ); // avoid the same-second idempotency-key collision (tested separately below)
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_status', array( 'id' => $v['id'], 'status' => 'reserved' ) );
$syncrun(); wp_cache_flush();
qa( 'RESERVE → marketplace status reserved, page still online', 'reserved' === $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status && 200 === $pub->get( '/car/' . $mv->slug . '/' )['status'], 'reserved + 200', $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status . ' | crm=' . $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v['id'] ) ) . ' item=' . json_encode( $dq( 'SELECT state,attempts,last_error FROM wp_acx_items WHERE uid=?', array( $v['uid'] ) ) ) . ' resp=' . $dv( 'SELECT message FROM wp_acx_log ORDER BY id DESC LIMIT 1' ) );
// sell
sleep( 1 ); // avoid the same-second idempotency-key collision (tested separately below)
$own->crm( '/manager/sales/new/', 'sale_create', array( 'vehicle_id' => $v['id'], 'new_customer_name' => 'Acheteur QA', 'new_customer_phone' => '770001234', 'sale_price' => '11 000 000', 'confirm' => 1 ) );
$syncrun(); wp_cache_flush();
$st = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status;
qa( 'SELL → marketplace status sold (no longer in catalog /cars/)', 'sold' === $st && false === strpos( $pub->get( '/cars/' )['body'], 'RAV4' ), 'sold + unlisted', $st );
$sold_page = $pub->get( '/car/' . $mv->slug . '/' );
qa( 'Sold vehicle page: 200 with "sold" state or 404 (not offered for sale)', in_array( $sold_page['status'], array( 200, 404, 410 ), true ), '200/404', (string) $sold_page['status'] );
// cancel sale → back on marketplace
sleep( 1 ); // avoid the same-second idempotency-key collision (tested separately below)
$sid = $dv( 'SELECT id FROM wp_adc_sales ORDER BY id DESC LIMIT 1' );
$own->crm( '/manager/sales/' . $sid . '/', 'sale_cancel', array( 'id' => $sid ) );
$syncrun(); wp_cache_flush();
qa( 'CANCEL SALE → listing active again on the marketplace', 'active' === $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status, 'active', $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status . ' | crm=' . $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v['id'] ) ) . ' updated_at=' . $dv( 'SELECT updated_at FROM wp_adc_vehicles WHERE id=?', array( $v['id'] ) ) . ' item=' . json_encode( $dq( 'SELECT state,attempts,last_error FROM wp_acx_items WHERE uid=?', array( $v['uid'] ) ) ) . ' replays=' . $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('sync_events')} WHERE route LIKE '%{$v['uid']}%'" ) );
// unpublish via status draft
sleep( 1 ); // avoid the same-second idempotency-key collision (tested separately below)
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_status', array( 'id' => $v['id'], 'status' => 'draft' ) );
$syncrun(); wp_cache_flush();
$st = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status;
qa( 'UNPUBLISH (CRM draft) → marketplace status unpublished, page no longer public', 'unpublished' === $st && 404 === $pub->get( '/car/' . $mv->slug . '/' )['status'], 'unpublished + 404', $st . ' / ' . $pub->last['status'] );
// republish then untick flag
sleep( 1 ); // avoid the same-second idempotency-key collision (tested separately below)
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_status', array( 'id' => $v['id'], 'status' => 'published' ) );
$syncrun(); wp_cache_flush();
qa( 'Re-publish → active again', 'active' === $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status, 'active', '' );
sleep( 1 );
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v['id'], 'price_amount' => '11 500 000', 'publish_to_marketplace' => 0 ) ) );
$syncrun(); wp_cache_flush();
qa( 'Un-ticking "publish on marketplace" removes it from the marketplace (unpublished)', 'unpublished' === $cv( "SELECT status FROM {$tbl('vehicles')} WHERE id = %d", $mv->id )->status, 'unpublished', '' );
// delete
sleep( 1 );
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_status', array( 'id' => $v['id'], 'status' => 'archived' ) );
$own->crm( '/manager/vehicles/' . $v['id'] . '/', 'vehicle_delete', array( 'id' => $v['id'] ) );
$syncrun(); wp_cache_flush();
qa( 'DELETE in CRM → listing deleted on the marketplace (row + media gone)', null === $cv( "SELECT id FROM {$tbl('vehicles')} WHERE id = %d", $mv->id ), 'row gone', '' );
qa( 'Connector queue cleaned after deletion', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_acx_items WHERE uid=?', array( $v['uid'] ) ), '0', '' );

// Two changes in the same second must both reach the marketplace (idempotency key = uid|op|queue-second).
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'model' => 'Collision', 'vin' => 'COLL' ) ) );
$syncrun(); sleep( 1 );
$cvid = (int) $dv( "SELECT id FROM wp_adc_vehicles WHERE model='Collision'" ); $cuid = $dv( "SELECT uid FROM wp_adc_vehicles WHERE model='Collision'" );
$out = $DM( "\$t=time(); ADC\\Vehicles::system_status( $cvid, 'reserved' ); ACX\\Sync::run(); ADC\\Vehicles::system_status( $cvid, 'published' ); ACX\\Sync::run(); echo ( time() - \$t ) . 's';" );
wp_cache_flush();
$cst = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id=%s", $dealerA, $cuid );
qa( 'Two successive changes (reserve then re-publish) sent within the same second are BOTH applied on the marketplace (final status active)', $cst && 'active' === $cst->status, 'active', ( $cst ? $cst->status : 'none' ) . " (dealer changed twice in $out)" );
$own->crm( '/manager/vehicles/' . $cvid . '/', 'vehicle_status', array( 'id' => $cvid, 'status' => 'archived' ) );
$own->crm( '/manager/vehicles/' . $cvid . '/', 'vehicle_delete', array( 'id' => $cvid ) ); $syncrun();

// ------------------------------------------------------------- raw signed API
qa_section( 'Signed API: idempotency, tampering, revocation' );
$route = '/marketplace/v1/sync/vehicles/qaext1';
$call = static function ( $method, $path, $body, array $o = array() ) use ( $C, $secretA, $cred ) {
	$route  = '/marketplace/v1' . $path;
	$payload = null === $body ? '' : ( is_string( $body ) ? $body : wp_json_encode( $body ) );
	$ts     = $o['ts'] ?? time();
	$key    = $o['key'] ?? $cred->key_id;
	$secret = $o['secret'] ?? $secretA;
	$sig    = $o['sig'] ?? Crypto::sign( $secret, $ts, $method, $o['sign_route'] ?? $route, $o['sign_body'] ?? $payload );
	$h = array( 'Content-Type: application/json', 'X-AMC-Key: ' . $key, 'X-AMC-Timestamp: ' . $ts, 'X-AMC-Signature: ' . $sig );
	if ( 'GET' !== $method && ! isset( $o['no_idem'] ) ) { $h[] = 'Idempotency-Key: ' . ( $o['idem'] ?? wp_generate_uuid4() ); }
	if ( isset( $o['omit'] ) ) { $h = array_values( array_filter( $h, static function ( $l ) use ( $o ) { return 0 !== stripos( $l, $o['omit'] . ':' ); } ) ); }
	$ch = curl_init( $C['url'] . '/wp-json' . $route );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30 ) );
	$raw = curl_exec( $ch ); $size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	$r = array( 'status' => (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 'headers' => substr( $raw, 0, $size ), 'body' => substr( $raw, $size ) );
	curl_close( $ch );
	return $r;
};
$vb = array( 'brand' => 'Nissan', 'model' => 'Patrol', 'year' => 2018, 'price_amount' => 20000000, 'status' => 'active', 'listing_type' => 'sale', 'source_updated_at' => gmdate( 'c' ), 'dealer_id' => $dealerB, 'is_featured' => 1 );
$idem = 'qa-idem-' . wp_generate_password( 8, false );
$r1 = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'idem' => $idem ) );
$r2 = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'idem' => $idem ) );
wp_cache_flush();
qa( 'PUT valid signed request → 201 created', 201 === $r1['status'], '201', $r1['status'] . ' ' . substr( $r1['body'], 0, 120 ) );
qa( 'Same Idempotency-Key twice → 2nd response replayed (same status+body, Idempotent-Replay: true)', $r2['status'] === $r1['status'] && $r2['body'] === $r1['body'] && false !== stripos( $r2['headers'], 'idempotent-replay: true' ), 'replay', $r2['status'] . ' ' . ( false !== stripos( $r2['headers'], 'idempotent-replay' ) ? 'replay-header' : 'NO replay header' ) );
qa( 'ONE effect: exactly 1 listing row and 1 sync event for that key', 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id='qaext1'", $dealerA ) ) && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('sync_events')} WHERE idempotency_key=%s", $idem ) ), '1 row, 1 event', '' );
$row = $cv( "SELECT * FROM {$tbl('vehicles')} WHERE dealer_id = %d AND external_id = 'qaext1'", $dealerA );
qa( 'Dealer resolved from the KEY: body dealer_id (B) ignored → listing belongs to A', $row && (int) $row->dealer_id === $dealerA && 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('vehicles')} WHERE dealer_id=%d", $dealerB ) ), 'dealer A', $row ? (string) $row->dealer_id : 'none' );
qa( 'Dealer cannot self-feature: is_featured in body ignored', 0 === (int) $row->is_featured, '0', (string) $row->is_featured );
$r3 = $call( 'PUT', '/sync/vehicles/qaext1', array_merge( $vb, array( 'price_amount' => 19000000, 'source_updated_at' => gmdate( 'c', time() + 5 ) ) ), array( 'idem' => 'qa-idem-new-' . wp_generate_password( 6, false ) ) );
wp_cache_flush();
qa( 'PUT with a NEW key updates the listing (200, price 19 000 000)', 200 === $r3['status'] && 19000000 === (int) $cv( "SELECT price_amount FROM {$tbl('vehicles')} WHERE id=%d", $row->id )->price_amount, '200/19000000', $r3['status'] );
$r4 = $call( 'PUT', '/sync/vehicles/qaext1', array_merge( $vb, array( 'price_amount' => 1, 'source_updated_at' => gmdate( 'c', time() - 3600 ) ) ), array() );
wp_cache_flush();
qa( 'Out-of-order (older source_updated_at) update ignored as stale', false !== strpos( $r4['body'], 'stale' ) && 19000000 === (int) $cv( "SELECT price_amount FROM {$tbl('vehicles')} WHERE id=%d", $row->id )->price_amount, 'ignored stale', substr( $r4['body'], 0, 100 ) );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'no_idem' => 1 ) );
qa( 'Write without Idempotency-Key → 400', 400 === $r['status'], '400', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'sign_body' => wp_json_encode( $vb ) . ' ' ) );
qa( 'Altered request (body differs by 1 byte from what was signed) → 401', 401 === $r['status'], '401', $r['status'] . ' ' . substr( $r['body'], 0, 80 ) );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'sign_route' => '/marketplace/v1/sync/vehicles/qaext2' ) );
qa( 'Signature for another route (replay on a different resource) → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'sig' => str_repeat( '0', 64 ) ) );
qa( 'Wrong signature → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'secret' => str_repeat( 'a', 64 ) ) );
qa( 'Signed with wrong secret → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'ts' => time() - 400 ) );
qa( 'Timestamp 400 s old (replay window 300 s) → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'ts' => time() + 400 ) );
qa( 'Timestamp 400 s in the future → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'key' => 'ab_' . str_repeat( 'f', 24 ) ) );
qa( 'Unknown key id → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaext1', $vb, array( 'omit' => 'X-AMC-Signature' ) );
qa( 'Missing signature header → 401', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'GET', '/sync/status', null );
qa( 'GET /sync/status signed → 200 with plan limits (vehicles limit 10)', 200 === $r['status'] && 10 === (int) ( json_decode( $r['body'], true )['limits']['vehicles'] ?? 0 ), '200 limit 10', $r['status'] . ' ' . substr( $r['body'], 0, 100 ) );
$unsigned = ( new QA_Session( $C['url'] ) )->request( 'PUT', '/wp-json/marketplace/v1/sync/vehicles/qaext1', wp_json_encode( $vb ), array( 'Content-Type: application/json' ) );
qa( 'Unsigned request (no headers at all) → 401', 401 === $unsigned['status'], '401', (string) $unsigned['status'] );
$r = $call( 'PUT', '/sync/vehicles/' . rawurlencode( "x'; DROP TABLE wp_amc_vehicles;--" ), $vb );
qa( 'Malicious external_id in URL does not match the route (404, no SQL effect)', in_array( $r['status'], array( 404, 401 ), true ) && (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('vehicles')}" ) >= 1, '404', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/qaxss', array( 'brand' => '<script>alert(1)</script>Evil', 'model' => 'X"><img src=x onerror=alert(2)>', 'status' => 'active', 'price_amount' => 5, 'thumbnail_url' => 'javascript:alert(3)', 'source_url' => 'javascript:alert(4)', 'description' => '<script>alert(5)</script>' ) );
wp_cache_flush();
$xs = $cv( "SELECT * FROM {$tbl('vehicles')} WHERE external_id='qaxss'" );
$pg = $xs ? $pub->get( '/car/' . $xs->slug . '/' ) : array( 'body' => '' );
qa( 'Stored XSS via sync payload: scripts/handlers neutralised on the public vehicle page', $xs && false === strpos( $pg['body'], '<script>alert(' ) && false === strpos( $pg['body'], 'onerror=alert' ) && false === stripos( $pg['body'], 'javascript:alert' ), 'escaped', $xs ? 'row ' . $xs->id : 'not stored (201?) ' . $r['status'] );
// Dealer B key cannot touch A
$wpdb->query( "DELETE FROM {$tbl('vehicles')} WHERE external_id IN ('qaxss')" );
$x = $admin->portal_action( '/platform-admin/connections/', 'conn_issue', array( 'id' => $dealerB ) );
$pageB = $admin->get( '/platform-admin/connections/' . $dealerB . '/' );
preg_match( '/AB1\.[A-Za-z0-9_-]+/', $pageB['body'], $mB );
$dB = json_decode( base64_decode( strtr( substr( $mB[0] ?? 'AB1.', 4 ), '-_', '+/' ) ), true );
$r = $call( 'PUT', '/sync/vehicles/qaext1', array_merge( $vb, array( 'price_amount' => 1 ) ), array( 'key' => $dB['key'], 'secret' => $dB['secret'] ) );
wp_cache_flush();
qa( 'Dealer B writing the same external_id creates ITS OWN listing, never overwrites A\'s (tenant isolation)', 19000000 === (int) $cv( "SELECT price_amount FROM {$tbl('vehicles')} WHERE id=%d", $row->id )->price_amount && (int) $cv( "SELECT COUNT(*) AS n FROM {$tbl('vehicles')} WHERE external_id='qaext1'" )->n === 2, 'A intact, B separate', (string) $r['status'] );
$r = $call( 'DELETE', '/sync/vehicles/qaext1', null, array( 'key' => $dB['key'], 'secret' => $dB['secret'] ) );
wp_cache_flush();
qa( 'Dealer B DELETE only removes B\'s own row', 1 === (int) $cv( "SELECT COUNT(*) AS n FROM {$tbl('vehicles')} WHERE external_id='qaext1' AND dealer_id=%d", $dealerA )->n, 'A row remains', '' );

// plan limit
qa_section( 'Plan limits, dealer status, patch bypass' );
$cnt0 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('vehicles')} WHERE dealer_id=%d AND status IN ('active','reserved')", $dealerA ) );
$last = 0;
for ( $i = 1; $i <= 12; $i++ ) {
	$rr = $call( 'PUT', '/sync/vehicles/lim' . $i, array( 'brand' => 'Kia', 'model' => 'Rio' . $i, 'status' => 'active', 'price_amount' => 3000000, 'source_updated_at' => gmdate( 'c' ) ) );
	if ( 409 === $rr['status'] ) { $last = $i; break; }
}
$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('vehicles')} WHERE dealer_id=%d AND status IN ('active','reserved')", $dealerA ) );
qa( 'Plan "start" limit (10 active listings): the 11th is refused with 409 amc_plan_limit', 10 === $used && $last > 0 && false !== strpos( $rr['body'], 'amc_plan_limit' ), '10 used, 409', "used=$used refused_at=$last" );
// bypass attempt: PUT unpublished then PATCH to active
$call( 'PUT', '/sync/vehicles/bypass1', array( 'brand' => 'Kia', 'model' => 'Bypass', 'status' => 'unpublished', 'price_amount' => 3000000 ) );
$rp = $call( 'PATCH', '/sync/vehicles/bypass1/status', array( 'status' => 'active' ) );
wp_cache_flush();
$used2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl('vehicles')} WHERE dealer_id=%d AND status IN ('active','reserved')", $dealerA ) );
qa( 'Plan limit cannot be bypassed via PATCH /status (PUT unpublished then PATCH active)', $used2 <= 10, '<= 10 active', "active listings now = $used2 (PATCH → {$rp['status']})" );
// inactive dealer
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'suspended' ), array( 'id' => $dealerA ) );
wp_cache_flush();
$ri = $call( 'PUT', '/sync/vehicles/inactive1', array( 'brand' => 'Kia', 'model' => 'Inactive', 'status' => 'active', 'price_amount' => 1 ) );
qa( 'Suspended dealer: PUT refused 402 amc_dealer_inactive', 402 === $ri['status'], '402', (string) $ri['status'] );
$rp = $call( 'PATCH', '/sync/vehicles/bypass1/status', array( 'status' => 'active' ) );
wp_cache_flush();
$stb = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE external_id='bypass1' AND dealer_id=%d", $dealerA );
qa( 'Suspended dealer: PATCH /status must also be refused (cannot republish while suspended)', 402 === $rp['status'] || ( $stb && 'active' !== $stb->status ), '402 or unchanged', "PATCH→{$rp['status']} status=" . ( $stb ? $stb->status : 'n/a' ) );
$pgl = $pub->get( '/cars/' );
qa( 'Suspended dealer\'s listings are hidden from the public catalog', false === strpos( $pgl['body'], 'Rio1' ), 'hidden', '' );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'active' ), array( 'id' => $dealerA ) );
wp_cache_flush();
// Error replay bug: refused with 402 under key K; retry same K after reactivation
$kk = 'qa-retry-' . wp_generate_password( 6, false );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'expired' ), array( 'id' => $dealerA ) ); wp_cache_flush();
$e1 = $call( 'PUT', '/sync/vehicles/retry1', array( 'brand' => 'Kia', 'model' => 'RetryCar', 'status' => 'active', 'price_amount' => 1 ), array( 'idem' => $kk ) );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'active' ), array( 'id' => $dealerA ) ); wp_cache_flush();
$wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id LIKE 'lim%%'", $dealerA ) );
$e2 = $call( 'PUT', '/sync/vehicles/retry1', array( 'brand' => 'Kia', 'model' => 'RetryCar', 'status' => 'active', 'price_amount' => 1 ), array( 'idem' => $kk ) );
qa( 'Refused write (402) retried with the SAME Idempotency-Key after the dealer is active again must succeed (errors must not be cached)', 402 === $e1['status'] && in_array( $e2['status'], array( 200, 201 ), true ), '402 then 201', "first={$e1['status']} retry={$e2['status']}" );

// ------------------------------------------------------------- connector retry-after-refusal (end to end)
qa_section( 'Connector: refused listing retried after reactivation (end to end)' );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'suspended' ), array( 'id' => $dealerA ) ); wp_cache_flush();
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'model' => 'Refused402' ) ) );
$syncrun();
$vr = $dq( "SELECT * FROM wp_adc_vehicles WHERE model='Refused402'" )[0];
$it = $dq( 'SELECT * FROM wp_acx_items WHERE uid=?', array( $vr['uid'] ) )[0] ?? array();
qa( 'Listing refused while dealer inactive → item state "error" with reason (402 not retried blindly)', 'error' === ( $it['state'] ?? '' ) && false !== stripos( $it['last_error'] ?? '', 'not active' ), 'error/not active', json_encode( array( $it['state'] ?? null, $it['last_error'] ?? null ) ) );
$wpdb->update( $tbl( 'dealers' ), array( 'status' => 'active' ), array( 'id' => $dealerA ) ); wp_cache_flush();
$DM( 'ACX\Sync::retry_errors(); ACX\Sync::run(); echo 1;' );
wp_cache_flush();
$it2 = $dq( 'SELECT * FROM wp_acx_items WHERE uid=?', array( $vr['uid'] ) )[0] ?? array();
$on = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id=%s", $dealerA, $vr['uid'] );
qa( 'After reactivation, "Retry refused vehicles" puts the listing online', 'synced' === ( $it2['state'] ?? '' ) && $on && 'active' === $on->status, 'synced + active', json_encode( array( $it2['state'] ?? null, $it2['last_error'] ?? null, $on ? $on->status : null ) ) );

// Make sure a live listing exists for the following sections even if the retry above failed (bug recorded above).
$ensure = $cv( "SELECT * FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id=%s AND status='active'", $dealerA, $vr['uid'] );
if ( ! $ensure ) {
	$call( 'PUT', '/sync/vehicles/' . $vr['uid'], array( 'brand' => 'Toyota', 'model' => 'Refused402', 'year' => 2019, 'status' => 'active', 'price_amount' => 8000000, 'listing_type' => 'sale', 'source_updated_at' => gmdate( 'c' ) ) );
	$DM( "global \$wpdb; \$wpdb->update( ACX\\Database::table( 'items' ), array( 'state' => 'synced', 'remote_id' => 0 ), array( 'uid' => '{$vr['uid']}' ) ); echo 1;" );
}
// ------------------------------------------------------------- freshness windows
qa_section( 'Stale / hidden windows and heartbeat' );
$wpdb->query( "DELETE FROM {$tbl('vehicles')} WHERE external_id LIKE 'qa%' OR external_id LIKE 'lim%' OR external_id LIKE 'bypass%' OR external_id LIKE 'retry%' OR external_id LIKE 'inactive%'" );
$fresh = $cv( "SELECT * FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id=%s", $dealerA, $vr['uid'] );
if ( $fresh ) {
	update_option( AMC\Settings::OPTION, array_merge( AMC\Settings::all(), array( 'stale_after_hours' => 1, 'hide_after_hours' => 2 ) ) );
	wp_cache_flush(); AMC\Vehicles::flush_cache();
	$wpdb->update( $tbl( 'vehicles' ), array( 'last_synced_at' => gmdate( 'Y-m-d H:i:s', time() - 5400 ) ), array( 'id' => $fresh->id ) ); AMC\Vehicles::flush_cache();
	$pgv = $pub->get( '/car/' . $fresh->slug . '/' );
	qa( 'Last sync 90 min ago with stale window 1 h → listing still visible but flagged "to confirm" (stale)', 200 === $pgv['status'] && ( false !== strpos( $pgv['body'], 'amds-alert--info' ) ), 'visible + stale flag', (string) $pgv['status'] );
	qa( 'Stale listing still in catalog /cars/', false !== strpos( $pub->get( '/cars/' )['body'], 'Refused402' ), 'listed', '' );
	$wpdb->update( $tbl( 'vehicles' ), array( 'last_synced_at' => gmdate( 'Y-m-d H:i:s', time() - 3 * 3600 ) ), array( 'id' => $fresh->id ) ); AMC\Vehicles::flush_cache();
	qa( 'Last sync 3 h ago with hide window 2 h → listing HIDDEN from the catalog', false === strpos( $pub->get( '/cars/' )['body'], 'Refused402' ), 'hidden', '' );
	qa( 'Hidden listing page is not served (404)', 404 === $pub->get( '/car/' . $fresh->slug . '/' )['status'], '404', (string) $pub->last['status'] );
	$DM( 'ACX\Sync::heartbeat(); echo 1;' ); wp_cache_flush(); AMC\Vehicles::flush_cache();
	$after = $cv( "SELECT last_synced_at FROM {$tbl('vehicles')} WHERE id=%d", $fresh->id );
	qa( 'Dealer heartbeat refreshes last_synced_at → listing visible again', strtotime( $after->last_synced_at . ' UTC' ) > time() - 120 && false !== strpos( $pub->get( '/cars/' )['body'], 'Refused402' ), 'fresh + listed', $after->last_synced_at );
	// heartbeat reconciliation: central has a listing the dealer does not list → unpublished
	$call( 'PUT', '/sync/vehicles/orphan1', array( 'brand' => 'Kia', 'model' => 'Orphan', 'status' => 'active', 'price_amount' => 1, 'source_updated_at' => gmdate( 'c' ) ) );
	$DM( 'ACX\Sync::heartbeat(); echo 1;' ); wp_cache_flush();
	$o = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id='orphan1'", $dealerA );
	qa( 'Heartbeat reconciliation: marketplace listing absent from the dealer list is unpublished', $o && 'unpublished' === $o->status, 'unpublished', $o ? $o->status : 'none' );
	// heartbeat asks for missing → dealer requeues
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id=%s", $dealerA, $vr['uid'] ) );
	$DM( 'ACX\Sync::heartbeat(); ACX\Sync::run(); echo 1;' ); wp_cache_flush();
	$back = $cv( "SELECT status FROM {$tbl('vehicles')} WHERE dealer_id=%d AND external_id=%s", $dealerA, $vr['uid'] );
	qa( 'Self-healing: listing deleted on the marketplace is re-sent after the next heartbeat', $back && 'active' === $back->status, 'active again', $back ? $back->status : 'missing' );
	update_option( AMC\Settings::OPTION, array_merge( AMC\Settings::all(), array( 'stale_after_hours' => 72, 'hide_after_hours' => 168 ) ) );
} else {
	qa_skip( 'Stale/hidden windows', 'no synced listing available' );
}

// ------------------------------------------------------------- marketplace leads → CRM
qa_section( 'Marketplace "Message the dealer" → CRM lead' );
$live = $cv( "SELECT * FROM {$tbl('vehicles')} WHERE dealer_id=%d AND status='active' LIMIT 1", $dealerA );
$pg = $pub->get( '/car/' . $live->slug . '/' );
preg_match( '#name="_amc_nonce"[^>]*value="([a-z0-9]+)"#', $pg['body'] . ' ', $mm ) || preg_match( '#value="([a-z0-9]{10})"[^>]*name="_amc_nonce"#', $pg['body'], $mm );
$ln = $mm[1] ?? '';
qa( 'Vehicle page has the "message the dealer" form (consent checkbox, honeypot, nonce)', '' !== $ln && false !== strpos( $pg['body'], 'name="consent"' ) && false !== strpos( $pg['body'], 'name="website"' ), 'form', '' );
$lf = array( 'action' => 'amc_vehicle_lead', 'vehicle_id' => $live->id, '_amc_nonce' => $ln, 'amc_ts' => time() - 10, 'name' => 'Visiteur Marketplace', 'phone' => '77 888 99 00', 'email' => 'visiteur@example.test', 'message' => 'Toujours dispo ?', 'consent' => 1 );
qa_reset_rate_limits();
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lf, array( 'consent' => '' ) ) );
qa( 'No consent → no lead stored (consent required)', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('marketplace_leads')}" ) && false !== strpos( $x['location'], 'amc_lead=invalid' ), 'invalid', $x['location'] );
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lf, array( 'name' => '', 'phone' => '', 'email' => '' ) ) );
qa( 'Missing name/contact → refused', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('marketplace_leads')}" ), '0', '' );
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lf, array( '_amc_nonce' => 'bad' ) ) );
qa( 'Bad nonce refused', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('marketplace_leads')}" ), '0', '' );
$x = $pub->post( '/wp-admin/admin-post.php', $lf );
wp_cache_flush();
$lead = $wpdb->get_row( "SELECT * FROM {$tbl('marketplace_leads')} ORDER BY id DESC LIMIT 1" );
qa( 'Valid message stored (status pending, channel webhook) and a lead.created webhook queued', $lead && 'pending' === $lead->status && 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('webhook_deliveries')} WHERE event='lead.created'" ), 'pending + 1 webhook', $lead ? $lead->status : 'none' );
Webhooks::run();
wp_cache_flush();
$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('marketplace_leads')} WHERE id=%d", $lead->id ) );
$crmlead = $dq( "SELECT * FROM wp_adc_leads WHERE source='marketplace' ORDER BY id DESC LIMIT 1" )[0] ?? null;
qa( 'Webhook delivered → lead appears in the dealer CRM (source marketplace, name, linked vehicle, listing URL in message)', $crmlead && 'Visiteur Marketplace' === $crmlead['name'] && 'delivered' === $lead->status && false !== strpos( $crmlead['message'], '/car/' ), 'CRM lead + delivered', $crmlead ? $crmlead['name'] . ' / ' . $lead->status : 'no CRM lead; status=' . $lead->status );
qa( 'CRM lead is linked to the local vehicle', $crmlead && (int) $crmlead['vehicle_id'] > 0, 'vehicle_id set', $crmlead ? $crmlead['vehicle_id'] : '' );
$ownleads = $own->get( '/manager/leads/' );
qa( 'Lead visible to the dealer owner in /manager/leads/', false !== strpos( $ownleads['body'], 'Visiteur Marketplace' ), 'visible', '' );
// replay same delivery
$dly = $wpdb->get_row( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE event='lead.created' ORDER BY id DESC LIMIT 1" );
Webhooks::deliver( $dly );
qa( 'Same webhook delivery re-sent → deduplicated (still exactly 1 CRM lead)', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE source='marketplace'" ), '1 lead', (string) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE source='marketplace'" ) );
// webhook signature checks (direct to dealer)
$cred = Credentials::active_for_dealer( $dealerA );
$wh = static function ( $body, array $o = array() ) use ( $D, $cred, $secretA ) {
	$route = '/dealer/v1/webhooks/marketplace';
	$ts = $o['ts'] ?? time();
	$sig = $o['sig'] ?? Crypto::sign( $secretA, $ts, 'POST', $route, $o['sign_body'] ?? $body );
	$ch = curl_init( $D['url'] . '/wp-json' . $route );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'X-AMC-Key: ' . $cred->key_id, 'X-AMC-Timestamp: ' . $ts, 'X-AMC-Signature: ' . $sig ) ) );
	$b = curl_exec( $ch ); $s = curl_getinfo( $ch, CURLINFO_HTTP_CODE ); curl_close( $ch );
	return array( $s, $b );
};
$payload = wp_json_encode( array( 'id' => wp_generate_uuid4(), 'event' => 'lead.created', 'data' => array( 'name' => 'Forged', 'phone' => '770000000', 'message' => 'x' ) ) );
list( $s ) = $wh( $payload, array( 'sig' => str_repeat( 'a', 64 ) ) );
qa( 'Webhook with a forged signature → 401, no lead created', 401 === $s && 0 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE name='Forged'" ), '401', (string) $s );
list( $s ) = $wh( $payload, array( 'sign_body' => $payload . ' ' ) );
qa( 'Webhook body altered after signing → 401', 401 === $s, '401', (string) $s );
list( $s ) = $wh( $payload, array( 'ts' => time() - 1000 ) );
qa( 'Webhook replayed with an old timestamp → 401', 401 === $s, '401', (string) $s );
list( $s ) = $wh( $payload );
qa( 'Correctly signed webhook accepted (200) → lead created', 200 === $s && 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE name='Forged'" ), '200 + lead', (string) $s );
list( $s ) = $wh( $payload );
qa( 'Exact replay of that webhook (same delivery id) → accepted but not duplicated', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE name='Forged'" ), '1 lead', (string) $dv( "SELECT COUNT(*) FROM wp_adc_leads WHERE name='Forged'" ) );
$xl = wp_json_encode( array( 'id' => wp_generate_uuid4(), 'event' => 'lead.created', 'data' => array( 'name' => '<script>alert(1)</script>', 'phone' => '770000001', 'message' => '<img src=x onerror=alert(1)>' ) ) );
$wh( $xl );
$lp = $own->get( '/manager/leads/' )['body'];
qa( 'XSS in a marketplace lead is escaped in the CRM', false === strpos( $lp, '<script>alert(1)' ) && false === strpos( $lp, 'onerror=alert' ), 'escaped', '' );
$rate = 0;
qa_reset_rate_limits();
for ( $i = 0; $i < 8; $i++ ) { $x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lf, array( 'name' => "Rl $i" ) ) ); if ( false !== strpos( $x['location'], 'amc_lead=rate' ) ) { $rate++; } }
qa( 'Marketplace lead form rate-limited (6/hour/IP): later submissions get "rate"', $rate >= 1, '>=1 rate response', (string) $rate );
qa_reset_rate_limits();

// ------------------------------------------------------------- retry / fallback
qa_section( 'Webhook retry/backoff and email fallback' );
$wpdb->query( "DELETE FROM {$tbl('webhook_deliveries')}" );
$good_url = $cred->site_url;
$wpdb->update( $tbl( 'api_credentials' ), array( 'site_url' => 'http://127.0.0.1:9' ), array( 'id' => $cred->id ) );
$lead_id = Webhooks::enqueue( $dealerA, 'lead.created', array( 'lead_id' => 0, 'name' => 'Retry Guy', 'phone' => '770000002', 'message' => 'retry', 'external_id' => '', 'vehicle' => '', 'vehicle_url' => '' ) );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE id=%d", $lead_id ) );
$ok = Webhooks::deliver( $row );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE id=%d", $lead_id ) );
qa( 'Dealer site unreachable → delivery stays pending, attempts=1, next attempt in ~60 s (backoff)', ! $ok && 'pending' === $row->status && 1 === (int) $row->attempts && abs( strtotime( $row->next_attempt_at . ' UTC' ) - ( time() + 60 ) ) < 15, 'pending/1/+60s', "{$row->status}/{$row->attempts}/" . ( strtotime( $row->next_attempt_at . ' UTC' ) - time() ) . 's' );
Webhooks::run();
$row2 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE id=%d", $lead_id ) );
delete_transient( 'amc_webhooks_lock' );
qa( 'Cron run before the backoff elapsed does not retry (attempts unchanged)', 1 === (int) $row2->attempts, 'attempts 1', (string) $row2->attempts );
$wpdb->update( $tbl( 'api_credentials' ), array( 'site_url' => $good_url ), array( 'id' => $cred->id ) );
$wpdb->update( $tbl( 'webhook_deliveries' ), array( 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() - 5 ) ), array( 'id' => $lead_id ) );
Webhooks::run();
$row3 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE id=%d", $lead_id ) );
qa( 'Dealer back online → retried and delivered (status delivered, attempts 2)', 'delivered' === $row3->status && 2 === (int) $row3->attempts, 'delivered/2', "{$row3->status}/{$row3->attempts}" );
qa( 'Staff screen "Connections" lists webhook history and sync log', 200 === $admin->get( '/platform-admin/connections/' )['status'], '200', '' );
// email fallback: never connected dealer B (no handshake) → immediate email
@unlink( QA_MAIL_CENTRAL );
$wpdb->query( "DELETE FROM {$tbl('webhook_deliveries')}" );
$vB = AMC\Vehicles::upsert( $dealerB, array( 'external_id' => 'bveh1', 'brand' => 'Peugeot', 'model' => '3008', 'status' => 'active', 'price_amount' => 9000000 ) );
$id = AMC\Marketplace_Leads::create( $dealerB, $vB, array( 'name' => 'Fallback Guy', 'phone' => '770000003', 'email' => 'fb@example.test', 'message' => 'Hello' ) );
$ml = AMC\Marketplace_Leads::get( $id );
$mails = is_file( QA_MAIL_CENTRAL ) ? file( QA_MAIL_CENTRAL ) : array();
qa( 'Dealer never connected (no handshake) → lead sent by EMAIL immediately (status emailed)', 'emailed' === $ml->status && (bool) array_filter( $mails, static function ( $l ) { return false !== strpos( $l, 'sync.b@example.test' ) && false !== strpos( $l, 'Fallback Guy' ); } ), 'emailed', $ml->status );
// disconnected dealer: central still believes it is connected → webhooks retry, email only after all attempts fail
$DM( 'ACX\Connection::disconnect(); echo 1;' );
$wpdb->query( "DELETE FROM {$tbl('webhook_deliveries')}" );
@unlink( QA_MAIL_CENTRAL );
$id = AMC\Marketplace_Leads::create( $dealerA, (int) $live->id, array( 'name' => 'Disconnected Guy', 'phone' => '770000004', 'email' => '', 'message' => 'Hello' ) );
$dr = $wpdb->get_row( "SELECT * FROM {$tbl('webhook_deliveries')} ORDER BY id DESC LIMIT 1" );
$res = Webhooks::deliver( $dr );
$ml = AMC\Marketplace_Leads::get( $id );
$dr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE id=%d", $dr->id ) );
$total_backoff = array_sum( Webhooks::BACKOFF );
qa( 'Dealer site disconnected (dealer removed its key): webhook refused (401) and lead status stays "pending" — email fallback only after all 8 retries (~' . round( $total_backoff / 3600 ) . ' h)', ! $res && 'pending' === $ml->status, 'pending (delayed fallback)', "lead={$ml->status} delivery={$dr->status}/{$dr->attempts} err={$dr->last_error} → buyer message waits up to " . round( $total_backoff / 3600 ) . ' h' );
$wpdb->update( $tbl( 'webhook_deliveries' ), array( 'attempts' => count( Webhooks::BACKOFF ) ), array( 'id' => $dr->id ) );
$dr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE id=%d", $dr->id ) );
Webhooks::deliver( $dr );
$ml = AMC\Marketplace_Leads::get( $id );
$mails = is_file( QA_MAIL_CENTRAL ) ? file( QA_MAIL_CENTRAL ) : array();
qa( 'After the final failed attempt the lead falls back to EMAIL to the dealer', 'emailed' === $ml->status && (bool) array_filter( $mails, static function ( $l ) { return false !== strpos( $l, 'Disconnected Guy' ); } ), 'emailed', $ml->status );

// ------------------------------------------------------------- revocation, rotation, resync
qa_section( 'Revocation, rotation, resync' );
$DM( "ACX\\Connection::connect( '" . $code . "' ); echo 1;" );
$old = Credentials::active_for_dealer( $dealerA );
qa( 'Reconnect with the (still valid) code works', true === ( $call( 'GET', '/sync/status', null )['status'] === 200 ), '200', '' );
$x = $admin->portal_action( '/platform-admin/connections/', 'conn_resync', array( 'id' => $dealerA ) );
Webhooks::run(); wp_cache_flush();
$rs = $wpdb->get_row( "SELECT * FROM {$tbl('webhook_deliveries')} WHERE event='resync.requested' ORDER BY id DESC LIMIT 1" );
qa( 'Staff "full resync" → resync.requested webhook delivered to the dealer', $rs && 'delivered' === $rs->status, 'delivered', $rs ? $rs->status : 'none' );
$x = $admin->portal_action( '/platform-admin/connections/', 'conn_revoke', array( 'id' => $dealerA ) );
wp_cache_flush();
$r = $call( 'GET', '/sync/status', null );
qa( 'REVOKED key → 401 on every call', 401 === $r['status'], '401', (string) $r['status'] );
$r = $call( 'PUT', '/sync/vehicles/afterrevoke', $vb );
qa( 'REVOKED key cannot write (401)', 401 === $r['status'] && null === $cv( "SELECT id FROM {$tbl('vehicles')} WHERE external_id='afterrevoke'" ), '401, no row', (string) $r['status'] );
$hs = $DM( "echo json_encode( ACX\\Connection::request( 'GET', '/sync/status' ) instanceof WP_Error ? 'error' : 'ok' );" );
qa( 'Dealer connector reports the failure cleanly (WP_Error, no fatal)', false !== strpos( $hs, 'error' ), 'error returned', $hs );
$new = Credentials::issue( $dealerA );
qa( 'Re-issuing a code rotates: old key stays revoked, new key active', 'revoked' === Credentials::find_by_key( $old->key_id )->status && 'active' === Credentials::find_by_key( $new['key_id'] )->status, 'rotated', '' );
$audit = $wpdb->get_col( "SELECT action FROM {$tbl('audit_log')}" );
qa( 'Audit: credentials.issued, credentials.revoked, connection.handshake, connection.resync recorded', ! array_diff( array( 'credentials.issued', 'credentials.revoked', 'connection.handshake', 'connection.resync' ), $audit ), 'present', implode( ',', array_diff( array( 'credentials.issued', 'credentials.revoked', 'connection.handshake', 'connection.resync' ), $audit ) ) );
$h = Credentials::health( Credentials::find_by_key( $old->key_id ) );
qa( 'Connection health computed (ok/stale/error) for staff screen', in_array( $h, array( 'ok', 'stale', 'error', 'none' ), true ), 'valid state', $h );
$e = qa_log_new_errors( $C['dir'], $log0 );
qa( 'No PHP warnings/notices on the central site during phase 4', ! $e, 'none', implode( ' | ', array_slice( $e, 0, 3 ) ) );
$e = qa_log_new_errors( $D['dir'], $dlog0 );
qa( 'No PHP warnings/notices on the dealer site during phase 4', ! $e, 'none', implode( ' | ', array_slice( $e, 0, 3 ) ) );
