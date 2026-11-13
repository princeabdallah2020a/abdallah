=== AutoBuzy Dealer CRM ===
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later

Independent dealership CRM and public inventory for the dealer's own WordPress website. Install with the "AutoDealer" theme.

== Installation ==

1. Upload the plugin (Plugins → Add new → Upload) and activate it.
2. Upload and activate the "AutoDealer" theme.
3. Settings → Permalinks: choose "Post name" and save.
4. Create the dealer owner: Users → Add new → Role "Dealer owner".
5. The dealer logs in at /manager/login/ and completes Settings (business, logo, colours, homepage).

== URLs ==

Website: / · /inventory/ · /rentals/ · /vehicle/{slug}/ · /about/ · /contact/ · /financing/
CRM: /manager/ (dashboard, vehicles, leads, customers, sales, rentals, team, settings, activity)
API: /wp-json/dealer/v1/vehicles · /wp-json/dealer/v1/business (public, read-only)

== Changelog ==

= 0.3.0 =
* First release: CRM, team roles and invitations, dealer website, SEO, public API.
