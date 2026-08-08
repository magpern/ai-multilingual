# A.SEO Evidence — Validation Philosophy

**Status:** Initial architectural evidence (parent freeze)  
**Parent:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](../ASEO_PARENT_IMPLEMENTATION_PLAN.md) §10  
**Matrix:** [A_SEO_DEPENDENCY_MATRIX.md](../A_SEO_DEPENDENCY_MATRIX.md) §6

This document records the **family validation philosophy**. Wave plans inherit it; they add surface-specific checks without weakening these categories.

---

## Categories (architecture)

| Category | Architectural requirement |
|---|---|
| EN/SV | Default Biopentra language pair for acceptance evidence |
| Canonical correctness | Canonical URL matches language-aware permalink policy |
| hreflang reciprocity | Every published alternate points back; `x-default` policy explicit |
| 404 safety | Unknown/malformed language URLs do not soft-200 wrong content |
| Redirect safety | No loops; no chains; no heuristic cross-language redirects |
| Duplicate URL detection | One public URL identity per language object |
| Duplicate metadata detection | No conflicting AIML+foreign duplicate emitters beyond admitted policy |
| Language leakage | Admitted surfaces render target language only (FP=0) |
| False positives | Diagnostics/validators must not flag source-fallback as failure incorrectly |
| Crawlability | Published languages discoverable; preview excluded |
| GSC readiness | Architecture supports Search Console URL inspection expectations |
| Rich Results compatibility | Schema cooperation does not invent unsupported markup |
| Sitemap validity | Well-formed XML; published-only; alternates coherent |
| Robots validation | robots/indexability matches published vs preview policy |
| Woo compatibility | Product/taxonomy URL structures remain coherent |
| Rank Math compatibility | Active and inactive paths both validated |

## Validation order

Follow the dependency matrix: preconditions → A.SEOa → A.SEOb → A.SEOc → A.SEOd → A.SEOe → A.SEOf → family closure.

## What this is not

- Not implementation acceptance criteria
- Not a license to scrape HTML for “SEO audits” as the primary architecture
- Not a second diagnostics product — A.SEOf reuses AIML Diagnostics conventions
