# A.SEO Evidence — Architecture Baseline

**Status:** Initial architectural evidence (parent freeze)  
**Parent:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](../ASEO_PARENT_IMPLEMENTATION_PLAN.md)  
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](../A_SEO_DEPENDENCY_MATRIX.md)

---

## 1. Frozen platform contracts

| Contract | Implication for SEO |
|---|---|
| ADR-0001 | One WP object; SEO translations are overlays |
| ADR-0002 | Prefix-strip routing; **no** translated rewrite bases without ADR |
| ADR-0007 | Hashes are not identity |
| ADR-0008 | Preview out of hreflang/sitemaps; no fallback chains yet |
| ADR-0013 / 0016 / 0017 | Identity families `b:` / `e:` / `p:` only |
| ADR-0018 | Transactional email language — not visitor SEO |
| Integration API v1 | Rank Math admission path |
| TARGET = 6 | No schema bump for parent freeze |

## 2. Known production gaps (classic M5 / deployment)

From [`docs/ROADMAP.md`](../../ROADMAP.md) and deployment notes:

1. Slugs are not translated — `/sv/<source-slug>/`.
2. Canonical URLs and document hreflang are not emitted.
3. `redirect_canonical` is suppressed for prefixed requests to avoid loops.
4. Rank Math emits `<title>` outside `document_title_parts`, so core title overlay never runs.
5. Sitemap alternates / robots indexability policy for preview languages are not implemented.

## 3. Identity stance

- No new identity family at parent freeze.
- SEO meta owned by Rank Math → prefer Integration API `p:` + official filters.
- Slug units → existing Store slug format / post-term identity discipline (detailed in A.SEOa plan).
- Path strings are **not** Store identities.

## 4. Non-goals at parent freeze

- Implementation work packages beyond wave boundaries
- Choosing concrete Rank Math filter names (A.SEOc evidence)
- Admitting translated rewrite bases
- Product-priority reordering (remains in `PRODUCT_PRIORITIES.md`)

## 5. Architecture verdict

**GO for architecture freeze** within existing ADRs and TARGET 6, provided:

- A.SEOa keeps rewrite bases Deferred under ADR-0002
- Rank Math remains foreign owner for title/meta
- No HTML scraping strategy is introduced

**No new ADR required** at parent freeze.
