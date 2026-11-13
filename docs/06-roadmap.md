# 6. Phases, acceptance criteria and progress record

Legend: ✔ done and tested · ◐ partial · ○ not started

## Phase 1 — Foundations and public marketplace ✔

| Item | State | Evidence |
|---|---|---|
| Architecture, schemas, API contracts, sync protocol, security model | ✔ | docs/01–05 |
| WordPress project setup, versioned migrations | ✔ | `class-database.php`, tests "Schema and migrations" |
| Design system (tokens, buttons, forms, cards, badges, alerts, tabs, pagination, tables, empty/loading states) | ✔ | `shared/design-system/` |
| Company site: Home, About, Services, How it works, Contact, FAQ, Become a dealer (landing + request form) | ✔ | HTTP tests (200, French) |
| Privacy policy, Terms | ◐ | Created as **draft templates**; need legal validation before publishing |
| Marketplace home (hero search, featured, sale, rent, dealers, recent, why, dealer promo, testimonials, CTA, footer) | ✔ | browser tests + screenshots |
| Catalog: all / sale / rent / featured / search results, 13 filters, 5 sorts, pagination, AJAX with URL sync and no-JS fallback | ✔ | 23 filter/sort tests + browser tests |
| Vehicle page: gallery, specs, description, features, price, dealer block, website link, WhatsApp, phone, similar vehicles | ✔ | HTTP + browser tests |
| Dealers directory and public profile (no private data) | ✔ | isolation tests |
| SEO: clean URLs, titles, descriptions, canonical, robots rules, Open Graph, JSON-LD (Car/Offer, AutoDealer, BreadcrumbList, ItemList, WebSite), sitemap | ✔ | "HTTP: SEO" tests |
| Listing freshness policy (stale flag, auto-hide) | ✔ | visibility tests |
| Public read API with rate limiting | ✔ | "HTTP: REST API" tests |
| French interface (213 + 98 strings), English source | ✔ | coverage test |
| Responsive, self-hosted fonts, lazy images, no third-party requests | ✔ | browser tests |

**Test results (local WordPress 6.8.3, PHP 8.3, SQLite): 127/127 integration checks, 32/32 browser checks.**

Not verified in Phase 1 (be aware):
- Not run on MySQL/MariaDB (no database server in the build environment). SQL is standard and goes through `dbDelta`/`$wpdb`; **run `tools/tests/run.php` on a MySQL staging site before launch.**
- Email delivery not tested (no mail server); requests are stored in the database regardless.
- WordPress admin screens (Settings, Contact requests, demo buttons) were exercised only through their underlying functions, not clicked through in a browser.
- No load test, no formal accessibility audit, no real photos (demo uses illustrations).

## Added after Phase 1 — brand and website AI assistant ◐

| Item | State | Evidence |
|---|---|---|
| Rebrand to **AutoBuzy** (theme `autobuzy`) | ✔ | all tests re-run |
| Public assistant: chat widget + homepage "ask" bar, vehicle cards, session history, rate limit (20 messages / 10 min per IP), daily cap | ✔ | browser check |
| Built-in natural-language search (works without any AI provider) | ✔ | tested on French queries |
| Conversational AI through the official Anthropic PHP SDK, read-only tools, usage log (`amc_ai_usage`) | ◐ | Code path executed up to the provider (an invalid key is rejected and the fallback takes over). **A real conversation has not been run: no API key was available.** Test it once your key is configured. |
| Admin assistant page and "draft a reply" on contact requests | ◐ | Pages load and the "not configured" path works; AI answers untested for the same reason |
| Wolof | ○ | The model is instructed to answer in Wolof when confident; quality has not been evaluated with native speakers |

This website assistant is separate from the Phase 5 dealer assistant (WhatsApp/voice, connected to each dealer CRM).

## Phase 2 — Dealer membership ◐ (built, not tested)

Built as specified in the acceptance criteria below: editable plans and durations/discounts, public pricing page, 5-step registration with private document upload, dealer statuses Pending → Under review → Approved → Active (+ Suspended / Expired / Cancelled), manual payment providers with receipt verification, subscriptions with renewal queueing, reminders (7 days, 1 day), grace period and expiry by daily cron, provisioning provider interface with a manual checklist, support conversations, transactional emails, dealer space (/dealer-dashboard/) and platform administration (/platform-admin/) separate from wp-admin, roles "Dealer owner" and "Platform manager", audit of every staff action, admin AI assistant tool for memberships, French translation (0 missing strings).

**Not tested:** at the owner's request, Phase 2 was delivered without running the test suites or opening the pages in a browser. Only PHP and JavaScript syntax checks were run. Test the full workflow on a staging site before taking real registrations: register a dealer, upload documents, approve, confirm a payment, complete the checklist, renew, and let a subscription expire.

Previous acceptance criteria:
Acceptance: a dealer can pick a plan, register, submit company + verification data, choose domain option, submit payment proof; an administrator can review, approve/reject, activate a subscription, suspend; statuses flow Pending → Under review → Approved → Active (+ Suspended/Expired/Cancelled); plans, prices, limits, durations and discounts are editable and never hardcoded; manual provisioning job with checklist; `/platform-admin/` with Overview, Dealers, Applications, Subscriptions, Payments, Websites, Listings, Featured, Support, Audit, Settings; expiry + reminders by cron; every admin action audited.

## Phase 3 — Dealer website + CRM ○
Acceptance: installable theme + CRM plugin on a blank WordPress; `/manager/` with dashboard, vehicles (7 statuses, photos), leads pipeline, customers, sales (confirm ⇒ vehicle sold), rentals (no overlapping confirmed rentals), team invites with 5 roles; all permissions enforced server-side; dealer branding without code.

## Phase 4 — API + connector ○
Acceptance: connector installs, handshake succeeds, publishing/price change/sold/unpublish reach the marketplace within the retry policy; duplicates impossible; forged, replayed or expired requests rejected; revoked key stops working immediately; offline dealer ⇒ stale then hidden; connection monitoring and manual resync; marketplace leads delivered to the CRM by signed webhook.

## Phase 5 — WhatsApp + AI assistant ○
Acceptance: text commands in French and English for all 15 tools; sensitive actions require preview + confirmation; unknown numbers refused; per-dealer quotas; Wolof text and audio evaluated on a recorded test set with a published accuracy figure before activation.

## Phase 6 — Telephony, reporting, hardening ○
Acceptance: provider/regulatory verification documented; inbound call flow with human handoff and summaries; advanced reports; security review, load test, backup/restore drill, deployment runbook.

## Decisions to confirm with the product owner

1. ~~Brand name~~ — decided: AutoBuzy.
2. **Canonical policy** default (`self`) — see docs/01 §1.4.
3. **Freshness windows** (72 h / 168 h) — editable in settings.
4. **Dealer hosting provider** — determines how much of provisioning can be automated in Phase 2.
5. **Payment aggregator** — determines whether renewals can be automatic.
