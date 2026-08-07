# A.1 — Plugin Integration Framework — Validation Log

**Milestone:** A.1 Plugin Integration Framework  
**Implementation branch:** `feature/a1-plugin-integration-framework`  
**Plan:** [A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md](A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md)  
**ADR:** [0017-plugin-integration-framework-ownership-and-identity.md](../adr/0017-plugin-integration-framework-ownership-and-identity.md) (**Accepted**)  
**Baseline:** `main` @ `e08c2567a4881cc7d8c448e594d3e748be218c85`

---

## A10 — Baseline / contract inventory

**Status:** PASS

| Item | Value |
|---|---|
| Schema TARGET | **6** (`Migrator::TARGET`) |
| Store `segment_key` | `VARCHAR(191)` — no bump in A.1 |
| Key families | Gutenberg `b:`; Elementor `e:`; reserved integration `p:` (new) |
| Composition root | `src/Plugin.php` — AdapterRegistry / ElementorControlRegistry pattern |
| Capabilities | Existing Workspace / translation capabilities (no per-plugin sprawl) |
| Diagnostics pattern | Logger → `do_action` → Aggregator counters (block/Elementor precedent) |
| Workspace metadata | Additive keys on extracted segments (`surface`, widget/control labels, etc.) |
| PluginGuard | No new REST; no new `$wpdb` repos expected; typed registration only |
| Public ZIP | `bin/build-zip.sh` copies `src/` + root plugin files only — `tests/` excluded |
| Existing IntegrationRegistry | **None** (confirmed pre-A11) |

### Baseline gates (pre-framework code)

| Gate | Result |
|---|---|
| Unit | 508 tests, 1247 assertions — OK (2 skipped) |
| PluginGuard | 17 tests, 8054 assertions — OK |
| PHPCS | PASS (0 errors; pre-existing warnings only) |
| `git diff --check` | PASS |

---

## A11–A18

*(filled as work packages complete)*
