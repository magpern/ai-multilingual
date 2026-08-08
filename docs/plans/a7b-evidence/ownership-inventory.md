# A.7b — Live ownership inventory

**Date:** 2026-08-08  
**Baseline:** `main` @ `ef1a63563d553ab018a33498072e3cef5f03ccaf`  
**Site:** https://dev.biopentra.eu  
**Method:** WP-CLI hooks/meta + theme/plugin source (Age Gate may obscure raw HTML)

## Stack

| Piece | Live value |
|---|---|
| Theme | `blocksy-child` / parent **Blocksy** |
| Shop page | ID **3755**, template `elementor_header_footer`, Elementor builder |
| Elementor Theme Builder product-archive / archive | **0** templates |
| WooCommerce | 10.9.4 |
| biopentra-storefront | active (compose mount) |
| biopentra-loop-card | active (compose mount) |
| Woo layered nav widgets | options exist; **no configured instances** (`_multiwidget` only) |

## Context split

| Context | Chrome system |
|---|---|
| `/shop/` | Elementor page + storefront shortcodes + loop-card pagination patches |
| `/product-category/*`, `/product-tag/*`, product search | Native Woo loop hooks + Blocksy wrappers + loop-card product cards |

## Ownership matrix

| Surface | Example | Owner | Source / evidence | A.7b disposition |
|---|---|---|---|---|
| Shop H1 / intro / disclaimer | Research Products; research-use notice | **Elementor** (`e:`) | Shop 3755 `_elementor_data` | Defer (Elementor) |
| Shop search placeholder / SR | Search peptides… | **biopentra-storefront** | `[biopentra_shop_search]` → `shopping-v2-helpers.php` | Defer (storefront) |
| Shop category chips label | Shop by category | **biopentra-storefront** | `[biopentra_home_categories]` → `home-v2-helpers.php` | Defer (storefront) |
| Shop chip / filter term names | Recovery Support | **WP/Woo terms** (text) + Elementor/storefront chrome | Taxonomy names; A.7a C3–C6 | **Out of A.7b** (A.7a) |
| Shop taxonomy filter “All” | All | **Elementor Pro** | taxonomy-filter `first_item_title` | Defer (Elementor) |
| Shop load-more / empty-more | Load more products; No more products | **biopentra-loop-card** | `shop-loop-filter.php` patches Elementor loop-grid | Defer (loop-card) |
| Shop AJAX i18n | Could not update products… | **biopentra-loop-card** | `wp_localize_script` in `shop-loop-filter.php` | Defer (loop-card) |
| Product card CTAs / badges | Add to cart; Strength:; Quick add; Out of stock | **biopentra-loop-card** | `biopentra-loop-card.php` localize | Defer (loop-card) |
| Live search suggestions | Searching…; No matching products | **biopentra-loop-card** | localize + `shop-live-search.js` | Defer (loop-card) |
| Search refine bar | Refine your product search… | **biopentra-storefront** | `biopentra_storefront_search_v2_render_refine_bar` on `woocommerce_before_shop_loop` prio 3 | Defer (storefront) |
| Native result-count markup | Showing 1–12 of 54 results | **WooCommerce** template `loop/result-count.php`; Blocksy only adds visibility classes | Hooked `woocommerce_result_count` prio 20; Blocksy `loop-elements.php` wraps HTML classes | Candidate → **Deferred** (see dynamic-text: no pre-interpolation filter for Showing templates) |
| Native “sorted by …” SR fragment | Sorted by popularity | **WooCommerce** | Filter `woocommerce_catalog_orderedby` in `woocommerce_result_count()` | **Woo candidate** (static labels) |
| Native sort `<select>` option labels | Default sorting; Sort by price… | **WooCommerce** | Filter `woocommerce_catalog_orderby` → `loop/orderby.php` | **Woo candidate** |
| Sort option **values/keys** | `menu_order`, `price-desc` | **WooCommerce** functional | Same filter keys | **Never translate** |
| Native empty results message | No products were found matching your selection. | **WooCommerce** | `loop/no-products-found.php` via `wc_no_products_found` | Candidate → **Deferred** (string via `__()` inside template; no official data filter before emit) |
| Native pagination Prev/Next/Load More | Prev; Next; Load More; No more products to load | **Blocksy** | Replaces `loop/pagination.php` output via `blocksy_display_posts_pagination()` | Defer (Blocksy) |
| Woo layered nav / price filter chrome | Any {attr}; filter headings | **Unused** | Widget options empty of instances | Defer (not present) |
| Archive title / term description | Category name; term HTML | **A.7a** (`woocommerce_page_title` / term `p:` / post fields) | Existing A.7a | **Out of A.7b** |

## Hook evidence (`woocommerce_before_shop_loop`)

| Priority | Callback | Owner |
|---|---|---|
| 1 | `biopentra_loop_card_wc_prepare_canonical_loop` | loop-card |
| 3 | `biopentra_storefront_search_v2_render_refine_bar` | storefront |
| 10 | `woocommerce_output_all_notices` / inventory preload / `wc_setup_loop` | Woo / inventory plugin |
| 20 | `woocommerce_result_count` | Woo |
| 30 | `woocommerce_catalog_ordering` | Woo |

`woocommerce_after_shop_loop` prio 10: `woocommerce_pagination` (Woo hook; **Blocksy replaces template HTML**).

## Notes

- Child theme (`blocksy-child`) has **no** archive chrome overrides (checkout/PDP only).
- Do not infer Woo ownership from “appears on a Woo archive URL.”
