# Dealer website + CRM — installation (Phase 3)

One installation per dealership, on its **own** domain and hosting (not a Multisite).

## 1. Requirements

WordPress 6.5+, PHP 8.1+, MySQL 5.7+/MariaDB 10.4+, HTTPS, pretty permalinks. No other plugin needed.

## 2. Install

1. Install WordPress on the dealer's domain (Settings → General → Site language: Français).
2. Plugins → Add new → Upload `autobuzy-dealer-crm-0.3.0.zip` → Activate.
3. Appearance → Themes → Add new → Upload `autodealer-theme-0.3.0.zip` → Activate.
4. Settings → Permalinks → "Post name" → Save.
5. Users → Add new → create the dealer's account with the role **Dealer owner** (strong password).
6. Send the dealer the address **`https://their-domain/manager/login/`**.

Your own administrator account keeps full access ("Platform support" in the CRM).

## 3. What the dealer does next (no coding)

In **/manager/ → Settings**: business name, phone, WhatsApp, address, hours, map link, logo, homepage photo and texts, "About us" text, optional Rentals and Financing pages, and the design (4 ready-made colour schemes — Classic, Bold, Elegant, Ocean — or custom colours). The website updates immediately.

Then: **Vehicles → Add a vehicle** (photos uploaded several at a time), **Team → Invite someone**.

## 4. Website pages (automatic)

`/` home · `/inventory/` · `/rentals/` · `/vehicle/{slug}/` · `/about/` · `/contact/` · `/financing/` — with SEO titles, descriptions, canonical URLs, Open Graph, schema.org (AutoDealer, Car/Offer) and the XML sitemap (`/wp-sitemap.xml`). Contact and vehicle forms create leads in the CRM and email the dealer.

## 5. CRM (`/manager/`)

| Section | Content |
|---|---|
| Dashboard | Stock, available/reserved/rented, new leads, sales and revenue this month, rental revenue, follow-ups due, upcoming appointments, pipeline, 6-month sales chart, recent activity |
| Vehicles | Statuses Draft → Pending review → Published → Reserved / Sold / Rented → Archived; photos; private VIN and purchase cost; "publish on the marketplace" flag (used in Phase 4) |
| Leads | Pipeline board New → Contacted → Interested → Appointment → Negotiation → Won / Lost; sources (website, marketplace, WhatsApp, phone, showroom, ads, referral); assignment; follow-up dates; history; appointments; convert to customer |
| Customers | Record, purchases, rentals, private documents (ID, licence, contracts) |
| Sales | Confirming a sale marks the vehicle sold and the lead won; cancelling puts the vehicle back on sale; payment follow-up |
| Rentals | 14-day availability planner; quote → confirmed → vehicle out → returned; **overlapping confirmed rentals are refused** (checked in a locked transaction); live total; deposit, payment, mileage, return condition |
| Team | Invitations by email (7-day single-use link); roles Owner, Manager, Sales agent, Rental agent, Viewer; extra permissions per person |
| Settings, Activity | Business and website settings; activity log |

### Permissions

| Permission | Owner | Manager | Sales | Rental | Viewer |
|---|:-:|:-:|:-:|:-:|:-:|
| Add / edit vehicles | ✔ | ✔ | ✔ (goes to review) | | |
| Publish / archive, change published prices | ✔ | ✔ | | | |
| Leads (all) / own and unassigned | all | all | own | own | read all |
| Customers | ✔ | ✔ | ✔ | ✔ | |
| Sales | ✔ | ✔ | ✔ | | |
| Rentals | ✔ | ✔ | | ✔ | |
| Revenue and statistics | ✔ | ✔ | | | ✔ |
| Settings | ✔ | ✔ | | | |
| Team | ✔ | | | | |

Every action is checked on the server. Team members never see wp-admin. The last owner cannot be removed.

## 6. Data and security notes

- Customer documents are stored in `wp-content/uploads/adc-private/` (web access denied by `.htaccess`; on Nginx add `location ^~ /wp-content/uploads/adc-private/ { deny all; }`) and served only to team members with customer, sales or rental rights.
- Vehicle photos go into the normal media library (they are public on the website).
- Deleting the plugin removes the roles but **never deletes business data**.
- Public API (read-only, no private field): `/wp-json/dealer/v1/vehicles`, `/wp-json/dealer/v1/business`.
