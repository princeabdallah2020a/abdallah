# Deployment runbook

Applies to the central site (AutoBuzy Marketplace Core + AutoBuzy theme) and to each dealer site (Dealer CRM + Marketplace Connector + AutoDealer theme).

## 1. Build the release packages

```bash
tools/dev/build-release.sh            # writes dist/*.zip
```

The script syncs the design system, installs the Anthropic PHP SDK into the central plugin (`composer install --no-dev`) and zips the five packages. Upload them with **Plugins → Add new → Upload** (or deploy the folders with your usual tool).

## 2. Server requirements

| Item | Central | Dealer |
|---|---|---|
| PHP | 8.1+ with `sodium`, `curl`, `mbstring`, `json` | 8.1+ with `sodium` |
| Database | MySQL 5.7+ / MariaDB 10.4+ | same |
| HTTPS | required | required (the connector refuses a non-HTTPS marketplace) |
| Outbound HTTPS | dealer sites, `api.anthropic.com`, `graph.facebook.com` (+ Meta media hosts), your speech-to-text provider | the marketplace |
| Cron | **real cron** (§4) | real cron |
| Object cache | Redis recommended | optional |

## 3. wp-config.php

```php
// Never commit these values.
define( 'AUTOBUZY_ANTHROPIC_API_KEY', 'sk-ant-…' ); // AI assistant (or Marketplace → Settings)
define( 'AMC_WA_ACCESS_TOKEN', '…' );         // WhatsApp Cloud API (optional)
define( 'AMC_WA_APP_SECRET', '…' );
define( 'AMC_STT_API_KEY', '…' );             // Speech-to-text (optional)
define( 'AMC_VOICE_SECRET', '…' );            // Voice gateway (optional)
define( 'DISABLE_WP_CRON', true );            // with the system cron below
define( 'DISALLOW_FILE_EDIT', true );
```

**Salts:** secrets stored in the database (dealer API secrets, tokens saved from the screens, each dealer's copy of its connection secret) are encrypted with a key derived from the WordPress salts. **Changing the salts makes them unreadable**: re-enter the tokens and re-issue every dealer connection code afterwards.

## 4. Cron

WP-Cron only runs when pages are visited. Webhooks (every 5 min), the connector queue (5 min), heartbeats (hourly), WhatsApp processing, subscription expiry and data purges (daily) need a real scheduler:

```cron
*/2 * * * * cd /var/www/site && wp cron event run --due-now --quiet
```

(or `curl -s https://site/wp-cron.php?doing_wp_cron >/dev/null`). Same on every dealer site.

## 5. Web server and cache rules

```nginx
location ^~ /wp-content/uploads/amc-private/ { deny all; }
location ^~ /wp-content/uploads/adc-private/ { deny all; }
```

Page caches / CDNs must bypass: `/platform-admin/`, `/dealer-dashboard/`, `/dealer-login/`, `/dealer-registration/`, `/manager/`, every `/wp-json/` route under `sync`, `webhooks`, `ai`, `whatsapp`, `voice`, and requests carrying a WordPress login cookie. The central plugin already sends `Cache-Control: no-store` on its private API routes and `public, max-age=60` on public catalog reads.

## 6. First deployment — central

1. WordPress (site language French), HTTPS, permalinks "Post name".
2. Activate **AutoBuzy Marketplace Core**, then the **AutoBuzy** theme.
3. Marketplace → Settings: contact email, currency, AI key. Platform admin → Settings: payment details.
4. Platform admin → Plans and prices: vehicle limits and **AI quota** (WhatsApp messages per month; 0 = not included).
5. SMTP for transactional email, SPF/DKIM on the domain.
6. Run the acceptance checklists of docs/06 on a **staging copy first**.

## 7. Connecting a dealer site

1. Dealer site: activate **AutoBuzy Dealer CRM**, **AutoBuzy Marketplace Connector** and the **AutoDealer** theme (docs/INSTALL-DEALER.md).
2. Central: Platform admin → Connections → dealer → **Generate a connection code** (shown once). Send it securely.
3. Dealer CRM → Marketplace → paste → **Connect**. Vehicles move to "Up to date" within minutes.
4. Central: the dealer shows **Healthy**; its listings appear in the catalog.

Key rotation: generate a new code (the old key stops immediately), then in the CRM *Disconnect* and connect with the new code.

## 8. Updates

1. Back up database + `wp-content/uploads` (§9).
2. Deploy on staging; open Platform admin and the CRM once (migrations run automatically and are versioned: `amc_db_version`, `adc_db_version`, `acx_db_version`).
3. Deploy to production in a quiet window; check Connections (all Healthy); send one WhatsApp test if enabled.

Rollback: restore the previous plugin folder **and** the database backup of step 1 (migrations are forward-only).

## 9. Backups and restore drill

- Daily database dump + weekly files, 30 days, stored off the server, private upload folders included.
- Each dealer site has its own backups (independent sites by design).
- **Restore drill** before launch, then quarterly: restore the latest backup to staging, log into Platform admin, open a dealer and a payment receipt, compare the catalog count. Record the duration.

## 10. Monitoring

- Uptime checks: home page, `/wp-json/marketplace/v1/facets`, each dealer `/wp-json/dealer/v1/business`.
- Platform admin → **Connections** (menu badge = dealer connections in error or silent for 3 h).
- Platform admin → **AI usage**, **WhatsApp & voice** (latest messages and errors).
- PHP error log lines starting with `[AutoBuzy assistant]`.

## 11. Load test (before launch)

Example with k6 against staging:

```js
import http from 'k6/http';
export const options = { vus: 50, duration: '5m' };
export default function () {
  http.get(`${__ENV.BASE}/cars/?brand=toyota&price_max=15000000`);
  http.get(`${__ENV.BASE}/wp-json/marketplace/v1/vehicles?type=sale&page=2`);
}
```

Suggested pass criteria: p95 under 800 ms with a warm object cache; no errors except deliberate 429s (raise `api_rate_limit` for the test or disable it on staging).
