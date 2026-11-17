# wotoyi-marketplace (core) — v0.2.0

Marketplace core plugin: vehicle + service catalogue, "maximum search", dealers, buyer favourites / saved searches / leads, and the **signed receiver** that lets the Dealer Platform create, update and unpublish marketplace vehicles and services automatically (PROJECT.md §13.6, DEV_BRIEF_V2 §3.1–§3.4, §5).

- PHP 8.1+, WordPress 6.4+, no external dependencies, no Composer, no JS.
- Namespace `Wotoyi\Marketplace`, REST namespace `wotoyi/v1`, tables `{prefix}wotoyi_*`.
- This package contains **no theme, no templates and no admin screens**. Frontend plugins/themes consume the REST API (or the PHP services in `Plugin::search()`).

## Install

1. Copy the folder to `wp-content/plugins/wotoyi-marketplace/` and activate it. Activation creates the tables (`dbDelta`), grants the capability `wotoyi_manage_marketplace` to administrators and schedules the daily prune job.
2. **Recommended:** add a long random secret to `wp-config.php` (used to encrypt dealer secrets at rest and to key hashes):
   `define( 'WOTOYI_SECRET_KEY', '<64+ random chars>' );`
   Without it the key is derived from the WordPress salts; rotating the salts then makes stored dealer secrets unreadable (dealers must be re-paired).
3. Behind a proxy/CDN, hook `wotoyi_mp_client_ip` to return the real client IP (only `REMOTE_ADDR` is trusted by default; rate limits depend on it).
4. Settings live in option `wotoyi_mp_settings` (no UI yet): `local_mode`, `clock_skew`, `key_grace`, `pairing_ttl`, `max_body_bytes`, `public_rate_limit`, `lead_rate_limit`, `sync_rate_limit`, `log_retention_days`, `audit_retention_days`, `lead_retention_delivered_days`, `lead_retention_undelivered_days`, `lead_retention_disconnected_days`, `lead_consent_version`, `lead_dealer_daily_cap`, `lead_contact_daily_cap`, `lead_contact_dealer_daily_cap`, `lead_challenge_required`, `delete_on_uninstall` (default **false**: uninstall keeps your data). Each setting can also be overridden with the filter `wotoyi_mp_setting_<name>`.
5. **HTTPS is mandatory** for `/sync/*` (see *Transport security*). Behind a TLS-terminating proxy/CDN that WordPress cannot see, vouch for the request with the filter `wotoyi_mp_request_is_secure`.

## Structure

```
wotoyi-marketplace.php        bootstrap (PHP version guard, hooks)
uninstall.php                 drops data only if delete_on_uninstall = true
includes/
  autoload.php, api.php       PSR-4 autoloader; public PHP API for local (demo) mode
  Plugin.php, Installer.php, Settings.php
  Schema/   Enums, Fields (generic sanitise/cast/DDL engine), VehicleFields (88 fields), ServiceFields, Schema (CREATE TABLE)
  Repository/  Abstract listing repo (versioned upsert/tombstone) + Vehicle, Service, Dealer, Lead, Favourite, SavedSearch, SyncLog, Nonce, Audit
  Search/   Filters (parse+validate), VehicleQuery (SQL), SearchService (search, count, facets, detail, services)
  Presenter/  Vehicle, Service, Dealer public JSON shapes
  Sync/     Signer, Authenticator (HMAC), Pairing, Receiver, Transport, HttpTransport, LocalTransport
  Privacy/  Privacy (WP exporter + eraser, delete_user cleanup, policy text)
  Rest/     Routes, Permissions, Public/Account/Admin/Sync controllers
  Support/  Crypto (libsodium), Clock, Db, Json, Cache, RateLimiter, Ip (IPv6 /64 buckets), LeadGuard, Tls, Uuid (v7)
tests/       standalone runner + WordPress/SQLite shim (see Tests)
bin/check.sh php -l on every file, then the full test suite
```

(Files follow PSR-4 names rather than WPCS `class-*.php` names: deliberate, because the autoloader maps namespaces to folders.)

## Data model

| Table | Purpose |
|---|---|
| `vehicles` | 105 columns: 88 catalogue fields (all optional) + system columns. Groups: identity (18), powertrain (11), consumption/environment (9), dimensions (11), equipment (1 JSON map, normalised into `vehicle_equipment`), condition (6), history (5), pricing (14), location (5), media (5), trust (3, **marketplace-managed**). `dealer_id` + `source_id` unique (tenant-scoped idempotent key); `version`, `status` (draft/published/unpublished/sold/deleted), tombstones via `deleted_at`. 22 secondary indexes, all led by `status`, for the search filters. |
| `vehicle_equipment` | (vehicle_id, code, category) for the AND-multi-select equipment filter. |
| `dealers` | profile, status (pending/active/suspended/disconnected), `verified`, `transport` (http/local), key id, **encrypted** secret (+ previous secret during rotation grace), hashed pairing code. |
| `services` | maintenance, repair, bodywork, detailing, rental with driver, import, inspection, insurance, financing, towing, spare parts, other. |
| `leads` | buyer leads; the marketplace is the outbox (`delivery_status` pending/delivered). Raw IPs are never stored (keyed hash of the IP, IPv6 as /64). Consent proof: `consent_at`, `consent_version`, `consent_purpose`. `anonymized_at` is set when retention/erasure cleared the personal columns. |
| `favourites`, `saved_searches` | per logged-in user (500 / 20 max). |
| `sync_log` | one row per (dealer, event_id): idempotency ledger + audit (status, http status, result, duration). No secrets, no raw payload. |
| `sync_nonces` | replay protection (dealer_id, nonce, expires_at). |
| `audit_log` | append-only trail of staff and security actions (see *Audit log*). |

Public ids are UUID v7 (+ slug). Internal numeric ids never appear in responses. `vin` and `plate` are stored for the dealer record but **never** returned by any public endpoint.

## REST API (`/wp-json/wotoyi/v1`)

Every route has a real `permission_callback` (enforced by a test). Public GETs are rate-limited per IP.

**Public catalogue**

| Route | Notes |
|---|---|
| `GET /vehicles` | search: filters, `sort`, `page`, `per_page` (max 48). Returns `items,total,page,per_page,pages,sort,filters` (normalised filters echoed); headers `X-WP-Total`, `X-WP-TotalPages`. |
| `GET /vehicles/count` | live result count for the filter panel (cached 30 s, invalidated on any write). |
| `GET /facets` | facet counts (make, model*, trim*, body_type, fuel, transmission, drive, condition, ext_colour, city, origin_country, customs_status, offer_type, equipment) + ranges (price, year, mileage_km). Each facet ignores its own filter. *model needs `make`, trim needs `model`. `facets=make,fuel` selects. |
| `GET /vehicles/{uuid-or-slug}` | detail grouped in tabs (`identity, engine, consumption, dimensions, equipment, history, commercial, media, trust`); empty tabs omitted; `lang=fr|en|wo|ar` with fallback lang → fr → en. Published and sold only. |
| `POST /vehicles/{ref}/view` | real view counter (deduplicated per visitor for 30 min). |
| `GET /services`, `GET /services/{ref}` | services search (`q, category, city, area, price_max, mobile_service, dealer, verified_dealer, lat/lng/radius_km, sort, page`). |
| `GET /dealers`, `GET /dealers/{ref}` | public dealer directory (no email, no credentials). |
| `GET /meta` | filter names, sorts, enumerations, equipment taxonomy. |
| `GET /leads/token[?vehicle=<ref>\|service=<ref>]`, `POST /leads` | lead capture: **single-use** time token (3 s – 2 h) bound to the requester's IP bucket and, when `vehicle`/`service` is passed to `/leads/token`, to that listing (response has `bound:true`) + honeypot field `website` + per-/64 limit (5 / 10 min) + per-dealer (200/day), per-contact (10/day) and per-contact-per-dealer (5/day) caps + required consent (optional `consent_version`) + phone or email + optional challenge provider (filter `wotoyi_mp_lead_challenge`). The token must be sent in `token`; fetch a fresh one for every submission. |

**Search filters (52 parameters, URL-shareable).** Lists accept `a,b` or `a[]=a&a[]=b`.
`q` (up to 5 terms, AND), `offer_type` (sale = sale+both, rental = rental+both, both), `rental_duration`, `make`, `model`, `trim`, `body_type`, `price_min`, `price_max`, `monthly_price_max` (rental month), `year_min/max`, `mileage_min/max`, `fuel`, `transmission`, `drive`, `engine_min/max` (cc), `power_min/max` (hp), `consumption_max`, `range_min` (electric km), `seats_min`, `doors`, `ext_colour`, `int_colour`, `upholstery`, `condition` (new/used/imported), `origin_country`, `customs_status`, `registration_country`, `owners_max`, `accident_free`, `service_history`, `warranty`, `inspected`, `verified_dealer`, `dealer` (uuids), `city`, `area`, `lat`+`lng`+`radius_km` (≤500, haversine; also `sort=distance`), `delivery`, `financing`, `trade_in`, `equipment` (all listed codes required, max 10), `posted_since` (days), `has_photos`, `has_video`, `has_360`.
Sorts: `relevance` (featured first, then newest), `price_asc|desc`, `year_asc|desc`, `mileage_asc|desc`, `newest`, `views`, `distance`.
Rules: unknown parameters are dropped (a request can never change `status`/`dealer_id`); invalid enum values are ignored (not "no results"); numbers are clamped; a row with NULL in a filtered column never matches that filter; flags only narrow when true.

**Buyer account** (logged in; writes also need `X-WP-Nonce`)
`GET/POST /me/favourites`, `DELETE /me/favourites/{vehicle|service}/{ref}`, `GET/POST /me/saved-searches`, `DELETE /me/saved-searches/{id}`.

**Marketplace staff** (capability `wotoyi_manage_marketplace` + REST nonce)
`POST /admin/dealers` (creates a dealer and returns the pairing code once), `POST /admin/dealers/{uuid}/pairing-code`, `POST /admin/dealers/{uuid}/status` (`status`, `verified`, `revoke_previous_secret`; suspend/disconnect revoke credentials), `GET /admin/dealers/{uuid}/sync-log`, `GET /admin/audit-log[?dealer=<uuid>&limit&before_id]`. Every write is recorded in the audit log.

**Sync receiver** (signed with the dealer's HMAC key, except `pair`)

| Route | Purpose |
|---|---|
| `POST /sync/pair` | exchange a pairing code for `key_id` + `secret` (returned once). |
| `GET /sync/health` | signed health check + counts. |
| `POST /sync/events` | one event (below). `POST /sync/batch` `{events:[…≤50]}` for initial sync. |
| `PUT/DELETE /sync/vehicles/{source_id}`, `PUT/DELETE /sync/services/{source_id}` | REST-style wrappers; the idempotency key is derived from method + path + body. |
| `POST /sync/leads/pull` `{limit}`, `POST /sync/leads/ack` `{lead_ids:[uuid…]}` | the Dealer Platform pulls its own pending leads and acknowledges them. |
| `POST /sync/manifest` `{type, cursor, limit}` | reconciliation: `source_id, version, status` of everything held for this dealer (tombstones included). |
| `POST /sync/rotate-key`, `POST /sync/disconnect` | rotate the secret (old one valid for `key_grace` s **for content routes only**) / revoke. Both accept **only the current secret**: a superseded secret gets 401 `wotoyi_secret_superseded`. |

**Every `/sync/*` request must use HTTPS and carry no query string** (see below).

## Sync protocol (Dealer Platform → Marketplace)

Pairing (PROJECT.md §13.6): staff create the dealer → one-time code `WTY-XXXX-XXXX-XXXX` (60 bits, 15 min, single use, stored as keyed hash) → the Dealer Platform posts `{code, source_dealer_uuid, [profile]}` to `/sync/pair` → receives `{dealer_id, key_id, secret}`. 5 failed attempts per IP (/64 for IPv6) / 15 min lock that IP (even for a correct code). 100 *missed* codes overall / hour block further misses, but never a code that matches a live record, so junk traffic cannot stop onboarding. A dealer record created by staff with a `source_dealer_uuid` requires that exact value when pairing; pairing only fills **empty** profile fields (it never overwrites staff-set values such as `website_url`, `logo_url`, `phone`).

Signing: headers `X-Wotoyi-Key`, `X-Wotoyi-Timestamp` (unix s, ±300 s), `X-Wotoyi-Nonce` (16–64 chars `[A-Za-z0-9_-]`, single use), `X-Wotoyi-Signature` = hex `HMAC-SHA256(secret, canonical)` with

```
canonical = "WOTOYI-HMAC-SHA256\n" . timestamp . "\n" . nonce . "\n" . METHOD . "\n" . route . "\n" . sha256_hex(body)
route     = "/wotoyi/v1/sync/events"      (the REST route, no host, no query string)
```

Reference implementation: `Sync\Signer::headers()` and `Sync\HttpTransport` (also usable from the Dealer Platform plugin). The canonical string is unchanged since v0.1.0.

**Transport security (v0.2.0, behaviour changes for clients):**

- `/sync/pair` and every signed route answer 403 `wotoyi_https_required` unless the request is HTTPS. `HttpTransport` throws on a non-`https://` base URL.
- A signed request with **any query string** is refused with 400 `wotoyi_query_not_allowed` (the signature does not cover it and WordPress lets a query value shadow a route value). Only `rest_route` (plain-permalink sites) is tolerated; extend with filter `wotoyi_mp_sync_allowed_query_params`. Put everything in the JSON body.
- The REST wrappers `PUT/DELETE /sync/{vehicles|services}/{source_id}` take the id **only** from the signed URL; a different `source_id` in the body is 422.
- Local development/tests only: `define( 'WOTOYI_MP_ALLOW_INSECURE', true )` or filter `wotoyi_mp_allow_insecure_transport` allows `http://`. It is **ignored when `wp_get_environment_type()` is `production`** (the WordPress default).
- `rotate-key` and `disconnect` require the *current* secret; `previous` (grace period) works for everything else. Staff can end the grace period at once with `revoke_previous_secret`.
- The per-key rate limit (`sync_rate_limit`) is counted after the signature is verified; unauthenticated traffic is limited per IP only.

Event envelope (PROJECT.md §13.4):

```json
{ "event_id": "8-64 chars [A-Za-z0-9_.:-]", "event_type": "vehicle.published", "occurred_at": "2026-11-17T10:00:00Z",
  "dealer_uuid": "optional, must be this dealer", "api_version": "v1",
  "data": { "source_id": "dealer-platform-vehicle-id", "version": 12, "make": "Toyota", "model": "RAV4", "…": "catalogue fields" } }
```

Types: `vehicle.{published,updated,unpublished,sold,deleted}`, `service.{published,updated,unpublished,deleted}`.

| Rule | Behaviour |
|---|---|
| Snapshot | `published`/`updated` replace all dealer-controlled fields; absent = cleared. `version` ≥ 1 required. |
| Quality rules | vehicle needs `title` or `make`+`model`; service needs `title` (else 422 `quality_rules`, logged). Filter `wotoyi_mp_can_publish` (plan limits/moderation) can refuse. |
| Ordering | version lower than stored → 409 `stale_version`, equal → `unchanged`. Delete is a tombstone, so late older events cannot resurrect a listing. |
| Idempotency | `(dealer, event_id)` claimed in `sync_log`; a replay returns the stored result with `duplicate:true`; same id + different payload → 409 `event_id_conflict`; a `failed` (5xx) event can be retried with the same id. **A corrected payload needs a new event_id.** |
| Tenant isolation | the dealer is the authenticated key, never the payload; any `dealer_uuid`/`dealer_id` naming someone else → 403 `tenant_mismatch`. Same `source_id` in two dealers = two separate listings. |
| Managed fields | `wotoyi_inspected`, `inspection_date`, `inspection_report_url`, `featured`, `views`, `slug`, `uuid`, `status` columns are never writable by a dealer payload. |
| Result | `{status: applied|unchanged|stale|rejected|failed, http_status, event_id, entity:{type,id,source_id,version,status}, warnings[], duplicate?}`; warnings list ignored/invalid fields (`invalid_value:x`, `unknown_field:x`, `managed_field_ignored:x`). |

### Local transport (InfinityFree single-install demo)

No HTTP: the Dealer Platform plugin on the same WordPress calls PHP functions that reach the **same** `Receiver` (validation, versions, idempotency, `sync_log` with `transport=local`).

```php
// wp-config.php or option wotoyi_mp_settings[local_mode]
define( 'WOTOYI_MP_LOCAL_MODE', true );

wotoyi_marketplace_local_register_dealer( 'dealer-platform-dealer-id', array( 'name' => 'Concessionnaire Démo', 'city' => 'Dakar' ) );
$result = wotoyi_marketplace_local_receive( 'dealer-platform-dealer-id', $event ); // same envelope as HTTP
// Also: Plugin::local_transport()->send_batch(), ->pull_leads(), ->ack_leads()
```

Safeguards: off by default (every call throws `local_mode_disabled`); local dealers have no key so they cannot be used over HTTP, and an HTTP-paired dealer cannot be addressed or taken over through local mode. Do **not** enable it on a production marketplace; the HMAC layer is replaced by "same PHP process" trust.

## Security notes (PROJECT.md §14.3)

- Capabilities + REST nonces on staff and buyer writes (explicit check in `Permissions`, on top of core cookie auth; non-cookie auth such as application passwords is accepted without nonce).
- Input: every filter goes through `Filters::parse` (whitelists, clamps, caps); every payload field through `Fields::sanitize` (typed, ranged, HTML stripped, `http/https` URLs only, equipment codes whitelisted). Output: public presenters re-validate URLs; JSON consumers must still escape on render (`esc_html`/`esc_attr`).
- SQL: a single choke point `Db::prepare()`; column names/SQL snippets come only from hard-coded specs; `LIKE` is escaped. A test fails the build if any SQL call bypasses it, and another replays injection payloads and inspects every executed statement.
- HMAC: constant-time compare (`hash_equals`), timestamp window, single-use nonce (atomic insert on a primary key), only authenticated nonces are stored, one generic error for unknown key and bad signature, per-IP failure throttle, per-key rate limit, body size cap (1 MB), no redirects in `HttpTransport`.
- Secrets: dealer secrets encrypted with libsodium secretbox (key from `WOTOYI_SECRET_KEY`), shown once, rotatable with grace; pairing codes and IPs stored only as keyed hashes; `sync_log` and API responses never contain secrets.
- Leads: honeypot + signed, single-use, IP- and (optionally) listing-bound token + per-/64, per-dealer and per-contact limits + optional challenge provider + consent proof (time, text version, purpose); the dealer can only pull/acknowledge its own leads.
- Vehicle `documents` (registration cards, customs papers) are stored but **never returned by the public API**.
- Audit log, retention and privacy tooling: see the next two sections.
- Tenant isolation is tested as a matrix (dealer A tries events, wrappers, batch, spoofed identity against dealer B; B's rows stay byte-identical).

## Audit log (PROJECT.md §14.5)

Table `audit_log`, append-only (repository exposes `record`, `recent`, age-based `prune` only; retention `audit_retention_days`, default 365, 0 = forever). Recorded: `dealer.create`, `pairing_code.issue`, `dealer.status` (before/after), `dealer.verified` (before/after), `key.revoke_previous`, `pairing.success` (dealer), `key.rotate` and `dealer.disconnect` (dealer, via the signed API), `leads.retention`, `privacy.erase`, `privacy.user_deleted`. Each row has actor type + WP user id, dealer, time, keyed IP hash and a correlation id (the `X-Request-Id` when the dealer sent one). `details` is restricted to a whitelist of non-secret keys, so secrets, pairing codes, e-mail addresses and raw IPs cannot reach it. Read it with `GET /admin/audit-log` or `Plugin::audit()->recent()`.

## Privacy and retention of buyer data (H2)

What the marketplace holds about buyers: leads (name, phone, e-mail, message, consent proof, IP hash), favourites and saved searches.

| Rule | Default | Setting |
|---|---|---|
| Delivered leads: name, phone, e-mail, message, IP hash, user id erased (row kept for statistics) | 30 days after delivery | `lead_retention_delivered_days` |
| Leads the dealer never collected: deleted | 90 days after creation | `lead_retention_undelivered_days` |
| Leads of a disconnected dealer: deleted | 30 days after disconnection | `lead_retention_disconnected_days` |

`0` disables a rule. The rules run in the daily `wotoyi_mp_prune` cron job (500 rows per batch, at most 20 batches per rule per run). **WP-Cron only fires on traffic: on a quiet site, trigger it from a real cron (`wp cron event run wotoyi_mp_prune`).** The defaults are a proposal: Legal must confirm them (Senegal law n° 2008-12 / CDP declaration, GDPR for EU residents).

- **Consent proof** is stored on every lead: `consent_at`, `consent_version` (the client may send `consent_version`; default `lead_consent_version`) and `consent_purpose` (`transmit_to_dealer`). Pending leads returned to the dealer include `consent_at` and `consent_version` (additive fields).
- **Export / erasure:** registered with WordPress Tools > Export/Erase Personal Data (exporter and eraser `wotoyi-marketplace`), matched by e-mail (case-insensitive) and by the WP user with that e-mail. The eraser deletes undelivered leads, anonymises delivered ones, deletes favourites and saved searches, and tells the data subject that a dealer keeps its own copy of a delivered lead (separate controller).
- **User deletion:** `delete_user` removes the user's favourites and saved searches and unlinks `leads.user_id`.
- **Privacy notice:** suggested text is added to Settings > Privacy > Policy Guide. Record of processing (for the CDP file): purpose = transmit a buyer's contact request to the dealer of the chosen listing; data = above; recipients = the dealer, the marketplace operator, hosting provider; retention = table above.
- `uninstall.php` keeps all data unless `delete_on_uninstall` is true: set it (or erase leads manually) when you decommission a site.
- Phone, e-mail and message are still stored in clear text (L8, open): use an encrypted database/backups.

## Tests

```
php tests/run.php [filter]      # standalone runner, exit code 1 on failure
bin/check.sh                    # php -l on every file + full suite
```

215 tests / 5,355 assertions at the time of writing (173 original + 42 regression tests for the security review; see Known limits for what they prove). `tests/shim/` is a **test-only** mini WordPress (options, transients, hooks, `WP_REST_*` with WordPress's real parameter order JSON > POST > query > URL, a route dispatcher that enforces `permission_callback`; `is_ssl()` is true by default, flip `$GLOBALS['wotoyi_is_ssl']`) and a `$wpdb` look-alike on PDO SQLite; the production code under `includes/` runs unmodified on top of it. Notices/warnings/deprecations are converted to exceptions.

## Known limits (honest list)

1. **Not proven on MySQL/MariaDB or real WordPress.** Tests run on SQLite via a shim: real `dbDelta`, MySQL strict mode, collations, `FOR UPDATE` locking and `ASIN/SQRT` behaviour were not exercised. Static checks cover dbDelta formatting, row width (< 65,535 B) and index key width (≤ 767 B). Run the QA matrix of DEV_BRIEF_V2 §6 on a fresh WordPress before relying on it.
2. Only PHP 8.3.6 was available; the code avoids known 8.2+ features but was **not executed on 8.1**.
3. WPCS/`phpcs` was **not run** (not installed; package registry blocked in this environment). Code is hand-formatted to WP style with `phpcs:ignore` markers on reviewed SQL calls; expect some sniff findings (PSR-4 file names, `Yoda` style in places).
4. Search uses `LIKE` on a denormalised `search_text` (no FULLTEXT, no typo tolerance, no relevance beyond featured → newest). Text filters rely on a case-insensitive collation. Only 22 composite indexes exist; low-selectivity filters scan rows within the `status` index range. Review with `EXPLAIN` on real data volumes.
5. Counts/facets are cached in transients (30–60 s, version-bump invalidation) and the rate limiter uses transients too: without a persistent object cache this creates short-lived option rows and is not atomic under heavy concurrency.
6. Out-of-order edge: an `unpublished`/`sold` event for an entity the marketplace has never seen is a no-op (not a tombstone), so a late older `published` could list it. Use `/sync/manifest` reconciliation (the Dealer Platform nightly job must be written) to repair.
7. No wp-admin screens, no moderation queue (only the `wotoyi_mp_can_publish` hook), no subscription/plan enforcement, no marketplace-initiated push to dealers, no lead notification email/WhatsApp, no REST endpoint to grant the Wotoyi inspection badge (`VehicleRepository::set_inspection()` is the PHP entry point).
8. **Personal data:** retention, export/erasure, user-deletion cleanup and consent proof exist since v0.2.0 (see *Privacy*), but the retention defaults are unvalidated by Legal, the cron needs a real scheduler on low-traffic sites, lead phone/e-mail/message are stored unencrypted (SECURITY-REVIEW L8) and a delivered lead cannot be erased from the dealer's own system.
9. Media are URLs only (http/https); no host allow-list, no reachability check, no upload or mirroring.
10. Labels/translations are not in this plugin (enum codes only); text domain is loaded but no `.pot` exists; `wotoyi-i18n` owns UI strings.
11. Single-site only (multisite untested). Sync request body is limited to 1 MB; batch to 50 events.
12. No OpenAPI file yet (the endpoint tables above are the contract).
13. **Open security findings (see SECURITY-REVIEW.md):** M6 local transport has no caller authorisation, creates dealers as active and can be enabled from the option/filter (keep it OFF in production); L2 a buyer cannot remove a favourite once the item is unpublished; L3 media URLs accept `http` and any host; L4 no per-dealer event quotas (limits count requests, not events); L5 the rate limiter is transient based and non-atomic; L6 the 1 MB body cap is checked after the body is read; L7 nonce/event-id keys use the default case-insensitive collation; L8 lead contact data is not encrypted at rest.
14. **Proxy and sync clients:** behind a CDN that terminates TLS, WordPress sees HTTP: sync is refused until you set the `wotoyi_mp_request_is_secure` filter. Per-IP limits use `REMOTE_ADDR` unless `wotoyi_mp_client_ip` is hooked.
15. Lead tokens and the contact/dealer caps use transients (not atomic, see 5); a determined attacker racing two requests with one token may get both through. Single-use is best-effort on hosts without a persistent object cache.
