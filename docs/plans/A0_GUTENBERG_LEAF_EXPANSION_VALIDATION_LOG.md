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

## A01 — Candidate inventory + admission matrix

**Status:** PASS

| Artifact | Path |
|---|---|
| Admission matrix | [A0_ADMISSION_MATRIX.md](A0_ADMISSION_MATRIX.md) |

Live publish counts (dev): quote=1, details=1, pullquote=1, table=0, image=0, file=0, audio=0, video=0, social-link=0, buttons=0.

Wave 3 default: **zero admissions** (no deterministic extra leaves in inventory).

## A02 — Wave 1 field admissions

**Status:** PASS

| Candidate | Disposition | Segment keys |
|---|---|---|
| `core/quote` citation | **Admitted** | `b:<uuid>:citation` |
| `core/details` summary | **Admitted** | `b:<uuid>:summary` |
| `core/pullquote` content/citation | **Admitted** (leaf form); nested-child-only hosts without citation remain child-only | `b:<uuid>:content`, `b:<uuid>:citation` |

## A03 — Structured textual candidates

**Status:** PASS (deferral)

| Candidate | Disposition | Reason |
|---|---|---|
| `core/table` | **Deferred** | Cell arrays need path/index identity |

## A04 — Block-local media/text candidates

**Status:** PASS

| Candidate | Disposition | Fields |
|---|---|---|
| `core/image` | **Admitted** | `caption` (figcaption only; alt/title denied) |
| `core/file` | **Admitted** | `fileName`, `downloadButtonText` |
| `core/audio` | **Admitted** | `caption` |
| `core/video` | **Admitted** | `caption` |

Media Library attachment metadata: **Unsupported**.


## A05 — Workspace + diagnostics consolidation

**Status:** PASS

Existing `BlockExtractionLogger` / `BlockRenderLogger` events cover newly admitted fields (`block_extracted`, `field_skipped`, `block_rendered`, nested/host counters). No new high-cardinality telemetry. New segments use existing Store → Workspace → Review → TM → Glossary → Jobs path without redesign.

## A06 — Performance + regression hardening

**Status:** PASS

| Observation | Value |
|---|---|
| Live A4 fixture inject | ~4.4 ms |
| Live A4 fixture extract (15 units) | ~1.7 ms |
| Live render citation+summary | ~19 ms |
| Duplicate logical units | **0** |
| N+1 Store behavior | Not introduced (existing per-key lookup) |

Regression locks: unit NestedGutenbergAdmission + A0LeafAdmission + full suites green.

## A07 — Full acceptance

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | **531** / **1324** — OK (2 skipped) |
| Integration | **510** / **11573** — OK (2 skipped) |
| PluginGuard | **17** / **8598** — OK |
| PHPCS (`src/Block`) | PASS |
| `git diff --check` | PASS |
| Live EN `/a4-nested-gutenberg-fixture/` | HTTP 200; citation/summary/source present |
| Live SV `/sv/a4-nested-gutenberg-fixture/` | HTTP 200 after IntegrationFrontendBridge fix |
| Rendered FP (render map smoke) | **0** |
| Language leakage (render map smoke) | **0** |
| Duplicate logical units | **0** |

Collateral fix: `IntegrationFrontendBridge` used undefined `LanguageContext::language()`; corrected to `current()` (A.1 regression).

## A08 — Documentation closure

**Status:** PASS — final supported-surface table below.

### Existing baseline
`core/paragraph|heading|button|list-item|preformatted|verse|code` → `content`

### Newly admitted
| Block | Fields |
|---|---|
| `core/quote` | `citation` |
| `core/details` | `summary` |
| `core/pullquote` | `content`, `citation` (leaf form) |
| `core/image` | `caption` |
| `core/file` | `fileName`, `downloadButtonText` |
| `core/audio` | `caption` |
| `core/video` | `caption` |

### Partially admitted
`core/pullquote` with nested children and no host citation → child-only (host not eligible).

### Evaluated but deferred
| Candidate | Reason |
|---|---|
| `core/table` | Cell arrays require path/index identity |

### Hard deferred
Navigation, Query, post-template, reusable/shared, synced patterns, dynamic bindings, Media Library ownership, parent list-item + innerBlocks ambiguity, wp-admin, email.

### Wave 3
**Zero admissions** (PASS).
