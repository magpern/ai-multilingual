# A.SEOc Evidence — Frontend Hook Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Rank Math frontend filters (A.SEOc primary)

| Filter | Timing | Use |
|---|---|---|
| `rank_math/frontend/title` | After token expansion | Overlay explicit SEO title translation |
| `rank_math/frontend/description` | After token expansion | Overlay explicit SEO description translation |
| `rank_math/frontend/robots` | Paper robots | Compatibility awareness; do not fight sitewide noindex |
| `rank_math/frontend/canonical` | Already A.SEOb | Do not redesign |

## Schema hooks (bounded)

| Filter | Use |
|---|---|
| `rank_math/json_ld` | Optional graph-level cooperation |
| `rank_math/snippet/rich_snippet_entity` | Prefer for name/description text |
| `rank_math/schema/language` | Already language-aware — do not break |

## Variable hooks (cooperation / diagnostics)

| Filter | Use |
|---|---|
| `rank_math/replacements` | Advanced token map; prefer not as primary translation seam |
| `rank_math/vars/{id}` | Per-variable; use only if evidence requires token-level fix for `%title%` inheritance |

## Deferred to other waves

| Filter family | Wave |
|---|---|
| `rank_math/opengraph/*` | A.SEOd |
| Sitemap filters | A.SEOe |

## AIML hooks today

`DocumentSeoHead`: `get_canonical_url`, `rank_math/frontend/canonical`, `wp_head` hreflang.  
No title/description/schema overlays yet — that is A.SEOc.

## Hard rule

Official filters/APIs only. No final HTML rewriting. No scrape.
