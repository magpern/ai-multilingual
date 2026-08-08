# A.6 — Shortcode analysis

Registered tags relevant to visitor chrome (live):

| Tag | Plugin | Visitor role | Disposition |
|---|---|---|---|
| `aiml_switcher` | AI Multilingual | Language switcher | Platform-owned (not A.6 translation content) |
| `blocksy_breadcrumbs` | Blocksy | Breadcrumb trail | Deferred (D4) |
| `blocksy_posts` | Blocksy | Posts listing helper | Out of A.6 admit (content/listing) |
| `fluentform` / `fluentform_info` | Fluent Forms | Forms | A.8 — out of A.6 |
| `biopentra_home_search` | storefront | Home product search UI | Deferred (D13) |
| `biopentra_home_categories` | storefront | Category chips | Deferred (D13) — labels may also be term titles (taxonomy lane) |
| `biopentra_shop_search` | storefront | Shop search UI | Deferred (D13); A.7b wrong-owner |
| `biopentra_search_refine` | storefront | Search refine bar | Deferred (D13) |
| `biopentra_wc_archive_link` | storefront | Archive CTA | Deferred (D13) |
| `biopentra_header_auth` | storefront | Header login chrome | Deferred / Elementor lane (D15) |
| `biopentra_footer_email` | storefront | Footer email link labels | Deferred (D13) |
| `biopentra_related_research_products` | storefront | Related products | Commerce content — not A.6 chrome admit |

---

## Roadmap lesson (§6.1)

Admit shortcode bridges **only** where an owner-declared deterministic identity exists.

Live storefront shortcodes render via `esc_*__()` inside PHP HTML builders — **no** dedicated string filters analogous to Fluent Forms `fluentform/rendering_field_data_*` or Woo `woocommerce_catalog_orderby`.

Therefore A.6 does **not** Supported-admit storefront/Blocksy shortcodes. Adding filters inside first-party plugins is an owner change outside this planning freeze; it may unlock a future admission without A.6 architecture change.
