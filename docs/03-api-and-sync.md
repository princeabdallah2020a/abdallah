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

## 3.2 Central dealer API — contract for Phase 4 (not implemented yet)

Authentication on every request (HTTPS only):

```
X-AMC-Key:        key_id issued to this dealer
X-AMC-Timestamp:  unix seconds (rejected if more than 300 s off)
X-AMC-Signature:  hex( HMAC-SHA256( secret, timestamp + "\n" + METHOD + "\n" + path + "\n" + sha256(body) ) )
Idempotency-Key:  UUID of the outbox event (required on writes)
```

The server resolves the dealer **from the key** — a `dealer_id` in the body is ignored. Checks in order: key exists and not revoked → signature → timestamp window → rate limit per key → dealer status `active` and subscription valid → plan limit (number of active listings) → payload validation.

| Method | Route | Purpose |
|---|---|---|
| POST | `/sync/handshake` | Connector registers site URL + version; returns plan limits and server time |
| PUT | `/sync/vehicles/{external_id}` | Create or update the listing (full public representation) |
| PATCH | `/sync/vehicles/{external_id}/status` | `active · reserved · sold · rented · unpublished` |
| DELETE | `/sync/vehicles/{external_id}` | Remove listing |
| POST | `/sync/vehicles/batch` | Full resynchronisation: list of `{external_id, payload_hash}`; server answers which to send, and unpublishes those absent |
| POST | `/sync/heartbeat` | "Still online; these external_ids are still published" → refreshes `last_synced_at` |
| GET | `/sync/status` | Subscription status, limits, listing count |

`PUT /sync/vehicles/{external_id}` body — **public fields only**:

```json
{
  "brand": "Toyota", "model": "Corolla", "year": 2019, "mileage_km": 62000,
  "fuel_type": "petrol", "transmission": "automatic", "body_type": "sedan",
  "vehicle_condition": "imported", "color": "Gris",
  "listing_type": "sale", "price_amount": 9500000, "price_period": "total", "currency": "XOF",
  "city": "Dakar", "country_code": "SN",
  "description": "…", "features": ["Climatisation", "Bluetooth"],
  "source_url": "https://dealer-a.com/vehicle/toyota-corolla-2019/",
  "status": "active",
  "media": [{ "url": "https://dealer-a.com/wp-content/uploads/…jpg", "alt": "…", "width": 1600, "height": 1067 }],
  "source_updated_at": "2026-11-13T16:00:00Z"
}
```

This is exactly the input of `AMC\Vehicles::upsert()`, which already exists and is tested (validation, enum rejection, HTML stripping, stable slug, uniqueness). Responses: `200/201 {id, url, status}` · `400` validation · `401` auth · `402` subscription inactive · `409` plan limit reached · `429` rate limit.

Media: the centre downloads each image once (hash-deduplicated), validates type/size, generates responsive sizes and serves its own copy, so the marketplace does not depend on dealer hosting speed.

## 3.3 Dealer API — contract for Phase 3–5 (`https://{dealer}/wp-json/dealer/v1`)

| Caller | Auth | Routes |
|---|---|---|
| CRM front-end (`/manager/`) | WordPress cookie + REST nonce + capability check | `/vehicles`, `/leads`, `/customers`, `/sales`, `/rentals`, `/team`, `/reports/*` |
| Central marketplace → dealer | Signed webhook (HMAC, timestamp, unique `X-AMC-Delivery` id) | `POST /webhooks/marketplace` — events: `lead.created`, `subscription.updated`, `listing.rejected`, `resync.requested`, `credentials.rotated` |
| AI service → dealer | Bearer token restricted to the `ai` scope + acting user id | `POST /ai/tools/{tool}` (read tools), `POST /ai/actions` (create pending action + preview), `POST /ai/actions/{id}/confirm` |

## 3.4 Synchronisation protocol

**Principle:** dealer CRM = source of truth; every change writes to the dealer database first, then an event is appended to `adc_outbox` *in the same transaction*; a background worker delivers events in order.

| Dealer action | Outbox event | Central effect |
|---|---|---|
| Vehicle published / edited | `vehicle.upsert` | Listing created/updated (same `external_id` ⇒ same listing) |
| Price changed | `vehicle.upsert` | Price updated |
| Reserved / sold / rented | `vehicle.status` | Status changed; sold/rented leave the results |
| Unpublished / archived / deleted | `vehicle.status` unpublished or `vehicle.delete` | Removed from active results |
| Every 6 h (cron) | `heartbeat` | `last_synced_at` refreshed for listed IDs |
| Manual "Resynchronise" button | `batch` | Full reconciliation |

Reliability:

- **Idempotency:** each event carries a UUID; the centre stores `(dealer_id, idempotency_key)` and replays return the first result.
- **Ordering:** events carry `source_updated_at`; the centre ignores an event older than the stored one.
- **Retries:** exponential backoff 1 min → 5 → 15 → 1 h → 6 h (max 24 h), then the event is parked as `failed` and shown on the connection screen; a later success for the same vehicle supersedes it.
- **Dealer offline / connector broken:** nothing arrives, so no heartbeat. After `stale_after_hours` (default 72 h) listings show "availability to confirm"; after `hide_after_hours` (default 168 h) they are hidden. **This freshness rule is already implemented and tested in Phase 1.** A vehicle can therefore never stay "available" indefinitely without confirmation.
- **Centre offline:** events wait in the dealer outbox; the dealer site and CRM are unaffected.
- **Monitoring:** `amc_connections` (last heartbeat, failure counter) drives the "API Connections" screen and alerts.
