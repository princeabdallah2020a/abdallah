# AutoBuzy — Automotive Marketplace Ecosystem

Monorepo for an international automotive platform built on WordPress:

1. **Central website + marketplace** (`central/`) — company site, vehicle catalog, dealer directory, public API.
2. **Independent dealer websites + CRM** (`dealer/`) — one separate WordPress install per dealership *(Phase 3)*.
3. **AI assistant** (module of the central plugin) — WhatsApp / voice assistant connected to each dealer CRM through signed APIs *(Phases 5–6)*.

This is **not** a WordPress Multisite: every dealership has its own domain, hosting, database and backups. The marketplace only aggregates approved public listings through signed APIs.

## Status

| Phase | Scope | Status |
|---|---|---|
| 1 | Architecture, schemas, design system, company site, marketplace home, catalog, vehicle pages | **Done and tested** |
| + | AutoBuzy brand, website AI assistant (visitors + admin) | **Built**; AI replies need your API key and are untested without one |
| 2 | Dealer registration, plans, onboarding, subscriptions, `/platform-admin/` | **Built, not tested** |
| 3 | Dealer theme, dealer CRM plugin | **Built, not tested** |
| 4 | Sync API, Marketplace Connector plugin, webhooks, marketplace leads | **Built, not tested** |
| 5 | WhatsApp + AI assistant (FR/EN/Wolof) | **Built, not tested**, off by default |
| 6 | Voice gateway, reports, privacy tools, hardening, deployment runbook | **Built, not tested**; telephony off by default |

Detailed checklist: [docs/06-roadmap.md](docs/06-roadmap.md).

## Documentation

| File | Content |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | **Start here** — central site: install, activate, configure, test |
| [docs/INSTALL-DEALER.md](docs/INSTALL-DEALER.md) | Dealer website + CRM: install and use |
| [docs/01-architecture.md](docs/01-architecture.md) | Final technical architecture, components, folder structure |
| [docs/02-database.md](docs/02-database.md) | Database schemas (central and dealer) |
| [docs/03-api-and-sync.md](docs/03-api-and-sync.md) | API contracts and synchronisation protocol |
| [docs/04-security.md](docs/04-security.md) | Security model |
| [docs/05-external-services.md](docs/05-external-services.md) | External accounts and dependencies |
| [docs/06-roadmap.md](docs/06-roadmap.md) | Phases, acceptance criteria, completed / pending record |
| [docs/07-whatsapp-and-voice.md](docs/07-whatsapp-and-voice.md) | WhatsApp and voice AI assistant: flow, confirmations, setup |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production runbook: build, cron, cache, backups, monitoring |
| [docs/SECURITY-REVIEW.md](docs/SECURITY-REVIEW.md) | Trust boundaries, controls, residual risks |

## Repository layout

```
central/
  plugins/automotive-marketplace-core/   Central plugin (data, catalog, API, SEO, admin)
  themes/autobuzy/                     Central theme (company site + marketplace UI)
dealer/plugins/automotive-dealer-crm/    Dealer CRM (/manager/) and public inventory
dealer/plugins/automotive-marketplace-connector/  Connects a dealer site to the marketplace (sync, leads, AI)
dealer/themes/autodealer/                Dealer website theme
shared/design-system/                    Design tokens + base components (single source of truth)
tools/dev/                               Local site setup, design-system sync, i18n extraction, release build
tools/tests/                             Integration tests (PHP) and browser tests (Playwright)
docs/                                    Architecture and guides
```
