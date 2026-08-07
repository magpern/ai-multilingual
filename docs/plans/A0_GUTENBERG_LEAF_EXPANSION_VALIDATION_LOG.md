# A.0 — Gutenberg Leaf Expansion — Validation Log

**Milestone:** A.0 Gutenberg Leaf Expansion  
**Implementation branch:** `feature/a0-gutenberg-leaf-expansion`  
**Plan:** [A0_GUTENBERG_LEAF_EXPANSION_IMPLEMENTATION_PLAN.md](A0_GUTENBERG_LEAF_EXPANSION_IMPLEMENTATION_PLAN.md)  
**Plan merge on main:** `074b920d8c72ca67e75b95ed4572043ff0408695`  
**Baseline (pre-admission coding):** `074b920d8c72ca67e75b95ed4572043ff0408695`

---

## A00 — Baseline and current coverage inventory

**Status:** PASS

### Preconditions

| Item | Value |
|---|---|
| Schema TARGET | **6** |
| Identity grammar | `b:<uuid>:<field>` |
| `Contract::SUPPORTED_FIELDS` | `content` only (pre-admission) |
| `SUPPORTED_BLOCKS` | paragraph, heading, button, list-item, preformatted, verse, code |
| Adapters | Seven leaf adapters in `AdapterRegistry` |
| A.4 nested | Complete (`a4-nested-gutenberg-complete`) |
| Elementor A.2/A.3 | Complete |
| Integration API v1 | Complete (`a1-plugin-integration-framework-complete`) |
| ADR-0017 | Accepted |

### Baseline gates

| Gate | Result |
|---|---|
| Unit | **523** tests / **1283** assertions — OK (2 skipped) |
| Integration | **510** tests / **11328** assertions — OK (2 skipped) |
| PluginGuard | **17** tests / **8360** assertions — OK |
| PHPCS (`src/Block`) | PASS (0 errors) |
| `git diff --check` | PASS |

No new block support in A00.

---

## Subsequent work packages

_Records appended as A01–A08 complete._
