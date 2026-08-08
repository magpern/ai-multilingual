# A.7d — Hook / template inventory

**WooCommerce:** `10.9.4`  
**Base class:** `WC_Email` (`includes/emails/class-wc-email.php`)

## Subject / heading (official)

| Hook pattern | Signature (approx.) | Applies to |
|---|---|---|
| `woocommerce_email_subject_{$email->id}` | `( $subject, $object, $email )` | All WC_Email subclasses via `get_subject()` |
| `woocommerce_email_heading_{$email->id}` | `( $heading, $object, $email )` | All via `get_heading()` |

Subjects/headings are also stored as Woo email settings (options / transients) and passed through `format_string()` placeholders (`{site_title}`, `{order_number}`, …). **Placeholders are runtime** — translate the chrome template, not the substituted values.

Special cases (same family; still `{id}`-scoped filters):

- Invoice / refund emails may expose additional option keys; still use `woocommerce_email_subject_{id}` / `heading_{id}` as the overlay seam.

## Per-CE templates (HTML + plain)

| CE | ID | HTML template | Plain template |
|---|---|---|---|
| CE1 | `customer_processing_order` | `emails/customer-processing-order.php` | `emails/plain/customer-processing-order.php` |
| CE2 | `customer_completed_order` | `emails/customer-completed-order.php` | `emails/plain/customer-completed-order.php` |
| CE3 | `customer_on_hold_order` | `emails/customer-on-hold-order.php` | `emails/plain/customer-on-hold-order.php` |
| CE4 | `customer_invoice` | `emails/customer-invoice.php` | `emails/plain/customer-invoice.php` |
| CE5 | `customer_note` | `emails/customer-note.php` | `emails/plain/customer-note.php` |
| CE6 | `customer_refunded_order` | `emails/customer-refunded-order.php` | `emails/plain/customer-refunded-order.php` |
| CE7 | `customer_new_account` | `emails/customer-new-account.php` | `emails/plain/customer-new-account.php` |
| CE8 | `customer_reset_password` | `emails/customer-reset-password.php` | `emails/plain/customer-reset-password.php` |
| CE9 | `customer_failed_order` | `emails/customer-failed-order.php` | `emails/plain/customer-failed-order.php` |
| CE10 | `customer_cancelled_order` | `emails/customer-cancelled-order.php` | `emails/plain/customer-cancelled-order.php` |
| CE11 | `customer_pos_completed_order` | POS HTML | POS plain |
| CE12 | `customer_pos_refunded_order` | POS HTML | POS plain |

Theme/plugin template overrides: none found under biopentra-storefront / active theme `woocommerce/emails/` at planning time. If overrides appear later, ownership must be re-checked (theme may steal the surface).

## Shared template actions (order emails)

Typical processing-order HTML flow:

| Hook | Role | A.7d stance |
|---|---|---|
| `woocommerce_email_header` | Renders heading chrome | Heading via `get_heading()` filter; header chrome mostly global |
| `woocommerce_email_order_details` | Order table / items | Product titles → **A.7a reuse**; labels only if Woo filterable |
| `woocommerce_email_order_meta` | Meta rows | Runtime / foreign risk |
| `woocommerce_email_customer_details` | Addresses | **PII / runtime — never translate as units** |
| `woocommerce_email_footer` | Footer | Global option text → **Defer** if shared-definition Store needed |
| Per-email `additional_content` | Merchant-configured HTML | Treat as merchant content; admission only if identity + Store host safe |

## Body / static-fragment hooks

Most body sentences are **`esc_html__()` / `printf` gettext inside templates**, not dedicated string filters. Planning rule (from A.7c):

- Prefer official filters → Candidate  
- Gettext-only with no stable filter → **Deferred** (do not invent `gettext` capture for A.7d)  
- Do not scrape rendered HTML/plain bodies

Hypothesis for later admission (not frozen — blocked on language ADR first):

| Fragment class | Likely seam | Preliminary |
|---|---|---|
| Subject | `woocommerce_email_subject_{id}` | Strong candidate |
| Heading | `woocommerce_email_heading_{id}` | Strong candidate |
| Greeting / intro gettext | Template gettext | Deferred unless Woo adds filter |
| Button labels in templates | Rare / gettext | Deferred |
| Order totals row labels inside email | May share `woocommerce_get_order_item_totals` | Reuse A.7c CJ6.2 if same filter fires in email context — **must re-prove** before admitting |

## Default subject / heading samples (EN source chrome)

Sanitized — no PII:

| CE | Default subject (chrome) | Default heading (chrome) |
|---|---|---|
| CE1 | Your {site_title} order has been received! | Thank you for your order |
| CE2 | Your order from {site_title} is on its way! | Good things are heading your way! |
| CE3 | Your {site_title} order has been received! | Thank you for your order |
| CE4 | Details for order #{order_number} on {site_title} | Details for order #{order_number} |
| CE5 | A note has been added to your order from {site_title} | A note has been added to your order |
| CE6 | Your {site_title} order #{order_number} has been refunded | Order refunded: {order_number} |
| CE7 | Your {site_title} account has been created! | Welcome to {site_title} |
| CE8 | Reset your password for {site_title} | Reset your password |
| CE9 | Your order at {site_title} was unsuccessful | Sorry, your order was unsuccessful |
| CE10 | [{site_title}]: Your order #{order_number} has been cancelled | Order cancelled: #{order_number} |

## Triggers (order-based examples)

CE1 (`customer_processing_order`) listens to status-transition notification actions such as:

- `woocommerce_order_status_pending_to_processing_notification`
- `woocommerce_order_status_on-hold_to_processing_notification`
- `woocommerce_order_status_failed_to_processing_notification`
- `woocommerce_order_status_cancelled_to_processing_notification`

These fire in the request that changes status **or** in deferred/admin contexts — language provenance must cover both (see [language-provenance.md](language-provenance.md)).

CE4 invoice / CE5 note / resend paths are admin- or customer-initiated and frequently **not** on the customer’s storefront URL.

## HTML / plain policy (planning)

| Admission claim | Requirement |
|---|---|
| **Supported** | Both HTML and plain receive the same overlay for admitted chrome, **or** limitation is explicit in admission matrix |
| **Partially Supported** | Only one variant proven; matrix must say which |
| **Deferred** | Variant not proven / gettext-only / unsafe |

Never mark an email fully Supported while silently ignoring a material plain-text variant.

## Third-party injection exposure

Any content injected via `woocommerce_email_order_details` / `order_meta` / gateway hooks is **foreign**. A.7d must not scrape or invent identities for it. Diagnostics must count misses, not bodies.
