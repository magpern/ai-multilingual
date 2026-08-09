# A.SEOe Evidence — Validation Strategy

## Automated

- Unit: Rank Math filter overlays; SB11 path→URL mapping; preview exclusion; noindex gate; inactive Rank Math skip  
- Integration: singular owner; no second provider registration; XML fragment helpers; Woo product/cat; Deferred SE10/SE11 guards  
- PluginGuard + PHPCS + `git diff --check`

## Live (observational)

| Check | Pass |
|---|---|
| One `/sitemap_index.xml` owner (Rank Math) | Pass |
| No AIML-generated competing index | Pass |
| Language discovery (SE2/SE3 admitted form) | Pass |
| Preview languages absent | Pass |
| Noindex objects absent | Pass |
| Product + product_cat language-aware | Pass |
| XML well-formed; xmlns valid; no duplicate NS | Pass |
| robots.txt still has single Sitemap: to Rank Math index | Pass |
| A.SEOa–d regression | Pass |

## Performance

Observe Rank Math pagination (`items_per_page`); avoid Store query amplification; reuse SB11 per entry without new cache subsystem unless plan authorizes later.
