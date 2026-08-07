# A.R2 — Nested Gutenberg Identity Research Log

**Status:** Complete  
**Charter:** [A4_NESTED_GUTENBERG_IDENTITY_PLAN.md](A4_NESTED_GUTENBERG_IDENTITY_PLAN.md)  
**Research branch:** `feature/ar2-nested-gutenberg-identity`  
**Planning merge:** `42b306d462672e6ddc9b2a43e32cb5067376d136`  
**Recommendation:** **CONDITIONAL GO**  
**ADR decision:** **No new ADR required** for the bounded minimum A.4 surface (deferred shared-definition families would need a separate ADR if later admitted)  
**F5 readiness:** **PASS** for the bounded approved surface  

This log is the source of truth for A.R2 findings. Confidence labels:

| Label | Meaning |
|---|---|
| **Proven by experiment** | Direct fixture / WP-CLI / unit evidence |
| **Supported by evidence** | Strong indirect evidence |
| **Inferred** | Reasonable but not measured |
| **Remaining assumption** | Explicitly not yet proven |

Fast-track architectural claims below rely only on **Proven by experiment** or **Supported by evidence**.

---

## Environment

| Fact | Value |
|---|---|
| Site | https://dev.biopentra.eu |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → WP plugins bind-mount |
| Research path | `research/ar2-nested-gutenberg-identity/` |
| Execution | `wp eval-file` via `apps/wordpress` wpcli |
| Focused unit baseline | `BlockTreeWalkerTest`, `BlockExtractorTest`, `ListItemAdapterTest`, `BlockRegistryTest` — **26/26 PASS** |

---

## A40 — Baseline nested-block inventory + fixture corpus

**Status:** Complete  
**Evidence:** [`a40-environment.json`](../../research/ar2-nested-gutenberg-identity/evidence/a40-environment.json), [`a40-inventory.json`](../../research/ar2-nested-gutenberg-identity/evidence/a40-inventory.json), [`a40-summary.json`](../../research/ar2-nested-gutenberg-identity/evidence/a40-summary.json)

### Production contracts verified

| Contract | Observation | Confidence |
|---|---|---|
| Grammar `b:<uuid>:<field>` | `Contract::SEGMENT_KEY_GRAMMAR` | Proven by experiment |
| UUID attr `aimlBlockId` | `Contract::ATTR_NAME` | Proven by experiment |
| Supported leaves | paragraph, heading, button, list-item, preformatted, verse, code | Proven by experiment |
| Adapter registry | Same seven; no container adapters | Proven by experiment |
| Eligibility | Requires supported + non-dynamic + **empty `innerBlocks`** + non-empty innerHTML | Proven by experiment |
| Dynamic deny | Includes `core/navigation`, `core/query`, `core/block`, … | Proven by experiment |
| Walker | `BlockTreeWalker` DFS-recurses `innerBlocks` | Proven by experiment |

### Fixture corpus

Research fixtures (sanitized): structural, list-nested-with-uuids, textual quote/pullquote/details, media cover/media-text/gallery, shared/dynamic, deep-nested-performance.

Authored corpus reused read-only: `list-nested.html`, `nested-group-columns.html`, `quote-with-citation.html`, `reusable-block.html`, `synced-pattern.html`, `dynamic-block.html`.

### A40 conclusions

1. Baseline identity/admission contracts match the planning hypothesis. — **Proven by experiment**
2. No nested production adapters exist yet. — **Proven by experiment**

---

## A41 — UUID stability under nesting / move / duplicate

**Status:** Complete  
**Evidence:** [`a41-uuid-stability.json`](../../research/ar2-nested-gutenberg-identity/evidence/a41-uuid-stability.json)

**Result:** **16/16 experiments PASS**

| Experiment | Result |
|---|---|
| Edit nested child text | Key retained; source hash changes |
| Reorder siblings | Keys retained |
| Move child between containers | Key retained (path is not identity) |
| Move container / column reorder | Keys retained |
| Duplicate child / container / page | First-wins extract + `UuidInjector` repair → unique keys |
| Same-doc copy/paste | Same as duplicate child |
| Cross-document copy/paste | Same keys OK (document-local Store ownership) — **Supported by evidence** |
| Wrap / unwrap Group | Keys retained |
| List item reorder / duplicate | Keys retained / repaired unique |
| Revision restore (re-parse) | Keys match baseline |
| Invalid freeform + valid tree | Supported keys retained |

### Primary decision

> Does existing child UUID identity remain deterministic without parent/path identity?

**YES** — **Proven by experiment**

---

## A42 — Recursive extraction / traversal model

**Status:** Complete  
**Evidence:** [`a42-traversal.json`](../../research/ar2-nested-gutenberg-identity/evidence/a42-traversal.json)

| Case | Result |
|---|---|
| Supported leaf in unsupported structural parent | Extracts (group → paragraph/heading) |
| Nested list leaves | Extracts 4 leaf list-items |
| Unsupported child among supported | Supported still extract |
| Deep nesting | Extracts depth ≥3 leaves |
| Empty innerHTML leaf | Not eligible; no extract |
| Dynamic navigation/query/reusable | Extract count 0 |
| Nested list-item with `innerBlocks` | Not eligible; adapter not translatable |

### Gap classification

| Not the gap | True gap |
|---|---|
| Missing recursion | Eligibility / adapter admission for non-empty `innerBlocks` instances |
| Identity grammar | (e.g. parent list-item that owns text + nested list) |
| Renderer architecture | Field admission for citation/summary/pullquote/image caption |

**Conclusion:** Existing walker + extractor already recurse. — **Proven by experiment**

---

## A43 — Structural container classification

**Status:** Complete  
**Evidence:** [`a43-structural.json`](../../research/ar2-nested-gutenberg-identity/evidence/a43-structural.json)

| Family | Classification |
|---|---|
| `core/group` | structural-only / existing child traversal |
| `core/columns` | structural-only / existing child traversal |
| `core/column` | structural-only / existing child traversal |

No container translation units; no adapters; container reorder preserves child keys. — **Proven by experiment**

---

## A44 — Textual container admission

**Status:** Complete  
**Evidence:** [`a44-textual.json`](../../research/ar2-nested-gutenberg-identity/evidence/a44-textual.json)

### List hard gate

| Requirement | Result |
|---|---|
| `core/list` does not duplicate `list-item` text | **PASS** — no list adapter; 0 list units |
| Nested leaf list-item UUIDs extract | **PASS** — nested A/B keys present |
| Reorder without path identity | **PASS** (A41) |
| Duplicate safe | **PASS** (A41 repair) |
| Parent list-item with innerBlocks text | **Skipped today** (eligibility) — admission gap, not identity gap |

Duplicate extraction stop condition: **not triggered**.

### Quote / Pullquote / Details

| Family | Child text | Parent attr/markup text | Disposition |
|---|---|---|---|
| `core/quote` | Paragraph extracts | Citation in quote markup — not extracted | child traversal + citation field admission (later) |
| `core/pullquote` | No child paragraph in fixture | Body+citation in parent HTML | adapter/field admission or defer |
| `core/details` | Body paragraph extracts | Summary in parent markup — not extracted | child traversal + summary field admission (later) |

Identity grammar `b:<uuid>:<field>` remains sufficient. — **Proven by experiment**

---

## A45 — Media / layout ownership

**Status:** Complete  
**Evidence:** [`a45-media.json`](../../research/ar2-nested-gutenberg-identity/evidence/a45-media.json)

| Family | Verdict |
|---|---|
| `core/cover` | Nested paragraph = existing child traversal; cover structural for text |
| `core/media-text` | Nested paragraph = existing child traversal |
| `core/gallery` / `core/image` | Captions/alts in block markup; **no** `core/image` adapter → defer field admission; do **not** claim Media Library ownership |

— **Proven by experiment**

---

## A46 — Navigation / shared / dynamic ownership

**Status:** Complete  
**Evidence:** [`a46-shared-dynamic.json`](../../research/ar2-nested-gutenberg-identity/evidence/a46-shared-dynamic.json)

| Family | Ownership | Disposition |
|---|---|---|
| `core/navigation` | shared / dynamic (`DYNAMIC_BLOCK_NAMES`) | **deferred** (ADR if later admitted) |
| `core/query` | dynamic runtime | **unsupported / deferred** |
| `core/post-template` | dynamic inside query | **unsupported / deferred** |
| synced / reusable (`core/block`) | shared-definition ref | **deferred** (ADR if later admitted) |

Candidate-local deferral is acceptable and does **not** fail A.R2. — **Proven by experiment**

---

## A47 — Rendering / cache / performance

**Status:** Complete  
**Evidence:** [`a47-render-perf.json`](../../research/ar2-nested-gutenberg-identity/evidence/a47-render-perf.json)

| Case | Result |
|---|---|
| Supported child inside unsupported parent | Overlay applied via existing `BlockRenderer` |
| Unsupported sibling | Remains source |
| Partial translation map | Local source fallback |
| Double apply same map | Stable HTML (no duplicate compounding) |
| Bad UUID beside good leaf | Good leaf still renders |
| Empty map | `changed=false` |
| HTML scraping | Not used — adapter-per-block walker |

### Performance observations (no invented budgets)

| Fixture | extract_ms | units | walker nodes | max depth |
|---|---:|---:|---:|---:|
| shallow structural | ~1.0 | 3 | 7 | 4 |
| deep nested | ~0.06 | 4 | 11 | 5 |
| list nested | ~0.05 | 4 | 7 | 4 |
| media mixed | ~0.03 | 2 | 7 | 2 |
| shared dynamic | ~0.01 | 0 | 6 | 3 |

— **Proven by experiment**

---

## A48 — Synthesis / verdict / F5 decision

**Status:** Complete  
**Evidence:** this log + [`a48-containment-audit.json`](../../research/ar2-nested-gutenberg-identity/evidence/a48-containment-audit.json)

### 1. Identity verdict

Existing `b:<uuid>:<field>` is sufficient for nested document-local leaves. Parent UUID / structural path must **not** become persistent identity. — **Proven by experiment**

### 2. Recursion verdict

`BlockTreeWalker` / `BlockExtractor` / `BlockRenderer` already recurse `innerBlocks`. — **Proven by experiment**

### 3. Container taxonomy

See A43–A46 classifications and minimum surface below.

### 4. List / list-item verdict

Hard gate **PASS**. Nested leaf list-items work under current grammar. Parent list-item with nested list is an **admission** follow-up (optional A.4 enhancement), not an identity redesign.

### 5. Quote / pullquote / details verdict

Children via traversal are safe. Citation / summary / pullquote body need optional field admission later.

### 6. Media ownership verdict

Cover / media-text child text safe. Image caption/alt deferred. No Media Library system.

### 7. Navigation / shared / dynamic verdict

Deferred. Shared-definition admission would require a **separate ADR** later — not required for minimum A.4.

### 8. Extraction verdict

Gap = eligibility / adapter admission, not traversal.

### 9. Rendering verdict

Existing renderer sufficient; no replacement; no scraping.

### 10. Performance observations

Recorded above; no budgets invented.

### 11. Unsupported / deferred matrix

| Item | Status |
|---|---|
| Navigation | deferred (shared/dynamic; ADR if admitted) |
| Query / post-template | unsupported / deferred |
| Synced / reusable `core/block` | deferred (shared-definition; ADR if admitted) |
| Pullquote body/citation | deferred adapter admission |
| Quote citation / details summary | deferred field admission |
| Gallery image caption/alt | deferred (`core/image` adapter) |
| Parent list-item text+innerBlocks | deferred admission enhancement |
| Media Library persistence | unsupported / out of scope |
| Path / parent identity | **denied** |
| Store / schema / second renderer | **denied** |

### 12. Minimum recommended A.4 surface (advisory until F5 formal close)

| Family | Classification |
|---|---|
| `core/group` | structural-only / child-traversal-safe |
| `core/columns` | structural-only / child-traversal-safe |
| `core/column` | structural-only / child-traversal-safe |
| `core/list` | structural wrapper — do **not** extract; child-traversal via list-item |
| nested `core/list-item` (leaf, empty `innerBlocks`) | child-traversal-safe (already supported once UUID present) |
| nested `core/list-item` (with `innerBlocks`) | deferred admission enhancement |
| `core/quote` | child-traversal-safe for inner paragraphs; citation deferred |
| `core/pullquote` | deferred adapter/field admission |
| `core/details` | child-traversal-safe for body; summary deferred |
| `core/cover` | child-traversal-safe for nested text |
| `core/media-text` | child-traversal-safe for nested text |
| `core/gallery` | deferred image caption/alt admission |

Separately:

| Family | Classification |
|---|---|
| `core/navigation` | deferred / ADR required if admitted |
| `core/query` | unsupported / deferred |
| `core/post-template` | unsupported / deferred |
| synced / reusable patterns | deferred / ADR required if admitted |

**A.4 implementation implication (advisory):** primarily **eligibility / registry policy clarification** and documentation that structural containers are transparent; optional later adapters for citation/summary/pullquote/image — **not** a new identity grammar or renderer.

### 13. ADR decision

**No new ADR required** for the bounded minimum A.4 surface.  
ADR-0013 remains authoritative.  
Shared-definition families remain blocked pending a future ADR if pursued.

### 14. F5 readiness

**F5 may PASS** for the bounded approved surface above.  
A.4 implementation planning may begin after this research is reviewed/merged.  
Completing A40–A48 does **not** itself merge production code.

### Verdict choice

## CONDITIONAL GO

Reasons:

- Existing identity / recursion / render architecture is proven sufficient (**would qualify for fast-track identity claims**).
- Shared / dynamic / reusable families remain explicitly deferred.
- Minimum useful A.4 scope is bounded, document-local, and deterministic.
- Admitted scope introduces **no new architectural contract** → no new ADR; proceed directly to A.4 implementation planning/freeze.

### Containment audit

| Check | Result |
|---|---|
| Artifacts under `research/ar2-nested-gutenberg-identity/` | Yes |
| ZIP exclusion documented | Yes (`ZIP_EXCLUSION.md`) |
| Not registered via `Plugin.php` | Yes |
| No schema / REST / runtime `src/` changes | Yes |
| Removable without affecting plugin | Yes |
| Focused Gutenberg leaf unit tests green | 26/26 PASS |

— **Proven by experiment**

### Evidence-confidence summary

| Area | Dominant confidence |
|---|---|
| Grammar / UUID stability / recursion / structural / list gate / render | Proven by experiment |
| Cross-document Store locality | Supported by evidence (ADR-0013 + same keys across docs) |
| Browser editor UX of wrap/unwrap/duplicate | Inferred as equivalent to tree transforms tested |
| Exact Media Library attachment meta sync edge cases | Remaining assumption (denied by ownership policy anyway) |

---

## Exact next step

1. Review / merge `feature/ar2-nested-gutenberg-identity` to `main`.  
2. Treat F5 as satisfied for the bounded surface.  
3. Create A.4 implementation planning branch and freeze an A.4 implementation plan for the minimum surface only.  
4. Do **not** start another research cycle.  
5. Do **not** admit Navigation / Query / reusable patterns without a dedicated ADR.
