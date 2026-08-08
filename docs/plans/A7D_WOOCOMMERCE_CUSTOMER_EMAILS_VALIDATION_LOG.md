# A.7d — WooCommerce Customer Emails — Validation Log

**Milestone:** A.7d WooCommerce Customer Emails
**Implementation branch:** `feature/a7d-woocommerce-customer-emails`
**Plan:** [A7D_WOOCOMMERCE_CUSTOMER_EMAILS_IMPLEMENTATION_PLAN.md](A7D_WOOCOMMERCE_CUSTOMER_EMAILS_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**)
**Language ADR:** [ADR-0018](../adr/0018-woocommerce-order-transactional-language-context.md) (**Accepted**)
**Baseline main:** `48985be3395c8e9baa99260d80395e044584a18d`
**Merged / tagged:** **Not in this task** — ready for independent review

---

## A7D.0 — Baseline + inventory

**Status:** PASS

| Check | Result |
|---|---|
| Plan status | Architecture Frozen; implementation authorized |
| ADR-0018 | Accepted |
| ADR-0001/0007/0013/0016/0017 | Accepted |
| TARGET | 6 |
| Woo live | 10.9.4 |
| Supported CE | CE1–CE6, CE9–CE10 subject+heading |
| Deferred | CE7/CE8, body gettext, global footer, POS/CE11–15 |

---

## A7D.1 — Language provenance

**Status:** PASS — ADR-0018 Accepted prior to coding.

---

## A7D.2 — Snapshot capture + identity

**Status:** PASS

- Class: `OrderTransactionalLanguage` (`_aiml_transactional_language`)
- Capture hooks: `woocommerce_checkout_order_processed`, `woocommerce_store_api_checkout_order_processed`
- Extract: 16 email units on checkout host `4506`
- Identities: `p:woocommerce:email:{id}:subject|heading` via `PluginIdentity::build`

---

## A7D.3 — Subject/heading overlays

**Status:** PASS

- Class: `CustomerEmailBridge`
- Filters: `woocommerce_email_subject_{id}` / `woocommerce_email_heading_{id}` for allowlist
- Resolve via ADR-0018 + `LanguageContext::with()`; checkout Store host; `format_string` for placeholders
- Live: subject filter registered for `customer_processing_order`

---

## A7D.4 / A7D.5 — Deferred

**Status:** PASS (Deferred as designed) — [deferred-surfaces.md](a7d-evidence/deferred-surfaces.md)

---

## A7D.6 — Platform / lifecycle / diagnostics

**Status:** PASS

- Bounded counters: `snapshot_*`, `transactional_language_resolved`, `source_language_fallback`, `context_restored`, plus IntegrationDiagnostics overlay counters
- No PII in diagnostics; no schema bump; no `wp_mail` interception

---

## A7D.7 — Full acceptance

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | **586** tests / **1559** assertions (2 skipped) |
| Integration | **512** tests / **11829** assertions (2 skipped) |
| PluginGuard | **17** / **8836** assertions |
| PHPCS (A.7d files) | **0 errors** |
| Live extract | checkout=4506; **16** email units |
| Subject filter registered | yes |
| `wp_mail` interception | **absent** |
| TARGET | **6** |
| Language leakage (unit) | **0** (restore after with / exception) |
| Email false positives (unit) | **0** |

### Regressions

Gutenberg / Elementor / Fluent Forms / A.7a–A.7c exercised by full unit+integration suites — **PASS**.

---

## A7D.8 — Documentation closure

**Status:** PASS — this log; awaiting operator merge/tag.

**Recommended tag (after merge):** `a7d-woocommerce-customer-emails-complete`

**Exact next milestone (Product Priorities):** **A.6** Remaining WordPress visitor chrome (after A.7d merge).

---

## Architecture audit

| Invariant | Result |
|---|---|
| Overlay not duplication | PASS |
| PluginIdentity `p:woocommerce` only | PASS |
| No new identity family | PASS |
| ADR-0018 language ladder | PASS |
| No Store/schema redesign | PASS |
| No generic email interception | PASS |
| CE7/CE8 Deferred independently | PASS |
