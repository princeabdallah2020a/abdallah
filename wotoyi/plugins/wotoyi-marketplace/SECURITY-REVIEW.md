# wotoyi-marketplace v0.1.0 — Security Review (CISO)

- **Reviewer:** CISO (independent of the author). Date: 2026-11-17.
- **Scope:** `wotoyi/plugins/wotoyi-marketplace/` (all 36 REST routes, sync/HMAC, pairing, secrets, SQL, I/O handling, rate limiting, tenant isolation, IDOR, SSRF, data protection, local transport). Rules: CLAUDE.md §4, PROJECT.md §14.3–14.5.
- **Method:** I read the code line by line. I ran `bin/check.sh` (php -l OK; **173 passed, 0 failed, 4,662 assertions**, PHP 8.3.6). I also wrote 8 throwaway exploit probes (`/tmp/wotoyi-probe/probe.php`) that run on the plugin's own test shim. The plugin code was not modified.
- **Labels:** **[VERIFIED-PROBE]** = I reproduced it with a probe. **[VERIFIED-CODE]** = I confirmed it by reading the code. **[UNVERIFIED]** = it depends on real WordPress/MySQL behaviour that I could not run here.

> **Caveat on the test suite:** the suite runs on a shim, not on WordPress. In several security-relevant places the shim behaves differently from WordPress core: `WP_REST_Request` parameter precedence, `esc_url_raw`, `sanitize_text_field`, cookie/nonce handling and MySQL collations. So 173 green tests prove less than they appear to. Finding M1 is a concrete case: the shim hides that bug.

---

## Summary

| Severity | Count | IDs |
|---|---|---|
| Critical | 0 | — |
| High | 2 | H1, H2 |
| Medium | 7 | M1–M7 |
| Low | 8 | L1–L8 |

## Remediation status (2026-11-17, plugin v0.2.0)

`bin/check.sh`: php -l OK, **215 passed, 0 failed, 5,355 assertions** (PHP 8.3.6, shim: not real WordPress/MySQL). The 42 new regression tests were also run against the unfixed v0.1.0 code: 39 of them failed there (the 3 that pass are guards: the shim self-check, "authenticated requests are still rate limited" and "a trusted proxy can vouch for TLS"), and the pre-existing wrapper test failed too; the fixed code passes all of them. The M1 shim bug is fixed: `WP_REST_Request` now uses WordPress's parameter order, which also turned the old `test_rest_style_put_and_delete_wrappers` red until the controller was fixed.

| ID | Status | Test(s) |
|---|---|---|
| H1 | Fixed | SecurityFixesTest::test_h1_previous_secret_cannot_rotate_or_disconnect, SecurityFixesTest::test_h1_staff_can_revoke_the_previous_secret_immediately |
| H2 | Fixed (legal values to confirm) | PrivacyTest::test_h2_* (14 tests, incl. `test_h2_review_probe_email_does_not_survive_400_days`) |
| M1 | Fixed | SecurityFixesTest::test_m1_unsigned_query_string_cannot_redirect_a_signed_delete, SecurityFixesTest::test_m1_json_body_source_id_cannot_override_the_signed_url, SecurityFixesTest::test_m1_query_string_is_refused_on_every_signed_route_but_rest_route_is_allowed, SyncAuthTest::test_rest_style_put_and_delete_wrappers (now real) |
| M2 | Fixed (real CAPTCHA is a plug-in) | SecurityFixesTest::test_m2_* (8 tests) |
| M3 | Fixed | SecurityFixesTest::test_m3_forged_requests_naming_a_real_key_id_do_not_throttle_the_dealer, SecurityFixesTest::test_m3_junk_pairing_codes_cannot_lock_out_a_valid_code, SecurityFixesTest::test_m3_authenticated_requests_are_still_rate_limited_per_key |
| M4 | Fixed | SecurityFixesTest::test_m4_* (5 tests) |
| M5 | Fixed | SecurityFixesTest::test_m5_vehicle_documents_are_never_public |
| M6 | Open | — |
| M7 | Fixed | SecurityFixesTest::test_m7_staff_and_security_actions_are_audited_without_secrets, SecurityFixesTest::test_m7_audit_log_endpoint_needs_the_staff_capability_and_has_no_mutators, SecurityFixesTest::test_m7_audit_details_are_whitelisted |
| L1 | Fixed | SecurityFixesTest::test_l1_pairing_cannot_omit_the_bound_dealer_identity, SecurityFixesTest::test_l1_pairing_cannot_overwrite_the_public_profile |
| L2 | Open | — |
| L3 | Open | — |
| L4 | Open | — |
| L5 | Open | — |
| L6 | Open | — |
| L7 | Open | — |
| L8 | Partially fixed | PrivacyTest::test_h2_consent_proof_is_stored_with_the_lead |

Behaviour changes visible to the Dealer Platform: rotate-key/disconnect need the current secret (H1); `/sync/*` needs HTTPS and no query string (M1, M4); pairing needs the exact bound `source_dealer_uuid` and no longer overwrites existing profile fields (L1). Everything else (signing canonical string, route names, payloads) is unchanged; new response fields are additive (`consent_at`, `consent_version` on pulled leads, `bound` on the lead token).

What is solid (verified):
- **SQL:** every query goes through `Db::prepare()`. Column names and SQL fragments are hard-coded, and `LIKE` input is escaped. I found no injection path.
- **Tenant isolation on HTTP sync:** the dealer comes from the authenticated key. Every repository write filters on `dealer_id`, and `pull_leads`, `ack_leads` and `manifest` are scoped to that dealer.
- **HMAC:** `hash_equals` is used, the method and route are signed, nonces are single-use (PK insert), and the same error is returned for an unknown key and for a bad signature.
- **Secrets at rest:** libsodium secretbox, with HKDF-derived subkeys.
- **Pairing codes:** 60 bits, keyed hash, single use.
- **Buyer IDOR:** none found. Favourites and saved searches are always keyed on `get_current_user_id()`, and `saved_search_delete` filters on `id AND user_id`.

---

## HIGH

### H1 — Previous (grace-period) secret can call `/sync/rotate-key` and take over the dealer key  [VERIFIED-PROBE]

> **Status: Fixed.** Test: SecurityFixesTest::test_h1_previous_secret_cannot_rotate_or_disconnect, SecurityFixesTest::test_h1_staff_can_revoke_the_previous_secret_immediately. Authenticator records the matched slot; rotate-key/disconnect need `current` (401 `wotoyi_secret_superseded`); staff `revoke_previous_secret`.

- **Where:** `includes/Sync/Authenticator.php:86-91` accepts current **and** previous secrets on every route. `includes/Rest/SyncController.php:175-180` and `includes/Repository/DealerRepository.php:216-230` (`rotate_secret` copies the *current* secret into `prev_secret_enc`).
- **Exploit:**
  1. A dealer's secret leaks (backup, log, ex-employee).
  2. The dealer rotates, which is the textbook response.
  3. For `key_grace` (600 s), the attacker signs `POST /sync/rotate-key` with the **old** secret.
  4. The server issues a new secret to the attacker. The dealer's legitimate new secret is demoted to "previous" and expires 10 minutes later.
- **Probe result:** the attacker's rotation returned 200. After the grace period, the legitimate dealer gets **401** and the attacker gets **200**. The marketplace now publishes listings and hands over buyer leads (PII) to the attacker. Rotation, the incident-response tool, becomes the takeover tool.
- **Fix:**
  - Make `Authenticator::verify()` record which secret matched (`$dealer['_auth_slot'] = 'current'|'previous'`).
  - Refuse `rotate-key` and `disconnect` unless the slot is `current`.
  - Optionally allow `previous` only for idempotent content events.
  - Add a test: "rotate with prev secret → 401".
  - Also invalidate `prev_secret_enc` immediately when staff force a rotation after an incident (staff endpoint, see M7).

### H2 — No retention, erasure or export for buyer personal data (leads, favourites, saved searches)  [VERIFIED-PROBE + CODE]

> **Status: Fixed (legal values to confirm).** Test: PrivacyTest::test_h2_* (14 tests, incl. `test_h2_review_probe_email_does_not_survive_400_days`). Daily retention job (30/90/30 days, configurable), WP exporter + eraser, `delete_user` cleanup, consent proof columns, policy text. Step 5 (written record of processing) is in README; Legal must confirm periods and CDP declaration.

- **Where:**
  - `includes/Plugin.php:86-89`: `prune()` only cleans nonces and sync_log.
  - `includes/Repository/LeadRepository.php:31-56, 78-89`: an acknowledged lead keeps name, phone, email, message, `user_id` and `ip_hash` indefinitely.
  - `grep` finds no `wp_privacy_personal_data_exporters`/`_erasers` filter and no `delete_user` hook.
  - `uninstall.php:13` keeps all data by default.
- **Probe result:** a lead with an email was delivered and acknowledged, then the clock was moved forward 400 days and `prune()` ran. **The email is still stored.**
- **Exploit / exposure:**
  - Any DB dump or backup leak exposes every buyer who ever contacted a dealer.
  - A buyer's erasure request cannot be met without manual SQL.
  - If a WP user is deleted, their favourites, saved searches and `leads.user_id` stay as orphans.
  - Leads are personal data under Senegal law n° 2008-12 (CDP: declaration, purpose limitation, retention limited to purpose, right of access/rectification/opposition) and under GDPR where EU residents (diaspora) are targeted. **Legal must confirm the current Senegalese text, the CDP declaration duty, and the cross-border-transfer rules, given the hosting location.**
- **Fix:**
  1. Add a daily retention job. Null `name/phone/email/message/ip_hash` on leads `delivered_at < now − N days` (setting, proposed 30). Delete undelivered leads after M days (proposed 90). Delete leads of disconnected dealers after the grace period.
  2. Register WP privacy exporters and erasers, keyed by user email and `user_id`, for leads, favourites and saved searches.
  3. Hook `delete_user` to delete favourites and saved searches and to null `leads.user_id`.
  4. Store `consent_text_version` and `consent_at` on the lead (see L8).
  5. Write a record of processing and a privacy notice that names the dealer as recipient.

---

## MEDIUM

### M1 — Unsigned query string (and JSON body) overrides `source_id` on signed PUT/DELETE wrappers  [VERIFIED-PROBE with WP-core parameter order; not run on real WP]

> **Status: Fixed.** Test: SecurityFixesTest::test_m1_unsigned_query_string_cannot_redirect_a_signed_delete, SecurityFixesTest::test_m1_json_body_source_id_cannot_override_the_signed_url, SecurityFixesTest::test_m1_query_string_is_refused_on_every_signed_route_but_rest_route_is_allowed, SyncAuthTest::test_rest_style_put_and_delete_wrappers (now real). `source_id` from `get_url_params()` only; any query string refused in `Authenticator::permission()` (except `rest_route`); shim now reproduces WP parameter order (`SecurityFixesTest::test_m1_request_shim_uses_wordpress_parameter_precedence`).

- **Where:** `includes/Rest/SyncController.php:111-115` uses `$request['source_id']` (= `get_param`).
- **Why it matters:** in WordPress core, `get_param()` looks in the sources in this order: **JSON → POST → GET (query string) → URL**. The signature covers the route but not the query string (`Signer.php:147-149`). So `DELETE /wotoyi/v1/sync/vehicles/A?source_id=B` passes the signature check for `/vehicles/A` but **deletes B**.
- **The 422 check is dead code on real WP:** with a JSON body, `$request['source_id']` *is* `$body['source_id']`, so the "body/URL mismatch" check at line 112 never fires. The shim (`tests/shim/wp-shim.php:74`) gives URL params priority, so the test passes while production would behave differently.
- **Probe result:** I used a request subclass that reproduces WP core's parameter order. A signed `DELETE /A` with `?source_id=B` returned 200 with the entity B deleted, and A stayed published.
- **Exploit:** this needs someone who can alter the request in transit or on a relay (a TLS-terminating CDN or proxy, or a compromised host on the Dealer Platform side). It breaks the "signature binds the entity id" guarantee. The idempotency key (line 118) ignores the query string, so the dealer's later genuine `DELETE /A` gets a permanent **409 event_id_conflict**.
- **Fix:**
  - Use `$request->get_url_params()['source_id']`.
  - Reject any sync request that has a non-empty query string (`$request->get_query_params()`), and do it in `Authenticator::permission()`.
  - Make the shim reproduce WP's parameter order and add a regression test.

### M2 — Lead anti-spam is weak: one token gives unlimited leads, per-IP limits are bypassable with IPv6, no per-dealer cap  [VERIFIED-PROBE]

> **Status: Fixed (real CAPTCHA is a plug-in).** Test: SecurityFixesTest::test_m2_* (8 tests). Single-use token bound to IP bucket and optionally to the listing, IPv6 /64 buckets, per-dealer / per-contact / per-contact-per-dealer daily caps, `wotoyi_mp_lead_challenge` provider filter + `lead_challenge_required`. No provider is bundled.

- **Where:**
  - `includes/Support/LeadGuard.php:253-267`: the token is stateless and not single-use. It is valid for 2 h and bound to nothing (no IP, no session, no target).
  - `includes/Rest/PublicController.php:169-175`.
  - `includes/Support/Ip.php:422-426`: limits are keyed on the full IP.
- **Probe result:** one token produced **20/20 accepted leads** from 20 IPs.
- **Exploit:** a bot fetches one token, waits 3 s, then submits from a botnet or by rotating addresses inside a single IPv6 /64 (2^64 "different" IPs). Any dealer can be flooded with fake leads, and any real phone number or email can be harassed through dealers ("someone at this number wants your car"). It also raises WhatsApp/SMS costs once lead notification exists.
- **Fix:**
  - Make the token single-use: store its keyed hash as a transient and delete it on use.
  - Bind the token to the IP-hash and to the target vehicle or service.
  - Bucket IPv6 by /64.
  - Add per-dealer and per-contact (phone/email hash) daily caps.
  - Add a real challenge (Turnstile/hCaptcha) behind a filter, which PROJECT §14.3 requires ("honeypot + challenge").

### M3 — Unauthenticated denial of service: global pairing lockout and per-key sync throttling  [VERIFIED-PROBE]

> **Status: Fixed.** Test: SecurityFixesTest::test_m3_forged_requests_naming_a_real_key_id_do_not_throttle_the_dealer, SecurityFixesTest::test_m3_junk_pairing_codes_cannot_lock_out_a_valid_code, SecurityFixesTest::test_m3_authenticated_requests_are_still_rate_limited_per_key. Per-key limit counted after HMAC; global pairing guard only stops misses; per-IP guard is per /64.

- **Where:**
  - `includes/Sync/Pairing.php:221` (`pairfail:global` 100/h).
  - `includes/Sync/Authenticator.php:79`: `sync:{key_id}` is counted **before** the signature is checked.
- **Probe results:**
  - 100 junk pairing codes from 100 IPs, then a **valid** code returned **429**. This means nobody can onboard a dealer, for one hour, renewable.
  - 240 forged requests carrying a real `key_id` (spread over 9 IPs to stay under the 30/min auth-fail limit), then the dealer's genuine signed request returned **429**. Sync is blocked for that dealer every minute.
  - The `key_id` travels in every request header and may appear in proxy logs.
- **Fix:**
  - Pairing: replace the global lock with per-IP (/64) exponential backoff plus an alert. 60-bit codes plus a 15-minute TTL already make brute force infeasible. If a global guard is kept, never let it block a code that hashes to an existing record (check the hash first, count failures only on misses).
  - Sync: move `RateLimiter::allow('sync:'.$key_id…)` **after** HMAC verification. Before HMAC, apply only the per-IP auth-fail limit.

### M4 — No TLS enforcement on secret-bearing endpoints  [VERIFIED-CODE]

> **Status: Fixed.** Test: SecurityFixesTest::test_m4_* (5 tests). `Support\Tls`: `/sync/*` and `/sync/pair` need HTTPS (403 `wotoyi_https_required`); `HttpTransport` throws on non-https; `WOTOYI_MP_ALLOW_INSECURE` / filter for non-production only; `wotoyi_mp_request_is_secure` for TLS-terminating proxies.

- **Where:**
  - `grep is_ssl` finds no match. `/sync/pair` (`SyncController.php:58-70`) and `/sync/rotate-key` (`:175-180`) return the clear HMAC secret.
  - `includes/Sync/HttpTransport.php:27` accepts any `base_url` scheme, including `http://`.
- **Exploit:** a site reachable over plain HTTP (misconfigured host, or a free host with optional SSL) leaks the dealer secret to anyone on-path during pairing or rotation. That gives full control of the dealer's listings and leads.
- **Fix:**
  - In `pairing_gate` and in `Authenticator::permission()`, return 403 unless `is_ssl()` (with a filter or constant override for local development only).
  - In `HttpTransport::__construct`, refuse a non-`https` `base_url`.

### M5 — Vehicle `documents` URLs are public, which can expose owner personal data the plugin otherwise hides  [VERIFIED-CODE; content risk is an assumption]

> **Status: Fixed.** Test: SecurityFixesTest::test_m5_vehicle_documents_are_never_public. `documents` is now `private` (stored, never returned publicly). Onboarding-guide wording for dealers is still to be written.

- **Where:** `includes/Schema/VehicleFields.php:140` (`documents`, group `media`, not `private`). `includes/Presenter/VehiclePresenter.php:72-82` publishes every non-private field.
- **Exploit:** dealers will naturally attach the registration card (carte grise), customs papers or invoices. These show the previous owner's name and address, the VIN and the plate. The plugin deliberately hides VIN and plate (`:44-45`), then publishes the same data in a document link on the public detail endpoint. Scraping all `/vehicles/{ref}` gives a dataset of owner identities.
- **Fix:**
  - Mark `documents` as `private` (dealer-side only).
  - Or expose only `{type, available:true}` and deliver documents to a buyer only after a lead or inspection flow.
  - Tell dealers in their onboarding guide not to publish identity documents.

### M6 — Local transport: no caller authorisation, auto-activated dealers, can be enabled from the DB  [VERIFIED-CODE]

> **Status: Open.** Test: —. Not fixed (documented in README Known limits #13). Keep local mode OFF in production.

- **Where:**
  - `includes/api.php:203-218`, `includes/Sync/LocalTransport.php:125-151`: `ensure_dealer` creates the dealer **active** with no staff approval.
  - `:173-183`: `resolve` trusts the caller's `dealer_ref`.
  - `includes/Settings.php:492, 506-512`: `local_mode` can be switched on through the option or a filter, not only through the constant.
- **Exploit:** when local mode is on, any plugin or theme code in the process can do these things for **any** local dealer, with no `current_user_can` check: publish listings, create dealers, and pull or ack every buyer lead (PII). If the Dealer Platform passes a user-supplied dealer id, that is a direct cross-tenant IDOR. An attacker who obtains `manage_options`, or any option-write bug in another plugin, can turn local mode on in production.
- **Fix:**
  - Honour **only** the `WOTOYI_MP_LOCAL_MODE` constant (drop the option and filter path).
  - Refuse local mode when `wp_get_environment_type() === 'production'`.
  - Require the caller to pass an explicit capability context, and check it in `resolve()` (for example `current_user_can('wotoyi_dealer_sync', $dealer_ref)`, or a per-dealer local token).
  - Create local dealers as `pending` unless an admin activates them.
  - Show a persistent admin notice while local mode is on.

### M7 — No audit log for staff and security actions (PROJECT §14.5)  [VERIFIED-CODE]

> **Status: Fixed.** Test: SecurityFixesTest::test_m7_staff_and_security_actions_are_audited_without_secrets, SecurityFixesTest::test_m7_audit_log_endpoint_needs_the_staff_capability_and_has_no_mutators, SecurityFixesTest::test_m7_audit_details_are_whitelisted. Append-only `audit_log`, whitelisted details, `GET /admin/audit-log`; written from every admin route, rotate-key, disconnect, pairing success, retention and privacy erasure.

- **Where:** `includes/Rest/AdminController.php:25-84`. Issuing pairing codes, changing status (suspend, reactivate), granting the `verified` badge, rotating keys and disconnecting leave no who/when/from-where/before-after record. `sync_log` covers only content events.
- **Exploit:** a rogue or compromised staff account can issue a pairing code for an active dealer and re-pair it to an attacker system (the old platform is silently cut off), or sell "verified" badges, with no trace.
- **Fix:** add an append-only `audit_log` table (actor user id, action, target dealer, before/after of `status`/`verified`, IP hash, correlation id). Write to it from every admin route, from rotate-key, from disconnect and from pairing success. Never log secrets or codes.

---

## LOW

### L1 — The pairing identity binding is skippable, and pairing can rewrite the public dealer profile  [VERIFIED-PROBE]

> **Status: Fixed.** Test: SecurityFixesTest::test_l1_pairing_cannot_omit_the_bound_dealer_identity, SecurityFixesTest::test_l1_pairing_cannot_overwrite_the_public_profile. Exact `source_dealer_uuid` match required when staff set one; pairing fills only empty profile fields.

- **Where:** `includes/Sync/Pairing.php:233-241, 249-253`.
- **Problem:** if staff created the dealer with a `source_dealer_uuid`, a pair call that simply **omits** that field passes. The "Code was issued for another dealer identity" check only runs when a different value is sent. Whoever intercepts a code (it is often sent by WhatsApp) can pair and also set `website_url`/`logo_url`/`phone` to phishing values (probe: 201).
- **Fix:** when the dealer record has a `source_dealer_uuid`, require an exact match. Apply pairing-time profile changes only to empty fields, or queue them for staff review.

### L2 — A buyer cannot remove a favourite once the item is unpublished  [VERIFIED-PROBE]

> **Status: Open.** Test: —. Documented.

- **Where:** `includes/Rest/AccountController.php:131-138` resolves the target through `published_*_ref`.
- **Probe result:** the row is still there after `DELETE`.
- **Fix:** resolve by uuid or slug regardless of status (still scoped to `user_id`). Purge favourites of deleted items in the prune job.

### L3 — Media URLs accept `http` and any host. No SSRF today.  [VERIFIED-CODE]

> **Status: Open.** Test: —. Documented.

- **Where:** `includes/Schema/Fields.php:243-249`, presenters.
- **No SSRF today:** `grep` shows that the marketplace never fetches media server-side. The only outbound request is `HttpTransport`, which is the client side.
- **Problems:**
  - Remaining risk: buyers' browsers load arbitrary third-party hosts, which leaks buyer IP and User-Agent to the dealer's chosen tracker.
  - It causes mixed content.
  - `booking_url`/`documents` can be phishing links.
- **Fix:**
  - Allow `https` only.
  - Add a host allow-list filter (Wotoyi CDN plus approved hosts).
  - **When any future feature fetches these URLs** (thumbnails, mirroring, AI vision), it must use `wp_safe_remote_get`, block private/link-local/metadata IP ranges after DNS resolution, re-check after redirects, and enforce size and time limits.

### L4 — No per-dealer quotas; batch amplification  [VERIFIED-CODE]

> **Status: Open.** Test: —. Documented.

- **Where:** `includes/Sync/Receiver.php:123-135`, `Authenticator.php:79`.
- **Problem:** rate limits count requests, not events: 240 × 50 = 12,000 writes per minute per key, with unbounded listings and tombstones (`AbstractListingRepository.php:179-199` creates tombstones for unknown ids). A compromised dealer key can flood the catalogue and the DB.
- **Fix:** count events in the limiter. Enforce plan quotas in `wotoyi_mp_can_publish` (currently a no-op hook). Cap tombstone creation for never-seen ids.

### L5 — Rate limiter on transients: non-atomic and write-amplifying  [VERIFIED-CODE; impact on MySQL UNVERIFIED]

> **Status: Open.** Test: —. Documented; IPv6 /64 bucketing (M2) reduces the row growth from address rotation.

- **Where:** `includes/Support/RateLimiter.php:454-470`, `PublicController.php:105-109`.
- **Problem:** on hosts without a persistent object cache (InfinityFree), every public GET does 1–2 `wp_options` writes. Each new IP or vehicle pair creates rows, so IP rotation inflates `wp_options` and inflates `views`, which drives `sort=views` ranking.
- **Fix:** use the object cache when present. Otherwise use a dedicated small table with `INSERT … ON DUPLICATE KEY UPDATE n=n+1`. Bucket IPv6 by /64. Do not count views from requests the limiter would reject.

### L6 — Body-size cap enforced after the whole body is read  [VERIFIED-CODE]

> **Status: Open.** Test: —. Documented.

- **Where:** `Authenticator.php:73`.
- **Problem:** WP has already read `php://input`, so the 1 MB cap does not protect memory.
- **Fix:** also check `$_SERVER['CONTENT_LENGTH']` first, and set `post_max_size`/web-server limits on the sync path.

### L7 — Case-insensitive collation on nonce and event-id keys  [UNVERIFIED on MySQL]

> **Status: Open.** Test: —. Documented.

- **Where:** `includes/Schema/Schema.php:236-242` (`sync_nonces` PK) and the `sync_log` `dealer_event` unique key both use the default `utf8mb4_unicode_ci`.
- **Problem:** nonces or event ids that differ only in case would collide, which gives false replays or 409s (availability only, not a bypass).
- **Fix:** declare those columns `COLLATE ascii_bin` (or `utf8mb4_bin`).

### L8 — Lead PII in clear at rest; consent proof is a bare boolean  [VERIFIED-CODE]

> **Status: Partially fixed.** Test: PrivacyTest::test_h2_consent_proof_is_stored_with_the_lead. Consent text version, timestamp and purpose are stored. Encryption of phone/email/message at rest is still open.

- **Where:** `includes/Schema/Schema.php:159-184`, `PublicController.php:177-180`.
- **Fix:** encrypt `phone`/`email`/`message` with the existing `Crypto` (decrypt only in `pending_for_dealer`). Store a consent text version, a timestamp and the purpose ("transmit to dealer X"). Make sure backups are encrypted (PROJECT §14.4).

---

## Route-by-route authz check (36 routes, `includes/Rest/Routes.php:34-80`)

| Group | Routes | Permission | Result |
|---|---|---|---|
| Public read | 11 GET (vehicles, count, facets, meta, vehicle, services, service, dealers, dealer, leads/token) | `public_read` (per-IP limit) | OK. Only published listings of active dealers. VIN/plate never returned. See M5 (documents). |
| Public write | `POST vehicles/{ref}/view`, `POST leads` | `public_write` | Works, but anti-abuse is weak (M2, L5). |
| Buyer | 6 `/me/*` | logged in (+ nonce on writes) | OK, user-scoped, no IDOR. L2. |
| Staff | 4 `/admin/*` | `wotoyi_manage_marketplace` (+ nonce on writes) | OK. No audit trail (M7). |
| Pairing | `POST /sync/pair` | `pairing_gate` + code | DoS (M3), no TLS (M4), weak binding (L1). |
| Signed sync | 13 `/sync/*` | HMAC `Authenticator` | Tenant isolation OK. H1, M1, M3, M4, L4. |

## Verdict: **PASS WITH FIXES**

No Critical issue. SQL safety, HMAC construction and tenant isolation on the HTTP path are well built. **The plugin must not go to production (real dealers, real buyer data) until fixes 1–5 below are merged, each with a regression test**, and the QA matrix has been run on real WordPress + MySQL. The shim divergence in M1 shows that the current tests cannot be relied on for request-parsing behaviour.

### Top 5 fixes (in order)

1. **H1:** only the *current* secret may call `rotate-key`/`disconnect`. Record the matched secret slot in `Authenticator`.
2. **H2:** lead retention/minimisation job, WP privacy exporter + eraser, `delete_user` cleanup, consent metadata. Legal confirms Senegal law 2008-12 / CDP obligations.
3. **M1:** read `source_id` from `get_url_params()` only, reject any query string on `/sync/*`, and fix the shim's parameter order.
4. **M4 + M3:** enforce HTTPS on `/sync/*` and in `HttpTransport`. Move the per-key sync limit after HMAC. Replace the global pairing lock.
5. **M2 + M5:** single-use, bound lead tokens, IPv6 /64 buckets, per-dealer/contact caps and a challenge provider. Make vehicle `documents` private.

Probe evidence (`php /tmp/wotoyi-probe/probe.php`, throwaway):

```
P1 attacker rotate with old secret status        200
P1 after grace: legit dealer health              401
P1 after grace: attacker health                  200
P2 signed DELETE /A + ?source_id=B status        200   (entity deleted: B; A stays published)
P3 leads accepted with ONE token from 20 IPs     20
P4 legit pairing after 100 junk attempts         429 wotoyi_rate_limited
P5 legit signed request after 240 forged ones    429
P6 favourites rows left for user 42 after DELETE 1
P7 pair with no source id + profile overwrite    201
P8 lead rows with email after ack + 400 days + prune 1
```
