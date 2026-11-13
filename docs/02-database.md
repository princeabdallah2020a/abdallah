# 2. Database schemas

Conventions: InnoDB, `utf8mb4`; all dates UTC `DATETIME`; money as integer in the currency's minor unit + ISO currency code (XOF has no minor unit); enumerations as `VARCHAR` validated in PHP (portable, migration-free); every table prefixed `{wp_prefix}amc_` (central) or `{wp_prefix}adc_` (dealer). Schema changes = a new numbered migration in `class-database.php`, never an edit of a shipped one.

## 2.1 Central — implemented (migration 1)

### `amc_dealers`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT NULL | WordPress user of the dealer owner (Phase 2) — **private** |
| slug | VARCHAR(191) UNIQUE | `/dealer/{slug}/` |
| business_name, logo_url, description, address, city, city_slug, country_code | | public profile |
| phone, whatsapp, website_url | | public contact |
| contact_email | VARCHAR(191) | **private**, never selected by public queries |
| is_verified | TINYINT | "Verified dealer" badge |
| status | VARCHAR(20) | pending · under_review · approved · active · suspended · expired · cancelled. Only `active` is public |
| sync_mode | VARCHAR(10) | `api` (freshness policy applies) · `manual` |
| is_demo | TINYINT | demo flag |
| created_at, updated_at | DATETIME | |

### `amc_vehicles` — public copy of approved listings
| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| dealer_id | BIGINT | → amc_dealers.id |
| external_id | VARCHAR(64) | vehicle ID in the dealer CRM. **UNIQUE (dealer_id, external_id)** prevents duplicates |
| slug | VARCHAR(191) UNIQUE | permanent once created |
| title, brand, brand_slug, model, model_slug | | |
| year, mileage_km | | indexed |
| fuel_type, transmission, body_type, vehicle_condition, color | | vocabulary keys |
| listing_type | VARCHAR(10) | sale · rent |
| price_amount, price_period, currency | | period: total · hour · day · month |
| city, city_slug, country_code | | |
| description, features (JSON array) | LONGTEXT | plain text only |
| thumbnail_url, source_url | | source_url = page on the dealer site |
| status | VARCHAR(20) | active · reserved · sold · rented · unpublished |
| is_featured, featured_until | | |
| source_updated_at, last_synced_at | DATETIME | freshness policy |
| published_at, created_at, updated_at | DATETIME | |

Indexes: `(status, listing_type, published_at)`, `(brand_slug, model_slug)`, `price_amount`, `year`, `mileage_km`, `city_slug`, `is_featured`.

### `amc_vehicle_media`
`id, vehicle_id, url, alt, width, height, position, source_hash, created_at` — index `(vehicle_id, position)`.

### `amc_inquiries`
`id, type (contact|dealer_interest), name, email, phone, company, subject, message, ip_hash, status (new|handled), created_at`.

### `amc_audit_log`
`id, actor_user_id, action, object_type, object_id, context (JSON), ip_hash, created_at` — append-only.

## 2.2 Central — planned

**Phase 2**

| Table | Main columns |
|---|---|
| `amc_plans` | id, code (start/business/premium), name, price_amount, currency, vehicle_limit, ai_quota, features (JSON), visibility_boost, is_active, sort — **seeded with 15 000 / 25 000 / 40 000 FCFA, editable in the dashboard; templates read from here** |
| `amc_plan_durations` | id, months (1/3/6/12), discount_percent |
| `amc_applications` | id, dealer_id, plan_id, duration_months, company data, verification documents (attachment IDs), website_option, domain_choice, status, reviewed_by, reviewed_at, rejection_reason |
| `amc_subscriptions` | id, dealer_id, plan_id, status, starts_at, ends_at, grace_ends_at, renewed_from_id |
| `amc_payments` | id, dealer_id, subscription_id, provider, method, amount, currency, reference, proof_attachment_id, status (pending/verified/rejected/refunded), verified_by, provider_payload |
| `amc_provisioning_jobs` | id, dealer_id, provider, domain, steps (JSON checklist + state), status, site_url, error |
| `amc_support_tickets`, `amc_support_messages` | support module |

**Phase 4**

| Table | Main columns |
|---|---|
| `amc_api_credentials` | id, dealer_id, key_id (public), secret_hash, webhook_secret_enc, scopes, last_used_at, revoked_at, rotated_from_id |
| `amc_sync_events` | id, dealer_id, idempotency_key **UNIQUE(dealer_id, idempotency_key)**, event_type, payload_hash, status, error, received_at, processed_at |
| `amc_webhook_deliveries` | id, dealer_id, event_type, payload, attempts, next_attempt_at, status, last_error |
| `amc_connections` | dealer_id, site_url, connector_version, last_heartbeat_at, last_success_at, consecutive_failures, status |
| `amc_leads` | id, dealer_id, vehicle_id, minimal contact data, delivery_status, delivered_at, purge_after |

**Phase 5–6**: `amc_ai_usage` (dealer_id, period, messages, audio_seconds, tokens, cost), `amc_whatsapp_connections`, `amc_call_usage`.

## 2.3 Dealer site — planned (Phase 3), tables `adc_*`

| Table | Main columns |
|---|---|
| `adc_vehicles` | id, public_id (UUID = `external_id` sent to the centre), slug, brand, model, year, mileage_km, fuel_type, transmission, body_type, vehicle_condition, color, vin (private), listing_type, price_amount, price_period, currency, purchase_cost (private), description, features, internal_notes (private), status (draft · pending_review · published · reserved · sold · rented · archived), publish_to_marketplace, created_by, timestamps |
| `adc_vehicle_media` | id, vehicle_id, attachment_id, position |
| `adc_leads` | id, name, phone, email, vehicle_id, budget_amount, source (website · marketplace · whatsapp · phone · form · manual · ads), assigned_to, status (new · contacted · interested · appointment · negotiation · won · lost), follow_up_at, lost_reason |
| `adc_lead_notes` | id, lead_id, author_id, note, created_at |
| `adc_customers` | id, type, name, phone, email, address, id_document_ref, notes |
| `adc_customer_documents` | id, customer_id, attachment_id (stored outside the public uploads path), label |
| `adc_sales` | id, vehicle_id, customer_id, salesperson_id, sale_price, currency, sold_at, payment_method, payment_status, status (draft · confirmed · cancelled) — confirming sets the vehicle to `sold` in the same transaction |
| `adc_rentals` | id, vehicle_id, customer_id, agent_id, starts_at, ends_at, rate_amount, rate_period, total_amount, deposit_amount, payment_status, status (draft · confirmed · active · returned · cancelled), return_condition — overlap check on confirmed/active rentals inside a transaction with row lock |
| `adc_appointments` | id, lead_id, customer_id, vehicle_id, user_id, starts_at, status |
| `adc_team_invites` | id, email, role, token_hash, expires_at, accepted_at |
| `adc_outbox` | id, event_type, entity_id, idempotency_key, payload, attempts, next_attempt_at, status, last_error (connector retry queue) |
| `adc_inbox` | id, delivery_id UNIQUE, event_type, payload, processed_at (webhook de-duplication) |
| `adc_ai_pending_actions` | id, user_id, tool, arguments, preview, expires_at, confirmed_at |
| `adc_audit_log` | same shape as central |

Roles (WordPress roles with custom capabilities such as `adc_manage_vehicles`, `adc_edit_prices`, `adc_view_reports`, `adc_manage_team`): Dealer Owner, Dealer Manager, Sales Agent, Rental Agent, Viewer.
