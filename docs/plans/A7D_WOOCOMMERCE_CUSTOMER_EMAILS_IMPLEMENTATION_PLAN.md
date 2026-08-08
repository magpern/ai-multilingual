# A.7d — WooCommerce Customer Emails — Implementation Plan

**Status:** **Architecture Frozen** — implementation authorized
**Parent family plan:** [A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md](A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md)
**Prior wave:** [A7C_WOOCOMMERCE_CUSTOMER_JOURNEY_IMPLEMENTATION_PLAN.md](A7C_WOOCOMMERCE_CUSTOMER_JOURNEY_IMPLEMENTATION_PLAN.md) — **Complete** (`a7c-woocommerce-customer-journey-complete`)
**Milestone:** Program A — **A.7d** Customer Emails (Woo-owned only)
**Plan freeze:** Visitor-facing **WooCommerce-owned** customer email **subject + heading** chrome for admitted order-backed emails; language via [ADR-0018](../adr/0018-woocommerce-order-transactional-language-context.md); reuse Integration API v1 + existing `WooCommerceIntegration`; TARGET **6**
**ADR assessment:** **ADR-0018 Accepted** — order transactional language snapshot. **No new identity-family ADR.**
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.2 / A.7 family
**Planning branch:** `feature/a7d-woocommerce-customer-emails-plan` (merged)
**Implementation branch:** `feature/a7d-woocommerce-customer-emails`
**Baseline (plan authoring):** `main` @ `d936addf81859d27047e192f1b6a6e139a45e746`
**Architecture freeze:** after ADR-0018 on `main`
**Depends on:** A.7 family; A.7a+A.7b+A.7c complete; ADR-0001/0007/0013/0016/0017/0018 **Accepted**; Integration API v1; schema TARGET **6**
**Evidence:** [ownership-inventory.md](a7d-evidence/ownership-inventory.md); [admission-matrix.md](a7d-evidence/admission-matrix.md); [language-provenance.md](a7d-evidence/language-provenance.md); [hook-template-inventory.md](a7d-evidence/hook-template-inventory.md)
**Product direction:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md) — A.7d after A.7c; then A.6

**Operational success:** Customer-facing WooCommerce-core emails render AIML-approved **subject/heading** chrome in the **customer’s transactional language** (ADR-0018) for sync, async, status-change, and resend paths — language leakage = 0; email false positives = 0.

**This plan is the frozen implementation contract for A.7d.** Coding starts only on `feature/a7d-woocommerce-customer-emails` after this freeze is on `main`.

---

## 1. Purpose

Ship the **fourth WooCommerce visitor coverage wave**: WooCommerce-core **customer email** chrome (subjects/headings first).

A.7d is **not**:

- generic `wp_mail` interception
- Woo admin/merchant emails
- MP Commerce / payment-provider / BTCPay / VCCP emails
- Fluent Forms / WordPress core / newsletter mail
- third-party invoice/tracking scrape
- a new Email Translation subsystem
- assuming “active locale at send time” is the customer language
- CE7/CE8 non-order emails (Deferred under ADR-0018)

---

## 2. Preconditions

| Precondition | Status |
|---|---|
| A.7a / A.7b / A.7c complete + tagged | **Pass** |
| ADR-0001 / 0007 / 0013 / 0016 / 0017 **Accepted** | **Pass** |
| ADR-0018 **Accepted** (transactional language) | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| Integration API v1 + `WooCommerceIntegration` | **Pass** |
| A.7d planning evidence on `main` | **Pass** |
| No A.7d production implementation in `src/` yet | **Pass** |
| WooCommerce live `10.9.4` inventory | **Pass** |
| Deterministic customer-language provenance for order emails | **Pass** (ADR-0018 contract) |

---

## 3. Frozen architecture

### 3.1 Language provenance (ADR-0018)

| Item | Frozen |
|---|---|
| Meta key | `_aiml_transactional_language` |
| Storage | Woo order meta via CRUD (HPOS-compatible) |
| Capture | Once at visitor order creation when `LanguageContext` is deterministic |
| Immutability | Default immutable after capture |
| Resolution | valid snapshot → source/default (no admin/request guess) |
| Switch | `LanguageContext::with()` / guaranteed restore |
| Historical orders | Source/default fallback; no heuristic backfill |

Evidence trail: [language-provenance.md](a7d-evidence/language-provenance.md).

### 3.2 Carry-forward rules

- Overlay only (ADR-0001); hash = freshness (ADR-0007)
- `PluginIdentity` / `p:woocommerce` only (ADR-0017) — **no `email:` family**
- Extend existing `WooCommerceIntegration` — no parallel email stack
- No schema bump for email chrome; no Store redesign
- Language snapshot is AIML operational meta (ADR-0018), **not** translation storage in Woo business fields
- Producer (Woo) owns sending; AIML owns translation overlay + language marker
- CE7/CE8 Deferred independently — must not block CE1–CE6/CE9–CE10

---

## 4. Admission freeze (final CE matrix)

Full matrix: [admission-matrix.md](a7d-evidence/admission-matrix.md).

### Supported

| ID | Surface | Chrome |
|---|---|---|
| **CE1** | Processing Order | subject + heading |
| **CE2** | Completed Order | subject + heading |
| **CE3** | On-Hold Order | subject + heading |
| **CE4** | Customer Invoice | subject + heading |
| **CE5** | Customer Note | subject + heading (note body = runtime/PII) |
| **CE6** | Refunded Order | subject + heading |
| **CE9** | Failed Order | subject + heading |
| **CE10** | Cancelled Order | subject + heading |

HTML **and** plain: **Supported in both** (subject/heading via `WC_Email` getters).

### Deferred

| ID / surface | Reason |
|---|---|
| **CE7** New Account | Non-order; ADR-0018 out of scope |
| **CE8** Reset Password | Non-order; ADR-0018 out of scope |
| CE11–CE15 | POS / not live-registered |
| Body gettext fragments | No dedicated filter |
| Global email header/footer options | Shared-definition Store risk |
| Filter-unproven body tokens | Await A7D.4 evidence |

### Exclude

Admin/merchant Woo emails; VCCP/BTCPay/payment; Fluent Forms; WP core; newsletter/marketing; third-party injected HTML.

---

## 5. Identity (frozen)

```
p:woocommerce:email:{email_id}:subject
p:woocommerce:email:{email_id}:heading
```

Exact Supported keys listed in [admission-matrix.md](a7d-evidence/admission-matrix.md). Build via `PluginIdentity::build()` only. Source hash = freshness only.

Body token keys (`…:body:{token}`) are **not** in the frozen Supported set.

---

## 6. Store / platform strategy

| Concern | Frozen plan |
|---|---|
| Store host (subject/heading) | `wc_get_page_id('checkout')` as **technical anchor only** |
| Language snapshot | Order meta — not Store segments |
| Workspace / Review / TM / Glossary / Jobs / Suggestions | Existing pipelines only |
| Diagnostics | Bounded counters; **no PII**, no raw bodies |
| Integration | Extend `WooCommerceIntegration` + Integration API v1 |
| Product lines in emails | **A.7a reuse** |
| Runtime values | Never chrome |
| Global email settings | **Deferred** |

---

## 7. Security / PII

Forbidden in segment identity, Store metadata, diagnostics, and evidence fixtures:

- customer names, addresses, emails, phones
- payment data, reset keys
- raw order notes, raw customer email bodies

Language snapshot: **code only**. Sanitized fixtures only. Acceptance mail **must not** go to real customers.

---

## 8. Work packages

A7D.0–A7D.1 **complete** (planning + ADR-0018). **A7D.2+ authorized** for coding on the implementation branch.

### A7D.0 — Baseline + customer-email inventory — **Complete**

Docs/evidence only.

### A7D.1 — Customer-language provenance / persistence decision — **Complete**

**Result:** ADR-0018 Accepted; provenance gate Pass for order-backed emails.

### A7D.2 — Identity + admission matrix + snapshot capture

| | |
|---|---|
| **Objective** | Register Supported identities; implement ADR-0018 capture (no email overlay yet if split) |
| **Dependencies** | Architecture Frozen |
| **Likely files** | `src/Integration/WooCommerce/*` |
| **Tests** | Capture EN/SV; idempotent; invalid reject; HPOS CRUD |
| **Acceptance** | Meta written once; never overwrite; no PII |
| **Rollback** | Remove capture hooks |
| **Stop** | Capture after first email need; direct SQL; schema bump |
| **Commit boundary** | `feat(woocommerce): …` |

### A7D.3 — Order-based email subjects/headings

| | |
|---|---|
| **Objective** | Overlay CE1–CE6 / CE9–CE10 subject/heading with ADR-0018 switch |
| **Dependencies** | A7D.2 |
| **Likely files** | `src/Integration/WooCommerce/*` |
| **Tests** | Sync/async/status/resend; EN→SV→EN→SV; restore |
| **Acceptance** | Filters only; placeholders preserved; leakage=0 |
| **Rollback** | Remove filters |
| **Stop** | Active locale at send; cannot restore context |
| **Commit boundary** | feat |

### A7D.4 — Order-based email body labels/fragments

| | |
|---|---|
| **Objective** | Optional later admission of filter-proven body tokens |
| **Dependencies** | A7D.3; new evidence |
| **Acceptance** | Not required to close first Supported set |
| **Stop** | HTML scrape or PII |
| **Commit boundary** | feat or defer docs |

### A7D.5 — Non-order customer emails

| | |
|---|---|
| **Objective** | Explicit Defer CE7/CE8 (unless separate user-language ADR later) |
| **Dependencies** | None for deferral |
| **Acceptance** | CE7/CE8 Deferred in validation log |
| **Commit boundary** | docs |

### A7D.6 — Platform / lifecycle / security / diagnostics

| | |
|---|---|
| **Objective** | Workspace, review/TM/glossary/jobs, diagnostics, disabled-email lifecycle |
| **Dependencies** | A7D.3+ |
| **Acceptance** | No PII in diagnostics; ADR-0018 counters |
| **Stop** | Unrelated schema bump |
| **Commit boundary** | feat/chore |

### A7D.7 — Full email acceptance + regression / performance

| | |
|---|---|
| **Objective** | Safe capture acceptance; alternating EN/SV; regressions |
| **Dependencies** | Admitted surfaces coded |
| **Acceptance** | AC set green; leakage=0; FP=0 |
| **Stop** | Real-customer sends; paid sync AI at render when approved translation exists |
| **Commit boundary** | test/docs validation |

### A7D.8 — Documentation / admission closure

| | |
|---|---|
| **Objective** | Validation log; roadmap; Supported≠Deferred exact |
| **Dependencies** | A7D.7 PASS |
| **Acceptance** | Tag only after merge per release process |
| **Commit boundary** | docs |

---

## 9. Acceptance criteria (52 ACs)

### Ownership & scope

1. AC-OWN-01 Only WooCommerce-core customer emails are candidates.
2. AC-OWN-02 Admin/merchant Woo emails are excluded.
3. AC-OWN-03 VCCP/BTCPay/payment/Fluent/WP-core/newsletter/marketing mail excluded.
4. AC-OWN-04 No generic `wp_mail` interception.
5. AC-OWN-05 Third-party injected email HTML is not scraped.
6. AC-OWN-06 Producer (Woo) remains the sender; AIML only overlays.

### Language provenance (ADR-0018)

7. AC-LANG-01 Customer language is never “whatever locale is active at send.”
8. AC-LANG-02 Sync checkout path resolves via order snapshot (or source fallback).
9. AC-LANG-03 Async/deferred path uses the same deterministic source as sync.
10. AC-LANG-04 Retry uses the same deterministic source.
11. AC-LANG-05 Status-change emails use the deterministic source (not admin locale).
12. AC-LANG-06 Admin resend uses the deterministic source.
13. AC-LANG-07 Customer-note chrome uses the deterministic source; note body stays runtime.
14. AC-LANG-08 Invoice resend uses the deterministic source.
15. AC-LANG-09 CE7/CE8 are explicitly Deferred (ADR-0018 out of scope).
16. AC-LANG-10 Language leakage count = 0 on alternating EN→SV→EN→SV sends.
17. AC-LANG-11 `LanguageContext` restored after each send (`with()` / finally).
18. AC-LANG-12 Missing translation → source fallback (never wrong language chrome).
19. AC-LANG-13 Snapshot meta key is `_aiml_transactional_language`; value is language code only.
20. AC-LANG-14 Valid snapshot is not overwritten by later request locale.

### Identity & runtime

21. AC-ID-01 Keys use `PluginIdentity` / `p:woocommerce` only.
22. AC-ID-02 No new `email:` identity family.
23. AC-ID-03 Identity excludes order ID, customer ID, names, URLs, HTML bodies.
24. AC-ID-04 Source hash is freshness only.
25. AC-ID-05 Runtime business values are not translation units.
26. AC-ID-06 Product/catalog strings reuse A.7a identities.

### Subject / heading / body / variants

27. AC-SUB-01 Admitted subjects overlay via `woocommerce_email_subject_{id}`.
28. AC-HEAD-01 Admitted headings overlay via `woocommerce_email_heading_{id}`.
29. AC-BODY-01 Body labels only via proven filters/tokens (not in first Supported set).
30. AC-VAR-01 HTML and plain both covered for Supported subject/heading.
31. AC-VAR-02 Placeholders (`{order_number}`, `{site_title}`, …) remain runtime.

### Platform

32. AC-PLAT-01 Store reused without redesign.
33. AC-PLAT-02 No schema TARGET bump for email chrome / language snapshot.
34. AC-PLAT-03 Workspace can edit admitted units.
35. AC-PLAT-04 Review workflow applies.
36. AC-PLAT-05 TM write-back remains approval-gated.
37. AC-PLAT-06 Glossary applies via existing path.
38. AC-PLAT-07 Jobs may translate admitted units without rendering mail to customers.
39. AC-PLAT-08 Diagnostics bounded; no PII/bodies.
40. AC-PLAT-09 No Email Translation subsystem.

### Security / Woo integrity

41. AC-SEC-01 No PII in identity/Store meta/diagnostics/fixtures.
42. AC-SEC-02 No Woo persistence mutation for translation storage (language marker meta only per ADR-0018).
43. AC-SEC-03 Reset keys never stored by AIML.
44. AC-SEC-04 Acceptance mail never sent to real customers.

### Regressions & quality

45. AC-REG-01 A.7a Supported surfaces unchanged.
46. AC-REG-02 A.7b Supported surfaces unchanged.
47. AC-REG-03 A.7c Supported surfaces unchanged.
48. AC-REG-04 Gutenberg overlays unchanged.
49. AC-REG-05 Elementor overlays unchanged.
50. AC-REG-06 Fluent Forms A.8 unchanged.
51. AC-REG-07 Integration API v1 unchanged.
52. AC-QA-01 Unit + integration + PluginGuard + PHPCS pass; email FP=0; no paid sync AI at render when approved translations exist.

**Authoritative count:** **52** acceptance criteria.

---

## 10. Safe email acceptance strategy

1. Use dev/test capture — **never** production customers.
2. Languages: **EN** and **SV**.
3. Alternating send validation: **EN → SV → EN → SV** for each Supported email ID.
4. After each send, assert `LanguageContext` restoration.
5. Assert subject/heading language matches **`_aiml_transactional_language`**, not admin UI language.
6. Assert source fallback when translation or snapshot missing.
7. Assert runtime placeholders still substitute correctly.
8. Assert HTML and plain subject/heading paths.
9. No live paid AI call required at render if Store already holds approved translations.

---

## 11. Stop conditions

Stop A.7d implementation if:

1. Language provenance equals “active locale at send.”
2. New identity family proposed without ADR.
3. Generic `wp_mail` interception proposed.
4. Store redesign or unrelated schema bump required for first admission.
5. PII enters identity/diagnostics/snapshot.
6. Ownership theft from theme/third-party templates.
7. CE7/CE8 weakness used to block CE1–CE6/CE9–CE10 — or order emails coded without ADR-0018 capture/resolve.
8. Direct SQL / non-CRUD order meta access.

---

## 12. Roadmap / next step

| Item | State |
|---|---|
| A.7c | **Complete** |
| A.7d planning | **Complete** |
| Language-context ADR | **ADR-0018 Accepted** |
| A.7d architecture | **Frozen** |
| A.7d implementation | **Authorized; not started** |
| Next after A.7d complete | **A.6** per Product Priorities |

**Exact next step:** Implement A7D.2+ on `feature/a7d-woocommerce-customer-emails` (snapshot capture, then subject/heading overlays).

---

## 13. Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/A7D_WOOCOMMERCE_CUSTOMER_EMAILS_IMPLEMENTATION_PLAN.md` |
| Language ADR | [0018-woocommerce-order-transactional-language-context.md](../adr/0018-woocommerce-order-transactional-language-context.md) |
| Implementation tag | **Not in this gate** |
