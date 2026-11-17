#!/usr/bin/env python3
"""Generates ../css/tokens.css from ../../design/tokens.json (run: python3 -I _src/gen_tokens.py)."""
import json, pathlib
here = pathlib.Path(__file__).resolve().parent
T = json.loads((here/'../../design/tokens.json').read_text())
V = lambda d: {k: v['$value'] for k, v in d.items() if isinstance(v, dict) and '$value' in v}
def colors(mode):
    return ''.join(f'  --wy-color-{k}: {v};\n' for k, v in V(T['color'][mode]).items())
def elev(mode):
    e = V(T['elevation'][mode])
    return ''.join(f'  --wy-elev-{k}: {v};\n' for k, v in e.items())
def glass(mode):
    g = T['glass']; o = ''
    if mode == 'light':
        o += f"  --wy-glass-bg: {g['light']['background']['$value']};\n  --wy-glass-media: {g['light-media']['background']['$value']};\n  --wy-glass-border: {g['light']['border']['$value']};\n  --wy-glass-fallback: {g['light']['fallback']['$value']};\n"
    else:
        o += f"  --wy-glass-bg: {g['dark']['background']['$value']};\n  --wy-glass-media: {g['dark-media']['background']['$value']};\n  --wy-glass-border: {g['dark']['border']['$value']};\n  --wy-glass-fallback: {g['dark']['fallback']['$value']};\n"
    return o
logo = {'light': ('sunset-2', 'sunset-text-1'), 'dark': ('sunset-1', 'sunset-3')}
def logo_vars(m): return f"  --wy-logo-a: var(--wy-color-{logo[m][0]});\n  --wy-logo-b: var(--wy-color-{logo[m][1]});\n"
def grads(m):
    g = V(T['gradient'])
    em = 'sunset-text' if m == 'light' else 'sunset'
    return f"  --wy-gradient-sunset: {g['sunset']};\n  --wy-gradient-sunset-text: {g['sunset-text']};\n  --wy-em: var(--wy-gradient-{em});\n"
def mode_block(m): return colors(m) + elev(m) + glass(m) + logo_vars(m) + grads(m)

root = ''
for grp in ('space', 'layout', 'radius'):
    for k, v in V(T[grp]).items(): root += f'  --wy-{grp}-{k}: {v};\n'
for k, v in V(T['font']['family']).items(): root += f'  --wy-font-{k}: {v};\n'
for k, v in V(T['font']['size']).items(): root += f'  --wy-font-size-{k}: {v};\n'
for g in ('duration', 'easing', 'distance'):
    for k, v in V(T['motion'][g]).items(): root += f'  --wy-{g}-{k}: {v};\n'
for k, v in V(T['z']).items(): root += f'  --wy-z-{k}: {v};\n'
for k, v in V(T['breakpoint']).items(): root += f'  /* breakpoint {k}: {v} (media queries cannot use custom properties) */\n'
night_glass = T['glass']['night']
root += f"  --wy-glass-night-bg: {night_glass['background']['$value']};\n  --wy-glass-night-border: {night_glass['border']['$value']};\n  --wy-glass-night-fallback: {night_glass['fallback']['$value']};\n  --wy-glass-blur: {T['glass']['blur']['$value']};\n"
for k, v in V(T['gradient']).items():
    if k in ('aurora', 'sunset', 'sunset-text'): continue
    root += f'  --wy-gradient-{k}: {v};\n'
for i, l in enumerate(T['gradient']['aurora']['$value'], 1): root += f'  --wy-aurora-{i}: {l};\n'

dark_vars = mode_block('dark')
night = dark_vars + "  --wy-color-base: var(--wy-color-night);\n  --wy-color-contrast: var(--wy-color-on-night);\n  --wy-color-muted: var(--wy-color-on-night-muted);\n  --wy-glass-bg: var(--wy-glass-night-bg);\n  --wy-glass-media: var(--wy-glass-night-bg);\n  --wy-glass-border: var(--wy-glass-night-border);\n  --wy-glass-fallback: var(--wy-glass-night-fallback);\n"

out = f"""/* GENERATED from wotoyi/design/tokens.json v{T['$version']} by _src/gen_tokens.py - do not edit by hand. */
:root {{
{root}{mode_block('light')}  color-scheme: light;
}}
:root[data-theme="dark"] {{
{dark_vars}  color-scheme: dark;
}}
@media (prefers-color-scheme: dark) {{
  :root:not([data-theme]) {{
{dark_vars}    color-scheme: dark;
  }}
}}
/* Night sections (hero, story, footer) always use the dark palette on the night background (DESIGN 1.1) */
.wy-night, :root[data-theme] .wy-night, .wy-header[data-tone="night"]:not([data-scrolled]) {{
{night}  color-scheme: dark;
  background: var(--wy-color-night);
  color: var(--wy-color-on-night);
}}
"""
(here/'../css/tokens.css').write_text(out)
print('tokens.css', len(out), 'bytes')
