# A.SEOb Evidence — Rank Math Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`  
**Plugin:** `seo-by-rank-math` (active on dev)

---

## 1. Live observations

| Signal | Finding |
|---|---|
| Active modules | include sitemap, rich-snippet, woocommerce, redirections, … |
| Document canonical tag | Omitted when `noindex` (dev site globally noindex) |
| Document hreflang | **None** |
| `og:url` / `og:locale` | Present; locale follows AIML; URL language-prefixed for SV |
| Schema `inLanguage` | Follows AIML locale |

## 2. Ownership

| Concern | Owner | A.SEOb action |
|---|---|---|
| Canonical tag emission when Rank Math active | Rank Math | Overlay language-aware URL via official filters |
| Title / description / schema content | Rank Math | **A.SEOc** |
| OG/Twitter | Rank Math | **A.SEOd** |
| Sitemap | Rank Math | **A.SEOe** |
| Multilingual alternate graph | **Not Rank Math today** | AIML SB11 + SB3 |

## 3. Cooperation rules (to freeze)

1. When Rank Math is active, AIML does **not** emit a second competing canonical tag; it filters Rank Math’s canonical value to the SB11 current-language URL (unless a Rank Math per-post canonical override is present — then apply SB2 policy).
2. AIML **does** emit document hreflang (Rank Math does not today). If a future Rank Math feature emits hreflang, A.SEOb/A.SEOc must coordinate to avoid duplicates — prefer single owner: AIML for language relationships.
3. Never read Rank Math HTML output; never mutate Rank Math tables as translation store.

## 4. Admission implication

Rank Math presence does **not** block Supported SB1–SB4/SB8/SB11. Title/meta remain Deferred to A.SEOc.
