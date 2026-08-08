# A.7d — WooCommerce Customer Emails — Implementation Plan

**Status:** **Blocked pending language-context architecture decision**  
**Parent family plan:** [A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md](A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md)  
**Prior wave:** [A7C_WOOCOMMERCE_CUSTOMER_JOURNEY_IMPLEMENTATION_PLAN.md](A7C_WOOCOMMERCE_CUSTOMER_JOURNEY_IMPLEMENTATION_PLAN.md) — **Complete** (`a7c-woocommerce-customer-journey-complete`)  
**Milestone:** Program A — **A.7d** Customer Emails (Woo-owned only)  
**Plan freeze:** **Not Architecture Frozen** — hard customer-language provenance gate failed; see [a7d-evidence/language-provenance.md](a7d-evidence/language-provenance.md)  
**ADR assessment:** **Focused language-context ADR required** (do not author in this milestone). **No A.7d production implementation** until that ADR is Accepted **and** this plan is updated to Architecture Frozen for an admitted CE set.  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.2 / A.7 family  
**Planning branch:** `feature/a7d-woocommerce-customer-emails-plan`  
**Implementation branch:** **Do not create** until Architecture Frozen on `main`  
**Baseline (plan authoring):** `main` @ `d936addf81859d27047e192f1b6a6e139a45e746`  
**Depends on:** A.7 family; A.7a+A.7b+A.7c complete; ADR-0001/0007/0013/0016/0017 **Accepted**; Integration API v1; schema TARGET **6**; future language-context ADR  
**Evidence:** [ownership-inventory.md](a7d-evidence/ownership-inventory.md); [admission-matrix.md](a7d-evidence/admission-matrix.md); [language-provenance.md](a7d-evidence/language-provenance.md); [hook-template-inventory.md](a7d-evidence/hook-template-inventory.md)  
**Product direction:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md) — A.7d after A.7c; then A.6

**Operational success (when unblocked):** Customer-facing WooCommerce-core emails render AIML-approved chrome (subject/heading/admitted labels) in the **customer’s intended language** for sync, async, status-change, and resend paths — with language leakage = 0 and email false positives = 0.

**This document is the canonical A.7d planning contract.** Do not implement production code on the planning branch.

---

## 1. Purpose

Plan the **fourth WooCommerce visitor coverage wave**: WooCommerce-core **customer email** chrome only.

A.7d is **not**:

- generic `wp_mail` interception  
- Woo admin/merchant emails  
- MP Commerce / payment-provider / BTCPay / VCCP emails  
- Fluent Forms / WordPress core / newsletter mail  
- third-party invoice/tracking scrape  
- a new Email Translation subsystem  
- assuming “active locale at send time” is the customer language  

---

## 2. Preconditions

| Precondition | Status |
|---|---|
| `main` clean @ `d936addf8…` | **Pass** |
| A.7a / A.7b / A.7c complete + tagged | **Pass** |
| ADR-0001 / 0007 / 0013 / 0016 / 0017 **Accepted** | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| Integration API v1 + `WooCommerceIntegration` | **Pass** |
| No prior `docs/plans/A7D*` / `a7d-evidence` | **Pass** |
| No A.7d production implementation in `src/` | **Pass** |
| WooCommerce live `10.9.4` inventory | **Pass** |
| Deterministic customer-language provenance for email paths | **Fail** → **Blocked** |

---

## 3. Architecture verdict

### 3.1 Hard gate — language provenance

Evidence: [language-provenance.md](a7d-evidence/language-provenance.md).

**Finding:** No deterministic persisted order/customer language marker exists. `LanguageResolver` is URL-prefix only. `LanguageContext` can switch languages but has nothing trustworthy to switch *to* on async/admin/resend paths.

**Verdict:** **Blocked pending language-context architecture decision.**

Valid alternate (not met): Architecture Frozen only if an existing deterministic source already covered admitted paths.

### 3.2 ADR assessment

| Outcome | When |
|---|---|
| No new ADR required | Existing deterministic language provenance sufficient |
| **Focused language-context ADR required** | **← current** — new persistent AIML order/customer language snapshot (or equivalent) needed |

Do **not** author that ADR in this task. Do **not** implement snapshot meta in A.7d planning.

### 3.3 Carry-forward rules (unchanged when unblocked)

- Overlay only (ADR-0001); hash = freshness (ADR-0007)  
- `PluginIdentity` / `p:woocommerce` only (ADR-0017) — **no `email:` family**  
- Extend existing `WooCommerceIntegration` — no parallel email stack  
- No schema bump for email chrome alone; no Store redesign  
- No Woo persistence mutation for translation storage  
- Producer (Woo) owns sending; AIML owns translation overlay  
- CE7/CE8 may defer independently after order language is solved  

---

## 4. Email inventory summary

**WooCommerce:** `10.9.4` (HPOS on).

Primary CE IDs: **CE1–CE8** as specified. Additional live Woo customer classes inventoried as **CE9–CE12**; package-only as **CE13–CE15**. Full tables: [ownership-inventory.md](a7d-evidence/ownership-inventory.md), [hook-template-inventory.md](a7d-evidence/hook-template-inventory.md).

| ID | Email | Disposition now |
|---|---|---|
| CE1–CE6 | Processing / Completed / On-Hold / Invoice / Note / Refunded | **Blocked (provenance)** |
| CE7–CE8 | New Account / Reset Password | **Blocked (provenance)**; independent deferral allowed later |
| CE9–CE10 | Failed / Cancelled | Secondary candidates; same gate |
| CE11–CE15 | POS / unregistered | **Deferred** |
| Foreign | VCCP, admin, etc. | **Exclude** |

---

## 5. Conditional admission (post-ADR)

Full matrix: [admission-matrix.md](a7d-evidence/admission-matrix.md).

**First coding candidates after language ADR + Architecture Frozen update:**

1. Order-based **subjects** via `woocommerce_email_subject_{id}`  
2. Order-based **headings** via `woocommerce_email_heading_{id}`  
3. Filter-proven body labels only (both HTML and plain, or explicit Partial)  
4. Reuse A.7a product strings inside order tables — do not duplicate  

**Remain Deferred unless proven:** gettext-only body sentences; global footer/header shared-definition; POS; unregistered fulfillment emails.

---

## 6. Identity strategy (hypothesis — not frozen)

```
p:woocommerce:email:{email_id}:subject
p:woocommerce:email:{email_id}:heading
p:woocommerce:email:{email_id}:body:{token}
```

Identity represents the **stable Woo-owned email surface**, never order/customer IDs, names, HTML bodies, or URLs. Source hash remains freshness only.

---

## 7. Store / platform strategy

| Concern | Plan |
|---|---|
| Store | Reuse; technical host TBD in A7D.2 after language ADR — no redesign |
| Workspace / Review / TM / Glossary / Jobs / Suggestions | Existing pipelines only |
| Diagnostics | Bounded counters; **no PII**, no raw bodies |
| Integration | Extend `WooCommerceIntegration` + Integration API v1 |
| Product lines in emails | **A.7a reuse** |
| Runtime values | Never chrome (order #, prices, addresses, …) |
| Global email settings | **Defer** if new shared-definition Store ownership required |

---

## 8. Security / PII

Forbidden in segment identity, Store metadata, diagnostics, and evidence fixtures:

- customer names, addresses, emails, phones  
- payment data, reset keys  
- raw order notes, raw customer email bodies  

Use sanitized fixtures only. Safe acceptance mail **must not** go to real customers.

---

## 9. Work packages

Implementation WPs are **gated**. A7D.0–A7D.1 may proceed as docs/ADR process only; **A7D.2+ production coding is stopped** until Architecture Frozen.

### A7D.0 — Baseline + customer-email inventory

| | |
|---|---|
| **Objective** | Lock Woo version, CE inventory, ownership, hooks/templates |
| **Scope** | Evidence pack; no `src/` |
| **Dependencies** | A.7c complete |
| **Likely files** | `docs/plans/a7d-evidence/*`, this plan |
| **Tests** | Docs link check |
| **Acceptance** | CE1–CE8+ extras inventoried; exclusions explicit |
| **Rollback** | Revert docs commit |
| **Stop** | Unexpected Woo ownership / conflicting plan on main |
| **Commit boundary** | This planning commit |

### A7D.1 — Customer-language provenance / persistence decision

| | |
|---|---|
| **Objective** | Prove or block language resolution for all send paths |
| **Scope** | Evidence + ADR recommendation; **no snapshot implementation** |
| **Dependencies** | A7D.0 |
| **Likely files** | `language-provenance.md`; future ADR (separate milestone) |
| **Tests** | Path matrix complete |
| **Acceptance** | Exactly one verdict: Frozen provenance **or** Blocked + ADR required |
| **Rollback** | Docs revert |
| **Stop** | Temptation to freeze “active locale at send” |
| **Commit boundary** | Planning / future ADR commit — **not** A.7d feature code |

**Current result:** Blocked + focused ADR required.

### A7D.2 — Identity + admission matrix

| | |
|---|---|
| **Objective** | Freeze CE Supported/Deferred/Exclude + identity keys |
| **Dependencies** | Language-context ADR **Accepted**; A7D.1 pass |
| **Likely files** | `WooCommerceIntegration` descriptors; admission docs |
| **Tests** | Identity unit tests; PluginGuard |
| **Acceptance** | Matrix frozen; no new identity family |
| **Rollback** | Revert integration registration |
| **Stop** | Shared-definition Store redesign required for first admission |
| **Commit boundary** | `feat(woocommerce): …` only after Architecture Frozen |

### A7D.3 — Order-based email subjects/headings

| | |
|---|---|
| **Objective** | Overlay CE1–CE6 (and admitted CE9–CE10) subject/heading |
| **Dependencies** | A7D.2; language switch at send via ADR contract |
| **Likely files** | `src/Integration/WooCommerce/*` |
| **Tests** | Unit + integration; EN/SV subject/heading |
| **Acceptance** | Filters only; placeholders preserved; leakage=0 on resend |
| **Rollback** | Remove filters |
| **Stop** | Cannot restore customer language on resend |
| **Commit boundary** | Single feat commit per AC group as needed |

### A7D.4 — Order-based email body labels/fragments

| | |
|---|---|
| **Objective** | Admit filter-proven static body labels (HTML+plain) |
| **Dependencies** | A7D.3 |
| **Tests** | Variant matrix; no gettext scrape |
| **Acceptance** | Partial explicitly labeled if needed |
| **Stop** | HTML scrape or PII in units |
| **Commit boundary** | feat |

### A7D.5 — Non-order customer emails (if provenance permits)

| | |
|---|---|
| **Objective** | CE7/CE8 only if user-language contract exists |
| **Dependencies** | Language ADR covering non-order paths **or** explicit Defer |
| **Acceptance** | Independent deferral allowed without blocking CE1–CE6 |
| **Stop** | Weaker provenance than order path without explicit Defer |
| **Commit boundary** | feat or docs deferral |

### A7D.6 — Platform / lifecycle / security / diagnostics

| | |
|---|---|
| **Objective** | Workspace visibility, review/TM/glossary/jobs paths, diagnostics counters, disabled-email lifecycle |
| **Dependencies** | A7D.3+ |
| **Acceptance** | No PII in diagnostics; disabled emails inert |
| **Stop** | Schema change pressure unrelated to accepted ADR |
| **Commit boundary** | feat/chore |

### A7D.7 — Full email acceptance + regression / performance

| | |
|---|---|
| **Objective** | Safe capture acceptance; EN↔SV alternating; regressions |
| **Dependencies** | Admitted surfaces coded |
| **Tests** | See §11; PluginGuard; PHPCS; A.7a/b/c; Gutenberg; Elementor; Fluent Forms; Integration API |
| **Acceptance** | ~AC set green; leakage=0; FP=0 |
| **Stop** | Real-customer sends; paid sync AI required at render when approved translation exists |
| **Commit boundary** | test/docs validation |

### A7D.8 — Documentation / admission closure

| | |
|---|---|
| **Objective** | Validation log; roadmap; Supported≠Deferred exact |
| **Dependencies** | A7D.7 PASS |
| **Acceptance** | Tag only after merge per release process |
| **Stop** | Docs claim Supported while provenance incomplete |
| **Commit boundary** | docs |

---

## 10. Acceptance criteria (planning set — 52 ACs)

### Ownership & scope

1. AC-OWN-01 Only WooCommerce-core customer emails are candidates.  
2. AC-OWN-02 Admin/merchant Woo emails are excluded.  
3. AC-OWN-03 VCCP/BTCPay/payment/Fluent/WP-core/newsletter/marketing mail excluded.  
4. AC-OWN-04 No generic `wp_mail` interception.  
5. AC-OWN-05 Third-party injected email HTML is not scraped.  
6. AC-OWN-06 Producer (Woo) remains the sender; AIML only overlays.

### Language provenance

7. AC-LANG-01 Customer language is never “whatever locale is active at send.”  
8. AC-LANG-02 Sync checkout path has a documented resolution rule.  
9. AC-LANG-03 Async/deferred path uses the same deterministic source as sync.  
10. AC-LANG-04 Retry uses the same deterministic source.  
11. AC-LANG-05 Status-change emails use the deterministic source (not admin locale).  
12. AC-LANG-06 Admin resend uses the deterministic source.  
13. AC-LANG-07 Customer-note chrome uses the deterministic source; note body stays runtime.  
14. AC-LANG-08 Invoice resend uses the deterministic source.  
15. AC-LANG-09 CE7/CE8 either share a proven non-order contract or are explicitly Deferred.  
16. AC-LANG-10 Language leakage count = 0 on alternating EN→SV→EN→SV sends.  
17. AC-LANG-11 `LanguageContext` restored after each send (`with()` / finally).  
18. AC-LANG-12 Missing translation → source fallback (never wrong language chrome).

### Identity & runtime

19. AC-ID-01 Keys use `PluginIdentity` / `p:woocommerce` only.  
20. AC-ID-02 No new `email:` identity family.  
21. AC-ID-03 Identity excludes order ID, customer ID, names, URLs, HTML bodies.  
22. AC-ID-04 Source hash is freshness only.  
23. AC-ID-05 Runtime business values are not translation units.  
24. AC-ID-06 Product/catalog strings reuse A.7a identities.

### Subject / heading / body / variants

25. AC-SUB-01 Admitted subjects overlay via `woocommerce_email_subject_{id}`.  
26. AC-HEAD-01 Admitted headings overlay via `woocommerce_email_heading_{id}`.  
27. AC-BODY-01 Body labels only via proven filters/tokens.  
28. AC-VAR-01 HTML and plain both covered or Partial explicit.  
29. AC-VAR-02 Placeholders (`{order_number}`, `{site_title}`, …) remain runtime.

### Platform

30. AC-PLAT-01 Store reused without redesign.  
31. AC-PLAT-02 No schema TARGET bump for email chrome alone.  
32. AC-PLAT-03 Workspace can edit admitted units.  
33. AC-PLAT-04 Review workflow applies.  
34. AC-PLAT-05 TM write-back remains approval-gated.  
35. AC-PLAT-06 Glossary applies via existing path.  
36. AC-PLAT-07 Jobs may translate admitted units without rendering mail to customers.  
37. AC-PLAT-08 Diagnostics bounded; no PII/bodies.  
38. AC-PLAT-09 No Email Translation subsystem.

### Security / Woo integrity

39. AC-SEC-01 No PII in identity/Store meta/diagnostics/fixtures.  
40. AC-SEC-02 No Woo persistence mutation for translation storage.  
41. AC-SEC-03 Reset keys never stored by AIML.  
42. AC-SEC-04 Acceptance mail never sent to real customers.

### Regressions & quality

43. AC-REG-01 A.7a Supported surfaces unchanged.  
44. AC-REG-02 A.7b Supported surfaces unchanged.  
45. AC-REG-03 A.7c Supported surfaces unchanged.  
46. AC-REG-04 Gutenberg overlays unchanged.  
47. AC-REG-05 Elementor overlays unchanged.  
48. AC-REG-06 Fluent Forms A.8 unchanged.  
49. AC-REG-07 Integration API v1 unchanged.  
50. AC-QA-01 Unit + integration + PluginGuard + PHPCS pass.  
51. AC-QA-02 Email false positives = 0.  
52. AC-QA-03 No paid synchronous AI generation required at email-render time when approved translations exist.

---

## 11. Safe email acceptance strategy

1. Use dev/test capture (mail logging / disposable inbox / WC email preview) — **never** production customers.  
2. Languages: **EN** and **SV**.  
3. Alternating send validation: **EN → SV → EN → SV** for each admitted email ID.  
4. After each send, assert `LanguageContext` restoration.  
5. Assert subject/heading language matches the **persisted customer language contract** (post-ADR), not the admin UI language.  
6. Assert source fallback when translation missing.  
7. Assert runtime placeholders still substitute correctly.  
8. Assert HTML and plain (or documented Partial).  
9. No live paid AI call required at render if Store already holds approved translations.

---

## 12. Stop conditions

Stop A.7d implementation (or refuse Architecture Frozen) if:

1. Language provenance still equals “active locale at send.”  
2. New identity family proposed without ADR.  
3. Generic `wp_mail` interception proposed.  
4. Store redesign or unrelated schema bump required for first admission.  
5. PII enters identity/diagnostics.  
6. Ownership theft from theme/third-party templates.  
7. CE7/CE8 weakness used incorrectly to block an otherwise sound **post-ADR** CE1–CE6 design — or conversely CE1–CE6 coded while provenance still missing.  
8. Repository drift: conflicting A7D plan or surprise `src/` email code on planning branch.

---

## 13. Roadmap / next step

| Item | State |
|---|---|
| A.7c | **Complete** |
| A.7d planning | **Complete (Blocked)** |
| A.7d implementation | **Not started** |
| Next after A.7d (product priorities) | **A.6** — only after A.7d is unblocked and completed, unless Product Priorities are revised |

**Exact next step:** Author and Accept a **focused language-context ADR** (order/customer language snapshot or equivalent). Then update this plan to **Architecture Frozen** for an admitted CE set and open the implementation branch.

---

## 14. Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/A7D_WOOCOMMERCE_CUSTOMER_EMAILS_IMPLEMENTATION_PLAN.md` |
| Planning commit message | `docs(woocommerce): create A.7d Customer Emails implementation plan` |
| Merge / tag | **Not in this task** |
