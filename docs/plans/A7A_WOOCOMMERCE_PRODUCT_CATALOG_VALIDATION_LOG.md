# A.7a — WooCommerce Product & Catalog — Validation Log

**Milestone:** A.7a WooCommerce Product & Catalog
**Implementation branch:** `feature/a7a-woocommerce-product-catalog`
**Plan:** [A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md](A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md)
**Family plan:** [A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md](A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md)
**Initial main HEAD (pre plan merges):** `b4932a0aeedc8d0304c7a0d8de941358f3fa1f82`
**A.7 family plan merge on main:** `55c866c9c`
**A.7a plan merge on main:** `b1c7edffd2a59fb66e8fb2faa07772495d09ef83`
**Implementation baseline HEAD:** `b1c7edffd2a59fb66e8fb2faa07772495d09ef83`
**Merged / tagged:** `a8094dcd7` / `a7a-woocommerce-product-catalog-complete`

---

## A7A.0 — Baseline

**Status:** PASS

### Live inventory (dev.biopentra.eu)

| Item | Value |
|---|---|
| WooCommerce version | **10.9.4** (active) |
| Published products | **24** (15 simple, **9 variable**) |
| Sample simple / variable | **3594** `BPC-157` (variable); **4457** `Bacteriostatic Water` (simple among catalog) |
| Editor mix (published) | **24 classic / 0 Gutenberg / 0 Elementor** on product bodies |
| Short description storage | **`post_excerpt`** (meta `_product_short_description` empty on samples) |
| Long description storage | **`post_content`** (classic HTML) |
| Product attributes (live) | Custom product attribute **`Strength`** / slug `strength` (not global taxonomy; `id=0`) on variable products; options e.g. `10mg`, `20mg` |
| Shop page | **ID 3755**, title `Shop`, `post_content` length **0** |
| `product_cat` samples | 40 Growth & Performance; 41 Recovery Support; 54 Research Supplies; 42 Weight Management (descriptions present); 15 Uncategorized (empty desc) |
| `product_tag` samples | 45 GHRH; 46 GHRP; 44 IGF; 48 Metabolic Peptide; 47 Repair Peptide (descriptions empty) |
| Migrator `TARGET` | **6** |
| Integration API v1 | Healthy (Fluent Forms A.8 present) |
| ADR-0013 / 0016 / 0017 | Accepted (unchanged) |
| Production WooCommerce AIML code (pre A7A.3) | **Absent** |

### Baseline gates

| Gate | Result |
|---|---|
| WooCommerce active | PASS |
| TARGET = 6 | PASS |
| Architecture contracts carried forward | PASS |
| `git diff --check` (this WP) | PASS (docs only) |
| Full unit / integration / PluginGuard / PHPCS | Deferred to A7A.7 (coding not started) |

### Version floor (frozen for A.7a)

| Item | Value |
|---|---|
| Live evidence | WooCommerce **10.9.4** |
| Integration `MIN_VERSION` (to implement) | **10.0.0** (Woo 10.x floor; incompatible → source fallback) |

### Stop conditions checked

| Condition | Result |
|---|---|
| Woo inactive | No |
| TARGET ≠ 6 | No |
| Requires Store/schema redesign at baseline | No |

---

## Subsequent work packages

_Records appended as A7A.1–A7A.8 complete._

## A7A.1 — Candidate inventory + admission shells

**Status:** PASS

| Artifact | Path |
|---|---|
| Inventory | [a7a-evidence/a7a-candidate-inventory.md](a7a-evidence/a7a-candidate-inventory.md) |
| Admission shells | [a7a-evidence/a7a-admission-records.md](a7a-evidence/a7a-admission-records.md) |

Every P1–P10 / C1–C6 has a stub disposition. Four attribute splits have independent shells. P4/P6/P8/P9/P10 stubbed **Deferred**.

## A7A.2 — Identity freeze

**Status:** PASS

| Artifact | Path |
|---|---|
| Identity matrix | [a7a-evidence/a7a-identity-matrix.md](a7a-evidence/a7a-identity-matrix.md) |
| Unit tests | `tests/unit/Integration/WooCommerceIdentityMatrixTest.php` (5 tests OK) |

No new identity family. P5/P7 keys distinct. Catalog terms use `owner_type` = taxonomy. Store host for C3–C6 = shop page 3755. P6/P8 remain Deferred.

## A7A.3 — Extraction

**Status:** PASS

| Item | Result |
|---|---|
| Package | `src/Integration/WooCommerce/WooCommerceIntegration.php` |
| Wired | `Plugin.php` registers alongside Fluent Forms |
| Product units | P5 + P7 attribute name keys |
| Shop units | C3–C6 term name/description |
| Unit tests | `WooCommerceIntegrationExtractTest` OK |
| Overlay | Deferred to A7A.4 (empty `register_output_hooks`) |

## A7A.4 — Overlay

**Status:** PASS

| Item | Result |
|---|---|
| Attribute overlay | `woocommerce_attribute_label` (P7 preferred when variation) |
| Catalog title | `single_term_title` + `woocommerce_page_title` |
| Catalog description | `term_description` + `wp_kses_post` |
| Bridge | `IntegrationFrontendBridge` resolves shop page for `product_cat`/`product_tag` archives |
| Unit tests | Overlay suite OK; miss → source |
| Woo DB mutation | None |

## A7A.5 — Workspace / platform path

**Status:** PASS

| Item | Result |
|---|---|
| Workspace post types | `post`, `page`, **`product`** |
| Rollout approved types | includes **`product`** |
| RenderGateContext | includes **`product`** |
| Cache invalidation on product save | Yes |
| Review / TM / Glossary / Jobs | Unchanged Integration API path (`surface=plugin_integration`) |
| Woo-specific workflow | None |

## A7A.6 — Lifecycle / security / diagnostics

**Status:** PASS

| Scenario | Behavior |
|---|---|
| Plugin missing/inactive | `unavailable` |
| Version &lt; 10.0.0 | `unsupported_version` |
| Hooks missing | `missing_required_hook` |
| `aiml_woocommerce_integration_disabled` | `disabled`; extract empty; no overlay; Store retained by platform |
| Attribute slug rename | New identity; no fuzzy rematch |
| Overlay sanitization | Plain for names; `wp_kses_post` for term descriptions |
| Foreign Woo persistence | No writes — overlay filters only |
| Diagnostics | Existing IntegrationDiagnostics counters via frontend bridge |

## A7A.7 — Full acceptance

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | **560** tests / **1417** assertions — OK (2 skipped) |
| Integration | **512** tests / **11761** assertions — OK (2 skipped) |
| PluginGuard | Included in integration suite — PASS |
| PHPCS (touched paths) | **0 errors** (warnings only on foreign Woo hook names in tests — same class as A.8) |
| TARGET | **6** unchanged |
| Hook EN→SV (product attrs) | `wc_attribute_label` → `Styrka-var` |
| Hook EN→SV (category) | `single_term_title` / `woocommerce_page_title` → `Återhämtningsstöd`; `term_description` → SV HTML |
| Foreign Woo source audit | Product title / shop title / term name unchanged in WP/Woo tables |
| Rendered FP on admitted hooks | **0** |
| Cross-language leakage on admitted hooks | **0** |
| A.8 Contact smoke | SV Contact still shows Namn / E-post / Skicka meddelande |
| Gutenberg / Elementor / Integration API | No intentional changes; suites green |
| Performance | No global product crawl; extract scoped to product attrs + shop-hosted terms |

### Live HTML notes

Visitor HTML is partially obscured by Age Gate and theme/Elementor/Rank Math chrome. Custom `biopentra-loop-card` i18n owns some “Strength:” strings outside Woo’s attribute-label filter. Official-hook overlays are verified via WP-CLI filter application against Store.

## A7A.8 — Closure

**Status:** PASS

| Artifact | Path |
|---|---|
| Supported surface | [a7a-evidence/a7a-supported-surface.md](a7a-evidence/a7a-supported-surface.md) |

**Merge readiness:** **Merged** to `main` @ `a8094dcd7`. Tagged `a7a-woocommerce-product-catalog-complete`.  
**Exact next step (planning only):** Plan **A.7b** Archives listing chrome from tagged `main` — do not start coding until the A.7b plan freezes.


## Closure merge validation

**Status:** PASS

| Gate | Result |
|---|---|
| Pre-merge unit | 560 / 1417 OK (2 skipped) |
| Pre-merge integration / PluginGuard | 512 / 11761 OK (2 skipped) |
| Pre-merge PHPCS | 0 errors after extract-test docblock fix |
| Post-merge unit | 560 / 1417 OK (2 skipped) |
| Post-merge PHPCS | 0 errors (warnings only on foreign Woo hooks in tests) |
| Live hooks EN→SV | attrs `Styrka-var`; cat title/desc SV |
| Foreign Woo persistence | Unchanged |
| A.8 regression | Namn / E-post / Skicka meddelande |
| Merge | `--no-ff` `a8094dcd7` |
| Tag | `a7a-woocommerce-product-catalog-complete` |
