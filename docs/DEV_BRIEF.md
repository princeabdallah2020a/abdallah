# DEV_BRIEF.md — Wotoyi Marketplace · Engineering Brief v1.0

> From: Project Manager / Lead Architect (Claude) · To: Engineering team (Claude Code)
> Client: the project owner — very demanding. Reference: **PROJECT.md v1.0** (read it fully first; it wins over this brief if they disagree).
> Language of deliverables: code and code comments in English; user-facing texts in French (source), Wolof, English, Arabic; guides in French.

---

## 0. Mission

Build the **Wotoyi Marketplace** (Platform One) as installable WordPress packages, with the **full scope of PROJECT.md §29**, every recommendation of PROJECT.md applied directly, tested on a real WordPress, ready to upload by the client on free cPanel hosting (InfinityFree) for a demo.

**Deliverables:**
1. `wotoyi` — block theme (FSE).
2. `wotoyi-marketplace` — vehicles, dealers, search, accounts, leads, featured listings, plans, inspection.
3. `wotoyi-builder` — builder v1 (animations, responsive, light/dark visibility, hover effects, section library, Design Studio).
4. `wotoyi-i18n` — FR / Wolof / EN / Arabic (RTL), per-language URLs, `hreflang`, translation UI.
5. `wotoyi-seo` — meta templates, structured data, sitemaps, social cards, redirects, AI crawler controls.
6. Original brand assets (logo, favicon, vehicle studio images, icons) — created by you, no third-party copyrighted material.
7. `GUIDE-INSTALLATION.pptx` — simple French installation guide (InfinityFree cPanel + WordPress), with screenshots of the result.
8. `DELIVERY-NOTES.md` — what was built, tests run and results, known limitations, items waiting for client decisions.

---

## 1. Non-negotiable rules

- **Never invent** prices, partner names, APIs, statistics, reviews or test results. Demo content is clearly labelled "Démo" and removable in one click.
- **Security (PROJECT.md §14.3):** capabilities, nonces, sanitize input, escape output, `$wpdb->prepare`, REST `permission_callback`, rate limiting + honeypot on public forms.
- **No third-party premium theme/builder.** Only proprietary code or open-source libraries with compatible licences (list them in DELIVERY-NOTES).
- **Performance budgets (§16.1):** Core Web Vitals "good" targets; only load CSS/JS of blocks used; images WebP/AVIF; animations on `transform`/`opacity` only; `prefers-reduced-motion` respected.
- **Light and dark modes (§16.9)** on every screen, contrast validated by script (WCAG AA). No flash of wrong mode.
- **RTL (§9.5):** logical CSS properties only; every screen tested in Arabic.
- **French copy:** « auto » / « voiture », never « car ».
- **Inspection wording (§10.6):** never "certified mileage", "guaranteed accident-free", "100% verified".
- **InfinityFree constraints:** no server-to-server API dependency for the marketplace to work; no reliance on real cron or PHP `mail()` (leads always stored in admin; email is a bonus). Keep each zip under the host's upload limit or document the cPanel File Manager method.
- **WordPress compatibility:** latest stable WordPress (7.1.x) and PHP 8.1+; no deprecated APIs.

---

## 2. Way of working

- Work in **vertical slices**, one workstream at a time (§4), each finished with tests and screenshots before the next.
- Keep a `CLAUDE.md` in the repo with these rules and the coding standards (PROJECT.md §25.4).
- Use git; one commit per meaningful step.
- After each workstream: run the **QA checklist (§5)**, fix everything, then update DELIVERY-NOTES.md.
- When a requirement is ambiguous: choose the option PROJECT.md recommends, record the choice in DELIVERY-NOTES.md, continue. Stop only for irreversible decisions.

---

## 3. Test environment (set up first)

- Real WordPress (latest stable from the official GitHub mirror) + SQLite integration plugin or MySQL, PHP built-in server or Docker, WP-CLI.
- Headless Chrome/Playwright for screenshots and functional tests.
- Matrix for screenshots: **4 languages × light/dark × phone (390 px) / tablet (820 px) / desktop (1440 px)** for: home, search results, vehicle page, dealer page, dealers list, inspection page, account pages, favorites, 404.
- `WP_DEBUG` + `WP_DEBUG_LOG` on: the log must be empty after the full test run.

---

## 4. Workstreams and prompts

Each prompt below is self-contained; run them in order.

### W1 — Brand & design system
> Create the Wotoyi visual identity following PROJECT.md §16.12 (Teranga): an original logo (symbol + wordmark converted to outlines, light and dark versions, SVG + PNG), favicon/app icons, a vehicle illustration set rendered as realistic "studio" images (at least 12 vehicles: SUV, sedan, pickup, city car, minibus — sale and rental), and an icon set (categories, specs, trust, inspection). Produce design tokens (light + dark + RTL-safe) as JSON and validate every text/background pair with a contrast script (WCAG AA). Add an Arabic open-licence font paired with Sora/Manrope. Deliver a one-page brand board (HTML) showing all of it.
> **Done when:** contrast script passes 100 %, assets have no third-party material, brand board screenshot approved by PM.

### W2 — Theme `wotoyi`
> Build the block theme: `theme.json` (tokens, fluid type, spacing, shadows, light palette; dark palette via CSS variables), templates (home, vehicle archive/search, vehicle single, dealer single/archive, taxonomy archives, page, landing page without title, account pages, 404, search), template parts (header with sticky glass effect, language switcher FR/WO/EN/AR, light/dark toggle, account and favorites icons; footer), and a library of premium patterns so that **every page is full** (hero with search, categories, featured vehicles, inspection, how it works, Wolof/Arabic AI teaser, dealers, trust, FAQ, CTA WhatsApp, plans). Motion system per §16.4. No-flash dark mode script. RTL via logical properties.
> **Done when:** all templates render complete pages with demo content in the 4 languages, light/dark, 3 sizes; Lighthouse mobile ≥ 90 performance on home and vehicle page.

### W3 — `wotoyi-i18n` (FR / Wolof / EN / Arabic)
> Build the multilingual layer: language-region URL folders (`/fr-sn/`, `/wo-sn/`, `/en-sn/`, `/ar-sn/`), per-language content for vehicles, dealers, pages and taxonomies (linked translations), UI string translation screen in admin (no code), `hreflang` + `x-default`, `dir="rtl"` for Arabic, language switcher block, fallback chain (wo→fr, ar→fr, en→fr). Provide all UI strings in the 4 languages; mark Wolof and Arabic strings "to be reviewed by native speaker" in DELIVERY-NOTES.
> **Done when:** switching language keeps the user on the equivalent page; Arabic layout is fully mirrored; no untranslated string on any tested screen.

### W4 — `wotoyi-marketplace` core
> Vehicles (custom post type, custom fields per PROJECT.md §6.5: sale and rental, full specs, gallery, video link, statuses, featured flag), taxonomies (make → model hierarchy, body type, fuel, transmission, condition, city), dealers (profile, logo, verified badge, contact, WhatsApp, website, hours, map link), admin screens that are simple and in plain language. Advanced search & filters (server-side, URL-shareable, sort by date/price/mileage/year), result count, pagination, empty states designed. Vehicle page: gallery with zoom/swipe, key specs, full specs, price (or "Prix sur demande" if enabled), dealer card, WhatsApp/call/contact form, "Request inspection" CTA, similar vehicles, share card for WhatsApp. Dynamic blocks with server-side render for all of this (usable in the builder). Leads stored in admin with source, status, CSV/Excel export.
> **Done when:** functional tests pass for search combinations, contact form (nonce, honeypot, rate limit), lead storage; sold vehicles handled per D-35 default.

### W5 — Accounts, favorites, saved searches, recently viewed
> Buyer sign-up/login (email + password; phone OTP prepared as extension point), account page (favorites, saved searches, inspection orders, profile, language), favorites for guests (localStorage) merged into the account at login, recently viewed vehicles.
> **Done when:** guest → login merge works; all account pages are full, translated, light/dark, RTL.

### W6 — Dealers: profiles, registration request, onboarding
> Public dealer directory and profiles; "Become a partner dealer" request form; admin onboarding checklist (documents received, verified, plan chosen, payment recorded, active); dealer listings hidden automatically when subscription inactive after grace period (configurable).
> **Done when:** the full flow request → verification → active → listings visible is testable in admin.

### W7 — Monetization
> Plans and prices fully configurable (no default prices — empty means "Sur devis"); featured listings with duration and placement, labelled "Sponsorisé"; manual payment recording with invoice numbering; payment-gateway extension point (interface + settings screen) ready for the provider chosen later (D-07b).
> **Done when:** admin can create a plan, record a payment, feature a vehicle, and see it expire automatically (WP-Cron fallback documented for InfinityFree).

### W8 — Inspection service (PROJECT.md §10)
> Configurable packages and modules (admin), request flow from any vehicle page (package, contact, preferred slots, consent), order states (§10.3), inspector role and mobile checklist with photo upload, admin QA review, branded multilingual PDF report with mandatory disclaimer and integrity hash, buyer access from the account. Payment: manual until D-07b.
> **Done when:** an order goes end-to-end in tests and the PDF renders correctly in the 4 languages including Arabic RTL; automated check that forbidden wording never appears.

### W9 — `wotoyi-seo` (§26)
> Meta templates per type and language with per-page override, structured data (Car/Vehicle + Offer, AutoDealer, BreadcrumbList, FAQPage, Organization), XML sitemaps per language, social card image generator, canonical rules, faceted-search noindex rules, landing pages by make/model/city/body type with minimum-listings threshold, redirects manager, AI crawler controls, `llms.txt` option.
> **Done when:** structured data validates, sitemaps list all languages, no indexable thin facet pages.

### W10 — `wotoyi-builder` v1 (§25)
> Editor extensions on every block: animation panel (presets, delay, duration, trigger, per-device intensity), responsive visibility, light/dark visibility, hover effects; premium section library (patterns) in 4 languages; Design Studio (global styles with contrast validator that blocks publishing a failing palette); version history guidance. No build step required for the client (plain JS using WordPress globals) unless a build is shipped compiled.
> **Done when:** an admin builds a complete landing page without code, previews phone/tablet/desktop, light/dark, and it renders identically on the front end.

### W11 — Admin & no-code settings (§27)
> Clean Wotoyi admin menu and dashboard (new leads, inspection orders, listings, dealers), settings for everything (contacts, WhatsApp, texts, legal pages, packages, plans, social links, AI options), audit log of admin actions, role-based access (admin, operations, inspection manager, inspector, support).
> **Done when:** every setting changes the front end without code; audit log records changes.

### W12 — Demo content
> One-click installer: 3–4 demo dealers and 12+ demo vehicles with the W1 images, pages and menus in 4 languages, inspection packages without prices; every demo item labelled "Démo"; one-click removal.
> **Done when:** a fresh WordPress + theme + plugins + demo = a complete, beautiful site in under 2 minutes.

### W13 — QA, security & performance audit
> Run the full QA checklist (§5). Fix everything. Produce the screenshot matrix and attach the most important ones to DELIVERY-NOTES.md.

### W14 — Packaging & guide
> Zips for theme and each plugin; `GUIDE-INSTALLATION.pptx` in French (simple, visual): create InfinityFree account and site, install WordPress, upload theme and plugins (WordPress admin or cPanel File Manager if upload too large), activate in the right order, set permalinks, install demo, change logo/colors/texts without code, known limits of free hosting (no emails, no API), next steps.

---

## 5. QA checklist (Definition of Done for every workstream)

- [ ] `php -l` on every PHP file; no PHP notice/warning in `debug.log` after full test run.
- [ ] WordPress coding and security rules respected (§14.3); public forms protected (nonce, honeypot, rate limit).
- [ ] Screenshot matrix: 4 languages × light/dark × phone/tablet/desktop — no empty section, no overflow, no invisible element, Arabic fully mirrored.
- [ ] Contrast script passes in both modes.
- [ ] Lighthouse mobile ≥ 90 performance, = 100 SEO, ≥ 95 accessibility on home and vehicle page.
- [ ] `prefers-reduced-motion` respected; no animation delays the main content.
- [ ] No forbidden inspection wording; no « car » in French copy; no invented figures.
- [ ] Works on a fresh install with only these packages active.

---

## 6. Kick-off prompt (first message of the engineering session)

> You are the engineering team of the Wotoyi project. Read PROJECT.md (v1.0) and DEV_BRIEF.md entirely. Then: (1) set up the test environment (§3), (2) execute workstreams W1 → W14 in order, applying the QA checklist after each, (3) deliver the zips, the PPTX guide and DELIVERY-NOTES.md. Apply every recommendation directly. Challenge any requirement that would harm quality, security or performance, propose the better option in DELIVERY-NOTES.md, and continue with the best decision. Never invent prices, APIs or test results.
