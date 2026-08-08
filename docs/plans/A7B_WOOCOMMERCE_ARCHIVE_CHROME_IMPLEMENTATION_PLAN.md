# A.7b — WooCommerce Archive Chrome — Implementation Plan

**Status:** **Complete** — implemented and validated on `feature/a7b-woocommerce-archive-chrome`; closure tag `a7b-woocommerce-archive-chrome-complete`  
**Parent family plan:** [A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md](A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md)  
**Prior wave:** [A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md](A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md) — **Complete** (`a7a-woocommerce-product-catalog-complete`)  
**Milestone:** Program A — **A.7b** Archive / listing chrome (Woo-owned only)  
**Plan freeze:** Visitor-facing **WooCommerce-owned** archive listing chrome only; per-surface admission; reuse Integration API v1 + existing `WooCommerceIntegration`; TARGET **6**  
**ADR assessment:** **No new ADR required** for the admitted Supported set (B1–B2). Deferred surfaces that lack pre-interpolation hooks or wrong owners do **not** justify silent Store redesign.  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.2 / A.7 family  
**Planning branch:** `feature/a7b-woocommerce-archive-chrome-plan`  
**Implementation branch:** `feature/a7b-woocommerce-archive-chrome`  
**Validation log:** [A7B_WOOCOMMERCE_ARCHIVE_CHROME_VALIDATION_LOG.md](A7B_WOOCOMMERCE_ARCHIVE_CHROME_VALIDATION_LOG.md)  
**Baseline (plan authoring):** `main` @ `ef1a63563d553ab018a33498072e3cef5f03ccaf`  
**Depends on:** A.7 family plan; A.7a complete; ADR-0013 / 0016 / 0017 **Accepted**; Integration API v1; schema TARGET **6**  
**Evidence:** [a7b-evidence/ownership-inventory.md](a7b-evidence/ownership-inventory.md); [a7b-evidence/admission-matrix.md](a7b-evidence/admission-matrix.md); [a7b-evidence/store-resolution-hypothesis.md](a7b-evidence/store-resolution-hypothesis.md)

**Operational success:** Merchants can translate **proven Woo-owned** catalog ordering labels (and related ordered-by status labels) through existing AIML paths on native archives, without stealing Blocksy/Elementor/storefront/loop-card chrome and without redesigning Store.

**This plan is the frozen implementation contract for A.7b.** Do not implement production code on the planning branch.

---

## 1. Purpose

Ship the **second WooCommerce visitor coverage wave**: archive/listing **chrome**, not more product business content.

A.7a already owns product/catalog **content** (titles, descriptions, attribute names, term titles/descriptions).

A.7b owns only listing chrome that WooCommerce itself renders through official filters — after a live ownership inventory.

A.7b is **not**:

- re-translation of A.7a surfaces
- cart / checkout / account / emails (A.7c / A.7d)
- theme or first-party plugin string coverage
- blanket “translate all archive UI”

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| A.7 family plan on `main` | **Pass** |
| A.7a complete / tagged `a7a-woocommerce-product-catalog-complete` | **Pass** |
| ADR-0001 / 0007 / 0013 / 0016 / 0017 **Accepted** | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| `WooCommerceIntegration` present | **Pass** |
| Integration API v1 present | **Pass** |
| No existing `docs/plans/A7B*` plan | **Pass** |
| No A.7b production implementation | **Pass** |
| Baseline HEAD `ef1a63563…` | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen architecture (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| ADR-0001 / ADR-0007 | Overlay; no foreign persistence ownership |
| ADR-0013 / ADR-0016 | Gutenberg `b:` / Elementor `e:` unchanged |
| ADR-0017 | Integration API + `p:` via `PluginIdentity` |
| Integration API v1 | Typed integrations only — **extend existing Woo integration**; no second Woo integration |
| Store / Workspace / Review / TM / Glossary / Jobs | Reuse unchanged |
| A.7a surfaces | Regression-protected; do not double-key |
| Schema TARGET | **6** — no bump |

**Forbidden:**

- new identity family / serializer
- Store / schema redesign
- second translation pipeline
- HTML scraping / unscoped buffering / DOM rewrite as primary strategy
- fuzzy identity
- WooCommerce persistence mutation for translations
- ownership theft from Blocksy / Elementor / biopentra-storefront / biopentra-loop-card
- inventing site-global Store ownership or a new source type
- duplicating archive-chrome rows per category/tag/search URL

---

## 4. Ownership model (frozen)

| Party | Owns |
|---|---|
| **WooCommerce** | Classic result-count / orderby / empty templates and their official filters when those callbacks still run |
| **Blocksy** | Pagination markup/strings that replace Woo `loop/pagination.php`; visibility class wrapping of Woo templates |
| **Elementor** | Shop page document chrome; Pro taxonomy-filter chrome |
| **biopentra-storefront** | Shop search, category chips, search refine bar |
| **biopentra-loop-card** | Product card CTAs/i18n; shop load-more patches; live-search strings |
| **AIML** | Store overlays for **admitted** Woo units only |

Hard rule: visible placement on a Woo URL does **not** imply Woo ownership.

---

## 5. Live ownership inventory (summary)

Full matrix: [a7b-evidence/ownership-inventory.md](a7b-evidence/ownership-inventory.md).

**Two chrome systems:**

1. **`/shop/`** — Elementor + storefront + loop-card (almost no classic Woo orderby/result-count UI).  
2. **Native category / tag / product search** — Woo `woocommerce_result_count` / `woocommerce_catalog_ordering` still hooked; Blocksy owns pagination strings; loop-card owns cards.

Woo layered nav: **unused** live.

---

## 6. Admission matrix (frozen)

Full table: [a7b-evidence/admission-matrix.md](a7b-evidence/admission-matrix.md).

### Supported

| ID | Surface | Overlay |
|---|---|---|
| **B1** | Catalog ordering **option labels** | `woocommerce_catalog_orderby` — mutate labels only |
| **B2** | Catalog **ordered-by status labels** (result-count SR fragment) | `woocommerce_catalog_orderedby` — mutate labels only |

### Deferred (selected)

| ID | Surface | Reason |
|---|---|---|
| B3 | “Showing …” result-count templates | Runtime numbers; no official pre-interpolation data filter |
| B4 | No-products-found message | Template `__()` only |
| B5 | Pagination | Blocksy-owned |
| B6–B8 | Elementor / storefront / loop-card chrome | Wrong owner |
| B9 | Layered nav | Not present |
| B10–B12 | Cards / A.7a content | Out of wave |

---

## 7. Identity model (frozen)

Preference order: existing `b:` / `e:` / post-field paths where already canonical → else **`p:woocommerce` via `PluginIdentity` only**.

| ID | Key shape |
|---|---|
| B1 | `p:woocommerce:catalog_orderby:{key}:label` |
| B2 | `p:woocommerce:catalog_orderedby:{key}:label` |

- `{key}` = Woo functional token (`popularity`, `price-desc`, …) — **not** source text.  
- Key length ≤ 191; no path identity; no fuzzy rematch after rename (missing key → omit unit).  
- **Never** translate option values/keys.

---

## 8. Store-resolution evidence (mandatory)

Full write-up: [a7b-evidence/store-resolution-hypothesis.md](a7b-evidence/store-resolution-hypothesis.md).

**Verdict:** The A.7a shop-page host may be reused as a **technical Store anchor only** for B1/B2:

- Resolve with `wc_get_page_id( 'shop' )` (no hardcoded IDs).  
- One Store row per segment under that `source_id` — not per archive URL.  
- Workspace metadata must identify **WooCommerce archive chrome**, not shop page document content.  
- Product-search context requires a **bridge context extension** (map Woo product search → shop page ID) during implementation — **not** a new source type / schema / Integration API v1 interface change.  
- Candidates needing new source types, shared-definition **Store** models, or per-archive duplication remain **Deferred**.

**Do not redesign Store in A.7b.**

---

## 9. Dynamic-text policy (frozen)

| Class | Rule | Example |
|---|---|---|
| Static label | Admit if Woo filter supplies label before render | Orderby option text |
| Static label + runtime params | Prefer translating label table only | Ordered-by SR labels via `woocommerce_catalog_orderedby` |
| Runtime-interpolated template | **Defer** without official template filter | “Showing %d–%d of %d results” |
| Opaque rendered HTML | **Defer** | Blocksy pagination SVG+text |

---

## 10. Extraction / overlay strategy

1. Compatibility via existing `WooCommerceIntegration` ladder (reuse; extend extract/overlay for B1/B2).  
2. Extract B1/B2 only when extracting the **current shop page** post (technical host).  
3. Overlay via official Woo filters; miss/stale/error → source label; continue.  
4. No HTML scrape; no unrestricted meta walkers.

Sanitization: plain text (`IntegrationSecurity::sanitize_plain`).

---

## 11. Platform reuse

Unchanged: Store, Workspace, Suggestions, Review, TM, Glossary, Jobs, Integration diagnostics.

Workspace presentation requirements for B1/B2:

- `surface = plugin_integration`
- `integration_id = woocommerce`
- human label e.g. “Catalog orderby: popularity”
- `parent_context` stating archive chrome / technical shop-page Store host

No archive-specific workflow.

---

## 12. Compatibility / lifecycle

Reuse A.7a Woo integration boundary (`aiml_woocommerce_integration_disabled`, version floor, hooks present).

| State | Behavior |
|---|---|
| Woo missing/inactive / unsupported / hooks missing / disabled | No extract/overlay; Store retained |
| Shop page ID changes | Anchor follows `wc_get_page_id( 'shop' )`; re-extract on new shop page |
| Orderby key removed by Woo/settings | Unit absent; no fuzzy remap |
| Theme hides result-count/orderby | Overlay harmless if filter still runs; UI may be absent |

---

## 13. Work packages (A7B.0 – A7B.8)

### A7B.0 — Baseline + ownership inventory

| | |
|---|---|
| **Objective** | Open validation log; confirm TARGET=6; freeze live ownership evidence |
| **Scope** | Docs |
| **Deps** | This plan on `main` |
| **Files** | `docs/plans/A7B_*_VALIDATION_LOG.md`; refresh `a7b-evidence/` if needed |
| **Validation** | Inventory covers shop/cat/tag/search/pagination/sort/filters/cards |
| **Rollback** | Revert docs |
| **Stop** | Woo inactive; TARGET ≠ 6 |
| **Commit** | `docs(woocommerce): establish A.7b baseline` |

### A7B.1 — Admission matrix freeze

| | |
|---|---|
| **Objective** | Confirm Supported = B1–B2 only unless new Woo-owned evidence appears |
| **Scope** | Docs / admission records |
| **Deps** | A7B.0 |
| **Stop** | Desire to admit Blocksy/Elementor/storefront/loop-card as Woo |
| **Commit** | `docs(woocommerce): freeze A.7b admission matrix` |

### A7B.2 — Identity + Store-anchor contract

| | |
|---|---|
| **Objective** | Freeze PluginIdentity keys; unit-test key shapes; document shop-page technical anchor + search bridge requirement |
| **Scope** | Docs + failing-first identity unit tests |
| **Deps** | A7B.1 |
| **Stop** | Requires new identity family or Store source type |
| **Commit** | `docs(woocommerce): freeze A.7b identity and store anchor` |

### A7B.3 — Extraction (Supported only)

| | |
|---|---|
| **Objective** | Emit B1/B2 units from shop-page extract path inside existing `WooCommerceIntegration` |
| **Scope** | `src/Integration/WooCommerce/*` only as justified |
| **Deps** | A7B.2 |
| **Validation** | Exact key set; non-shop posts empty for these units; no key mutation |
| **Stop** | Universal string walker; scrape |
| **Commit** | `feat(woocommerce): extract A.7b archive chrome units` |

### A7B.4 — Overlay + bridge context

| | |
|---|---|
| **Objective** | Overlay B1/B2 via official filters; ensure resolve works on category/tag/search via shop-page technical anchor |
| **Scope** | Woo filters + minimal `IntegrationFrontendBridge` context selection for product search |
| **Deps** | A7B.3 |
| **Validation** | EN untouched; SV labels applied; option **values** unchanged; Woo DB unchanged |
| **Stop** | HTML scrape required; Store redesign proposed |
| **Commit** | `feat(woocommerce): overlay A.7b archive chrome translations` |

### A7B.5 — Filters / pagination subset

| | |
|---|---|
| **Objective** | Confirm B5/B9 remain Deferred unless new Woo-owned evidence appears; no code for Deferred |
| **Scope** | Docs + optional negative tests |
| **Deps** | A7B.4 |
| **Commit** | `docs(woocommerce): record A.7b deferred filter pagination` |

### A7B.6 — Workspace / lifecycle / diagnostics

| | |
|---|---|
| **Objective** | Units visible on shop Workspace with correct Woo chrome labeling; Review/TM/Glossary/Jobs smoke; diagnostics bounded |
| **Deps** | A7B.4 |
| **Stop** | Woo-specific workflow; false shop-page ownership copy |
| **Commit** | `feat(woocommerce): connect A.7b units to platform workflow` |

### A7B.7 — Acceptance

| | |
|---|---|
| **Objective** | Tier 0 + live EN/SV on category/search (and shop if classic chrome present); A.7a / Gutenberg / Elementor / Fluent Forms regressions |
| **Deps** | A7B.6 |
| **Validation** | FP=0; leakage=0; option keys unchanged |
| **Commit** | `test(woocommerce): complete A.7b acceptance` |

### A7B.8 — Closure

| | |
|---|---|
| **Objective** | Supported/Deferred tables final; roadmap; tag prep |
| **Deps** | A7B.7 PASS |
| **Commit** | `docs(woocommerce): close A.7b archive chrome` |

---

## 14. Acceptance criteria (~42)

### Ownership

1. Every Supported surface has proven Woo ownership with hook/template evidence.  
2. No Blocksy-owned string admitted as Woo.  
3. No Elementor-owned string admitted as Woo.  
4. No storefront-owned string admitted as Woo.  
5. No loop-card-owned string admitted as Woo.  
6. No duplicate A.7a term/content support.

### Architecture

7. TARGET remains **6**.  
8. No Store redesign.  
9. No schema change.  
10. No new identity family.  
11. Integration API v1 interface unchanged (additive consumer behavior only).  
12. Single `WooCommerceIntegration` (no second Woo integration).  
13. No HTML scraping as primary strategy.  
14. No fuzzy identity.  
15. No Woo persistence mutation for translations.  
16. PluginGuard PASS.  
17. PHPCS PASS (0 errors on touched paths).  
18. Unit suite PASS.  
19. Integration suite PASS.

### Identity / extract / overlay

20. B1/B2 keys via `PluginIdentity` only.  
21. Orderby **keys/values** unchanged after overlay.  
22. Extract emits only Supported units.  
23. Overlay uses official Woo filters.  
24. Store miss → source label.  
25. One-field failure isolated.  
26. Default language unchanged.

### Store anchor

27. Production resolve uses `wc_get_page_id( 'shop' )` (no hardcoded shop ID).  
28. No per-archive duplicate Store rows for B1/B2.  
29. Workspace does not present B1/B2 as shop page document body fields.  
30. Product-search overlay resolves via the same technical shop anchor after bridge extension.

### Dynamic text

31. Result-count “Showing …” templates remain Deferred unless a safe official filter appears.  
32. Runtime numbers/queries not used as identity.  
33. B4 no-products-found remains Deferred without a data filter.

### Platform

34. Workspace lists B1/B2.  
35. Manual edit/save works.  
36. Review approve/reject path works.  
37. TM policy respected.  
38. Glossary/Suggestions path works.  
39. Jobs compatible for materialized units.  
40. Diagnostics bounded.

### Live / quality

41. Live category (and search after bridge extension) EN/SV labels correct for Supported units; FP=0; leakage=0.  
42. A.7a / Gutenberg / Elementor / Fluent Forms regressions PASS; foreign Woo source audit PASS.

---

## 15. Stop conditions

**Defer candidate** if:

- owner is theme/plugin rather than Woo  
- runtime string has no deterministic pre-interpolation hook  
- identity would require rendered text / scrape  
- functional option values would be mutated  
- Store/schema/API redesign or new source type required  
- false ownership of shop page content would result  

**Stop the milestone** only if meaningful Supported work requires Store redesign, schema redesign, Integration API v1 redesign, a new identity family, or forced ownership theft.

---

## 16. Out of scope

A.7a product/catalog content; A.7c customer workflow; A.7d emails; extensions; merchant UI; theme/loop-card redesign; wp-admin; BTCPay/multicurrency; layered nav (absent).

---

## 17. Risks

| Risk | Mitigation |
|---|---|
| Elementor shop hides classic orderby | Still extract/overlay for native archives; shop may show no UI — not a failure |
| Blocksy removes result-count/orderby via theme mod | Compatibility: no fatal; source remains |
| Search bridge gap | A7B.4 explicit extension; until then search may miss overlays |
| Thin Supported set | Honest freeze preferred over ownership theft |
| False shop-page ownership UX | Mandatory Workspace labeling |

---

## 18. Architecture verdict

**Complete** for admitted Woo-owned archive surface **B1–B2 only**.

- No new ADR required.  
- Shop page Store host reuse is a **technical anchor** with evidence — not canonical content ownership.  
- Broader archive UI remains Deferred to correct owners or future ADR if gettext-only Woo templates become strategic.
- Search bridge maps product search → shop page Store anchor inside IntegrationFrontendBridge (no Store redesign).
- Woo injects search `relevance` **after** `woocommerce_catalog_orderby`; filter-mediated B1 keys still translate on search.

**Final Supported:** B1 catalog orderby option labels; B2 orderedby/status labels.  
**Final Deferred:** B3–B12 unchanged.  
**Next milestone:** A.7c planning (not started).

---

## 19. Document control

| Version | Date | Notes |
|---|---|---|
| 1.0 | 2026-08-08 | Initial freeze on planning branch from A.7a-complete baseline |
