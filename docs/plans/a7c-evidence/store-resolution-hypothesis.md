# A.7c — Store resolution hypothesis

## Verdict

**PASS** for Supported CJ3 / CJ4 / CJ6 without Store redesign.

| Context | Queried object | `resolve_source_id` | Units host |
|---|---|---|---|
| Checkout / order-received | Checkout page post | Post ID | `wc_get_page_id('checkout')` |
| My Account endpoints | My Account page post | Post ID | `wc_get_page_id('myaccount')` |
| Cart page | Cart page post | Post ID | No A.7c Supported units on cart |
| Product / shop / archives | Existing A.7a/A.7b rules | Unchanged | Unchanged |

No bridge remapping required for Supported A.7c: pages are real posts.

## Rejected alternatives

| Idea | Why rejected |
|---|---|
| Shared-definition Store for coupon notices across cart+checkout | Requires Store/source-type redesign → **STOP/defer CJ5** |
| Duplicate identical `p:` rows under cart and checkout | Duplicate logical units / translator burden |
| Remap cart page resolve → checkout | Would break Elementor/`e:` overlays on cart page 82 |

## Technical anchor ≠ content owner

Workspace must label units as WooCommerce customer-journey chrome (`parent_context`), not as cart/checkout page body fields.
