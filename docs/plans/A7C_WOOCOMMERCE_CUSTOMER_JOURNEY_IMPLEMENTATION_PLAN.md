# A.7c — WooCommerce Customer Journey — Implementation Plan

**Status:** **Architecture Frozen (planning)** — ready for merge to `main`; implementation not started  
**Parent family plan:** [A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md](A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md)  
**Prior wave:** [A7B_WOOCOMMERCE_ARCHIVE_CHROME_IMPLEMENTATION_PLAN.md](A7B_WOOCOMMERCE_ARCHIVE_CHROME_IMPLEMENTATION_PLAN.md) — **Complete** (`a7b-woocommerce-archive-chrome-complete`)  
**Milestone:** Program A — **A.7c** Customer Journey (Woo-owned only)  
**Plan freeze:** Visitor-facing **WooCommerce-owned** customer journey chrome only; per-surface admission via **CJ1–CJ6**; reuse Integration API v1 + existing `WooCommerceIntegration`; TARGET **6**  
**ADR assessment:** **No new ADR required** for the admitted Supported set.  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.2 / A.7 family  
**Planning branch:** `feature/a7c-woocommerce-customer-journey-plan`  
**Implementation branch:** `feature/a7c-woocommerce-customer-journey` (**create only after this plan merges to `main`**)  
**Baseline (plan authoring):** `main` @ `b5010949993bf3e66b90f625d285670feab9b0ec`  
**Depends on:** A.7 family; A.7a+A.7b complete; ADR-0013/0016/0017 **Accepted**; Integration API v1; schema TARGET **6**  
**Evidence:** [a7c-evidence/ownership-inventory.md](a7c-evidence/ownership-inventory.md); [a7c-evidence/admission-matrix.md](a7c-evidence/admission-matrix.md); [a7c-evidence/store-resolution-hypothesis.md](a7c-evidence/store-resolution-hypothesis.md)  
**Product direction:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md) — A.7c next after A.7b

**Operational success:** Customers can complete checkout and use My Account navigation in SV (and other non-default languages) for **proven Woo-owned** labels, without translating storefront/Blocksy/Elementor/payment chrome and without Store redesign.

**This plan is the frozen implementation contract for A.7c.** Do not implement production code on the planning branch.

---

## 1. Purpose

Ship the **third WooCommerce visitor coverage wave**: customer journey chrome (cart → checkout → account → thank-you), not emails (A.7d) and not catalog/archive (A.7a/A.7b).

A.7c is **not**:

- blanket “translate all Woo UI”
- theme / biopentra-storefront / loop-card / Elementor chrome
- payment gateway / BTCPay / card fields
- customer PII or order values as translation units
- emails

---

## 2. Preconditions

| Precondition | Status |
|---|---|
| A.7a / A.7b complete + tagged | **Pass** |
| ADR-0001 / 0007 / 0013 / 0016 / 0017 **Accepted** | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| `WooCommerceIntegration` present | **Pass** |
| Integration API v1 present | **Pass** |
| No prior `docs/plans/A7C*` | **Pass** |
| Baseline `b50109499…` | **Pass** |

---

## 3. Frozen architecture

Carry forward A.7b rules: overlay only; PluginIdentity `p:woocommerce` only; extend existing Woo integration; no schema bump; no scrape; no fuzzy ID; no Woo persistence mutation; no ownership theft.

---

## 4. Admission freeze (CJ1–CJ6)

Full matrix: [a7c-evidence/admission-matrix.md](a7c-evidence/admission-matrix.md).

### Supported

| ID | Surface |
|---|---|
| **CJ3.1** | Checkout field labels |
| **CJ3.2** | Place order button text |
| **CJ4.1** | My Account menu labels |
| **CJ4.2** | Account endpoint titles |
| **CJ6.1** | Thank-you received text |
| **CJ6.2** | Order item totals labels |

### Deferred families

| ID | Reason |
|---|---|
| **CJ1** | Cart chrome gettext-only |
| **CJ2** | Mini-cart owned by biopentra-storefront |
| **CJ5** | Notices need shared-definition or embed runtime product/amount data |
| Remaining CJ3/CJ4/CJ6 gettext / gateway chrome | Wrong owner or no filter |

---

## 5. Identity (frozen)

| ID | Key |
|---|---|
| CJ3.1 | `p:woocommerce:checkout_field:{field_key}:label` |
| CJ3.2 | `p:woocommerce:checkout:order_button:label` |
| CJ4.1 | `p:woocommerce:account_menu:{endpoint}:label` |
| CJ4.2 | `p:woocommerce:endpoint:{endpoint}:title` |
| CJ6.1 | `p:woocommerce:checkout:thankyou_received:label` |
| CJ6.2 | `p:woocommerce:order_totals:{row_key}:label` |

Build via `PluginIdentity::build()` only. Field keys / endpoints / row keys are functional tokens — never translate keys.

---

## 6. Store resolution

| Units | Technical host |
|---|---|
| CJ3.*, CJ6.* | `wc_get_page_id('checkout')` |
| CJ4.* | `wc_get_page_id('myaccount')` |

Pages are WP posts — existing bridge post resolution applies. See [store-resolution-hypothesis.md](a7c-evidence/store-resolution-hypothesis.md).

---

## 7. Work packages

### A7C.0 — Baseline

| | |
|---|---|
| **Objective** | Validation log; reconfirm live ownership; baseline gates |
| **Commit** | `docs(woocommerce): establish A.7c implementation baseline` |

### A7C.1 — Admission finalization

| | |
|---|---|
| **Objective** | Final Supported table + admission records |
| **Stop** | Desire to admit CJ1/CJ2/CJ5 without new evidence |
| **Commit** | `docs(woocommerce): finalize A.7c customer journey admissions` |

### A7C.2 — CJ1 Cart

| | |
|---|---|
| **Objective** | Confirm CJ1 remains Deferred; document; no cart chrome code |
| **Commit** | `docs(woocommerce): record A.7c cart surface deferred` |

### A7C.3 — CJ2 Mini cart

| | |
|---|---|
| **Objective** | Confirm CJ2 Deferred (storefront ownership); no code |
| **Commit** | `docs(woocommerce): record A.7c mini-cart surface deferred` |

### A7C.4 — CJ3 Checkout

| | |
|---|---|
| **Objective** | Extract/overlay CJ3.1–CJ3.2 in `WooCommerceIntegration` |
| **Likely files** | `src/Integration/WooCommerce/WooCommerceIntegration.php`; unit tests |
| **Stop** | Payment-gateway interception; scrape |
| **Commit** | `feat(woocommerce): implement A.7c checkout translation surface` |

### A7C.5 — CJ4 My Account

| | |
|---|---|
| **Objective** | Extract/overlay CJ4.1–CJ4.2 |
| **Commit** | `feat(woocommerce): implement A.7c account translation surface` |

### A7C.6 — CJ5 Notices + CJ6 Order received

| | |
|---|---|
| **Objective** | CJ5 remains Deferred; implement CJ6.1–CJ6.2 |
| **Commit** | `feat(woocommerce): implement A.7c order-received surfaces` |

### A7C.7 — Full validation

| | |
|---|---|
| **Objective** | Full gates + live EN/SV for Supported surfaces; FP=0; leakage=0 |
| **Commit** | `test(woocommerce): complete A.7c customer journey acceptance` |

### A7C.8 — Docs closure

| | |
|---|---|
| **Objective** | Plan/log/roadmap Supported≠Deferred exact; next=A.7d planning |
| **Commit** | `docs(woocommerce): close A.7c Customer Journey implementation` |

---

## 8. Acceptance criteria (summary — expand in validation log)

1–6. Ownership / Supported-only / Deferred CJ1/CJ2/CJ5.  
7–15. Exact identities; keys untranslated; extract on correct hosts.  
16–25. Overlay EN untouched / SV applied; Store miss → source; isolated failure.  
26–32. Workspace / Review / TM / Glossary / Jobs via existing `p:` path.  
33–40. Lifecycle (Woo missing/disabled/version/page missing); source fallback.  
41–45. No PII in diagnostics; no Woo persistence writes; no payment UI translated.  
46–50. Unit/integration/PluginGuard/PHPCS; FP=0; leakage=0; duplicates=0.  
51–55. A.7a/A.7b/Gutenberg/Elementor/Fluent Forms regression.

---

## 9. Stop conditions

**Candidate:** defer if ownership uncertain / no official hook / scrape / fuzzy / Store redesign / PII.

**Milestone STOP:** Store redesign, schema bump, new identity family, payment interception, second Woo integration, ADR change.

---

## 10. Out of scope

A.7d emails; admin; merchant UI; Age Gate; SEO; theme/storefront/loop-card redesign.

---

## 11. Architecture verdict

**Architecture Frozen** for Supported **CJ3.1–CJ3.2, CJ4.1–CJ4.2, CJ6.1–CJ6.2** only.

CJ1, CJ2, CJ5 remain Deferred with evidence.

Implementation may begin **only after this planning document is merged to `main`**.
