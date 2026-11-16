---
name: multilingual-wolof-arabic
description: "Use when writing or reviewing UI strings, translations, content or AI replies in French, Wolof, English or Arabic, or when building RTL layouts."
---

# Multilingual FR / Wolof / EN / AR

## Languages
- **French** default for Senegal. **Wolof**: Latin script, official orthography (CLAD: é, ë, ñ, ŋ, double consonants/vowels), natural Dakar code-switching accepted for marketing, cleaner Wolof for UI. **English**: simple international. **Arabic**: Modern Standard Arabic, RTL.
- Vocabulary: "oto/woto" (car), "auto"/"voiture" in French — avoid "car" (= bus in Senegal). Prices: `1 500 000 FCFA` (space thousands separator in FR).
- Never claim Wolof or Arabic is verified unless a native speaker checked it: mark 👤.

## Code
- Every string through `__()`/`esc_html__()`/`_n()` with the right text domain; no concatenated sentences — use placeholders `%1$s`.
- Locale codes: `fr_FR`, `wo` (Wolof, add if missing), `en_US`, `ar`. `hreflang` on public pages.
- RTL: `dir="rtl"` from `is_rtl()`, CSS logical properties (`margin-inline-start`, `inset-inline-end`, `text-align: start`), mirrored icons for arrows only.
- Fonts must include Arabic glyphs (fallback stack), and Wolof diacritics (ë, ñ, ŋ).
- Dates/numbers: `date_i18n`, `number_format_i18n`.

## AI replies
Detect the user's language from the message, answer in it; if Wolof confidence is low, answer in simple Wolof + French. Voice notes: transcribe, show the transcript to staff.
