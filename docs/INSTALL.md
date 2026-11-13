# Installation guide — Phase 1 (central website + marketplace)

## 1. Requirements

- WordPress **6.5 or newer** (tested on 6.8.3), PHP **7.4+** (tested on 8.3), MySQL 5.7+/MariaDB 10.4+
- HTTPS on the production domain
- Pretty permalinks (any structure except "Plain")

No third-party plugin is required.

## 2. Install the files

Copy two folders from this repository to the server:

| From (repository) | To (WordPress) |
|---|---|
| `central/plugins/automotive-marketplace-core/` | `wp-content/plugins/automotive-marketplace-core/` |
| `central/themes/autobuzy/` | `wp-content/themes/autobuzy/` |

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
