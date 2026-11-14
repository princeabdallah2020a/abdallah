# 4. Security model

## 4.1 Principles

1. **Identity is resolved server-side.** A dealer is determined from the authenticated session, API key or token — never from a `dealer_id` sent by a browser, a dealer site or the AI.
2. **Isolation by architecture.** Each dealer's private data lives only in that dealer's own database. The centre holds public listing copies and account/billing data.
3. **Least privilege.** Roles with granular capabilities; API keys and AI tokens are scoped and revocable; the AI can only call a fixed list of functions.
4. **Deny by default.** Public queries select allow-listed columns; statuses not explicitly public are invisible.

## 4.2 Controls and where they live

| Control | Implementation | Status |
|---|---|---|
| Prepared SQL everywhere | `$wpdb->prepare()` / `insert()` / `update()`; table names only from `Database::table()` | Phase 1 ✔ tested with hostile input |
| Input validation | `Vehicles::sanitize_filters()` (enum allow-lists, numeric casting, length limits), `Vehicles::upsert()`, `Settings::sanitize()` | ✔ tested |
| Output escaping | `esc_html/attr/url` in every template; JSON-LD encoded with `JSON_HEX_TAG` | ✔ |
| CSRF | Nonces on the contact form and every admin action (`check_admin_referer`) | ✔ tested |
| Authorisation | `current_user_can()` on every admin screen and handler | ✔ tested (anonymous call blocked) |
| Rate limiting | `Rate_Limiter` — public API per IP (configurable), contact form 5/hour | ✔ tested (429) |
| Spam | Honeypot + minimum fill time on forms | ✔ tested |
| Privacy | IP addresses stored only as salted HMAC; dealer `contact_email`/`user_id` never in public queries or API | ✔ tested |
| Audit log | `Audit::log()` — activation, settings changes, demo import/removal, inquiry handling | ✔ (extended every phase) |
| Listing visibility | Dealer must be `active`; listing `active/reserved`; freshness window | ✔ tested |
| Safe uninstall | Data kept unless explicitly opted in | ✔ |
| Password handling | WordPress core hashing; no custom password storage | by design |
| Per-dealer API credentials, HMAC signatures, timestamp window, idempotency keys | `amc_api_credentials` (secret stored hashed; webhook secret encrypted with a key from `wp-config.php`) | Phase 4 |
| Secret rotation / revocation | New key issued, old key valid for a grace period, then revoked; logged | Phase 4 |
| Signed webhooks + replay protection | HMAC + timestamp + unique delivery ID stored in `adc_inbox` | Phase 4 |
| CRM role-based access | Custom capabilities checked in every REST `permission_callback` | Phase 3 |
| Restricted, audited staff access to dealer data | Platform staff see account/billing/sync data only; no CRM data is reachable from the centre at all | Phase 2 (by architecture) |
| AI confirmations | Preview + pending action + explicit confirmation + expiry; acting user's permissions apply | Phase 5 |
| File uploads (documents, payment proofs) | MIME/size validation, stored outside public URLs, served through permission-checked handler | Phase 2–3 |
| Data export / deletion | Hooks into WordPress privacy tools (`wp_privacy_personal_data_exporters/erasers`) for inquiries, leads, customers; retention settings | Phase 2–3 |
| Backups, error monitoring | Per-site host backups + off-site copy; error reporting service; documented in the deployment runbook | Phase 6 |

## 4.3 Server recommendations (production)

HTTPS with HSTS; `DISALLOW_FILE_EDIT`; unique salts; automatic minor updates; a persistent object cache (Redis) so rate limiting does not write to the options table; web application firewall / login throttling; database user limited to the site's database; daily backups with restore test.

## 4.4 Known limitations of Phase 1

- Rate limiting uses the visitor IP as seen by PHP. Behind a reverse proxy/CDN, configure the real-IP header at the web-server level (or the `amc_client_ip` filter); otherwise all visitors share one bucket.
- No CAPTCHA on forms (honeypot + rate limit only). Add one if spam appears.
- The privacy policy and terms are templates in draft: legal validation (Senegal law 2008-12, CDP declaration) is required before launch.

## Phases 4–6

The controls added for the dealer sync API, webhooks, the WhatsApp/voice assistant and personal-data retention, with the residual risks, are listed in [SECURITY-REVIEW.md](SECURITY-REVIEW.md).
