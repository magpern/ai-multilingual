# A.SEOb Evidence — Ownership Inventory

**Status:** Planning freeze evidence  
**Wave:** A.SEOb Canonical URLs, hreflang & language relationships  
**Baseline:** `main` @ `a1e91f4429428cac166db5e72c892734b4587b5c`  
**Live env:** https://dev.biopentra.eu (WP + Woo + Rank Math + Blocksy + Elementor + AIML + biopentra-storefront)  
**Parent:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](../ASEO_PARENT_IMPLEMENTATION_PLAN.md)  
**Depends on:** A.SEOa Complete (`a-seoa-slugs-permalinks-complete`; Supported SA7/SA10)

---

## 1. WordPress core

| Surface | Owner | Live / code finding | A.SEOb role |
|---|---|---|---|
| Permalink generation | WordPress | `get_permalink` + rewrite; AIML prefixes via `home_url` (SA7) | Consume SA7 URLs |
| `redirect_canonical` | WordPress | AIML blind-suppress when prefixed ([`Router::filter_redirect_canonical`](../../../src/Routing/Router.php)) | Replace suppress with language-aware policy |
| `rel_canonical` / `get_canonical_url` | WordPress | Core hook present; Rank Math removes `rel_canonical` when active | Cooperate via filters when Rank Math inactive |
| Document `<link rel="alternate" hreflang>` | None today | **Absent** in live HTML | AIML emission policy (Supported candidate) |

## 2. WooCommerce

| Surface | Owner | Finding | A.SEOb role |
|---|---|---|---|
| Product / taxonomy / shop URLs | Woo + WP | Source leaf + AIML prefix (SA7); live product EN/SV 200 | Canonical/hreflang consume same URL identity |
| Woo SEO head | Woo / Rank Math Woo module | Rank Math Woo module active on site | Do not annex Woo meta |

## 3. Rank Math (`seo-by-rank-math`)

| Surface | Owner | Finding | A.SEOb role |
|---|---|---|---|
| Canonical emission | Rank Math | Removes WP `rel_canonical`; emits via `rank_math/head` → `canonical()`; **suppressed when noindex** | Cooperate via official Rank Math filters; do not scrape |
| Title / meta / schema | Rank Math | Live schema `inLanguage` follows AIML locale | **A.SEOc** — out of A.SEOb |
| OG / Twitter | Rank Math | Live `og:url` / `og:locale` language-aware via prefixed home | **A.SEOd** |
| Sitemap | Rank Math | Module active | **A.SEOe** |
| hreflang | Rank Math | **No** document hreflang observed | AIML owns relationship emission; Rank Math not emitting multilingual alternates today |

## 4. biopentra-storefront (foreign)

| Surface | Owner | Finding | A.SEOb role |
|---|---|---|---|
| `get_canonical_url` filter | Storefront Technical SEO | Adjusts canonical on selected noindex singulars | Foreign owner — AIML must not steal; cooperate |

## 5. Theme / Blocksy

| Finding | Verdict |
|---|---|
| Head chrome / styles only for SEO relationships | **No** canonical/hreflang ownership |
| Disposition | Not admitted as SEO relationship owner |

## 6. Elementor

| Finding | Verdict |
|---|---|
| Owns `_elementor_data` body content | **Not** SEO URL/relationship ownership |
| Disposition | Out of A.SEOb |

## 7. AIML today

| Surface | Owner | Finding |
|---|---|---|
| Language prefix strip / prefix | Router | ADR-0002; SA7 |
| Blind `redirect_canonical` suppress | Router | Documented as temporary until SEO milestone |
| Switcher `hreflang` attributes | Switcher | UI anchors only — **not** document alternates |
| Languages::routable | Languages + LanguageResolver | Published always; preview capability-gated (ADR-0008) |
| Preview URLs | PreviewService | SA10; not public SEO |
| Document canonical / hreflang | — | **Not implemented** |

## 8. Ownership freeze statement

- WordPress owns permalink infrastructure and baseline `redirect_canonical`.
- Woo owns Woo URL structures.
- Rank Math owns Rank Math head emission when active (canonical tag path).
- Storefront may filter `get_canonical_url` — foreign.
- AIML owns **language-relationship emission policy** and language-aware URL construction (SA7), and may overlay canonical/hreflang **only** through official WP/Rank Math hooks.
- No annexation of Rank Math post meta or WP slug columns as a translation store (ADR-0001 / ADR-0017).
