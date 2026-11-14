# Security review — Phases 1–6

**Type:** design and code review by the developer. No automated scanner and no penetration test were run. Repeat it with an independent reviewer before handling real payments or personal data at scale.

## Trust boundaries and controls

| Boundary | Threat | Control (where) |
|---|---|---|
| Visitor → forms (contact, vehicle message, registration) | Spam, CSRF, injection | Nonce, honeypot, minimum fill time, per-IP rate limit, sanitisation; stored before emailing (`Inquiries`, `Marketplace_Leads`, `Memberships`) |
| Visitor → public API | Scraping, abuse | Read-only allow-listed fields, per-IP rate limit, short public cache (`Api`) |
| Staff / dealer → `/platform-admin/`, `/dealer-dashboard/` | Privilege escalation, CSRF, clickjacking | Capability checks, dealer resolved from the logged-in user, per-action nonce, audit log, `X-Frame-Options` |
| Dealer team → `/manager/` | Over-privileged staff | Capability per action (5 roles + per-person permissions), lead visibility rules, server-side only |
| Dealer site → marketplace (`/sync/*`) | Forged, replayed or modified requests; writing another dealer's listings | HMAC over method + route + body hash, ±300 s window, idempotency store, dealer resolved from the key only, privileged fields stripped, plan limit, revocation, 600 req/min per key (`Sync_Api`) |
| Marketplace → dealer site (webhooks, AI) | Forged commands; SSRF from the centre | HMAC with the dealer's own secret, delivery-id replay protection, `wp_safe_remote_post` (no private/internal targets), no redirects (`Webhooks`, `Dealer_Agent`, connector `Connection::verify`) |
| WhatsApp → marketplace | Forged webhook, flooding | `X-Hub-Signature-256` with the app secret, phone-number-id check, message-id dedupe, 40 messages/hour per number, plan quota, global daily AI cap |
| AI model → CRM | Prompt injection, unauthorised actions | Dealer taken from the contact table, user re-validated and permissions enforced by the dealer site, sensitive actions only after a yes/no parsed **outside** the model, 15-minute expiry, CRM activity log, third-party text flagged as data in prompts |
| Voice gateway → marketplace | Forged events | HMAC with shared secret, timestamp window, disabled by default (`Telephony`) |
| Media download | Fetching arbitrary URLs | Meta media hosts only, 16 MB limit (`Whatsapp::download_media`) |
| Secrets at rest | Database leak | Constants preferred; otherwise libsodium secretbox, key derived from the salts; never displayed again; connection code shown once |
| Private files | Direct download | Private folders with deny rules, served by PHP after a capability check |
| Personal data | Over-retention | Automatic purges (vehicle messages 180 d, WhatsApp/calls 90 d, contact requests 1 y), WordPress export/erase tools, privacy policy text |

## Residual risks and recommendations

1. Phases 2–6 have **not been executed**: run the acceptance checklists (docs/06) on staging before production.
2. Changing the WordPress salts makes stored secrets unreadable (DEPLOYMENT §3): plan it with a re-issue of dealer codes.
3. One symmetric secret per dealer: a compromised dealer site can only act as that dealer, on its own listings. Rotate its code after any incident.
4. WP-Cron must be driven by a real cron, otherwise webhooks and expiries are delayed.
5. Wolof speech-recognition quality is unknown. A wrong transcription cannot trigger a sensitive action without explicit confirmation, but can produce a wrong answer.
6. Rate limits use transients (per server without a shared object cache): use Redis in multi-server setups.
7. Payments are verified manually by staff (no gateway yet).
8. Next steps: dependency scanning of `vendor/`, WAF, two-factor authentication for staff, external penetration test.
