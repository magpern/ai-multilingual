# A.SEOc Evidence — Validation Strategy

**Status:** Architecture validation philosophy for Supported + Partially Supported gates  
**Admissions:** [admission-matrix.md](admission-matrix.md)

---

## 1. Applies to Supported / Partially Supported

| Category | Requirement |
|---|---|
| EN/SV | Explicit Rank Math title/description show translated overlays on SV; template-only inherits content tokens |
| Hook/API | Assert via `rank_math/frontend/title\|description` and schema entity filters — not scrape-only |
| Ownership | Rank Math meta unchanged as Store; no duplicate `<title>` emitters |
| SB11 | Unchanged; preview excluded |
| FP / leakage | 0 |
| Duplicate ownership | 0 semantic duplicates for same SEO field |
| noindex honesty | Title/description still validated while canonical HTML may be omitted |
| Regression | A.SEOa, A.SEOb, Gutenberg, Elementor, Woo A.7*, Fluent Forms, A.6 |

## 2. Negatives / guards

| Guard | Assert |
|---|---|
| No Rank Math table writes as TM | Code/PluginGuard |
| No `document_title_parts`-only assumption | Integration tests with Rank Math present (or filter-level doubles) |
| No OG/sitemap work | Deferred guards |
| No SB11 mutation | Diff/guards |

## 3. Suites

Unit, integration, PluginGuard, PHPCS, live head inspection (title/meta/schema text + locale), Rank Math hook validation.

## 4. Performance

Overlays must be bounded Store lookups; no Rank Math full re-parse scrape; no N+1 schema rewrites beyond admitted entities.
