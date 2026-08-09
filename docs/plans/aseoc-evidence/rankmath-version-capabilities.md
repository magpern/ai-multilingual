# A.SEOc Evidence — Rank Math Version & Capabilities

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Version

| Source | Value |
|---|---|
| Plugin header / `$version` | **1.0.275** |
| Slug | `seo-by-rank-math` |
| Status | Active (dev) |

## Modules (`rank_math_modules`)

Active includes: `link-counter`, `analytics`, `seo-analysis`, `sitemap`, `rich-snippet`, `woocommerce`, `buddypress`, `bbpress`, `acf`, `web-stories`, `content-ai`, `instant-indexing`, `local-seo`, `404-monitor`, `redirections`, `ai-visibility`.

A.SEOc-relevant: **rich-snippet**, **woocommerce**. Sitemap/OpenGraph inventory for A.SEOe/A.SEOd only.

## Settings option keys

| Option | Role |
|---|---|
| `rank-math-options-general` | General / WC base / breadcrumbs / Content AI |
| `rank-math-options-titles` | Title/meta/robots/schema templates (149 keys) |
| `rank-math-options-sitemap` | Sitemap (A.SEOe) |
| `rank-math-options-instant-indexing` | IndexNow |

## Capability summary for A.SEOc

| Capability | Present? | Notes |
|---|---|---|
| Official title filter | Yes | `rank_math/frontend/title` (post-expansion) |
| Official description filter | Yes | `rank_math/frontend/description` |
| Official canonical filter | Yes | Used by A.SEOb |
| Schema JSON-LD filters | Yes | `rank_math/json_ld`, `rank_math/snippet/rich_snippet_*_entity` |
| Variable/replacement filters | Yes | `rank_math/replacements`, `rank_math/vars/{id}` |
| Document hreflang | No | AIML owns (A.SEOb) |
| Public API to annex meta as TM | No | Forbidden path |

## Compatibility floor (planning)

Supported implementation must degrade safely when Rank Math missing/inactive/unsupported. Exact minimum version pin is an implementation detail; evidence baseline is **1.0.275**. Do not claim older versions without re-inventory.
