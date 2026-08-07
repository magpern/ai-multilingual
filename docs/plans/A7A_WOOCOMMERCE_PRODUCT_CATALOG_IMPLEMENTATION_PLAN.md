# A.7a — WooCommerce Product & Catalog — Implementation Plan

**Status:** **Complete / merged / tagged** `a7a-woocommerce-product-catalog-complete` — visitor-facing product + catalog presentation; per-surface admission; reuse existing post / `b:` / `e:` / `p:` paths; no Woo persistence mutation; TARGET **6**
**ADR assessment:** **No new ADR required** — stayed within ADR-0001 / ADR-0007 / ADR-0013 / ADR-0016 / ADR-0017 + Integration API v1.
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.2 / A.7 family
**Planning branch:** `feature/a7a-woocommerce-product-catalog-plan` (merged)
**Implementation branch:** `feature/a7a-woocommerce-product-catalog` (merged)
**Baseline (plan authoring):** `main` @ `b4932a0aeedc8d0304c7a0d8de941358f3fa1f82` (+ A.7 family plan merge)
**Depends on:** A.7 family plan; P1; A.R1–A.3; A.R2/A.4; A.1; A.0; A.8; ADR-0013 / 0016 / 0017 **Accepted**
**Related:** [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md); [A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md](A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md)

**Operational success:** Merchants can translate admitted product and catalog visitor strings (titles, descriptions, attribute/variation labels as separately admitted units, shop/category/tag titles & descriptions) through existing AIML platform paths, with FP=0 / leakage=0 on admitted hook surfaces, without mutating WooCommerce data.

**This plan was the frozen implementation contract for A.7a.** Implementation is complete on `main`.


---

## 1. Purpose

Ship the **first WooCommerce visitor coverage wave**: product & catalog presentation.

A.7a proves WooCommerce can be admitted into AIML the same way Elementor and Fluent Forms were: **allowlisted surfaces**, **deterministic identity**, **official hooks**, **overlay-not-duplication**.

A.7a is **not**:

- cart / checkout / account / emails (A.7c / A.7d)
- archive listing chrome such as sort, pagination, layered nav (A.7b remainder)
- Gutenberg/Elementor re-admission (already `b:` / `e:`)
- blanket “translate all Woo strings”

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| A.7 family plan exists and is authoritative | **Pass** |
| A.8 Fluent Forms complete | **Pass** (`a8-fluentforms-contact-integration-complete`) |
| A.1 Integration API v1 complete | **Pass** |
| A.0 / A.2 / A.3 / A.4 complete | **Pass** |
| ADR-0013 / 0016 / 0017 **Accepted** | **Pass** |
| Schema TARGET = **6** | **Pass** |
| No A.7a production code in `src/` | **Pass** |
| No existing `docs/plans/A7A*` plan | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen contracts (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| ADR-0001 / ADR-0007 | Overlay architecture; no foreign persistence ownership |
| ADR-0013 | Gutenberg `b:` unchanged |
| ADR-0016 | Elementor `e:` unchanged |
| ADR-0017 | Integration API ownership + `p:` via `PluginIdentity` |
| Integration API v1 | Typed integrations only |
| Store / Workspace / Review / TM / Glossary / Jobs | Reuse unchanged |
| Fluent Forms A.8 | Regression-protected |
| Schema TARGET | **6** — no bump |

**Forbidden:**

- new identity family
- Store / schema redesign
- second translation pipeline
- HTML scraping / unscoped buffering / DOM rewrite as primary strategy
- fuzzy identity
- WooCommerce persistence mutation for translations
- reopening ADR-0013 / 0016 / 0017 for convenience
- wp-admin / merchant UI translation

---

## 4. Ownership model (frozen)

| Party | Owns |
|---|---|
| **WooCommerce** | Product CPT persistence, product meta, attributes taxonomy data, catalog term data, Woo notices/breadcrumb logic it owns, business rules |
| **WordPress** | Post title/content/excerpt when used as product fields; term name/description for product_cat / product_tag |
| **Gutenberg / Elementor** | Block/widget content on product documents (continue `b:` / `e:` — **not re-admitted as Woo**) |
| **AIML** | Store overlays, Review, TM, Glossary, Jobs, diagnostics |

Local failure → source + continue. Never write translations into Woo tables / product meta as the translation store.

---

## 5. Frozen implementation scope (A.7a allowlist)

### 5.1 Individual product (visitor)

| # | Candidate | Notes |
|---|---|---|
| P1 | Product title | Prefer existing post/title pipeline if already covered |
| P2 | Product short description | Prefer existing excerpt/short-desc path if covered |
| P3 | Product long description | Prefer existing content / blocks / Elementor paths |
| P4 | Product tabs (visitor-visible) | Woo tab titles/bodies that Woo owns; admit per tab |
| P5 | Attribute **names** | **Separate admission** (mandatory split) |
| P6 | Attribute **values** | **Separate admission** (mandatory split) |
| P7 | Variation attribute **names** | **Separate admission** (mandatory split) |
| P8 | Variation attribute **values** | **Separate admission** (mandatory split) |
| P9 | Woo notices on product page | Only Woo-owned, deterministic |
| P10 | Woo-owned breadcrumbs | Only if ownership deterministic (else defer) |

### 5.2 Catalog (visitor titles/descriptions)

| # | Candidate | Notes |
|---|---|---|
| C1 | Shop archive title | Where Woo/WP owns the string |
| C2 | Shop archive description | Where present and owned |
| C3 | Product category title | `product_cat` term name |
| C4 | Product category description | `product_cat` description |
| C5 | Product tag title | `product_tag` term name |
| C6 | Product tag description | `product_tag` description |

**Boundary note vs A.7 family §7:** A.7a admits **taxonomy/shop title & description** as catalog presentation. Remaining archive listing chrome (ordering labels, pagination, layered nav, search chrome) stays **A.7b**.

### 5.3 Explicitly out of A.7a

Cart, checkout, account, emails, payment gateways, subscriptions, bookings, bundles, composites, product add-ons, inventory, merchant UI, BTCPay/multicurrency/extensions, sort/pagination/layered-nav, anything already owned by Gutenberg/Elementor document extractors.

---

## 6. Admission model (frozen)

**No blanket WooCommerce support.**

Every candidate (P1–P10, C1–C6, and each attribute/variation split) requires an admission record before coding that surface.

### Required admission fields

| Field | Content |
|---|---|
| Owner | Woo / WP / theme (must be explicit) |
| Ownership class | `record` / `document` / `shared-definition` / unsupported |
| Identity | Exact serializer family + components (or “reuse existing post path”) |
| Extraction | API/hook + allowlisted path |
| Overlay | Official filter/hook; mutation shape |
| Lifecycle | delete / rename / draft / private |
| Sanitization | plain / HTML policy |
| Diagnostics | Bounded counters |
| Workspace / Review / TM / Glossary / Jobs | Existing path evidence |
| Browser | EN/SV fixtures |
| Disposition | **Supported** / **Deferred** / **Experimental** / **Unsupported** |

### Mandatory attribute split

Do **not** treat “attributes” as one feature. Independent records required for:

1. attribute names  
2. attribute values  
3. variation attribute names  
4. variation attribute values  

Any one may be **Supported** while others remain **Deferred**.

---

## 7. Identity strategy (research freeze for A7A.2)

**Do not invent free-form keys.** Prefer, in order:

1. **Existing post pipeline** (title / content / excerpt already in Store)  
2. **`b:`** (ADR-0013) for Gutenberg on product  
3. **`e:`** (ADR-0016) for Elementor on product  
4. **`p:` via `PluginIdentity`** only where Woo plugin ownership requires Integration API v1  

Likely `integration_id` for Woo-specific units: `woocommerce` (confirm at A7A.2 — must match `INTEGRATION_ID_PATTERN`).

### Planning hypotheses (must be proven or deferred at A7A.2)

| Candidate | Likely path |
|---|---|
| P1 title | Existing post title pipeline |
| P2 short description | Existing excerpt / product short description path |
| P3 long description | Existing content + `b:` / `e:` as applicable |
| P4 tabs | `p:woocommerce:…` **or** defer if non-deterministic |
| P5–P8 attributes/variations | `p:` record-owned with stable attribute/term IDs — **four separate identities** |
| P9 notices | `p:` only if notice key stable; else defer |
| P10 breadcrumbs | Often theme-owned → default **Deferred** unless Woo ownership proven |
| C3–C6 term titles/descriptions | Prefer existing term/taxonomy pipeline if present; else `p:` / document-adjacent Store mapping proven at A7A.2 |
| C1–C2 shop archive | Prefer page/post or Woo shop page identity; else defer |

### Hard identity rules

- Deterministic only  
- No source text in identity  
- Source hash = freshness only  
- No path identity  
- No fuzzy rematch after rename/delete  
- Key length ≤ 191 via framework serializers  
- No new grammar  

**A7A.2 deliverable:** identity matrix for every candidate with disposition.

---

## 8. Extraction / overlay strategy

1. Detect WooCommerce active + compatible version.  
2. Extract only **Supported** allowlisted candidates.  
3. Prefer official Woo / WP APIs and filters.  
4. Overlay via official hooks; miss/stale/error → source.  
5. One unit failure must not break the product page.  
6. No unrestricted product meta walkers; no HTML scrape.

Sanitization: plain vs HTML per admission (descriptions/tabs may be HTML with existing Store HTML format — never invent a new sanitizer family).

---

## 9. Lifecycle / compatibility

| State | Behavior |
|---|---|
| Woo missing/inactive | No Woo extract/overlay |
| Unsupported version | source fallback |
| Product draft/private/deleted | no visitor overlay; Store history retained |
| Attribute/term renamed | new identity; **no** fuzzy remap |
| Integration/wave disabled | Store retained; source |
| Reactivation | only after compatibility PASS |

Version floor: freeze from live evidence at A7A.0 (dev WooCommerce **10.9.4** at recent inventory — re-verify).

---

## 10. Platform reuse

Must use unchanged: Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Integration diagnostics.

No Woo-specific Store/Review/TM/Jobs pipeline.

Gutenberg/Elementor product-document content continues through existing extractors — A.7a must not double-extract the same string under a second identity.

---

## 11. Work packages (A7A.0 – A7A.8)

### A7A.0 — Baseline

| | |
|---|---|
| **Objective** | Open validation log; confirm Woo active/version; TARGET=6; fixtures inventory |
| **Scope** | Docs only |
| **Deps** | This plan frozen on `main` |
| **Affected files** | `docs/plans/A7A_*_VALIDATION_LOG.md` |
| **Validation** | Tier 0 baseline; `git diff --check` |
| **Rollback** | Revert docs |
| **Stop** | Woo inactive; TARGET ≠ 6 |
| **Commit** | `docs(woocommerce): establish A.7a baseline` |

### A7A.1 — Candidate inventory + admission shells

| | |
|---|---|
| **Objective** | Live inventory of P1–P10 / C1–C6 with owner evidence; create admission record stubs including **four attribute splits** |
| **Scope** | Docs |
| **Deps** | A7A.0 |
| **Affected files** | `docs/plans/a7a-evidence/*` |
| **Validation** | Every candidate has a stub disposition |
| **Rollback** | Revert docs |
| **Stop** | Owner cannot be determined → mark Deferred |
| **Commit** | `docs(woocommerce): inventory A.7a product catalog candidates` |

### A7A.2 — Identity freeze

| | |
|---|---|
| **Objective** | Freeze identity matrix; prove serializer fit; resolve post vs `b:` vs `e:` vs `p:` |
| **Scope** | Docs + failing-first identity unit tests if needed |
| **Deps** | A7A.1 |
| **Affected files** | admission records; optional `tests/unit/...` |
| **Validation** | No new family; lengths ≤ 191; attribute splits have distinct keys |
| **Rollback** | Revert |
| **Stop** | Requires Store redesign / shared-definition hack / new grammar |
| **Commit** | `docs(woocommerce): freeze A.7a identity matrix` |

### A7A.3 — Extraction (Supported only)

| | |
|---|---|
| **Objective** | Implement allowlisted extraction for **Supported** candidates only |
| **Scope** | `src/` Woo/Integration modules as justified; wire via Integration API or existing extractors |
| **Deps** | A7A.2 |
| **Affected files** | `src/Integration/WooCommerce/*` or equivalent; `src/Plugin.php` wiring |
| **Validation** | Exact unit sets; no duplicates; non-product posts empty for Woo units |
| **Rollback** | Feature flag / disable integration |
| **Stop** | Universal meta walker; scrape |
| **Commit** | `feat(woocommerce): extract A.7a product catalog units` |

### A7A.4 — Overlay

| | |
|---|---|
| **Objective** | Official-hook overlays for Supported candidates |
| **Scope** | Frontend filters/hooks |
| **Deps** | A7A.3 |
| **Affected files** | overlay/bridge classes |
| **Validation** | EN untouched; SV applied; Woo DB unchanged |
| **Rollback** | Disable overlay registration |
| **Stop** | HTML scrape required |
| **Commit** | `feat(woocommerce): overlay A.7a product catalog translations` |

### A7A.5 — Workspace / platform path

| | |
|---|---|
| **Objective** | Units visible/editable; Review/TM/Glossary/Jobs smoke |
| **Scope** | Additive metadata only |
| **Deps** | A7A.4 |
| **Affected files** | minimal if any; mostly tests/docs |
| **Validation** | Workspace save; Review approve/reject; TM policy; Glossary; Jobs |
| **Stop** | Woo-specific workflow |
| **Commit** | `feat(woocommerce): connect A.7a units to platform workflow` |

### A7A.6 — Lifecycle / security / diagnostics

| | |
|---|---|
| **Objective** | Compatibility matrix; delete/rename; sanitization; bounded diagnostics |
| **Deps** | A7A.5 |
| **Validation** | Lifecycle table PASS; foreign persistence audit PASS |
| **Stop** | Foreign mutation |
| **Commit** | `feat(woocommerce): harden A.7a lifecycle diagnostics` |

### A7A.7 — Acceptance

| | |
|---|---|
| **Objective** | Full Tier 0 + live product/catalog EN/SV browser matrix |
| **Deps** | A7A.6 |
| **Validation** | FP=0; leakage=0; Gutenberg/Elementor/A.8 regressions PASS |
| **Commit** | `test(woocommerce): complete A.7a acceptance` |

### A7A.8 — Closure

| | |
|---|---|
| **Objective** | Final Supported surface table; admission dispositions; roadmap; tag prep |
| **Deps** | A7A.7 PASS |
| **Commit** | `docs(woocommerce): close A.7a product catalog` |

---

## 12. Acceptance criteria (~48)

### Architecture

1. TARGET remains **6**.  
2. No Store redesign.  
3. No new identity family.  
4. ADR-0013 / 0016 / 0017 unreopened for convenience.  
5. Integration API v1 unchanged unless pre-approved ADR.  
6. No second translation pipeline.  
7. No Woo persistence mutation for translations.  
8. No HTML scraping as primary strategy.  
9. No fuzzy identity.  
10. PluginGuard PASS.  
11. PHPCS PASS.  
12. Unit suite PASS.  
13. Integration suite PASS.  
14. Gutenberg regressions PASS.  
15. Elementor regressions PASS.  
16. A.8 Fluent Forms regressions PASS.

### Scope / admission

17. Only A.7a allowlisted candidates considered.  
18. Cart/checkout/account/emails absent.  
19. Attribute names/values and variation names/values have **four independent** admission records.  
20. Each Supported surface has a complete admission record.  
21. Deferred surfaces listed with reasons.  
22. Gutenberg/Elementor product content not double-keyed under Woo identities.

### Identity / extract / overlay

23. Identity preference order respected (post → `b:` → `e:` → `p:`).  
24. Keys via framework serializers only.  
25. Source hash freshness only.  
26. Extract emits only Supported units.  
27. Overlay uses official hooks.  
28. Store miss → source.  
29. Stale behavior safe.  
30. One-field failure isolated.  
31. Default language unchanged.  
32. Rename does not fuzzy-rematch.

### Platform

33. Workspace lists Supported units.  
34. Manual edit/save works.  
35. Review approve works.  
36. Review reject/resubmit works.  
37. TM approval write-back respects policy.  
38. Glossary/Suggestions path works.  
39. Jobs compatibility for materialized units.  
40. Diagnostics bounded (no bodies/secrets).

### Live / quality

41. Live product EN sources correct.  
42. Live product SV overlays correct for Supported units.  
43. Live catalog/taxonomy EN/SV correct for Supported C* units.  
44. Rendered FP = 0 on admitted surfaces.  
45. Language leakage = 0 on admitted surfaces.  
46. Foreign Woo source audit PASS.  
47. Disabled integration → source; Store retained.  
48. Performance notes recorded; no global product crawl / invented budgets.

---

## 13. Stop conditions

**Stop A.7a immediately if implementation requires:**

- Store redesign  
- schema change  
- new identity family  
- HTML scraping  
- fuzzy identity  
- Woo persistence ownership  
- second translation pipeline  
- reopening ADR-0013 / 0016 / 0017  

**Candidate-local failure** → defer only that candidate (especially individual attribute/variation splits).

---

## 14. Out of scope (reminder)

A.7b listing chrome; A.7c customer workflow; A.7d emails; extensions; merchant UI; re-implementing Gutenberg/Elementor.

---

## 15. Validation / browser matrix (minimum)

1. Simple product EN/SV (title, short, long as Supported)  
2. Variable product EN/SV (only Supported attribute/variation splits)  
3. Product with Elementor/Gutenberg content — `e:`/`b:` unchanged  
4. Shop page title/description if Supported  
5. Category + tag archives titles/descriptions if Supported  
6. Disabled Woo integration → source  
7. Product delete/rename lifecycle  
8. FP=0 / leakage=0  
9. A.8 Contact form regression smoke  

---

## 16. Documentation / roadmap (this planning task)

Create this plan. Update editorial pointers in:

- [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md)  
- [../ROADMAP.md](../ROADMAP.md)  

No production code. No ADR. No implementation branch.

---

## 17. Sequencing after freeze

1. Architecture review / fast-track freeze.  
2. Merge A.7 family plan + this A.7a plan to `main` (authoritative copies).  
3. Create `feature/a7a-woocommerce-product-catalog`.  
4. Begin **A7A.0**.  
5. Do not start A.7b coding until A.7a is independently validated/merged/closed.

---

## 18. Fast-track freeze

No new architectural contract beyond existing ADRs + Integration API v1.

Expected verdict:

- **Architecture Frozen**  
- A.7a implementation authorized after merge to `main`  
- **No further A.7a planning cycle** unless a stop condition forces ADR work  

---

## 19. Risks

| Risk | Mitigation |
|---|---|
| Attribute/variation ID instability | Separate admissions; defer unsafe splits |
| Double extraction with `b:`/`e:` | Prefer existing pipelines; deny Woo duplicate keys |
| Theme-owned breadcrumbs/notices | Default Deferred without Woo ownership proof |
| Catalog term Store mapping | Prove at A7A.2; else defer C* |
| Scope creep into A.7b chrome | Hard allowlist |

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md` |
| Parent | `docs/plans/A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md` |
| Planning branch | `feature/a7a-woocommerce-product-catalog-plan` |
| Implementation branch | `feature/a7a-woocommerce-product-catalog` (after freeze) |
| Baseline | `main` @ `b4932a0aeedc8d0304c7a0d8de941358f3fa1f82` |
