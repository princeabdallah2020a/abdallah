# Wotoyi Design System v2 — "Luxe Motion"

> Owner: Lead UX/UI & Brand Architect · Status: **PROPOSED, awaiting Coach + CTO review** (DEV_BRIEF_V2 §2) · 2026-11-17
> Implements: DEV_BRIEF_V2 §2–§3, PROJECT.md §16. Tokens: `tokens.json` (same folder). Contrast proof: `palette.json` + `contrast_ext.py`.
> Audience: Sonnet frontend engineer. Everything here is buildable as written; anything marked **[VERIFY]** must be checked during build, not assumed.

---

## 1. Direction

### 1.1 Mood
Night showroom at golden hour in Dakar. Three ideas, in this order of importance:
1. **The vehicle is the hero.** Large studio renders/photos, generous negative space, nothing competes with the car.
2. **Cinematic contrast.** Public pages alternate porcelain/sand sections with full-bleed *night* sections (hero, storytelling, footer) where the sunset gradient and the aurora glow live.
3. **Quiet luxury in the details.** Editorial serif headlines, 1px chrome hairlines, glass only where it carries meaning (header, hero search, sticky bars), slow confident easing. No neon, no busy patterns, no stock "Africa" clichés.

Three identities on shared tokens (§16.2): **Marketplace** = cinematic, full motion. **Wotoyi Pro** = dense, calm, motion limited to micro/UI/page. **Dealer sites** = one personality per template (Prestige spec in §4.5).

### 1.2 Logo refinement — "Horizon"
- **Wordmark:** `wotoyi`, lowercase, drawn from Manrope 700 at tracking −0.02em, then converted to outlines (no font dependency). Terminals of `t` and `y` cut horizontally to echo the horizon line.
- **Signature:** the **second `o`** (between `t` and `y`) is replaced by the **sunset disc**: a circle of the same optical size as `o`, filled with `gradient.sunset` (top `sunset-1` → bottom `sunset-3`), cut by a **horizon gap** — a horizontal 3/40 of the diameter transparent band at 62 % of its height (sun setting on the Atlantic). Lower segment at 70 % opacity.
- **SVG spec:** `viewBox="0 0 240 64"`, letters `fill="currentColor"`, disc gradient via `<linearGradient id="wy-sun">` whose stops read `var(--wy-color-sunset-1|3)`; horizon gap made with a `<mask>` (white circle, black rect). `role="img"` + `<title>Wotoyi</title>`.
- **Modes:** letters = `color.contrast` (ink on light, porcelain on dark) — one inline SVG, no file swap needed. In light mode the disc uses stops `sunset-2 → sunset-text-1` for edge definition on porcelain.
- **Monogram / favicon / PWA icon:** the sunset disc with horizon gap, centred on an ink rounded square (`#06080F`, radius 22 % of size). Sizes: 32, 180, 192, 512 + maskable 512 (disc at 60 % safe zone).
- **Clear space:** height of the disc on every side. **Minimum size:** wordmark 88 px wide; below that use the monogram.
- **Theme toggle "logo swap":** handled by the theme View Transition (§3, #18); the circular reveal sweeps across the header, so the logo visibly changes colour inside the reveal.
- Final logo still subject to D-01 checks (domain + OAPI trademark search) before print/launch.

### 1.3 Typography (all SIL OFL 1.1, self-hosted, WOFF2)

| Role | Latin (FR / WO / EN) | Arabic (AR) |
|---|---|---|
| Display (≥ 32 px) | **Instrument Serif** 400 + Italic | **Noto Kufi Arabic** variable 600–700 |
| UI / body / prices | **Manrope** variable 200–800 (already in `wotoyi/fonts/`) | **IBM Plex Sans Arabic** 400 / 500 / 600 |

**Licence note (truthful):** OFL is not a GPL licence, but fonts are separate works bundled with the theme; WordPress.org theme rules accept OFL fonts. Ship each `OFL-*.txt` next to the files (already done for Manrope/Sora).

**Glyph coverage — checked 2026-11-17 with fontTools:**
- Instrument Serif: covers ë ñ ŋ Ŋ à é è ç ô û € → Wolof OK.
- Manrope: `latin` subset lacks ŋ/Ŋ, `latin-ext` subset has them → **both subsets must be declared** (unicode-range), as today.
- IBM Plex Sans Arabic lacks ŋ → Arabic @font-face **must carry an Arabic-only `unicode-range`** (`U+0600-06FF, U+0750-077F, U+0870-08FF, U+FB50-FDFF, U+FE70-FEFF`) so Latin and Wolof text inside Arabic pages fall through to Manrope.
- Sora: retired from the marketplace; kept in the repo for the dealer "Urban" template.

**Files to add** (subset with `pyftsubset --flavor=woff2 --layout-features='*'`): `instrument-serif-{latin,latin-ext}-{normal,italic}.woff2`, `noto-kufi-arabic-arabic-wght.woff2`, `ibm-plex-sans-arabic-arabic-{400,500,600}.woff2` + `OFL-InstrumentSerif.txt`, `OFL-NotoKufiArabic.txt`, `OFL-IBMPlexSansArabic.txt`. Sources: github.com/google/fonts `ofl/` folders.

**Rules**
- Scale in `tokens.json → font.size` (fluid `clamp`). Display font only for display sizes; everything ≤ 28 px is Manrope.
- Max **one** emphasised word per headline: Instrument Serif *italic* + `gradient.sunset-text` (light) / `gradient.sunset` (dark & night). Arabic: no italic, no letter-spacing, no uppercase; emphasis = colour only; line-height +0.15.
- Prices, mileages, counters: Manrope 800, `font-variant-numeric: tabular-nums`. Format with `Intl.NumberFormat(locale, {style:'currency', currency:'XOF', maximumFractionDigits:0})`. Arabic UI keeps Western digits (0-9) for prices/specs (consistency with Senegal market) — **decision to log in PROJECT §23**.
- Loading: preload only `manrope-latin` and `instrument-serif-latin-normal` (the hero H1). `font-display: swap` + metric-matched fallback faces (`size-adjust`, `ascent-override`…) **computed with a tool (e.g. fontaine/capsize), not guessed** → CLS ≤ 0.1. Arabic fonts load only on `lang="ar"` pages.
- French copy never uses « car » (rule from §6 of the brief).

### 1.4 Spacing, layout, radius
- 4 px base scale (`space.*`). Section rhythm `space.section` (64–160 px). Gutters `space.gutter`. Containers 1320 / wide 1600; running text max 68ch.
- Grid: 4 cols < 768, 8 cols 768–1199, 12 cols ≥ 1200; gap 16 / 24 / 32.
- Radius: badges `xs`, inputs `sm`, menus `md`, cards & panels `lg`, hero search / sheets / media `xl`, public buttons & chips `pill`. Pro uses `sm` buttons (denser).
- Touch targets ≥ 44 × 44 px everywhere, including chips and swatches (pad the hit area).

### 1.5 Elevation & glass
- Light mode: warm-tinted shadows (`elevation.light.*`). Dark mode: depth = darker shadow **plus** a 1px inset top highlight (chrome). Never animate `box-shadow`: put the hover shadow on a `::after` and animate its `opacity`.
- Levels: 1 = resting card, 2 = hover card / dropdown, 3 = sticky bars / sheets, 4 = modals. `glow` only on the primary CTA hover.
- **Glass** (`glass.*`): `backdrop-filter: var(--wy-glass-blur)` on max **3 surfaces visible at once**: header, hero search card, sticky CTA bar (+ bottom sheet header). Never on cards in scrolling lists. Over photos/video use the `-media` (0.88) or `night` (0.78) recipe — these are the only opacities that passed worst-case contrast (§2). Fallback: `@supports not (backdrop-filter: blur(1px))` or `data-motion="lite"` → opaque `fallback` colour.

### 1.6 Theme mechanics
`<html data-theme="light|dark" data-theme-pref="auto|light|dark" data-motion="full|lite|reduced">`. A ≤ 1 kB inline script in `<head>` (before CSS) reads `localStorage`, `prefers-color-scheme`, `prefers-reduced-motion`, Save-Data and device class, then sets the three attributes → no flash. `color-scheme: light dark` set accordingly. All colours come from `--wy-color-*`; zero hard-coded colours in components.

---

## 2. Colour tokens & contrast proof

Full values in `tokens.json → color.light / color.dark`. Summary:

| Token | Light | Dark | Use |
|---|---|---|---|
| base | `#F7F4EE` porcelain | `#07090F` ink | page |
| surface / -2 / -3 | `#FFFFFF` / `#EFE8DC` sand / `#E5DBCB` | `#10141D` graphite / `#171C28` / `#202636` | cards / panels / chips |
| contrast · muted | `#0C111C` · `#4A5263` | `#F2EFE9` · `#A4ABBA` | text |
| border · border-strong | `#E2D9CB` · `#857B6E` | `#252B3A` · `#6F7789` | decorative · component edges |
| accent (+accent-ink `#0C111C`) | `#F2A93B` | `#FFB547` | gold fills |
| accent-text · accent-graphic | `#94480A` · `#B8700F` | `#FFC968` · `#FFB547` | links · accent icons/lines |
| sunset 1→2→3 | `#F7C964 → #F2A93B → #EA8A3A` | `#FFD47E → #FFB547 → #F5953F` | gradient fills |
| sunset-text 1→2 | `#A85A0C → #8A3F06` | `#FFD47E → #F5953F` | gradient text |
| secondary (+ink) | `#0A6670` (+`#FFFFFF`) | `#3CCFC0` (+`#06221F`) | teal |
| success · warning · danger | `#146B3F` · `#8A5200` · `#B3261E` | `#4ADE80` · `#FBBF24` · `#FF8A80` | states |
| focus | `#0A6670` | `#FFC968` | 2px ring, 2px offset |
| night · on-night · on-night-muted | `#06080F` · `#F5F2EC` · `#AFB5C3` | `#030509` · same | cinematic sections |

**Validation run 2026-11-17 (real output, both exit 0):**
- `python3 -I scripts/contrast.py wotoyi/design/palette.json` → 32 pairs, **0 failures**. Lowest: border-strong on surface 4.10 (dark) / 4.15 (light), min 3.0; lowest text pair accent-text on base light 6.00.
- `python3 -I wotoyi/design/contrast_ext.py wotoyi/design/palette.json` → 66 pairs (surface-3, all gradient stops with accent-ink, gradient text, status colours, focus, glass composites over worst-case black/white photos), **0 failures**. Lowest: light border-strong on surface-2 3.41 (min 3.0), light accent-graphic on base 3.57 (min 3.0), light sunset-text-1 on base 4.63 (min 4.5), on-night-muted on night-glass over white photo 4.68 (min 4.5).
- `scripts/contrast.py` has a hard-coded pair list, so the extra pairs live in `contrast_ext.py`; CI must run **both**.

**Rules that follow from the numbers**
1. Light `accent` on porcelain is **1.82:1** → gold is a *fill only* in light mode (with ink text). Accent icons/lines on light backgrounds use `accent-graphic`; accent text uses `accent-text`.
2. Gradient text in light mode uses `sunset-text`, never `sunset`.
3. Brand WhatsApp green `#25D366` fails with white text → WhatsApp buttons use `color.whatsapp` (`#146B3F`, white text 6.55:1) with the official glyph in white.
4. `border` is decorative; any input, checkbox, toggle track or card that needs a visible edge uses `border-strong`.
5. Text on photos only through `gradient.scrim-bottom` or a `glass.*-media/night` panel.
6. Dealer-chosen accent colours (Prestige etc.) are run through the same validator at save time; if a pair fails, the system derives `accent-text`/`accent-graphic` by darkening/lightening in OKLCH until it passes, and blocks publish otherwise (§16.9).

---

## 3. Motion system

### 3.1 Tiers (set by the head script)
- `full` — desktop or capable phone, no reduced-motion.
- `lite` — `navigator.hardwareConcurrency <= 4` **or** `navigator.deviceMemory <= 2` **or** Save-Data **or** `effectiveType` in `slow-2g|2g`. Mid-range Android (our primary target) usually lands in `full` or `lite`; **design every page so `lite` still looks premium**.
- `reduced` — `prefers-reduced-motion: reduce`. Only opacity fades ≤ 160 ms and instant state changes; no movement, no parallax, no autoplay.
- Pro PWA forces at most `lite`-level ambient effects (no aurora/parallax/tilt/cursor) regardless of device.

### 3.2 Signature animations

Libraries: **Lenis** (MIT, darkroomengineering/lenis) is the only third-party runtime on public pages; **uPlot** (MIT) only in Pro Reports. Everything else is in-house vanilla JS/CSS. **GSAP is excluded**: its licence is not an OSI/GPL-compatible licence. Record licences in `wotoyi/THIRD-PARTY.md` at build time **[VERIFY licence text at install]**.

| # | Animation | Technique | Timing / easing | `lite` | `reduced` |
|---|---|---|---|---|---|
| 1 | **Aurora hero** | 3 absolutely-positioned layers with `gradient.aurora` (pre-blurred radial gradients, **no `filter: blur`**), CSS keyframes on `transform: translate3d() rotate() scale()`; paused via IntersectionObserver (`animation-play-state`) when hero leaves viewport | 32 s loop, `linear`, alternate | static gradient | static |
| 2 | **Split-text headlines** | Words split **server-side** in the block's PHP render (`<span class="wy-split__w"><span>word</span></span>`), never letters (breaks Arabic shaping; word-only for all languages). Inner span `translateY(105%) → 0` inside `overflow: clip`. **Below-the-fold headlines only**; the hero H1 (LCP candidate) is never hidden — it gets #2b | 640 ms `out`, stagger 40 ms, cap 320 ms | fade only | none |
| 2b | **Hero H1 sheen** | `::after` gold-white highlight band translated across once (`translateX(-120% → 120%)`) with `mix-blend-mode: soft-light`; starts 300 ms after `load` | 1200 ms `in-out`, once | none | none |
| 3 | **Scroll reveals** | `@supports (animation-timeline: view())`: `animation: wy-rise linear both; animation-timeline: view(); animation-range: entry 0% entry 40%`. Else IntersectionObserver (threshold 0.15, unobserve after) toggles `.is-in`. Hidden state applied **only** under `html.wy-js` so no-JS shows content | `opacity 0→1`, `translateY(24px→0)`; IO path 640 ms `out` | IO path, 16px | opacity 160 ms |
| 4 | **Pinned storytelling** | Section height `n × 100svh`, inner `position: sticky; top: 0`. Named `view-timeline` drives step crossfades (opacity) and the vehicle visual (`transform`). Fallback: IO on each step sets `data-step` on the sticky stage | scroll-linked | **unpinned**, stacked steps with #3 | stacked, static |
| 5 | **Parallax depth** | Scroll-driven `animation-timeline: scroll()`/`view()` on 2–3 layers (backdrop, vehicle, foreground light); `translateY` ≤ 12 %; no scroll listeners | scroll-linked | off | off |
| 6 | **Horizontal galleries** | Native `overflow-x: auto; scroll-snap-type: x mandatory` (touch + trackpad), prev/next buttons using `scrollBy` with `behavior: smooth`; drag-to-scroll with pointer events on `(pointer: fine)`. One desktop showcase may be pinned + scroll-driven `translateX` | snap native | native only | native, `behavior: auto` |
| 7 | **3D tilt + light sheen (vehicle cards)** | Attach lazily on first `pointerenter`; rAF-throttled `pointermove` writes `--rx --ry --sx --sy`; card `transform: perspective(900px) rotateX() rotateY()` ≤ 6°; sheen = `::after` with a fixed radial gradient moved by `translate()`. Only `(hover: hover) and (pointer: fine)` | follow: 160 ms `out`; reset 420 ms `out` | off | off |
| 8 | **Magnetic buttons** | Primary CTAs only (`data-motion="magnetic"`); pointer offset within 80 px radius → `translate` ≤ 6 px; label moves 50 % more | 280 ms `out` | off | off |
| 9 | **Custom cursor** | 8 px dot + 36 px ring (`position: fixed`, `transform` only, lerp 0.18 in rAF). **System cursor stays visible** (adornment only). Grows + shows label "Voir" over `.wy-vcard`. Not on forms, not in Pro, not on touch | ring lerp; grow 280 ms `out` | off | off |
| 10 | **Page & shared-element transitions** | Marketplace (MPA): `@view-transition { navigation: auto; }` + `view-transition-name: wy-v-{id}` on card media and vehicle-page main image; root crossfade. Pro (SPA): `document.startViewTransition()` around route changes. Feature-detected; no polyfill | 420 ms `in-out` | root crossfade only | `::view-transition-group(*) { animation: none }` |
| 11 | **Animated counters** | Final value **server-rendered** in HTML (SEO, no CLS, no fake numbers). If below the fold at load, IO resets display to 0 and counts up via rAF; `tabular-nums`, fixed `min-inline-size`; `aria-hidden` on the animated copy, real value in visually-hidden text | 1200 ms `out` | instant | instant |
| 12 | **Animated charts (Pro, home dashboards)** | SVG bars `scaleY(0→1)` (`transform-origin: bottom`); lines revealed by a mask `<rect>` `scaleX(0→1)`; uPlot only for long time series in Reports, `animation` disabled there | 640 ms `out`, stagger 40 ms | instant | instant |
| 13 | **Make marquee** | Two copies of the list (second `aria-hidden="true"`), CSS `translateX(0 → -50%)`; pause on `:hover` / `:focus-within`; direction reversed under `:dir(rtl)`. **Make names as typography, not manufacturer logos** (trademark risk — Legal to clear before any logo use) | 48 s `linear` infinite | slower 72 s | static wrapped list |
| 14 | **Hover video preview** | Only listings with a video. `(hover: hover)` + 300 ms hover intent → create `<video muted playsinline loop preload="none">` over the image, fade in on `playing`; removed on leave. Never on touch, `lite` or Save-Data | fade 280 ms | off | off |
| 15 | **Skeleton shimmer** | Real loading states only (search refetch, Pro lists). Block bg `surface-3`; `::after` gradient band `translateX(-100% → 100%)` | 1.4 s `linear` infinite | same | static `surface-3` |
| 16 | **Micro-interactions** | Press `scale(0.97)` 80 ms; favourite heart: `scale 0.8→1.15→1` + 6 particle spans (transform/opacity) ; switch thumb `translateX`; accordion chevron `rotate`; invalid field shake `translateX` ±4 px ×3 (300 ms) + message; live result count rolls digits (`translateY`). Focus ring appears **instantly** | `micro` 160 ms `out` | same | opacity only |
| 17 | **360° viewer** | In-house `wy-viewer360`: image sequence (24 or 36 frames, uploaded via the Pro photo wizard); poster = frame 0 (eager, `fetchpriority` only if it is the LCP image); remaining frames load on first interaction or when visible + `requestIdleCallback`; decode with `img.decode()`; pointer drag with inertia (velocity × 0.92 decay); keyboard ←/→; buttons "Tourner", "Plein écran"; progress ring while loading; no autoplay spin | drag 1 frame / 8 px | 12-frame subset | no inertia, no autoplay |
| 18 | **Theme toggle** | `document.startViewTransition()` then animate `::view-transition-new(root)` `clip-path: circle(0 at x y) → circle(150% at x y)` from the toggle button (one-off; accepted exception to transform/opacity rule). Toggle icon: sun/moon morph via `rotate` + `scale` | 420 ms `in-out` | instant swap | instant swap |
| 19 | **Smooth inertial scroll** | Lenis, loaded on `requestIdleCallback` after `load`, only `(pointer: fine)`, `full` tier, public pages. Disabled inside sheets/modals/filter panel (`data-lenis-prevent`). Native scroll everywhere else | lerp 0.1 | off | off |
| 20 | **Bottom sheet physics** | Pointer drag on handle/header, `translateY`; snap to 50 % / 92 % / closed by velocity; scrim opacity follows | 280 ms `in-out` | same | instant snap |

### 3.3 Performance rules (release gate)
1. Animate only `transform` and `opacity` (exceptions: #18 clip-path once; #12 SVG mask transform). Never animate layout, `filter`, `box-shadow`, `background-position`.
2. **Never delay LCP**: the LCP element (hero image/H1 or vehicle main image) is visible in the first paint, never `opacity: 0`, never lazy, has `fetchpriority="high"`; no JS needed to show it.
3. Motion JS = ES modules per block (`viewScriptModule`), imported on visibility or interaction; nothing render-blocking. Own motion code budget: **≤ 8 kB gzipped per page** (target, set by Design; QA measures).
4. `will-change` only while an animation runs; removed after.
5. One rAF loop shared (cursor, tilt, magnetic); reads before writes; passive listeners.
6. Below-the-fold sections: `content-visibility: auto` + `contain-intrinsic-size`. Not on sections that use pinning (#4).
7. Pause everything off-screen (IO) and when `document.hidden`.
8. 60 fps verified with a performance trace on desktop and 4× CPU-throttled mobile (brief §6).

---

## 4. Page specs

Shared chrome for public Marketplace pages:
- **`wy-header`** (glass, sticky, 72/64 px; transparent over hero then glass after 24 px scroll via IO sentinel): logo · nav *Acheter, Louer, Services, Concessionnaires, Inspection* · `wy-lang-switch` (FR / WO / EN / AR — Marketplace only) · `wy-theme-toggle` (Clair / Sombre / Auto) · favourites · account · CTA "Espace pro".
- **Phone:** header = logo, search icon, menu; **`wy-bottom-nav`** 4 items: Accueil, Rechercher, Favoris, Compte; **`wy-ai-fab`** above it (inline-end).
- **`wy-footer`** (night): brand + short mission, columns (Acheter: by body type / by make / by city; Louer; Services; Concessionnaires: join, Wotoyi Pro, login; Aide: inspection, safety tips, contact; Légal: CGU, confidentialité, mentions), language switcher, theme toggle, social links (only accounts that exist), © year.
- **`wy-ai-panel`**: assistant drawer (400 px desktop / full-height sheet phone), example prompts, honest notice when no API key ("Recherche simple activée — l'assistant IA complet n'est pas configuré").

### 4.1 Home (Marketplace)
1. **Hero** (night + aurora #1, parallax #5): overline "Marketplace automobile · Sénégal"; H1 display-xl (e.g. "Le showroom automobile du Sénégal, *en ligne*." — copy final by CMO); subline; **`wy-quicksearch`** (glass night, radius xl): segmented *Acheter / Louer*, make combobox, model (dependent), budget max, city, primary button "Rechercher" + live count "**n** véhicules" (real); below it a natural-language field "Décrivez le véhicule recherché…" that opens the AI panel. Hero vehicle render (`hero-suv` until premium renders exist) with depth layers.
2. **Body-type shortcuts**: icon chips SUV, Berline, Citadine, 4x4, Pick-up, Monospace, Utilitaire, Électrique/Hybride → filtered search. Count per type shown only if > 0.
3. **Featured listings** (`wy-hscroll` #6, cards #7): paid featured vehicles, each labelled "Sponsorisé"; heading + "Tout voir". Hidden if none.
4. **Make marquee** (#13): makes that actually have listings, each a link to the filtered search.
5. **Trust story** (night, pinned #4), 3 steps: *Concessionnaires vérifiés* (what verification means) → *Inspection Wotoyi* (independent paid inspection, report) → *Contact direct WhatsApp*. Visual: vehicle with badges appearing per step.
6. **Rental strip**: vehicles for rent with day/month price; CTA "Toutes les locations". Hidden if none.
7. **Services** grid: service listings (maintenance, location avec chauffeur, import, detailing…) from the Services content type; hidden if none.
8. **Inspection block**: what is checked, how ordering works, packages pulled from settings (prices only from admin settings, never hard-coded); CTA "Commander une inspection". Wording per PROJECT §10.6.
9. **Verified dealers** carousel: logo, name, city, listings count (real). Hidden if < 3 dealers.
10. **AI showcase**: 4 example prompts as chips (one per language — WO and AR strings pending native review), click = opens AI panel prefilled; capability list (search, compare, explain specs, WhatsApp handoff).
11. **Platform figures** (#11): listings, verified dealers, cities — real DB counts only; whole block hidden if any value is 0 (no invented statistics).
12. **Dealer CTA** (sand section): "Vous êtes concessionnaire ?" + 3 benefits (own website, automatic publication on Wotoyi, CRM + AI) + Wotoyi Pro screenshot + CTA.
13. **Recently viewed** (localStorage): hidden if empty.
14. Footer.

### 4.2 Search results
**Top bar (sticky under header):** keyword/NL field with AI icon · "Filtres (n)" button (phone/tablet) · sort select (Pertinence, Prix ↑, Prix ↓, Année récente, Kilométrage ↓, Plus récentes, Plus vues) · view toggle grid/list · "Enregistrer la recherche".
**Below:** `wy-active-filters` chips (each removable, "Tout effacer") · `wy-result-bar`: "**n** résultats" (`aria-live="polite"`, debounced 300 ms) + saved state.
**Layout:** ≥ 1200 px: filter panel 320 px sticky (inline-start, own scroll, `data-lenis-prevent`) + 3-col grid (list view: 1 col horizontal cards). 768–1199: grid 2 col, filters in a 400 px side drawer. < 768: 1-col cards, filters in bottom sheet.

**Filter panel (`wy-filter-panel`)** — accordion groups; header of each group shows its active count; fields appear only when relevant (rental fields when *Louer*, electric range when fuel includes electric/PHEV):
1. **Essentiel** (open): offer type segmented (Achat / Location / Les deux) · make (searchable combobox) · model (dependent) · version/finition · body type (icon chips) · price min/max (dual range + two inputs, FCFA) · monthly price + rental duration (rental) · year min/max · mileage min/max · city/area · distance from me (asks geolocation only on click; radius chips 10/25/50/100 km).
2. **Motorisation**: fuel chips (Essence, Diesel, Hybride, Hybride rechargeable, Électrique, GPL) · transmission · drive (Traction, Propulsion, 4x4, Intégrale) · engine size range · power range (ch) · consumption max · electric range min.
3. **Carrosserie & intérieur**: seats · doors · exterior colour swatches (with names) · interior colour · upholstery.
4. **État & historique**: condition (Neuf / Occasion / Venant) · origin country · customs status (Dédouané / En transit) · first-registration country · number of owners · accident-free declared · service history · warranty.
5. **Confiance & vendeur**: inspected by Wotoyi · verified dealer · specific dealer (combobox).
6. **Conditions commerciales**: delivery · financing possible · trade-in accepted.
7. **Équipements**: searchable multi-select checklist grouped (Confort, Sécurité, Multimédia, Extérieur…): AC, GPS, caméra, capteurs, toit ouvrant, cuir, régulateur, Bluetooth/CarPlay, keyless, jantes alliage, 7 places…
8. **Médias & date**: with photos / video / 360° · posted since (24 h, 7 j, 30 j).
Panel footer (sticky): "Effacer" + (drawer/sheet only) primary "Voir **n** résultats".

**Mobile bottom sheet (`wy-sheet`, #20):** opens at 92 %; handle + header (Filtres · Effacer · close ×); the same accordion groups; sticky footer "Voir **n** résultats"; focus trapped; Esc / back button closes (history state pushed on open); scrim tap closes; body scroll locked.
**State:** every filter in the URL query (shareable, crawlable canonical rules by SEO agent); saved searches in the account.
**Results:** `wy-vcard` grid; sponsored cards labelled; numbered server-rendered pagination + "Charger plus" enhancement; skeletons (#15) during refetch.
**Empty state (`wy-empty`):** illustration, "Aucun véhicule ne correspond à ces critères", one-click removal of the most restrictive filters (chips), "Enregistrer la recherche pour être alerté", "Demander à l'assistant".
**SEO intro/outro** for make/city landing URLs (original copy by SEO agent); hidden when none.

### 4.3 Vehicle page
1. **Breadcrumb** (Accueil › Make › Model › title).
2. **Media stage** (`wy-gallery`): main image = LCP + shared-element target (#10); thumbnail rail; counter "3 / 18"; tabs *Photos / Vidéo / 360°* (only available ones); fullscreen `wy-lightbox` with pinch-zoom & swipe; `wy-viewer360` (#17). Branded placeholder if no photo.
3. **Title block**: make model version (display-m), year · badges (Concessionnaire vérifié, Inspecté par Wotoyi, Sponsorisé, Venant / Dédouané, Démo when demo) · `wy-price` (sale and/or rental day/week/month, "Négociable") · key-facts strip with icons: année, kilométrage, carburant, boîte, transmission, ville.
4. **Contact column / bar (`wy-cta-bar`)** — desktop: sticky right card (inline-end): price, dealer mini-card, **WhatsApp** (primary, prefilled message with listing ref), Appeler, Message (lead form), "Demander une inspection", "Demander à l'IA", favourite, share, compare. Phone: fixed bottom glass bar appearing once the title block leaves the viewport: WhatsApp (wide), call icon, AI icon; respects `env(safe-area-inset-bottom)`; bottom-nav hidden on this page.
5. **Spec tabs (`wy-tabs`)**, sticky tab bar, horizontally scrollable on phone, deep-linkable (`#specs-moteur`); all panels server-rendered (SEO); **empty groups hidden**: *Aperçu* (identity: make, model, version, year, first registration, body, colours, condition, origin, customs) · *Moteur & performances* · *Consommation & environnement* · *Dimensions & capacité* · *Équipements* (by category, check-icon lists) · *Historique & documents* · *Conditions commerciales* (prices, deposit, financing, trade-in, delivery, availability, location) · *Confiance* (verification, inspection report, real view count). VIN and plate never public.
6. **Description**: dealer text; "Traduit automatiquement" label + "Voir l'original" when AI-translated.
7. **Inspection block**: if inspected → badge, date, summary, PDF report; else → explanation + packages + CTA.
8. **Dealer block**: logo, name, verified, city/area, opening hours with "Ouvert maintenant" state, number of vehicles, "Voir le concessionnaire", itinerary link (opens maps URL; no embedded map key needed).
9. **Similar vehicles** (`wy-hscroll`), same body/price band; hidden if none.
10. **Safety tips**: never pay before seeing the vehicle and its papers; prefer inspection; meet at the dealer. Link "Signaler l'annonce".

### 4.4 Dealer page (Marketplace profile)
1. **Cover**: dealer banner or night/aurora fallback; logo; name (display-m); verified badge; city; opening hours + open-now; buttons WhatsApp, Appeler, "Site web" (if any).
2. **Figures strip**: vehicles for sale, for rent, services — real counts; zero items hidden.
3. **Tabs**: *Véhicules* (mini filter bar: offer type, body, price; grid of `wy-vcard`) · *Location* · *Services* · *À propos* (description, address + itinerary link, hours table, team if provided, languages spoken). Empty tabs hidden.
4. **Trust**: verification explanation; vehicles inspected count (real; hidden if 0).
5. **Contact form** (lead → dealer CRM) with WhatsApp alternative.
6. **Other dealers in the same city** (hidden if none).

### 4.5 Dealer-site template "Prestige"
Personality: night-dominant, Instrument Serif at large sizes, gold hairlines, slower motion (reveal 800 ms, no marquee, no cursor), lots of air. Dealer can change: logo (light + dark), accent colour (validated, §2 rule 6), font pair from a curated list (Instrument Serif + Manrope default; Fraunces + Manrope alternate), default mode, section order/visibility. No language switcher.
1. **Header**: dealer logo, nav (Stock, Location, Services, À propos, Contact), WhatsApp button, theme toggle.
2. **Hero**: full-bleed featured vehicle (dealer-chosen) or the dealer's showroom photo; dealer slogan (display-xl); CTAs "Voir le stock" / "Prendre rendez-vous"; if dealer verified on Wotoyi: discreet badge.
3. **Spotlight** (pinned #4 on desktop): one featured vehicle, 3 key specs revealed per scroll step, price, CTA.
4. **Stock**: latest 6 vehicles (`wy-vcard--prestige`: dark card, chrome hairline) + "Tout le stock" page with the full filter set (reused component).
5. **Location** (hidden if no rentals): rental cards with day/week/month prices.
6. **Services** (hidden if none).
7. **Pourquoi nous**: 3–4 dealer-written value points (demo content clearly marked "Démo — à remplacer").
8. **Équipe** (hidden if empty): photo, name, role, WhatsApp.
9. **Showroom**: address, itinerary link, hours with open-now, photo.
10. **Rendez-vous / contact form**: name, phone, vehicle of interest (prefilled when coming from a vehicle), preferred date; stored in the lead outbox when central systems are down.
11. **AI visitor assistant** widget (inline-end, above WhatsApp).
12. **Footer**: dealer legal info, social links, "Espace gestion" link to `/gestion`, "Propulsé par Wotoyi".
Vehicle page on the dealer site = §4.3 minus marketplace badges/sponsoring, plus dealer branding.

### 4.6 Wotoyi Pro — dashboard shell
- **Desktop ≥ 1200**: `wy-sidebar` 264 px (collapsible to 72 px rail): dealer logo + "Wotoyi Pro"; nav *Accueil, Prospects, Clients, Véhicules, Services, Locations, Réservations, Ventes, Tâches, Rapports, Site web, Équipe, Paramètres*; bottom: `wy-sync-status` (Marketplace + site: last publication, errors), Aide, account. **Top bar**: global search / command palette `wy-cmdk` (Ctrl/⌘ K), "+ Ajouter" menu (véhicule, service, prospect, tâche, location), notifications, AI toggle, theme toggle, profile. **AI panel** = right drawer 400 px; every action shows a confirmation card before execution.
- **Tablet 768–1199**: 72 px rail + two-pane list/detail.
- **Phone**: compact top bar (small logo, search, AI, notifications); `wy-bottom-nav` *Accueil, Prospects, Véhicules, Tâches, Plus*; FAB "+" (add vehicle, ≤ 2 taps); `wy-offline` banner when offline.
- **Home "Aujourd'hui"**: KPI tiles (`wy-kpi`): new leads today, tasks due, vehicles to update, rentals starting / ending today — each clickable to the filtered list; **Leads to treat** list with WhatsApp / call quick actions and source tag; **Tasks due** with check-off; **Rentals timeline** (today + 7 days); **Publication status** (last synced vehicles, failures with retry); charts: leads by source (30 days, bars #12), sales/rentals per month. Teaching empty states for a new dealer ("Ajoutez votre premier véhicule" + button).
- Pro density: body-s (14 px) in tables, body (16 px) in forms; radius `sm`; motion = micro, UI, page transitions (#10), charts (#12), skeletons only.

---

## 5. Components & naming

**Convention:** prefix `wy-`, BEM-like `wy-block__element--modifier`; states `is-open | is-active | is-loading | is-in | is-disabled`; JS hooks `data-wy-*` (never styled); motion opt-in `data-motion="reveal|split|tilt|magnetic|parallax|counter"`. CSS custom properties `--wy-<token path>`. Styles use **logical properties only**: `margin-inline-start`, `padding-block`, `inset-inline-end`, `border-start-start-radius`, `text-align: start`, `inline-size`/`block-size`. Directional icons (arrows, chevrons, back) flip with `:dir(rtl) { scale: -1 1; }`; logos, play, check, media icons never flip. Galleries, marquee and sheets mirror direction under RTL. Icons = inline SVG `currentColor`, 24 px grid, 1.75 px stroke.

| Group | Components |
|---|---|
| Foundations | `wy-container`, `wy-grid`, `wy-section` (`--night`, `--sand`), `wy-stack`, `wy-visually-hidden` |
| Brand & chrome | `wy-logo`, `wy-header`, `wy-nav`, `wy-lang-switch`, `wy-theme-toggle`, `wy-bottom-nav`, `wy-footer`, `wy-breadcrumb` |
| Actions | `wy-btn` (`--primary` sunset, `--secondary` teal, `--ghost`, `--whatsapp`, `--icon`, `--sm`, `--lg`, `--magnetic`), `wy-link`, `wy-fab`, `wy-share` |
| Inputs | `wy-field`, `wy-input`, `wy-select`, `wy-combobox`, `wy-range` (dual), `wy-segmented`, `wy-checkbox`, `wy-radio`, `wy-switch`, `wy-chip` (`--filter`, `--removable`), `wy-swatch`, `wy-search` (`--nl` AI) |
| Display | `wy-badge` (`--verified`, `--inspected`, `--sponsored`, `--demo`, `--venant`, `--cleared`), `wy-price`, `wy-keyfacts`, `wy-spec-table`, `wy-equip-list`, `wy-kpi`, `wy-counter`, `wy-chart`, `wy-table`, `wy-timeline`, `wy-hours` |
| Cards | `wy-vcard` (`--grid`, `--list`, `--compact`, `--prestige`), `wy-dealer-card`, `wy-service-card`, `wy-step-card`, `wy-team-card` |
| Media | `wy-gallery`, `wy-lightbox`, `wy-viewer360`, `wy-video`, `wy-placeholder` |
| Containers | `wy-accordion`, `wy-tabs`, `wy-sheet`, `wy-drawer`, `wy-modal`, `wy-popover`, `wy-tooltip`, `wy-cmdk`, `wy-hscroll` |
| Feedback | `wy-toast`, `wy-skeleton`, `wy-empty`, `wy-offline`, `wy-sync-status`, `wy-progress` |
| Search | `wy-quicksearch`, `wy-filter-panel`, `wy-filter-group`, `wy-active-filters`, `wy-result-bar`, `wy-sort`, `wy-pagination`, `wy-saved-search` |
| Motion sections | `wy-aurora`, `wy-split`, `wy-story` (pinned), `wy-marquee`, `wy-cursor` |
| Conversion & AI | `wy-cta-bar`, `wy-ai-fab`, `wy-ai-panel`, `wy-ai-confirm`, `wy-lead-form`, `wy-inspection-cta` |
| Pro | `wy-sidebar`, `wy-rail`, `wy-topbar`, `wy-pipeline` (kanban), `wy-calendar`, `wy-list-detail`, `wy-site-preview` (device switcher) |

Every component documents: anatomy, all states (default, hover, focus-visible, active, disabled, loading, error, empty), light + dark, LTR + RTL, phone + desktop, and its motion entry from §3.

---

## 6. Open items (not decided here)
- Premium vehicle renders (studio + three-quarter) do not exist yet; current SVGs are placeholders. Asset production needs an owner and a decision (commissioned 3D renders vs. AI-assisted, with licence clarity).
- Wolof and Arabic UI strings require native review (founder-only blocker, STATUS.md).
- Logo final sign-off after D-01 trademark/domain checks.
- Decisions to log in PROJECT §23: Western digits in Arabic UI; GSAP excluded; Sora retired from Marketplace; WhatsApp button colour.
