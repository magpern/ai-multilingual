# A.SEOb Evidence — Admission Matrix

**Status:** Planning freeze — evidence-driven dispositions  
**Baseline:** `main` @ `a1e91f442`  
**Rule:** Every item started as **Candidate**. Disposition only after evidence.

---

## Final dispositions

| ID | Topic | Disposition | Evidence basis | ADR gate |
|---|---|---|---|---|
| SB1 | Canonical URL generation | **Supported** | Official WP/Rank Math filters + SA7 absolute URLs; AIML emits/overlays language-aware canonical | None new |
| SB2 | Canonical override policy | **Supported** | Respect foreign Rank Math / storefront overrides when present; otherwise SB11 current URL; no guessing | None new |
| SB3 | hreflang generation | **Supported** | No document hreflang today; AIML owns relationship emission via `wp_head` + SB11 | None new |
| SB4 | x-default policy | **Supported** | `x-default` → default language absolute URL (`Languages` is_default) | None new |
| SB5 | Alternate language discovery | **Supported** | `Languages::routable(false)` / published-only set | ADR-0008 |
| SB6 | Cross-language URL relationships | **Supported** | SA7 path+prefix rules (Switcher-equivalent); no reverse slug map | A.SEOa SA7 |
| SB7 | Language availability policy | **Supported** | Published languages only for public SEO graph | ADR-0008 |
| SB8 | Canonical / hreflang interaction | **Supported** | Current-language canonical equals that language’s hreflang URL; no duplicate conflicting tags beyond cooperation policy | None new |
| SB9 | Preview exclusion | **Supported** | Preview never in public hreflang/canonical discovery graph | ADR-0008 |
| SB10 | Language relationship validation | **Supported** | Acceptance tests + reciprocity/orphan/duplicate guards in A.SEOb; **diagnostics UI → A.SEOf** | None new for tests |
| SB11 | Canonical reusable language-relationship contract consumed unchanged by A.SEOc–A.SEOf | **Supported** | Lightweight read-only contract from existing primitives; consumer-proven; no Store/API/identity/TARGET change; no circular deps | None new |

---

## Explicitly not Supported in A.SEOb

| Topic | Disposition | Why |
|---|---|---|
| Translated leaf slugs in relationship URLs | Deferred (A.SEOa SA1+) | No reverse lookup; SA7 source leaf only |
| Persistent URL-relationship / redirect-history tables | Deferred / ADR | Forbidden without ADR; TARGET 6 |
| Rank Math title / meta / schema overlays | Deferred → A.SEOc | Parent wave boundary |
| OG / Twitter overlays | Deferred → A.SEOd | Parent wave boundary |
| Sitemap alternate emission | Deferred → A.SEOe | Parent wave boundary; **consumes SB11** |
| SEO diagnostics admin UI | Deferred → A.SEOf | Consumes SB11 validation signals |
| HTML scraping / fuzzy URL matching | Unsupported | Family forbidden |
| Second router / new identity family | Unsupported | Family forbidden |
| Depending on A.SEOc–A.SEOf to define SB11 | Unsupported | Circular dependency forbidden |

---

## Supported set (frozen)

**SB1, SB2, SB3, SB4, SB5, SB6, SB7, SB8, SB9, SB10, SB11**
