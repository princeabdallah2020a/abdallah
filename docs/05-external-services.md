# 5. External services and dependencies

Nothing external is needed for Phase 1 beyond hosting. Later phases depend on accounts **you** must open; none is assumed to work until verified.

| Need | Phase | Candidates | What must be verified first | You provide |
|---|---|---|---|---|
| Hosting for the central site | 1 | Any PHP 8.1+/MySQL host with HTTPS, cron, daily backups; Redis recommended | — | Hosting account, domain, DNS access |
| Transactional email | 1–2 | SMTP service (e.g. Brevo, Mailgun, Amazon SES) via an SMTP plugin or `wp-config` | Deliverability to local providers | SMTP credentials, SPF/DKIM on the domain |
| Website AI assistant | now | Anthropic API (Claude) via the official PHP SDK | Your monthly budget; set the daily cap accordingly | API key from console.anthropic.com |
| Hosting for dealer sites | 2–3 | A reseller/VPS panel **with an API** enables automation; otherwise manual provisioning | Whether the host exposes an API for account + WordPress creation; pricing per site | Reseller account, API token |
| Domain registration | 2 | Registrar with API (for `.com`); `.sn` domains go through NIC Sénégal-accredited registrars, usually manual | API availability per extension | Registrar account |
| Mobile money / card payments | 2 | Wave, Orange Money, or an aggregator (e.g. PayDunya, CinetPay, PayTech) | Merchant account approval; API for one-off payments; **whether recurring/tokenised payments exist** (often not for mobile money) | Merchant account, API keys, webhook secret |
| WhatsApp | 5 | WhatsApp Business Platform (Cloud API) by Meta, directly or through a BSP | Business verification; number registration; per-conversation pricing; template approval | Meta Business account, phone number |
| LLM | 5 | An LLM API with tool calling | Quality on French and on Wolof/French code-switching, measured on real messages | API key, budget |
| Speech-to-text / text-to-speech | 5 | Provider-independent interface; candidates evaluated on real audio | **Wolof recognition and synthesis quality is not assumed**: test set recorded by native speakers | API keys |
| Telephony / SIP | 6 | SIP trunk or programmable-voice provider | Availability of Senegalese numbers, inbound/outbound rules, cost per minute, recording/consent regulation (ARTP, CDP) | Provider account, number |
| Error monitoring, uptime | 6 | Any (e.g. Sentry, UptimeRobot) | — | Accounts |

Credentials are never committed: they go in `wp-config.php` constants or encrypted options, and the code ships placeholders only.
