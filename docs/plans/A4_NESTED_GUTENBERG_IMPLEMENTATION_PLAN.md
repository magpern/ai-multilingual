# A.4 — Nested Gutenberg — Implementation Plan

**Status:** **Architecture Frozen** — implementation authorized for the bounded F5-approved surface; coding not started
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — milestone **A.4** (Architecture / Coverage)
**Baseline:** `main` @ `39f41ebdd335725b9e74f534670d101f601728f8` (A.R2 merged; tag `ar2-nested-gutenberg-identity-research-complete`)
**Planning branch:** `feature/a4-nested-gutenberg-implementation-plan`
**Implementation branch:** `feature/a4-nested-gutenberg` (create from updated `main` when coding starts)
**ADR:** [0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) — **Accepted** — **no new ADR** for this bounded surface
**Evidence:** [A4_NESTED_GUTENBERG_IDENTITY_RESEARCH_LOG.md](A4_NESTED_GUTENBERG_IDENTITY_RESEARCH_LOG.md) (**CONDITIONAL GO**; F5 **PASS** for bounded surface); [A4_NESTED_GUTENBERG_IDENTITY_PLAN.md](A4_NESTED_GUTENBERG_IDENTITY_PLAN.md)
**Validation log (reserved):** `docs/plans/A4_NESTED_GUTENBERG_VALIDATION_LOG.md` — create when A4.0 begins on the implementation branch

**Operational success:** Nested supported Gutenberg leaves inside structural / host containers translate under existing `b:<uuid>:<field>` identity, without a new recursion engine, Store redesign, or renderer replacement.

**This plan is the frozen implementation contract for A.4.** After merge, create the implementation branch and begin **A4.0**. Do not open another research cycle.

---

## 1. Purpose

Ship the **bounded** nested Gutenberg production surface proven by A.R2:

- structural containers remain transparent (not translation units);
- supported leaf descendants remain independently addressable;
- existing recursive traversal / extract / render stack is reused;
- eligibility / admission policy is the primary production change;
- deferred families stay deferred.

A.4 is an **admission / eligibility milestone**, not an architecture rewrite.

---

## 2. Preconditions (verified at freeze)

| Precondition | Status |
|---|---|
| A.R2 complete / **CONDITIONAL GO** | **Pass** |
| F5 **PASS** for bounded surface | **Pass** |
| Research merged + tagged `ar2-nested-gutenberg-identity-research-complete` | **Pass** |
| No new ADR required for bounded scope | **Pass** |
| Grammar remains `b:<uuid>:<field>` | **Pass** |
| Child UUID stability without parent/path identity | **Pass** |
| Existing walker / extractor / renderer recurse | **Pass** |
| No `src/` production nested changes yet | **Pass** |
| Navigation / Query / reusable remain deferred | **Pass** |
| Baseline `main` @ `39f41ebdd…` | **Pass** |

If any precondition regresses before coding starts: **STOP**.

---

## 3. Goals

1. Make structural containers transparent to existing child extraction/rendering.
2. Admit nested supported leaves (especially nested `core/list-item` leaves) without path identity.
3. Prevent duplicate extraction (list vs list-item; container vs child).
4. Preserve local failure / source fallback.
5. Keep dynamic / shared families denied.
6. Leave citation / summary / pullquote / gallery caption as future admission.
7. Close with an explicit final supported-surface table.

---

## 4. Frozen architecture (non-negotiable)

Preserve exactly:

```text
b:<uuid>:<field>
```

Freeze:

| Contract | Rule |
|---|---|
| Document-local UUID | Sole identity owner for Gutenberg units |
| Parent UUID | **Not** part of child identity |
| Structural path | Traversal context only — **never** persistent identity |
| Source hash | Freshness only |
| Traversal | Existing `BlockTreeWalker` DFS over `innerBlocks` |
| Extraction | Existing `BlockExtractor` |
| Rendering | Existing `BlockRenderer` |
| Admission | `BlockRegistry` + `AdapterRegistry` only |
| Store | Overlay SoT; no schema bump |
| TM / Review / Glossary / Jobs | Unchanged ownership |
| Second nested registry | **Forbidden** |
| REST redesign | **Forbidden** |
| HTML scraping / fuzzy rematch | **Forbidden** |
| New ADR for this surface | **Not required** |

If implementation begins requiring a new identity layer, new recursion engine, Store redesign, or renderer replacement: **STOP**.

---

## 5. Bounded A.4 production surface

### 5.1 Structural-transparent containers (no own translation unit)

| Block | Role |
|---|---|
| `core/group` | structural-transparent |
| `core/columns` | structural-transparent |
| `core/column` | structural-transparent |
| `core/list` | structural wrapper — **must not** extract list text |

Supported descendants remain traversable.

### 5.2 Child-traversal hosts (no new parent-owned fields in A.4)

| Block | A.4 role |
|---|---|
| `core/quote` | host for supported child paragraphs/headings/…; citation **deferred** |
| `core/details` | host for supported body children; summary **deferred** |
| `core/cover` | host for nested supported text children |
| `core/media-text` | host for nested supported text children |

### 5.3 Nested leaf admission

| Block | A.4 rule |
|---|---|
| `core/list-item` (empty `innerBlocks`) | Supported leaf — including when nested under lists/groups/columns — using existing adapter + `b:<uuid>:content` |
| `core/list-item` (non-empty `innerBlocks`) | **Deferred** — do not invent parent-text extraction in A.4 |
| Other F14 leaves | Remain supported when nested under transparent/host containers |

### 5.4 Explicitly deferred / denied

| Item | Disposition |
|---|---|
| Quote citation | deferred field admission |
| Pullquote body / citation | deferred |
| Details summary | deferred |
| Gallery captions / alt | deferred |
| Media Library metadata | denied / out of scope |
| Parent list-item with own `innerBlocks` text | deferred |
| `core/navigation` | deferred (ADR if later) |
| `core/query` / `core/post-template` | unsupported / deferred |
| Synced / reusable (`core/block`) | deferred (ADR if later) |
| Path / parent identity | **denied** |

Do not partially enable deferred families by accident.

---

## 6. Real implementation gap

A.R2 proved recursion, identity, and rendering already work.

A.4 therefore focuses only on:

1. Eligibility policy refinements
2. Structural-container transparency
3. Nested leaf admission (esp. list-item leaves)
4. Duplicate-extraction prevention
5. Regression-proof render / fallback
6. Bounded diagnostics
7. Browser validation

Do **not** introduce a new recursion engine or identity layer.

---

## 7. Eligibility strategy

### Current problem

`BlockRegistry::is_eligible` and `AbstractBlockAdapter::is_translatable_instance` reject any block with non-empty `innerBlocks`. That is correct for preventing accidental parent extraction, but it also blocks nested **leaf** cases only if the leaf itself has children. Nested leaves with empty `innerBlocks` are already eligible once UUID-injected — A.R2 showed this.

### A.4 required distinction

| Case | Policy |
|---|---|
| Structural parent (`group` / `columns` / `column` / `list`) | Never a translation unit; walker continues to children |
| Host parent (`quote` / `details` / `cover` / `media-text`) | No new parent fields in A.4; walker continues to children |
| Supported leaf with empty `innerBlocks` | Eligible (existing rule) — including when deeply nested |
| Supported type with non-empty `innerBlocks` | **Not** auto-extractable; only extract own fields if a future adapter explicitly admits them (out of A.4 for list-item parents) |
| Dynamic / shared denied names | Skip extract; children only if separately document-local and proven — default **source** for entire dynamic family in A.4 |
| Unsupported container | Transparent for traversal of supported descendants |

### Preferred direction (frozen)

- Do **not** globally delete all `innerBlocks` checks.
- Structural / host parents remain non-extracting.
- Having children does **not** make a parent extractable.
- Having children on an ancestor does **not** make a supported descendant ineligible.
- A block’s own fields extract only when its adapter explicitly supports them **and** the instance remains deterministic.
- Admission remains registry/adapter-driven.

Likely code touchpoints (minimal):

- `src/Block/BlockRegistry.php` — clarify eligibility comments / any true leaf-vs-parent distinction needed for UUID injection
- `src/Block/Adapter/AbstractBlockAdapter.php` — keep non-empty `innerBlocks` ⇒ not translatable for current leaf adapters
- `src/Block/UuidInjector.php` — continue injecting only eligible leaves inside nested trees (already walks recursively)
- Extractor / renderer — prefer **no** structural rewrite; verify behavior with tests

If UUID injection or extract unexpectedly skips nested leaves after policy edits: **STOP** and fix before broadening.

---

## 8. List / list-item hard contract

Freeze:

- `core/list` = structural wrapper; **zero** list translation units
- `core/list-item` keeps its own UUID
- Nested leaf list-items use `b:<uuid>:content` (existing adapter field)
- Reorder / move preserves identity (no array index / path identity)
- Duplicate extraction = **implementation failure**

Required tests:

- flat list
- nested list
- reorder
- move
- duplicate item
- duplicate page
- list inside Group / Column
- source edit → stale

---

## 9. Container transparency model

| Parent | Classification | A.4 extracts parent fields? |
|---|---|---|
| `core/group` | structural-transparent | No |
| `core/columns` | structural-transparent | No |
| `core/column` | structural-transparent | No |
| `core/list` | structural-transparent | No |
| `core/quote` | child-traversal host | No (citation deferred) |
| `core/details` | child-traversal host | No (summary deferred) |
| `core/cover` | child-traversal host | No |
| `core/media-text` | child-traversal host | No |
| `core/pullquote` | future adapter candidate | No — deferred entirely |

Goal: **nested child coverage**, not container-field expansion.

---

## 10. Duplicate-extraction policy

Hard invariant:

> A visitor-facing source value must produce at most one canonical translation unit.

Fail validation on:

- duplicate segment keys
- list + list-item double extract of the same text
- quote markup + child paragraph double extract
- cover/media-text parent + child double extract
- any container extracting the same logical text as a descendant

Required fixtures: list+list-item; quote+paragraph; cover+heading; media-text+paragraph; nested groups/columns; deep nested leaves.

---

## 11. Local failure / fallback

Preserve:

- malformed / unsupported parent does not crash the document
- supported child continues when safe
- unsafe candidate remains source
- one nested failure does not suppress unrelated siblings
- no whole-page failure
- no generic recursive string walker

---

## 12. Dynamic / shared deny policy

Keep denied / deferred in production A.4:

- `core/navigation`
- `core/query`
- `core/post-template`
- synced / reusable shared definitions (`core/block`)
- other `DYNAMIC_BLOCK_NAMES`

Default = **source**.

Even if the walker observes nodes inside these trees, do **not** enable production translation unless A.R2 ownership explicitly permits it (it does not for these families).

---

## 13. Component impact

Prefer minimal changes in existing components:

| Area | Expectation |
|---|---|
| `BlockRegistry` | Eligibility clarity / structural transparency policy |
| `AbstractBlockAdapter` / leaf adapters | Keep leaf-only extract; no parent auto-admission |
| `BlockExtractor` | Verify nested extract; harden duplicate guards if needed |
| `BlockRenderer` | Verify nested overlay; no second renderer |
| `UuidInjector` | Nested leaf UUID persistence remains recursive |
| Diagnostics | Bounded counters only |
| Tests / fixtures / browser acceptance | Primary delivery volume |

Do **not** add a parallel `NestedBlock*` service hierarchy unless clearly necessary. If a new architectural subsystem appears required: **STOP**.

---

## 14. Diagnostics

Add only if needed (no source/target text; no high-cardinality parent-path logging):

| Signal | Meaning |
|---|---|
| `structural_container_seen` | Transparent container encountered |
| `nested_supported_leaf` | Supported leaf extracted under nesting |
| `nested_unsupported_leaf` | Unsupported nested node left as source |
| `duplicate_unit_prevented` | Duplicate key / logical unit blocked |
| `recursion_guard` | Depth / malformed guard tripped |
| `nested_source_fallback` | Nested unit fell back to source |

---

## 15. Performance strategy

A.R2 showed low overhead. A.4 must measure **production** impact:

Fixtures: shallow; deep; many siblings; nested lists; columns; mixed supported/unsupported; source-only; translated.

Record: extraction time; unit count; duplicate count; Store lookup count; render time; max observed depth.

Do **not** invent thresholds. Stop on pathological recursion or N+1 Store behavior.

---

## 16. Platform compatibility

Nested leaf units use existing workflows unchanged:

- Workspace
- Review
- TM
- Glossary
- Jobs
- stale / source hash
- suggestions

Optional parent/container context may be **additive metadata only**. No parent workflow state.

Elementor A.2/A.3 surface must remain unaffected (`e:` grammar; widgets).

---

## 17. Work packages (A4.0–A4.8)

Naming uses **A4.0–A4.8** to avoid colliding with A.R2 research WPs A40–A48.

### A4.0 — Implementation baseline + F5 contract verification

| | |
|---|---|
| **Objective** | Confirm frozen contracts on implementation branch; create validation log; pin baseline |
| **Surface** | Docs / fixtures only at start |
| **Deps** | This plan merged |
| **Likely files** | Validation log; fixture index |
| **Tests** | Existing Gutenberg leaf unit suite green |
| **Browser** | None required |
| **Rollback** | Delete branch |
| **Stop** | Any precondition fail |
| **Commit** | `docs(gutenberg): open A.4 Nested Gutenberg implementation` |

### A4.1 — Eligibility policy / structural transparency

| | |
|---|---|
| **Objective** | Encode structural/host transparency without global innerBlocks deletion |
| **Surface** | group/columns/column/list transparency policy |
| **Deps** | A4.0 |
| **Likely files** | `BlockRegistry.php`, possibly adapter base, UuidInjector comments/guards |
| **Tests** | Eligibility matrix unit tests |
| **Browser** | None yet |
| **Rollback** | Revert commit |
| **Stop** | Policy requires path identity or parent-keyed grammar |
| **Commit** | `feat(gutenberg): clarify nested eligibility for structural containers` |

### A4.2 — Nested list / list-item admission + duplicate protection

| | |
|---|---|
| **Objective** | Guarantee nested leaf list-items extract once; list never extracts |
| **Surface** | list + nested list-item leaves |
| **Deps** | A4.1 |
| **Likely files** | ListItemAdapter (verify only), extractor duplicate guards, tests/fixtures |
| **Tests** | Flat/nested/reorder/move/duplicate/page/list-in-group |
| **Browser** | Nested list EN/SV smoke when ready |
| **Rollback** | Revert |
| **Stop** | Duplicate extraction unavoidable |
| **Commit** | `feat(gutenberg): harden nested list-item admission` |

### A4.3 — General structural container child traversal

| | |
|---|---|
| **Objective** | Prove/ship group/columns/column nested leaf coverage in production path |
| **Surface** | structural containers + F14 leaves |
| **Deps** | A4.1–A4.2 |
| **Likely files** | Tests; minimal registry/extractor if gaps remain |
| **Tests** | Nested group/columns fixtures; reorder columns |
| **Browser** | Group→Paragraph; Columns→Heading |
| **Rollback** | Revert |
| **Stop** | Container becomes a translation unit accidentally |
| **Commit** | `feat(gutenberg): enable structural container child traversal` |

### A4.4 — Quote / details / cover / media-text bounded child traversal

| | |
|---|---|
| **Objective** | Nested supported children inside hosts; no parent field admission |
| **Surface** | quote/details/cover/media-text children only |
| **Deps** | A4.3 |
| **Likely files** | Tests/fixtures; deny parent field extraction |
| **Tests** | Duplicate protection vs children; citation/summary not extracted |
| **Browser** | Quote/Details/Cover/Media-text child cases |
| **Rollback** | Revert |
| **Stop** | Parent citation/summary accidentally extracted as whole-block HTML |
| **Commit** | `feat(gutenberg): support nested children in quote details cover media-text` |

### A4.5 — Diagnostics + regression hardening

| | |
|---|---|
| **Objective** | Bounded nested diagnostics; Elementor + leaf regression locks |
| **Surface** | Diagnostics only |
| **Deps** | A4.2–A4.4 |
| **Likely files** | Logger / metrics aggregator |
| **Tests** | Counter presence; no text leakage |
| **Browser** | Mixed Gutenberg+Elementor page |
| **Rollback** | Revert |
| **Stop** | High-cardinality path logging introduced |
| **Commit** | `feat(gutenberg): add bounded nested diagnostics` |

### A4.6 — Performance / cache / render safety

| | |
|---|---|
| **Objective** | Measure production extract/render; confirm no scrape / no N+1 |
| **Surface** | Measurement harness + docs |
| **Deps** | A4.4 |
| **Likely files** | Validation log; optional research-style script under tests or docs evidence |
| **Tests** | Timing table recorded; deep nesting safe |
| **Browser** | Source-only + translated deep page |
| **Rollback** | N/A docs; code revert if perf bug |
| **Stop** | Pathological recursion / Store N+1 |
| **Commit** | `test(gutenberg): record A.4 nested performance evidence` |

### A4.7 — Full Tier 0 + browser acceptance

| | |
|---|---|
| **Objective** | Unit / integration / PluginGuard / PHPCS / targeted browser PASS; FP=0 |
| **Surface** | Full bounded surface |
| **Deps** | A4.2–A4.6 |
| **Likely files** | Acceptance fixtures; validation log |
| **Tests** | Full green gates |
| **Browser** | Validation matrix §20 |
| **Rollback** | Hold merge |
| **Stop** | FP>0 or leaf/Elementor regression |
| **Commit** | `test(gutenberg): complete A.4 browser acceptance` |

### A4.8 — Closure docs + final supported-surface table

| | |
|---|---|
| **Objective** | Close milestone; final surface table; roadmap pointers |
| **Surface** | Docs only |
| **Deps** | A4.7 PASS |
| **Likely files** | Validation log; roadmap; this plan status → Complete |
| **Tests** | Link check |
| **Browser** | None |
| **Rollback** | N/A |
| **Stop** | Surface table disagrees with code |
| **Commit** | `docs(gutenberg): close A.4 Nested Gutenberg milestone` |

---

## 18. Acceptance criteria (40)

1. `b:<uuid>:<field>` grammar unchanged.
2. UUID ownership / `aimlBlockId` unchanged.
3. No parent UUID in child identity.
4. No structural-path identity.
5. `BlockTreeWalker` remains the recursion engine (not replaced).
6. `core/group` transparent (no unit).
7. `core/columns` transparent.
8. `core/column` transparent.
9. `core/list` wrapper non-extracting.
10. Nested leaf `core/list-item` supported.
11. No list / list-item double extraction.
12. Nested list reorder preserves identity.
13. Nested list duplicate isolation (first-wins / repair).
14. Quote child text works.
15. Details child text works.
16. Cover child text works.
17. Media-text child text works.
18. Unsupported parent does not suppress safe child.
19. Unsafe child remains source.
20. No duplicate logical units across host+child pairs.
21. No schema bump.
22. No Store redesign.
23. No TM redesign.
24. No Review redesign.
25. No Glossary redesign.
26. No Jobs redesign.
27. Navigation deferred.
28. Query / post-template deferred.
29. Reusable / synced deferred.
30. No HTML scraping.
31. No fuzzy rematch.
32. Malformed nesting fails locally / safely.
33. Performance evidence recorded (no invented budgets).
34. Unit suite green.
35. Integration suite green.
36. PluginGuard green.
37. PHPCS green.
38. Targeted browser matrix PASS.
39. Rendered false positives = 0.
40. Existing Gutenberg leaves unaffected; Elementor A.2/A.3 unaffected.

---

## 19. Stop conditions

Stop A.4 if implementation requires:

- new identity grammar
- parent / path identity
- failing child UUID stability
- unpreventable duplicate extraction
- Store schema change
- renderer replacement
- `BlockTreeWalker` replacement
- shared-definition ownership for minimum surface
- existing leaf regression
- Elementor regression
- unsafe malformed nesting

Do not bypass with special-case hacks.

---

## 20. Validation matrix (targeted)

| # | Scenario |
|---|---|
| 1 | Group → Paragraph |
| 2 | Columns → Column → Heading |
| 3 | Nested List → List Item |
| 4 | Nested List Item → nested List (leaf items) |
| 5 | Quote → Paragraph |
| 6 | Details → Paragraph |
| 7 | Cover → Heading |
| 8 | Media Text → Paragraph |
| 9 | Supported child inside unsupported parent |
| 10 | Unsupported child inside structural parent |
| 11 | Deep nesting |
| 12 | Duplicate page |
| 13 | Reorder / move |
| 14 | Source edit → stale |
| 15 | Mixed Gutenberg + Elementor page |
| 16 | Existing A.2/A.3 Elementor fixture regression |
| 17 | EN / SV render |
| 18 | Rendered FP = 0 |

Do not run unrelated historical browser suites unless repository policy requires them.

---

## 21. Fast-track freeze

This plan introduces **no new architectural contract**.

Therefore:

- **Architecture Frozen** in this pass
- Implementation is **authorized** for the bounded F5-approved surface
- No further A.4 research cycle is required

One implementation-oriented refinement is allowed only if coding reveals a concrete ambiguity inside this surface — not a new spike.

---

## 22. Roadmap status (pointers)

After this plan merges:

- A.R2 complete
- F5 PASS for bounded surface
- A.4 implementation plan exists / frozen
- A.4 coding not started until implementation branch opens
- Navigation / shared / dynamic remain deferred

---

## 23. Branch / commit governance

| Stage | Branch |
|---|---|
| Planning (this doc) | `feature/a4-nested-gutenberg-implementation-plan` |
| After planning merge | Create `feature/a4-nested-gutenberg` from updated `main` |
| A4.0 start | Create validation log |
| A4.0–A4.8 | Implementation branch only |
| Closure | Merge + tag per repo convention (e.g. `a4-nested-gutenberg-complete`) |

Do not implement on the planning branch.

---

## 24. Explicitly out of scope

- New ADR
- Navigation / Query / reusable production support
- Pullquote / citation / summary / gallery caption admission
- Media Library translation system
- Elementor changes
- WooCommerce / visitor chrome
- AIML render-cache activation
- External adapter SDK
- Another nested-identity research cycle

---

## 25. Exact next step

1. Review / merge this planning document to `main`.
2. Create `feature/a4-nested-gutenberg` from updated `main`.
3. Begin **A4.0**.
4. Create `docs/plans/A4_NESTED_GUTENBERG_VALIDATION_LOG.md`.
5. Execute A4.1–A4.8 within the frozen surface.
