# A.SEOc Evidence — Admission Matrix

**Status:** Planning freeze — evidence-driven dispositions  
**Baseline:** `main` @ `488e62f93`  
**Rule:** Every SC1–SC14 item started as **Candidate**. Disposition only after evidence.

---

## Final dispositions

| ID | Topic | Disposition | Evidence basis | ADR gate |
|---|---|---|---|---|
| SC1 | Post/page SEO title | **Supported** | Explicit `rank_math_title` + `rank_math/frontend/title`; template-only inherits `%title%` without new identity | None new (ADR-0017 `p:` if needed) |
| SC2 | Post/page meta description | **Supported** | Explicit `rank_math_description` + `rank_math/frontend/description`; `%excerpt%` inherits content overlays | None new |
| SC3 | Woo product SEO title | **Supported** | Same as SC1 for `product` CPT; live filled meta gap on SV | None new |
| SC4 | Woo product meta description | **Supported** | Same as SC2 for products | None new |
| SC5 | Taxonomy SEO title | **Supported** | Term meta + tax templates; explicit term titles overlay; `%term%` inherits | None new |
| SC6 | Taxonomy meta description | **Supported** | Explicit term descriptions + `%term_description%` inheritance | None new |
| SC7 | Title template/token cooperation | **Partially Supported** | Freeze no-duplicate-token policy; do not translate templates wholesale; ensure content tokens inherit | None new |
| SC8 | Description template/token cooperation | **Partially Supported** | Same as SC7 for description tokens | None new |
| SC9 | Supported schema textual properties | **Partially Supported** | Only name/description-like text aligned to admitted SEO fields via entity filters; machine/URL/price untouched | None new |
| SC10 | Rank Math compatibility / lifecycle | **Supported** | Missing/inactive/module/filter/noindex degrade to native Rank Math/WP; never fatal | None new |
| SC11 | SB11 language-relationship consumption | **Supported** | Consume A.SEOb contract unchanged | A.SEOb SB11 |
| SC12 | Preview metadata behavior | **Supported** | ADR-0008 / SA10 / SB9; authorized preview only; no public preview SEO variants | ADR-0008 |
| SC13 | Source fallback / stale behavior | **Supported** | Empty Store → Rank Math native; source meta change → existing stale/hash conventions | ADR-0007 |
| SC14 | Metadata validation / duplicate-ownership guards | **Supported** | Tests/guards: no meta annexation; no second `<title>`; no SB11 fork; no scrape | None new |

---

## Explicitly not Supported in A.SEOc

| Topic | Disposition | Why |
|---|---|---|
| OG / Twitter field overlays | Deferred → A.SEOd | Parent wave boundary |
| Sitemap / robots indexability product | Deferred → A.SEOe | Parent wave boundary |
| SEO diagnostics UI | Deferred → A.SEOf | Parent wave boundary |
| Translating Rank Math template strings as identity | Unsupported / Deferred | Unstable / duplicate ownership |
| Translating `%wc_price%` / `%wc_sku%` / ratings / IDs | Unsupported | Machine values |
| Broad “all schema translated” | Unsupported | Opaque/dynamic nodes |
| HTML scrape / final-head rewrite | Unsupported | Family forbidden |
| Rank Math meta as AIML translation DB | Unsupported | Ownership forbidden |
| Changing SB11 / A.SEOa URLs / A.SEOb canonical | Unsupported | Hard boundary |

---

## Supported set (frozen for implementation authorization)

**Supported:** SC1–SC6, SC10–SC14  
**Partially Supported (bounded):** SC7, SC8, SC9  

Implementation may claim Partially Supported only within the frozen partial boundaries in the wave plan.
