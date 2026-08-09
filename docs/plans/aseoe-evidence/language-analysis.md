# A.SEOe Evidence — Language Analysis

| Contract | Use for sitemaps |
|---|---|
| A.SEOa SA7 | Language-aware absolute URLs |
| A.SEOb SB11 `LanguageRelationshipService` | Public relationship graph |
| `for_public_request()` / `for_path($path, false)` | Published/routable only; preview excluded |
| `url_for_language()` | Default unprefixed; others `/{code}/...` |
| `LanguageRelationship` | code, hreflang (BCP47), url, is_default, is_current |

## Rules

- No URL guessing / path heuristics
- No independent language enumeration
- Sitemap alternates must not disagree with document hreflang for the same object
- `/sv/` home 301-loop is **pre-existing** Router/front-page debt (A.SEOd established); record if it affects crawl validation; do not fix inside A.SEOe

## Live

Document hreflang EN↔SV reciprocal. Sitemap locs default-only — discovery gap A.SEOe must address via Rank Math filters + SB11.
