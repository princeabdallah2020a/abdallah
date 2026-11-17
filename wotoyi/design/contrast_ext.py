"""Wotoyi v2 extended contrast check: pairs not covered by scripts/contrast.py
(surface-3, gradient stops, status colours, focus, glass composites).
Usage: python3 -I wotoyi/design/contrast_ext.py wotoyi/design/palette.json"""
import json, sys

def lum(h):
    h = h.lstrip('#'); r, g, b = (int(h[i:i+2], 16) / 255 for i in (0, 2, 4))
    f = lambda c: c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)

def ratio(a, b):
    x, y = sorted((lum(a), lum(b)), reverse=True); return (x + .05) / (y + .05)

def comp(fg, a, bg):  # alpha-composite fg over bg
    fg, bg = fg.lstrip('#'), bg.lstrip('#')
    return '#' + ''.join('%02X' % round(a * int(fg[i:i+2], 16) + (1 - a) * int(bg[i:i+2], 16)) for i in (0, 2, 4))

PAIRS = [
    ("contrast", "surface-3", 4.5), ("muted", "surface-3", 4.5),
    ("accent-text", "surface-2", 4.5), ("secondary", "surface-2", 4.5),
    ("accent-ink", "sunset-1", 4.5), ("accent-ink", "sunset-2", 4.5), ("accent-ink", "sunset-3", 4.5),
    ("sunset-text-1", "base", 4.5), ("sunset-text-2", "base", 4.5), ("sunset-text-1", "surface", 4.5), ("sunset-text-2", "surface", 4.5),
    ("secondary-ink", "secondary", 4.5),
    ("success", "base", 4.5), ("warning", "base", 4.5), ("warning", "surface", 4.5),
    ("danger", "base", 4.5), ("danger", "surface", 4.5),
    ("focus", "base", 3.0), ("focus", "surface", 3.0), ("focus", "surface-2", 3.0),
    ("border-strong", "base", 3.0), ("border-strong", "surface-2", 3.0),
    ("accent-graphic", "base", 3.0), ("accent-graphic", "surface", 3.0),  # icons/lines in accent colour; raw light accent on base is 1.82 -> never use it for graphics
    ("on-night", "glass-night", 4.5), ("on-night-muted", "glass-night", 4.5), ("accent", "glass-night", 3.0),
]
# glass recipes: (label, tint, alpha, worst-case backdrop, text tokens)
GLASS = {
    "light": [("glass .72 over base", "#FFFFFF", .72, "base", ("contrast", "muted")),
              ("glass .88 over black photo", "#FFFFFF", .88, "#000000", ("contrast", "muted"))],
    "dark":  [("glass .72 over base", "#10141D", .72, "base", ("contrast", "muted")),
              ("glass .88 over white photo", "#10141D", .88, "#FFFFFF", ("contrast", "muted"))],
}
NIGHT_GLASS = ("night-glass .78 over white photo", "#0C111C", .78, "#FFFFFF", ("on-night", "on-night-muted"))

P = json.load(open(sys.argv[1])); fail = 0
for mode, pal in P.items():
    for fg, bg, mn in PAIRS:
        r = ratio(pal[fg], pal[bg]); ok = r >= mn; fail += not ok
        print(f"{mode:5} {fg:15} on {bg:12} {r:5.2f} (min {mn}) {'OK' if ok else 'FAIL'}")
    for label, tint, a, bg, fgs in GLASS[mode] + [NIGHT_GLASS]:
        c = comp(tint, a, pal.get(bg, bg))
        for fg in fgs:
            r = ratio(pal[fg], c); ok = r >= 4.5; fail += not ok
            print(f"{mode:5} {fg:15} on {label} ({c}) {r:5.2f} (min 4.5) {'OK' if ok else 'FAIL'}")
print("FAILURES:", fail)
sys.exit(1 if fail else 0)
