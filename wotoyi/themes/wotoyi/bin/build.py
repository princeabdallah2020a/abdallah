#!/usr/bin/env python3
"""Wotoyi theme build (authoring helper, NOT needed to run the theme).

  python3 -I bin/build.py            # from wotoyi/themes/wotoyi/

1. assets/css/tokens.css  <- wotoyi/design/tokens.json  (CSS custom properties, light/dark/night)
2. theme.json             <- wotoyi/design/tokens.json  (palette/sizes/spacing point at the CSS variables)
3. assets/dist/*.min.css|js and critical-*.css  (esbuild; set ESBUILD=/path/to/esbuild, otherwise unminified copies)

Colours are never duplicated: theme.json presets are `var(--wy-color-*)`, so dark mode switches without regenerating anything.
"""
import json, os, pathlib, re, shutil, subprocess, sys

HERE = pathlib.Path(__file__).resolve().parent.parent
TOK = json.loads((HERE / '../../design/tokens.json').read_text())
V = lambda d: {k: v['$value'] for k, v in d.items() if isinstance(v, dict) and '$value' in v}

# ---------------------------------------------------------------- tokens.css
def colors(mode): return ''.join(f'  --wy-color-{k}: {v};\n' for k, v in V(TOK['color'][mode]).items())
def elev(mode): return ''.join(f'  --wy-elev-{k}: {v};\n' for k, v in V(TOK['elevation'][mode]).items())
def glass(mode):
    g = TOK['glass']; m = 'light' if mode == 'light' else 'dark'
    return (f"  --wy-glass-bg: {g[m]['background']['$value']};\n  --wy-glass-media: {g[m + '-media']['background']['$value']};\n"
            f"  --wy-glass-border: {g[m]['border']['$value']};\n  --wy-glass-fallback: {g[m]['fallback']['$value']};\n")
LOGO = {'light': ('sunset-2', 'sunset-text-1'), 'dark': ('sunset-1', 'sunset-3')}
def logo_vars(m): return f"  --wy-logo-a: var(--wy-color-{LOGO[m][0]});\n  --wy-logo-b: var(--wy-color-{LOGO[m][1]});\n"
def grads(m):
    g = V(TOK['gradient']); em = 'sunset-text' if m == 'light' else 'sunset'
    return f"  --wy-gradient-sunset: {g['sunset']};\n  --wy-gradient-sunset-text: {g['sunset-text']};\n  --wy-em: var(--wy-gradient-{em});\n"
def mode_block(m): return colors(m) + elev(m) + glass(m) + logo_vars(m) + grads(m)

# Size-adjusted fallbacks live in fonts.css; the stacks below name them so text does not jump when the web font lands.
FONT_STACKS = {
    'display':    "'Instrument Serif', 'Instrument Serif Fallback', 'Instrument Serif Fallback Android', 'Iowan Old Style', Georgia, serif",
    'sans':       "'Manrope', 'Manrope Fallback', 'Manrope Fallback Roboto', 'Segoe UI', Roboto, system-ui, sans-serif",
    'display-ar': "'Noto Kufi Arabic', 'Manrope', 'Manrope Fallback', sans-serif",
    'sans-ar':    "'IBM Plex Sans Arabic', 'Manrope', 'Manrope Fallback', sans-serif",
}

def tokens_css():
    root = ''
    for grp in ('space', 'layout', 'radius'):
        for k, v in V(TOK[grp]).items(): root += f'  --wy-{grp}-{k}: {v};\n'
    for k, v in FONT_STACKS.items(): root += f'  --wy-font-{k}: {v};\n'
    for k, v in V(TOK['font']['size']).items(): root += f'  --wy-font-size-{k}: {v};\n'
    for g in ('duration', 'easing', 'distance'):
        for k, v in V(TOK['motion'][g]).items(): root += f'  --wy-{g}-{k}: {v};\n'
    for k, v in V(TOK['z']).items(): root += f'  --wy-z-{k}: {v};\n'
    ng = TOK['glass']['night']
    root += (f"  --wy-glass-night-bg: {ng['background']['$value']};\n  --wy-glass-night-border: {ng['border']['$value']};\n"
             f"  --wy-glass-night-fallback: {ng['fallback']['$value']};\n  --wy-glass-blur: {TOK['glass']['blur']['$value']};\n")
    for k, v in V(TOK['gradient']).items():
        if k in ('aurora', 'sunset', 'sunset-text'): continue
        root += f'  --wy-gradient-{k}: {v};\n'
    for i, l in enumerate(TOK['gradient']['aurora']['$value'], 1): root += f'  --wy-aurora-{i}: {l};\n'
    dark = mode_block('dark')
    night = dark + ("  --wy-color-base: var(--wy-color-night);\n  --wy-color-contrast: var(--wy-color-on-night);\n  --wy-color-muted: var(--wy-color-on-night-muted);\n"
                    "  --wy-glass-bg: var(--wy-glass-night-bg);\n  --wy-glass-media: var(--wy-glass-night-bg);\n  --wy-glass-border: var(--wy-glass-night-border);\n"
                    "  --wy-glass-fallback: var(--wy-glass-night-fallback);\n")
    return f"""/* GENERATED from wotoyi/design/tokens.json v{TOK['$version']} by bin/build.py - do not edit by hand. */
:root {{
{root}{mode_block('light')}  color-scheme: light;
}}
:root[data-theme="dark"] {{
{dark}  color-scheme: dark;
}}
@media (prefers-color-scheme: dark) {{
  :root:not([data-theme]) {{
{dark}    color-scheme: dark;
  }}
}}
/* Night sections (hero, story, footer) always use the dark palette on the night background (DESIGN 1.1) */
.wy-night, .is-style-wy-night, :root[data-theme] .wy-night, :root[data-theme] .is-style-wy-night, .wy-header[data-tone="night"]:not([data-scrolled]) {{
{night}  color-scheme: dark;
  background: var(--wy-color-night);
  color: var(--wy-color-on-night);
}}
"""

# ---------------------------------------------------------------- theme.json
PALETTE_LABELS = {
    'base': 'Porcelaine / Encre (fond)', 'surface': 'Surface', 'surface-2': 'Sable (surface 2)', 'surface-3': 'Sable profond (surface 3)',
    'contrast': 'Encre (texte)', 'muted': 'Texte secondaire', 'border': 'Filet', 'border-strong': 'Bordure composant',
    'accent': 'Or (remplissage)', 'accent-ink': 'Texte sur or', 'accent-text': 'Or (texte)', 'accent-graphic': 'Or (icone)',
    'secondary': 'Teal', 'secondary-ink': 'Texte sur teal', 'success': 'Succes', 'warning': 'Alerte', 'danger': 'Danger',
    'night': 'Nuit', 'on-night': 'Texte sur nuit', 'on-night-muted': 'Texte secondaire sur nuit', 'whatsapp': 'WhatsApp',
}
SIZE_LABELS = {'display-xl': 'Display XL', 'display-l': 'Display L', 'display-m': 'Display M', 'h3': 'Titre 3', 'h4': 'Titre 4',
               'body-l': 'Corps large', 'body': 'Corps', 'body-s': 'Corps petit', 'caption': 'Legende', 'overline': 'Surtitre',
               'price-l': 'Prix L', 'price': 'Prix'}

def theme_json():
    palette = [{'slug': k, 'name': PALETTE_LABELS[k], 'color': f'var(--wy-color-{k})'} for k in PALETTE_LABELS]
    gradients = [{'slug': 'sunset', 'name': 'Coucher de soleil', 'gradient': 'var(--wy-gradient-sunset)'},
                 {'slug': 'sunset-text', 'name': 'Coucher de soleil (texte)', 'gradient': 'var(--wy-gradient-sunset-text)'}]
    sizes = [{'slug': k, 'name': SIZE_LABELS[k], 'size': f'var(--wy-font-size-{k})'} for k in SIZE_LABELS]
    spaces = [{'slug': k, 'name': f'{k}', 'size': f'var(--wy-space-{k})'} for k in ('1', '2', '3', '4', '5', '6', '8', '10', '12', '16', '20', '24', '32', 'section', 'gutter')]
    shadows = [{'slug': f'elev-{n}', 'name': f'Elevation {n}', 'shadow': f'var(--wy-elev-{n})'} for n in ('1', '2', '3', '4')]
    fam = lambda slug, name, key: {'slug': slug, 'name': name, 'fontFamily': f'var(--wy-font-{key})'}
    return {
        '$schema': 'https://schemas.wp.org/trunk/theme.json',
        'version': 3,
        'settings': {
            'appearanceTools': True,
            'useRootPaddingAwareAlignments': False,
            'layout': {'contentSize': 'var(--wy-layout-measure)', 'wideSize': 'var(--wy-layout-container)'},
            'color': {'custom': False, 'customGradient': False, 'defaultPalette': False, 'defaultGradients': False, 'defaultDuotone': False,
                      'palette': palette, 'gradients': gradients, 'duotone': []},
            'typography': {'customFontSize': True, 'fluid': False, 'defaultFontSizes': False, 'writingMode': False,
                           'fontFamilies': [fam('display', 'Instrument Serif (titres)', 'display'), fam('sans', 'Manrope (interface)', 'sans'),
                                            fam('display-ar', 'Noto Kufi Arabic (titres AR)', 'display-ar'), fam('sans-ar', 'IBM Plex Sans Arabic (texte AR)', 'sans-ar')],
                           'fontSizes': sizes},
            'spacing': {'units': ['px', 'rem', '%', 'vw', 'svh'], 'spacingSizes': spaces, 'padding': True, 'margin': True, 'blockGap': True},
            'shadow': {'defaultPresets': False, 'presets': shadows},
            'border': {'radius': True, 'color': True, 'style': True, 'width': True},
            'custom': {'layout': {'header': 'var(--wy-layout-header-h)', 'touch': 'var(--wy-layout-touch-min)'},
                       'radius': {k: f'var(--wy-radius-{k})' for k in ('xs', 'sm', 'md', 'lg', 'xl', 'pill')},
                       'motion': {'easing': 'var(--wy-easing-out)', 'ui': 'var(--wy-duration-ui)'}},
            'blocks': {'core/button': {'border': {'radius': True}}},
        },
        'styles': {
            'color': {'background': 'var(--wy-color-base)', 'text': 'var(--wy-color-contrast)'},
            'typography': {'fontFamily': 'var(--wy-font-sans)', 'fontSize': 'var(--wy-font-size-body)', 'lineHeight': '1.6'},
            'spacing': {'blockGap': '0'},
            'elements': {
                'link': {'color': {'text': 'var(--wy-color-accent-text)'}, ':hover': {'typography': {'textDecoration': 'underline'}}},
                'heading': {'typography': {'fontWeight': '700', 'lineHeight': '1.2'}},
                'h1': {'typography': {'fontFamily': 'var(--wy-font-display)', 'fontWeight': '400', 'fontSize': 'var(--wy-font-size-display-m)', 'lineHeight': '1.08'}},
                'h2': {'typography': {'fontFamily': 'var(--wy-font-display)', 'fontWeight': '400', 'fontSize': 'var(--wy-font-size-display-m)', 'lineHeight': '1.08'}},
                'h3': {'typography': {'fontSize': 'var(--wy-font-size-h3)', 'letterSpacing': '-0.01em'}},
                'h4': {'typography': {'fontSize': 'var(--wy-font-size-h4)'}},
                'button': {'color': {'gradient': 'var(--wy-gradient-sunset)', 'text': 'var(--wy-color-accent-ink)'},
                           'border': {'radius': 'var(--wy-radius-pill)'},
                           'typography': {'fontWeight': '700'},
                           'spacing': {'padding': {'top': '0.75rem', 'bottom': '0.75rem', 'left': '1.5rem', 'right': '1.5rem'}}},
            },
            'blocks': {
                'core/separator': {'color': {'text': 'var(--wy-color-border)'}},
                'core/quote': {'border': {'width': '0 0 0 3px', 'color': 'var(--wy-color-accent-graphic)'}},
            },
        },
        'templateParts': [{'name': 'header', 'title': 'En-tete', 'area': 'header'}, {'name': 'footer', 'title': 'Pied de page', 'area': 'footer'}],
        'customTemplates': [{'name': 'page-night', 'title': 'Page nocturne (pleine largeur)', 'postTypes': ['page']}],
        'patterns': [],
    }

# ---------------------------------------------------------------- bundles
def read(p): return (HERE / p).read_text()

BUNDLES_CSS = {
    # critical = inlined in <head>; the rest is loaded per template (see inc/assets.php)
    'critical-default': ['assets/css/tokens.css', 'assets/css/fonts.css', 'assets/css/base.css'],
    'critical-home':    ['assets/css/tokens.css', 'assets/css/fonts.css', 'assets/css/base.css', 'assets/css/home-critical.css'],
    'chrome':  ['assets/css/chrome.css', 'assets/css/motion.css', 'assets/css/blocks.css'],
    'cards':   ['assets/css/cards.css'],
    'home':    ['assets/css/home.css'],
    'search':  ['assets/css/search.css'],
    'vehicle': ['assets/css/vehicle.css'],
    'dealer':  ['assets/css/dealer.css'],
    'content': ['assets/css/content.css'],
}
BUNDLES_JS = {
    'core': ['assets/js/core.js'], 'motion': ['assets/js/motion.js'], 'home': ['assets/js/home.js'],
    'search': ['assets/js/search.js'], 'vehicle': ['assets/js/vehicle.js'],
}

def minify(kind, text):
    exe = os.environ.get('ESBUILD')
    if not exe: return text
    r = subprocess.run([exe, f'--loader={kind}', '--minify'], input=text, capture_output=True, text=True)
    if r.returncode: sys.exit(r.stderr)
    return r.stdout

def main():
    (HERE / 'assets/css/tokens.css').write_text(tokens_css())
    (HERE / 'theme.json').write_text(json.dumps(theme_json(), indent=2, ensure_ascii=False) + '\n')
    dist = HERE / 'assets/dist'
    shutil.rmtree(dist, ignore_errors=True); dist.mkdir(parents=True)
    for name, files in BUNDLES_CSS.items():
        existing = [f for f in files if (HERE / f).exists()]
        text = '\n'.join(read(f) for f in existing)
        (dist / f'{name}.css').write_text(minify('css', text))
    for name, files in BUNDLES_JS.items():
        existing = [f for f in files if (HERE / f).exists()]
        if existing: (dist / f'{name}.js').write_text(minify('js', '\n'.join(read(f) for f in existing)))
    for p in sorted(dist.iterdir()): print(f'{p.name:28s}{p.stat().st_size:8d} B')
    print('theme.json + tokens.css written')

if __name__ == '__main__':
    main()
