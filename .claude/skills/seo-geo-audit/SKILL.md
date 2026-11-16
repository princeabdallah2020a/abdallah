---
name: seo-geo-audit
description: "Use when auditing or implementing SEO, local SEO, structured data, or GEO (visibility in AI answer engines) for a site or page."
---

# SEO + GEO audit

## Technical
- Indexable (no stray `noindex`), one canonical per page, XML sitemaps (vehicles, dealers, pages), robots.txt, clean slugs, 301 for changed URLs, no orphan pages.
- `hreflang` for fr / wo / en / ar + `x-default`.
- Core Web Vitals: LCP < 2.5 s, CLS < 0.1, INP < 200 ms on mobile (measure, don't guess).

## On-page
- Unique `<title>` (≤ 60 chars) and meta description per vehicle/dealer/city; one H1; descriptive alt text; internal links (brand → model → listing; city → dealers).

## Structured data (JSON-LD, validate)
- Listing: `Car` (brand, model, vehicleModelDate, mileageFromOdometer, fuelType, vehicleTransmission) + `Offer` (price, priceCurrency XOF, availability, seller).
- Dealer: `AutoDealer` with address, geo, openingHours, telephone, sameAs. Site: `Organization`, `WebSite` + SearchAction, `BreadcrumbList`, `FAQPage` where real FAQs exist.

## Local (Dakar → West Africa)
Google Business Profile per dealer, consistent NAP, city/neighbourhood landing pages with real content, reviews flow.

## GEO (AI answer engines)
Factual, quotable sentences (prices, specs, process, guarantees and limits), clear entity naming (Wotoyi + city), FAQ blocks, author/organisation info, fast HTML without JS-only content, optional `llms.txt`.

## Output
Issue · impact (high/med/low) · fix · how to verify. Check current Google guidance online before asserting a rule.
