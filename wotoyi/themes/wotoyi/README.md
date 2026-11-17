# Wotoyi theme (block theme, v2.0.0)

Premium "Luxe Motion" marketplace theme: light / dark, French today (English / Arabic / Wolof need the `wotoyi-i18n` plugin, which does not exist yet; right-to-left CSS is in place but unproven in the theme), no dependency on
Flatsome, UX Builder or any page builder. It works on its own and fills itself with vehicles, dealers and services when the
**wotoyi-marketplace** plugin is active.

## What you need
- WordPress 6.6 or newer, PHP 8.0 or newer.
- Nothing else. No build step: the ready-to-use CSS/JS is already inside `assets/dist/`.

## Install (no coding)
1. Zip the folder `wotoyi/themes/wotoyi` (the zip must contain `style.css` at its top level, inside a folder named `wotoyi`).
2. WordPress admin > Appearance > Themes > Add New > Upload Theme > choose the zip > Install > Activate.
3. Install and activate the **wotoyi-marketplace** plugin (Plugins > Add New > Upload Plugin). Without it the site still
   works and shows friendly "catalogue coming soon" messages.
4. Settings > Permalinks > click **Save Changes** once (this activates the pages /vehicules/, /vehicule/<name>/ and
   /concessionnaire/<name>/).
5. Pages > Add New: create a page called "Accueil" and another called "Actualites". Settings > Reading > "A static page":
   Homepage = Accueil. (If you leave "Your latest posts", the home design still shows.)
6. Appearance > Editor > Patterns > "Wotoyi - Accueil": every home section is a pattern you can edit, reorder or remove.
   Site name and tagline (Settings > General) feed the footer text.

## Dark mode, language, motion
- The visitor chooses light / dark / auto with the sun-moon button; the choice is remembered in the browser.
- Animations switch off automatically for visitors who ask their device for reduced motion, and are simplified on low-end phones.
- Language switcher appears only when a translation plugin (wotoyi-i18n) provides it.

## Links in the header and footer
The theme shows only links that exist. Terms, privacy, contact, Wotoyi Pro, account and safety pages are empty until the
plugins (or a developer, with the filter `wotoyi_theme_urls`) give them an address.

## Troubleshooting
- Plain, unstyled page: the folder was zipped one level too deep (style.css must be directly inside `wotoyi/`).
- "Page not found" on /vehicules/: do step 4 (Save Permalinks).
- Fonts look different for a second: normal, they are self-hosted and size-matched to avoid layout jumps.

## For developers
- `inc/data.php` is the only place that talks to the plugin; override any data with `wotoyi_theme_data_{search|facets|vehicle|dealer|dealers|services}`.
- `inc/routes.php` registers the three public routes (disable: `add_filter( 'wotoyi_theme_routes', '__return_false' )`).
- Design tokens: `wotoyi/design/tokens.json` -> `python3 -I bin/build.py` regenerates `assets/css/tokens.css`, `theme.json`, `assets/dist/*`.
  Optional minification: `ESBUILD=/path/to/esbuild python3 -I bin/build.py`.
- Heading word animation is opt-in with the CSS class `wy-split-heading` (not `wy-split`, which is the two-column layout). Never put it on the hero H1 (LCP): the hero uses `data-wy-sheen` instead.

## Search, vehicle page, leads (what is wired)
- Search form (`inc/views/block-search.php`) sends plain GET parameters to the marketplace search. About 45 of the plugin's 52 parameters
  are exposed: keyword, offer type, make, model and trim (once a make/model is chosen), city, price, monthly rent, year, mileage, engine cc,
  power, consumption, electric range, seats, doors, owners, body, fuel, gearbox, drive, condition, exterior and interior colour,
  upholstery, customs status, origin country, equipment (max 10, the plugin's limit), posted since, and 11 yes/no flags
  (inspected, verified dealer, accident free, warranty, photos, service history, delivery, financing, trade-in, video, 360).
- Vehicle page lead form: dialog "Envoyer une demande" -> `GET /wotoyi/v1/leads/token?vehicle=<uuid>` then `POST /wotoyi/v1/leads` (JSON: vehicle, token,
  name, phone or e-mail, message, consent, honeypot `website`). A fresh single-use token is fetched at every opening and after every failure;
  the form waits for the plugin's minimum age (3 s). Without JavaScript the button is hidden and WhatsApp / phone remain.
  The consent sentence is a draft and needs Legal review.

## Not yet (honest list)
- **Never run on real WordPress/MySQL.** Routing, template hierarchy, the lead round trip and the block registration are verified by code reading and PHP lint only; no Lighthouse, no 60 fps traces.
- **Languages:** the theme is French only. No `languages/` files, no `wotoyi-i18n`, no Wolof. The English/Arabic strings exist only in the HTML prototype.
- **Search gaps:** dependent model list needs a page reload after choosing the make (no JavaScript cascade on this page); no distance / map search (`lat`, `lng`, `radius_km`), no `rental_duration`, no dealer filter,
  no registration country; no "Enregistrer la recherche" (needs login; plugin route `/me/saved-searches` exists, theme UI does not).
- **Vehicle page:** no 360 degree viewer (only the `has_360` filter); no compare view; no financing calculator. Lead form has no CAPTCHA/challenge provider (plugin filter `wotoyi_mp_lead_challenge` supports one).
- **Lead delivery:** the plugin stores the lead; notification e-mail/WhatsApp to the dealer is not built (plugin README, limit 7).
- **Vehicle illustrations** are the placeholder SVGs (same silhouette for SUV/pickup/sedan); real photos or renders need a founder decision.
- Equipment labels cover the plugin's current taxonomy; new codes fall back to the raw code until labelled.
