# Wotoyi Dealer Platform (core)

Multi-tenant dealer back office for WordPress: CRM, vehicle and service stock, deals, rentals, employees, tasks,
dashboard KPIs, plus the sync engine that publishes a dealer's catalogue to the Wotoyi Marketplace and pulls
marketplace leads back into the CRM.

- PHP 8.1+, no Composer, no external dependencies. REST namespace `wotoyi-pro/v1`.
- Tables: `{prefix}wotoyi_dp_*` (they never collide with the marketplace's `wotoyi_*` tables).
- Only this folder was touched. No git operations were run by the author.

## Status: read this first

| Claim | Evidence |
|---|---|
| 85 tests, 1466 assertions pass (service, REST, tenant isolation, sync, static checks) | `php tests/run.php`, PHP 8.3.6 |
| 7 cross-plugin tests, 89 assertions pass (both plugins in one process, real marketplace receiver/search) | `php tests/integration/run.php` |
| All 74 PHP files lint clean | `bin/check.sh` |
| **NOT proven on real MySQL/MariaDB or a real WordPress** | Tests run on a WP shim plus SQLite (`tests/shim`). `dbDelta` output, MySQL collations, `FOR UPDATE`-style locking and real `wpdb` behaviour are untested. |
| **NOT run on PHP 8.1 / 8.2** | Only 8.3.6 was available. The code avoids 8.2+ only features by intent, not by proof. |
| **phpcs / WordPress Coding Standards not run** | Not installed here. |
| **No browser, no PWA, no load test** | Backend only. |

Run everything: `bin/check.sh` (needs `../wotoyi-marketplace` for the integration suite).

## Install

1. Copy the folder to `wp-content/plugins/` and activate.
2. Define a secret in `wp-config.php` (required, at least 32 random bytes, never commit it):
   `define( 'WOTOYI_SECRET_KEY', '<long random string>' );`
   Without it the plugin refuses to store sync secrets (pairing returns 503 `wotoyi_secret_key_missing`) and shows an
   admin notice. There is deliberately no fallback to WordPress salts: rotating salts must not silently break or
   weaken stored secrets. Losing the key means re-pairing.
3. Create tenants via `POST /platform/tenants` (needs the platform capability `wotoyi_dp_manage_platform`).
4. Cron: sync runs on WP-cron (`wotoyi_dp_minute` schedule). On low-traffic sites WP-cron only fires on page
   views, so add a system cron: `* * * * * curl -s https://site/wp-cron.php?doing_wp_cron >/dev/null`
   (or `wp cron event run --due-now`). Manual trigger: `POST /sync/run`.

Constants: `WOTOYI_DP_LOCAL_MODE` (demo mode, see below), `WOTOYI_DP_ALLOW_INSECURE_MARKETPLACE` (dev only, allows http URLs).

## Structure

```
wotoyi-dealer-platform.php   bootstrap, PHP guard, hooks
includes/Plugin.php          service locator, cron hooks
includes/Installer.php       dbDelta tables, caps, cron scheduling
includes/Schema/             field registry (vehicles, services), entity registry, DDL generator
includes/Data/               TenantContext, TenantDb (the ONLY query path), Directory (cross-tenant gateway)
includes/Auth/               Roles (matrix), Access (authorize + nonce)
includes/Service/            EntityService/SyncedEntityService + Vehicle, Service, Customer, Lead, Deal, Rental,
                             Task, Employee, Member, Tenant, Activity, AuditLog, Dashboard
includes/Sync/               Outbox, Payload, Signer, Http/Local transport, Pairing, Worker, Reconciler, LeadPuller
includes/Rest/               Routes, Handler
tests/                       runner, shim, FakeMarketplace, cases/, integration/
bin/check.sh                 lint + both suites
```

## Tenant isolation (the core design rule)

- Every tenant table has `tenant_id`. `TenantDb` is the only code that builds SQL (enforced by a static test that
  fails on `$wpdb`/raw SQL anywhere outside `Data/`, `Installer`, `uninstall.php`).
- `TenantDb` always appends `tenant_id = <current>`, rejects a caller-supplied `tenant_id`, validates table and column
  names against the schema registry, requires a WHERE for UPDATE, and allows DELETE only on the outbox.
- With no tenant context every query throws `no_tenant_context`. `TenantContext::run()` is the only way to switch.
- `Directory` is the single cross-tenant gateway (tenants list, one user's memberships, tenants with due sync work).
- Cross references (e.g. a deal's customer) are stored as uuids and checked to belong to the same tenant.
- Internal numeric ids are never exposed; the API `id` is a uuid.
- A WordPress administrator has NO access to tenant data by being admin. Access = membership of a tenant with a role.
- Tenant choice: the user's memberships; with several, send `X-Wotoyi-Tenant: <tenant uuid>`.
- A failed DB read throws instead of looking like "no rows".

## Roles

Plugin-internal roles (not WP roles): `owner`, `manager`, `sales`, `mechanic`, `accountant`. Default deny; the
matrix is in `includes/Auth/Roles.php`. Finance fields (`purchase_cost`, `sold_price`, `trade_in_value`, revenue,
margin) require `finance.read` and are removed from responses otherwise. Writes require the REST nonce
(`X-WP-Nonce`). The last owner of a tenant cannot be removed or demoted.

## Endpoints (`/wp-json/wotoyi-pro/v1`)

Lists accept `page`, `per_page`, `q`, filters and `orderby`. Updates take `version` for optimistic concurrency
(409 on a stale write). Errors are `{code, message, details?}`; 500s never leak internals.

| Area | Routes |
|---|---|
| Session | `GET /me` |
| Tenant | `GET/PUT /tenant`; platform: `GET/POST /platform/tenants` |
| Members | `GET/POST /members`, `PUT/DELETE /members/{id}` |
| Vehicles | `GET/POST /vehicles`, `GET/PUT/DELETE /vehicles/{id}`, `POST /vehicles/{id}/publish\|unpublish\|sold` |
| Services | `GET/POST /services`, `GET/PUT/DELETE /services/{id}`, `POST /services/{id}/publish\|unpublish` |
| Customers | `GET/POST /customers`, `GET/PUT/DELETE /customers/{id}`, `GET /customers/{id}/timeline`, `POST /customers/{id}/erase` |
| Leads | `GET/POST /leads`, `GET/PUT/DELETE /leads/{id}`, `GET /leads/pipeline`, `POST /leads/{id}/stage`, `POST /leads/{id}/convert` |
| Deals | `GET/POST /deals`, `GET/PUT /deals/{id}` |
| Rentals | `GET/POST /rentals`, `GET/PUT /rentals/{id}`, `GET /rentals/calendar` |
| Employees / Tasks | `GET/POST /employees`, `GET/PUT/DELETE /employees/{id}`; `GET/POST /tasks`, `GET/PUT /tasks/{id}`, `POST /tasks/{id}/complete` |
| Activities | `GET/POST /activities` (append-only) |
| Dashboard | `GET /dashboard` (real aggregates only) |
| Audit | `GET /audit` |
| Sync | `GET /sync/status`, `GET /sync/outbox`, `POST /sync/pair`, `POST /sync/pair-local`, `POST /sync/run`, `POST /sync/reconcile`, `POST /sync/requeue`, `POST /sync/disconnect` |

There is no OpenAPI file yet; this table plus `includes/Rest/Routes.php` is the contract.

## Sync protocol (client side of the marketplace receiver)

- Vehicle/service create, update, publish, unpublish, sold, delete write an outbox row in the SAME DB transaction as
  the change. Drafts and never-published records emit nothing.
- Event types: `*.published` and `*.updated` carry a snapshot; `*.unpublished`, `*.sold`, `*.deleted` carry only
  `source_id` and `version`. `source_id` is the local uuid; `version` increments on every change.
- `event_id` is deterministic (`dp-` + sha256 of tenant, type, uuid, version, event type), so retries and replays are
  idempotent on the marketplace. Older pending events for the same record are marked `superseded`.
- Only catalogue fields are sent. `vin`, plate, `purchase_cost`, `internal_notes`, supplier and `stock_ref` never leave
  the dealer platform (tested end to end against the real marketplace search).
- Worker: atomic claim with a lock token and expiry, batches of up to 50 events and 800 kB, signed with the
  documented HMAC-SHA256 scheme (verified against an independent implementation and by the real receiver).
  Retry backoff: 30 s doubling, capped at 1 h, +20% jitter; 12 attempts then `dead` (`POST /sync/requeue` revives
  `dead` and `rejected`). Per-event `rejected` results are not retried automatically (fix the data, then requeue).
  `stale` results count as done. Credential errors (`wotoyi_auth_failed`, `wotoyi_dealer_inactive`,
  `wotoyi_auth_malformed`, `wotoyi_secret_superseded`) set state `auth_failed` and stop pushing until re-pairing.
  Any other HTTP-level failure, including 4xx such as `wotoyi_https_required`, is retried.
- Pairing: dealer enters the `WTY-XXXX-XXXX-XXXX` code and the marketplace URL (HTTPS only, no credentials in URL,
  no private/loopback hosts unless the dev constant is set). The returned secret is encrypted with libsodium
  secretbox (key derived with HKDF from `WOTOYI_SECRET_KEY`) and never returned by any endpoint, log or audit row.
  Pairing attempts are rate limited.
- Nightly reconciliation uses `/sync/manifest`: missing remote -> push; remote older -> push; remote same/newer but
  different -> local version bumped to max+1, audited, pushed; deleted locally but alive remotely -> tombstone.
  Orphans on the marketplace are reported, never deleted. This closes the "no reconciliation" item in the
  marketplace README.
- Leads: pulled every 120 s per tenant, imported idempotently on `external_id` (marketplace lead uuid), then
  acknowledged. Invalid leads are acknowledged and counted so they cannot block the queue. A
  `vehicle_source_id` is linked only if it is a vehicle of the same tenant.
- Local/demo mode: with the marketplace plugin active in the same WordPress and `local_mode` enabled on both sides
  (`WOTOYI_DP_LOCAL_MODE` and `WOTOYI_MP_LOCAL_MODE`), `POST /sync/pair-local` registers the dealer through the
  marketplace PHP API and events are pushed inline after commit, with no HTTP. Not for production.

## Known limits and decisions

- Reconciliation compares `version` and `status` only (the manifest has no content hash). Content drift at an equal
  version and status is NOT detected.
- Catalogue field model: 85 writable fields vs the marketplace's 88. The 3 marketplace trust fields (managed by
  marketplace staff, not dealers) are excluded on purpose.
- WP-cron needs a system cron on quiet sites (see Install). HTTP-mode pushes happen in cron, not in the user request.
- Dashboard: ratios are `null` when the denominator is 0. Margin uses only deals whose purchase cost is known and
  reports the unknown count separately. It is N+1 (up to 1000 deals per call). Win rate approximates the time of the
  stage change with `updated_at`.
- A completed deal is final (cannot be cancelled). Rentals whose dates only touch count as overlapping.
- Transactions are re-entrant by depth counter (MySQL has no nested transactions): an inner failure that the outer
  callback catches and swallows is not rolled back separately.
- Customer erasure anonymises the customer and scrubs related leads and activities; the audit log keeps field names
  only, never values.
- Non-pretty permalinks are unsupported for sync URLs (`/wp-json/...` is assumed).
- Marketplace gap noted (M6): the local-transport entry points on the marketplace side do not authorise the caller
  beyond local mode being on. Keep local mode off in production.
- Users created through `MemberService` get the WP role `subscriber` and no WP capabilities.
- Uninstall keeps data unless `delete_on_uninstall` is set.

## Tests

```
php tests/run.php [filter]                 85 tests: schema, isolation, services, CRM, REST, dashboard, sync, static checks
php tests/integration/run.php [filter]     7 tests: both plugins, real receiver, search, reconcile, leads, local mode
WOTOYI_TEST_NO_KEY=1 php tests/scripts/no_key.php    proves the no-key refusal (also run inside SyncTest)
```

The integration suite proves: dealer adds a vehicle -> outbox -> signed push -> appears in marketplace search; edit,
unpublish/republish, sold and delete propagate; a wrong secret publishes nothing; tenant A's data is invisible and
untouchable for tenant B in both plugins; reconciliation restores data lost on the marketplace; marketplace leads
reach the CRM exactly once; local mode works without HTTP.

What the tests cannot show: real MySQL/WordPress behaviour, real TLS and network failure modes, concurrency across
real PHP processes (claims are tested sequentially against SQLite), and PHP versions other than 8.3.
