# A.SEOc Evidence — WooCommerce Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `488e62f93`

---

## Module

Rank Math `woocommerce` module active. Hooks include `rank_math/frontend/description` and robots integrations plus OpenGraph/sitemap subclasses.

## Product SEO persistence

Same postmeta keys as posts: `rank_math_title`, `rank_math_description`, …  
Woo-specific **variables**: `%wc_price%`, `%wc_sku%`, `%wc_shortdesc%`, `%wc_brand%` — not separate meta keys on this install.

## Live product (`bpc-157`)

- Explicit filled SEO title + description (English on SV URL)  
- Schema Product `name` follows English SEO title  
- Breadcrumb may show translated Woo title — content ownership ≠ SEO ownership  

## Site options (inventory)

`wc_remove_product_base` / category base / parent slugs **off** (ADR-0002 alignment — A.SEOc must not reopen).  
Snippet removals for shop/cat/tag data enabled on this site.

## Admission implication

SC3/SC4 Supported under the same explicit-field overlay policy as SC1/SC2. Do not translate `%wc_price%` / `%wc_sku%`. `%wc_shortdesc%` inherits product short-description overlays from A.7a where present.
