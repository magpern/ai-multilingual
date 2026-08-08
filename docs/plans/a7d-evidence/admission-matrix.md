# A.7d — Admission matrix (planning)

**Status:** **Not Architecture Frozen** — language provenance gate **Blocked** ([language-provenance.md](language-provenance.md)).

Dispositions below are **conditional candidates** for a future freeze **after** a language-context ADR. None are Supported for implementation today.

## Disposition legend

| Disposition | Meaning |
|---|---|
| **Blocked (provenance)** | Woo-owned chrome candidate, but customer language cannot be resolved safely |
| **Deferred** | Wrong seam, niche, gettext-only, shared-definition, or not live-registered |
| **Exclude** | Wrong owner / hard out of scope |
| **Supported** | *Reserved — none until Architecture Frozen* |

## CE matrix

| ID | Surface | Order? | HTML/plain | Identity hypothesis (not frozen) | Disposition |
|---|---|---|---|---|---|
| CE1 | Processing Order | Y | both templates exist | `p:woocommerce:email:customer_processing_order:subject\|heading` (+ body tokens if filter-proven) | **Blocked (provenance)** |
| CE2 | Completed Order | Y | both | `…:customer_completed_order:…` | **Blocked (provenance)** |
| CE3 | On-Hold Order | Y | both | `…:customer_on_hold_order:…` | **Blocked (provenance)** |
| CE4 | Customer Invoice / Order Details | Y | both | `…:customer_invoice:…` | **Blocked (provenance)** |
| CE5 | Customer Note | Y | both | chrome only; note body = runtime/PII | **Blocked (provenance)** |
| CE6 | Refunded Order | Y | both | `…:customer_refunded_order:…` | **Blocked (provenance)** |
| CE7 | New Account | N | both | `…:customer_new_account:…` | **Blocked (provenance)** — may **Defer** independently after order ADR |
| CE8 | Reset Password | N | both | `…:customer_reset_password:…` | **Blocked (provenance)** — may **Defer** independently |
| CE9 | Failed Order | Y | both | `…:customer_failed_order:…` | **Blocked (provenance)** — secondary to CE1–CE6 |
| CE10 | Cancelled Order | Y | both | `…:customer_cancelled_order:…` | **Blocked (provenance)** — secondary |
| CE11 | POS Completed | Y | both | — | **Deferred** (POS niche) |
| CE12 | POS Refunded | Y | both | — | **Deferred** (POS niche) |
| CE13–CE15 | Fulfillment / partial refund / review | — | — | — | **Deferred** (not live-registered) |
| — | VCCP / admin / Fluent / WP core / marketing | — | — | — | **Exclude** |
| — | Global email footer/header option text | shared | — | shared-definition risk | **Deferred** |
| — | Template gettext body sentences without filters | — | — | — | **Deferred** |
| — | Product titles inside email tables | — | — | A.7a identities | **Reuse A.7a** (not new A.7d keys) |

## Identity matrix rules (for future freeze)

Use existing **`PluginIdentity`** / integration id **`woocommerce`** only. **No `email:` family.**

| Allowed in key | Forbidden in key |
|---|---|
| Stable Woo email `id` token (`customer_processing_order`) | Order ID, customer ID |
| Stable role token (`subject`, `heading`, `body:{token}`) | Customer name, email address |
| Functional field tokens | Rendered HTML / full sentences as identity |
| | Current request URL |
| | Runtime prices, SKUs, coupon codes |

Source hash = freshness only (ADR-0007).

### Example keys (hypothesis — not frozen)

```
p:woocommerce:email:customer_processing_order:subject
p:woocommerce:email:customer_processing_order:heading
p:woocommerce:email:customer_processing_order:body:intro_hi
```

Build only via `PluginIdentity::build()`.

## HTML / plain admission rule

| Claim | Rule |
|---|---|
| Supported | Both variants overlayed **or** explicit Partial |
| Partially Supported | Matrix states which variant |
| Deferred | Variant unproven |

## Runtime-value policy

Never admit as email chrome:

- order numbers, names, addresses, phones, emails  
- prices, quantities, product IDs, coupon codes  
- payment values, tracking numbers  
- reset keys, raw order notes, raw customer email bodies  

## Store strategy (hypothesis — blocked)

| Unit class | Likely technical host | Risk |
|---|---|---|
| Per-email-type subject/heading | Shared across orders — may need shared-definition **or** a stable non-order Store anchor | A.8 shared-definition lesson |
| Order-scoped product lines | Product posts (A.7a) | Reuse |
| Global footer text | Woo options | **Defer** |

Do **not** redesign Store. Do **not** bump schema for A.7d email chrome alone. Language snapshot persistence (if ADR requires) is a **separate** contract from Store segments.

## Platform reuse (unchanged)

Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Diagnostics, `WooCommerceIntegration`, Integration API v1.

**No Email Translation subsystem.**
