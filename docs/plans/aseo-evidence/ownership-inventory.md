# A.SEO Evidence — Ownership Inventory

**Status:** Initial architectural evidence (parent freeze)  
**Parent:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](../ASEO_PARENT_IMPLEMENTATION_PLAN.md)  
**Baseline:** `main` @ `48985be3395c8e9baa99260d80395e044584a18d`  
**Environment notes:** Rank Math classified as A.SEO lane in [A8_INTEGRATION_CANDIDATE_SELECTION.md](../A8_INTEGRATION_CANDIDATE_SELECTION.md); Router suppresses `redirect_canonical` for prefixed requests ([`src/Routing/Router.php`](../../../src/Routing/Router.php)).

---

## 1. WordPress core

| Surface | Owner | AIML today | Target wave |
|---|---|---|---|
| `post_name` | WordPress | Not translated (source slug in URLs) | A.SEOa |
| Term slug | WordPress | Not translated | A.SEOa |
| Rewrite rules | WordPress | None registered by AIML (ADR-0002) | A.SEOa |
| Permalink generation | WordPress | `home_url` prefix after `parse_request` | A.SEOa |
| `redirect_canonical` | WordPress | Filtered to `false` when prefixed | A.SEOa / A.SEOb |
| Core `<title>` via `document_title_parts` | WordPress | Overlay exists but ineffective when Rank Math owns title | A.SEOc |

## 2. WooCommerce

| Surface | Owner | AIML today | Target wave |
|---|---|---|---|
| Product permalink structure | WooCommerce | Respects WP permalink + language prefix | A.SEOa |
| Product category / tag slugs | WooCommerce + WP terms | Source slugs | A.SEOa |
| Shop page | WooCommerce | Prefixed via Router | A.SEOa |
| Endpoint URLs (cart/checkout/account) | WooCommerce | Prefixed via Router; not SEO content wave | A.SEOa (URL shape only) |

## 3. Rank Math (`seo-by-rank-math`)

| Surface | Owner | AIML today | Target wave |
|---|---|---|---|
| SEO titles | Rank Math | Not overlaid (title tag emitted by Rank Math) | A.SEOc |
| Meta descriptions | Rank Math | Not overlaid | A.SEOc |
| Schema | Rank Math | Not coordinated | A.SEOc |
| Canonical generation | Rank Math / WP | Not emitted by AIML | A.SEOb (+ cooperation) |
| Sitemap URL generation | Rank Math | Not language-aware via AIML | A.SEOe |
| OG / Twitter | Rank Math (typical) | Not overlaid | A.SEOd |

## 4. AIML

| Surface | Owner | Notes |
|---|---|---|
| Language prefix strip/prefix | AIML Router | ADR-0002 |
| Store overlays | AIML | `FORMAT_SLUG` exists in Store; slug translation not shipped |
| LanguageContext | AIML | Request language state |
| hreflang document tags | AIML (future) | Switcher may expose `hreflang` attrs on links only — not document alternates |
| SEO diagnostics | AIML (future) | A.SEOf |

## 5. Elementor

| Finding | Verdict |
|---|---|
| Owns `_elementor_data` document content | **Not SEO URL / meta ownership** |
| Must not be re-admitted as SEO units | Confirmed — use `e:` for body only |

## 6. Theme / Blocksy

| Finding | Verdict |
|---|---|
| May render chrome affecting perceived SEO | Ownership ambiguous unless proven |
| Default disposition | **Unsupported / Deferred** until deterministic owner + hooks |

---

## 7. Ownership freeze statement

Only the real owner may produce translation units. AIML overlays Store values through official hooks. No annexation of Rank Math post meta or WP slug columns as a translation store (ADR-0001 / ADR-0017).
