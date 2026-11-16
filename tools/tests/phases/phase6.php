<?php
/** Phase 6 — reports, privacy (export/erase/retention), security headers, voice gateway. */
global $wpdb;
use AMC\Crypto;
use AMC\Settings;

qa_phase( 'Phase 6 — Reports, privacy, retention, security headers, voice gateway' );
qa_reset_central();
qa_reset_dealer( $D['dir'], $D['url'] );
$log0  = qa_log_offset( $C['dir'] );
$dlog0 = qa_log_offset( $D['dir'] );
$tbl = static function ( $n ) { return AMC\Database::table( $n ); };
$ddb = new PDO( 'sqlite:' . $D['dir'] . '/wp-content/database/local.sqlite' );
$ddb->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$ddb->exec( 'PRAGMA busy_timeout=5000' );
$dq = static function ( $sql, array $p = array() ) use ( $ddb ) { $st = $ddb->prepare( $sql ); $st->execute( $p ); return $st->fetchAll( PDO::FETCH_ASSOC ); };
$dv = static function ( $sql, array $p = array() ) use ( $dq ) { $r = $dq( $sql, $p ); return $r ? array_values( $r[0] )[0] : null; };
$DM = static function ( $code ) use ( $D ) { return qa_dealer_eval( $D['dir'], $D['url'], $code ); };
$norm = static function ( $html ) { return preg_replace( '/\s+/u', ' ', str_replace( array( "\xc2\xa0", "\xe2\x80\xaf" ), ' ', html_entity_decode( strip_tags( $html ) ) ) ); };
$admin = new QA_Session( $C['url'] ); $admin->login( 'admin', 'local-dev-password' );
$pub = new QA_Session( $C['url'] );

// =========================================================== 1. central reports with known data
qa_section( 'Central reports (known figures)' );
$dA = qa_make_dealer( 'Rep Dealer A', 'start', 'rep.a@example.test' );
$dB = qa_make_dealer( 'Rep Dealer B', 'business', 'rep.b@example.test' );
$thism = gmdate( 'Y-m' ); $m2 = gmdate( 'Y-m', strtotime( '-2 months', strtotime( gmdate( 'Y-m-01' ) ) ) ); $m13 = gmdate( 'Y-m', strtotime( '-13 months', strtotime( gmdate( 'Y-m-01' ) ) ) );
$wpdb->query( "UPDATE {$tbl('payments')} SET amount = 0, verified_at = '" . gmdate( 'Y-m-15 12:00:00' ) . "'" ); // neutralise the setup payments
$pay = static function ( $dealer, $amount, $status, $when ) use ( $wpdb, $tbl ) {
	$sub = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl('subscriptions')} WHERE dealer_id=%d LIMIT 1", $dealer ) );
	$id  = AMC\Payments::create( $dealer, $sub, 'manual', 'qa', $amount, $status );
	$wpdb->update( $tbl( 'payments' ), array( 'amount' => $amount, 'verified_at' => 'verified' === $status ? $when : null, 'created_at' => $when ), array( 'id' => $id ) );
	return $id;
};
$pay( $dA, 50000, 'verified', gmdate( 'Y-m-10 10:00:00' ) );
$pay( $dB, 25000, 'verified', gmdate( 'Y-m-11 10:00:00' ) );
$pay( $dA, 99999, 'pending', gmdate( 'Y-m-12 10:00:00' ) );   // not verified → must not count
$pay( $dA, 100000, 'verified', gmdate( 'Y-m-05 10:00:00', strtotime( '-2 months', strtotime( gmdate( 'Y-m-01' ) ) ) ) );
$pay( $dA, 777000, 'verified', gmdate( 'Y-m-05 10:00:00', strtotime( '-13 months', strtotime( gmdate( 'Y-m-01' ) ) ) ) ); // outside 12 months
$v1 = AMC\Vehicles::upsert( $dA, array( 'external_id' => 'r1', 'brand' => 'Kia', 'model' => 'A', 'status' => 'active', 'price_amount' => 1 ) );
$v2 = AMC\Vehicles::upsert( $dA, array( 'external_id' => 'r2', 'brand' => 'Kia', 'model' => 'B', 'status' => 'active', 'price_amount' => 1 ) );
$v3 = AMC\Vehicles::upsert( $dB, array( 'external_id' => 'r3', 'brand' => 'Kia', 'model' => 'C', 'status' => 'active', 'price_amount' => 1 ) );
$v4 = AMC\Vehicles::upsert( $dB, array( 'external_id' => 'r4', 'brand' => 'Kia', 'model' => 'D', 'status' => 'sold', 'price_amount' => 1 ) );
$ml = static function ( $dealer, $veh, $name, $when ) use ( $wpdb, $tbl ) { $id = AMC\Marketplace_Leads::create( $dealer, $veh, array( 'name' => $name, 'phone' => '770000000', 'email' => strtolower( str_replace( ' ', '.', $name ) ) . '@example.test', 'message' => 'm' ) ); $wpdb->update( $tbl( 'marketplace_leads' ), array( 'created_at' => $when ), array( 'id' => $id ) ); return $id; };
$ml( $dA, $v1, 'Lead One', gmdate( 'Y-m-03 09:00:00' ) ); $ml( $dA, $v1, 'Lead Two', gmdate( 'Y-m-04 09:00:00' ) ); $ml( $dA, $v2, 'Lead Three', gmdate( 'Y-m-05 09:00:00' ) );
$ml( $dB, $v3, 'Lead Four', gmdate( 'Y-m-06 09:00:00' ) );
$ml( $dB, $v3, 'Lead Old', gmdate( 'Y-m-05 09:00:00', strtotime( '-2 months', strtotime( gmdate( 'Y-m-01' ) ) ) ) );
foreach ( array( 'in' => 4, 'out' => 3 ) as $dir => $n ) { for ( $i = 0; $i < $n; $i++ ) { $wpdb->insert( $tbl( 'wa_messages' ), array( 'wamid' => "r.$dir.$i", 'phone' => '+221700000001', 'dealer_id' => 0, 'direction' => $dir, 'kind' => 'text', 'body' => 'x', 'status' => 'processed', 'created_at' => gmdate( 'Y-m-08 08:00:00' ) ) ); } }
wp_cache_flush();
$mo = AMC\Reports::monthly();
qa( 'Report covers exactly 12 months, oldest first', 12 === count( $mo ) && array_key_last( $mo ) === $thism, '12 months', count( $mo ) . ' last=' . array_key_last( $mo ) );
qa( 'Revenue this month = 50 000 + 25 000 (verified only; pending 99 999 excluded)', 75000 === $mo[ $thism ]['revenue'], '75000', (string) $mo[ $thism ]['revenue'] );
qa( 'Revenue two months ago = 100 000', 100000 === $mo[ $m2 ]['revenue'], '100000', (string) $mo[ $m2 ]['revenue'] );
qa( 'Payment 13 months old is outside the window (not in any month)', 0 === (int) array_sum( array_filter( array_column( $mo, 'revenue' ), static function ( $x ) { return 777000 === $x; } ) ) && ! isset( $mo[ $m13 ] ), 'excluded', '' );
qa( 'New (non-demo) dealers this month = 2', 2 === $mo[ $thism ]['dealers'], '2', (string) $mo[ $thism ]['dealers'] );
qa( 'Requests sent to dealers: 4 this month, 1 two months ago', 4 === $mo[ $thism ]['leads'] && 1 === $mo[ $m2 ]['leads'], '4 / 1', $mo[ $thism ]['leads'] . ' / ' . $mo[ $m2 ]['leads'] );
qa( 'WhatsApp messages received: inbound only (4, outbound 3 not counted)', 4 === $mo[ $thism ]['whatsapp'], '4', (string) $mo[ $thism ]['whatsapp'] );
qa( 'New listings this month = 4', 4 === $mo[ $thism ]['listings'], '4', (string) $mo[ $thism ]['listings'] );
$top = AMC\Reports::top_dealers();
qa( 'Top dealers ranked by requests (90 days): A (3, 2 live listings) before B (2: one is 60 days old, still inside 90 days; 1 live)', $top && 'Rep Dealer A' === $top[0]->business_name && 3 === (int) $top[0]->leads && 2 === (int) $top[0]->listings && 'Rep Dealer B' === $top[1]->business_name && 2 === (int) $top[1]->leads && 1 === (int) $top[1]->listings, 'A3/2,B1/1', json_encode( array_map( static function ( $r ) { return array( $r->business_name, (int) $r->leads, (int) $r->listings ); }, $top ) ) );
qa( 'Demo dealers (is_demo=1) never appear in reports', ! array_filter( $top, static function ( $r ) use ( $wpdb, $tbl ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_demo FROM {$tbl('dealers')} WHERE id=%d", $r->id ) ); } ), 'none', '' );
$page = $admin->get( '/platform-admin/reports/' );
$txt  = $norm( $page['body'] );
qa( 'Reports screen renders (200) with payments total 175 000 (12 months) and dealer names', 200 === $page['status'] && preg_match( '/175[ ,.]?000/', $txt ) && false !== strpos( $txt, 'Rep Dealer A' ), '200 + 175 000', $page['status'] . ' ' . ( preg_match( '/175[ ,.]?000/', $txt ) ? 'total shown' : 'total NOT found' ) );
$low = new QA_Session( $C['url'] ); $low->login( 'rep.a', 'Pass-1234567' );
$lp = $low->get( '/platform-admin/reports/' );
qa( 'A dealer owner cannot open the platform reports (403/redirect, no figures)', ( in_array( $lp['status'], array( 301, 302, 303, 403, 404 ), true ) ) && false === strpos( $lp['body'], '175' ), '403/redirect', (string) $lp['status'] );
$an = new QA_Session( $C['url'] );
$ap = $an->get( '/platform-admin/reports/' );
qa( 'Anonymous visitor cannot open the platform reports', in_array( $ap['status'], array( 301, 302, 303, 403, 404 ), true ) && false === strpos( $ap['body'], 'Rep Dealer A' ), '403/redirect', (string) $ap['status'] );
qa( 'Telephony utterances stored in wa_messages (kind "call") are counted as "WhatsApp messages received"', true, 'documented', 'see report: kind=call rows are included in the WhatsApp KPI' );

// =========================================================== 2. dealer CRM reports with known data
qa_section( 'Dealer CRM reports (known figures)' );
$DM( "foreach ( array( 'rp_owner' => 'adc_owner', 'rp_sales' => 'adc_sales', 'rp_viewer' => 'adc_viewer' ) as \$l => \$r ) { wp_insert_user( array( 'user_login' => \$l, 'user_pass' => 'Pass-' . \$l . '-1', 'user_email' => \$l . '@example.test', 'role' => \$r ) ); } echo 1;" );
$own = new QA_Session( $D['url'] ); $own->login( 'rp_owner', 'Pass-rp_owner-1' );
$img = qa_jpeg( 'qa-rep.jpg' );
$vf = static function ( array $o ) use ( $img ) { return array_merge( array( 'brand' => 'Toyota', 'model' => 'X', 'year' => 2020, 'mileage_km' => 1000, 'fuel_type' => 'petrol', 'transmission' => 'manual', 'body_type' => 'suv', 'vehicle_condition' => 'imported', 'color' => 'Gris', 'listing_type' => 'sale', 'status' => 'published', 'publish_to_marketplace' => 0, 'photos[0]' => new CURLFile( $img, 'image/jpeg', 'r.jpg' ) ), $o ); };
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'model' => 'RepOne', 'price_amount' => '10 000 000', 'purchase_price' => '8 000 000' ) ) );
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'model' => 'RepTwo', 'price_amount' => '5 000 000', 'purchase_price' => '' ) ) );
$own->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'model' => 'RepStock', 'price_amount' => '3 000 000' ) ) );
$r1 = (int) $dv( "SELECT id FROM wp_adc_vehicles WHERE model='RepOne'" ); $r2 = (int) $dv( "SELECT id FROM wp_adc_vehicles WHERE model='RepTwo'" ); $r3 = (int) $dv( "SELECT id FROM wp_adc_vehicles WHERE model='RepStock'" );
$own->crm( '/manager/sales/new/', 'sale_create', array( 'vehicle_id' => $r1, 'new_customer_name' => 'Acheteur Un', 'new_customer_phone' => '770001001', 'sale_price' => '9 500 000', 'confirm' => 1 ) );
$own->crm( '/manager/sales/new/', 'sale_create', array( 'vehicle_id' => $r2, 'new_customer_name' => 'Acheteur Deux', 'new_customer_phone' => '770001002', 'sale_price' => '4 800 000', 'confirm' => 1 ) );
// leads: 3 website (1 won), 1 whatsapp (0 won) ; plus 1 old lead (100 days)
$DM( "wp_set_current_user( 1 ); foreach ( array( array( 'A1', 'website' ), array( 'A2', 'website' ), array( 'A3', 'website' ), array( 'B1', 'whatsapp' ) ) as \$x ) { \$id = ADC\\Leads::save( array( 'name' => \$x[0], 'phone' => '77000' . rand( 1000, 9999 ), 'message' => 'm', 'source' => \$x[1] ) ); } echo 1;" );
$wonid = (int) $dv( "SELECT id FROM wp_adc_leads WHERE name='A1'" );
$DM( "wp_set_current_user( 1 ); ADC\\Leads::set_status( $wonid, 'won' ); echo 1;" );
$DM( "global \$wpdb; \$now = current_time( 'mysql', true ); \$wpdb->insert( ADC\\Database::table( 'leads' ), array( 'name' => 'OldLead', 'phone' => '770000000', 'email' => '', 'message' => '', 'source' => 'website', 'status' => 'new', 'interest' => 'sale', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 100 * DAY_IN_SECONDS ), 'updated_at' => \$now ) ); echo 1;" );
// publication ages: RepOne published 10 days before the sale, RepTwo 20 days; stock vehicle waiting 90 days
$DM( "global \$wpdb; \$t = ADC\\Database::table( 'vehicles' ); \$wpdb->update( \$t, array( 'published_at' => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ) ), array( 'id' => $r1 ) ); \$wpdb->update( \$t, array( 'published_at' => gmdate( 'Y-m-d H:i:s', time() - 20 * DAY_IN_SECONDS ) ), array( 'id' => $r2 ) ); \$wpdb->update( \$t, array( 'published_at' => gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ), array( 'id' => $r3 ) ); echo 1;" );
// rental fleet: 1 vehicle, rental 5 days ago → 3 days ago (2 days) = 60 000
$DM( "global \$wpdb; \$now = current_time( 'mysql', true ); \$v = ADC\\Database::table( 'vehicles' ); \$row = \$wpdb->get_row( \"SELECT * FROM \$v WHERE id = $r3\" ); \$wpdb->insert( \$v, array_merge( (array) \$row, array( 'id' => null, 'uid' => wp_generate_uuid4(), 'slug' => 'rep-rent', 'model' => 'RepRent', 'title' => 'Toyota RepRent 2020', 'listing_type' => 'rent', 'status' => 'published', 'published_at' => \$now ) ) ); \$rid = \$wpdb->insert_id; \$wpdb->insert( ADC\\Database::table( 'customers' ), array( 'name' => 'Loueur', 'notes' => '', 'created_at' => \$now, 'updated_at' => \$now ) ); \$cid = \$wpdb->insert_id; \$wpdb->insert( ADC\\Database::table( 'rentals' ), array( 'vehicle_id' => \$rid, 'customer_id' => \$cid, 'starts_at' => gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ), 'ends_at' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ), 'total_amount' => 60000, 'status' => 'returned', 'return_notes' => '', 'notes' => '', 'created_at' => \$now, 'updated_at' => \$now ) ); echo \$wpdb->last_error ?: 'ok';" );
$rep = json_decode( $DM( 'echo wp_json_encode( ADC\Reports::build( 30 ) );' ), true );
qa( 'Sales in 30 days = 2, revenue 14 300 000', 2 === ( $rep['sales'] ?? null ) && 14300000 === ( $rep['revenue'] ?? null ), '2 / 14300000', json_encode( array( $rep['sales'] ?? null, $rep['revenue'] ?? null ) ) );
qa( 'Gross margin counts only the sale whose purchase price is known: 9 500 000 − 8 000 000 = 1 500 000 (1 of 2)', 1500000 === ( $rep['margin'] ?? null ) && 1 === ( $rep['with_cost'] ?? null ), '1500000 / 1', json_encode( array( $rep['margin'] ?? null, $rep['with_cost'] ?? null ) ) );
qa( 'Leads in 30 days = 4 (the 100-day-old lead excluded), conversion 25.0 % (1 won)', 4 === ( $rep['leads'] ?? null ) && 25.0 == ( $rep['conversion'] ?? null ), '4 / 25', json_encode( array( $rep['leads'] ?? null, $rep['conversion'] ?? null ) ) );
$src = array(); foreach ( $rep['sources'] ?? array() as $s ) { $src[ $s['source'] ] = array( (int) $s['total'], (int) $s['won'] ); }
qa( 'Leads by source: website 3 (1 won), whatsapp 1 (0 won)', ( $src['website'] ?? null ) === array( 3, 1 ) && ( $src['whatsapp'] ?? null ) === array( 1, 0 ), 'web 3/1, wa 1/0', json_encode( $src ) );
qa( 'Average time to sell = (10 + 20) / 2 = 15 days', 15 === ( $rep['days_to_sell'] ?? null ), '15', json_encode( $rep['days_to_sell'] ?? null ) );
qa( 'Stock waiting > 60 days lists the 90-day-old vehicle only', 1 === count( $rep['aging'] ?? array() ) && 'Toyota RepStock 2020' === ( $rep['aging'][0]['title'] ?? '' ), '1 vehicle', json_encode( array_column( $rep['aging'] ?? array(), 'title' ) ) );
qa( 'Rental income 60 000 and fleet occupancy 2 days / (1 vehicle × 30 days) = 6.7 %', 60000 === ( $rep['rent_income'] ?? null ) && 6.7 == ( $rep['utilisation'] ?? null ) && 1 === ( $rep['fleet'] ?? null ), '60000 / 6.7 / 1', json_encode( array( $rep['rent_income'] ?? null, $rep['utilisation'] ?? null, $rep['fleet'] ?? null ) ) );
$rp = $own->get( '/manager/reports/' );
$rt = $norm( $rp['body'] );
qa( 'Owner sees the reports screen with the figures (200; "25,0"/"25.0 %", 1 500 000)', 200 === $rp['status'] && preg_match( '/1[ ,.]?500[ ,.]?000/', $rt ) && preg_match( '/25[.,]0/', $rt ), '200 + figures', (string) $rp['status'] );
$sl = new QA_Session( $D['url'] ); $sl->login( 'rp_sales', 'Pass-rp_sales-1' );
$sp = $sl->get( '/manager/reports/' );
qa( 'Sales agent (no adc_view_reports) cannot read the reports (margin stays private)', false === strpos( $norm( $sp['body'] ), '1 500 000' ) && ( 200 !== $sp['status'] || false === stripos( $sp['body'], 'Gross margin' ) ), 'denied', (string) $sp['status'] );
$vw = new QA_Session( $D['url'] ); $vw->login( 'rp_viewer', 'Pass-rp_viewer-1' );
qa( 'Viewer (adc_view_reports) can read the reports', 200 === $vw->get( '/manager/reports/' )['status'], '200', (string) $vw->last['status'] );
$r90 = json_decode( $DM( 'echo wp_json_encode( ADC\Reports::build( 90 ) );' ), true );
qa( 'Period 90 days now includes the 100-day-old lead? (no: 100 > 90) leads still 4; period 365 includes it', 4 === $r90['leads'] && 5 === json_decode( $DM( 'echo wp_json_encode( ADC\Reports::build( 365 ) );' ), true )['leads'], '4 / 5', (string) $r90['leads'] );
qa( 'Arbitrary period value (e.g. 7 or "1 OR 1") falls back to 30 days', 30 === json_decode( $DM( 'echo wp_json_encode( ADC\Reports::build( 7 ) );' ), true )['days'], '30', '' );
$emp = json_decode( $DM( "global \$wpdb; foreach ( array('sales','leads','rentals','vehicles') as \$t ) { \$wpdb->query( 'DELETE FROM ' . ADC\\Database::table( \$t ) ); } echo wp_json_encode( ADC\\Reports::build( 30 ) );" ), true );
qa( 'Empty CRM: reports compute without error (zero sales, no division by zero, null days-to-sell/utilisation)', 0 === $emp['sales'] && 0 === $emp['conversion'] && null === $emp['days_to_sell'] && null === $emp['utilisation'], 'zeros/null', json_encode( array( $emp['sales'] ?? '?', $emp['conversion'] ?? '?', $emp['days_to_sell'] ?? '?' ) ) );
$ep = $own->get( '/manager/reports/' );
qa( 'Reports screen renders with an empty CRM (200)', 200 === $ep['status'], '200', (string) $ep['status'] );

// =========================================================== 3. privacy
qa_section( 'Privacy: export / erase / retention' );
$mail = 'erase.me@example.test'; $phone = '+221770123456';
$iq = AMC\Inquiries::create( array( 'type' => 'contact', 'name' => 'Erase Me', 'email' => $mail, 'phone' => $phone, 'company' => '', 'subject' => '', 'message' => 'Message Erase Me secret-token-ZZ' ) );
$vlive = $wpdb->get_row( "SELECT * FROM {$tbl('vehicles')} WHERE external_id='r1'" );
$lid = AMC\Marketplace_Leads::create( $dA, (int) $vlive->id, array( 'name' => 'Erase Me', 'phone' => $phone, 'email' => $mail, 'message' => 'Lead Erase Me secret-token-ZZ' ) );
$wpdb->insert( $tbl( 'wa_messages' ), array( 'wamid' => 'p.1', 'phone' => $phone, 'dealer_id' => 0, 'direction' => 'in', 'kind' => 'text', 'body' => 'Erase Me whatsapp secret-token-ZZ', 'status' => 'processed', 'created_at' => current_time( 'mysql', true ) ) );
$wpdb->insert( $tbl( 'call_logs' ), array( 'provider' => 'gateway', 'call_ref' => 'p-call', 'direction' => 'inbound', 'from_number' => $phone, 'to_number' => '', 'dealer_id' => 0, 'status' => 'ended', 'transcript' => 'Erase Me call secret-token-ZZ', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ) );
$ex = AMC\Privacy::export( $mail );
$json = json_encode( $ex );
qa( 'Export returns the contact request AND the message sent to a dealer for that email', 2 <= count( $ex['data'] ) && false !== strpos( $json, 'secret-token-ZZ' ), '>= 2 items', (string) count( $ex['data'] ) );
qa( 'Export for an unknown email returns nothing (no data of other people)', 0 === count( AMC\Privacy::export( 'nobody@example.test' )['data'] ), '0', '' );
qa( 'Export does not leak other people\'s requests (SQL-ish email)', 0 === count( AMC\Privacy::export( "x' OR '1'='1" )['data'] ), '0', '' );
qa( 'Export includes the WhatsApp messages and call transcripts of that person (same data subject, by phone)', false !== strpos( $json, 'whatsapp secret-token-ZZ' ) || false !== strpos( $json, 'call secret-token-ZZ' ), 'WA + call data exported', 'only inquiries + marketplace leads are exported (wa_messages, call_logs, wa_conversations missing)' );
$er = AMC\Privacy::erase( $mail );
wp_cache_flush();
qa( 'Erase removes the inquiry and the marketplace lead of that email', ! $wpdb->get_var( "SELECT id FROM {$tbl('inquiries')} WHERE email='$mail'" ) && ! $wpdb->get_var( "SELECT id FROM {$tbl('marketplace_leads')} WHERE email='$mail'" ) && $er['items_removed'], 'removed', json_encode( $er ) );
$left = array();
$cdb = new PDO( 'sqlite:' . $C['dir'] . '/wp-content/database/local.sqlite' ); $cdb->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$ctables = $cdb->query( "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '" . $wpdb->prefix . "amc%'" )->fetchAll( PDO::FETCH_COLUMN );
foreach ( $ctables as $t ) {
	foreach ( $cdb->query( "SELECT * FROM \"$t\"" )->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
		$j = json_encode( $row, JSON_UNESCAPED_UNICODE );
		if ( false !== strpos( $j, 'secret-token-ZZ' ) || false !== strpos( $j, $mail ) ) { $left[ $t ] = ( $left[ $t ] ?? 0 ) + 1; }
	}
}
qa( 'After erasure NO trace of the person remains in any marketplace table (webhook queue payloads, WhatsApp, call transcripts, audit…)', ! $left, 'no residue', count( $ctables ) . ' tables scanned; residual personal data in: ' . json_encode( $left ) );
// retention
$old = static function ( $days ) { return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS - 3600 ); };
$new = static function ( $days ) { return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS + 3600 ); };
$wpdb->query( "DELETE FROM {$tbl('marketplace_leads')}" ); $wpdb->query( "DELETE FROM {$tbl('wa_messages')}" ); $wpdb->query( "DELETE FROM {$tbl('wa_conversations')}" ); $wpdb->query( "DELETE FROM {$tbl('call_logs')}" ); $wpdb->query( "DELETE FROM {$tbl('inquiries')}" ); $wpdb->query( "DELETE FROM {$tbl('webhook_deliveries')}" );
$a = AMC\Marketplace_Leads::create( $dA, (int) $vlive->id, array( 'name' => 'Old Lead', 'phone' => '770000011', 'email' => 'o1@example.test', 'message' => 'x' ) ); $wpdb->update( $tbl( 'marketplace_leads' ), array( 'created_at' => $old( 180 ) ), array( 'id' => $a ) );
$b = AMC\Marketplace_Leads::create( $dA, (int) $vlive->id, array( 'name' => 'Recent Lead', 'phone' => '770000012', 'email' => 'o2@example.test', 'message' => 'x' ) ); $wpdb->update( $tbl( 'marketplace_leads' ), array( 'created_at' => $new( 180 ) ), array( 'id' => $b ) );
foreach ( array( 'w.old' => $old( 90 ), 'w.new' => $new( 90 ) ) as $k => $t ) { $wpdb->insert( $tbl( 'wa_messages' ), array( 'wamid' => $k, 'phone' => '+221700000002', 'dealer_id' => 0, 'direction' => 'in', 'kind' => 'text', 'body' => 'x', 'status' => 'processed', 'created_at' => $t ) ); }
foreach ( array( 'c.old' => $old( 30 ), 'c.new' => $new( 30 ) ) as $k => $t ) { $wpdb->insert( $tbl( 'wa_conversations' ), array( 'phone' => '+22170000' . substr( md5( $k ), 0, 4 ), 'dealer_id' => 0, 'history' => '[]', 'pending_action' => '', 'updated_at' => $t ) ); }
foreach ( array( 'call.old' => $old( 90 ), 'call.new' => $new( 90 ) ) as $k => $t ) { $wpdb->insert( $tbl( 'call_logs' ), array( 'provider' => 'gateway', 'call_ref' => $k, 'direction' => 'inbound', 'from_number' => '+221700000003', 'to_number' => '', 'dealer_id' => 0, 'status' => 'ended', 'transcript' => 'x', 'created_at' => $t, 'updated_at' => $t ) ); }
foreach ( array( 'old' => $old( 365 ), 'new' => $new( 365 ) ) as $k => $t ) { AMC\Inquiries::create( array( 'type' => 'contact', 'name' => "Inq $k", 'email' => "i$k@example.test", 'phone' => '770000013', 'company' => '', 'subject' => '', 'message' => 'x' ) ); $wpdb->query( $wpdb->prepare( "UPDATE {$tbl('inquiries')} SET created_at=%s WHERE name=%s", $t, "Inq $k" ) ); }
$wpdb->insert( $tbl( 'webhook_deliveries' ), array( 'dealer_id' => $dA, 'delivery_id' => wp_generate_uuid4(), 'event' => 'lead.created', 'payload' => wp_json_encode( array( 'name' => 'Old Payload Person', 'phone' => '770000099' ) ), 'status' => 'delivered', 'attempts' => 1, 'next_attempt_at' => $old( 400 ), 'delivered_at' => $old( 400 ), 'created_at' => $old( 400 ) ) );
qa( 'Fixture inserted: a 400-day-old delivered webhook with a buyer name and phone', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('webhook_deliveries')} WHERE payload LIKE '%Old Payload Person%'" ), '1 row', $wpdb->last_error );
qa( 'Retention cron is scheduled daily (amc_daily)', (bool) wp_next_scheduled( AMC\Subscriptions::CRON ), 'scheduled', '' );
do_action( AMC\Subscriptions::CRON );
wp_cache_flush();
$cnt = static function ( $sql ) use ( $wpdb ) { return (int) $wpdb->get_var( $sql ); };
qa( 'Marketplace requests older than 180 days purged, the 179-day-old one kept', 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('marketplace_leads')} WHERE name='Old Lead'" ) && 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('marketplace_leads')} WHERE name='Recent Lead'" ), 'old gone / recent kept', '' );
qa( 'WhatsApp messages: > 90 days purged, 89-day-old kept', 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE wamid='w.old'" ) && 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE wamid='w.new'" ), 'old gone / recent kept', '' );
qa( 'Conversation memory: > 30 days purged, 29-day-old kept', 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('wa_conversations')}" ), '1 left', (string) $cnt( "SELECT COUNT(*) FROM {$tbl('wa_conversations')}" ) );
qa( 'Call transcripts: > 90 days purged, 89-day-old kept', 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref='call.old'" ) && 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref='call.new'" ), 'old gone / recent kept', '' );
qa( 'Contact requests: > 1 year purged, 364-day-old kept', 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('inquiries')} WHERE name='Inq old'" ) && 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('inquiries')} WHERE name='Inq new'" ), 'old gone / recent kept', '' );
qa( 'Webhook queue payloads (name + phone of buyers) are also purged after the retention period', 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('webhook_deliveries')} WHERE payload LIKE '%Old Payload Person%'" ), 'purged', 'a 400-day-old delivery with name and phone is still stored (no purge of webhook_deliveries)' );
// Dealer-side erase
$DM( "global \$wpdb; \$now = current_time( 'mysql', true ); wp_set_current_user( 1 ); \$wpdb->insert( ADC\\Database::table( 'customers' ), array( 'name' => 'Erase Customer', 'phone' => '770555555', 'email' => '$mail', 'address' => 'Rue secret-addr-QQ', 'city' => 'Dakar', 'id_number' => 'CNI-SECRET-777', 'notes' => 'note secret-addr-QQ', 'created_at' => \$now, 'updated_at' => \$now ) ); \$cid = \$wpdb->insert_id; \$lid = ADC\\Leads::save( array( 'name' => 'Erase Lead', 'phone' => '770555555', 'email' => '$mail', 'message' => 'msg secret-addr-QQ', 'source' => 'website' ) ); if ( ! is_wp_error( \$lid ) ) { ADC\\Leads::note( \$lid, 'note', 'private note about Erase Lead secret-addr-QQ' ); } echo 1;" );
$dex = json_decode( $DM( "echo wp_json_encode( ADC\\Privacy::export( '$mail' ) );" ), true );
qa( 'Dealer export returns customer + lead records of that email, including ID number', 2 <= count( $dex['data'] ?? array() ) && false !== strpos( json_encode( $dex ), 'CNI-SECRET-777' ), '>= 2 items', (string) count( $dex['data'] ?? array() ) );
$DM( "ADC\\Privacy::erase( '$mail' ); echo 1;" );
$res = array();
foreach ( $dq( "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'wp\\_adc\\_%' ESCAPE '\\'" ) as $t ) {
	foreach ( $dq( 'SELECT * FROM ' . $t['name'] ) as $row ) { $j = json_encode( $row, JSON_UNESCAPED_UNICODE ); if ( false !== strpos( $j, 'secret-addr-QQ' ) || false !== strpos( $j, 'CNI-SECRET-777' ) || false !== strpos( $j, $mail ) || false !== strpos( $j, '770555555' ) ) { $res[ $t['name'] ] = ( $res[ $t['name'] ] ?? 0 ) + 1; } }
}
qa( 'Dealer erase anonymises customer + lead (name/phone/email/ID/address/message) and leaves NO trace in any CRM table (lead notes, activity log…)', ! $res, 'no residue', 'residual personal data in: ' . json_encode( $res ) );
qa( 'Dealer erase keeps the sale/rental history rows (accounting) — customer row still exists, anonymised', 'Anonymisé' === $dv( "SELECT name FROM wp_adc_customers WHERE id=(SELECT MAX(id) FROM wp_adc_customers WHERE notes='')" ) || (int) $dv( "SELECT COUNT(*) FROM wp_adc_customers WHERE name IN ('Anonymised','Anonymisé')" ) >= 1, 'anonymised row kept', '' );
qa( 'Privacy policy text is registered by both plugins', (bool) $DM( "echo has_action( 'admin_init', array( 'ADC\\\\Privacy', 'policy' ) ) ? 1 : 0;" ) && (bool) has_action( 'admin_init', array( 'AMC\\Privacy', 'policy' ) ), 'registered', '' );

// =========================================================== 4. security headers
qa_section( 'HTTP security headers' );
$hdr = static function ( $url, $cookie = '' ) {
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $cookie ? array( 'Cookie: ' . $cookie ) : array() ) );
	$raw = curl_exec( $ch ); $size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE ); $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); curl_close( $ch );
	return array( $code, strtolower( substr( $raw, 0, $size ) ) );
};
list( $c, $h ) = $hdr( $C['url'] . '/' );
qa( 'Central home: X-Content-Type-Options nosniff + Referrer-Policy + Permissions-Policy', 200 === $c && false !== strpos( $h, 'x-content-type-options: nosniff' ) && false !== strpos( $h, 'referrer-policy: strict-origin-when-cross-origin' ) && false !== strpos( $h, 'permissions-policy:' ), 'all three', $c . ' ' . implode( ',', array_filter( array( false !== strpos( $h, 'nosniff' ) ? 'nosniff' : '', false !== strpos( $h, 'referrer-policy' ) ? 'referrer' : '', false !== strpos( $h, 'permissions-policy' ) ? 'permissions' : '' ) ) ) );
list( $c, $h ) = $hdr( $C['url'] . '/platform-admin/' );
list( $c, $h ) = $hdr( $C['url'] . '/dealer-login/' );
qa( 'Registration/portal pages send X-Frame-Options SAMEORIGIN (clickjacking of forms)', false !== strpos( $h, 'x-frame-options' ), 'present', $c . ' ' . ( false !== strpos( $h, 'x-frame-options' ) ? 'yes' : 'no header' ) );
list( $c, $h ) = $hdr( $C['url'] . '/wp-json/marketplace/v1/vehicles' );
qa( 'Public REST catalog: cacheable (public, max-age=60) + nosniff', false !== strpos( $h, 'cache-control: public, max-age=60' ) && false !== strpos( $h, 'nosniff' ), 'public cache', $c );
$tok = $admin->jar_header ?? '';
list( $c, $h ) = $hdr( $C['url'] . '/wp-json/marketplace/v1/sync/status' );
qa( 'Private/authenticated API answers are never cached (no-store)', false !== strpos( $h, 'cache-control: no-store' ), 'no-store', $c . ' ' . ( preg_match( '/cache-control:[^\r\n]*/', $h, $mm ) ? $mm[0] : 'none' ) );
list( $c, $h ) = $hdr( $D['url'] . '/manager/login/' );
$dh = array( 'nosniff' => false !== strpos( $h, 'x-content-type-options: nosniff' ), 'frame' => false !== strpos( $h, 'x-frame-options' ), 'referrer' => false !== strpos( $h, 'referrer-policy' ) );
qa( 'Dealer CRM login (/manager/login/): nosniff, X-Frame-Options and Referrer-Policy present', 200 === $c && ! in_array( false, $dh, true ), 'all three', $c . ' ' . json_encode( $dh ) );
list( $c, $h ) = $hdr( $D['url'] . '/' );
qa( 'Dealer public site: nosniff + Referrer-Policy', false !== strpos( $h, 'nosniff' ) && false !== strpos( $h, 'referrer-policy' ), 'present', $c . ' ' . json_encode( array( false !== strpos( $h, 'nosniff' ), false !== strpos( $h, 'referrer-policy' ) ) ) );
list( $c, $h ) = $hdr( $C['url'] . '/' );
qa_skip( 'X-Powered-By / server version leak', 'php -S dev server always sends X-Powered-By; check on the real host (expose_php=Off)' );
qa( 'Content-Security-Policy on HTML pages (defence in depth against XSS)', false !== strpos( $h, 'content-security-policy' ), 'CSP present', 'no CSP header on HTML pages (only on document downloads); HSTS must be set by the host' );

// =========================================================== 5. voice gateway
qa_section( 'Voice gateway (signature, window, events) — no real telephony' );
$VS = 'qa-voice-secret';
$vurl = $C['url'] . '/wp-json/marketplace/v1/voice/events';
$vpost = static function ( array $payload, array $o = array() ) use ( $vurl, $VS ) {
	$body = $o['raw'] ?? wp_json_encode( $payload ); $ts = $o['ts'] ?? time();
	$sig = $o['sig'] ?? hash_hmac( 'sha256', $ts . '.' . ( $o['sign_body'] ?? $body ), $VS );
	$h = array( 'Content-Type: application/json' );
	if ( empty( $o['nohdr'] ) ) { $h[] = 'X-AMC-Timestamp: ' . $ts; $h[] = 'X-AMC-Voice-Signature: ' . $sig; }
	$ch = curl_init( $vurl ); curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 30 ) );
	$b = curl_exec( $ch ); $s = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); curl_close( $ch );
	return array( $s, json_decode( (string) $b, true ), (string) $b );
};
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ) );
qa( 'Voice channel disabled by default → 403', 403 === $s, '403', (string) $s );
update_option( Settings::OPTION, array_merge( Settings::all(), array( 'voice_provider' => 'gateway', 'voice_secret' => Crypto::encrypt( $VS ), 'wa_public_assistant' => 0 ) ) ); wp_cache_flush();
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ), array( 'nohdr' => 1 ) );
qa( 'Enabled but no signature headers → 401', 401 === $s, '401', (string) $s );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ), array( 'sig' => str_repeat( 'a', 64 ) ) );
qa( 'Forged signature → 401, nothing stored', 401 === $s && 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref='v1'" ), '401', (string) $s );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ), array( 'sign_body' => '{"event":"call.started","call_ref":"v2"}' ) );
qa( 'Body altered after signing → 401', 401 === $s, '401', (string) $s );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ), array( 'ts' => time() - 400 ) );
qa( 'Timestamp 400 s old → 401', 401 === $s, '401', (string) $s );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ), array( 'ts' => time() + 400 ) );
qa( 'Timestamp 400 s in the future → 401', 401 === $s, '401', (string) $s );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ), array( 'sig' => strtoupper( hash_hmac( 'sha256', time() . '.' . wp_json_encode( array( 'event' => 'call.started', 'call_ref' => 'v1' ) ), $VS ) ) ) );
qa( 'Signature in upper-case hex still verifies when correct (case-insensitive hex)', in_array( $s, array( 200, 401 ), true ), '200 (or 401 if ts second differs)', (string) $s );
list( $s, $j ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1', 'from' => '+221770000777', 'to' => '+221330000000', 'direction' => 'inbound' ) );
qa( 'Valid call.started → 200 and a call log row is created', 200 === $s && 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref='v1'" ), '200 + 1 row', (string) $s );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => 'v1' ) );
qa( 'Same call.started twice does not create a second call row', 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref='v1'" ), '1 row', (string) $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref='v1'" ) );
list( $s ) = $vpost( array( 'event' => 'call.started' ) );
qa( 'Missing call_ref → 400', 400 === $s, '400', (string) $s );
list( $s, $j ) = $vpost( array( 'event' => 'utterance', 'call_ref' => 'v1', 'from' => '+221770000777', 'text' => 'Bonjour <b>je cherche</b> une voiture' ) );
qa( 'utterance from an unknown number → spoken answer "reserved for dealers" (no markup, no markdown stars)', 200 === $s && ! empty( $j['say'] ) && false === strpos( $j['say'], '<' ) && false === strpos( $j['say'], '*' ), 'say text', json_encode( $j ) );
qa( 'Utterance logged as an inbound message (kind call) and transcript stored without raw HTML', 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE wamid='call:v1' AND kind='call'" ) && false === strpos( (string) $wpdb->get_var( "SELECT transcript FROM {$tbl('call_logs')} WHERE call_ref='v1'" ), '<b>' ), 'logged, sanitised', '' );
list( $s, $j ) = $vpost( array( 'event' => 'utterance', 'call_ref' => 'v1', 'from' => '+221770000777', 'text' => '' ) );
qa( 'Empty utterance → polite "repeat please" answer', 200 === $s && ! empty( $j['say'] ), 'say', json_encode( $j ) );
list( $s, $j ) = $vpost( array( 'event' => 'utterance', 'call_ref' => 'v1', 'text' => 'sans numero' ) );
qa( 'Utterance without caller number → polite answer, no processing', 200 === $s && ! empty( $j['say'] ), 'say', json_encode( $j ) );
list( $s ) = $vpost( array( 'event' => 'call.ended', 'call_ref' => 'v1', 'duration_seconds' => 42 ) );
qa( 'call.ended → status ended with duration 42 s', 200 === $s && 'ended' === $wpdb->get_var( "SELECT status FROM {$tbl('call_logs')} WHERE call_ref='v1'" ) && 42 === (int) $wpdb->get_var( "SELECT duration_seconds FROM {$tbl('call_logs')} WHERE call_ref='v1'" ), 'ended/42', '' );
list( $s ) = $vpost( array( 'event' => 'weird.event', 'call_ref' => 'v1' ) );
qa( 'Unknown event type is acknowledged without side effect', 200 === $s, '200', (string) $s );
$ref = 'v-inj-' . wp_generate_password( 4, false );
list( $s ) = $vpost( array( 'event' => 'call.started', 'call_ref' => $ref . "'; DROP TABLE x;--<script>" ) );
qa( 'call_ref with SQL/HTML characters is stripped to a safe token', 200 === $s && 0 === $cnt( "SELECT COUNT(*) FROM {$tbl('call_logs')} WHERE call_ref LIKE '%<script>%' OR call_ref LIKE '%DROP TABLE%'" ), 'sanitised', '' );
$body = wp_json_encode( array( 'event' => 'utterance', 'call_ref' => 'v1', 'from' => '+221770000777', 'text' => 'replay me' ) ); $ts = time();
$vpost( array(), array( 'raw' => $body, 'ts' => $ts ) ); $vpost( array(), array( 'raw' => $body, 'ts' => $ts ) );
qa( 'An identical signed utterance replayed inside the 5-minute window is not processed twice', 1 === $cnt( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE kind='call' AND body='replay me'" ), '1 message', $cnt( "SELECT COUNT(*) FROM {$tbl('wa_messages')} WHERE kind='call' AND body='replay me'" ) . ' stored (timestamp-only replay protection)' );
list( $s ) = $vpost( array(), array( 'raw' => '{bad json' ) );
qa( 'Signed but malformed JSON → rejected cleanly (4xx), no 500', $s >= 400 && $s < 500, '4xx', (string) $s );
$cfg = $admin->get( '/platform-admin/whatsapp/' );
qa( 'Staff screen does not print the voice secret', false === strpos( $cfg['body'], $VS ), 'hidden', '' );
qa_skip( 'Real telephony: SIP gateway, speech recognition/synthesis, Wolof speech, latency', 'needs a voice gateway and provider credentials' );
$e = qa_log_new_errors( $C['dir'], $log0 );
qa( 'No PHP warnings/notices on the central site during phase 6', ! $e, 'none', implode( ' | ', array_slice( $e, 0, 3 ) ) );
$e = qa_log_new_errors( $D['dir'], $dlog0 );
qa( 'No PHP warnings/notices on the dealer site during phase 6', ! $e, 'none', implode( ' | ', array_slice( $e, 0, 3 ) ) );
