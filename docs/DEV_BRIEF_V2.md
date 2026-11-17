# DEV_BRIEF_V2.md — Wotoyi v2 · Full Ecosystem Build

> From: CEO (Claude) · To: Tech, Product & Design departments (see CLAUDE.md) · Client: the founder.
> Read **CLAUDE.md**, **PROJECT.md (v1.2)**, **DELIVERY-NOTES.md** (v1 results) and this brief entirely before writing code. PROJECT.md wins on architecture; this brief wins on v2 scope and design.
> Founder verdict on v1: **design not satisfying.** v2 must look and feel like a **"one-million-dollar" product**: luxurious, very beautiful, very attractive, with incredible advanced animations, flawless on phone, tablet and desktop, perfect front and back end.

---

## 0. Team, models and quality gates

Create the agents of CLAUDE.md §4 in `.claude/agents/` (one file per role, with the `model:` from the routing table) plus the **Coach** (§4 of CLAUDE.md). Then:

- **Think with Fable:** CTO (architecture), Lead UX/UI (design direction), CPO (scope), CISO (security review) and **Coach** (independent review) run as subagents with `model: fable` (fallback `opus` if unavailable).
- **Execute with Sonnet:** Backend, Frontend, Design implementation, AI engineer implementation, DevOps, QA scripts, SEO content.
- **Haiku** for bulk: translation first drafts, demo data, icon variants, formatting.
- **Every milestone passes the Coach gate** before delivery: the Coach did not build it, re-runs the tests, checks the screenshots against §6 and either validates or sends back with a list of defects. Nothing reaches the founder without "COACH: VALIDATED".

---

## 1. Deliverables (WordPress packages, ready to upload)

| Package | Platform | Content |
|---|---|---|
| `wotoyi` (theme) | Marketplace | v2 "Luxe Motion" block theme — complete redesign |
| `wotoyi-marketplace` | Marketplace | Catalogue, max search, max details, dealers, accounts, featured, plans, inspection, **receiver for dealer listings and services** |
| `wotoyi-i18n` | Marketplace | FR / Wolof / EN / Arabic (RTL) — **language switcher shown on the Marketplace only** |
| `wotoyi-seo` | Marketplace + dealer sites | SEO / GEO |
| `wotoyi-builder` | All | Builder v2: advanced animation engine + Dealer mode |
| `wotoyi-ai` | All | AI Gateway + assistants (buyer, dealer, visitor) |
| `wotoyi-dealer-platform` | Dealer Platform | CRM, vehicles master, services, website control, sync engine, AI, REST API |
| `wotoyi-pro` (PWA, compiled) | Dealer Platform | Wotoyi Pro app — desktop + mobile, installable |
| `wotoyi-connector` | Dealer sites | Pairing, receives signed pushes, lead outbox, AI widget, "Gestion" page link |
| `wotoyi-dealer-*` (4 themes) | Dealer sites | 4 premium dealer templates (Prestige, Urban, Rental, Multi-brand) |
| `GUIDE-INSTALLATION-V2.pptx` | — | Simple French guide incl. InfinityFree demo mode |
| `DELIVERY-NOTES-V2.md` | — | Real test results, coach verdicts, limits |

Use the v1 zips if the founder attached them to this session (reuse backend logic, replace the design). Otherwise rebuild from the specs; v1 behaviour described in DELIVERY-NOTES.md is the minimum to match.

---

## 2. Design direction v2 — "Luxe Motion" [Lead UX/UI on Fable defines, Coach validates]

**References given by the founder (inspiration only, never copy code, assets or texts):** wintech.sn (agency site: animations, light/dark with logo swap) and car360-three.vercel.app (vehicle site: hero search, type shortcuts, featured listings). Our tools could not capture their animations; design an original system that meets or beats that level.

- **Mood:** automotive luxury showroom × modern African premium. Deep cinematic darks (ink, graphite), warm light mode (porcelain, sand), signature accent (Dakar-sunset amber/gold gradient) + secondary teal; chrome/metal highlights; glass surfaces; generous whitespace; editorial typography (display serif or high-contrast grotesk for headlines + clean sans for UI; Arabic pairing).
- **Motion system (advanced, purposeful, 60 fps):** smooth inertial scroll (with native fallback), scroll-triggered reveals and split-text headline animations, pinned storytelling sections, parallax depth layers, horizontal scroll galleries, 3D tilt + light-sheen on vehicle cards, magnetic buttons and custom cursor on desktop, morphing page/route transitions (View Transitions API), animated counters and charts, marquee of brands, hover video previews, skeleton shimmer, micro-interactions on every control, **360° vehicle viewer**, animated gradient/aurora hero background, dark/light toggle with animated transition and logo swap.
- **Rules:** lightweight animation libraries only, open-source licences compatible with GPL (record them), loaded on demand; animate `transform`/`opacity`; `prefers-reduced-motion` honoured; reduced effects on low-end devices; never delay LCP. Budgets of PROJECT.md §16.1 still apply (mobile Lighthouse performance ≥ 90 on home, search, vehicle page — v1 failed on home: fix with critical CSS and per-block assets).
- **Visual assets:** original, high-end: new logo refinement, premium vehicle renders (studio + three-quarter views, consistent lighting), icons, textures. No third-party copyrighted material.
- Deliver first a **design board + clickable animated prototype page** (HTML) of the home, search and vehicle page; Coach + CTO review before theme work.

---

## 3. Marketplace v2

### 3.1 Maximum search (hero quick search + full filter panel, URL-shareable, live result count, saved searches)
Keyword · offer type (sale / rental / both) · make · model (dependent) · version/trim · body type · price min/max · monthly price (rental) · rental duration · year min/max · mileage min/max · fuel (petrol, diesel, hybrid, plug-in hybrid, electric, LPG) · transmission · drive (FWD/RWD/4x4/AWD) · engine size range · power range (hp) · consumption max · electric range min · seats · doors · exterior colour · interior colour · upholstery · condition (new / used / imported "venant") · origin country · customs status (cleared / in transit) · first registration country · number of owners · accident-free declared · service history available · warranty · inspected by Wotoyi · verified dealer · dealer · city/area · distance from me · delivery available · financing possible · trade-in accepted · equipment (multi-select: AC, GPS, camera, sensors, sunroof, leather, cruise control, Bluetooth/CarPlay, keyless, alloy wheels, 7 seats…) · posted since · with photos/video/360° only · sort (relevance, price, year, mileage, newest, most viewed).

### 3.2 Maximum vehicle details (grouped tabs on the vehicle page, all fields optional, empty groups hidden)
**Identity:** make, model, version, year, first registration date, VIN (private), plate (private), body, colour (ext/int), condition, origin, customs status. **Engine & performance:** fuel, engine size, cylinders, power hp/kW, torque, transmission, gears, drive, top speed, 0–100. **Consumption & environment:** urban/extra-urban/combined, CO₂, Euro norm, battery capacity, electric range, charging time/ports. **Dimensions & capacity:** length, width, height, wheelbase, kerb weight, payload, towing, boot volume, tank, seats, doors. **Equipment (by category):** comfort, safety (airbags, ABS, ESP, lane assist…), multimedia, exterior, interior, lighting, driver assistance. **History & documents:** mileage, number of owners, service book, last service date, accident declaration, technical inspection date, registration papers, customs documents, warranty. **Commercial:** sale price, negotiable, rental prices (day/week/month), deposit, financing, trade-in, delivery, availability, location. **Media:** gallery, video, 360°, documents (PDF). **Trust:** verified dealer, Wotoyi inspection badge + report, views count (real).

### 3.3 AI everywhere (buyer side)
Floating assistant on every page (FR/WO/EN/AR, code-switching): natural-language search ("un SUV diesel automatique moins de 15 millions à Dakar") → filters, compare vehicles, explain specs, explain inspection, capture lead, hand off to WhatsApp. Provider-agnostic gateway (`wotoyi-ai`): API keys configured in settings (no provider assumed — D-09/D-10); **without a key, a built-in rule-based parser** still turns text into filters, and the UI says so honestly. Tool calls only through permission-checked server functions.

### 3.4 Receiver for dealer content
Endpoint + local transport that create/update/unpublish marketplace **vehicles and services** from the Dealer Platform within seconds (PROJECT.md §13.6). Services = new content type on the Marketplace (maintenance, rental with driver, import, detailing…) with their own search.

---

## 4. Dealer Platform + Wotoyi Pro + dealer sites

- **`wotoyi-dealer-platform`** (PROJECT.md §6, §11.3): tenant-isolated custom tables; CRM (customers, leads from all sources, timeline, pipeline drag-and-drop, tasks/reminders, follow-ups), vehicles master (all §3.2 fields), **services**, rentals + calendar, reservations, sales, employees + roles, reports + charts, Excel export, audit log, REST API v1 (OpenAPI file), pairing codes, sync engine with signed HMAC requests, retries, reconciliation.
- **AI for dealers:** assistant in Wotoyi Pro — summarise conversations, draft vehicle descriptions in 4 languages, analyse sales, suggest follow-ups, search the CRM, perform actions **only after confirmation**, strictly limited to the dealer's own data (isolation tests mandatory).
- **`wotoyi-pro` PWA** (React + Vite, compiled into the plugin): premium dark/light app UI with the same motion quality; mobile bottom nav, tablet two-pane, desktop sidebar; dashboards; **Website** section to change everything on the dealer site without code — logo, slogan, colours, fonts (curated), sections (builder Dealer mode), pages, contact, WhatsApp, team, services, SEO — with live preview per device and one-click publish/rollback.
- **Dealer "management page" on the dealer's own site:** `dealer-site.tld/gestion` opens Wotoyi Pro for that dealer (secure login/SSO). The dealer never sees wp-admin.
- **`wotoyi-connector`** + **4 dealer themes**: same luxury level as the marketplace, each a distinct personality; AI visitor assistant (vehicle Q&A, lead capture, appointments, WhatsApp); site works when central systems are down (local copy + lead outbox).
- **Languages:** dealer sites and Wotoyi Pro have **no public language switcher** (dealer chooses the site language in settings); the multilingual switcher appears **only on the Marketplace**.
- **Automatic publication:** a vehicle or service added in Wotoyi Pro appears on the dealer site **and** on the Marketplace automatically (target ≤ 60 s; instant in demo mode).

---

## 5. InfinityFree demo mode (mandatory)

InfinityFree blocks server-to-server API calls. Provide a **"single-install demo mode"**: Marketplace + Dealer Platform + one dealer site (as a sub-site rendered by the dealer theme under `/concessionnaire-demo/` or a subdomain mapping) on **one** WordPress, using a **local transport** for sync (same code path, no HTTP). The founder can then test the whole flow (add a vehicle in Wotoyi Pro → appears on dealer site and marketplace) on free hosting. Production uses separate installs + HTTP transport. Document both in the guide.

---

## 6. QA matrix and Definition of Done (Coach checks every item)

- Real WordPress (latest stable) fresh installs: (a) separate installs with HTTP transport, (b) single-install demo mode.
- `php -l` all files, empty `debug.log`, 0 JS console errors.
- Screenshots: marketplace 4 languages × light/dark × 390/820/1440 px; dealer themes and Wotoyi Pro × light/dark × 3 sizes.
- Lighthouse mobile: performance ≥ 90, accessibility ≥ 95, SEO 100, best practices 100 on marketplace home/search/vehicle and one dealer template.
- Animations: 60 fps trace on desktop and throttled mobile; reduced-motion verified.
- Security: CISO review of auth, nonces, capabilities, HMAC signing, tenant isolation (dealer A cannot read dealer B — automated tests), AI tool authorisation.
- E2E: add vehicle + service in Wotoyi Pro → dealer site + marketplace; lead from dealer site → CRM; AI NL search → correct filters (with and without API key).
- No invented prices/figures/reviews; demo labelled "Démo"; no « car » in French copy; no forbidden inspection wording.

## 7. Delivery protocol (do not stop until done)

Work milestone by milestone and **send the founder the zips at each validated milestone** so nothing is lost:
- **M1** Design board + animated prototype → **M2** Marketplace v2 (theme + plugins) → **M3** Dealer Platform + Wotoyi Pro + AI → **M4** Connector + 4 dealer themes + sync + demo mode → **M5** Full QA, Coach final verdict, guide PPTX, DELIVERY-NOTES-V2.md.
- Save DELIVERY-NOTES-V2.md to the claude.ai Project (`claude/DELIVERY-NOTES-V2.md`) after each milestone.
- If the session reaches its limits, deliver every validated milestone with honest notes on what remains, in order.
- No questions to the founder: take the best decision per PROJECT.md, record it in the notes, continue.
