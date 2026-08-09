# A.SEOa Evidence — Ownership Inventory

**Status:** Investigation complete (planning)  
**Baseline:** `main` @ `d8375f37abb6e5ce337a866ebd07dd5f960677e3`  
**Parent:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](../ASEO_PARENT_IMPLEMENTATION_PLAN.md)  
**Code:** [`src/Routing/Router.php`](../../../src/Routing/Router.php), [`src/Translation/Store.php`](../../../src/Translation/Store.php), [`src/Translation/Extractor.php`](../../../src/Translation/Extractor.php)

---

## 1. WordPress core

| Surface | Owner | Persistence | AIML today | Notes |
|---|---|---|---|---|
| `post_name` (posts/pages/CPTs) | WordPress | `wp_posts.post_name` | Not extracted/overlaid | Canonical slug persistence |
| Page slugs | WordPress | same as posts (`page` post type) | Same | Hierarchy via `post_parent` |
| Term slugs | WordPress | `wp_terms.slug` | No `SOURCE_TERM` | Uniqueness via `wp_unique_term_slug` |
| Attachment slugs | WordPress | attachment `post_name` | Untouched | Often media filenames |
| `wp_unique_post_slug` | WordPress | — | Not wrapped | Source-language uniqueness |
| `wp_unique_term_slug` | WordPress | — | Not wrapped | Source-language uniqueness |
| `get_permalink` / `get_term_link` | WordPress | — | Prefixed via late `home_url` only | Paths use **source** slugs |
| `redirect_canonical` | WordPress | — | Blind suppress when language-prefixed ([`Router::filter_redirect_canonical`](../../../src/Routing/Router.php)) | SEO canonical emission is A.SEOb |
| Rewrite rules | WordPress | options / rewrite API | **None registered by AIML** (ADR-0002) | |

## 2. WooCommerce

| Surface | Owner | AIML today | Notes |
|---|---|---|---|
| Product permalink structure | WooCommerce | Respects WP + prefix | e.g. `/product/%postname%/` — **base** not translated |
| Product category / tag URLs | WooCommerce + WP terms | Source term slugs | Rewrite bases (`product-category`, etc.) ADR-0002 Deferred |
| Shop page | WooCommerce | Prefixed via Router | Page slug is WP `post_name` |
| Attribute archives | WooCommerce | Untouched | Bases + attribute taxonomies |

## 3. AIML

| Surface | Owner | Evidence |
|---|---|---|
| Language prefix strip/prefix | AIML Router | ADR-0002; `Router::resolve` / `filter_home_url` |
| Store overlays | AIML | `FORMAT_SLUG` constant exists; **no slug segments written** |
| Source types | AIML | `Store::SOURCE_POST` only — **no term source type** |
| Post field extract | AIML | `post_title`, `post_excerpt`, `post_content` only — **not `post_name`** |
| Preview URLs | AIML | `PreviewService::preview_url` — prefix + source permalink |
| Historical redirects | — | **Absent** |

## 4. Elementor / Blocksy

| Party | URL ownership finding |
|---|---|
| Elementor | Owns `_elementor_data` document content (ADR-0016). **No canonical URL / slug ownership.** |
| Blocksy / theme | May style chrome; **no evidence of owning `post_name` or rewrite rules.** Default: not SEO URL owners. |

## 5. Ownership freeze statement (investigation)

WordPress and WooCommerce remain canonical owners of slug persistence and permalink/rewrite structures. AIML may only overlay translated slug **values** in Store and participate in language-aware URL generation/lookup **if** admitted without annexing WP tables. No ownership theft.
