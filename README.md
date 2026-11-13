# Automotive Marketplace Ecosystem

Monorepo for an international automotive platform built on WordPress:

1. **Central website + marketplace** (`central/`) — company site, vehicle catalog, dealer directory, public API.
2. **Independent dealer websites + CRM** (`dealer/`) — one separate WordPress install per dealership *(Phase 3)*.
3. **AI assistant service** (`ai-service/`) — WhatsApp / voice assistant connected to each dealer CRM *(Phase 5–6)*.

This is **not** a WordPress Multisite: every dealership has its own domain, hosting, database and backups. The marketplace only aggregates approved public listings through signed APIs.

## Status

| Phase | Scope | Status |
|---|---|---|
| 1 | Architecture, schemas, design system, company site, marketplace home, catalog, vehicle pages | **Done and tested** |
| 2 | Dealer registration, plans, onboarding, subscriptions, `/platform-admin/` | Not started |
| 3 | Dealer theme, dealer CRM plugin | Not started |
| 4 | Sync API, Marketplace Connector plugin, webhooks | Designed (docs/03), not started |
| 5 | WhatsApp + AI assistant (FR/EN/Wolof) | Designed (docs/01), not started |
| 6 | SIP telephony, reporting, hardening, production | Not started |

Detailed checklist: [docs/06-roadmap.md](docs/06-roadmap.md).

## Documentation

| File | Content |
|---|---|
| [docs/INSTALL.md](docs/INSTALL.md) | **Start here** — install, activate, configure, test |
| [docs/01-architecture.md](docs/01-architecture.md) | Final technical architecture, components, folder structure |
| [docs/02-database.md](docs/02-database.md) | Database schemas (central and dealer) |
| [docs/03-api-and-sync.md](docs/03-api-and-sync.md) | API contracts and synchronisation protocol |
| [docs/04-security.md](docs/04-security.md) | Security model |
| [docs/05-external-services.md](docs/05-external-services.md) | External accounts and dependencies |
| [docs/06-roadmap.md](docs/06-roadmap.md) | Phases, acceptance criteria, completed / pending record |

## Repository layout

```
central/
  plugins/automotive-marketplace-core/   Central plugin (data, catalog, API, SEO, admin)
  themes/automarket/                     Central theme (company site + marketplace UI)
dealer/                                  Dealer theme, CRM and connector plugins (Phase 3–4)
ai-service/                              AI assistant service (Phase 5–6)
shared/design-system/                    Design tokens + base components (single source of truth)
tools/dev/                               Local site setup, design-system sync, i18n extraction
tools/tests/                             Integration tests (PHP) and browser tests (Playwright)
docs/                                    Architecture and guides
```
