# A.7c — Live ownership inventory

**Date:** 2026-08-08  
**Site:** https://dev.biopentra.eu  
**WooCommerce:** 10.9.4  

## Configured pages

| Role | ID | Notes |
|---|---|---|
| Cart | 82 | `[woocommerce_cart]`; Elementor builder meta present but shortcode cart used |
| Checkout | 4506 | `[woocommerce_checkout]`; classic |
| My Account | 84 | Elementor builder meta; Woo endpoints under this page |
| Shop | 3755 | A.7a/A.7b technical host |

`woocommerce_coming_soon=yes` (store-only) may hide storefront for guests; inventory uses WP-CLI + template/hook evidence.

## By surface

### CJ1 Cart

| String class | Owner | Hook? | Notes |
|---|---|---|---|
| Column headers | Woo/Blocksy template gettext | No label filter | Deferred |
| Apply/Update coupon | Template gettext | No | Deferred |
| Proceed to checkout | Template gettext | No | Deferred |
| Remove-item link | Woo HTML filter | `woocommerce_cart_item_remove_link` | HTML; defer for A.7c |
| Cart totals H2 / Subtotal th | Gettext | No | Deferred |

### CJ2 Mini cart

| String class | Owner | Notes |
|---|---|---|
| Empty / trust / CTA | **biopentra-storefront** | `woocommerce_locate_template` @ 999 → custom mini-cart |
| Elementor Menu Cart chrome | Elementor Pro | Wrong owner |
| Blocksy fragments | Blocksy | Overridden by storefront |

### CJ3 Checkout

| String class | Owner | Hook | Notes |
|---|---|---|---|
| Field labels | Woo | `woocommerce_checkout_fields` | **Supported** |
| Place order | Woo | `woocommerce_order_button_text` | **Supported** |
| Section headings | Gettext | — | Deferred |
| Gateway titles | Woo filter exists | `woocommerce_gateway_title` | Deferred this wave (payment UX risk) |

### CJ4 My Account

| String class | Owner | Hook | Notes |
|---|---|---|---|
| Nav labels | Woo | `woocommerce_account_menu_items` | **Supported** (live includes `gift-cards` from extension) |
| Endpoint titles | Woo | `woocommerce_endpoint_{endpoint}_title` | **Supported** for menu endpoints |
| Order/address content | Customer data | — | Never |

### CJ5 Notices

| String class | Owner | Notes |
|---|---|---|
| Add-to-cart message | Woo filter + product title | Dynamic; defer |
| Coupon messages | Woo by code | Shared-definition / interpolation; defer |
| Template shells | Woo | No label filter |

### CJ6 Order received

| String class | Owner | Hook | Notes |
|---|---|---|---|
| Received text | Woo | `woocommerce_thankyou_order_received_text` | **Supported** |
| Totals row labels | Woo | `woocommerce_get_order_item_totals` | **Supported** (labels) |
| Overview labels | Gettext | — | Deferred |
