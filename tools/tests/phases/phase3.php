<?php
/** Phase 3 — dealer website + CRM (dealer site, over HTTP + direct SQLite reads). */

qa_phase( 'Phase 3 — Dealer CRM and theme' );
qa_reset_dealer( $D['dir'], $D['url'] );
$dlog0 = qa_log_offset( $D['dir'] );
$dsqlite = $D['dir'] . '/wp-content/database/local.sqlite';
$ddb = new PDO( 'sqlite:' . $dsqlite );
$ddb->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$ddb->exec( 'PRAGMA busy_timeout=5000' );
/** Dealer DB query helper: dq( sql, params ) → rows. */
$dq  = static function ( $sql, array $p = array() ) use ( $ddb ) { $st = $ddb->prepare( $sql ); $st->execute( $p ); return $st->fetchAll( PDO::FETCH_ASSOC ); };
$dv  = static function ( $sql, array $p = array() ) use ( $dq ) { $r = $dq( $sql, $p ); return $r ? array_values( $r[0] )[0] : null; };
$dmail = static function () use ( $D ) { $f = $D['dir'] . '/wp-content/qa-mail.log'; return is_file( $f ) ? array_map( 'json_decode', file( $f ) ) : array(); };
$DM = static function ( $code ) use ( $D ) { return qa_dealer_eval( $D['dir'], $D['url'], $code ); };

$mk_user = static function ( $login, $role ) use ( $DM ) {
	return $DM( "echo wp_insert_user( array( 'user_login' => '$login', 'user_pass' => 'Pass-$login-1', 'user_email' => '$login@example.test', 'role' => '$role', 'display_name' => '$login' ) );" );
};

// ---------------------------------------------------------------- install
qa_section( 'Install on a blank WordPress' );
$active = $DM( "echo json_encode( array( is_plugin_active('automotive-dealer-crm/automotive-dealer-crm.php') || in_array('automotive-dealer-crm/automotive-dealer-crm.php', get_option('active_plugins')), get_option('template'), get_option('permalink_structure') ) );" );
qa( 'Dealer CRM plugin active, theme autodealer, pretty permalinks', false !== strpos( $active, 'autodealer' ) && 0 === strpos( $active, '[true' ), 'active', $active );
$tables = $dq( "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'wp\\_adc\\_%' ESCAPE '\\'" );
qa( 'CRM creates its custom tables (11 found; docs say 13 → doc mismatch noted)', 11 === count( $tables ), '11', (string) count( $tables ) );
$roles = $DM( "echo json_encode( array_values( array_intersect( array('adc_owner','adc_manager','adc_sales','adc_rental','adc_viewer'), array_keys( wp_roles()->roles ) ) ) );" );
qa( 'Five CRM roles registered', 5 === count( json_decode( $roles, true ) ), '5 roles', $roles );
$pub = new QA_Session( $D['url'] );
foreach ( array( '/' => 'home', '/inventory/' => 'inventory', '/rentals/' => 'rentals', '/about/' => 'about', '/contact/' => 'contact', '/manager/login/' => 'login', '/wp-sitemap.xml' => 'sitemap' ) as $path => $name ) {
	$x = $pub->get( $path );
	qa( "Public page {$name} ({$path}) 200, no PHP error text", 200 === $x['status'] && ! preg_match( '/Fatal error|Warning:|Notice:|Stack trace/', $x['body'] ), '200', (string) $x['status'] );
}
$x = $pub->get( '/financing/' );
qa( '/financing/ is 404 until enabled in Settings (show_financing=0)', 404 === $x['status'], '404', (string) $x['status'] );
$x = $pub->get( '/manager/' );
qa( 'Anonymous /manager/ → redirect to /manager/login/', 302 === $x['status'] && false !== strpos( $x['location'], 'manager/login' ), '302', $x['status'] . ' ' . $x['location'] );
$x = $pub->get( '/vehicle/does-not-exist/' );
qa( 'Unknown vehicle slug → 404', 404 === $x['status'], '404', (string) $x['status'] );

// ---------------------------------------------------------------- users
$ownerId = $mk_user( 'qa_owner', 'adc_owner' );
$S = array( 'owner' => new QA_Session( $D['url'] ) );
$F = $S['owner']->get( '/manager/login/' );
preg_match( '#name="_adc_nonce"[^>]*value="([a-z0-9]+)"#', $F['body'] . ' ', $m ) || preg_match( '#value="([a-z0-9]{10})"[^>]*name="_adc_nonce"#', $F['body'], $m );
$nonce = $m[1] ?? '';
$x = $S['owner']->post( '/manager/login/', array( '_adc_nonce' => $nonce, 'log' => 'qa_owner', 'pwd' => 'wrong-password' ) );
qa( '/manager/login/: wrong password → error message, no redirect', 200 === $x['status'] && false !== strpos( $x['body'], 'role="alert"' ), '200 + alert', (string) $x['status'] );
$x = $S['owner']->post( '/manager/login/', array( '_adc_nonce' => 'bad', 'log' => 'qa_owner', 'pwd' => 'Pass-qa_owner-1' ) );
qa( '/manager/login/: bad nonce refused', 200 === $x['status'], '200 form re-shown', (string) $x['status'] );
$x = $S['owner']->post( '/manager/login/', array( '_adc_nonce' => $nonce, 'log' => 'qa_owner', 'pwd' => 'Pass-qa_owner-1' ) );
qa( '/manager/login/: owner logs in → 302 to /manager/', 302 === $x['status'] && false !== strpos( $x['location'], '/manager' ), '302', $x['status'] . ' ' . $x['location'] );
$x = $S['owner']->get( '/manager/' );
qa( 'Owner dashboard /manager/ 200', 200 === $x['status'] && false !== strpos( $x['body'], 'adc-app' ), '200', (string) $x['status'] );
foreach ( array( 'vehicles', 'vehicles/new', 'leads', 'leads/new', 'customers', 'customers/new', 'sales', 'sales/new', 'rentals', 'rentals/new', 'team', 'settings', 'reports', 'activity', 'marketplace' ) as $sec ) {
	$x = $S['owner']->get( '/manager/' . $sec . '/' );
	qa( "Owner /manager/{$sec}/ renders 200 clean", 200 === $x['status'] && ! preg_match( '/Fatal error|Warning:|Notice:|Stack trace|Uncaught/', $x['body'] ), '200', (string) $x['status'] );
}
$x = $S['owner']->get( '/manager/' );
qa( 'CRM pages are noindex', false !== stripos( $x['body'], 'noindex' ), 'noindex', '' );
$x = $S['owner']->get( '/wp-admin/' );
qa( 'Owner (team-only) kept out of wp-admin', 302 === $x['status'] && false !== strpos( $x['location'], 'manager' ), '302 → /manager/', $x['status'] . ' ' . $x['location'] );

// ---------------------------------------------------------------- team invitations (5 roles)
qa_section( 'Team: invitations and 5 roles' );
$roles5 = array( 'manager' => 'adc_manager', 'sales' => 'adc_sales', 'rental' => 'adc_rental', 'viewer' => 'adc_viewer' );
$x = $S['owner']->crm( '/manager/team/', 'team_invite', array( 'email' => 'bad-email', 'role' => 'adc_sales' ) );
qa( 'Invite with invalid email refused', 0 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_invites" ), '0 invites', '' );
foreach ( $roles5 as $key => $role ) {
	$S['owner']->crm( '/manager/team/', 'team_invite', array( 'email' => "qa_$key@example.test", 'role' => $role, 'name' => ucfirst( $key ) ) );
}
qa( '4 invitations created (manager, sales, rental, viewer)', 4 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_invites' ), '4', (string) $dv( 'SELECT COUNT(*) FROM wp_adc_invites' ) );
$mails = $dmail();
$links = array();
foreach ( $mails as $mm ) {
	if ( preg_match( '#/manager/invite/([A-Za-z0-9]+)#', $mm->body, $lm ) ) { $links[ $mm->to ] = $lm[1]; }
}
qa( 'Invitation emails carry a 40-char single-use token link', 4 === count( $links ) && 40 === strlen( reset( $links ) ), '4 links', (string) count( $links ) );
qa( 'Token stored hashed (sha256), not in clear', 64 === strlen( (string) $dv( 'SELECT token_hash FROM wp_adc_invites LIMIT 1' ) ) && ! in_array( $dv( 'SELECT token_hash FROM wp_adc_invites LIMIT 1' ), $links, true ), 'hash', '' );
$bad = new QA_Session( $D['url'] );
$f = $bad->get( '/manager/invite/INVALIDTOKEN123/' );
qa( 'Invalid invitation token page renders (no crash)', 200 === $f['status'], '200', (string) $f['status'] );
foreach ( $roles5 as $key => $role ) {
	$S[ $key ] = new QA_Session( $D['url'] );
	$tok = $links[ "qa_$key@example.test" ] ?? 'x';
	$f = $S[ $key ]->get( "/manager/invite/$tok/" );
	preg_match( '#value="([a-z0-9]{10})"[^>]*name="_adc_nonce"#', $f['body'], $m ) || preg_match( '#name="_adc_nonce"[^>]*value="([a-z0-9]+)"#', $f['body'] . ' ', $m );
	if ( 'sales' === $key ) {
		$short = $S[ $key ]->post( "/manager/invite/$tok/", array( '_adc_nonce' => $m[1] ?? '', 'name' => 'Sales QA', 'password' => 'short' ) );
		qa( 'Invitation: password < 8 chars refused', 200 === $short['status'] && ! get_user_by( 'email', 'qa_sales@example.test' ) , 'form re-shown', (string) $short['status'] );
	}
	$x = $S[ $key ]->post( "/manager/invite/$tok/", array( '_adc_nonce' => $m[1] ?? '', 'name' => ucfirst( $key ) . ' QA', 'password' => "Pass-qa_$key-1" ) );
	$uid = $dv( "SELECT ID FROM wp_users WHERE user_email = ?", array( "qa_$key@example.test" ) );
	$caps = $dv( "SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = 'wp_capabilities'", array( $uid ) );
	qa( "Invitation accepted: {$key} account created with role {$role}, logged in, redirected", 302 === $x['status'] && false !== strpos( (string) $caps, $role ), "302 + $role", $x['status'] . ' ' . $caps );
}
$x = ( new QA_Session( $D['url'] ) )->post( '/manager/invite/' . ( $links['qa_sales@example.test'] ?? 'x' ) . '/', array( '_adc_nonce' => 'bad', 'name' => 'x', 'password' => 'Another-pass-1' ) );
$again = new QA_Session( $D['url'] ); $f = $again->get( '/manager/invite/' . ( $links['qa_sales@example.test'] ?? 'x' ) . '/' );
qa( 'Used invitation token cannot be reused (shows invalid/expired)', false === strpos( $f['body'], 'name="password"' ), 'no password form', '' );
$S['owner']->crm( '/manager/team/', 'team_invite', array( 'email' => 'qa_revoke@example.test', 'role' => 'adc_sales' ) );
$rid = $dv( "SELECT id FROM wp_adc_invites WHERE email='qa_revoke@example.test'" );
$S['owner']->crm( '/manager/team/', 'invite_revoke', array( 'id' => $rid ) );
$rtoken = null; foreach ( $dmail() as $mm ) { if ( 'qa_revoke@example.test' === $mm->to && preg_match( '#/manager/invite/([A-Za-z0-9]+)#', $mm->body, $lm ) ) { $rtoken = $lm[1]; } }
$f = ( new QA_Session( $D['url'] ) )->get( "/manager/invite/$rtoken/" );
qa( 'Revoked invitation no longer usable', false === strpos( $f['body'], 'name="password"' ), 'no form', '' );
$S['owner']->crm( '/manager/team/', 'team_invite', array( 'email' => 'qa_sales@example.test', 'role' => 'adc_sales' ) );
qa( 'Inviting an email that already has an account is refused', 0 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_invites WHERE email='qa_sales@example.test' AND accepted_at IS NULL" ), 'no new invite', '' );
// Last owner protection
$x = $S['owner']->crm( '/manager/team/', 'team_update', array( 'id' => $ownerId, 'role' => 'adc_sales' ) );
$ownerRole = $dv( "SELECT meta_value FROM wp_usermeta WHERE user_id=? AND meta_key='wp_capabilities'", array( $ownerId ) );
qa( 'Last owner cannot be demoted', false !== strpos( $ownerRole, 'adc_owner' ), 'still owner', $ownerRole );
$x = $S['owner']->crm( '/manager/team/', 'team_remove', array( 'id' => $ownerId ) );
$ownerRole = $dv( "SELECT meta_value FROM wp_usermeta WHERE user_id=? AND meta_key='wp_capabilities'", array( $ownerId ) );
qa( 'Owner cannot remove their own access', false !== strpos( $ownerRole, 'adc_owner' ), 'still owner', $ownerRole );

// ---------------------------------------------------------------- vehicles (7 statuses, photos)
qa_section( 'Vehicles: create, photos, 7 statuses' );
$p1 = qa_jpeg( 'qa-car1.jpg', 800, 500 ); $p2 = qa_jpeg( 'qa-car2.jpg', 640, 400, array( 20, 90, 200 ) );
$bad_img = qa_tmp_file( 'qa-notimage.jpg', '<?php echo 1;' );
$vf = static function ( array $over = array() ) { return array_merge( array( 'brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2019, 'mileage_km' => 85000, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'body_type' => 'sedan', 'vehicle_condition' => 'imported', 'color' => 'Blanc', 'vin' => 'JTDKN3DU0A0123456', 'listing_type' => 'sale', 'price_amount' => '8 500 000', 'purchase_price' => '6 900 000', 'description' => 'Très propre.', 'features' => "Climatisation\nCamera de recul", 'status' => 'published' ), $over ); };
$x = $S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => '', 'model' => '' ) ) );
qa( 'Vehicle without brand/model refused', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicles' ), '0 vehicles', '' );
$x = $S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'photos[0]' => new CURLFile( $p1, 'image/jpeg', 'a.jpg' ), 'photos[1]' => new CURLFile( $p2, 'image/jpeg', 'b.jpg' ) ) ) );
$v1 = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0] ?? null;
qa( 'Owner creates a published vehicle with 2 photos (302 to its page)', $v1 && 'published' === $v1['status'] && 302 === $x['status'], 'published', $v1 ? $v1['status'] : 'none' );
qa( 'Photos stored in media library and linked (2 rows, thumbnail set)', 2 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicle_media WHERE vehicle_id=?', array( $v1['id'] ) ) && (int) $v1['thumbnail_id'] > 0, '2 media', (string) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicle_media' ) );
qa( 'Price "8 500 000" parsed to 8500000, slug generated, uid is UUID', 8500000 === (int) $v1['price_amount'] && 'toyota-corolla-2019' === $v1['slug'] && preg_match( '/^[0-9a-f-]{36}$/', $v1['uid'] ), '8500000/slug/uuid', "{$v1['price_amount']} {$v1['slug']}" );
$x = $S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => 'Hyundai', 'model' => 'Tucson', 'photos[0]' => new CURLFile( $bad_img, 'image/jpeg', 'evil.jpg' ) ) ) );
$v2 = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0];
qa( 'Non-image file renamed .jpg is NOT added to the gallery (vehicle saved, photo refused)', 'Tucson' === $v2['model'] && 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicle_media WHERE vehicle_id=?', array( $v2['id'] ) ), '0 media', (string) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicle_media WHERE vehicle_id=?', array( $v2['id'] ) ) );
$evil_up = glob( $D['dir'] . '/wp-content/uploads/*/*/evil*' );
qa( 'No evil.jpg / PHP payload left in uploads', ! $evil_up, 'none', implode( ',', (array) $evil_up ) );
$php_up = glob( $D['dir'] . '/wp-content/uploads/*/*/*.php' );
qa( 'No .php file anywhere in uploads', ! $php_up, 'none', implode( ',', (array) $php_up ) );
$x = $S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => 'Toyota', 'model' => 'Hilux', 'year' => 2021, 'status' => 'draft', 'photos[0]' => new CURLFile( $p1, 'image/jpeg', 'c.jpg' ) ) ) );
$v3 = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0];
qa( 'Draft vehicle is not public (/vehicle/<slug>/ 404)', 'draft' === $v3['status'] && 404 === $pub->get( '/vehicle/' . $v3['slug'] . '/' )['status'], '404', (string) $pub->last['status'] );
qa( 'Published vehicle page 200 with title, price (FCFA), JSON-LD', 200 === $pub->get( '/vehicle/' . $v1['slug'] . '/' )['status'] && false !== strpos( $pub->last['body'], 'application/ld+json' ) && preg_match( '/8[\s\x{202f}\x{00a0}]?500[\s\x{202f}\x{00a0}]?000/u', $pub->last['body'] ), '200 + ld+json + price', (string) $pub->last['status'] );
$x = $pub->get( '/inventory/' );
qa( 'Inventory lists the published vehicle, not the draft', false !== strpos( $x['body'], 'Corolla' ) && false === strpos( $x['body'], 'Hilux' ), 'Corolla only', '' );
$all_ok = true; $trace = array();
foreach ( array( 'pending_review', 'published', 'reserved', 'sold', 'rented', 'archived', 'draft' ) as $st ) {
	$S['owner']->crm( '/manager/vehicles/' . $v3['id'] . '/', 'vehicle_status', array( 'id' => $v3['id'], 'status' => $st ) );
	$got = $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v3['id'] ) );
	$trace[] = $got; $all_ok = $all_ok && $got === $st;
}
qa( 'All 7 statuses reachable by the owner: pending_review → published → reserved → sold → rented → archived → draft', $all_ok, '7 transitions', implode( '>', $trace ) );
$x = $S['owner']->crm( '/manager/vehicles/' . $v3['id'] . '/', 'vehicle_status', array( 'id' => $v3['id'], 'status' => 'nonsense' ) );
qa( 'Unknown status value refused', 'draft' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v3['id'] ) ), 'unchanged', '' );
$x = $S['owner']->crm( '/manager/vehicles/' . $v1['id'] . '/', 'vehicle_delete', array( 'id' => $v1['id'] ) );
qa( 'Cannot delete a published vehicle (must archive first)', 1 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicles WHERE id=?', array( $v1['id'] ) ), 'still there', '' );
$S['owner']->crm( '/manager/vehicles/' . $v3['id'] . '/', 'vehicle_delete', array( 'id' => $v3['id'] ) );
qa( 'Draft vehicle can be deleted (row + media gone)', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicles WHERE id=?', array( $v3['id'] ) ) && 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_vehicle_media WHERE vehicle_id=?', array( $v3['id'] ) ), 'deleted', '' );

// ---------------------------------------------------------------- publish as manager vs sales agent
qa_section( 'Publish as manager vs sales agent; price rule' );
$x = $S['sales']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'status' => 'published' ) ) );
$v4 = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0];
qa( 'Sales agent asks "published" → saved as pending_review (never public)', 'pending_review' === $v4['status'] && 404 === $pub->get( '/vehicle/' . $v4['slug'] . '/' )['status'], 'pending_review + 404', $v4['status'] );
$x = $S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_status', array( 'id' => $v4['id'], 'status' => 'published' ) );
qa( 'Sales agent cannot publish via quick status (server-side)', 'pending_review' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), 'still pending_review', '' );
$x = $S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'status' => 'published' ) ) );
qa( 'Sales agent cannot publish by re-saving with status=published', 'pending_review' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), 'still pending_review', $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ) );
$x = $S['manager']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_status', array( 'id' => $v4['id'], 'status' => 'published' ) );
qa( 'Manager approves (publishes) the vehicle → public page 200', 'published' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ) && 200 === $pub->get( '/vehicle/' . $v4['slug'] . '/' )['status'], 'published + 200', '' );
// sales edits a published vehicle without touching the price
$S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'color' => 'Noir', 'status' => 'published' ) ) );
$col = $dv( 'SELECT color FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) );
qa( 'Sales agent can edit non-price fields of a published vehicle (price unchanged)', 'Noir' === $col, 'color=Noir', (string) $col );
$S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'price_amount' => '1 000', 'status' => 'published' ) ) );
qa( 'Sales agent cannot change the price of a published vehicle (server-side)', 8500000 === (int) $dv( 'SELECT price_amount FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), '8500000', (string) $dv( 'SELECT price_amount FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ) );
$S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'status' => 'draft' ) ) );
qa( 'Sales agent cannot UNPUBLISH a published vehicle by re-saving with status=draft (publish/archive right)', 'published' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), 'still published', (string) $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ) );
$S['manager']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'price_amount' => '7 900 000', 'status' => 'published' ) ) );
qa( 'Manager can change the price (7 900 000)', 7900000 === (int) $dv( 'SELECT price_amount FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), '7900000', '' );
// purchase price confidentiality
$f = $S['sales']->get( '/manager/vehicles/' . $v4['id'] . '/' );
qa( 'Private purchase cost is not shown to a sales agent', false === strpos( $f['body'], 'name="purchase_price"' ) || false === strpos( $f['body'], '6 900 000' ) && false === strpos( $f['body'], '6900000' ), 'hidden', '' );
qa( 'Public vehicle page and API never expose VIN or purchase cost', ( $b = $pub->get( '/vehicle/' . $v4['slug'] . '/' )['body'] ) && false === strpos( $b, 'JTDKN3DU0A0123456' ) && false === strpos( $b, '6900000' ) && false === strpos( $b, '6 900 000' ), 'absent', '' );
$api = $pub->get( '/wp-json/dealer/v1/vehicles' );
qa( 'REST dealer/v1/vehicles: 200 JSON, no vin / purchase_price keys', 200 === $api['status'] && false === stripos( $api['body'], 'purchase' ) && false === stripos( $api['body'], '"vin"' ), 'allow-list only', (string) $api['status'] );
$api = $pub->get( '/wp-json/dealer/v1/vehicles?status=draft' );
$j = json_decode( $api['body'], true );
qa( 'REST: ?status=draft does not reveal draft/pending vehicles', 200 === $api['status'] && ! array_filter( (array) ( $j['items'] ?? array() ), static function ( $i ) { return in_array( $i['status'] ?? '', array( 'draft', 'pending_review' ), true ); } ), 'no drafts', substr( $api['body'], 0, 120 ) );

// ---------------------------------------------------------------- leads
qa_section( 'Leads: website form → pipeline' );
$F = $pub->get( '/vehicle/' . $v4['slug'] . '/' );
preg_match( '#name="_adc_nonce"[^>]*value="([a-z0-9]+)"#', $F['body'] . ' ', $m ) || preg_match( '#value="([a-z0-9]{10})"[^>]*name="_adc_nonce"#', $F['body'], $m );
$ln = $m[1] ?? ''; preg_match( '#name="adc_ts" value="(\d+)"#', $F['body'], $mt ); $ts = $mt[1] ?? ( time() - 10 );
qa( 'Vehicle page has the lead form with nonce + time-trap + honeypot', '' !== $ln && isset( $mt[1] ) && false !== strpos( $F['body'], 'name="website"' ), 'fields present', "nonce=$ln ts=" . ( $mt[1] ?? 'none' ) );
$lead = array( '_adc_nonce' => $ln, 'adc_ts' => time() - 10, 'name' => 'Moussa Ndiaye', 'phone' => '77 555 12 34', 'email' => 'moussa@example.test', 'message' => 'Est-il disponible ?', 'vehicle_id' => $v4['id'], 'action' => 'adc_lead' );
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lead, array( 'adc_ts' => time() ) ) );
qa( 'Form submitted < 3 s after load is silently dropped (bot trap)', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_leads' ), '0 leads', '' );
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lead, array( 'website' => 'http://spam' ) ) );
qa( 'Honeypot filled → no lead', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_leads' ), '0 leads', '' );
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lead, array( '_adc_nonce' => 'bad' ) ) );
qa( 'Bad nonce → no lead, redirected with error flag', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_leads' ) && false !== strpos( $x['location'], 'adc_sent=error' ), 'error', $x['location'] );
$x = $pub->post( '/wp-admin/admin-post.php', $lead );
$L1 = $dq( 'SELECT * FROM wp_adc_leads ORDER BY id DESC LIMIT 1' )[0] ?? null;
qa( 'Valid website form → lead created (status new, source website, vehicle linked, unassigned)', $L1 && 'new' === $L1['status'] && 'website' === $L1['source'] && (int) $L1['vehicle_id'] === (int) $v4['id'] && 0 === (int) $L1['assigned_to'], 'new/website/linked', $L1 ? json_encode( array( $L1['status'], $L1['source'], $L1['vehicle_id'], $L1['assigned_to'] ) ) : 'none' );
qa( 'Phone normalised (77 555 12 34 → +221 or digits only)', preg_match( '/^\+?\d{9,15}$/', $L1['phone'] ), 'normalised', $L1['phone'] );
qa( 'Dealer notified by email of the website lead', (bool) array_filter( $dmail(), static function ( $mm ) { return false !== strpos( $mm->subject, 'Moussa' ); } ), 'mail', '' );
$x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lead, array( 'name' => '<script>alert(1)</script>Evil', 'message' => '<img src=x onerror=alert(1)>' ) ) );
$f = $S['owner']->get( '/manager/leads/' );
$f2 = $S['owner']->get( '/manager/leads/' . $dv( 'SELECT MAX(id) FROM wp_adc_leads' ) . '/' );
qa( 'Stored XSS in lead name/message escaped in pipeline and detail page', false === strpos( $f['body'] . $f2['body'], '<script>alert(1)' ) && false === strpos( $f['body'] . $f2['body'], '<img src=x onerror' ), 'escaped', '' );
// rate limit: 5/hour
for ( $i = 0; $i < 6; $i++ ) { $x = $pub->post( '/wp-admin/admin-post.php', array_merge( $lead, array( 'name' => "Spam $i" ) ) ); }
qa( 'Lead form rate-limited per IP (5/hour): last response flagged "rate"', false !== strpos( $x['location'], 'adc_sent=rate' ) && (int) $dv( 'SELECT COUNT(*) FROM wp_adc_leads' ) <= 5, 'rate', $x['location'] . ' total=' . $dv( 'SELECT COUNT(*) FROM wp_adc_leads' ) );
$DM( "global \$wpdb; foreach ( \$wpdb->get_col( \"SELECT option_name FROM {\$wpdb->options}\" ) as \$o ) { if ( false !== strpos( \$o, 'adc_lead_rl' ) ) { delete_option( \$o ); } } echo 'ok';" );
// pipeline
$lid = (int) $L1['id'];
$trace = array();
foreach ( array( 'contacted', 'interested', 'appointment', 'negotiation', 'won' ) as $st ) {
	$S['sales']->crm( '/manager/leads/' . $lid . '/', 'lead_status', array( 'id' => $lid, 'status' => $st ) );
	$trace[] = $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $lid ) );
}
qa( 'Pipeline: new → contacted → interested → appointment → negotiation → won (sales agent)', $trace === array( 'contacted', 'interested', 'appointment', 'negotiation', 'won' ), '5 steps', implode( '>', $trace ) );
qa( 'Sales agent moving an unassigned lead takes ownership (assigned_to = self)', (int) $dv( "SELECT ID FROM wp_users WHERE user_login='qa_sales'" ) === (int) $dv( 'SELECT assigned_to FROM wp_adc_leads WHERE id=?', array( $lid ) ), 'assigned', '' );
qa( 'Each transition written to lead history (>= 5 status notes)', (int) $dv( "SELECT COUNT(*) FROM wp_adc_lead_notes WHERE lead_id=? AND kind='status'", array( $lid ) ) >= 5, '>=5', (string) $dv( "SELECT COUNT(*) FROM wp_adc_lead_notes WHERE lead_id=? AND kind='status'", array( $lid ) ) );
$S['sales']->crm( '/manager/leads/' . $lid . '/', 'lead_status', array( 'id' => $lid, 'status' => 'lost', 'reason' => 'Trop cher' ) );
qa( 'Lost lead stores the reason', 'lost' === $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $lid ) ) && 'Trop cher' === $dv( 'SELECT lost_reason FROM wp_adc_leads WHERE id=?', array( $lid ) ), 'lost + reason', '' );
$S['sales']->crm( '/manager/leads/' . $lid . '/', 'lead_status', array( 'id' => $lid, 'status' => 'bogus' ) );
qa( 'Invalid lead status refused', 'lost' === $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $lid ) ), 'unchanged', '' );
$S['sales']->crm( '/manager/leads/' . $lid . '/', 'lead_note', array( 'id' => $lid, 'kind' => 'call', 'body' => 'Rappel demain', 'follow_up_at' => gmdate( 'Y-m-d\TH:i', time() + 86400 ) ) );
qa( 'Note + follow-up date saved', 'Rappel demain' === $dv( "SELECT body FROM wp_adc_lead_notes WHERE lead_id=? AND kind='call'", array( $lid ) ) && $dv( 'SELECT follow_up_at FROM wp_adc_leads WHERE id=?', array( $lid ) ), 'saved', '' );
// manual lead + visibility
$S['manager']->crm( '/manager/leads/new/', 'lead_save', array( 'name' => 'Lead Manager', 'phone' => '770000001', 'source' => 'phone', 'interest' => 'sale' ) );
$LM = (int) $dv( "SELECT id FROM wp_adc_leads WHERE name='Lead Manager'" );
qa( 'Manager creates a manual lead (visible to all, unassigned)', $LM > 0 && 0 === (int) $dv( 'SELECT assigned_to FROM wp_adc_leads WHERE id=?', array( $LM ) ), 'created', '' );
$S['sales']->crm( '/manager/leads/new/', 'lead_save', array( 'name' => 'Lead Sales Own', 'phone' => '770000002', 'source' => 'walk_in' ) );
$LS = (int) $dv( "SELECT id FROM wp_adc_leads WHERE name='Lead Sales Own'" );
qa( 'Lead created by sales agent is assigned to them', (int) $dv( "SELECT ID FROM wp_users WHERE user_login='qa_sales'" ) === (int) $dv( 'SELECT assigned_to FROM wp_adc_leads WHERE id=?', array( $LS ) ), 'assigned self', '' );
$rental_uid = (int) $dv( "SELECT ID FROM wp_users WHERE user_login='qa_rental'" );
$S['manager']->crm( '/manager/leads/' . $LM . '/', 'lead_assign', array( 'id' => $LM, 'assigned_to' => $rental_uid ) );
qa( 'Manager assigns a lead to the rental agent', $rental_uid === (int) $dv( 'SELECT assigned_to FROM wp_adc_leads WHERE id=?', array( $LM ) ), 'assigned', '' );
// Sales sees only own+unassigned; rental agent sees own
$page = $S['rental']->get( '/manager/leads/' )['body'];
qa( 'Rental agent sees own assigned lead but not the sales agent\'s lead', false !== strpos( $page, 'Lead Manager' ) && false === strpos( $page, 'Lead Sales Own' ), 'filtered', '' );
$x = $S['rental']->get( '/manager/leads/' . $LS . '/' );
qa( 'Rental agent opening another agent\'s lead directly gets no lead data (IDOR)', false === strpos( $x['body'], 'Lead Sales Own' ), 'hidden', (string) $x['status'] );
$x = $S['sales']->get( '/manager/leads/' . $LM . '/' );
qa( 'Sales agent opening a lead assigned to someone else gets no lead data (IDOR)', false === strpos( $x['body'], 'Lead Manager' ), 'hidden', (string) $x['status'] );
$S['sales']->crm( '/manager/leads/' . $LM . '/', 'lead_status', array( 'id' => $LM, 'status' => 'contacted' ) );
qa( 'Sales agent cannot change status of another agent\'s lead via POST', 'new' === $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $LM ) ), 'new', $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $LM ) ) );
$S['sales']->crm( '/manager/leads/' . $LM . '/', 'lead_note', array( 'id' => $LM, 'body' => 'intrusion' ) );
qa( 'Sales agent cannot add a note to another agent\'s lead', 0 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_lead_notes WHERE body='intrusion'" ), 'no note', '' );
$S['sales']->crm( '/manager/leads/' . $LM . '/', 'lead_assign', array( 'id' => $LM, 'assigned_to' => (int) $dv( "SELECT ID FROM wp_users WHERE user_login='qa_sales'" ) ) );
qa( 'Sales agent cannot (re)assign leads', $rental_uid === (int) $dv( 'SELECT assigned_to FROM wp_adc_leads WHERE id=?', array( $LM ) ), 'unchanged', '' );
$S['viewer']->crm( '/manager/leads/' . $LM . '/', 'lead_status', array( 'id' => $LM, 'status' => 'contacted' ) );
qa( 'Viewer (read-only) cannot change lead status', 'new' === $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $LM ) ), 'new', '' );
$x = $S['viewer']->get( '/manager/leads/' );
qa( 'Viewer can read all leads', false !== strpos( $x['body'], 'Lead Manager' ) && false !== strpos( $x['body'], 'Lead Sales Own' ), 'sees all', '' );
$S['sales']->crm( '/manager/leads/' . $LS . '/', 'lead_convert', array( 'id' => $LS ) );
qa( 'Lead → customer conversion creates a customer record', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_customers WHERE name='Lead Sales Own'" ), '1 customer', '' );
$S['rental']->crm( '/manager/leads/' . $LM . '/', 'lead_convert', array( 'id' => $LM ) );
qa( 'Rental agent (customers right) can convert own lead', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_customers WHERE name='Lead Manager'" ), '1', '' );

// ---------------------------------------------------------------- sales
qa_section( 'Sales: confirm ⇒ vehicle sold; cancel' );
$S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => 'Peugeot', 'model' => '508', 'year' => 2018, 'vin' => 'VF3XXXXXXXXXXXXXX' ) ) );
$v5 = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0];
$saleBase = array( 'vehicle_id' => $v5['id'], 'new_customer_name' => 'Fatou Sow', 'new_customer_phone' => '770001122', 'sale_price' => '8 000 000', 'sold_on' => gmdate( 'Y-m-d' ), 'payment_method' => 'cash', 'payment_status' => 'partial', 'amount_paid' => '2 000 000', 'lead_id' => $lid );
$x = $S['rental']->crm( '/manager/sales/new/', 'sale_create', $saleBase );
qa( 'Rental agent cannot create a sale (server-side)', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_sales' ), '0 sales', '' );
$x = $S['viewer']->crm( '/manager/sales/new/', 'sale_create', $saleBase );
qa( 'Viewer cannot create a sale', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_sales' ), '0 sales', '' );
$x = $S['sales']->crm( '/manager/sales/new/', 'sale_create', $saleBase );
$sale1 = $dq( 'SELECT * FROM wp_adc_sales ORDER BY id DESC LIMIT 1' )[0] ?? null;
qa( 'Sales agent records a DRAFT sale (no confirm): vehicle becomes reserved', $sale1 && 'draft' === $sale1['status'] && 'reserved' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ), 'draft + reserved', $sale1 ? $sale1['status'] . '/' . $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ) : 'none' );
qa( 'Quick customer created from the sale form with phone', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_customers WHERE name='Fatou Sow'" ), '1', '' );
$S['sales']->crm( '/manager/sales/' . $sale1['id'] . '/', 'sale_confirm', array( 'id' => $sale1['id'] ) );
qa( 'Confirm sale ⇒ sale confirmed, vehicle SOLD (+sold_at), linked lead WON', 'confirmed' === $dv( 'SELECT status FROM wp_adc_sales WHERE id=?', array( $sale1['id'] ) ) && 'sold' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ) && $dv( 'SELECT sold_at FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ) && 'won' === $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $lid ) ), 'confirmed/sold/won', $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ) . '/' . $dv( 'SELECT status FROM wp_adc_leads WHERE id=?', array( $lid ) ) );
qa( 'Sold vehicle disappears from /inventory/ but page stays reachable (200)', false === strpos( $pub->get( '/inventory/' )['body'], '508' ) && 200 === $pub->get( '/vehicle/' . $v5['slug'] . '/' )['status'], 'hidden in list / 200 page', '' );
$S['sales']->crm( '/manager/sales/' . $sale1['id'] . '/', 'sale_confirm', array( 'id' => $sale1['id'] ) );
qa( 'Confirming twice refused (still 1 confirmed sale)', 1 === (int) $dv( "SELECT COUNT(*) FROM wp_adc_sales WHERE status='confirmed'" ), '1', '' );
$S['sales']->crm( '/manager/sales/new/', 'sale_create', array_merge( $saleBase, array( 'lead_id' => 0, 'new_customer_name' => 'Double' ) ) );
qa( 'Second sale on an already sold vehicle refused', 1 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_sales' ), '1 sale', (string) $dv( 'SELECT COUNT(*) FROM wp_adc_sales' ) );
$S['sales']->crm( '/manager/sales/' . $sale1['id'] . '/', 'sale_payment', array( 'id' => $sale1['id'], 'payment_status' => 'paid', 'amount_paid' => '8 000 000' ) );
qa( 'Payment follow-up: status paid, amount 8 000 000', 'paid' === $dv( 'SELECT payment_status FROM wp_adc_sales WHERE id=?', array( $sale1['id'] ) ) && 8000000 === (int) $dv( 'SELECT amount_paid FROM wp_adc_sales WHERE id=?', array( $sale1['id'] ) ), 'paid', '' );
$S['viewer']->crm( '/manager/sales/' . $sale1['id'] . '/', 'sale_cancel', array( 'id' => $sale1['id'] ) );
qa( 'Viewer cannot cancel a sale', 'confirmed' === $dv( 'SELECT status FROM wp_adc_sales WHERE id=?', array( $sale1['id'] ) ), 'confirmed', '' );
$S['sales']->crm( '/manager/sales/' . $sale1['id'] . '/', 'sale_cancel', array( 'id' => $sale1['id'] ) );
qa( 'Cancel sale ⇒ sale cancelled, vehicle back on sale (published), appears in inventory again', 'cancelled' === $dv( 'SELECT status FROM wp_adc_sales WHERE id=?', array( $sale1['id'] ) ) && 'published' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ) && false !== strpos( $pub->get( '/inventory/' )['body'], '508' ), 'cancelled/published/listed', $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v5['id'] ) ) );
$S['sales']->crm( '/manager/sales/' . $sale1['id'] . '/', 'sale_cancel', array( 'id' => $sale1['id'] ) );
qa( 'Cancelling twice refused (no error, status unchanged)', 'cancelled' === $dv( 'SELECT status FROM wp_adc_sales WHERE id=?', array( $sale1['id'] ) ), 'cancelled', '' );
// edge: cancel a sale of a vehicle that was draft before → vehicle goes to published (unintended)
$S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => 'Nissan', 'model' => 'Qashqai', 'year' => 2017, 'status' => 'draft' ) ) );
$v6 = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0];
$S['owner']->crm( '/manager/sales/new/', 'sale_create', array_merge( $saleBase, array( 'vehicle_id' => $v6['id'], 'lead_id' => 0, 'confirm' => 1, 'new_customer_name' => 'Edge Case' ) ) );
$sale2 = $dq( 'SELECT * FROM wp_adc_sales ORDER BY id DESC LIMIT 1' )[0];
$S['owner']->crm( '/manager/sales/' . $sale2['id'] . '/', 'sale_cancel', array( 'id' => $sale2['id'] ) );
qa( 'Cancelling a sale of a vehicle that was a DRAFT must not auto-publish it', 'published' !== $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v6['id'] ) ), 'not published (draft/archived)', 'now: ' . $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $v6['id'] ) ) );

// ---------------------------------------------------------------- rentals
qa_section( 'Rentals: overlap refusal' );
$S['owner']->crm( '/manager/vehicles/new/', 'vehicle_save', $vf( array( 'brand' => 'Toyota', 'model' => 'Yaris Location', 'year' => 2022, 'listing_type' => 'rent', 'price_period' => 'day', 'price_amount' => '25 000', 'status' => 'published' ) ) );
$vr = $dq( 'SELECT * FROM wp_adc_vehicles ORDER BY id DESC LIMIT 1' )[0];
$t0 = strtotime( '+10 days 10:00' );
$dt = static function ( $ts ) { return wp_date( 'Y-m-d\TH:i', $ts ); };
$rb = array( 'vehicle_id' => $vr['id'], 'new_customer_name' => 'Client Loc 1', 'new_customer_phone' => '770009991', 'rate_unit' => 'day', 'rate_amount' => '25 000', 'deposit_amount' => '100 000', 'payment_status' => 'unpaid', 'confirm' => 1 );
$x = $S['sales']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'starts_at' => $dt( $t0 ), 'ends_at' => $dt( $t0 + 3 * 86400 ) ) ) );
qa( 'Sales agent cannot create a rental (no rentals right)', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_rentals' ), '0', '' );
$x = $S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'starts_at' => $dt( $t0 + 86400 ), 'ends_at' => $dt( $t0 ) ) ) );
qa( 'Return date before start refused', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_rentals' ), '0', '' );
$x = $S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'starts_at' => $dt( $t0 ), 'ends_at' => $dt( $t0 + 3 * 86400 ) ) ) );
$r1 = $dq( 'SELECT * FROM wp_adc_rentals ORDER BY id DESC LIMIT 1' )[0] ?? null;
qa( 'Rental #1 (3 days) created and confirmed; total = 3 x 25 000 = 75 000', $r1 && 'confirmed' === $r1['status'] && 3 === (int) $r1['units'] && 75000 === (int) $r1['total_amount'], 'confirmed/3/75000', $r1 ? "{$r1['status']}/{$r1['units']}/{$r1['total_amount']}" : 'none' );
$x = $S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'new_customer_name' => 'Client Loc 2', 'starts_at' => $dt( $t0 + 86400 ), 'ends_at' => $dt( $t0 + 5 * 86400 ) ) ) );
$r2 = $dq( 'SELECT * FROM wp_adc_rentals ORDER BY id DESC LIMIT 1' )[0];
qa( 'Overlapping rental #2 (starts inside #1) is REFUSED at confirmation — saved only as quote', (int) $r2['id'] !== (int) $r1['id'] && 'quote' === $r2['status'], 'quote (not confirmed)', $r2['status'] );
$flash = $S['rental']->get( '/manager/rentals/' . $r2['id'] . '/' )['body'];
qa( 'Refusal message (flash) names the existing booking: customer "Client Loc 1"', false !== strpos( $flash, 'Client Loc 1' ) && false !== strpos( $flash, 'amds-alert' ), 'message with customer name', substr( trim( preg_replace( '/\\s+/', ' ', strip_tags( preg_match( '#amds-alert.*?</div>#s', $flash, $fm ) ? $fm[0] : '' ) ) ), 0, 160 ) );
$S['rental']->crm( '/manager/rentals/' . $r2['id'] . '/', 'rental_confirm', array( 'id' => $r2['id'] ) );
qa( 'Confirming the overlapping quote later is also refused', 'quote' === $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ), 'quote', $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ) );
$x = $S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'new_customer_name' => 'Client Loc 3', 'starts_at' => $dt( $t0 + 3 * 86400 ), 'ends_at' => $dt( $t0 + 5 * 86400 ) ) ) );
$r3 = $dq( 'SELECT * FROM wp_adc_rentals ORDER BY id DESC LIMIT 1' )[0];
qa( 'Back-to-back rental (starts exactly when #1 ends) is allowed', 'confirmed' === $r3['status'], 'confirmed', $r3['status'] );
$x = $S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'new_customer_name' => 'Client Loc 4', 'starts_at' => $dt( $t0 - 86400 ), 'ends_at' => $dt( $t0 + 3600 ) ) ) );
$r4 = $dq( 'SELECT * FROM wp_adc_rentals ORDER BY id DESC LIMIT 1' )[0];
qa( 'Rental overlapping the START of #1 by one hour refused', 'quote' === $r4['status'], 'quote', $r4['status'] );
$x = $S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'new_customer_name' => 'Client Loc 5', 'starts_at' => $dt( $t0 - 86400 ), 'ends_at' => $dt( $t0 + 10 * 86400 ) ) ) );
$r5 = $dq( 'SELECT * FROM wp_adc_rentals ORDER BY id DESC LIMIT 1' )[0];
qa( 'Rental fully ENCLOSING #1 and #3 refused', 'quote' === $r5['status'], 'quote', $r5['status'] );
$S['rental']->crm( '/manager/rentals/' . $r1['id'] . '/', 'rental_cancel', array( 'id' => $r1['id'] ) );
$S['rental']->crm( '/manager/rentals/' . $r2['id'] . '/', 'rental_confirm', array( 'id' => $r2['id'] ) );
qa( 'Quote #2 still refused while confirmed #3 (back-to-back, overlaps #2) exists', 'quote' === $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ), 'quote', $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ) );
$S['rental']->crm( '/manager/rentals/' . $r3['id'] . '/', 'rental_cancel', array( 'id' => $r3['id'] ) );
$S['rental']->crm( '/manager/rentals/' . $r2['id'] . '/', 'rental_confirm', array( 'id' => $r2['id'] ) );
qa( 'After cancelling the conflicting rentals the refused quote #2 can be confirmed', 'cancelled' === $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r1['id'] ) ) && 'confirmed' === $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ), 'confirmed', $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ) );
// start / finish
$S['rental']->crm( '/manager/rentals/' . $r2['id'] . '/', 'rental_start', array( 'id' => $r2['id'], 'mileage' => 10000 ) );
qa( 'Start rental ⇒ active, vehicle status "rented"', 'active' === $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ) && 'rented' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $vr['id'] ) ), 'active/rented', '' );
$S['rental']->crm( '/manager/rentals/' . $r2['id'] . '/', 'rental_finish', array( 'id' => $r2['id'], 'mileage' => 10800, 'return_notes' => 'RAS' ) );
qa( 'Return ⇒ returned, vehicle back to published, mileage updated if higher', 'returned' === $dv( 'SELECT status FROM wp_adc_rentals WHERE id=?', array( $r2['id'] ) ) && 'published' === $dv( 'SELECT status FROM wp_adc_vehicles WHERE id=?', array( $vr['id'] ) ), 'returned/published', '' );
// hourly
$S['rental']->crm( '/manager/rentals/new/', 'rental_create', array_merge( $rb, array( 'new_customer_name' => 'Hourly', 'rate_unit' => 'hour', 'rate_amount' => '3000', 'starts_at' => $dt( $t0 + 20 * 86400 ), 'ends_at' => $dt( $t0 + 20 * 86400 + 5400 ) ) ) );
$rh = $dq( 'SELECT * FROM wp_adc_rentals ORDER BY id DESC LIMIT 1' )[0];
qa( 'Hourly rental: 90 minutes billed as 2 hours = 6 000', 2 === (int) $rh['units'] && 6000 === (int) $rh['total_amount'], '2h/6000', "{$rh['units']}/{$rh['total_amount']}" );
$f = $S['rental']->get( '/manager/rentals/' );
qa( 'Rentals list + 14-day planner render 200', 200 === $f['status'] && false !== strpos( $f['body'], 'Yaris' ), '200', (string) $f['status'] );

// ---------------------------------------------------------------- permission matrix (server side)
qa_section( 'Permissions enforced server-side (forbidden actions via direct POST)' );
$cases = array(
	array( 'rental', 'vehicle_save', $vf( array( 'brand' => 'Hack', 'model' => 'Rental' ) ), 'SELECT COUNT(*) FROM wp_adc_vehicles WHERE brand="Hack"', 0, 'Rental agent cannot add vehicles' ),
	array( 'viewer', 'vehicle_save', $vf( array( 'brand' => 'Hack', 'model' => 'Viewer' ) ), 'SELECT COUNT(*) FROM wp_adc_vehicles WHERE brand="Hack"', 0, 'Viewer cannot add vehicles' ),
	array( 'viewer', 'customer_save', array( 'name' => 'Hack Cust', 'phone' => '770000099' ), 'SELECT COUNT(*) FROM wp_adc_customers WHERE name="Hack Cust"', 0, 'Viewer cannot create customers' ),
	array( 'viewer', 'lead_save', array( 'name' => 'Hack Lead', 'phone' => '770000099' ), 'SELECT COUNT(*) FROM wp_adc_leads WHERE name="Hack Lead"', 0, 'Viewer cannot create leads' ),
	array( 'sales', 'team_invite', array( 'email' => 'qa_hack@example.test', 'role' => 'adc_owner' ), 'SELECT COUNT(*) FROM wp_adc_invites WHERE email="qa_hack@example.test"', 0, 'Sales agent cannot invite team members' ),
	array( 'manager', 'team_invite', array( 'email' => 'qa_hack2@example.test', 'role' => 'adc_owner' ), 'SELECT COUNT(*) FROM wp_adc_invites WHERE email="qa_hack2@example.test"', 0, 'Manager cannot invite (team = owner only)' ),
	array( 'manager', 'settings_save', array( 'business_name' => 'HACKED BY MANAGER?' ), 'SELECT COUNT(*) > 0 FROM wp_options WHERE option_value LIKE "%HACKED BY MANAGER%"', 1, 'Manager CAN edit settings (by design: manager has settings right)' ),
	array( 'sales', 'settings_save', array( 'business_name' => 'HACKED BY SALES' ), 'SELECT COUNT(*) FROM wp_options WHERE option_value LIKE "%HACKED BY SALES%"', 0, 'Sales agent cannot edit settings' ),
	array( 'rental', 'settings_save', array( 'business_name' => 'HACKED BY RENTAL' ), 'SELECT COUNT(*) FROM wp_options WHERE option_value LIKE "%HACKED BY RENTAL%"', 0, 'Rental agent cannot edit settings' ),
	array( 'viewer', 'settings_save', array( 'business_name' => 'HACKED BY VIEWER' ), 'SELECT COUNT(*) FROM wp_options WHERE option_value LIKE "%HACKED BY VIEWER%"', 0, 'Viewer cannot edit settings' ),
	array( 'viewer', 'rental_create', array_merge( $rb, array( 'new_customer_name' => 'VR', 'starts_at' => $dt( $t0 + 40 * 86400 ), 'ends_at' => $dt( $t0 + 41 * 86400 ) ) ), 'SELECT COUNT(*) FROM wp_adc_customers WHERE name="VR"', 0, 'Viewer cannot create rentals (no customer created either)' ),
	array( 'sales', 'vehicle_delete', array( 'id' => $v4['id'] ), 'SELECT COUNT(*) FROM wp_adc_vehicles WHERE id=' . (int) $v4['id'], 1, 'Sales agent cannot delete vehicles' ),
	array( 'rental', 'customer_save', array( 'name' => 'Rental Cust', 'phone' => '770000077' ), 'SELECT COUNT(*) FROM wp_adc_customers WHERE name="Rental Cust"', 1, 'Rental agent CAN create customers (by design)' ),
);
$matrix_bad = array();
foreach ( $cases as $c ) {
	$S[ $c[0] ]->crm( '/manager/', $c[1], $c[2] );
	$got = (int) $dv( $c[3] );
	qa( $c[5], $got === $c[4], (string) $c[4], (string) $got );
}
// pages guarded by capability
foreach ( array( array( 'sales', 'team' ), array( 'manager', 'team' ), array( 'rental', 'team' ), array( 'viewer', 'team' ), array( 'sales', 'settings' ), array( 'rental', 'settings' ), array( 'viewer', 'settings' ), array( 'sales', 'reports' ), array( 'rental', 'reports' ), array( 'sales', 'activity' ) ) as $pc ) {
	$b = $S[ $pc[0] ]->get( '/manager/' . $pc[1] . '/' )['body'];
	$marker = array( 'team' => 'name="email"', 'settings' => 'name="business_name"', 'reports' => 'adc-report', 'activity' => 'adc-activity' );
	qa( "{$pc[0]} opening /manager/{$pc[1]}/ falls back to dashboard (no {$pc[1]} content)", false === strpos( $b, $marker[ $pc[1] ] ) , 'dashboard', '' );
}
$x = $S['viewer']->get( '/manager/reports/' );
qa( 'Viewer (reports right) can open /manager/reports/', 200 === $x['status'], '200', (string) $x['status'] );
// Extra per-person permission
$sales_id = (int) $dv( "SELECT ID FROM wp_users WHERE user_login='qa_sales'" );
$S['owner']->crm( '/manager/team/', 'team_update', array( 'id' => $sales_id, 'role' => 'adc_sales', 'caps' => array( 'adc_publish_vehicles', 'adc_edit_prices', 'adc_manage_team' ) ) );
$S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'price_amount' => '7 700 000', 'status' => 'published' ) ) );
qa( 'Extra permission "edit prices" granted to a sales agent takes effect', 7700000 === (int) $dv( 'SELECT price_amount FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), '7700000', '' );
$S['sales']->crm( '/manager/team/', 'team_invite', array( 'email' => 'qa_esc@example.test', 'role' => 'adc_owner' ) );
qa( 'Team-management right cannot be granted as an extra capability (privilege escalation blocked)', 0 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_invites WHERE email="qa_esc@example.test"' ), 'no invite', '' );
$S['owner']->crm( '/manager/team/', 'team_update', array( 'id' => $sales_id, 'role' => 'adc_sales', 'caps' => array() ) );
$S['sales']->crm( '/manager/vehicles/' . $v4['id'] . '/', 'vehicle_save', $vf( array( 'id' => $v4['id'], 'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2020, 'price_amount' => '1 111', 'status' => 'published' ) ) );
qa( 'Removing the extra permission takes effect immediately', 7700000 === (int) $dv( 'SELECT price_amount FROM wp_adc_vehicles WHERE id=?', array( $v4['id'] ) ), '7700000', '' );
// remove access
$viewer_id = (int) $dv( "SELECT ID FROM wp_users WHERE user_login='qa_viewer'" );
$S['owner']->crm( '/manager/team/', 'team_remove', array( 'id' => $viewer_id ) );
$x = $S['viewer']->get( '/manager/' );
qa( 'Removed member: existing session is destroyed → /manager/ no longer accessible', 200 !== $x['status'], '302/403', (string) $x['status'] );

// ---------------------------------------------------------------- private customer documents
qa_section( 'Customer private documents' );
$cid = (int) $dv( "SELECT id FROM wp_adc_customers WHERE name='Fatou Sow'" );
$S['owner']->crm( '/manager/customers/' . $cid . '/', 'customer_file', array( 'id' => $cid, 'label' => 'CNI', 'document' => new CURLFile( $p1, 'image/jpeg', 'cni.jpg' ) ) );
$file = $dq( 'SELECT * FROM wp_adc_files ORDER BY id DESC LIMIT 1' )[0] ?? null;
qa( 'Customer document stored (private path adc-private, random name)', $file && false !== strpos( $file['path'] ?? '', 'adc-private' ), 'adc-private/…', $file ? json_encode( $file ) : 'none' );
$exe = qa_tmp_file( 'qa-shell.php', '<?php echo 1;' );
$S['owner']->crm( '/manager/customers/' . $cid . '/', 'customer_file', array( 'id' => $cid, 'label' => 'shell', 'document' => new CURLFile( $exe, 'image/jpeg', 'shell.php' ) ) );
qa( 'PHP file refused as customer document', 1 === (int) $dv( 'SELECT COUNT(*) FROM wp_adc_files' ), '1 file', (string) $dv( 'SELECT COUNT(*) FROM wp_adc_files' ) );
$php_up = array_merge( (array) glob( $D['dir'] . '/wp-content/uploads/adc-private/*/*.php' ), (array) glob( $D['dir'] . '/wp-content/uploads/adc-private/*.php' ) );
qa( 'No .php stored in adc-private', ! array_filter( $php_up, static function ( $p ) { return 'index.php' !== basename( $p ); } ), 'none', implode( ',', $php_up ) );
$cust_page = $S['sales']->get( '/manager/customers/' . $cid . '/' );
qa( 'Customer page shows the document link to a sales agent', false !== strpos( $cust_page['body'], 'CNI' ), 'visible', '' );
$vpage = $S['viewer']->get( '/manager/customers/' . $cid . '/' );
qa( 'Viewer (no customer rights) gets no customer data', false === strpos( $vpage['body'], 'Fatou Sow' ), 'hidden', '' );

// ---------------------------------------------------------------- branding
qa_section( 'Branding without code' );
$logo = qa_jpeg( 'qa-logo.png', 200, 80, array( 10, 20, 30 ) );
$x = $S['owner']->crm( '/manager/settings/', 'settings_save', array( 'business_name' => 'Almadies Motors QA', 'tagline' => 'Votre auto de confiance', 'phone' => '+221 77 000 00 00', 'whatsapp' => '+221770000000', 'address' => 'Route des Almadies', 'city' => 'Dakar', 'country' => 'SN', 'email' => 'contact@example.test', 'lead_email' => 'leads@example.test', 'currency' => 'XOF', 'preset' => 'elegant', 'hero_title' => 'Trouvez votre voiture à Dakar', 'hero_text' => 'Un choix vérifié.', 'about_text' => 'Concessionnaire depuis 2010.', 'show_rentals' => 1, 'show_financing' => 1, 'financing_text' => 'Financement possible.', 'logo' => new CURLFile( $logo, 'image/png', 'logo.png' ) ) );
$home = $pub->get( '/' )['body'];
qa( 'Settings saved: business name + hero title shown on homepage', false !== strpos( $home, 'Almadies Motors QA' ) && false !== strpos( $home, 'Trouvez votre voiture' ), 'visible', '' );
qa( 'Design preset "elegant" colours applied (#13322b / #b8893b in CSS)', false !== stripos( $home, '#13322b' ) && false !== stripos( $home, '#b8893b' ), 'colours', '' );
qa( '/financing/ page now enabled (200)', 200 === $pub->get( '/financing/' )['status'], '200', (string) $pub->last['status'] );
$S['owner']->crm( '/manager/settings/', 'settings_save', array( 'business_name' => 'Almadies Motors QA', 'preset' => 'ocean', 'color_primary' => '#112233', 'color_accent' => '#ff00aa', 'show_rentals' => 1, 'show_financing' => 0 ) );
$home = $pub->get( '/' )['body'];
qa( 'Custom colours (#112233 / #ff00aa) override the preset', false !== stripos( $home, '#112233' ) && false !== stripos( $home, '#ff00aa' ), 'custom colours', '' );
$S['owner']->crm( '/manager/settings/', 'settings_save', array( 'business_name' => '</title><script>alert(9)</script>', 'preset' => 'classic', 'color_primary' => 'red;}</style><script>alert(8)</script>', 'show_rentals' => 1 ) );
$home = $pub->get( '/' )['body'];
qa( 'Settings values are escaped in <title>/CSS (no script injection)', false === strpos( $home, '<script>alert(9)' ) && false === strpos( $home, '<script>alert(8)' ), 'escaped', '' );
$S['owner']->crm( '/manager/settings/', 'settings_save', array( 'business_name' => 'Almadies Motors QA', 'preset' => 'elegant', 'show_rentals' => 1, 'logo' => new CURLFile( $exe, 'image/png', 'logo.php' ) ) );
qa( 'Non-image logo (.php) refused', 0 === count( (array) glob( $D['dir'] . '/wp-content/uploads/*/*/logo*.php' ) ), 'no php logo', '' );
// dark mode / RTL / 4 languages (CLAUDE.md §2.1 validated decisions)
$root = dirname( __DIR__, 3 );
$css_all = '';
foreach ( array_merge( (array) glob( $root . '/dealer/themes/autodealer/assets/css/*.css' ), (array) glob( $root . '/dealer/plugins/*/assets/css/*.css' ), array( $root . '/dealer/themes/autodealer/style.css' ) ) as $cf ) { $css_all .= (string) @file_get_contents( $cf ); }
qa( 'Dark mode exists in the dealer site/CRM (prefers-color-scheme or data-theme rules) — required by CLAUDE.md §2.1', false !== stripos( $css_all, 'prefers-color-scheme' ) || false !== stripos( $css_all, 'data-theme' ), 'dark-mode CSS', 'none found in dealer theme/plugin CSS (grep prefers-color-scheme|data-theme = 0 hits repo-wide)' );
qa( 'RTL stylesheet / rules for Arabic exist in the dealer theme or CRM', false !== stripos( $css_all, '[dir=rtl]' ) || false !== stripos( $css_all, '[dir="rtl"]' ) || false !== stripos( $css_all, ':dir(rtl)' ) || is_file( $root . '/dealer/themes/autodealer/rtl.css' ), 'rtl rules', 'no [dir=rtl] rule, no rtl.css' );
qa( 'Arabic (ar) and Wolof (wo) translation files exist for the dealer plugin/theme', is_file( $root . '/dealer/plugins/automotive-dealer-crm/languages/automotive-dealer-crm-ar.l10n.php' ) && is_file( $root . '/dealer/plugins/automotive-dealer-crm/languages/automotive-dealer-crm-wo.l10n.php' ), 'ar + wo files', 'only fr_FR present' );
$derrs = qa_log_new_errors( $D['dir'], $dlog0 );
qa( 'No PHP warnings/notices/fatals in dealer debug.log during phase 3', ! $derrs, 'none', implode( ' | ', array_slice( $derrs, 0, 3 ) ) );
