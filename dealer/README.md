# Dealer package (Phase 3)

Installed on **each independent dealer WordPress site** (own domain, hosting, database).

| Folder | What it is |
|---|---|
| `plugins/automotive-dealer-crm/` | **AutoBuzy Dealer CRM** — the CRM at `/manager/` and the public inventory of the dealer website |
| `themes/autodealer/` | **AutoDealer** — the dealer website theme, branded from the CRM settings |

The Marketplace Connector (sync with the central marketplace) is Phase 4. The CRM already emits the `adc_vehicle_changed( $id, $event, $uid )` and `adc_lead_created( $id )` actions it will use.

Installation: see `docs/INSTALL-DEALER.md`.
