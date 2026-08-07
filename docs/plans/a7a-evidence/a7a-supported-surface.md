# A.7a — Closure / Supported surface

**Milestone:** A.7a WooCommerce Product & Catalog  
**Status:** **Complete / merged / tagged** `a7a-woocommerce-product-catalog-complete`  
**Merge commit:** see validation log  

## Supported surface (final)

| # | Surface | Path |
|---|---|---|
| P1 | Product title | Existing `post_title` + Workspace `product` |
| P2 | Product short description | Existing `post_excerpt` |
| P3 | Product long description | Existing `post_content` (classic; `b:`/`e:` unchanged if present) |
| P5 | Attribute names | `p:woocommerce:product:{id}:attribute_name:{slug}` + `woocommerce_attribute_label` |
| P7 | Variation attribute names | `p:woocommerce:product:{id}:variation_attribute_name:{slug}` + same filter (P7 preferred) |
| C1 | Shop title | Shop page `post_title` |
| C2 | Shop description | Shop page `post_content` when non-empty |
| C3 | Category title | `p:woocommerce:product_cat:{id}:name` |
| C4 | Category description | `p:woocommerce:product_cat:{id}:description` |
| C5 | Tag title | `p:woocommerce:product_tag:{id}:name` |
| C6 | Tag description | `p:woocommerce:product_tag:{id}:description` |

## Deferred surface

| # | Reason |
|---|---|
| P4 | Tab titles are Woo shared i18n; body covered by P3 |
| P6 | Live custom attribute options lack stable IDs |
| P8 | Same as P6 |
| P9 | Notices are shared-definition i18n |
| P10 | Breadcrumb ownership not Woo-deterministic (theme) |

## Limitations / technical debt

- Live theme uses custom loop-card i18n for some “Strength:” chrome; official Woo `woocommerce_attribute_label` path is implemented and verified via filters.
- Age Gate + Elementor/Rank Math chrome may not surface every overlay in raw HTML scrapes; admitted hook surfaces verified EN→SV.
- Catalog term Store host is the shop page (no `SOURCE_TERM`).
- No A.7b listing chrome.

## Exact next step (planning only)

Plan **A.7b** Archives (shop / category / tag / search listing chrome) from tagged `main`. Do not start A.7b implementation until that plan freezes.
