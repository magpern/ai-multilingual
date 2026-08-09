# A.SEOe Evidence — Rank Math Analysis

**Version:** 1.0.275
**Modules:** `sitemap` active; `woocommerce` active; `news-sitemap` / `video-sitemap` inactive (PRO stubs)

---

## Options

Option key: `rank-math-options-sitemap`

Observed: pages/products/product_cat on; product_tag/category/post_tag off; `include_images=on`; `items_per_page=200`; excluded post IDs present; `robots_txt_content` empty.

---

## Providers

| Provider | Types |
|---|---|
| Post_Type | page, product, post, … |
| Taxonomy | product_cat (enabled), others per settings |
| Author | gated by indexability |
| Custom via `rank_math/sitemap/providers` | Exists — **A.SEOe must not use this to replace Rank Math** |

WooCommerce adjusts exclusions/archives/images via `includes/modules/woocommerce/class-sitemap.php` — not a parallel provider.

---

## Multilingual

Rank Math free emits **default-language** URLs only. WPML notice in module acknowledges hreflang is document-level, not sitemap. No built-in xhtml:link emission.

---

## A.SEOc / A.SEOd interaction

A.SEOc/A.SEOd hooks are frontend title/meta/OG/Twitter/schema — **orthogonal** to sitemap generation. Do not reopen those admissions. Sitemap cooperation extends `RankMathIntegration` or a tightly coupled helper under the same Integration API id `rankmath`.
