# Installation guide — Phase 1 (central website + marketplace)

## 1. Requirements

- WordPress **6.5 or newer** (tested on 6.8.3), PHP **8.1+** (tested on 8.3), MySQL 5.7+/MariaDB 10.4+
- HTTPS on the production domain
- Pretty permalinks (any structure except "Plain")

No third-party plugin is required.

## 2. Install the files

Copy two folders from this repository to the server:

| From (repository) | To (WordPress) |
|---|---|
| `central/plugins/automotive-marketplace-core/` | `wp-content/plugins/automotive-marketplace-core/` |
| `central/themes/autobuzy/` | `wp-content/themes/autobuzy/` |

**AI library (needed only for the conversational AI assistant):** on your computer or on the server, open a terminal in `central/plugins/automotive-marketplace-core/` and run `composer install --no-dev` once. It creates a `vendor/` folder (not stored in the repository); upload it with the plugin. Without it everything else works and the public assistant uses its built-in search engine.

(Or zip each folder and upload through *Plugins → Add new → Upload* and *Appearance → Themes → Add new → Upload*.)

## 3. Activate, in this order

1. **Settings → General → Site language**: choose *Français* (WordPress downloads its French pack; the plugin and theme ship their own French translation).
2. **Settings → Permalinks**: choose *Post name*, save.
3. **Plugins**: activate **Automotive Marketplace Core**. On activation it:
   - creates 5 tables (`wp_amc_dealers`, `wp_amc_vehicles`, `wp_amc_vehicle_media`, `wp_amc_inquiries`, `wp_amc_audit_log`);
   - creates the company pages (About, Services, How it works, Contact, FAQ, Become a dealer) in French, and Privacy/Terms **as drafts**;
   - registers the marketplace URLs.
4. **Appearance → Themes**: activate **AutoBuzy**.

## 4. Configure

- **Marketplace → Settings**: main city, currency, contact email, company phone / WhatsApp / address, listing freshness policy, canonical policy.
- **Appearance → Customize → Homepage**: hero title, subtitle and background photo. **Site Identity**: logo and site name.
- **Appearance → Menus** (optional): assign a menu to "Main menu"; until then a default menu is shown.
- **Pages**: edit the texts. Complete *Politique de confidentialité* and *Conditions générales* with a lawyer, then publish them — the footer links appear automatically once published.
- **Marketplace → Testimonials**: add real testimonials (the homepage section stays hidden while there are none).

## 4b. AI assistant

Two assistants are included:

| Where | What it does |
|---|---|
| Public site — floating "Demander à l’assistant" button and the bar in the homepage hero | Visitors describe what they want in French, English or Wolof; the assistant searches the live catalog and shows matching vehicles. |
| Dashboard — **Marketplace → Assistant IA**, and a "Rédiger une réponse avec l’IA" button on each contact request | Answers questions about the platform (figures, listings, dealers, requests, activity) and drafts messages. Read-only: it cannot change anything. |

Setup:

1. Create an API key at https://console.anthropic.com (billing account required — usage is paid per request).
2. Recommended: add this line to `wp-config.php`, above "That's all, stop editing":
   `define( 'AUTOBUZY_ANTHROPIC_API_KEY', 'sk-ant-PASTE-YOUR-KEY' );`
   Alternative: paste the key in **Marketplace → Réglages → Assistant IA**.
3. In the same settings section choose the model (default Claude Opus 5.5; Sonnet and Haiku cost less) and the daily request limit (default 500).

Without a key: the public assistant still works using the built-in natural-language search (it understands brand, model, city, budget, fuel, gearbox, body type, sale/rental) and the admin assistant shows a "not configured" message.

Privacy: conversations are sent to Anthropic for processing when a key is configured; the site stores no transcript (only token counts per request, for cost tracking). Mention this in your privacy policy.

## 4c. Dealer memberships (Phase 2)

Pages created automatically (no WordPress page needed):

| URL | Who | Purpose |
|---|---|---|
| `/pricing/` | public | Plans, prices, commitment discounts, comparison, FAQ |
| `/dealer-registration/` | public | 5-step application: plan → account → dealership + documents → website/domain → payment |
| `/dealer-login/` | dealers | Login |
| `/dealer-dashboard/` | dealers | Status timeline, getting started, subscription and payment, payments, website setup progress, public profile, documents, support |
| `/platform-admin/` | staff | Overview, reports, applications, dealers, subscriptions, payments, dealer websites, listings, connections, plans and prices, support, AI assistant, WhatsApp & voice, AI usage, audit log, settings |

Aliases: `/dealer-onboarding/`, `/subscription-checkout/`, `/application-status/` open the matching dealer-space section.

**First configuration (5 minutes):**

1. Open **/platform-admin/settings/** and fill in at least one payment method (Wave number, Orange Money number, bank details or cash office). Only filled methods are offered to dealers.
2. Open **/platform-admin/plans/**: check the three plans (Start 15 000, Business 25 000, Premium 40 000 FCFA/month) and the discounts for 3 / 6 / 12 months (5 / 10 / 15 % by default). Everything is editable; prices are never written in templates.
3. Make sure WordPress can send email (SMTP plugin recommended): dealers and staff are notified at every step.
4. Optional: give a colleague the role **Platform manager** (Users → Edit → Role): they get /platform-admin/ without full WordPress admin rights.

**The workflow:** application received (dealer status *Pending*) → *Start review* (*Under review*) → *Approve* (status *Approved*, a website setup checklist is created) or *Reject* with a reason → payment receipt *Confirmed* in Payments → subscription starts, dealer becomes *Active* and visible on the marketplace. A payment confirmed before approval is kept and the subscription starts at approval. Every day, the plugin sends renewal reminders 7 days and 1 day before the end, keeps the dealer online during the grace period (5 days by default), then sets the account to *Expired* (listings hidden). Staff can suspend, reactivate or cancel an account, extend or cancel a subscription, record a cash/offered subscription, and grant the "verified" badge.

**Payments are manual by design:** dealers pay by Wave, Orange Money, transfer or cash and upload the receipt; staff confirm it. There is no automatic debit. A payment gateway can be added later through the `amc_payment_providers` filter (interface `AMC\Payment_Provider`).

**Website provisioning is a checklist by design:** WordPress cannot buy domains or open hosting accounts on its own. The checklist (hosting, domain + SSL, WordPress, dealer packages, branding, admin account, API credentials, handover) is completed by staff; when every step is ticked the dealer is notified. A hosting provider with an API can be plugged in through `amc_provisioning_providers` (interface `AMC\Provisioning_Provider`).

**Documents** (RCCM, NINEA, ID, receipts) are stored in `wp-content/uploads/amc-private/` with web access denied and are served only to staff and to the owning dealer. On Nginx, add `location ^~ /wp-content/uploads/amc-private/ { deny all; }` (Apache is covered by the bundled `.htaccess`).

**Cron:** reminders and expiry run with WP-Cron once a day. On low-traffic sites, configure a real cron calling `wp-cron.php`.

## 4d. Dealer website connections (Phase 4)

**/platform-admin/connections/** lists every dealer with the health of its website connection. Open a dealer → **Generate a connection code** → copy it (shown once) and send it to the dealer, who pastes it in their CRM (Marketplace section). From then on their vehicles are published, updated and withdrawn automatically, and messages sent by buyers from marketplace vehicle pages ("Send a message to the dealer") arrive in their CRM — or by email when their website is not connected. Also on this screen: rotate or revoke a key, request a full resynchronisation, retry a webhook, and the log of API calls. Details: docs/03 and docs/DEPLOYMENT.md §7.

## 4e. WhatsApp & voice assistant (Phases 5–6, off by default)

**/platform-admin/channels/**: WhatsApp Cloud API credentials, webhook URL and verify token, voice-note transcription provider, voice gateway. Nothing is sent or received until you tick *Enable*. Set the monthly **AI quota** of each plan in Plans and prices (0 = not included). Step-by-step setup and the safety model: docs/07-whatsapp-and-voice.md.

## 5. Preview with demo data

**Marketplace → Overview → Import demo data** creates 6 fictional dealers and 34 vehicles (all labelled "Démo", with illustrated placeholder images). **Remove demo data** deletes them. Remove before launch.

Until Phase 2–4 are delivered, real dealers and vehicles can only enter through the demo importer or the internal PHP API (`AMC\Dealers::create()`, `AMC\Vehicles::upsert()`); there is deliberately no public write endpoint yet.

## 6. URLs

| URL | Page |
|---|---|
| `/` | Home (company + marketplace) |
| `/cars/` | All vehicles / search results (`?brand=toyota&price_max=…`) |
| `/cars-for-sale/`, `/cars-for-rent/`, `/featured/` | Catalog sections |
| `/car/{slug}/` | Vehicle details |
| `/dealers/`, `/dealer/{slug}/` | Directory, dealer profile |
| `/about/`, `/services/`, `/how-it-works/`, `/contact/`, `/faq/`, `/join-dealer/` | Company pages |
| `/wp-json/marketplace/v1/…` | Public read API (see docs/03) |
| `/wp-sitemap.xml` | Sitemap (includes vehicles and dealers) |

If a marketplace URL returns 404 after an update, re-save *Settings → Permalinks*.

## 7. Local test site and tests (developers)

A disposable local site running on SQLite (no MySQL needed):

```bash
git clone --depth 1 --branch 6.8.3 https://github.com/WordPress/WordPress /tmp/wp-src
git clone --depth 1 https://github.com/WordPress/sqlite-database-integration /tmp/sqlite-src
tools/dev/setup-local-site.sh /tmp/site /tmp/wp-src /tmp/sqlite-src 8088
php -S 127.0.0.1:8088 -t /tmp/site tools/dev/router.php     # admin / local-dev-password
```

Run the tests (they import then remove demo data — never run on production):

```bash
php tools/tests/run.php /tmp/site http://127.0.0.1:8088 --keep-demo   # 127 checks
node tools/tests/browser.js http://127.0.0.1:8088                      # 32 checks, needs Playwright + demo data
```

## 8. Customising templates

Copy any file from `automotive-marketplace-core/templates/` into `autobuzy/amc/` (same relative path) to override it. Design tokens live in `shared/design-system/design-system.css`; after editing run `tools/dev/sync-design-system.sh`.

## 9. Uninstall

Deleting the plugin keeps all data unless *Marketplace → Settings → Data → Delete all marketplace data* is ticked.
