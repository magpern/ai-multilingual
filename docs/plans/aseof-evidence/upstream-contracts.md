# A.SEOf Evidence — Upstream A.SEOa–e Contracts

**Baseline:** `main` @ `fbc719a78`

| Wave | Tag | Supported (consume) | Deferred (do not invent) | Key paths |
|---|---|---|---|---|
| A.SEOa | `a-seoa-slugs-permalinks-complete` | SA7, SA10 | SA1–SA6/SA8/SA9 leaf slugs | Router / PreviewService |
| A.SEOb | `a-seob-canonical-hreflang-complete` | SB1–SB11 | URL history / scrape | `LanguageRelationshipService`, `DocumentSeoHead` |
| A.SEOc | `a-seoc-rankmath-complete` | SC1–SC6/SC10–SC14; Partial SC7–SC9 | OG/sitemap/diagnostics | `RankMathIntegration` |
| A.SEOd | `a-seod-opengraph-complete` | SD1–SD3/SD5–SD8/SD11; Partial FB/TW text | SD4/SD9/SD10/**SD12** | same Rank Math integration |
| A.SEOe | `a-seoe-sitemaps-complete` | SE1–SE9/SE12 | **SE10**, **SE11** | `RankMathSitemapOverlay` |

## Immutable inputs for diagnostics

1. URL identity = SA7 source-leaf + language prefix
2. Relationship graph = SB11 only (`for_path(..., false)` public)
3. Document head = `DocumentSeoHead` + Rank Math canonical filters
4. Title/meta/social = `RankMathIntegration` overlays
5. Discovery = Rank Math owner + sitemap overlay honesty (`blog_public`)

**Do not invent** SD12 SocialMeta or SE11 SitemapDiscovery during A.SEOf.
