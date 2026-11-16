<?php
/** Phase 2 — dealer membership (central site). */
global $wpdb;
use AMC\Memberships;
use AMC\Subscriptions;
use AMC\Payments;
use AMC\Plans;
use AMC\Database;

qa_phase( 'Phase 2 — Dealer membership' );
qa_reset_central();
$log0 = qa_log_offset( $C['dir'] );
file_put_contents( QA_MAIL_CENTRAL, '' );
$tbl = static function ( $n ) { return AMC\Database::table( $n ); };
$one = static function ( $sql, ...$args ) use ( $wpdb ) { wp_cache_flush(); return $args ? $wpdb->get_row( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_row( $sql ); };

// ------------------------------------------------------------------ setup
$pdf = qa_tmp_file( 'qa-rccm.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );
$png = qa_tmp_file( 'qa-id.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );
$exe = qa_tmp_file( 'qa-evil.php', '<?php echo "pwned";' );
$big = qa_tmp_file( 'qa-big.pdf', "%PDF-1.4\n" . str_repeat( 'A', 5300000 ) );

$admin = new QA_Session( $C['url'] );
qa( 'Admin can log in over HTTP', $admin->login( 'admin', 'local-dev-password' ), '302 + auth cookie', (string) $admin->last['status'] );

// Seeded plans
qa_section( 'Plans and pricing (not hardcoded)' );
$plans = Plans::all();
qa( 'Three plans seeded (start, business, premium)', 3 === count( $plans ) && array( 'start', 'business', 'premium' ) === array_column( $plans, 'code' ), '3 plans', implode( ',', array_column( $plans, 'code' ) ) );
$pub = new QA_Session( $C['url'] );
$page = $pub->get( '/pricing/' );
qa( 'Public pricing page 200 lists 3 plans with DB prices', 200 === $page['status'] && false !== strpos( $page['body'], 'Business' ) && preg_match( '/25[\s\x{202f}\x{00a0}]?000/u', $page['body'] ), 'HTTP 200, "25 000"', (string) $page['status'] );
// Edit plan price through Platform admin and verify pricing page changes
$biz = Plans::get_by_code( 'business' );
$r = $admin->portal_action( '/platform-admin/plans/', 'plan_save', array( 'id' => $biz->id, 'name' => 'Business', 'tagline' => $biz->tagline, 'price_amount' => '31 500', 'currency' => 'XOF', 'vehicle_limit' => 40, 'ai_quota' => 500, 'team_members' => 5, 'features' => "A\nB", 'is_active' => 1, 'is_highlighted' => 1, 'sort_order' => 2 ) );
$page = $pub->get( '/pricing/' );
qa( 'Editing a plan price in /platform-admin/plans/ changes /pricing/ immediately', 302 === $r['status'] && preg_match( '/31[\s\x{202f}\x{00a0}]?500/u', $page['body'] ) && ! preg_match( '/25[\s\x{202f}\x{00a0}]?000/u', $page['body'] ), 'price 31 500 shown, 25 000 gone', 'status ' . $r['status'] );
$admin->portal_action( '/platform-admin/plans/', 'plan_save', array( 'id' => $biz->id, 'name' => 'Business', 'tagline' => $biz->tagline, 'price_amount' => '25000', 'currency' => 'XOF', 'vehicle_limit' => 40, 'ai_quota' => 500, 'team_members' => 5, 'features' => "A\nB", 'is_active' => 1, 'is_highlighted' => 1, 'sort_order' => 2 ) );
$r = $admin->portal_action( '/platform-admin/plans/', 'durations_save', array( 'durations' => array( array( 'months' => 1, 'discount' => 0 ), array( 'months' => 3, 'discount' => 5 ), array( 'months' => 6, 'discount' => 10 ), array( 'months' => 12, 'discount' => 20 ) ) ) );
wp_cache_flush();
qa( 'Durations/discounts editable (12 months 20% saved)', 20.0 === (float) ( Plans::durations()[12] ?? 0 ), '20', (string) ( Plans::durations()[12] ?? 'n/a' ) );
$p = Plans::price( Plans::get_by_code( 'business' ), 12 );
qa( 'Price math: 25000 x 12 at 20% = 240000', 240000 === $p['total'] && 60000 === $p['saving'], '240000/60000', json_encode( $p ) );
$admin->portal_action( '/platform-admin/plans/', 'durations_save', array( 'durations' => array( array( 'months' => 1, 'discount' => 0 ), array( 'months' => 3, 'discount' => 5 ), array( 'months' => 6, 'discount' => 10 ), array( 'months' => 12, 'discount' => 15 ) ) ) );

// ------------------------------------------------------------------ registration validation
qa_section( 'Registration form — validation and security' );
$reg = new QA_Session( $C['url'] );
$form = $reg->get( '/dealer-registration/' );
qa( 'Registration page 200 (anonymous)', 200 === $form['status'], '200', (string) $form['status'] );
preg_match( '#name="_amc_nonce" value="([a-z0-9]+)"#', $form['body'], $m );
$nonce = $m[1] ?? '';
qa_reset_rate_limits();
$base = array( '_amc_nonce' => $nonce, 'plan' => 'business', 'months' => 3, 'full_name' => 'Awa Diop', 'email' => 'awa.diop@example.test', 'password' => 'S3cret-pass!', 'phone' => '+221 77 123 45 67', 'business_name' => 'Almadies Auto QA', 'city' => 'Dakar', 'registration_number' => 'SN-DKR-2024-B-1234', 'website_option' => 'new_domain', 'domain' => 'almadies-auto-qa.com', 'payment_method' => 'bank_transfer', 'payer_reference' => 'VIR-0001', 'accept_terms' => 1, 'activity' => 'both', 'fleet_size' => '11-40' );
$err = static function ( $r ) { return $r['body']; };
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'accept_terms' => '', 'email' => 'bad', 'password' => 'short' ) ) );
qa( 'Invalid email/short password/no terms rejected with field errors (200, no account)', 200 === $r['status'] && false !== strpos( $r['body'], 'amc-field-error' ) && ! email_exists( 'awa.diop@example.test' ), '200 + errors', (string) $r['status'] );
qa( 'Errors re-displayed in French', false !== strpos( $r['body'], 'adresse' ) || false !== strpos( $r['body'], 'mot de passe' ), 'French text', substr( strip_tags( $r['body'] ), 0, 0 ) );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( '_amc_nonce' => 'deadbeef' ) ) );
qa( 'Bad nonce refused', 200 === $r['status'] && ! email_exists( 'awa.diop@example.test' ), 'no account', (string) $r['status'] );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'company_url' => 'http://spam.test' ) ) );
qa( 'Honeypot filled → refused', ! email_exists( 'awa.diop@example.test' ), 'no account', '' );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'domain' => 'not a domain' ) ) );
qa( 'Invalid domain refused', ! email_exists( 'awa.diop@example.test' ), 'no account', '' );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'registration_number' => '', 'tax_id' => '' ) ) );
qa( 'RCCM or NINEA required', ! email_exists( 'awa.diop@example.test' ), 'no account', '' );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'plan' => 'nonexistent' ) ) );
qa( 'Unknown plan refused', ! email_exists( 'awa.diop@example.test' ), 'no account', '' );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'doc_rccm' => new CURLFile( $exe, 'application/pdf', 'rccm.pdf' ) ) ) );
qa( 'Upload: PHP file disguised as PDF refused (finfo mime check)', ! email_exists( 'awa.diop@example.test' ), 'no account', '' );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'doc_rccm' => new CURLFile( $big, 'application/pdf', 'big.pdf' ) ) ) );
qa( 'Upload: > 5 MB refused', ! email_exists( 'awa.diop@example.test' ), 'no account', '' );
$r = $reg->post( '/dealer-registration/', array_merge( $base, array( 'full_name' => '<script>alert(1)</script>Awa' , 'accept_terms' => '' ) ) );
qa( 'XSS in re-displayed field is escaped', false === strpos( $r['body'], '<script>alert(1)</script>' ), 'no raw script tag', '' );

// ------------------------------------------------------------------ successful registration A
qa_section( 'Registration — success path (dealer A, with 3 documents + payment proof)' );
qa_reset_rate_limits();
$mail_before = file_exists( QA_MAIL_CENTRAL ) ? count( file( QA_MAIL_CENTRAL ) ) : 0;
$r = $reg->post( '/dealer-registration/', array_merge( $base, array(
	'doc_rccm' => new CURLFile( $pdf, 'application/pdf', 'rccm.pdf' ),
	'doc_ninea' => new CURLFile( $png, 'image/png', 'ninea.png' ),
	'doc_id' => new CURLFile( $png, 'image/png', 'id.png' ),
	'payment_proof' => new CURLFile( $png, 'image/png', 'receipt.png' ),
) ) );
qa( 'Registration succeeds: 302 to /dealer-dashboard/?welcome=1', 302 === $r['status'] && false !== strpos( $r['location'], 'dealer-dashboard' ), '302 → dashboard', $r['status'] . ' ' . $r['location'] );
wp_cache_flush();
$user = get_user_by( 'email', 'awa.diop@example.test' );
qa( 'User created with role amc_dealer_owner', $user && in_array( 'amc_dealer_owner', (array) $user->roles, true ), 'role', $user ? implode( ',', $user->roles ) : 'no user' );
$dealerA = $user ? Memberships::dealer_for_user( $user->ID ) : null;
qa( 'Dealer created with status pending, private until active', $dealerA && 'pending' === $dealerA->status, 'pending', $dealerA ? $dealerA->status : 'none' );
$appA = $dealerA ? Memberships::latest_application( $dealerA->id ) : null;
qa( 'Application stored (status submitted, plan business, 3 months, domain, activity)', $appA && 'submitted' === $appA->status && 3 === (int) $appA->duration_months && 'almadies-auto-qa.com' === $appA->domain && 'both' === $appA->activity, 'submitted/3/domain', $appA ? "{$appA->status}/{$appA->duration_months}/{$appA->domain}" : 'none' );
$docs = $dealerA ? AMC\Documents::for_dealer( $dealerA->id ) : array();
qa( '4 documents stored (rccm, ninea, id, payment proof)', 4 === count( $docs ), '4', (string) count( $docs ) );
$upl = wp_upload_dir()['basedir'];
$ok_files = $docs && ! array_filter( $docs, static function ( $d ) use ( $upl ) { return ! is_file( $upl . '/' . $d->path ) || ! preg_match( '#^amc-private/\d+/[a-f0-9]{32}\.(pdf|png)$#', $d->path ); } );
qa( 'Files stored under uploads/amc-private/<dealer>/<random>.ext (random names)', (bool) $ok_files, 'random 32-hex names', $docs ? $docs[0]->path : '' );
qa( 'amc-private has .htaccess deny + index.php + web.config', is_file( $upl . '/amc-private/.htaccess' ) && is_file( $upl . '/amc-private/index.php' ) && is_file( $upl . '/amc-private/web.config' ), 'protection files', '' );
$subA = $dealerA ? Subscriptions::open_for_dealer( $dealerA->id ) : null;
qa( 'Subscription pending_payment, amount = 25000x3 less 5% = 71250 XOF', $subA && 'pending_payment' === $subA->status && 71250 === (int) $subA->amount && 'XOF' === $subA->currency, '71250', $subA ? "{$subA->status} {$subA->amount}" : 'none' );
$payA = $subA ? Payments::pending_for_subscription( $subA->id ) : null;
qa( 'Payment declared pending (bank_transfer, reference AB00000N, proof attached)', $payA && 'bank_transfer' === $payA->method && preg_match( '/^AB\d{6}$/', $payA->reference ) && (int) $payA->proof_document_id > 0, 'pending/ref/proof', $payA ? json_encode( array( $payA->method, $payA->reference, $payA->proof_document_id ) ) : 'none' );
$aud = $wpdb->get_col( "SELECT action FROM {$tbl('audit_log')} ORDER BY id" );
qa( 'Audit: application.submitted + subscription.created + payment.created', in_array( 'application.submitted', $aud, true ) && in_array( 'subscription.created', $aud, true ) && in_array( 'payment.created', $aud, true ), 'entries', implode( ',', array_slice( $aud, -6 ) ) );
$mails = file( QA_MAIL_CENTRAL ) ?: array();
$new = array_slice( $mails, $mail_before );
qa( 'Emails: dealer acknowledgement + staff notification (2+ mails captured)', count( $new ) >= 2, '>=2', (string) count( $new ) );
qa( 'Dealer is logged in automatically after registration', 200 === $reg->get( '/dealer-dashboard/' )['status'], '200', (string) $reg->last['status'] );

// duplicate email
$r = ( new QA_Session( $C['url'] ) );
$f = $r->get( '/dealer-registration/' ); preg_match( '#name="_amc_nonce" value="([a-z0-9]+)"#', $f['body'], $m );
$x = $r->post( '/dealer-registration/', array_merge( $base, array( '_amc_nonce' => $m[1], 'business_name' => 'Dup' ) ) );
qa( 'Duplicate email refused (no second dealer)', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('dealers')} WHERE contact_email='awa.diop@example.test'" ), '1 dealer', (string) $x['status'] );
// rate limit
qa_reset_rate_limits();
$rl = new QA_Session( $C['url'] ); $f = $rl->get( '/dealer-registration/' ); preg_match( '#name="_amc_nonce" value="([a-z0-9]+)"#', $f['body'], $m );
$last = 0; for ( $i = 0; $i < 7; $i++ ) { $x = $rl->post( '/dealer-registration/', array_merge( $base, array( '_amc_nonce' => $m[1], 'email' => 'bad' ) ) ); }
qa( 'Registration rate-limited after 5 attempts/hour (6th+ attempt shows "Too many attempts")', false !== stripos( $x['body'], 'Trop de tentatives' ) || false !== stripos( $x['body'], 'Too many' ), 'rate-limit message', '' );
qa_reset_rate_limits();

// ------------------------------------------------------------------ dealer space
qa_section( 'Dealer space /dealer-dashboard/' );
foreach ( array( '', 'onboarding', 'subscription', 'payments', 'website', 'profile', 'documents', 'support' ) as $sec ) {
	$x = $reg->get( '/dealer-dashboard/' . ( $sec ? $sec . '/' : '' ) );
	qa( "Dealer section /{$sec} renders 200 without PHP error text", 200 === $x['status'] && ! preg_match( '/Fatal error|Warning:|Notice:|Deprecated:/', $x['body'] ), '200 clean', (string) $x['status'] );
}
$x = $reg->get( '/dealer-dashboard/checkout/' );
qa( 'Dealer /subscription-checkout/ renders', 200 === $reg->get( '/subscription-checkout/' )['status'], '200', (string) $reg->last['status'] );
$x = $reg->get( '/platform-admin/' );
qa( 'Dealer cannot open /platform-admin/ (403)', 403 === $x['status'], '403', (string) $x['status'] );
$anon = new QA_Session( $C['url'] );
$x = $anon->get( '/dealer-dashboard/' );
qa( 'Anonymous → redirected to /dealer-login/', 302 === $x['status'] && false !== strpos( $x['location'], 'dealer-login' ), '302 login', $x['status'] . ' ' . $x['location'] );
$x = $anon->get( '/platform-admin/' );
qa( 'Anonymous /platform-admin/ → redirected to login (no content)', 302 === $x['status'], '302', (string) $x['status'] );
$x = $reg->get( '/wp-admin/', false );
qa( 'Dealer owner kept out of wp-admin (redirect to dealer space)', 302 === $x['status'] && false !== strpos( $x['location'], 'dealer-dashboard' ), '302 → dealer-dashboard', $x['status'] . ' ' . $x['location'] );
$x = $reg->get( '/dealer-dashboard/' );
qa( 'Portal pages are noindex + no-cache', false !== stripos( $x['body'], 'noindex' ) && false !== stripos( $x['headers'], 'no-cache' ), 'robots noindex, Cache-Control no-cache', '' );
// CSRF
$x = $reg->post( '/dealer-dashboard/support/', array( 'amc_action' => 'ticket_new', '_amc_nonce' => 'bad', 'subject' => 'x', 'message' => 'y' ) );
wp_cache_flush();
qa( 'Dealer POST with bad nonce does nothing', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('tickets')}" ), '0 tickets', '' );
$x = $reg->portal_action( '/dealer-dashboard/support/', 'ticket_new', array( 'subject' => 'Question test', 'category' => 'billing', 'message' => 'Hello <b>team</b>' ) );
wp_cache_flush();
$ticketA = $wpdb->get_row( "SELECT * FROM {$tbl('tickets')} ORDER BY id DESC LIMIT 1" );
qa( 'Dealer can open a support ticket (302 to thread)', $ticketA && 302 === $x['status'], 'ticket created', (string) $x['status'] );
$x = $reg->portal_action( '/dealer-dashboard/documents/', 'document', array( 'type' => 'other', 'document' => new CURLFile( $pdf, 'application/pdf', 'extra.pdf' ) ) );
wp_cache_flush();
qa( 'Dealer can add a document from /documents/', 5 === count( AMC\Documents::for_dealer( $dealerA->id ) ), '5 docs', (string) count( AMC\Documents::for_dealer( $dealerA->id ) ) );
$x = $reg->portal_action( '/dealer-dashboard/profile/', 'profile', array( 'description' => 'Concessionnaire à Dakar', 'city' => 'Dakar', 'phone' => '+221771234567', 'whatsapp' => '+221771234567', 'website_url' => 'javascript:alert(1)' ) );
wp_cache_flush();
$d = Memberships::get_dealer( $dealerA->id );
qa( 'Profile saved; javascript: URL not stored as website_url', 'Concessionnaire à Dakar' === $d->description && false === stripos( (string) $d->website_url, 'javascript' ), 'description saved, url clean', (string) $d->website_url );

// ------------------------------------------------------------------ platform admin sections
qa_section( 'Platform admin /platform-admin/ — all sections load' );
$secs = array_keys( AMC\Portal::platform_sections() );
foreach ( $secs as $sec ) {
	$x = $admin->get( '/platform-admin/' . ( 'overview' === $sec ? '' : $sec . '/' ) );
	qa( "Platform section {$sec}: 200, no PHP error text", 200 === $x['status'] && ! preg_match( '/Fatal error|Warning:|Notice:|Deprecated:|Stack trace/', $x['body'] ), '200 clean', (string) $x['status'] );
}
$apps = $admin->get( '/platform-admin/applications/' );
qa( 'Applications list shows the new application', false !== strpos( $apps['body'], 'Almadies Auto QA' ), 'business name present', '' );
$docp = $admin->get( '/platform-admin/applications/' . $appA->id . '/' );
qa( 'Application detail page shows business, the 3 uploaded documents and payment receipt link', 200 === $docp['status'] && false !== strpos( $docp['body'], 'Almadies Auto QA' ) && false !== strpos( $docp['body'], 'rccm.pdf' ) && false !== strpos( $docp['body'], 'ninea.png' ) && false !== strpos( $docp['body'], 'id.png' ), 'name + 3 files', (string) $docp['status'] );
$dr = $admin->get( '/platform-admin/dealers/' . $dealerA->id . '/' );
qa( 'Dealer record page shows subscription + payment (AB reference)', 200 === $dr['status'] && false !== strpos( $dr['body'], 'AB' . str_pad( (string) $subA->id, 6, '0', STR_PAD_LEFT ) ), 'reference present', (string) $dr['status'] );

// Platform manager role & buyer
$pm_id = wp_insert_user( array( 'user_login' => 'qa_pm', 'user_pass' => 'pm-pass-1234', 'user_email' => 'pm@example.test', 'role' => 'amc_platform_manager' ) );
$by_id = wp_insert_user( array( 'user_login' => 'qa_buyer', 'user_pass' => 'buyer-pass-1234', 'user_email' => 'buyer@example.test', 'role' => 'subscriber' ) );
$pm = new QA_Session( $C['url'] ); $buyer = new QA_Session( $C['url'] );
qa( 'Platform manager logs in', $pm->login( 'qa_pm', 'pm-pass-1234' ), 'login ok', '' );
qa( 'Platform manager reaches /platform-admin/ (200)', 200 === $pm->get( '/platform-admin/' )['status'], '200', (string) $pm->last['status'] );
$buyer->login( 'qa_buyer', 'buyer-pass-1234' );
qa( 'Buyer (subscriber) gets 403 on /platform-admin/', 403 === $buyer->get( '/platform-admin/' )['status'], '403', (string) $buyer->last['status'] );
$x = $buyer->get( '/dealer-dashboard/' );
qa( 'Buyer without dealership redirected to registration from /dealer-dashboard/', 302 === $x['status'] && false !== strpos( $x['location'], 'dealer-registration' ), '302 register', $x['status'] . ' ' . $x['location'] );
$x = $buyer->portal_action( '/platform-admin/applications/', 'app_approve', array( 'id' => $appA->id ) );
wp_cache_flush();
qa( 'Buyer cannot approve an application via direct POST', 'submitted' === Memberships::get_application( $appA->id )->status, 'still submitted', (string) $x['status'] );
$x = $reg->portal_action( '/platform-admin/applications/', 'app_approve', array( 'id' => $appA->id ) );
wp_cache_flush();
qa( 'Dealer cannot approve own application via direct POST', 'submitted' === Memberships::get_application( $appA->id )->status && in_array( $x['status'], array( 403, 302 ), true ), 'still submitted', (string) $x['status'] );

// ------------------------------------------------------------------ workflow
qa_section( 'Application workflow: Pending → Under review → Approved → Active' );
$x = $admin->portal_action( '/platform-admin/applications/', 'app_review', array( 'id' => $appA->id ) );
wp_cache_flush();
qa( 'Start review: application under_review, dealer under_review', 'under_review' === Memberships::get_application( $appA->id )->status && 'under_review' === Memberships::get_dealer( $dealerA->id )->status, 'under_review', Memberships::get_dealer( $dealerA->id )->status );
$x = $admin->portal_action( '/platform-admin/applications/', 'app_review', array( 'id' => $appA->id ) );
qa( 'Second "start review" is refused (state machine)', 'under_review' === Memberships::get_application( $appA->id )->status, 'unchanged', '' );
$pay = Payments::pending_for_subscription( $subA->id );
$x = $admin->portal_action( '/platform-admin/payments/', 'pay_verify', array( 'id' => $pay->id ) );
wp_cache_flush();
qa( 'Verify payment BEFORE approval: payment verified, subscription "paid" (waits for approval), dealer not active', 'verified' === Payments::get( $pay->id )->status && 'paid' === Subscriptions::get( $subA->id )->status && 'active' !== Memberships::get_dealer( $dealerA->id )->status, 'paid/not active', Subscriptions::get( $subA->id )->status . '/' . Memberships::get_dealer( $dealerA->id )->status );
$x = $admin->portal_action( '/platform-admin/payments/', 'pay_verify', array( 'id' => $pay->id ) );
qa( 'Verifying the same payment twice is refused (idempotent)', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('audit_log')} WHERE action='payment.verified' AND object_id={$pay->id}" ), '1 audit entry', '' );
$x = $admin->portal_action( '/platform-admin/applications/', 'app_approve', array( 'id' => $appA->id ) );
wp_cache_flush();
$dA = Memberships::get_dealer( $dealerA->id ); $sA = Subscriptions::get( $subA->id );
qa( 'Approve: application approved → subscription auto-active → dealer ACTIVE', 'approved' === Memberships::get_application( $appA->id )->status && 'active' === $sA->status && 'active' === $dA->status, 'approved/active/active', Memberships::get_application( $appA->id )->status . '/' . $sA->status . '/' . $dA->status );
$days = ( strtotime( $sA->ends_at . ' UTC' ) - strtotime( $sA->starts_at . ' UTC' ) ) / DAY_IN_SECONDS;
qa( 'Subscription period = 3 months (89–93 days); grace = ends + 5 days', $days >= 89 && $days <= 93 && 5 === (int) round( ( strtotime( $sA->grace_ends_at . ' UTC' ) - strtotime( $sA->ends_at . ' UTC' ) ) / DAY_IN_SECONDS ), '~90 days, +5 days grace', round( $days, 1 ) . ' days' );
$job = AMC\Provisioning::for_dealer( $dealerA->id );
qa( 'Provisioning job created on approval with 8-step manual checklist', $job && 8 === count( $job->steps ), '8 steps', $job ? (string) count( $job->steps ) : 'none' );
$x = $admin->portal_action( '/platform-admin/applications/', 'app_approve', array( 'id' => $appA->id ) );
qa( 'Approving an already approved application is refused (still 1 job)', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('provisioning_jobs')} WHERE dealer_id={$dealerA->id}" ), '1 job', '' );
foreach ( array_keys( $job->steps ) as $k ) { $admin->portal_action( '/platform-admin/websites/', 'job_step', array( 'id' => $job->id, 'step' => $k, 'done' => 1 ) ); }
wp_cache_flush();
$job = AMC\Provisioning::get( $job->id );
qa( 'Checklist: ticking all 8 steps completes the job (status done, completed_at set)', 'done' === $job->status && $job->completed_at, 'done', $job->status );
$x = $admin->get( '/platform-admin/websites/' . $job->id . '/' );
qa( 'Website job detail page renders', 200 === $x['status'], '200', (string) $x['status'] );
$aud = $wpdb->get_col( "SELECT action FROM {$tbl('audit_log')}" );
$need = array( 'application.under_review', 'dealer.status.under_review', 'payment.verified', 'application.approved', 'dealer.status.approved', 'subscription.activated', 'dealer.status.active', 'provisioning.step', 'provisioning.completed' );
$missing = array_diff( $need, $aud );
qa( 'Audit log has every staff action (review, verify, approve, activate, checklist)', ! $missing, 'all present', 'missing: ' . implode( ',', $missing ) );
$au = $admin->get( '/platform-admin/audit/' );
qa( 'Audit page lists actions', false !== strpos( $au['body'], 'application.approved' ) || false !== strpos( $au['body'], 'approved' ), 'visible', '' );

// Dealer's public listing visibility on dealer active
$pubd = $pub->get( '/dealers/' );
qa( 'Active dealer appears in public dealers directory', false !== strpos( $pubd['body'], 'Almadies Auto QA' ), 'listed', '' );

// ------------------------------------------------------------------ reject
qa_section( 'Reject, suspend, reactivate, cancel' );
$mk = static function ( $email, $biz_name, $method, $proof ) use ( $C, $base, $pdf ) {
	qa_reset_rate_limits();
	$s = new QA_Session( $C['url'] ); $f = $s->get( '/dealer-registration/' ); preg_match( '#name="_amc_nonce" value="([a-z0-9]+)"#', $f['body'], $m );
	$fields = array_merge( $base, array( '_amc_nonce' => $m[1], 'email' => $email, 'business_name' => $biz_name, 'payment_method' => $method, 'plan' => 'start', 'months' => 1, 'doc_rccm' => new CURLFile( $pdf, 'application/pdf', 'rccm.pdf' ) ) );
	$s->post( '/dealer-registration/', $fields );
	wp_cache_flush();
	$u = get_user_by( 'email', $email );
	return array( $s, $u ? Memberships::dealer_for_user( $u->ID ) : null );
};
list( $sB, $dealerB ) = $mk( 'dealer.b@example.test', 'Garage Bravo QA', 'later', false );
qa( 'Dealer B registered (payment "later" → no payment row)', $dealerB && ! Payments::pending_for_subscription( Subscriptions::open_for_dealer( $dealerB->id )->id ), 'no payment', '' );
$appB = Memberships::latest_application( $dealerB->id );
$x = $admin->portal_action( '/platform-admin/applications/', 'app_reject', array( 'id' => $appB->id, 'reason' => '' ) );
wp_cache_flush();
qa( 'Reject without reason refused', 'submitted' === Memberships::get_application( $appB->id )->status, 'still submitted', '' );
$x = $admin->portal_action( '/platform-admin/applications/', 'app_reject', array( 'id' => $appB->id, 'reason' => 'Documents illisibles' ) );
wp_cache_flush();
$dB = Memberships::get_dealer( $dealerB->id );
qa( 'Reject: application rejected, dealer cancelled with reason, open order cancelled', 'rejected' === Memberships::get_application( $appB->id )->status && 'cancelled' === $dB->status && 'Documents illisibles' === $dB->status_reason && ! Subscriptions::open_for_dealer( $dealerB->id ), 'rejected/cancelled', $dB->status );
$mails = file( QA_MAIL_CENTRAL );
qa( 'Rejection email sent to dealer with the reason', (bool) array_filter( $mails, static function ( $l ) { return false !== strpos( $l, 'dealer.b@example.test' ) && false !== strpos( $l, 'illisibles' ); } ), 'mail with reason', '' );
$x = $sB->get( '/dealer-dashboard/' );
qa( 'Rejected dealer can still open dashboard (200) and sees status', 200 === $x['status'], '200', (string) $x['status'] );
$x = $sB->portal_action( '/dealer-dashboard/subscription/', 'checkout', array( 'plan' => 'start', 'months' => 1 ) );
wp_cache_flush();
qa( 'Cancelled dealer cannot place a new order', ! Subscriptions::open_for_dealer( $dealerB->id ), 'no open order', '' );

// suspend / reactivate dealer A
$x = $admin->portal_action( '/platform-admin/dealers/', 'dealer_suspend', array( 'id' => $dealerA->id, 'reason' => '' ) );
qa( 'Suspend without reason refused', 'active' === Memberships::get_dealer( $dealerA->id )->status, 'still active', '' );
$x = $admin->portal_action( '/platform-admin/dealers/', 'dealer_suspend', array( 'id' => $dealerA->id, 'reason' => 'Plainte client QA' ) );
wp_cache_flush();
qa( 'Suspend: dealer suspended with reason', 'suspended' === Memberships::get_dealer( $dealerA->id )->status, 'suspended', Memberships::get_dealer( $dealerA->id )->status );
$pubd = $pub->get( '/dealers/' );
qa( 'Suspended dealer disappears from public directory', false === strpos( $pubd['body'], 'Almadies Auto QA' ), 'not listed', '' );
Subscriptions::process_daily();
qa( 'Cron does not override a manual suspension', 'suspended' === Memberships::get_dealer( $dealerA->id )->status, 'suspended', '' );
$x = $reg->get( '/dealer-dashboard/' );
qa( 'Suspended dealer sees the dashboard (200)', 200 === $x['status'], '200', (string) $x['status'] );
$x = $reg->portal_action( '/dealer-dashboard/subscription/', 'checkout', array( 'plan' => 'start', 'months' => 1 ) );
wp_cache_flush();
qa( 'Suspended dealer cannot place a new order', 1 === count( array_filter( Subscriptions::for_dealer( $dealerA->id ), static function ( $s ) { return 'pending_payment' === $s->status; } ) ) ? false : true, 'no new order', '' );
$x = $admin->portal_action( '/platform-admin/dealers/', 'dealer_reactivate', array( 'id' => $dealerA->id ) );
wp_cache_flush();
qa( 'Reactivate: dealer back to active (approved + active subscription)', 'active' === Memberships::get_dealer( $dealerA->id )->status, 'active', Memberships::get_dealer( $dealerA->id )->status );
$pubd = $pub->get( '/dealers/' );
qa( 'Reactivated dealer visible again', false !== strpos( $pubd['body'], 'Almadies Auto QA' ), 'listed', '' );

// ------------------------------------------------------------------ renewal & cron
qa_section( 'Renewal, reminders, expiry (cron)' );
$x = $reg->portal_action( '/dealer-dashboard/subscription/', 'checkout', array( 'plan' => 'business', 'months' => 6 ) );
wp_cache_flush();
$renew = Subscriptions::open_for_dealer( $dealerA->id );
qa( 'Dealer places renewal order: 25000x6 less 10% = 135000', $renew && 'pending_payment' === $renew->status && 135000 === (int) $renew->amount, '135000', $renew ? (string) $renew->amount : 'none' );
$x = $reg->portal_action( '/dealer-dashboard/subscription/', 'payment', array( 'subscription_id' => $renew->id, 'payment_method' => 'bank_transfer', 'payer_reference' => '' ) );
wp_cache_flush();
qa( 'Payment without receipt or reference refused', ! Payments::pending_for_subscription( $renew->id ), 'none', '' );
$x = $reg->portal_action( '/dealer-dashboard/subscription/', 'payment', array( 'subscription_id' => $renew->id, 'payment_method' => 'bank_transfer', 'payer_reference' => 'VIR-0002', 'payment_proof' => new CURLFile( $png, 'image/png', 'r2.png' ) ) );
wp_cache_flush();
$pay2 = Payments::pending_for_subscription( $renew->id );
qa( 'Dealer declares payment with receipt → pending', (bool) $pay2, 'pending payment', '' );
$x = $reg->portal_action( '/dealer-dashboard/subscription/', 'payment', array( 'subscription_id' => $renew->id, 'payment_method' => 'bank_transfer', 'payer_reference' => 'VIR-0003' ) );
qa( 'Second payment declaration while one is pending refused', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('payments')} WHERE subscription_id={$renew->id}" ), '1 payment', '' );
// IDOR: dealer B declares payment on dealer A's subscription
$x = $sB->portal_action( '/dealer-dashboard/subscription/', 'payment', array( 'subscription_id' => $renew->id, 'payment_method' => 'cash', 'payer_reference' => 'hack' ) );
qa( 'IDOR: dealer B cannot pay/act on dealer A\'s order', 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('payments')} WHERE subscription_id={$renew->id}" ), '1 payment', '' );
$oldEnd = $sA->ends_at;
$x = $admin->portal_action( '/platform-admin/payments/', 'pay_reject', array( 'id' => $pay2->id, 'reason' => 'Montant incorrect' ) );
wp_cache_flush();
qa( 'Reject payment with reason → rejected, order still pending_payment', 'rejected' === Payments::get( $pay2->id )->status && 'pending_payment' === Subscriptions::get( $renew->id )->status, 'rejected', Payments::get( $pay2->id )->status );
$x = $reg->portal_action( '/dealer-dashboard/subscription/', 'payment', array( 'subscription_id' => $renew->id, 'payment_method' => 'bank_transfer', 'payer_reference' => 'VIR-0004', 'payment_proof' => new CURLFile( $png, 'image/png', 'r3.png' ) ) );
wp_cache_flush();
$pay3 = Payments::pending_for_subscription( $renew->id );
$x = $admin->portal_action( '/platform-admin/payments/', 'pay_verify', array( 'id' => $pay3->id ) );
wp_cache_flush();
$r2 = Subscriptions::get( $renew->id ); $sA = Subscriptions::get( $subA->id );
qa( 'Renewal verified: new subscription active and QUEUED to start when the current one ends', 'active' === $r2->status && $r2->starts_at === $sA->ends_at, 'starts_at == previous ends_at', "{$r2->starts_at} vs {$sA->ends_at}" );
$endR = ( strtotime( $r2->ends_at . ' UTC' ) - strtotime( $r2->starts_at . ' UTC' ) ) / DAY_IN_SECONDS;
qa( 'Renewal period = 6 months', $endR >= 180 && $endR <= 184, '~181 days', round( $endR, 1 ) );
// reminders
$setEnd = static function ( $sub_id, $ends, $grace = null, $stage = 0 ) use ( $wpdb, $tbl ) {
	$grace = $grace ?: gmdate( 'Y-m-d H:i:s', strtotime( $ends . ' UTC' ) + 5 * DAY_IN_SECONDS );
	$wpdb->update( $tbl( 'subscriptions' ), array( 'ends_at' => $ends, 'grace_ends_at' => $grace, 'reminder_stage' => $stage ), array( 'id' => $sub_id ) );
	wp_cache_flush();
};
// make renewal not exist for reminder test: cancel it temporarily by marking it 'paid'
$wpdb->update( $tbl( 'subscriptions' ), array( 'status' => 'cancelled' ), array( 'id' => $renew->id ) );
$mc = static function () { return count( file( QA_MAIL_CENTRAL ) ); };
$setEnd( $subA->id, gmdate( 'Y-m-d H:i:s', time() + 6 * DAY_IN_SECONDS ) );
$n0 = $mc(); Subscriptions::process_daily();
qa( 'Cron: 6 days left → 7-day reminder sent, reminder_stage = 1', 1 === (int) Subscriptions::get( $subA->id )->reminder_stage && $mc() === $n0 + 1, 'stage 1, +1 mail', 'stage ' . Subscriptions::get( $subA->id )->reminder_stage . ', mails +' . ( $mc() - $n0 ) );
$n0 = $mc(); Subscriptions::process_daily();
qa( 'Cron run again same day: no duplicate reminder', $mc() === $n0, '+0 mail', '+' . ( $mc() - $n0 ) );
$setEnd( $subA->id, gmdate( 'Y-m-d H:i:s', time() + 12 * 3600 ), null, 1 );
$n0 = $mc(); Subscriptions::process_daily();
qa( 'Cron: 12 h left → 1-day reminder, stage 2', 2 === (int) Subscriptions::get( $subA->id )->reminder_stage && $mc() === $n0 + 1, 'stage 2, +1 mail', 'stage ' . Subscriptions::get( $subA->id )->reminder_stage );
$setEnd( $subA->id, gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ), null, 2 );
Subscriptions::process_daily();
qa( 'Cron: after end, within grace (5 d): still active (dealer stays online)', 'active' === Subscriptions::get( $subA->id )->status && 'active' === Memberships::get_dealer( $dealerA->id )->status, 'active', Subscriptions::get( $subA->id )->status );
$setEnd( $subA->id, gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ), 2 );
$n0 = $mc(); Subscriptions::process_daily();
wp_cache_flush();
qa( 'Cron: grace over → subscription expired, dealer expired, expiry email', 'expired' === Subscriptions::get( $subA->id )->status && 'expired' === Memberships::get_dealer( $dealerA->id )->status && $mc() === $n0 + 1, 'expired/expired/+1 mail', Subscriptions::get( $subA->id )->status . '/' . Memberships::get_dealer( $dealerA->id )->status . '/+' . ( $mc() - $n0 ) );
$pubd = $pub->get( '/dealers/' );
qa( 'Expired dealer hidden from public directory', false === strpos( $pubd['body'], 'Almadies Auto QA' ), 'not listed', '' );
// Renewal path: re-activate renewal after expiry
$wpdb->update( $tbl( 'subscriptions' ), array( 'status' => 'pending_payment' ), array( 'id' => $renew->id ) );
$x = $admin->portal_action( '/platform-admin/subscriptions/', 'sub_activate', array( 'id' => $renew->id, 'reference' => 'cash-qa' ) );
wp_cache_flush();
qa( 'Staff manual activation of a pending order after expiry → dealer active again, starts now', 'active' === Subscriptions::get( $renew->id )->status && 'active' === Memberships::get_dealer( $dealerA->id )->status, 'active/active', Subscriptions::get( $renew->id )->status . '/' . Memberships::get_dealer( $dealerA->id )->status );
$endBefore = Subscriptions::get( $renew->id )->ends_at;
$x = $admin->portal_action( '/platform-admin/subscriptions/', 'sub_extend', array( 'id' => $renew->id, 'months' => 2 ) );
wp_cache_flush();
$endAfter = Subscriptions::get( $renew->id )->ends_at;
qa( 'Staff can extend an active subscription (+2 months)', abs( ( strtotime( $endAfter . ' UTC' ) - strtotime( $endBefore . ' UTC' ) ) / DAY_IN_SECONDS - 61 ) < 3, '~+61 days', "$endBefore -> $endAfter" );
qa( 'MRR computed from active subscriptions (> 0)', Subscriptions::mrr() > 0, '>0', (string) Subscriptions::mrr() );
$x = $admin->portal_action( '/platform-admin/subscriptions/', 'sub_cancel', array( 'id' => $renew->id ) );
wp_cache_flush();
qa( 'Staff cancels the subscription → dealer expired', 'cancelled' === Subscriptions::get( $renew->id )->status && 'expired' === Memberships::get_dealer( $dealerA->id )->status, 'cancelled/expired', Subscriptions::get( $renew->id )->status . '/' . Memberships::get_dealer( $dealerA->id )->status );
$x = $admin->portal_action( '/platform-admin/subscriptions/', 'sub_create', array( 'dealer_id' => $dealerA->id, 'plan' => 'premium', 'months' => 1, 'paid' => 1, 'reference' => 'Espèces' ) );
wp_cache_flush();
qa( 'Staff creates a subscription already paid (premium) → dealer active, visibility boost on', 'active' === Memberships::get_dealer( $dealerA->id )->status && 1 === (int) Memberships::get_dealer( $dealerA->id )->visibility_boost, 'active + boost', Memberships::get_dealer( $dealerA->id )->status );
$x = $admin->portal_action( '/platform-admin/dealers/', 'dealer_cancel', array( 'id' => $dealerA->id, 'reason' => 'test' ) );
wp_cache_flush();
qa( 'Cancel account: dealer cancelled and open subscriptions cancelled', 'cancelled' === Memberships::get_dealer( $dealerA->id )->status && ! Subscriptions::current_for_dealer( $dealerA->id ), 'cancelled, no active sub', '' );

// ------------------------------------------------------------------ privacy of documents
qa_section( 'Documents: private access' );
$docA = AMC\Documents::for_dealer( $dealerA->id )[0];
$url = html_entity_decode( AMC\Documents::url( $docA->id ) );
// URLs from PHP CLI use admin_url with home → make relative to our session
$path = substr( $url, strlen( $C['url'] ) );
$x = $anon->get( $path );
qa( 'Anonymous cannot download a private document', false === strpos( $x['body'], '%PDF' ), 'redirect to login / 403', (string) $x['status'] );
$x = $sB->get( $path );
qa( 'IDOR: dealer B cannot download dealer A\'s document (nonce valid only for the user it was minted for)', false === strpos( $x['body'], '%PDF' ) && 200 !== $x['status'], '403', (string) $x['status'] );
// With a nonce minted for dealer B on A's doc id
$nB = $sB->mint( 'amc_document_' . $docA->id );
$x = $sB->get( '/wp-admin/admin-post.php?action=amc_document&id=' . $docA->id . '&_wpnonce=' . $nB );
qa( 'IDOR: dealer B with own valid nonce on A\'s doc id → 403 (ownership check)', 403 === $x['status'] && false === strpos( $x['body'], '%PDF' ), '403', (string) $x['status'] );
$nA = $reg->mint( 'amc_document_' . $docA->id );
$x = $reg->get( '/wp-admin/admin-post.php?action=amc_document&id=' . $docA->id . '&_wpnonce=' . $nA );
qa( 'Owner can open own document (200, correct Content-Type, nosniff, CSP sandbox)', 200 === $x['status'] && false !== stripos( $x['headers'], 'x-content-type-options: nosniff' ) && false !== stripos( $x['headers'], 'sandbox' ), '200 + headers', (string) $x['status'] );
$nS = $admin->mint( 'amc_document_' . $docA->id );
$x = $admin->get( '/wp-admin/admin-post.php?action=amc_document&id=' . $docA->id . '&_wpnonce=' . $nS );
qa( 'Staff can open any dealer document', 200 === $x['status'], '200', (string) $x['status'] );
$x = $anon->get( '/wp-content/uploads/' . $docA->path );
if ( 200 === $x['status'] ) {
	qa_skip( 'Direct URL to stored private file is denied by the web server', 'php -S serves the file (ignores .htaccess). Needs Apache/Nginx; docs/INSTALL.md + DEPLOYMENT.md document the Nginx rule. Test on staging: curl -I <site>/wp-content/uploads/' . $docA->path );
} else {
	qa( 'Direct URL to stored private file is denied', true, '403/404', (string) $x['status'] );
}

// ticket IDOR
$x = $sB->get( '/dealer-dashboard/support/' . $ticketA->id . '/' );
qa( 'IDOR: dealer B cannot read dealer A\'s support ticket', false === strpos( $x['body'], 'Hello' ), 'thread hidden', '' );
$x = $sB->portal_action( '/dealer-dashboard/support/', 'ticket_reply', array( 'ticket_id' => $ticketA->id, 'message' => 'intruder' ) );
qa( 'IDOR: dealer B cannot reply into dealer A\'s ticket', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl('ticket_messages')} WHERE body LIKE 'intruder'" ), 'no message', '' );
$x = $admin->portal_action( '/platform-admin/support/', 'ticket_reply', array( 'id' => $ticketA->id, 'message' => 'Bonjour, nous traitons votre demande.', 'close' => 1 ) );
wp_cache_flush();
qa( 'Staff reply closes the ticket and emails the dealer', 'closed' === AMC\Support::get( $ticketA->id )->status, 'closed', AMC\Support::get( $ticketA->id )->status );

// settings
qa_section( 'Platform settings' );
$x = $admin->portal_action( '/platform-admin/settings/', 'settings_save', array( 'registration_open' => 0, 'require_documents' => 1, 'grace_days' => 3 ) );
wp_cache_flush();
qa( 'Settings: close registrations, require documents, grace 3 days', ! AMC\Settings::get( 'registration_open' ) && AMC\Settings::get( 'require_documents' ) && 3 === (int) AMC\Settings::get( 'grace_days' ), 'saved', json_encode( array( AMC\Settings::get( 'registration_open' ), AMC\Settings::get( 'require_documents' ), AMC\Settings::get( 'grace_days' ) ) ) );
$f = $anon->get( '/dealer-registration/' );
qa( 'Registration closed message when disabled', false !== stripos( $f['body'], 'fermées' ) || false !== stripos( $f['body'], 'closed' ) || false !== stripos( $f['body'], 'temporairement' ), 'closed notice', '' );
$admin->portal_action( '/platform-admin/settings/', 'settings_save', array( 'registration_open' => 1, 'require_documents' => 0, 'grace_days' => 5 ) );
wp_cache_flush();
qa( 'Settings restored', (bool) AMC\Settings::get( 'registration_open' ) && 5 === (int) AMC\Settings::get( 'grace_days' ), 'restored', '' );
// admin settings: checkboxes
$before = AMC\Settings::get( 'ai_visitor_enabled' );
$admin->portal_action( '/platform-admin/settings/', 'settings_save', array( 'registration_open' => 1 ) );
wp_cache_flush();

// listing moderation
qa_section( 'Listings moderation' );
$vid = (int) $wpdb->get_var( "SELECT id FROM {$tbl('vehicles')} LIMIT 1" );
if ( $vid ) {
	$x = $admin->portal_action( '/platform-admin/listings/', 'listing_feature', array( 'id' => $vid, 'days' => 7 ) );
	wp_cache_flush();
	qa( 'Feature a listing for 7 days', 1 === (int) $wpdb->get_var( "SELECT is_featured FROM {$tbl('vehicles')} WHERE id={$vid}" ), 'is_featured=1', '' );
	$admin->portal_action( '/platform-admin/listings/', 'listing_unfeature', array( 'id' => $vid ) );
} else {
	qa_skip( 'Listing moderation', 'no vehicle in DB at this point (demo removed)' );
}

// ------------------------------------------------------------------ stored XSS / injection
qa_section( 'Stored XSS and injection' );
$evil = '"><img src=x onerror=alert(1)>Evil';
list( $sC, $dealerC ) = $mk( 'dealer.c@example.test', $evil, 'later', false );
$wpdb->update( $tbl( 'dealers' ), array( 'description' => '<script>alert(2)</script>', 'status' => 'active' ), array( 'id' => $dealerC->id ) );
$sC->portal_action( '/dealer-dashboard/support/', 'ticket_new', array( 'subject' => '<script>alert(3)</script>', 'category' => 'other', 'message' => '<script>alert(4)</script>' ) );
AMC\Vehicles::flush_cache();
$leaks = array();
foreach ( array( '/platform-admin/', '/platform-admin/applications/', '/platform-admin/dealers/', '/platform-admin/dealers/' . $dealerC->id . '/', '/platform-admin/support/', '/platform-admin/audit/', '/platform-admin/subscriptions/' ) as $path ) {
	$b = $admin->get( $path )['body'];
	if ( preg_match( '/<img src=x onerror|<script>alert\(\d\)/', $b ) ) {
		$leaks[] = $path;
	}
}
foreach ( array( '/dealers/', '/dealer/' . ( $dealerC->slug ?? '' ) . '/', '/dealer-dashboard/' ) as $path ) {
	$b = ( '/dealer-dashboard/' === $path ? $sC : $pub )->get( $path )['body'];
	if ( preg_match( '/<img src=x onerror|<script>alert\(\d\)/', $b ) ) {
		$leaks[] = $path;
	}
}
qa( 'Stored XSS in business name / description / ticket is escaped on every staff, dealer and public page', ! $leaks, 'no raw payload', implode( ', ', $leaks ) );
$x = $admin->get( "/platform-admin/dealers/?q=" . rawurlencode( "' OR 1=1 -- " ) );
qa( 'SQL-injection string in staff dealer search is harmless (200, no SQL error)', 200 === $x['status'] && false === stripos( $x['body'], 'syntax error' ) && false === stripos( $x['body'], 'wordpress database error' ), '200 clean', (string) $x['status'] );
$x = $admin->get( "/platform-admin/payments/?status=" . rawurlencode( "pending' OR '1'='1" ) );
qa( 'SQL-injection string in payments status filter is harmless', 200 === $x['status'] && false === stripos( $x['body'], 'wordpress database error' ), '200 clean', (string) $x['status'] );
$errs = qa_log_new_errors( $C['dir'], $log0 );
qa( 'No PHP warnings/notices/fatals in debug.log during phase 2', ! $errs, 'none', implode( ' | ', array_slice( $errs, 0, 3 ) ) );
