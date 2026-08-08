# A.7c — Admission matrix (CJ1–CJ6)

**Frozen for planning and implementation.** Production may only code **Supported** rows.

Live baseline (dev.biopentra.eu): WooCommerce **10.9.4**; cart=`82`; checkout=`4506`; myaccount=`84`; shop=`3755`. Cart/checkout use classic shortcodes. Mini-cart owned by **biopentra-storefront**.

## Supported

| ID | Subsurface | Owner | Identity | Extract host | Overlay | Class | Disposition |
|---|---|---|---|---|---|---|---|
| **CJ3.1** | Checkout field labels | Woo | `p:woocommerce:checkout_field:{field_key}:label` | `wc_get_page_id('checkout')` | `woocommerce_checkout_fields` (mutate `label` only) | Static | **Supported** |
| **CJ3.2** | Place order button text | Woo | `p:woocommerce:checkout:order_button:label` | checkout page | `woocommerce_order_button_text` | Static | **Supported** |
| **CJ4.1** | My Account menu labels | Woo | `p:woocommerce:account_menu:{endpoint}:label` | `wc_get_page_id('myaccount')` | `woocommerce_account_menu_items` | Static | **Supported** |
| **CJ4.2** | Account endpoint titles | Woo | `p:woocommerce:endpoint:{endpoint}:title` | myaccount page | `woocommerce_endpoint_{endpoint}_title` | Static | **Supported** |
| **CJ6.1** | Thank-you received text | Woo | `p:woocommerce:checkout:thankyou_received:label` | checkout page | `woocommerce_thankyou_order_received_text` | Static | **Supported** |
| **CJ6.2** | Order item totals labels | Woo | `p:woocommerce:order_totals:{row_key}:label` | checkout page | `woocommerce_get_order_item_totals` (labels only; values untouched) | Static keys | **Supported** |

Allowlisted order_totals row keys: `cart_subtotal`, `shipping`, `discount`, `order_total`, `payment_method` (skip dynamic fee rows).

## Deferred (family groups)

| ID | Surface | Reason |
|---|---|---|
| **CJ1** | Cart table/totals chrome (headers, Apply/Update coupon, Proceed to checkout, Subtotal/Total th) | Gettext-only in Woo/Blocksy templates; no official pre-output label map |
| **CJ2** | Mini-cart empty/CTA/trust chrome | **biopentra-storefront** owns `cart/mini-cart.php` (priority 999); Elementor Menu Cart wraps UI |
| **CJ3.x** | Checkout section H3s (“Billing details”, “Your order”, …); coupon field placeholder/button | Gettext in templates |
| **CJ3.x** | Payment gateway iframe/card UI; BTCPay; shipping-plugin chrome | Wrong owner / sensitive |
| **CJ4.x** | Customer-entered account/order content | Runtime customer data — never translate as chrome |
| **CJ5** | Notices (add-to-cart HTML, coupon messages, validation) | Add-to-cart embeds product titles; coupon messages often interpolate amounts; multi-page shared-definition unresolved without Store redesign |
| **CJ6.x** | Thank-you overview labels (Order number/Date/Email/Total) | Gettext in `thankyou.php` |
| **CJ6.x** | Payment gateway thank-you output | Wrong owner |

## Store anchors (technical only)

| Host page | `wc_get_page_id` | Units |
|---|---|---|
| Checkout | `checkout` | CJ3.*, CJ6.* |
| My Account | `myaccount` | CJ4.* |

Queried object on these pages is the WP page post — existing `IntegrationFrontendBridge` post resolution applies. No shared-definition Store redesign.

Functional field keys / account endpoints remain canonical and untranslated.
