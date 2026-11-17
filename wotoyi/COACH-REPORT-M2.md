# Coach gate report: M2 Marketplace slice

**[Coach]**, independent of every author (I did not write any of the reviewed code). Date: 2026-11-17, 16:38 UTC. Model: Opus (the Fable fallback, CLAUDE.md §3.1.7).
**Scope:** `wotoyi/prototype/`, `wotoyi/themes/wotoyi/`, `wotoyi/plugins/wotoyi-marketplace/`, `wotoyi/plugins/wotoyi-dealer-platform/`, and compliance with CLAUDE.md and PROJECT.md. Read-only review: no code edited, no git commands run.
**References:** DEV_BRIEF_V2 §2, §3 and §6; DESIGN.md; PROJECT.md §10.6 and §23; CLAUDE.md §3.1 and §5.

## Verdict: **SENT BACK**

The plugins pass their own suites, and I confirmed that the security tests catch real regressions. The prototype is clean in automated checks and visually strong at the fold. The slice still does not meet the brief §6 Definition of Done, for three reasons:

- The theme has never run in WordPress.
- The theme does not deliver the marketplace languages or the "maximum search".
- The dealer platform has two open **High** security findings.

On top of that, there are visible defects in Arabic, in the French copy and in the vehicle visuals.

> **The tree changed while I was reviewing it.** Between 16:34 and 16:36 UTC, other agents changed both `bin/check.sh` files, wrote `wotoyi-dealer-platform/SECURITY-REVIEW.md` and created `plugins/wotoyi-demo/`. For about 20 seconds a deliberate failing test (`ZzDeliberateFailTest.php`) sat in the marketplace `tests/cases/`. It is gone now. So this report describes a snapshot, not a commit.
>
> **Process defect P0:** a gate must review a frozen commit hash. Next time, give me the commit hash and stop work on those paths during the review.

---

## 1. What I ran (real results)

| Check | Command / method | Result |
|---|---|---|
| Marketplace plugin | `bash wotoyi-marketplace/bin/check.sh` (run twice, before and after the concurrent edits) | php -l clean. **215 passed, 0 failed, 5,355 assertions**, PHP 8.3.6, exit 0 |
| Dealer platform | `bash wotoyi-dealer-platform/bin/check.sh` (run twice) | Lint clean. **85/85** unit tests (1,466 assertions) and **7/7** integration tests pass. Missing-key script OK. `CHECK PASSED`, exit 0 |
| Do the tests catch a regression? | Temp copy of the marketplace. I disabled the H1 fix (`Authenticator.php:60`) and the M1 fix (`SyncController.php:113`, `get_url_params()` → `get_params()`) | **3 tests failed**, as they should (`test_h1_previous_secret_cannot_rotate_or_disconnect`, `test_m1_json_body_source_id_cannot_override_the_signed_url`, `test_rest_style_put_and_delete_wrappers`) |
| Dealer `check.sh` fails on a failing test | Temp copy with an injected failing test | Current script: exit **1**, `CHECK FAILED` (correct) |
| Theme lint | `php -l` on every theme PHP file | 0 errors |
| Theme build reproducible | `python3 -I bin/build.py` in a temp copy, then `diff -rq` against the repo | Identical |
| theme.json | Parsed the JSON | Valid. Version 3. Template parts `header`/`footer` exist. Custom template `page-night` exists. All 13 pattern slugs used in `front-page.html` exist, and their category is registered |
| Prototype matrix | Playwright Chromium. 3 pages × 390/820/1440 × (FR, AR) × (light, dark), plus EN at 390. 39 runs on the device's auto tier, which resolved to `lite` because the host has 2 CPU cores. A second pass of 12 runs forced `motion=full` | **0 console errors or warnings, 0 failed requests, 0 HTTP ≥ 400.** No horizontal overflow (`scrollWidth == viewport` everywhere). No empty sections. No unlabelled buttons or links. No target under 24 px. Exactly one H1 per page. `dir=rtl` set for AR |
| Reduced motion | `reduced_motion=reduce` on all 3 pages at 390 px | Tier `reduced`, 0 running animations, no content left hidden |
| JS budget | gzip -9 | `motion.js` 5,962 B gz (budget 8 kB). `app.js` 5,064 B gz |
| External assets | grep for URLs | No CDN, no third-party fonts or images. The only URLs are `w3.org` (SVG namespace), `wa.me` and `google.com` (maps link) |

Screenshots are in `/tmp/coach/shots/` (about 100 PNGs). This folder is temporary and is lost when the session ends.

---

## 2. Defects

Severity scale:
- **Blocker:** prevents validation of the milestone.
- **Major:** must be fixed before delivery to the founder.
- **Minor:** fix in the next pass.

### Blockers

**B1. The theme has never run in WordPress. None of the brief §6 Definition of Done items are met for the theme.**
- **Evidence:** no WordPress, MySQL or wp-cli exists in this environment. `docs/STATUS.md` says "Unproven: real MySQL/WordPress … Lighthouse".
- **Not done:** fresh installs (separate and single-install), empty `debug.log`, screenshots of the rendered theme, Lighthouse mobile ≥ 90/95/100/100, 60 fps traces.
- **Code reading only, so assumptions:** routing, the template hierarchy for the virtual routes (`inc/routes.php`, `home_template_hierarchy` with `wotoyi_view`), `supports.autoRegister` in `inc/blocks.php:59`, and the `render_block_core/heading` split filter are all unverified.
- **Fix:** install WordPress 6.7 with PHP 8.x (LocalWP, Docker or the InfinityFree demo) and run the §6 matrix on the theme and both plugins.

**B2. The marketplace has no languages in the theme.**
- `partial-lang.php` renders the switcher only if `wotoyi-i18n` fills `wotoyi_theme_languages`. That plugin does not exist.
- There is no `languages/` folder and no `.po`/`.mo` files, although `inc/setup.php:13` loads that folder.
- Result: the theme is French-only. The brief §3.3 and §6 (4 languages × light/dark × 3 sizes) and PROJECT §24.1 ("FR/EN/Wolof switchable") are not met.
- The theme README also claims "French / English / Arabic" and leaves out Wolof.
- The prototype does have FR/EN/AR (Wolof disabled and flagged). The theme does not carry those translations over.

**B3. Dealer platform: two open High security findings (CISO, `wotoyi-dealer-platform/SECURITY-REVIEW.md`).**
- **H1:** any tenant owner can attach any WordPress user to their tenant. I confirmed this in the code: `MemberService.php:120-148` accepts any `user_id` or email with no consent step. Effect: cross-tenant lock-out and an enumeration oracle.
- **H2:** erasure is incomplete. I did not reproduce the CISO's probe P5. I only saw that `CustomerService` erase nulls the customer, leads and activities.
- Medium findings still open: M1–M4 and M6. M5 is fixed (see m7).
- A plugin with an open High finding cannot be merged to `master` (CLAUDE.md §9).

### Major

**M1. A visible light band sits over the Arabic hero headline (prototype and theme).**
- **Where:** prototype `css/app.css:263-265`. The theme has the identical rules in `assets/css/home-critical.css:21-23`.
- **Cause:** the `.wy-hero__h1::after` sheen rests *inside* the clipped headline in RTL. Measured `transform: matrix(1,0,0,1,304.97,0)` on an H1 669 px wide at x = 711.
- **When it shows:** in the `lite` and `reduced` tiers. `lite` is the default for mid-range Android, our primary target. In `full`, the animation moves the band out of view (measured −813 px).
- **Evidence:** `/tmp/coach/shots/index-1440-ar-light-fold.png`, `index-390-ar-dark-fold.png`, `sheen-ar-6s.png`.
- **Worse in the theme:** the hero H1 in `patterns/home-hero.php` has no `data-wy-sheen`, so `is-sheen` is never added and the band would sit there **in every tier** on Arabic pages. This is inferred from the code; the theme was never rendered.

**M2. The theme hides the hero H1 (the LCP candidate) until JavaScript runs.**
- `patterns/home-hero.php:19` gives the H1 the class `wy-split-heading`.
- `inc/split.php:46-58` then wraps every word, and `assets/css/motion.css:11,15,17` sets them to `translateY(105%)` or `opacity:0` until IntersectionObserver adds `.is-in`.
- This breaks DESIGN §3.2 #2 ("the hero H1 is never hidden") and §3.3 rule 2 ("never delay LCP"), and puts the Lighthouse ≥ 90 gate at risk.
- **Fix:** remove `wy-split-heading` from the hero H1 and add `data-wy-sheen` after M1 is fixed.

**M3. 47 French strings in the theme have no accents, and the demo label is wrong.**
- Examples:
  - `inc/cards.php:15-18` badges: « Inspecte par Wotoyi », « Concessionnaire verifie », « Dedouane »
  - `inc/routes.php:105` and `block-search.php:52`: page title and H1 « Vehicules a vendre et a louer »
  - `block-vehicle.php:43-97`: « Annee », « Kilometrage », « Etat », « Identite », « Equipements »
  - `block-footer-columns.php:28-29`: « Confidentialite », « Mentions legales »
  - `block-footer-brand.php:6`: « du Senegal »
  - `inc/labels.php:20`: « Electrique »
- The demo badge reads `'Demo'` (`inc/cards.php:13`). The brief §6 requires « Démo ».
- The prototype strings are correctly accented, so this is a regression from M1 to M2.
- Visible on every listing card and every vehicle page. Not premium French.

**M4. Theme search and vehicle page are far below the brief and the prototype, and the README does not say so.**
- **Search form** (`block-search.php`): q, sort, offer type, make, city, price min/max, 5 facet groups (body, fuel, gearbox, condition, drive) and 5 flags. That is about 15 criteria.
- The brief §3.1 lists about 45, DESIGN §4.2 has 8 groups, and the plugin supports almost all of them (`README.md:76`).
- **Missing from the theme:** model (dependent), year, mileage, engine/power, consumption, range, seats/doors, colours, upholstery, origin, customs, owners, equipment, distance, delivery/financing/trade-in, posted since, video/360, saved search ("Enregistrer la recherche": 0 occurrences).
- **Vehicle page:** no lead form (deferred, `block-vehicle.php:4-5`) and no 360° viewer.
- **Fix:** expose the plugin's filters in the theme, or list each gap in the theme README as a known limit. Silence is not acceptable.

**M5. Every vehicle illustration is the same sedan, including the files named SUV and pickup.**
- `wotoyi/images/cars/{hero-suv,suv-amber,suv-pearl,suv-sage,pickup-atlantic}.svg` are the same 3-box sedan silhouette as `sedan-*.svg`, only recoloured. File sizes are 3,878–3,895 B.
- So the Toyota Land Cruiser Prado, RAV4, Mercedes GLC, Nissan X-Trail and Toyota Hilux all appear as sedans: on cards, on the vehicle page, and in the "4x4" hero.
- The illustrations are flat and cartoon-like. They do not meet the brief §2 standard of "premium vehicle renders (studio + three-quarter)" or the "one-million-dollar luxury" bar.
- DESIGN §6 does say these are placeholders, which is honest. But a body type that does not match the listing is misleading even in a demo.
- **Founder decision needed:** commissioned 3D renders, licensed studio photos, or AI-assisted renders with clear licensing. Meanwhile, draw at least distinct SUV and pickup silhouettes.

**M6. The AI button overlaps primary controls.**
- 1440 px FR: it covers the "Assistant" hint at the end of the natural-language field (`index-1440-fr-*-fold.png`).
- 1440 px AR: it overlaps the "بحث" (Rechercher) button (`index-1440-ar-light-fold.png`).
- 390 px: at the fold it covers part of the Acheter/Louer control (`index-390-ar-dark-fold.png`).
- **Fix:** hide the button while the hero quick search is visible (IntersectionObserver), or offset it.

**M7. Marketplace v2 scope is incomplete against the brief §3 and §7 for M2.**
- None of these exist yet: `wotoyi-ai` (§3.3 assistant, gateway and the rule-based parser server-side), `wotoyi-i18n`, `wotoyi-seo`, `wotoyi-builder`.
- `docs/STATUS.md` honestly lists them under "Next".
- M2 cannot be declared delivered without them, or without a recorded decision that moves them to a later milestone.

### Minor

- **m1.** The prototype footer shows « Véhicules **àDakar** » (missing space): `/tmp/coach/shots/c-footer.png`, key `footer.incity`.
- **m2.** On the Arabic vehicle page the subtitle comes out as "D-4D VX · 2021 2.8" (mixed-direction text not isolated). Wrap version, year and specs in `<bdi>`, as is already done for prices: `vehicle-390-ar-light.png`.
- **m3.** The selected "Auto" pill in the prototype controls is unreadable in dark mode (`c-proto-ctrl.png`). This only affects the prototype.
- **m4.** `prototype/_src/strings.py:3` and `prototype/js/motion.js:5` refer to a prototype README that does not exist. `wotoyi/THIRD-PARTY.md`, required by DESIGN §3.2, is absent. No third-party runtime ships today, so the impact is low, but Lenis is in the spec.
- **m5.** DESIGN.md §6 decisions are not logged in PROJECT §23: GSAP excluded, Sora retired from the marketplace, WhatsApp button colour. Only D-41 (digits) exists. This breaks CLAUDE.md §5.5.
- **m6.** DESIGN.md is still marked "PROPOSED, awaiting Coach + CTO review". There is no record of the CTO review that the brief §2 requires before theme work.
- **m7.** The dealer `bin/check.sh` piped the suites into `tail` without `pipefail`, so failing tests were hidden. Another agent fixed this during my review (file timestamp 16:34:57). I checked the fixed version with an injected failing test: exit 1, `CHECK FAILED`. **Closed.**
- **m8.** Marketplace security findings M6 and L2–L7 are still open (documented). The local transport must stay OFF in production until M6 is fixed.

---

## 3. Compliance (CLAUDE.md / PROJECT.md)

| Rule | Result |
|---|---|
| No « car » in French copy (PROJECT §8 / v0.5) | **Pass.** The only « car » hits are in English strings and code identifiers |
| Forbidden inspection wording (§10.6) | **Pass.** No "certified mileage", "guaranteed accident-free" or "100% verified". The mandatory disclaimer is present (prototype, theme `home-inspection` pattern) |
| No invented statistics or prices | **Pass in the prototype.** Every price and figure is labelled demo (top strip, « DÉMO » badges, the "La démo en chiffres" note). In the theme, the figures block is hidden when there is no data (`block-figures.php:7`). Inspection prices come from settings. **Theme demo badge spelling fails** (M3) |
| No copyrighted assets | **Pass, from what I could check.** Car illustrations are in-house SVGs. Fonts are OFL with licence files shipped. Brand names appear as text, not logos. No copying from the reference sites found |
| Wolof / Arabic flagged for native review | **Pass in the prototype.** Wolof is disabled with an explicit notice. EN/AR are labelled "demo translations, not natively reviewed" in the source. STATUS lists it as a founder blocker. **Not applicable to the theme**, which has no translations (B2) |
| Decisions logged the same day | **Fail** (m5) |
| Honest test claims | **Pass.** Both READMEs and SECURITY-REVIEW.md state plainly that results come from a SQLite shim and are not proven on real WordPress/MySQL. Test counts match my runs (215; 85 + 7) |

## 4. Design quality against the "luxury" bar

- **Strong:** the night hero with the aurora and the serif display headline; the sunset-disc logo; the editorial section rhythm with night and sand alternation; the glass quick-search card; the honest demo labelling; the trust-story section; the inspection report mock; the safety tips; the dark mode, which is cohesive. In my opinion the composition, typography and spacing are premium level.
- **Pulls it down:**
  - The vehicle visuals (M5) are the single biggest gap. A luxury automotive site lives or dies on its imagery.
  - The Arabic hero artefact (M1).
  - The FAB overlaps (M6).
  - The unaccented French in the theme (M3).

## 5. Not verified (and why)

- Real WordPress 6.6/6.7 and MySQL/MariaDB for the theme and both plugins: no runtime available.
- Lighthouse (performance, accessibility, SEO, best practices), Core Web Vitals and 60 fps traces: not run.
- Theme rendering in any browser: no WordPress. Every theme finding comes from code reading.
- Browsers other than headless Chromium (no Safari/iOS, Firefox or real Android device); 4× CPU throttling.
- The 820 px screenshots: checked by the automated DOM tests only; I did not inspect them visually one by one.
- Arabic language quality: I am not a native reviewer. Wolof: not delivered.
- Dealer H2 (erasure): I rely on the CISO's probe and did not reproduce it.
- Visual QA of the `full` tier: covers 12 runs (390 and 1440 px, FR-dark and AR-light), not the whole matrix.

## 6. To pass this gate

1. Run on real WordPress with the brief §6 matrix (B1). Ship `wotoyi-i18n` with FR/EN/AR (Wolof behind a flag) (B2). Fix dealer H1 and H2 with regression tests (B3).
2. Fix M1–M6. For M7, either deliver the missing plugins or log a §23 decision that moves them to a later milestone.
3. Freeze the tree and give the Coach a commit hash for the re-review.

**[Coach]**
