# Security audit — 2026-11-16 — Wotoyi (code name AutoBuzy), phases 2–6

> Author: `security-auditor` (Opus), code review only — no exploit or runtime test was run, so every finding is ⏸ not tested at runtime.
> CEO validation: ✅ 2026-11-16 — H1, H2, M1, M5, M6 re-checked line by line by the CEO and confirmed.

## Executive summary

**Overall risk: medium-high. No critical issue found.** The base is solid: nonces and capability checks on every action traced; sync signatures use HMAC with constant-time compare and a time window; the dealer is always resolved from the key or the logged-in user; payment status cannot be changed by a dealer; private files are served by PHP after a permission check.

The real weaknesses are in the **AI assistant layer** (the human-confirmation control can be bypassed) and the **voice channel** (faked caller ID gives CRM access).

**Launch blockers**
1. **H1** before the WhatsApp dealer assistant is switched on for real dealers.
2. **H2**: keep voice disabled (default) until fixed.
3. **M1** before the dealer assistant is switched on.
4. **M4** and **M5** before real personal data or payments are handled.

---

## High

### H1 · Prompt injection can bypass the "confirm before acting" step
- **Files:** `central/.../includes/class-dealer-agent.php:255-263`, `class-whatsapp-agent.php:148-150`, `dealer/.../automotive-marketplace-connector/includes/class-ai-endpoint.php:204`
- **Code:** the reply sent is the model's own text; the server summary is used only when the model returns nothing: `if ( 'refusal' === $response->stopReason || '' === $text ) { $text = $pending ? $pending['summary'] ...`. Customer text reaches the model through tool results (`'message' => mb_substr( (string) $l->message, 0, 200 )` in list_leads); anyone can write a lead message (public form `class-marketplace-leads.php:66`, lead webhook).
- **Scenario:** an attacker's lead message instructs the model to prepare `update_vehicle_price vehicle_id=12 price_amount=1` and to tell the user it is only marking the lead as contacted. The salesperson asks "show my leads", reads "Mark lead Awa as contacted? Reply OUI", replies "oui" → `call_dealer(..., $pending['args'], true)` changes the price. The person approves text the model wrote, not what will run.
- **Fix:** (1) when `$pending` is set, always send a server-built confirmation message: `"Action en attente : {$pending['summary']} — répondez OUI pour confirmer, NON pour annuler."`; never let model text stand in for it. (2) Put action type + ids in the summary on the dealer site (`ai-endpoint.php:301,319,340`), e.g. `Vehicle #12 «Toyota…»: price 8 500 000 → 1 FCFA`; quote attacker-controlled names. (3) Refuse price changes beyond X % unless the user typed the amount. (4) Wrap third-party text in tool results in explicit untrusted-data markers.
- **Verify:** eval case with the injected lead message; the outgoing WhatsApp message must contain the real server summary.

### H2 · Voice channel identifies staff by caller ID, which can be faked
- **File:** `central/.../includes/class-telephony.php:87, 94, 141`
- **Code:** `$from = Ai_Contacts::normalize( (string) $request->get_param( 'from' ) );` → `$contact = $from ? Ai_Contacts::find( $from ) : null;` → `Whatsapp_Agent::respond( $from, $text )`.
- **Scenario:** an attacker spoofs a staff member's number and gets that person's dealer assistant (lists leads with personal data, confirms price/status changes by saying "oui"). The gateway signature proves only that the event came from the gateway.
- **Fix:** for `kind = 'call'`, never map the caller to a dealer contact (public buyer assistant only), or require a hashed per-user voice PIN checked in `Ai_Endpoint::execute` before any CRM tool; no write actions from voice.
- **Verify:** signed `utterance` event with `from` = registered staff number → answer contains no CRM data.

---

## Medium

### M1 · The "yes" detector accepts long sentences that start with yes
- **File:** `class-whatsapp-agent.php:156` — `preg_match( "/^(oui|yes|y|ok|okay|waaw|...|1)( .*)?$/", $t ) && mb_strlen( $t ) <= 25`
- **Scenario:** "Ok mais plutôt 4 500 000" or "Oui mais pas celle-là" count as **yes** and apply the old pending action.
- **Fix:** exact match only `^(oui|yes|waaw|ok|okay|confirme|confirmer|confirm|d'?accord)$`; anything else = not confirmed, ask again, keep pending; drop `1` and `y`.
- **Verify:** unit test `yes_no()`: "ok mais 4 500 000" ≠ yes; "oui" = yes.

### M2 · Plan limit and staff moderation can be bypassed through the sync API
- **Files:** `class-sync-api.php:284-302` (patch_status), `:251-256`, `class-platform-admin.php:202-205, 224-226`
- **Scenario:** a dealer at its limit PUTs listings as `unpublished` (not counted), then PATCHes them to `active` → unlimited listings. Staff hide a fraudulent listing; the dealer's next PUT/PATCH republishes it.
- **Fix:** `patch_status` calls `require_active()` and applies the limit check on non-public → public; add `moderated_at`/`hidden_by_staff`; when set, PUT/PATCH cannot change status (409 `amc_moderated`).
- **Verify:** integration test fill limit → PUT unpublished → PATCH active = 409.

### M3 · WhatsApp numbers linked to CRM accounts without proof of ownership; cross-dealer squatting
- **Files:** `dealer/.../connector/includes/class-contacts.php:42-58`, `central/.../class-ai-contacts.php:46-48`
- **Scenario:** a wrong/third-party number gets a member's CRM access over WhatsApp; dealer A pre-registers dealer B's staff numbers (first come, first served).
- **Fix:** one-time code via approved WhatsApp template; only verified contacts (`ai_contacts.verified_at`) reach the dealer agent; cross-dealer conflicts escalated to staff.
- **Verify:** unverified number must not reach the dealer agent.

### M4 · Dealer site may be plain HTTP; replay within 300 s
- **Files:** `class-sync-api.php:198`, `class-webhooks.php:165-166`, `class-dealer-agent.php:137-138`, `connector/class-connection.php:197-199`
- **Code:** `esc_url_raw( ..., array( 'https', 'http' ) )`; `Idempotency-Key` not covered by the signature; `/ai/execute` has no delivery id.
- **Scenario:** lead data readable on the network; a captured signed request (AI `confirm:true`, old heartbeat with fresh Idempotency-Key) replayed within 300 s.
- **Fix:** refuse non-HTTPS `site_url` in handshake unless `WP_DEBUG`; sign the Idempotency-Key on both sides; add UUID `X-AMC-Delivery` to `/ai/execute` stored in the connector inbox.
- **Verify:** handshake with `http://` → 400; replayed AI request rejected.

### M5 · Anthropic API key stored unencrypted
- **File:** `class-settings.php:127-128` (read back as is in `class-assistant.php:46`)
- **Scenario:** DB dump / SQL injection elsewhere / backup leak exposes the key (direct cost). Contradicts SECURITY-REVIEW.md "secrets encrypted at rest".
- **Fix:** `Crypto::encrypt` + read via `Settings::secret( 'ai_api_key', 'AUTOBUZY_ANTHROPIC_API_KEY' )` like `wa_access_token`; one-time migration.
- **Verify:** `wp option get amc_settings` must not show `sk-ant-`.

### M6 · Platform managers can change payment details and secrets, unaudited
- **Files:** `class-platform-admin.php:194-196`, `class-settings.php:139-143, 165-167`, `class-speech.php:55`
- **Code:** `case 'settings_save': update_option( Settings::OPTION, ... )` gated only by `Permissions::is_staff()`, no `Audit::log`; the form accepts `pay_wave_details`, `pay_om_details`, `pay_bank_details`, AI key, WhatsApp secrets, `delete_on_uninstall`, `stt_base_url`.
- **Scenario:** a rogue/compromised staff account swaps the Wave/Orange Money number → dealers pay the attacker, nothing logged; or redirects speech-to-text to collect voice notes + key.
- **Fix:** require `manage_options` for settings and channels; `Audit::log( 'settings.updated', … keys only )`; email the owner on any `pay_*` change.
- **Verify:** POST `settings_save` as `amc_platform_manager` → forbidden; as admin → audit row.

---

## Low

| ID | Title | File:line | Scenario | Fix |
|---|---|---|---|---|
| L1 | Clickjacking on dealer CRM | `class-api.php:47-49` (central only); dealer `class-manager.php`, `class-router.php:198-215` | `/manager/` frameable; one-click forms rely on JS `confirm()` | `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'` in `Router::dispatch` |
| L2 | Privacy erase/export incomplete | central `class-privacy.php:91-97`; dealer `class-privacy.php:81-96`; `class-webhooks.php:71` | Email-only erase; `wa_messages`, `wa_conversations`, `call_logs`, `webhook_deliveries.payload`, `lead_notes`, phone-only leads not covered | Phone-based erasure, cover tables, clear delivered payloads; `legal-compliance` check (CDP, CNDP) |
| L3 | Dealer agent ignores global daily AI cap | `class-dealer-agent.php:179, 20, 193`; cap only in `class-assistant.php:128` | One message → up to 7 paid calls | `within_daily_budget()` in `Dealer_Agent::reply`; count tokens |
| L4 | Weak anti-bot timer and rate limiter | `class-marketplace-leads.php:53`, dealer `class-public-site.php:98`, `class-rate-limiter.php:26-30` | Client timestamp fakeable; read-then-write race | Server-signed timestamp; `wp_cache_incr` with object cache |
| L5 | Any staff with one of 3 caps opens any customer document | dealer `class-files.php:129-135` | Rental agent opens sales customers' IDs | Check by file link: rental/sale/customer capability |
| L6 | DB write on every bad signature before rate limiting | `class-sync-api.php:80-81` | Flood credentials table | IP rate limit before signature check; count failures ≤ 1/min |
| L7 | SDK extras shipped in release zip | `tools/dev/build-release.sh:25` | examples/scripts/fixtures in zip | Exclude `*/vendor/*/examples/*`, `scripts`, `fixtures` |
| L8 | Missing HSTS/CSP; no wp-login throttling | whole repo | Brute force on `wp-login.php` | HSTS at server/CDN; throttle wp-login; 2FA for staff |

---

## Good practices confirmed (code review)
- Sync API: HMAC over timestamp+method+route+body hash, `hash_equals`, ±300 s, dealer from key only, protected fields stripped, idempotency store, revocation checked per request.
- Central → dealer calls via `wp_safe_remote_post` without redirects; lead webhook delivery ids deduplicated.
- WhatsApp webhook: `X-Hub-Signature-256` with `hash_equals` on raw body; phone-number-id filter; processing in cron; voice-note media hosts restricted.
- Sensitive AI tools re-check CRM permissions on the dealer site as the linked active member.
- Per-action nonces, capability checks, ownership checks; dealers cannot create a verified payment (`Payments::create` forces `pending`).
- Uploads: finfo type allow-list, size limits, random names, served behind capability check with `nosniff`, CSP sandbox, `nocache_headers`.
- Invitations: hashed 40-char token, 7-day expiry, allow-listed roles.
- SQL with user input always prepared.
- Secrets (except M5) with libsodium secretbox; no secret found in repo or git history.
- Redirects through `wp_validate_redirect` / `wp_safe_redirect`.

## Not verifiable by code review
- **nginx hosting:** private folders rely on `.htaccess` (ignored by nginx) → check direct URL access on the real host; better, store files outside web root.
- DNS rebinding vs `wp_safe_remote_post`; TLS and headers behind a CDN.
- Real effect of prompt injection (H1) and Wolof yes/no detection → adversarial eval set (`ai-agent-engineer`).
- Meta webhook signature on real payloads; retry dedupe under concurrency.
- `composer audit` on `vendor/`; rate limits with/without object cache.
- Sending lead data to Anthropic needs a data-processing agreement + privacy policy mention (`legal-compliance`).

## Proposed addition to `docs/SECURITY-REVIEW.md` (residual risks)
9. AI confirmations: confirmed text must be server-built (H1); exact-match yes/no (M1). 10. Voice: caller ID is not authentication; keep disabled until PIN/OTP (H2). 11. WhatsApp numbers verified by OTP before CRM access (M3). 12. HTTPS mandatory for dealer sites; Idempotency-Key signed; AI calls replay-protected (M4). 13. Anthropic key currently plaintext (M5). 14. Platform settings restricted to administrators and audited (M6). 15. "Secrets at rest" row inaccurate for `ai_api_key` until M5 is fixed.

## Assignment (CEO)
- `ai-agent-engineer`: H1, M1, H2, M3 — before any real WhatsApp/voice activation (outside this quarter's pilot scope, see `DECISIONS.md`).
- `wp-plugin-engineer`: M2, M4, M5, M6, L1–L6.
- `devops-release`: L7. `legal-compliance`: L2 + data-processing agreement.
- Then second review by `security-auditor`, then `qa-engineer`.
