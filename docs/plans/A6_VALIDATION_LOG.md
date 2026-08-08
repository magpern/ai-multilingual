# A.6 — WordPress Visitor Chrome — Validation Log

**Milestone:** A.6 WordPress Visitor Chrome  
**Implementation branch:** `feature/a6-wordpress-visitor-chrome`  
**Plan:** [A6_WORDPRESS_VISITOR_CHROME_IMPLEMENTATION_PLAN.md](A6_WORDPRESS_VISITOR_CHROME_IMPLEMENTATION_PLAN.md)  
**Evidence:** [a6-evidence/](a6-evidence/)  
**Planning freeze on main:** `8db7d5c67fd0f78314232c8730000fa2ff9abe55`  
**Implementation baseline HEAD:** `8db7d5c67fd0f78314232c8730000fa2ff9abe55`

---

## A6.0 — Baseline

**Status:** PASS

### Preconditions

| Item | Result |
|---|---|
| `main` clean / synced at branch cut | **Pass** (`8db7d5c67…`) |
| A.6 plan Architecture Frozen on `main` | **Pass** |
| TARGET | **6** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 | **Accepted** |
| Integration API v1 | Present / unchanged |
| A.7d tag `a7d-woocommerce-customer-emails-complete` | Present |
| A.8 tag `a8-fluentforms-contact-integration-complete` | Present |
| A.6 production code (pre A6.2) | Absent |

### Live ownership re-check (dev.biopentra.eu)

| Item | Value |
|---|---|
| Theme | `blocksy-child` / parent Blocksy |
| Main Menu | term_id **34** |
| Custom title fixture | nav_menu_item **3474** — `post_title` = `Home` |
| Object-title items | Shop / News / Contact (empty custom `post_title`) |
| Widgets | `widget_block_*` + `woocommerce_products-2` (Deferred) |
| Blocksy header_text / copyright | Present (Deferred D1/D2) |
| Renderer `nav_menu_item` skip | Still present pre-A6.4 |

---

## A6.1 — Admission freeze

**Status:** PASS — Supported **N1** only. Records: [a6-evidence/a6-admission-records.md](a6-evidence/a6-admission-records.md).

---

## A6.2 — Identity + Workspace contract

**Status:** PASS

- N1 = `post_title` on `source_id` = menu item post ID; no `p:` / PluginIdentity
- Workspace `SUPPORTED_POST_TYPES` includes `nav_menu_item`
- List label prefix `Menu item: …`
- Contract: [a6-evidence/identity-workspace-contract.md](a6-evidence/identity-workspace-contract.md)
- No new identity family; TARGET **6**

---

## A6.3 — Extraction (N1)

**Status:** PASS — `Extractor` returns title-only for non-empty `nav_menu_item.post_title`; empty custom titles → no units.

---

## A6.4 — Overlay (N1)

**Status:** PASS — `Renderer::filter_title` overlays `nav_menu_item` via Store `post_title`; miss → source; HOOKS.md updated.

---

## A6.5 — Deferred chrome

**Status:** PASS — D1–D20 remain Deferred; no theme_mod/widget_block/gettext/storefront code. See [a6-evidence/deferred-surfaces-confirmed.md](a6-evidence/deferred-surfaces-confirmed.md).

---

## A6.6 — Workspace / lifecycle

**Status:** PASS

- `nav_menu_item` in Workspace allowlist (A6.2)
- List label `Menu item: …`
- Existing `save_post` stale detection syncs N1 via Extractor title-only path
- Review / TM / Glossary / Jobs reuse Store `post_title` segments — no second pipeline
- Diagnostics: no new counters; no PII

---

## A6.7 — Full acceptance

**Status:** PASS

### Quality gates

| Gate | Result |
|---|---|
| Unit | **586** tests / **1559** assertions (2 skipped) — OK |
| Integration | **519** tests / **11865** assertions (2 skipped) — OK |
| PluginGuard | **17** tests / **8836** assertions — OK |
| PHPCS (touched PHP) | **PASS** (`Extractor.php`, `Renderer.php`, `WorkspaceService.php`) |
| TARGET | **6** unchanged |

### Live EN → SV → EN (`wp_nav_menu` menu **34**)

| Step | menu-item-3474 (custom) | menu-item-3756 (object title) |
|---|---|---|
| EN | Home | Shop |
| SV | **Hem** | **Butik** (page title AC1) |
| EN | Home | Shop |

- False positives on N1: **0**
- Language leakage EN↔SV: **0**
- Empty custom title extract count for item 3756: **0**

**Note:** Homepage header chrome uses Elementor mega-menu (`n-menu`) with document `item_title` strings — **Elementor-owned** (AC3 / not A.6). N1 applies to WordPress `nav_menu_item` / `wp_nav_menu` (locations `menu_1`, `menu_mobile`). No ownership theft.

### Regressions (suite-level)

| Surface | Result |
|---|---|
| Gutenberg / Elementor / Woo A.7a–A.7d / Fluent A.8 | Covered by full integration suite — **PASS** (no A.6-related failures) |

### Architecture audit

| Check | Result |
|---|---|
| Supported = N1 only | Pass |
| Deferred D1–D20 untouched | Pass |
| Identity = `post_title` / menu item `source_id` | Pass |
| No PluginIdentity / `p:` for N1 | Pass |
| No Store/schema redesign | Pass |
| No HTML scrape / gettext capture | Pass |

---

## A6.8 — Closure

**Status:** PASS — plan/log/roadmap mark Supported **N1** Complete on impl branch; Deferred unchanged; recommended tag `a6-wordpress-visitor-chrome-complete` after independent review/merge. No merge/tag in this milestone execution.
