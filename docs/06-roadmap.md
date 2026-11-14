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

## Phase 3 — Dealer website + CRM ◐ (built, not tested)

Built: plugin **AutoBuzy Dealer CRM** (13 custom tables, `/manager/` application with dashboard, vehicles, leads pipeline, customers, sales, rentals with overlap protection and availability planner, appointments, team invitations, 5 roles + per-person permissions, settings, activity log; public inventory, vehicle pages, about/contact/financing pages, lead forms, SEO, sitemap, read-only `dealer/v1` API) and theme **AutoDealer** (homepage, header, footer, 4 design presets, branding from the CRM). French translation complete. See docs/INSTALL-DEALER.md.

**Not tested:** delivered without running WordPress or a browser, at the owner's request; only syntax checks were run. Test on a staging dealer site: create vehicles with photos, publish as manager and as sales agent, receive a website lead, move it through the pipeline, record and cancel a sale, create two overlapping rentals and check the second is refused, invite a team member.

Acceptance criteria:
Acceptance: installable theme + CRM plugin on a blank WordPress; `/manager/` with dashboard, vehicles (7 statuses, photos), leads pipeline, customers, sales (confirm ⇒ vehicle sold), rentals (no overlapping confirmed rentals), team invites with 5 roles; all permissions enforced server-side; dealer branding without code.

## Phase 4 — API + connector ◐ (built, not tested)

Built: signed sync API `marketplace/v1/sync/*` (handshake, status, upsert, status, delete, heartbeat/reconciliation, contacts) with HMAC, timestamp window, idempotency replay, per-key rate limit, plan limits and stale-update protection; per-dealer credentials (connection code shown once, encrypted secret, rotation, revocation, health); signed webhooks with queue, backoff and retry (`lead.created`, `resync.requested`, `dealer.updated`); "Message the dealer" form on marketplace vehicle pages, delivered to the CRM as a lead or by email as fallback; Platform admin → **Connections**. New dealer plugin **AutoBuzy Marketplace Connector** (connection by code, coalesced outbox, backoff, hourly heartbeat with self-healing, webhook receiver with replay protection, CRM screen *Marketplace*). Docs: docs/03.

**Not tested** (owner's request, syntax checks only). Acceptance to run on staging: connect a dealer site; publish, change price, reserve, sell, unpublish and delete a vehicle and check the marketplace each time; send the same request twice (same Idempotency-Key) and check one effect; alter one byte of a signed request and expect 401; revoke the key and expect 401; switch off the dealer site 72 h/168 h (or lower the windows) and check stale then hidden; send a message from a marketplace vehicle page and find the lead in the CRM; disconnect the dealer site and check the email fallback.

## Phase 5 — WhatsApp + AI assistant ◐ (built, not tested, disabled by default)

Built: WhatsApp Cloud API webhook (signature, dedupe, async processing), dealer agent with 11 CRM tools executed on the dealer site as the linked team member, server-side confirmation (OUI/YES/WAAW · NON/NO/DEEDET, 15 min expiry) for status and price changes, public buyer assistant for unknown numbers, plan quotas and flood limits, provider-independent speech-to-text for voice notes, team members' numbers registered from the CRM, Platform admin → **WhatsApp & voice**. Docs: docs/07.

**Not tested.** Acceptance before enabling: Meta number verified; each tool exercised in French and English from a dealer number, and with a user lacking the permission; confirm/cancel/expiry paths; unknown number answered by the buyer assistant; quota reached message; **Wolof text and voice evaluated on a recorded test set by native speakers** with a written accuracy figure.

## Phase 6 — Telephony, reporting, hardening ◐ (built, not tested; telephony disabled by default)

Built: voice gateway endpoint (signed, provider-independent) using the same assistant, call log; Reports in the CRM (lead sources and conversion, sales, margin, time to sell, aging stock, rental income and occupancy, team) and in Platform admin (12-month growth, most requested dealers); privacy export/erase tools and retention purges (central and CRM); cache and security headers; docs/DEPLOYMENT.md (runbook, backups, restore drill, monitoring, load-test script), docs/SECURITY-REVIEW.md, `tools/dev/build-release.sh`.

**Not done / to do by the owner:** choose and verify a telephony provider (Senegalese numbers, cost, recording consent rules ARTP/CDP), test the gateway end to end with human handoff; run the load test and the restore drill; independent penetration test.

## Decisions to confirm with the product owner

1. ~~Brand name~~ — decided: AutoBuzy.
2. **Canonical policy** default (`self`) — see docs/01 §1.4.
3. **Freshness windows** (72 h / 168 h) — editable in settings.
4. **Dealer hosting provider** — determines how much of provisioning can be automated in Phase 2.
5. **Payment aggregator** — determines whether renewals can be automatic.
