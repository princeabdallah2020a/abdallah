"""ECO contrast validator — checks every text/background pair of the light and dark palettes (WCAG 2.2 AA)."""
import json, sys

def lum(h):
    h = h.lstrip('#')
    r, g, b = (int(h[i:i+2], 16) / 255 for i in (0, 2, 4))
    f = lambda c: c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)

def ratio(a, b):
    la, lb = sorted((lum(a), lum(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)

P = json.load(open(sys.argv[1]))
# (foreground, background, minimum)
PAIRS = [
    ("contrast", "base", 4.5), ("contrast", "surface", 4.5), ("contrast", "surface-2", 4.5),
    ("muted", "base", 4.5), ("muted", "surface", 4.5), ("muted", "surface-2", 4.5),
    ("accent-text", "base", 4.5), ("accent-text", "surface", 4.5),
    ("secondary", "base", 4.5), ("secondary", "surface", 4.5),
    ("accent-ink", "accent", 4.5), ("on-night", "night", 4.5), ("on-night-muted", "night", 4.5),
    ("accent", "night", 3.0), ("border-strong", "surface", 3.0), ("success", "surface", 4.5),
]
fail = 0
for mode, pal in P.items():
    for fg, bg, mn in PAIRS:
        r = ratio(pal[fg], pal[bg])
        ok = r >= mn
        fail += not ok
        print(f"{mode:5} {fg:15} on {bg:10} {r:5.2f} (min {mn}) {'OK' if ok else 'FAIL'}")
print("FAILURES:", fail)
sys.exit(1 if fail else 0)
