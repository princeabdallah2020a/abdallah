# AI assistant service (Phase 5–6)

A separate service (not a WordPress plugin) that receives WhatsApp / telephone
messages, identifies the dealership, and calls the dealer API with a scoped,
revocable token. Nothing is implemented yet; see `docs/01-architecture.md` §6.

It must not be started before the marketplace, the CRM and the sync API are functional.
