# Wotoyi theme (block theme, v2.0.0)

Premium "Luxe Motion" marketplace theme: light / dark, French / English / Arabic (right-to-left supported), no dependency on
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
- Heading word animation is opt-in with the CSS class `wy-split-heading` (not `wy-split`, which is the two-column layout).
