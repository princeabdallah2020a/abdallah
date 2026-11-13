# 1. Technical architecture

## 1.1 The three systems

```
                         ┌──────────────────────────────────────────┐
   Buyers  ───────────►  │ CENTRAL SITE (one WordPress)             │
   Dealers (signup) ──►  │  theme  autobuzy                       │
   Platform staff ────►  │  plugin automotive-marketplace-core      │
                         │   catalog · dealers · plans · payments   │
                         │   REST marketplace/v1 · /platform-admin/ │
                         └───────▲──────────────────────┬───────────┘
              signed events      │                      │ signed webhooks
              (vehicles, status) │                      ▼ (leads, commands)
        ┌────────────────────────┴──────┐   ┌───────────────────────────────┐
        │ DEALER SITE A (own WordPress, │   │ DEALER SITE B … (same package)│
        │ own domain/host/database)     │   └───────────────────────────────┘
        │  theme  autodealer            │
        │  plugin automotive-dealer-crm │◄──── scoped token ────┐
        │  plugin marketplace-connector │                       │
        │  /manager/ · REST dealer/v1   │        ┌──────────────┴─────────────┐
        └───────────────────────────────┘        │ AI SERVICE (separate app)  │
                                                 │ WhatsApp · speech · SIP    │
              WhatsApp / phone ─────────────────►│ dealer identification      │
                                                 │ intent → tool → confirm    │
                                                 └────────────────────────────┘
```

Rules that follow from the "independent dealers" decision:

- **No shared database, no Multisite.** The only link between a dealer site and the centre is HTTPS + per-dealer credentials.
- **The dealer CRM is the source of truth for inventory.** The centre stores a *public copy* of approved listings, keyed by `(dealer_id, external_id)`.
- **The centre is the source of truth for** dealer account status, subscription, plan limits and marketplace visibility.
- **The centre never stores CRM data** (customers, sales, rentals, internal notes). Leads generated on the marketplace are forwarded to the dealer and kept centrally only as minimal delivery records.
- A dealer site keeps working (website + CRM) if the centre is down, and vice-versa.

## 1.2 Central WordPress components

| Component | Role | Phase |
|---|---|---|
| Theme `autobuzy` | Presentation only: header, footer, homepage, company pages, design-system styling | 1 ✔ |
| Plugin `automotive-marketplace-core` | All business logic | 1 ✔ → 4 |
| ├ `Database` | Versioned migrations (`AMC_DB_VERSION`) | 1 ✔ |
| ├ `Vehicles`, `Dealers` | Repositories; the *only* place visibility rules are enforced | 1 ✔ |
| ├ `Marketplace` | Virtual routes `/cars/`, `/car/{slug}/`, `/dealers/`…, theme-overridable templates | 1 ✔ |
| ├ `Seo`, `Sitemaps` | Titles, descriptions, canonical, robots, Open Graph, JSON-LD, sitemap providers | 1 ✔ |
| ├ `Api` | REST `marketplace/v1` — public read now; dealer sync endpoints in Phase 4 | 1 ✔ / 4 |
| ├ `Inquiries`, `Rate_Limiter`, `Audit`, `Settings`, `Content`, `Admin`, `Demo_Data` | Contact requests, throttling, audit log, options, pages/testimonials, WP-admin screens, demo importer | 1 ✔ |
| ├ `Memberships`, `Subscriptions`, `Payments` (provider interface), `Provisioning` (provider interface) | Plans, onboarding workflow, payment proof / gateways, site provisioning jobs | 2 |
| ├ `Platform_Admin` | `/platform-admin/` dashboard (separate from wp-admin) | 2 |
| └ `Sync`, `Credentials`, `Webhooks` | Event ingestion, idempotency, API keys, outbound signed webhooks, connection monitoring | 4 |

**Why custom tables for vehicles and dealers** (not custom post types): listings arrive by API in volume, are filtered on numeric ranges (price, year, mileage) and several indexed attributes, and must be upserted idempotently by `(dealer_id, external_id)`. `wp_postmeta` performs poorly for this and makes uniqueness impossible to guarantee. Public pages are still SEO-complete (clean URLs, sitemap, structured data) through the `Marketplace`/`Seo` classes. **Custom post types / pages are used where WordPress editing is the benefit**: company pages and testimonials.

## 1.3 Dealer WordPress components (Phase 3–4)

| Component | Role |
|---|---|
| Theme `autodealer` | Home, Inventory, Vehicle, About, Contact, Financing, Rentals. Branding through the Customizer (logo, colours, hero, texts). Several style presets. Uses the same design system. |
| Plugin `automotive-dealer-crm` | `/manager/` application (own login screen, not wp-admin). Modules: dashboard, vehicles, leads, customers, sales, rentals, team. Custom tables `adc_*`. REST `dealer/v1`. Roles and granular capabilities. `AI_Actions` = the only functions the AI service can call. |
| Plugin `automotive-marketplace-connector` | Stores the dealer's API credentials (encrypted), an **outbox** of events with retries, webhook receiver with signature check, connection status screen, manual resync. |

Vehicles on the dealer site: a public CPT `adc_vehicle` is **not** used either; the CRM table is the record and the theme renders `/inventory/` and `/vehicle/{slug}/` from it, exactly like the centre does. One model, one set of rules.

## 1.4 Canonical / duplicate-content strategy

The same vehicle exists on the dealer site and on the marketplace.

- Default (`canonical_policy = self`): each page is self-canonical. Pages differ substantially (marketplace adds dealer block, similar vehicles from other dealers, different title pattern "…à vendre à Dakar"). The marketplace targets generic/local queries; the dealer site targets brand-name queries.
- Optional (`canonical_policy = dealer`): marketplace vehicle pages point their canonical to the dealer page (`source_url`) — to be used if search engines treat pages as duplicates, or as a Premium-plan benefit for dealers.
- Filtered catalog URLs (`/cars/?brand=…`) are `noindex,follow` and canonicalised to the section. Sold/rented vehicle pages stay online (HTTP 200, `noindex`) with similar vehicles; unpublished ones return 404.
- Planned (Phase 6): indexable landing pages `/cars/{brand}/` and `/cars/{city}/` for local SEO expansion beyond Dakar.

## 1.5 Multilingual architecture

- All interface strings use WordPress i18n (text domains `automotive-marketplace-core`, `autobuzy`), written in English, shipped with **fr_FR** translations (`*.l10n.php`, WordPress ≥ 6.5 format). Adding English = switching site language; adding Wolof = adding a `wo` translation file.
- Stored values are language-neutral keys (`petrol`, `automatic`, `suv`…) translated at display time.
- Editorial pages are WordPress pages (French at launch). Per-language URLs (`/en/`, `/wo/`) and a language switcher are planned for the phase where English/Wolof content exists; listing descriptions stay in the language the dealer wrote them.

## 1.6 AI assistant architecture (Phase 5–6, design only)

```
WhatsApp Cloud API / SIP trunk
  → Gateway (webhook verification, media download)
  → Dealer identification (sender number ↔ authorised user of ONE dealer; unknown number = refused)
  → Speech-to-text (provider interface; Wolof quality must be measured, not assumed)
  → LLM with tool definitions (get_inventory, add_vehicle, update_vehicle_price, …)
  → Policy layer: read tools run immediately; write tools produce a PREVIEW + pending action
  → User confirms ("oui") → dealer API call with scoped token + idempotency key
  → Result message (text, optional TTS)
```

- The AI service holds, per dealer, a **revocable token limited to `AI_Actions`**. It never has WordPress admin access and never chooses the dealer: the dealer is derived from the token.
- Each action runs with the permissions of the *human* who sent the message (a Sales Agent cannot change prices through the AI if the CRM does not allow it).
- Sensitive actions (publish, price change, sale, rental confirmation, archive/delete, account changes) always require explicit confirmation of a preview; pending actions expire.
- Usage (messages, audio minutes, tokens, calls) is metered per dealer against plan quotas.
- Telephony stays disabled until provider availability in Senegal, cost and regulatory requirements (consent, recording retention) are verified.

## 1.7 Provisioning architecture (Phase 2)

`Provisioning_Provider` interface: `create_site()`, `configure_domain()`, `install_package()`, `create_admin()`, `status()`. Implementations: `Manual_Provider` (MVP — creates a checklist job that a platform administrator completes, then records credentials and runs the connection test) and later host-specific providers where the host exposes an API. WordPress itself cannot buy domains or open hosting accounts; the workflow never pretends it can.

## 1.8 Payment architecture (Phase 2)

`Payment_Provider` interface: `create_payment()`, `verify()`, `handle_webhook()`, `supports_recurring()`. MVP provider: `Manual_Proof` (Wave / Orange Money / bank transfer reference or receipt upload, verified by an administrator). Gateways are added one by one after their API has been verified for Senegal; a subscription is only called "automatic renewal" when `supports_recurring()` is true for the chosen provider — otherwise the system sends renewal reminders and expires the account after a grace period.

## 1.9 Folder structure

See the repository README. Inside the central plugin:

```
automotive-marketplace-core/
  automotive-marketplace-core.php   bootstrap, autoloader (AMC\Foo_Bar → includes/class-foo-bar.php)
  uninstall.php
  includes/                         one class per responsibility + template-functions.php
  templates/                        catalog, vehicle, dealers, dealer + parts/ (overridable from the theme's amc/ folder)
  assets/css|js/                    design-system.css (synced copy), marketplace.css, catalog.js, gallery.js
  languages/                        fr_FR translation
```
