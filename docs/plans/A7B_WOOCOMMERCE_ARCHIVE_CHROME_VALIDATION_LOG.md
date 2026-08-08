# A.7b — WooCommerce Archive Chrome — Validation Log

**Milestone:** A.7b WooCommerce Archive Chrome
**Implementation branch:** `feature/a7b-woocommerce-archive-chrome`
**Plan:** [A7B_WOOCOMMERCE_ARCHIVE_CHROME_IMPLEMENTATION_PLAN.md](A7B_WOOCOMMERCE_ARCHIVE_CHROME_IMPLEMENTATION_PLAN.md)
**Planning merge on main:** `eee83f13cfb911d33baf3cbadc0aaedd4ae49d72`
**Initial main HEAD (pre plan merge):** `ef1a63563d553ab018a33498072e3cef5f03ccaf`
**Implementation baseline HEAD:** `eee83f13cfb911d33baf3cbadc0aaedd4ae49d72`
**Merged / tagged:** _pending merge/tag on main_

---

## A7B.0 — Baseline

**Status:** PASS

### Live re-check

| Item | Value |
|---|---|
| WooCommerce | Active **10.9.4** (MIN_VERSION 10.0.0) |
| Shop page | `wc_get_page_id('shop')` → **3755** |
| Native cat/tag/search | Present; orderby/result-count still hooked when store is not coming-soon |
| B1/B2 ownership | Woo filters `woocommerce_catalog_orderby` / `woocommerce_catalog_orderedby` |
| Blocksy pagination | Still replaces Woo pagination template |
| Shop Elementor/storefront/loop-card | Unchanged ownership split |
| TARGET | **6** |
| A.7b production code (pre A7B.2) | Absent |

---

## A7B.1 — Admission freeze

**Status:** PASS — Supported **B1 + B2** only. Records: [a7b-evidence/a7b-admission-records.md](a7b-evidence/a7b-admission-records.md).

---

## A7B.2 — Identity + Store context

**Status:** PASS

- `PluginIdentity::build('woocommerce','catalog_orderby',{key},'label')` / `catalog_orderedby`
- `IntegrationFrontendBridge` resolves `product_cat` / `product_tag` / product search → `wc_get_page_id('shop')`
- No new source type; no Store redesign

---

## A7B.3 — Orderby extraction

**Status:** PASS — 7 B1 + 6 B2 units on shop host (13 total). Allowlist exact. Live extract on post **3755** confirmed.

---

## A7B.4 — Overlay

**Status:** PASS — Official filters only; keys unchanged; values translated; miss → source.

---

## A7B.5 — Ownership boundaries

**Status:** PASS — No hooks for B3–B8 deferred surfaces; A.7a identities distinct.

---

## A7B.6 — Lifecycle / diagnostics

**Status:** PASS — Disabled → empty extract + no overlay; Workspace `parent_context=WooCommerce archive chrome`; owner_id = functional key (not shop page ID).

---

## A7B.7 — Full acceptance

**Status:** PASS (with documented relevance limitation)

### Quality gates

| Gate | Result |
|---|---|
| Unit | **572** tests / **1518** assertions (2 skipped) — OK |
| Integration | **512** tests / **11761** assertions (2 skipped) — OK |
| PluginGuard | **17** tests / **8768** assertions — OK |
| PHPCS | **0 errors** (warnings only, pre-existing style + foreign Woo hook names) |
| `git diff --check` | Clean after whitespace fix |
| Markdown local links (A7B docs) | PASS |
| TARGET | **6** unchanged |

### Live matrix (dev.biopentra.eu)

Store coming-soon was temporarily set to `no` for HTTP archive chrome (restored to `yes` after). Overlay also proven via WP-CLI filter simulation without HTML.

| # | Check | Result |
|---|---|---|
| 1 | Woo compatible | PASS |
| 2 | Shop technical Store anchor | PASS (3755) |
| 3 | B1 units extracted | PASS (7) |
| 4 | B2 units extracted | PASS (6) |
| 5 | Canonical `p:` identities | PASS |
| 6 | Functional sort keys unchanged | PASS (`value="popularity"`, `price-desc`) |
| 7 | EN ordering labels source-correct | PASS |
| 8 | SV ordering labels translated | PASS (cat/tag/search filter-mediated keys) |
| 9 | Shop context | PASS (anchor host) |
| 10 | Category context | PASS |
| 11 | Tag context | PASS |
| 12 | Search context | PASS for filter-mediated labels; see limitation |
| 13 | Store miss → source | PASS |
| 14 | Stale → safe | PASS (unit/overlay isolation) |
| 15 | One-unit failure isolated | PASS |
| 16–21 | Workspace / Review / TM / Glossary / Jobs | PASS via existing `p:` path + A.7a reuse (no new pipeline) |
| 22–24 | Woo disabled / reactivation / unsupported | PASS (compatibility ladder) |
| 25 | Shop-page anchor change | PASS (uses `wc_get_page_id`, not hardcoded) |
| 26 | Foreign Woo persistence unchanged | PASS (filters only) |
| 27 | A.7a regression | PASS (`Återhämtningsstöd` on SV category) |
| 28–30 | Gutenberg / Elementor / Fluent Forms | No code path changes; prior suites green |
| 31–33 | Blocksy / loop-card / storefront outside Woo surface | PASS (no hooks) |
| 34 | Rendered FP | **0** |
| 35 | Language leakage | **0** (EN page has no SV orderby labels) |
| 36 | Duplicate logical units | **0** |

### Known limitation (not STOP)

WooCommerce merges `relevance => __( 'Relevance' )` **after** `woocommerce_catalog_orderby` on search loops. B1 still extracts/overlays `relevance` when present in the filter payload; live search injects the label post-filter so the search **Relevance** option may remain source text. Other search orderby options translate correctly (bridge + overlay proven).

### Performance

No invented budgets. Extraction is O(allowlist); overlay is O(options on the filter map); Store lookup reuses IntegrationFrontendBridge per segment.

---

## A7B.8 — Closure

**Status:** PASS — Supported B1+B2; Deferred B3–B12 unchanged. Next milestone = **A.7c planning** (not started).
