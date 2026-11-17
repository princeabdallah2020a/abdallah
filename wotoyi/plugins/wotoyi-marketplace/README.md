# wotoyi-marketplace (core) — v0.1.0

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
4. Settings live in option `wotoyi_mp_settings` (no UI yet): `local_mode`, `clock_skew`, `key_grace`, `pairing_ttl`, `max_body_bytes`, `public_rate_limit`, `lead_rate_limit`, `sync_rate_limit`, `log_retention_days`, `delete_on_uninstall` (default **false**: uninstall keeps your data).

## Structure

```
wotoyi-marketplace.php        bootstrap (PHP version guard, hooks)
uninstall.php                 drops data only if delete_on_uninstall = true
includes/
  autoload.php, api.php       PSR-4 autoloader; public PHP API for local (demo) mode
  Plugin.php, Installer.php, Settings.php
  Schema/   Enums, Fields (generic sanitise/cast/DDL engine), VehicleFields (88 fields), ServiceFields, Schema (CREATE TABLE)
  Repository/  Abstract listing repo (versioned upsert/tombstone) + Vehicle, Service, Dealer, Lead, Favourite, SavedSearch, SyncLog, Nonce
  Search/   Filters (parse+validate), VehicleQuery (SQL), SearchService (search, count, facets, detail, services)
  Presenter/  Vehicle, Service, Dealer public JSON shapes
  Sync/     Signer, Authenticator (HMAC), Pairing, Receiver, Transport, HttpTransport, LocalTransport
  Rest/     Routes, Permissions, Public/Account/Admin/Sync controllers
  Support/  Crypto (libsodium), Clock, Db, Json, Cache, RateLimiter, Ip, LeadGuard, Uuid (v7)
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
| `leads` | buyer leads; the marketplace is the outbox (`delivery_status` pending/delivered). Raw IPs are never stored (keyed hash only). |
| `favourites`, `saved_searches` | per logged-in user (500 / 20 max). |
| `sync_log` | one row per (dealer, event_id): idempotency ledger + audit (status, http status, result, duration). No secrets, no raw payload. |
| `sync_nonces` | replay protection (dealer_id, nonce, expires_at). |

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
| `GET /leads/token`, `POST /leads` | lead capture: time token (3 s – 2 h) + honeypot field `website` + per-IP limit (5 / 10 min) + required consent + phone or email. |

**Search filters (52 parameters, URL-shareable).** Lists accept `a,b` or `a[]=a&a[]=b`.
`q` (up to 5 terms, AND), `offer_type` (sale = sale+both, rental = rental+both, both), `rental_duration`, `make`, `model`, `trim`, `body_type`, `price_min`, `price_max`, `monthly_price_max` (rental month), `year_min/max`, `mileage_min/max`, `fuel`, `transmission`, `drive`, `engine_min/max` (cc), `power_min/max` (hp), `consumption_max`, `range_min` (electric km), `seats_min`, `doors`, `ext_colour`, `int_colour`, `upholstery`, `condition` (new/used/imported), `origin_country`, `customs_status`, `registration_country`, `owners_max`, `accident_free`, `service_history`, `warranty`, `inspected`, `verified_dealer`, `dealer` (uuids), `city`, `area`, `lat`+`lng`+`radius_km` (≤500, haversine; also `sort=distance`), `delivery`, `financing`, `trade_in`, `equipment` (all listed codes required, max 10), `posted_since` (days), `has_photos`, `has_video`, `has_360`.
Sorts: `relevance` (featured first, then newest), `price_asc|desc`, `year_asc|desc`, `mileage_asc|desc`, `newest`, `views`, `distance`.
Rules: unknown parameters are dropped (a request can never change `status`/`dealer_id`); invalid enum values are ignored (not "no results"); numbers are clamped; a row with NULL in a filtered column never matches that filter; flags only narrow when true.

**Buyer account** (logged in; writes also need `X-WP-Nonce`)
`GET/POST /me/favourites`, `DELETE /me/favourites/{vehicle|service}/{ref}`, `GET/POST /me/saved-searches`, `DELETE /me/saved-searches/{id}`.

**Marketplace staff** (capability `wotoyi_manage_marketplace` + REST nonce)
`POST /admin/dealers` (creates a dealer and returns the pairing code once), `POST /admin/dealers/{uuid}/pairing-code`, `POST /admin/dealers/{uuid}/status` (`status`, `verified`; suspend/disconnect revoke credentials), `GET /admin/dealers/{uuid}/sync-log`.

**Sync receiver** (signed with the dealer's HMAC key, except `pair`)

| Route | Purpose |
|---|---|
| `POST /sync/pair` | exchange a pairing code for `key_id` + `secret` (returned once). |
| `GET /sync/health` | signed health check + counts. |
| `POST /sync/events` | one event (below). `POST /sync/batch` `{events:[…≤50]}` for initial sync. |
| `PUT/DELETE /sync/vehicles/{source_id}`, `PUT/DELETE /sync/services/{source_id}` | REST-style wrappers; the idempotency key is derived from method + path + body. |
| `POST /sync/leads/pull` `{limit}`, `POST /sync/leads/ack` `{lead_ids:[uuid…]}` | the Dealer Platform pulls its own pending leads and acknowledges them. |
| `POST /sync/manifest` `{type, cursor, limit}` | reconciliation: `source_id, version, status` of everything held for this dealer (tombstones included). |
| `POST /sync/rotate-key`, `POST /sync/disconnect` | rotate the secret (old one valid for `key_grace` s) / revoke. |

## Sync protocol (Dealer Platform → Marketplace)

Pairing (PROJECT.md §13.6): staff create the dealer → one-time code `WTY-XXXX-XXXX-XXXX` (60 bits, 15 min, single use, stored as keyed hash) → the Dealer Platform posts `{code, source_dealer_uuid, [profile]}` to `/sync/pair` → receives `{dealer_id, key_id, secret}`. 5 failed attempts per IP / 15 min and 100 overall / hour lock pairing (even for a correct code).

Signing: headers `X-Wotoyi-Key`, `X-Wotoyi-Timestamp` (unix s, ±300 s), `X-Wotoyi-Nonce` (16–64 chars `[A-Za-z0-9_-]`, single use), `X-Wotoyi-Signature` = hex `HMAC-SHA256(secret, canonical)` with

```
canonical = "WOTOYI-HMAC-SHA256\n" . timestamp . "\n" . nonce . "\n" . METHOD . "\n" . route . "\n" . sha256_hex(body)
route     = "/wotoyi/v1/sync/events"      (the REST route, no host, no query string)
```

Reference implementation: `Sync\Signer::headers()` and `Sync\HttpTransport` (also usable from the Dealer Platform plugin). Handlers never read query parameters, only the route and the signed body.

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
- Leads: honeypot + signed time token + per-IP limit + consent flag; the dealer can only pull/acknowledge its own leads.
- Tenant isolation is tested as a matrix (dealer A tries events, wrappers, batch, spoofed identity against dealer B; B's rows stay byte-identical).

## Tests

```
php tests/run.php [filter]      # standalone runner, exit code 1 on failure
bin/check.sh                    # php -l on every file + full suite
```

173 tests / 4,662 assertions at the time of writing (see Known limits for what they prove). `tests/shim/` is a **test-only** mini WordPress (options, transients, hooks, `WP_REST_*`, a route dispatcher that enforces `permission_callback`) and a `$wpdb` look-alike on PDO SQLite; the production code under `includes/` runs unmodified on top of it. Notices/warnings/deprecations are converted to exceptions.

## Known limits (honest list)

1. **Not proven on MySQL/MariaDB or real WordPress.** Tests run on SQLite via a shim: real `dbDelta`, MySQL strict mode, collations, `FOR UPDATE` locking and `ASIN/SQRT` behaviour were not exercised. Static checks cover dbDelta formatting, row width (< 65,535 B) and index key width (≤ 767 B). Run the QA matrix of DEV_BRIEF_V2 §6 on a fresh WordPress before relying on it.
2. Only PHP 8.3.6 was available; the code avoids known 8.2+ features but was **not executed on 8.1**.
3. WPCS/`phpcs` was **not run** (not installed; package registry blocked in this environment). Code is hand-formatted to WP style with `phpcs:ignore` markers on reviewed SQL calls; expect some sniff findings (PSR-4 file names, `Yoda` style in places).
4. Search uses `LIKE` on a denormalised `search_text` (no FULLTEXT, no typo tolerance, no relevance beyond featured → newest). Text filters rely on a case-insensitive collation. Only 22 composite indexes exist; low-selectivity filters scan rows within the `status` index range. Review with `EXPLAIN` on real data volumes.
5. Counts/facets are cached in transients (30–60 s, version-bump invalidation) and the rate limiter uses transients too: without a persistent object cache this creates short-lived option rows and is not atomic under heavy concurrency.
6. Out-of-order edge: an `unpublished`/`sold` event for an entity the marketplace has never seen is a no-op (not a tombstone), so a late older `published` could list it. Use `/sync/manifest` reconciliation (the Dealer Platform nightly job must be written) to repair.
7. No wp-admin screens, no moderation queue (only the `wotoyi_mp_can_publish` hook), no subscription/plan enforcement, no marketplace-initiated push to dealers, no lead notification email/WhatsApp, no REST endpoint to grant the Wotoyi inspection badge (`VehicleRepository::set_inspection()` is the PHP entry point).
8. **Personal data:** leads hold names/phones/emails; there is no retention job and no erasure/export endpoint yet (needed for the Senegal data-protection law / GDPR; CISO + Legal item). Leads are never pruned by the daily job.
9. Media are URLs only (http/https); no host allow-list, no reachability check, no upload or mirroring.
10. Labels/translations are not in this plugin (enum codes only); text domain is loaded but no `.pot` exists; `wotoyi-i18n` owns UI strings.
11. Single-site only (multisite untested). Sync request body is limited to 1 MB; batch to 50 events.
12. No OpenAPI file yet (the endpoint tables above are the contract).
