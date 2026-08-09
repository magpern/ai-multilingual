# A.SEOf Evidence — Ownership Inventory

**Status:** Planning freeze evidence
**Baseline:** `main` @ `fbc719a78` (A.SEOe Complete / `a-seoe-sitemaps-complete`)

---

## Owners (unchanged by A.SEOf)

| Surface | Owner | AIML role today |
|---|---|---|
| Leaf slugs / permalinks | WordPress / Woo | SA7 language-aware URL overlays only |
| Canonical emission | Rank Math (filterable) | A.SEOb `DocumentSeoHead` reinforces via official filters |
| Hreflang / x-default | AIML document head | `DocumentSeoHead` emits from SB11 |
| Language relationships | AIML SB11 | `LanguageRelationshipService` (read-only graph) |
| Titles / meta / schema | Rank Math | A.SEOc overlays via official filters |
| OpenGraph / Twitter | Rank Math | A.SEOd overlays via official hooks |
| Sitemap XML / index | Rank Math | A.SEOe `RankMathSitemapOverlay` via official filters |
| robots.txt / Sitemap: line | WP + Woo + Rank Math | A.SEOe honesty gates only |
| Store translation identity | AIML Store | Not SEO-owned |
| Diagnostics conventions | AIML Diagnostics | BlockHealth / IntegrationDiagnostics / REST / CLI |

---

## A.SEOf may own

- Read-only SEO health/validation/reporting over admitted A.SEOa–e contracts
- Bounded emission checks that compare observed output to expected contract state
- Machine-readable diagnostics results consumed by admin/CLI/REST

## A.SEOf must not own or mutate

- Slugs, canonical, hreflang generation semantics
- Rank Math persistence or emission ownership
- Sitemap generation / robots policy
- SB11 graph construction
- Store schema / identity families

**Disposition recommendation:** Freeze A.SEOf as observer-only; no ownership annexation.
