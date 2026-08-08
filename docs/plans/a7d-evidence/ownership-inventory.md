# A.7d — Ownership inventory (WooCommerce customer emails)

**Milestone:** A.7d planning  
**WooCommerce (live):** `10.9.4`  
**HPOS:** enabled  
**Baseline:** `main` @ `d936addf81859d27047e192f1b6a6e139a45e746`  
**Date:** 2026-08-08

## Scope rule

A.7d covers **WooCommerce-core customer email chrome** only (subjects, headings, static body labels/fragments that Woo owns and exposes via official filters/templates).

**Producer owns translation responsibility** (AIML overlays Woo-owned strings; Woo remains the email producer).

## Explicit exclusions (not A.7d)

| Surface | Owner | Disposition |
|---|---|---|
| Generic `wp_mail` interception | WordPress / any plugin | **Out of scope** — never |
| Woo admin / merchant emails (`new_order`, `cancelled_order`, `failed_order`, `admin_payment_gateway_enabled`, …) | WooCommerce (merchant) | **Exclude** |
| VCCP payment emails (`vccp_*`) | BTCPay / VCCP plugin | **Exclude** |
| MP Commerce / third-party fulfillment mail | Third party | **Exclude** |
| Payment-provider customer mail | Gateways | **Exclude** |
| Fluent Forms notifications | Fluent Forms | **Exclude** (A.8) |
| WordPress core mail (password, etc. outside WC_Email) | WordPress | **Exclude** |
| Newsletter / marketing automation | Third party | **Exclude** |
| Third-party invoice / tracking HTML injected into Woo templates | Third party | **Exclude** — do not scrape |
| Product/catalog content already owned by A.7a | AIML / Woo product | **Reuse A.7a** — do not duplicate into email identities |
| Global email header/footer option text requiring new shared-definition Store ownership | Woo settings / shared-definition | **Defer** until Store host proven (see plan § Global email settings) |

## Live customer-email classes (installed WC 10.9.4)

| CE | Email ID | Class | Enabled (live) | Order-based | Owner |
|---|---|---|---|---|---|
| **CE1** | `customer_processing_order` | `WC_Email_Customer_Processing_Order` | on | yes | WooCommerce core |
| **CE2** | `customer_completed_order` | `WC_Email_Customer_Completed_Order` | on | yes | WooCommerce core |
| **CE3** | `customer_on_hold_order` | `WC_Email_Customer_On_Hold_Order` | on | yes | WooCommerce core |
| **CE4** | `customer_invoice` | `WC_Email_Customer_Invoice` | off | yes | WooCommerce core |
| **CE5** | `customer_note` | `WC_Email_Customer_Note` | on | yes | WooCommerce core |
| **CE6** | `customer_refunded_order` | `WC_Email_Customer_Refunded_Order` | on | yes | WooCommerce core |
| **CE7** | `customer_new_account` | `WC_Email_Customer_New_Account` | on | **no** | WooCommerce core |
| **CE8** | `customer_reset_password` | `WC_Email_Customer_Reset_Password` | on | **no** | WooCommerce core |
| **CE9** | `customer_failed_order` | `WC_Email_Customer_Failed_Order` | on | yes | WooCommerce core |
| **CE10** | `customer_cancelled_order` | `WC_Email_Customer_Cancelled_Order` | off | yes | WooCommerce core |
| **CE11** | `customer_pos_completed_order` | `WC_Email_Customer_POS_Completed_Order` | off | yes (POS) | WooCommerce core (POS) |
| **CE12** | `customer_pos_refunded_order` | `WC_Email_Customer_POS_Refunded_Order` | off | yes (POS) | WooCommerce core (POS) |

### Package-present but not registered on this install

Class files exist under `woocommerce/includes/emails/` for fulfillment / review / partial-refund variants. They were **not** returned by `WC()->mailer()->get_emails()` on live `10.9.4` (feature/registration gated). Inventory IDs reserved:

| CE | Intended ID | Class file | Live registered | Notes |
|---|---|---|---|---|
| **CE13** | `customer_fulfillment_*` | `class-wc-email-customer-fulfillment-*.php` | **N** | Defer until registered + ownership proven |
| **CE14** | `customer_partially_refunded_order` | `class-wc-email-customer-partially-refunded-order.php` | **N** | Defer |
| **CE15** | `customer_review_request` | `class-wc-email-customer-review-request.php` | **N** | Defer |

## Ownership class per candidate chrome

| Layer | Ownership class | Notes |
|---|---|---|
| Default / configured subject | Woo email option + `woocommerce_email_subject_{id}` | Filterable; AIML may overlay via Integration API |
| Default / configured heading | Woo email option + `woocommerce_email_heading_{id}` | Same |
| Template static gettext (`Hi %s,`, intro sentences) | Woo gettext / template | Prefer official filters; gettext-only → Deferred (A.7c lesson) |
| Order line items / product titles | Product/catalog (A.7a) + runtime | **Reuse A.7a**; do not invent email product keys |
| Prices, quantities, addresses, order numbers | Runtime business data | **Not translation units** |
| Customer note body / reset key / PII | Runtime / PII | **Never** Store / identity / diagnostics |
| `woocommerce_email_footer_text` / header branding | Woo global options | Shared-definition risk → **Defer** unless Store host proven without redesign |
| Third-party content hooked into `woocommerce_email_order_details` | Foreign | **Exclude** |

## Preliminary disposition (ownership only — language gate separate)

| CE | Ownership preliminary | Language-gate note |
|---|---|---|
| CE1–CE6, CE9–CE10 | **Candidate** (Woo-owned order emails) | Blocked on deterministic language provenance |
| CE7–CE8 | **Candidate** (Woo-owned non-order) | Weaker provenance; may defer independently |
| CE11–CE12 | **Deferred** (POS niche) | Out of first admission |
| CE13–CE15 | **Deferred** (not live-registered) | Re-inventory if Woo enables |
| VCCP / admin | **Exclude** | Wrong owner |

## Evidence method

- Live: `WC()->mailer()->get_emails()` via WP-CLI on `dev.biopentra.eu` stack  
- Package: `woocommerce/includes/emails/class-wc-email-customer-*.php`  
- No AIML email hooks exist in `WooCommerceIntegration` today (confirmed by planning inventory)
