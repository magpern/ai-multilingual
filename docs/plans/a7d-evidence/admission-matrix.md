# A.7d — Admission matrix (Architecture Frozen)

**Status:** **Architecture Frozen** — language provenance gate **Pass** via [ADR-0018](../../adr/0018-woocommerce-order-transactional-language-context.md).
Prior planning evidence: [language-provenance.md](language-provenance.md).

## Disposition legend

| Disposition | Meaning |
|---|---|
| **Supported** | Admitted for A.7d implementation (subject to AC + ADR-0018 language contract) |
| **Deferred** | Wrong seam, niche, gettext-only, shared-definition, non-order provenance, or not live-registered |
| **Exclude** | Wrong owner / hard out of scope |

## Final CE matrix

| ID | Surface | Order? | Admitted chrome | HTML/plain | Identity (frozen) | Disposition |
|---|---|---|---|---|---|---|
| **CE1** | Processing Order | Y | subject + heading | **Supported in both** (via `WC_Email::get_subject` / `get_heading`) | `p:woocommerce:email:customer_processing_order:subject` / `:heading` | **Supported** |
| **CE2** | Completed Order | Y | subject + heading | Supported in both | `…:customer_completed_order:subject` / `:heading` | **Supported** |
| **CE3** | On-Hold Order | Y | subject + heading | Supported in both | `…:customer_on_hold_order:subject` / `:heading` | **Supported** |
| **CE4** | Customer Invoice | Y | subject + heading | Supported in both | `…:customer_invoice:subject` / `:heading` | **Supported** |
| **CE5** | Customer Note | Y | subject + heading only (note body = runtime/PII) | Supported in both | `…:customer_note:subject` / `:heading` | **Supported** |
| **CE6** | Refunded Order | Y | subject + heading | Supported in both | `…:customer_refunded_order:subject` / `:heading` | **Supported** |
| **CE7** | New Account | N | — | — | — | **Deferred** (ADR-0018 does not cover non-order) |
| **CE8** | Reset Password | N | — | — | — | **Deferred** (ADR-0018 does not cover non-order) |
| **CE9** | Failed Order | Y | subject + heading | Supported in both | `…:customer_failed_order:subject` / `:heading` | **Supported** |
| **CE10** | Cancelled Order | Y | subject + heading | Supported in both | `…:customer_cancelled_order:subject` / `:heading` | **Supported** |
| CE11–CE12 | POS emails | Y | — | — | — | **Deferred** (POS niche) |
| CE13–CE15 | Fulfillment / partial / review | — | — | — | — | **Deferred** (not live-registered) |
| — | VCCP / admin / Fluent / WP core / marketing | — | — | — | — | **Exclude** |
| — | Global email footer/header option text | shared | — | — | — | **Deferred** (shared-definition Store risk) |
| — | Template gettext body sentences | — | — | — | — | **Deferred** (no dedicated filter) |
| — | Product titles inside email tables | — | — | — | A.7a | **Reuse A.7a** |

**Body labels/fragments:** remain **Deferred** until a filter-proven token exists; do not scrape templates. A7D.4 may admit additional tokens only with new evidence — not part of the frozen Supported set above.

## Language contract (frozen)

Per [ADR-0018](../../adr/0018-woocommerce-order-transactional-language-context.md):

- Meta key: `_aiml_transactional_language`
- Capture once at visitor order creation; immutable by default
- Resolve: valid snapshot → (no second source) → source/default
- Switch via `LanguageContext::with()` / guaranteed restore
- Forbidden: active locale at send, admin locale, country/currency guess

## Identity matrix rules (frozen)

Use existing **`PluginIdentity`** / integration id **`woocommerce`** only. **No `email:` family.**

| Allowed in key | Forbidden in key |
|---|---|
| Stable Woo email `id` token (`customer_processing_order`) | Order ID, customer ID |
| Role token `subject` or `heading` | Customer name, email address |
| | Rendered HTML / full sentences as identity |
| | Current request URL |
| | Runtime prices, SKUs, coupon codes |

Source hash = freshness only (ADR-0007).

### Frozen keys (Supported set)

```
p:woocommerce:email:customer_processing_order:subject
p:woocommerce:email:customer_processing_order:heading
p:woocommerce:email:customer_completed_order:subject
p:woocommerce:email:customer_completed_order:heading
p:woocommerce:email:customer_on_hold_order:subject
p:woocommerce:email:customer_on_hold_order:heading
p:woocommerce:email:customer_invoice:subject
p:woocommerce:email:customer_invoice:heading
p:woocommerce:email:customer_note:subject
p:woocommerce:email:customer_note:heading
p:woocommerce:email:customer_refunded_order:subject
p:woocommerce:email:customer_refunded_order:heading
p:woocommerce:email:customer_failed_order:subject
p:woocommerce:email:customer_failed_order:heading
p:woocommerce:email:customer_cancelled_order:subject
p:woocommerce:email:customer_cancelled_order:heading
```

Build only via `PluginIdentity::build()`.

## HTML / plain admission rule

Subject/heading overlays apply through `WC_Email` getters used by **both** HTML and plain sends → **Supported in both** for the frozen set. Body variants remain Deferred.

## Runtime-value policy

Never admit as email chrome: order numbers, names, addresses, phones, emails, prices, quantities, product IDs, coupon codes, payment values, tracking numbers, reset keys, raw order notes, raw customer email bodies.

## Store strategy (frozen for first wave)

| Unit class | Technical host | Notes |
|---|---|---|
| Admitted email subject/heading | `wc_get_page_id('checkout')` | **Technical anchor only** (A.7b/A.7c pattern) — not a claim that checkout content owns Woo email options |
| Product lines in emails | Product posts | **A.7a reuse** |
| Global footer text | — | **Deferred** |
| Language snapshot | Order meta `_aiml_transactional_language` | ADR-0018 — **not** a Store segment |

Do **not** redesign Store. Do **not** bump schema for A.7d email chrome. Snapshot persistence is separate from Store segments.

## Platform reuse (unchanged)

Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Diagnostics, `WooCommerceIntegration`, Integration API v1.

**No Email Translation subsystem.**
