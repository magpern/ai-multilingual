# A.0 — Gutenberg Leaf Expansion — Implementation Plan

**Status:** **Complete on branch** `feature/a0-gutenberg-leaf-expansion` — ready for independent review / merge; not tagged
**Plan freeze:** Coverage-expansion only; existing `b:<uuid>:<field>` grammar; block-specific adapters; evidence-gated admission waves; no new architectural concepts
**ADR assessment:** **No new ADR required** (see §22)
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — Program A — milestone **A.0** (Product)
**Planning branch:** `feature/a0-gutenberg-leaf-expansion-plan`
**Implementation branch:** `feature/a0-gutenberg-leaf-expansion`
**Validation log:** [A0_GUTENBERG_LEAF_EXPANSION_VALIDATION_LOG.md](A0_GUTENBERG_LEAF_EXPANSION_VALIDATION_LOG.md) (**PASS on branch**)
**Admission matrix:** [A0_ADMISSION_MATRIX.md](A0_ADMISSION_MATRIX.md)
**Baseline (plan authoring):** `main` @ `51d01da1a92b0228364cd1c29a23d1f915d15154`
**Related:** ADR-0001 (overlay); ADR-0007 (hash ≠ identity); ADR-0013 (`b:` Accepted); ADR-0017 (Plugin Integration — coexistence); [A4_NESTED_GUTENBERG_IMPLEMENTATION_PLAN.md](A4_NESTED_GUTENBERG_IMPLEMENTATION_PLAN.md); F14 block expansion

**Operational success:** After A.0, Gutenberg visitor-facing coverage is materially broader while remaining **architecturally identical** to the platform delivered by A.4. Visitors observe only broader translation coverage, not a different translation architecture.

**This plan is the frozen implementation contract for A00–A08.** No further A.0 planning/refinement cycle is required. Do not implement production code on the planning branch.

---

## 1. Milestone definition (coverage expansion only)

A.0 is intentionally a **coverage-expansion** milestone.

It introduces **no new architectural concepts**.

Success is measured by safely admitting additional deterministic visitor-facing Gutenberg blocks/fields into the architecture already proven through **F14** and **A.4**.

A.0 is **not** expected to modify:

- identity
- UUID ownership
- traversal
- rendering architecture
- Store
- Workspace
- Review
- TM
- Glossary
- Jobs
- Elementor
- Plugin Integration API
- schema
- cache architecture

---

## 2. Purpose

Expand first-party Gutenberg visitor-facing leaf/field coverage under the existing Strategy F / A.4 stack:

- document-local UUID ownership via `aimlBlockId`
- segment grammar `b:<uuid>:<field>`
- `BlockRegistry` + `AdapterRegistry` admission
- existing `BlockTreeWalker` / `BlockExtractor` / `BlockRenderer`
- existing Store → Workspace → Review → TM → Glossary → Jobs pipeline

A.0 is **not** nested-identity work (A.4), **not** Elementor (A.2/A.3), **not** Plugin Integration Framework (A.1), and **not** exhaustive Gutenberg coverage.

---

## 3. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| A.1 Plugin Integration Framework complete / tagged `a1-plugin-integration-framework-complete` | **Pass** |
| ADR-0017 **Accepted** | **Pass** |
| A.4 Nested Gutenberg complete / tagged `a4-nested-gutenberg-complete` | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| Gutenberg identity remains `b:<uuid>:<field>` ([`Contract`](../adr/0013-gutenberg-segment-identity.md)) | **Pass** |
| Current supported leaves: `core/paragraph`, `core/heading`, `core/button`, `core/list-item`, `core/preformatted`, `core/verse`, `core/code` (field `content` only) | **Pass** |
| No existing `docs/plans/A0*` implementation plan | **Pass** |
| No A.0 production implementation in `src/` | **Pass** |
| Baseline `main` @ `51d01da1a…` | **Pass** |

If any precondition regresses before coding starts: **STOP**.

---

## 4. Goals

1. Admit additional deterministic Gutenberg visitor-facing fields/blocks without architectural change.
2. Prefer highest-leverage **A.4-deferred** field admissions first (Wave 1).
3. Evidence-gate structured/media candidates (Wave 2); admit none by default.
4. Bound Wave 3; zero Wave 3 admissions is an acceptable PASS.
5. Keep Media Library, Navigation, Query, reusable/shared, and dynamic families deferred/denied.
6. Preserve Elementor `e:` and Integration `p:` coexistence.
7. Close with an explicit final supported-surface table.

---

## 5. Frozen architecture (non-negotiable)

Preserve:

- ADR-0001
- ADR-0007
- ADR-0013
- ADR-0017
- A.4 bounded nested Gutenberg architecture

Identity remains:

```text
b:<uuid>:<field>
```

Hard invariants:

| Contract | Rule |
|---|---|
| Document-local UUID | Sole Gutenberg identity owner |
| New key family | **Forbidden** |
| Parent identity | **Forbidden** |
| Structural-path identity | **Forbidden** |
| Index identity | **Forbidden** |
| Fuzzy identity | **Forbidden** |
| Source hash | Freshness only |
| UUID injector semantics | **Unchanged** |
| Schema TARGET | Remains **6** |
| Traversal | Existing `BlockTreeWalker` |
| Rendering | Existing `BlockRenderer` |
| Store | Existing overlay SoT |
| HTML scraping | **Forbidden** |
| Second translation pipeline | **Forbidden** |

Additive field identifiers (e.g. `citation`, `summary`, `caption`) may extend `Contract::SUPPORTED_FIELDS` **without** changing grammar — still `b:<uuid>:<field>`. A field that cannot be represented using the existing field component alone is a **STOP** for that candidate (and for the milestone if the only path forward would change grammar/injector semantics).

---

## 6. Block-specific adapter contract (frozen)

Each newly admitted Gutenberg block continues to own **exactly one** block-specific adapter.

Adapters remain **explicit and block-specific**.

**Do not introduce:**

- `UniversalLeafAdapter`
- `GenericFieldExtractor`
- metadata reflection
- arbitrary attribute walking
- “translate every string” behavior
- a second registry
- NestedBlockFramework / LeafFramework / UniversalWalker

**Continue using:**

- `BlockRegistry`
- `AdapterRegistry`
- existing `BlockExtractor`
- existing `BlockRenderer`

Registry admission remains authoritative. Leaf-local empty-`innerBlocks` eligibility guards remain unless a candidate’s admission record explicitly and safely justifies a narrower, block-local exception that does **not** reopen UUID injector semantics or parent extraction.

---

## 7. Admission model

Every candidate block/field requires a **canonical admission record** before production coding admits it.

### 7.1 Required record fields

| Field | Content |
|---|---|
| Block name | e.g. `core/quote` |
| Field | e.g. `citation` |
| Ownership | Block-local visitor-facing source ownership proof |
| UUID / identity evidence | Document-local `aimlBlockId` stability; `b:` compatibility |
| Source location | Attribute / rich-text / explicit block-owned storage |
| Extraction strategy | Adapter method; no scrape |
| Render strategy | Existing renderer path |
| Sanitization | Allowed HTML / plaintext rules |
| Workspace behavior | Segment visibility / editability |
| Review behavior | Unchanged review axis |
| TM behavior | Existing TM keying |
| Glossary behavior | Existing glossary consumption |
| Jobs compatibility | Existing job pipeline |
| Fallback behavior | Source on failure |
| Browser evidence | EN/SV smoke expectations |
| Performance observations | Extract/render notes |
| Limitations | Explicit non-claims |
| Final disposition | See §7.2 |

### 7.2 Disposition values

| Disposition | Meaning |
|---|---|
| **Admitted** | Production support required for milestone closure of that candidate |
| **Partially admitted** | Subset of fields only; remainder deferred with record |
| **Deferred** | Evidence failed or insufficient; remains out of production |
| **Unsupported** | Permanently out of A.0 scope (or forever without ADR) |

**No candidate is automatically admitted** merely because it appears in a planned wave.

---

## 8. Baseline production surface (not re-admitted by A.0)

| Block | Field | Notes |
|---|---|---|
| `core/paragraph` | `content` | F14 |
| `core/heading` | `content` | F14 |
| `core/button` | `content` | F14 |
| `core/list-item` | `content` | F14 + A.4 nested leaf |
| `core/preformatted` | `content` | F14 |
| `core/verse` | `content` | F14 |
| `core/code` | `content` | F14 |

A.4 structural-transparent / host behavior remains in force (group/columns/column/list; quote/details/cover/media-text children). A.0 may add **parent-owned fields** on hosts only when admission proves block-local ownership without redesigning host transparency.

---

## 9. Implementation waves

### Wave 1 — Deferred A.4 field admissions

Highest-leverage candidates:

| Candidate | Proposed field(s) | Notes |
|---|---|---|
| `core/quote` | `citation` | Host remains child-traversal; citation is additive field only |
| `core/details` | `summary` | Same pattern |
| `core/pullquote` | body / citation | New **block-specific** adapter; not a structural host rewrite |

Each must prove deterministic block-local ownership and existing `b:` identity compatibility.

No parent/container architecture redesign.

### Wave 2 — Candidate structured / text / media leaves (evidence required)

Evidence-gated candidates:

- `core/table`
- `core/image`
- `core/file`
- `core/audio`
- `core/video`

Only **block-local visitor-facing** values may be admitted (examples: block-local caption; explicit block-owned label).

**Do not** take ownership of WordPress Media Library:

- attachment alt
- attachment title
- attachment caption
- other attachment metadata

`core/table` is **not** promised admission. If table cells require path/index identity or cannot satisfy existing UUID/field semantics: **defer `core/table`**.

Each Wave 2 candidate requires an admission record. Candidates that cannot satisfy UUID ownership without path/index identity remain deferred. **No candidate is guaranteed admission.**

### Wave 3 — Bounded additional low-risk core leaves

Use A01 inventory to identify deterministic candidates.

Potential examples (illustrative only — not commitments):

- social-link labels
- button/container refinements that are true leaves
- CTA-like native core text fields
- other clearly block-local core leaves

**Bound the wave.** Do not turn A.0 into exhaustive Gutenberg coverage.

**Zero Wave 3 admissions is an acceptable PASS outcome.**

---

## 10. Hard defer / deny

Keep deferred or unsupported in A.0:

| Surface | Disposition |
|---|---|
| Navigation (`core/navigation`) | Deferred (ADR if later) |
| Query / `core/post-template` | Unsupported / deferred |
| `core/block` reusable / shared | Deferred (ADR if later) |
| Synced patterns | Deferred |
| Dynamic bindings | Deferred / deny |
| Shared-definition ownership | Denied |
| Media Library ownership | Denied |
| Parent `core/list-item` with ambiguous `innerBlocks` text | Deferred (unchanged from A.4) |
| wp-admin | Out of scope |
| Email / arbitrary output interception | Out of scope |
| Arbitrary dynamic / server-rendered content | Denied |

Do not partially enable deferred families by accident.

---

## 11. Platform compatibility

Newly admitted units must use existing workflows unchanged:

- Workspace
- Review
- TM
- Glossary
- Jobs
- stale / source hash
- suggestions
- diagnostics (bounded additive counters only)

Elementor A.2/A.3 surface must remain unaffected (`e:` grammar; widgets).

A.1 Integration API / `p:` family must remain unaffected.

A.4 nested structural/host behavior must remain unaffected.

---

## 12. Work packages (A00–A08)

### A00 — Baseline and current coverage inventory

| | |
|---|---|
| **Objective** | Confirm frozen contracts on implementation branch; open validation log; inventory current seven leaves + A.4 deferred list |
| **Exact scope** | Docs / fixture index; no production adapter admissions |
| **Dependencies** | This plan merged to `main` |
| **Expected files** | Validation log; fixture index under `tests/` or docs evidence index |
| **Unit tests** | Existing Gutenberg leaf suite green |
| **Integration tests** | Existing path green |
| **Browser** | None required |
| **Rollback** | Delete implementation branch |
| **Stop conditions** | Any precondition fail; TARGET ≠ 6; identity drift |
| **Commit boundary** | `docs(gutenberg): open A.0 Gutenberg Leaf Expansion implementation` |

### A01 — Candidate inventory + admission matrix

| | |
|---|---|
| **Objective** | Produce candidate inventory and admission-record template; classify Wave 1–3 candidates |
| **Exact scope** | Admission matrix docs; no production admissions yet |
| **Dependencies** | A00 |
| **Expected files** | Admission matrix / evidence templates under `docs/plans/` or validation log appendices |
| **Unit tests** | N/A (docs) unless helper fixtures added |
| **Integration tests** | N/A |
| **Browser** | Optional discovery-only screenshots for candidates |
| **Rollback** | Revert docs commit |
| **Stop conditions** | Inventory proposes path/index identity or Media Library ownership as “required” |
| **Commit boundary** | `docs(gutenberg): publish A.0 admission matrix` |

### A02 — Wave 1 field admissions

| | |
|---|---|
| **Objective** | Admit `core/quote` citation and/or `core/details` summary when records PASS |
| **Exact scope** | Additive fields on existing hosts; extend `SUPPORTED_FIELDS` if needed; adapter updates |
| **Dependencies** | A01 |
| **Expected files** | `src/Block/Contract.php`, host-related adapters or new field handlers, `BlockRegistry` allowlists, tests |
| **Unit tests** | Citation/summary extract/render; identity keys; duplicate guards vs child paragraphs |
| **Integration tests** | Store round-trip; Workspace/Review/TM path for new fields |
| **Browser** | Quote/Details EN/SV with citation/summary |
| **Rollback** | Revert commits; leave hosts child-only |
| **Stop conditions** | Field cannot fit `b:<uuid>:<field>`; injector semantics must change; scrapes HTML |
| **Commit boundary** | `feat(gutenberg): admit quote citation and details summary fields` (or split per field) |

### A03 — Structured textual candidates

| | |
|---|---|
| **Objective** | Admit Wave 1 `core/pullquote` and any Wave 2 structured textual candidates that PASS admission (e.g. table **only if** proven) |
| **Exact scope** | New block-specific adapters for admitted blocks only |
| **Dependencies** | A01; preferably A02 for field-extension patterns |
| **Expected files** | New `*Adapter.php`; `BlockRegistry::SUPPORTED_BLOCKS`; tests/fixtures |
| **Unit tests** | Per admitted block extract/render/identity |
| **Integration tests** | Overlay + Store for admitted blocks |
| **Browser** | Admitted structured blocks EN/SV |
| **Rollback** | Revert adapter registration |
| **Stop conditions** | Table (or other) requires path/index identity; generic extractor introduced |
| **Commit boundary** | `feat(gutenberg): admit pullquote adapter` / deferred commits for failed candidates |

### A04 — Block-local media/text candidates

| | |
|---|---|
| **Objective** | Admit Wave 2 media-related **block-local** captions/labels that PASS admission |
| **Exact scope** | Block-local fields only; never Media Library attachment meta |
| **Dependencies** | A01; A03 patterns as needed |
| **Expected files** | Block-specific adapters; registry; tests |
| **Unit tests** | Caption extract from block attributes; deny attachment-meta fixtures |
| **Integration tests** | Overlay for admitted captions |
| **Browser** | Image/file/audio/video block-local caption cases when admitted |
| **Rollback** | Revert adapters |
| **Stop conditions** | Any path reaches attachment meta as identity/source; UUID injector change |
| **Commit boundary** | `feat(gutenberg): admit block-local media captions` (only for admitted set) |

### A05 — Workspace / diagnostics consolidation

| | |
|---|---|
| **Objective** | Bounded Workspace labeling / diagnostics counters for newly admitted families |
| **Exact scope** | Additive UI labels / counters; no workflow redesign |
| **Dependencies** | A02–A04 admissions that shipped |
| **Expected files** | Diagnostics aggregator; Workspace viewmodel labels if needed |
| **Unit tests** | Counter presence; no source/target text in logs |
| **Integration tests** | Workspace lists new segments |
| **Browser** | Workspace smoke for new fields |
| **Rollback** | Revert diagnostics commit |
| **Stop conditions** | High-cardinality path logging; Workspace architecture change |
| **Commit boundary** | `feat(gutenberg): add A.0 diagnostics and workspace labels` |

### A06 — Performance / regression hardening

| | |
|---|---|
| **Objective** | Measure extract/render; lock Gutenberg flat, A.4 nested, Elementor, A.1 regressions |
| **Exact scope** | Measurement + regression tests; no cache architecture change |
| **Dependencies** | A02–A05 |
| **Expected files** | Validation log evidence; regression fixtures |
| **Unit tests** | Regression suites green |
| **Integration tests** | Elementor + Integration fixture unchanged |
| **Browser** | Mixed page smoke |
| **Rollback** | Revert perf-related code if any |
| **Stop conditions** | Pathological extract cost; N+1 Store; cache redesign proposed as required |
| **Commit boundary** | `test(gutenberg): record A.0 performance and regression evidence` |

### A07 — Full Tier 0 + browser acceptance

| | |
|---|---|
| **Objective** | Unit / integration / PluginGuard / PHPCS / browser PASS; FP=0; leakage=0 |
| **Exact scope** | Full admitted A.0 surface |
| **Dependencies** | A02–A06 |
| **Expected files** | Acceptance fixtures; validation log |
| **Unit tests** | Full green gates |
| **Integration tests** | Full green gates |
| **Browser** | Validation matrix §16 |
| **Rollback** | Hold merge |
| **Stop conditions** | FP>0; leakage>0; flat/nested/Elementor/A.1 regression |
| **Commit boundary** | `test(gutenberg): complete A.0 browser acceptance` |

### A08 — Documentation closure + final supported-surface table

| | |
|---|---|
| **Objective** | Close milestone; publish final supported-surface table; roadmap pointers |
| **Exact scope** | Docs only |
| **Dependencies** | A07 PASS |
| **Expected files** | Validation log; this plan status → Complete; roadmap editorial |
| **Unit tests** | Link check |
| **Integration tests** | N/A |
| **Browser** | None |
| **Rollback** | N/A |
| **Stop conditions** | Surface table disagrees with code |
| **Commit boundary** | `docs(gutenberg): close A.0 Gutenberg Leaf Expansion milestone` |

---

## 13. Validation contract

Every admitted family must prove:

- deterministic extraction
- `b:` identity compatibility
- rendering
- overlay
- source fallback
- Workspace
- Review
- TM
- Glossary
- Jobs
- diagnostics
- EN/SV
- rendered **FP = 0**
- language **leakage = 0**
- existing Gutenberg leaves unaffected
- nested A.4 behavior unaffected
- Elementor unaffected
- Integration API unaffected

Candidate-local failure → defer that candidate. It does **not** necessarily fail A.0.

---

## 14. Operational success

After A.0, Gutenberg visitor-facing coverage is materially broader while remaining architecturally identical to the platform delivered by A.4.

All newly admitted blocks use:

- existing `b:` identities
- existing Store
- existing Workspace
- existing Review
- existing TM
- existing Glossary
- existing Jobs
- existing extraction/rendering architecture

A visitor should observe only broader translation coverage, not a different translation architecture.

---

## 15. Stop conditions

### 15.1 Stop a candidate if it requires

- new identity grammar
- parent / path / index identity
- modification of existing UUID injector semantics
- a field that cannot fit the existing `b:<uuid>:<field>` contract
- schema bump
- Store redesign
- `BlockTreeWalker` redesign
- renderer rewrite
- generic HTML scraping
- fuzzy matching
- Media Library ownership
- shared-definition ownership
- Query / Navigation ownership
- arbitrary dynamic translation
- ADR changes
- generalized field extractor / metadata reflection / universal attribute walker

### 15.2 Stop the whole milestone if

Existing frozen Gutenberg architecture itself must change to make progress (injector semantics, grammar, Store, walker, renderer, schema, or adapter philosophy).

Candidate-local failure does **not** necessarily fail A.0.

---

## 16. Browser / acceptance matrix (minimum)

| Case | Expectation |
|---|---|
| Baseline seven leaves | Unchanged EN/SV |
| Nested A.4 group/columns/list | Unchanged |
| Wave 1 admitted fields | Translate; FP=0 |
| Wave 2 admitted only | Translate block-local values; Media Library meta stays source |
| Deferred candidates | Remain source |
| Mixed Gutenberg + Elementor | Elementor unchanged |
| A.1 reference / integration path | Unaffected |
| Source fallback on malformed admitted block | Source; siblings continue |

---

## 17. Acceptance criteria (40)

1. `b:<uuid>:<field>` grammar unchanged.
2. UUID injector semantics unchanged.
3. Schema TARGET remains 6.
4. Store architecture unchanged.
5. `BlockTreeWalker` unchanged as recursion engine.
6. `BlockRenderer` unchanged as render engine.
7. Adapter-per-block contract held (no universal extractor).
8. Explicit admission records exist for every newly supported block/field.
9. Wave 1 outcomes recorded (admitted / deferred per candidate).
10. Wave 2 outcomes recorded; no guaranteed table/media admission.
11. Wave 3 bound; zero admissions acceptable PASS.
12. Media Library ownership excluded.
13. Query / Navigation / reusable / shared excluded.
14. Workspace supports newly admitted segments without redesign.
15. Review axis unchanged for new segments.
16. TM behaves unchanged for new keys.
17. Glossary consumption unchanged.
18. Jobs pipeline unchanged.
19. Diagnostics remain bounded (no text leakage).
20. Performance evidence recorded for admitted surface.
21. Gutenberg flat regression PASS.
22. A.4 nested regression PASS.
23. Elementor regression PASS.
24. A.1 Integration API regression PASS.
25. Unit suite PASS for A.0 scope.
26. Integration suite PASS for A.0 scope.
27. PluginGuard PASS.
28. PHPCS PASS for touched PHP.
29. Browser matrix §16 PASS for admitted surface.
30. Rendered FP = 0 on admitted pages.
31. Language leakage = 0.
32. No HTML scraping introduced.
33. No second translation pipeline.
34. No new identity family.
35. No parent / path / index identity.
36. `core/pullquote` admitted only via dedicated adapter (if admitted).
37. Table deferred if path/index identity required.
38. Final supported-surface table matches code.
39. Roadmap pointers updated at closure.
40. No ADR reopened; no new ADR required for delivered surface.

---

## 18. Out of scope

- Elementor widget expansion
- A.1 merchant / product bridges (WooCommerce, forms, etc.)
- Theme Builder / FSE template ownership models beyond existing denies
- wp-admin / operator UI translation
- Email interception
- Exhaustive Gutenberg core catalog coverage
- Cache architecture changes
- REST/CLI redesign

---

## 19. Likely code touchpoints (implementation phase only)

| Area | Expectation |
|---|---|
| `src/Block/Contract.php` | Additive `SUPPORTED_FIELDS` only if needed |
| `src/Block/BlockRegistry.php` | Register newly admitted blocks |
| `src/Block/Adapter/*` | New or extended **block-specific** adapters |
| `src/Block/AdapterRegistry.php` | Wire adapters |
| `UuidInjector` | **No semantic change**; verify nested/host injection still correct |
| Extractor / renderer | Prefer verify-only; harden duplicate guards if needed |
| Diagnostics / Workspace labels | Bounded additive |
| Tests / fixtures / browser | Primary delivery volume |

Do **not** add a parallel leaf framework. If a new architectural subsystem appears required: **STOP** and report.

---

## 20. Documentation / roadmap (this planning task)

Create this plan. Update editorial pointers only:

- [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md)
- [../ROADMAP.md](../ROADMAP.md)

Record: A.1 complete; A.0 plan exists; A.0 implementation **not** started; A.8 remains later/parallel as already defined. Do not reorder milestones.

---

## 21. Implementation sequencing (after plan merge)

1. Merge this planning branch to `main`.
2. Create `feature/a0-gutenberg-leaf-expansion` from updated `main`.
3. Begin **A00**.
4. Proceed A01→A08 under this contract.
5. Tag on closure only after A08 (tag name decided at closure; not part of this planning task).

---

## 22. Architecture / ADR assessment

**Verdict: No new ADR required**, provided A.0 remains within:

- existing `b:` grammar
- existing UUID ownership / injector semantics
- existing block-specific adapter model
- existing Store / extract / render architecture

A.0 is field/block **admission** under ADR-0013 + A.4 proven stack, not a new identity or integration family.

If implementation discovers a genuinely new architectural contract (new grammar, path identity, Media Library ownership model, universal walker, schema bump): **do not freeze it automatically**. Stop the candidate or milestone and report the blocker for a separate ADR/planning decision.

---

## 23. Fast-track freeze

This completed plan introduces **no new architectural contract**.

Therefore:

- **Status: Architecture Frozen**
- **A.0 implementation is authorized** after this document merges to `main`
- **No further planning/refinement cycle is required**

Do **not** create the implementation branch during plan authoring.

---

## 24. Rollback philosophy

- Per-adapter / per-field commits preferred for surgical revert
- Deferred candidates leave no partial production registration
- Milestone abort restores pre-A.0 supported-surface table without schema rollback (TARGET stays 6)

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/A0_GUTENBERG_LEAF_EXPANSION_IMPLEMENTATION_PLAN.md` |
| Planning branch | `feature/a0-gutenberg-leaf-expansion-plan` |
| Implementation branch | `feature/a0-gutenberg-leaf-expansion` (after merge) |
| Baseline | `main` @ `51d01da1a92b0228364cd1c29a23d1f915d15154` |
