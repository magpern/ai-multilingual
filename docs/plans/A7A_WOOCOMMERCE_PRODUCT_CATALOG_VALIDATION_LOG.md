# A.7a — WooCommerce Product & Catalog — Validation Log

**Milestone:** A.7a WooCommerce Product & Catalog
**Implementation branch:** `feature/a7a-woocommerce-product-catalog`
**Plan:** [A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md](A7A_WOOCOMMERCE_PRODUCT_CATALOG_IMPLEMENTATION_PLAN.md)
**Family plan:** [A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md](A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md)
**Initial main HEAD (pre plan merges):** `b4932a0aeedc8d0304c7a0d8de941358f3fa1f82`
**A.7 family plan merge on main:** `55c866c9c`
**A.7a plan merge on main:** `b1c7edffd2a59fb66e8fb2faa07772495d09ef83`
**Implementation baseline HEAD:** `b1c7edffd2a59fb66e8fb2faa07772495d09ef83`
**Merged / tagged:** _not yet — stop after implementation; do not merge/tag_

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
