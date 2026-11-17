#!/usr/bin/env python3
"""Builds the 'Horizon' wordmark as outlines from Manrope 700 (DESIGN 1.2). Prints SVG inner paths to logo_paths.json."""
import json, pathlib
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
here = pathlib.Path(__file__).resolve().parent
f = instancer.instantiateVariableFont(TTFont(str(here/'../../fonts/manrope-latin-wght-normal.woff2')), {'wght': 700})
gs = f.getGlyphSet(); cmap = f.getBestCmap(); upm = f['head'].unitsPerEm
track = -0.02 * upm
# layout: w o t [sun] y i  (second 'o' replaced by the sun disc)
x = 0; parts = []; sun = None
for i, ch in enumerate('wotoyi'):
    g = cmap[ord(ch)]; adv = gs[g].width
    if i == 3:
        # disc of the same optical size as 'o'
        bp = __import__('fontTools.pens.boundsPen', fromlist=['BoundsPen']).BoundsPen(gs); gs[cmap[ord('o')]].draw(bp)
        x0, y0, x1, y1 = bp.bounds
        sun = (x + (adv)/2, (y0+y1)/2, (y1-y0)/2)
    else:
        pen = SVGPathPen(gs); gs[g].draw(TransformPen(pen, (1, 0, 0, -1, x, 0)))
        parts.append(pen.getCommands())
    x += adv + track
W = x - track
# viewBox: height = ascender region; baseline at y=0 (flipped, so glyphs go negative)
bp = __import__('fontTools.pens.boundsPen', fromlist=['BoundsPen']).BoundsPen(gs)
for ch in 'woty i'.replace(' ', ''): gs[cmap[ord(ch)]].draw(bp)
top = -bp.bounds[3]; bottom = -bp.bounds[1]
json.dump({'paths': parts, 'sun': sun, 'width': W, 'top': top, 'bottom': bottom, 'upm': upm}, open(here/'logo_paths.json', 'w'))
print(W, top, bottom, sun)
