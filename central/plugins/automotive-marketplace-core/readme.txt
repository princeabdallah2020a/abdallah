=== AutoBuzy Marketplace Core ===
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later

AutoBuzy central platform: automotive marketplace, dealer memberships and subscriptions, platform administration and AI assistant.

== Installation ==

1. Upload the "automotive-marketplace-core" folder to wp-content/plugins/ (or upload the zip in Plugins → Add new → Upload).
2. Activate the plugin, then activate the "AutoBuzy" theme.
3. Settings → Permalinks: choose "Post name" and save.
4. Open /platform-admin/ (Marketplace → Platform admin ↗) to configure payment details, plans and prices.
5. Optional AI assistant: add define( 'AUTOBUZY_ANTHROPIC_API_KEY', 'sk-ant-…' ); to wp-config.php.

Full guide: docs/INSTALL.md in the project repository.

== Main URLs ==

/ · /cars/ · /car/{slug}/ · /dealers/ · /dealer/{slug}/ · /pricing/ · /dealer-registration/ · /dealer-login/ · /dealer-dashboard/ · /platform-admin/

== Changelog ==

= 0.2.0 =
* Dealer memberships: editable plans and commitment discounts, online registration with documents, manual payments (Wave, Orange Money, bank transfer, cash) with receipt verification, subscriptions with reminders, grace period and expiry, website provisioning checklist, support conversations, dealer space and platform administration.
* AI assistant for visitors and administrators.

= 0.1.0 =
* Marketplace: catalog, filters, vehicle pages, dealers directory, SEO, public API.
