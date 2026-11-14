# 3. API contracts and synchronisation protocol

## 3.1 Central public API — implemented (Phase 1)

Base: `https://{marketplace}/wp-json/marketplace/v1` · read-only · no authentication · rate limited per IP (default 120 req/min, HTTP 429 beyond).

| Method | Route | Parameters | Returns |
|---|---|---|---|
| GET | `/vehicles` | `type` sale\|rent, `brand`, `model`, `city`, `dealer` (slugs), `fuel`, `transmission`, `body`, `condition`, `availability` available\|reserved, `price_min`, `price_max`, `year_min`, `year_max`, `mileage_max`, `featured`, `q`, `sort` latest\|price_asc\|price_desc\|mileage_asc\|featured, `page`, `per_page` (≤ 48) | `{ items[], total, pages, page, per_page }` |
| GET | `/vehicles/{id}` | | listing + `description`, `features[]`, `media[]`, `dealer{}` |
| GET | `/facets` | | brands (with models), cities, dealers, year range, vocabularies |
| GET | `/dealers` | `city`, `q`, `page`, `per_page` | `{ items[], total, page }` |
| GET | `/catalog` | same filters + `preset`, `pg`, `dealer_page` | `{ html, total, url }` — server-rendered results used by the catalog page |

Example:

```bash
curl "https://example.com/wp-json/marketplace/v1/vehicles?type=sale&brand=toyota&price_max=15000000&sort=price_asc"
```

```json
{
  "items": [{
    "id": 12, "slug": "toyota-corolla-2019-dakar-cb9f",
    "url": "https://example.com/car/toyota-corolla-2019-dakar-cb9f/",
    "title": "Toyota Corolla 2019", "brand": "Toyota", "model": "Corolla",
    "year": 2019, "mileage_km": 62000, "fuel_type": "petrol", "transmission": "automatic",
    "body_type": "sedan", "condition": "imported", "color": "Gris", "listing_type": "sale",
    "price": { "amount": 9500000, "currency": "XOF", "period": "total", "formatted": "9 500 000 FCFA" },
    "city": "Dakar", "country_code": "SN", "status": "active",
    "is_featured": true, "is_stale": false, "thumbnail_url": "https://…",
    "dealer": { "name": "…", "slug": "…", "verified": true, "url": "https://example.com/dealer/…/" },
    "published_at": "2026-11-13T16:00:00"
  }],
  "total": 1, "pages": 1, "page": 1, "per_page": 12
}
```

Responses are built from explicit allow-lists (`Api::vehicle_summary()`, `Api::dealer_public()`): a new database column is never exposed by accident.

## 3.2 Central dealer sync API — implemented (Phase 4, not tested)

Base: `https://{marketplace}/wp-json/marketplace/v1/sync` · code: `AMC\Sync_Api`.

Authentication on every request (HTTPS):

```
X-AMC-Key:        key id (ab_…) issued to this dealer
X-AMC-Timestamp:  unix seconds (rejected beyond ±300 s)
X-AMC-Signature:  hex( HMAC-SHA256( secret, timestamp + "\n" + METHOD + "\n" + route + "\n" + sha256(body) ) )
Idempotency-Key:  required on every write; a replay returns the first response (header Idempotent-Replay: true)
```

`route` is the REST route, e.g. `/marketplace/v1/sync/vehicles/9b2c…`. The dealer is resolved **from the key**: any `dealer_id` in a body is ignored; `is_featured`, `is_demo`, `published_at` sent by a dealer are dropped. Checks in order: key exists and active → signature → timestamp → 600 requests/minute per key → idempotency replay → dealer `active` (else **402**) → plan limit of active listings (else **409**) → payload validation (`AMC\Vehicles::upsert`).

| Method | Route | Purpose |
|---|---|---|
| POST | `/sync/handshake` | Registers the website URL and connector version; returns account, subscription, limits, server time |
| GET | `/sync/status` | Same information |
| PUT | `/sync/vehicles/{external_id}` | Create/update the listing (public fields only, see below). `200` update, `201` create, `{ "ignored": "stale" }` if an older version arrives after a newer one |
| PATCH | `/sync/vehicles/{external_id}/status` | `active · reserved · sold · rented · unpublished` |
| DELETE | `/sync/vehicles/{external_id}` | Remove the listing and its media |
| POST | `/sync/heartbeat` | `{ vehicles: [{external_id, updated_at}] }` = everything the dealer wants online. Listings not in the list are unpublished; the answer lists `missing` ids the connector must send again |
| POST | `/sync/contacts` | WhatsApp numbers of team members allowed to use the assistant (Phase 5) |

`PUT` body (built by the connector from the CRM; never purchase price, VIN, notes or customers):

```json
{
  "title": "Toyota Corolla 2019", "brand": "Toyota", "model": "Corolla", "year": 2019, "mileage_km": 62000,
  "fuel_type": "petrol", "transmission": "automatic", "body_type": "sedan", "vehicle_condition": "imported",
  "color": "Gris", "listing_type": "sale", "price_amount": 9500000, "price_period": "total", "currency": "XOF",
  "city": "Dakar", "country_code": "SN", "description": "…", "features": ["Climatisation"],
  "thumbnail_url": "https://dealer-a.com/…jpg",
  "media": [{ "url": "https://dealer-a.com/…jpg", "alt": "…", "width": 1600, "height": 1067 }],
  "source_url": "https://dealer-a.com/vehicle/toyota-corolla-2019/",
  "status": "active", "source_updated_at": "2026-11-13T16:00:00+00:00"
}
```

Media are referenced on the dealer website (not copied). Copying/resizing images centrally remains a possible improvement.

**Credentials** (`AMC\Credentials`): staff click *Generate a connection code* (Platform admin → Connections → dealer). The code `AB1.<base64url JSON {v,url,key,secret}>` is shown **once**; the secret is stored encrypted (libsodium, key derived from the WordPress salts). Rotating revokes the previous key immediately. Health: *Healthy* (success < 3 h), *No recent contact*, *Errors* (3 failures in a row).

## 3.3 Marketplace → dealer — implemented (Phases 4–5, not tested)

Signed with the same secret and scheme (route `/dealer/v1/…`), sent by `wp_safe_remote_post` (no private/internal addresses).

| Route on the dealer site | Sent by | Purpose |
|---|---|---|
| `POST /wp-json/dealer/v1/webhooks/marketplace` | `AMC\Webhooks` (queue + WP-Cron every 5 min, backoff 1 min → 5 → 15 → 1 h → 3 h → 6 h → 12 h → 24 h, then *failed*) | Events `lead.created` (message from a marketplace vehicle page → CRM lead, source *Marketplace*), `resync.requested`, `dealer.updated` (status / plan changed). Header `X-AMC-Delivery` = unique id; the dealer stores it and ignores replays |
| `POST /wp-json/dealer/v1/ai/execute` | `AMC\Dealer_Agent` | AI assistant tools, run as the CRM user linked to the WhatsApp number (see docs/07) |

If a dealer website is not connected, or a `lead.created` delivery finally fails, the request is emailed to the dealer instead — a buyer's message is never lost.

## 3.4 Synchronisation protocol — implemented in the Marketplace Connector

Plugin **AutoBuzy Marketplace Connector** (`dealer/plugins/automotive-marketplace-connector`, namespace `ACX`), installed next to the Dealer CRM.

- **Trigger:** every CRM change fires `adc_vehicle_changed`; the connector marks the vehicle *pending* in `acx_items` (one row per vehicle — rapid edits are coalesced, the payload is built when sent, so only the latest state travels).
- **Wanted on the marketplace** = "Also publish on the AutoBuzy marketplace" ticked **and** status published or reserved. Sold/rented/unpublished vehicles already online are updated (status) rather than silently left.
- **Delivery:** right after the change (single cron event) and every 5 minutes; batch of 20; lock against parallel runs; backoff 1 min → 5 → 15 → 1 h → 3 h → 6 h. Errors that a retry cannot fix (400, 402 inactive account, 409 plan limit) stop immediately and appear in the CRM *Marketplace* screen with the reason. When the account becomes active again (`dealer.updated`), refused vehicles are retried automatically.
- **Idempotency:** the key is derived from (vehicle, operation, last change), so a retry after a timeout is never applied twice.
- **Ordering:** `source_updated_at` — the marketplace ignores an older version.
- **Self-healing:** hourly heartbeat with every wanted vehicle; the marketplace unpublishes the others and returns the ones it is missing, which are queued again.
- **Dealer offline / connector broken:** no heartbeat → after `stale_after_hours` (72 h) listings show "availability to confirm", after `hide_after_hours` (168 h) they are hidden (Phase 1 freshness rule).
- **Marketplace offline:** changes wait in `acx_items`; the dealer website and CRM are unaffected.
