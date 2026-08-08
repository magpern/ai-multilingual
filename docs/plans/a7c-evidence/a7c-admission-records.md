# A.7c — Final admission records (Supported only)

## CJ3.1 — Checkout field labels

| Field | Value |
|---|---|
| Owner | WooCommerce |
| Identity | `p:woocommerce:checkout_field:{field_key}:label` |
| Hook | `woocommerce_checkout_fields` |
| Store anchor | `wc_get_page_id('checkout')` |
| Overlay | Mutate field `label` only; keys unchanged |
| Sanitization | Plain |
| Disposition | **Supported** |

## CJ3.2 — Place order button

| Field | Value |
|---|---|
| Identity | `p:woocommerce:checkout:order_button:label` |
| Hook | `woocommerce_order_button_text` |
| Disposition | **Supported** |

## CJ4.1 — Account menu

| Field | Value |
|---|---|
| Identity | `p:woocommerce:account_menu:{endpoint}:label` |
| Hook | `woocommerce_account_menu_items` |
| Store anchor | `wc_get_page_id('myaccount')` |
| Disposition | **Supported** |

## CJ4.2 — Endpoint titles

| Field | Value |
|---|---|
| Identity | `p:woocommerce:endpoint:{endpoint}:title` |
| Hook | `woocommerce_endpoint_{endpoint}_title` |
| Disposition | **Supported** |

## CJ6.1 — Thank-you received text

| Field | Value |
|---|---|
| Identity | `p:woocommerce:checkout:thankyou_received:label` |
| Hook | `woocommerce_thankyou_order_received_text` |
| Disposition | **Supported** |

## CJ6.2 — Order totals labels

| Field | Value |
|---|---|
| Identity | `p:woocommerce:order_totals:{row_key}:label` |
| Hook | `woocommerce_get_order_item_totals` |
| Allowlist | `cart_subtotal`, `shipping`, `discount`, `order_total`, `payment_method` |
| Disposition | **Supported** |

## Deferred families

CJ1 (cart gettext), CJ2 (storefront mini-cart), CJ5 (notices / shared-definition).
