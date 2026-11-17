# Security review: wotoyi-dealer-platform 0.1.0

**Reviewer:** [CISO], independent of the author. **Date:** 2026-11-17. **Scope:** `wotoyi/plugins/wotoyi-dealer-platform/` (read-only, no code edits).
**Reference rules:** PROJECT.md §14.3 (WordPress rules), §14.4 (central rules), §14.5 (audit logs). Senegal Law 2008-12 on personal data and GDPR where applicable.

## Method and evidence

- Read every file under `includes/`, plus `uninstall.php`, the main plugin file and `bin/check.sh`.
- Ran `bin/check.sh`. Results: lint clean, **85/85** unit tests pass, **7/7** cross-plugin integration tests pass, the missing-key script behaves correctly, exit code 0.
- Wrote throwaway probes in `/tmp/ciso/` (`probe.php`, `idor.php`) that run on the plugin's own test shim (in-memory SQLite, WP shim). Each finding below says whether it was **verified by probe** or **verified by code reading**.
- **Limit:** the shim's `wp_safe_remote_request()` is a pass-through. The host-blocking behaviour of real WordPress core was **not** executed. Where a finding depends on it, I say so.

## What is solid (verified)

- **Tenant isolation.** `Data/TenantDb` is the only way to reach scoped tables. It always adds `tenant_id = TenantContext::id()`, rejects caller-supplied `tenant_id`, checks columns against the schema, binds every value through `prepare()`, and throws when there is no tenant context (`TenantContext.php:55-60`).
  - **Probe:** 36 method × route combinations on every `{id}` route, run as the owner of tenant A against tenant B's UUIDs. Result: **0 reads or writes**. Every combination returned 404/422, except `GET /customers/{B}/timeline`, which returned 200 with an empty list (no leak).
- **SQL injection.** Probe P7 fuzzed `orderby`, `order`, filters, `q`, `per_page`/`page`, `tenant_id`, array params, and the outbox `status`. No SQL error and no out-of-scope rows.
- **REST permission callbacks.** Every route has a real `permission_callback`. Anonymous callers get 401. Writes need a REST nonce unless the request came through an application password. A WordPress administrator has no automatic access to tenant data.
- **Role checks.** A manager cannot manage members or tenant settings (403, probe P8). An owner cannot touch another tenant's members (404).
- **Secrets.** The marketplace secret is stored encrypted with libsodium secretbox, using an HKDF key derived from `WOTOYI_SECRET_KEY`. There is no salt fallback, and pairing is refused without the key. `SyncState::present()` never returns the secret or its ciphertext.
- **Transport.** `sslverify` is on, redirects are off (`redirection => 0`) and response size is capped. HMAC canonical signing includes the body hash.
- **What is sent to the marketplace.** `Payload::snapshot` sends catalogue fields only. VIN, plate and dealer-only fields (purchase cost, notes, supplier) stay local.
- **Output.** No HTML output except two notices, and both are escaped. Text fields go through `sanitize_text_field`/`sanitize_textarea_field`. Descriptions go through `wp_kses` with a 7-tag allow-list and no attributes.

## Findings

### Critical

None verified.

### High

#### H1. Any dealer owner can attach any WordPress user to their tenant without consent: cross-tenant lock-out and tenant confusion. Verified by probe P1.

- **Where:** `includes/Service/MemberService.php:45-64` and `:120-148` (`resolve_user` accepts a raw `user_id` or any email); `includes/Auth/Access.php:88-89` (a user with 2 or more memberships gets 400 unless they send `X-Wotoyi-Tenant`).
- **How it is exploited:**
  1. The owner of tenant A calls `POST /members {"user_id":12,"role":"sales"}`. User 12 is the owner of tenant B. The call returns 201.
  2. From then on, every request from B's owner returns `400 wotoyi_tenant_required`. Probe result: before = 200, after = 400.
  3. User IDs are sequential. The error messages are also an enumeration oracle:
     - `User not found.` vs 201 for user IDs;
     - `No WordPress user has this email` vs 201 for emails.
  4. So one malicious dealer can lock every other dealer's staff out of any client that does not send the tenant header.
  5. Once a tenant picker exists, the victim's staff may type their customers into the attacker's tenant (data diversion through social engineering).
  6. The invitee is never asked and never told.
- **Fix:**
  1. Replace direct add with an **invitation**:
     - store a `pending` membership plus a single-use token (hashed, 7-day TTL);
     - email the invitee;
     - make the membership `active` only when the invitee accepts while logged in.
  2. Remove `user_id` from the owner API. Keep it on the platform route only.
  3. Return one generic `202 Invitation sent` whether or not the email exists.
  4. Make the client remember the last selected tenant and show a picker. Never auto-select.
  5. Rate-limit invitations (see M4).

#### H2. The right to erasure is incomplete: personal data survives `POST /customers/{id}/erase`. Verified by probe P5.

- **Where:** `includes/Service/CustomerService.php:56-69`. It only anonymises the customer row, leads where `customer_uuid` = the customer, and activities where `entity_type='customer'`.
- **Data left after erasure (probe output):**
  - the person's name and phone in **unlinked leads** with the same phone;
  - **activities on their leads** (free-text notes);
  - **tasks** (title "Call Moussa Diop +221…", `related_uuid` = customer);
  - **rental notes** ("ID card of Moussa Diop copied");
  - deal `notes` and lead `lost_reason` are also untouched (code reading).
  - Erasure is **not propagated to the marketplace**: there is no outbox event, and marketplace leads still hold the original copy.
- **Exposure:** Law 2008-12 (rights of opposition and suppression) and GDPR Art. 17. When a data subject or the CDP checks, the dealer has asserted erasure that did not happen. The `customers.erase` audit entry makes the claim on record.
- **Fix:**
  1. Erase by **subject**, not just by FK. Match `customer_uuid` OR the same phone, WhatsApp or email across leads.
  2. Overwrite `body`/`notes`/`description`/`title`/`lost_reason` in the activities, tasks, deals and rentals linked to the customer **or** to their leads.
  3. Emit a `lead.erase` request to the marketplace (new signed endpoint) and record it.
  4. Return per-table counts and keep them in the audit log.
  5. Add a regression test that seeds PII in every table and greps the whole DB after erasure, the way probe P5 does.

### Medium

#### M1. A *manager* can re-pair or disconnect the dealer's marketplace and point sync at any host. The URL blocklist is weak and the endpoint leaks a status oracle. Verified by probes P2 and P3.

- **Where:**
  - `includes/Auth/Roles.php:34` gives `sync.manage` to manager;
  - `includes/Rest/Routes.php:99-128` (pair, pair-local, disconnect);
  - `includes/Sync/Pairing.php:31-53` (`validate_base_url`), `:105` (echoes the upstream HTTP status).
- **How it is exploited:**
  1. A manager (not the owner) calls `/sync/disconnect`, which returns 200. This silently stops marketplace publication.
  2. The manager then pairs with `https://attacker.example`. Probe: the request went out to `https://attacker.example/wp-json/wotoyi/v1/sync/pair`.
  3. A rogue endpoint that answers with any `key_id`/secret becomes the tenant's marketplace. It then:
     - receives all catalogue events;
     - can inject arbitrary leads, which are later fed to AI agents (prompt-injection surface);
     - can force arbitrary version bumps through the manifest (`Reconciler::reconcile_row` trusts `remote version`).
  4. The plugin-level blocklist only catches literal private IPs. These were **accepted**:
     - `https://2130706433`, `https://0x7f000001`, `https://127.1`;
     - `https://[::ffff:127.0.0.1]`;
     - `https://localtest.me`, `https://foo.localhost`.
  5. In production, the second layer is WordPress core `wp_http_validate_url`. It blocks 127/8, 10/8, 172.16/12, 192.168/16 and 0/8 after DNS resolution and limits ports to 80/443/8080. It does not block 169.254/16 behind a DNS name, and it is open to DNS rebinding (TOCTOU). (WordPress core behaviour, not executed here.)
  6. The error `HTTP <status>` returned at `Pairing.php:105` is a blind SSRF port/host oracle.
- **Fix:**
  1. Move pair, pair-local and disconnect to **owner only** (`settings.manage`). Keep `sync.run`/`requeue` for manager.
  2. Pin the marketplace origin with a constant (`WOTOYI_DP_MARKETPLACE_URL`, or an allow-list of hosts) and reject everything else. A dealer never needs to choose an arbitrary marketplace.
  3. Return a generic `wotoyi_pairing_failed` without the upstream status.
  4. Cap the remote `version` accepted by the reconciler (for example, local + 1,000) and audit anything above it.

#### M2. Timeline entries bypass entity capabilities. Verified by probe P4.

- **Where:** `includes/Rest/Routes.php:89` (`GET /activities` only needs `activities.read`). The service applies no entity-type filter by caller capability.
- **How it is exploited:** mechanic gets `GET /leads` → 403, but `GET /activities?entity_type=lead` → 200 with lead notes. The probe returned "Called Awa on +221770000009, wants credit". The same applies to deals and rentals for any role holding `activities.read`.
- **Fix:** in `ActivityService::list`, restrict `entity_type` to the types whose `<entity>.read` capability the caller holds. If the caller asks for a type they cannot read, return 403. If no type is given, force an `IN (...)` filter.

#### M3. The audit log does not meet §14.5. Verified by probe P6 and code reading.

- **Not immutable:** `audit_log` is a scoped table, so `TenantDb::update()` (`includes/Data/TenantDb.php:275`) accepts it. Only `delete` is restricted (`:308`). There is no hash chain.
- **No "before" value on permission changes:** `MemberService.php:109` records the new role only. The probe audit row reads `{"fields":["role"],"role":"owner"}`, so "sales → owner" cannot be reconstructed.
- **Platform actor lost:** `TenantService::create` runs with no member, so `tenant.create` is stored with `user_id 0` and `ip_hash null`. The administrator who created the tenant is not recorded.
- **Not logged at all:** PII reads/exports (customer lists, `per_page=100` pagination), authorization failures (403s), and a correlation ID.
- **Fix:**
  1. Reject `update`/`delete` on `audit_log` in `TenantDb`.
  2. Add `prev_hash` and `hash = sha256(prev_hash || row)` per tenant, plus a verification command.
  3. Pass the acting WordPress user into `TenantService::create`.
  4. Record `from`/`to` for role and status changes.
  5. Log list/export of customers, leads and employees (actor, filter, count), and log 403s.
  6. Add a request correlation ID.

#### M4. No abuse throttling on authenticated bulk operations (§14.4). Verified by probe P10 and code reading.

- **Account creation:** one owner created **30 WordPress accounts in a row** (`create_user=true`), each triggering `wp_new_user_notification` email. That is an email-bombing and spam-account vector from the platform's domain.
- **Bulk reads:** customer, lead and employee lists have no per-user or per-tenant limit. A stolen sales session can pull the full CRM at 100 rows per request with no alert.
- **What exists:** only pair, run and reconcile are throttled (`Routes.php:132-136`).
- **Fix:**
  - `RateLimiter` on `POST /members`: 10 per hour per tenant;
  - a per-user read budget on PII lists (for example, 600 rows/minute, then 429 plus an audit event);
  - alert on threshold breaches.

#### M5. `bin/check.sh` hides failing tests. Verified.

- **Where:** `bin/check.sh` runs `php tests/run.php | tail -n 12 || status=1` without `set -o pipefail`. The exit status is `tail`'s, which is always 0.
- **Reproduced:** a command that prints "1 failed" and exits 1, piped the same way, gives `status=0`.
- **Impact:** any CI or quality gate (Coach review) that trusts the exit code would pass a red suite. This is a security issue because the isolation tests are the main control.
- **Fix:** add `set -o pipefail`, or capture `${PIPESTATUS[0]}` for each suite.

#### M6. No retention, export or offboarding for personal data (Law 2008-12, GDPR Art. 5(1)(e), 15, 20). Verified by code reading.

- **Retention:** soft-deleted customers, leads and employees (`deleted_at`) keep full PII forever. There is no purge job (`Plugin::prune` only prunes the outbox).
- **Consent:** a bare boolean, with no timestamp, source, purpose or channel. Direct-marketing consent for WhatsApp cannot be proven.
- **Access right:** no data-subject export endpoint.
- **Tenant offboarding:** no export or deletion of a dealer's data.
- **Uninstall:** keeps all data unless an option is set that no UI exposes (`uninstall.php:14`).
- **Fix:**
  1. Configurable retention per entity (for example, purge PII of soft-deleted rows after N days) in the daily cron.
  2. Add `consent_at`, `consent_source`, `consent_purpose`.
  3. `GET /customers/{id}/export` (JSON of everything linked, audited).
  4. A platform-operator tenant export plus a hard-delete runbook.
  5. **Founder or legal action:** CDP declaration/authorisation before production, and a cross-border transfer analysis if hosting is outside Senegal.

### Low

| # | Finding | Where | Fix |
|---|---|---|---|
| L1 | Invalid marketplace leads are **acknowledged and dropped**. Only a count is audited, so a real customer enquiry can be lost silently. | `Sync/LeadPuller.php:49-52` | Do not ack invalid leads. Quarantine the raw sanitised payload in a `lead_rejects` row and ack only after it is stored. |
| L2 | Upstream transport errors (`get_error_message()`, marketplace `message`) are stored in `last_error` and shown to sales and accountant (`sync.read`). They may reveal internal hosts or cURL details. | `Sync/HttpTransport.php:57`, `Sync/Worker.php:182-203` | Store the code only for API display. Keep the detail in the PHP error log. |
| L3 | Finance fields are write-only for roles without `finance.read`. Sales can set `purchase_cost` (probe P9: stored 12,000,000) but cannot see it, so gross-margin KPIs can be manipulated unseen. | `Schema/VehicleFields.php:155`, `Service/EntityService.php` (sanitise ignores `finance`) | Treat `finance` fields as readonly for roles without `finance.read` (warning, not written). |
| L4 | Any owner can demote or suspend all co-owners, with no notification. Only the last-owner rule exists. | `Service/MemberService.php:104-106` | Notify all owners by email on any owner-role change, and require re-authentication for owner demotion. |
| L5 | `uninstall.php` does not remove `wotoyi_dp_manage_platform` from the administrator role, does not handle multisite, and leaves `wotoyi_rl_*` transients. | `uninstall.php`, `Installer.php:28` | Remove the capability, iterate sites on multisite, and delete the transients. |
| L6 | `WOTOYI_SECRET_KEY` is checked for length only, and there is no rotation path: changing it makes every stored secret unreadable, so every tenant must re-pair. | `Support/Crypto.php:21-30` | Support `WOTOYI_SECRET_KEY_PREVIOUS` for decryption and re-encrypt on read. Document generation (`openssl rand -base64 48`). |
| L7 | WordPress accounts created through `create_user` stay after the member is removed (data minimisation). | `Service/MemberService.php:140` | When the last membership of a plugin-created user is suspended, flag the account for deletion after N days. |
| L8 | `GET /customers/{foreign-id}/timeline` answers 200 with an empty list instead of 404. This is not a leak, but it is inconsistent with the other routes. | `Rest/Routes.php:72` | Call `Plugin::customers()->row($id)` first. |

## Remediation status (Lead Backend, 2026-11-17)

Regression tests: `tests/cases/SecurityFixesTest.php`. Run against the unfixed code they fail 19 of 20 (M5 shown separately below); against the fixed code all pass. `bin/check.sh` of both plugins was run after the fixes (results at the end of this section).

| # | Status | Regression test(s) | What changed / what remains |
|---|---|---|---|
| H1 | **Fixed** | `test_h1_owner_cannot_attach_another_tenants_user_or_lock_them_out`, `..._reply_is_identical_whether_or_not_the_email_exists_or_is_a_member`, `..._invitee_must_accept_with_the_invited_email_and_the_token_is_single_use`, `..._expired_and_revoked_invitations_cannot_be_accepted`, `..._invitations_are_rate_limited_per_tenant_and_per_address`, `..._invitation_user_cannot_be_forged_by_manager_or_sales`, `..._direct_attachment_exists_only_on_the_platform_route_and_is_audited_with_its_actor` | `POST /members` now invites by email and always answers `202` with the same body. Token = 32 random bytes, only its SHA-256 stored, 7-day TTL, single use, sent by email in a URL fragment. `POST /members/accept` needs a logged-in user whose WordPress email equals the invited address; every failure gives one answer. `user_id` and `create_user` removed from the owner API. Direct attachment only via `POST /platform/members` (platform capability). Limits: 10 invitations/hour/tenant (429), 3 mails/day/address across tenants (silent). New: `GET /members/invitations`, `DELETE /members/invitations/{id}`. **Client duty (open, front-end):** the PWA must remember the last chosen tenant and show a picker; the server still answers 400 `wotoyi_tenant_required` for multi-tenant users without the header. Accounts are no longer auto-created for invitees (they register or are created by the platform operator first). |
| H2 | **Fixed (dealer side) / Open (marketplace side)** | `test_h2_erasure_leaves_no_trace_of_the_person_in_the_whole_database` (seeds PII in 8 tables, erases, then searches EVERY table of the test database for 17 values; also checks a bystander is untouched), `test_h2_export_returns_everything_held_about_the_person_and_is_audited` | Erasure is by subject: leads linked OR sharing phone (digit-normalised) or email, lead messages and lost reasons, timeline entries of the customer, their leads, deals and rentals, tasks, deal and rental notes, plus any free text that mentions the phone, email or full name. Per-table counts returned and audited. **Open:** the marketplace receiver protocol has no erase endpoint (routes: pair, health, events, batch, vehicles, services, leads pull/ack, manifest, rotate-key, disconnect). The marketplace erases through its WordPress privacy eraser (`Privacy::erase`, by email). The response carries `follow_up.marketplace = "not_propagated"` with the instruction. Needs a new signed `POST /sync/leads/erase` in the marketplace (protocol change, not done to keep the protocol backward compatible). **Known limits:** free text is matched on the canonical phone digits, the email and the full name (5+ chars); a nickname or a number written in another form is only erased when the row is linked by id. Other customers' `notes` fields are not scanned. Scan is O(rows of the tenant). |
| M1 | **Fixed** (version cap open) | `test_m1_only_the_owner_can_pair_or_disconnect_and_a_manager_keeps_run_and_requeue`, `test_m1_internal_address_forms_are_rejected_and_public_ones_accepted`, `test_m1_a_pinned_marketplace_url_is_the_only_target`, `test_m1_every_remote_failure_gives_the_same_answer_and_no_upstream_status` | pair, pair-local, disconnect need `settings.manage` (owner). Optional `WOTOYI_DP_MARKETPLACE_URL` pins the only allowed origin (and makes `base_url` optional). Validator rejects decimal/hex/octal/short IPv4, IPv4-in-IPv6 (mapped, compatible, NAT64, 6to4), private/CGNAT/reserved ranges, single-label hosts, localhost and `.localhost/.local/.internal/...` suffixes, wildcard-DNS names (nip.io, localtest.me, ...), non-443 ports, odd paths, and any name resolving to a non-public address. Every remote failure returns one generic `wotoyi_pairing_failed` (422); the detail goes to the PHP error log. **Open:** DNS rebinding between validation and request is only mitigated by core `wp_safe_remote_request`; use the constant in production. The reconciler still trusts the remote `version` (cap not implemented). |
| M2 | **Fixed** | `test_m2_timeline_entries_follow_the_read_capability_of_the_record` | `ActivityService` filters by the caller's `<entity>.read`: 403 when a forbidden type is requested, `IN (readable types)` otherwise; creating a note on an unreadable record is 403. |
| M3 | **Fixed** (partial extras open) | `test_m3_audit_log_is_append_only_and_tamper_evident`, `test_m3_role_changes_record_the_old_and_the_new_value_and_every_entry_has_an_actor`, `test_m3_tenant_creation_records_the_platform_administrator`, `test_m3_personal_data_reads_are_audited_without_the_data` | `TenantDb::update` refuses `audit_log`, delete already restricted; per-tenant HMAC hash chain with `AuditLog::verify()`; `actor_type` (user, platform, system) on every row; role/status changes store `from`/`to`; platform admin recorded on tenant creation and direct member add; list reads of customers, leads and employees, customer export and erase are logged (counts and filter names, never values). **Open:** deleting the newest rows (tail truncation) is not detectable without an external anchor; 403s and a request correlation id are not logged; there is no CLI/cron that calls `verify()` yet. Schema change: DB version 2 adds columns and the `invitations` table (dbDelta on upgrade). |
| M4 | **Fixed** | `test_h1_invitations_are_rate_limited_per_tenant_and_per_address`, `test_m4_personal_data_reads_have_a_per_user_row_budget_with_an_alert` | Invitations rate limited (see H1). Customer, lead, employee lists and the subject export share a per-user budget of 600 rows/minute (filter `wotoyi_dp_pii_rows_per_minute`): 429 plus one `pii.rate_limited` audit event per window. Limiter is transient based (not atomic without an object cache). |
| M5 | **Fixed** | `test_m5_check_scripts_cannot_hide_a_failing_suite` (fails against the old script, passes now) | Dealer `bin/check.sh`: `set -u -o pipefail`, prints CHECK PASSED/FAILED. Marketplace `bin/check.sh`: a lint failure previously did not change the exit code (`[ ] && echo` under `set -e`); it now exits 1. Proven with a deliberately failing test in each plugin: both scripts exited 1; the test files were then removed. |
| M6 | **Open (partly)** | n/a | Customer export added (access right, audited). Still open: retention/purge of soft-deleted rows, consent timestamp/source/purpose, tenant offboarding export, uninstall behaviour, CDP declaration (founder/legal). |
| L1-L8 | **Open** | n/a | Documented, not changed. L5 and L8 are trivial and can follow. |

## Verdict

**PASS WITH FIXES.** The tenant-isolation core is well designed and held under every probe: no cross-tenant read or write, and no SQL injection. Nothing Critical was found.

The plugin must **not** go to production with real dealer and customer data until **H1, H2, M1 and M5** are fixed. M2, M3 and M6 must be fixed before the first paying dealer.

## Top 5 fixes (in order)

1. **H1:** replace direct member add with a consented invitation flow (hashed single-use token), drop `user_id` from the owner API, use one generic response, and rate-limit it.
2. **H2:** make erasure subject-wide (unlinked leads, lead activities, tasks, rental and deal notes, `lost_reason`), propagate it to the marketplace, and add a whole-database PII regression test.
3. **M1:** make pair and disconnect owner-only, pin the marketplace origin with a constant allow-list, stop echoing the upstream status, and bound remote versions.
4. **M5:** add `set -o pipefail` to `bin/check.sh` so the quality gate can actually fail.
5. **M3 + M2:** make the audit log append-only with a hash chain and before/after on role changes, record the platform actor, and filter timeline reads by entity capability.

**[CISO]**
