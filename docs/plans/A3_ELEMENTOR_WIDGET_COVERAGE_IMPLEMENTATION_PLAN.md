# A.3 — Elementor Widget Coverage — Implementation Plan

**Status:** **Implementation Complete** — pending independent review / merge / tag (do not begin A.4)
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — milestone **A.3** (Product / Coverage)
**Baseline:** `main` @ `adb39f7183be695a36793a3a0c293b79848c0263` (A.2 complete; tag `a2-elementor-foundation-complete`)
**Planning branch:** `feature/a3-elementor-widget-coverage-plan`
**Implementation branch:** `feature/a3-elementor-widget-coverage` (create from updated `main` when coding starts)
**ADR:** [0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md) — **Accepted** (immutable; A.3 adds only reserved nested identity)
**Evidence:** [AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md](AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md) (**CONDITIONAL GO**); [DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md); [A2_ELEMENTOR_FOUNDATION_VALIDATION_LOG.md](A2_ELEMENTOR_FOUNDATION_VALIDATION_LOG.md) (**PASS — closed**)
**Validation log:** [A3_ELEMENTOR_WIDGET_COVERAGE_VALIDATION_LOG.md](A3_ELEMENTOR_WIDGET_COVERAGE_VALIDATION_LOG.md) (**PASS — implementation branch**)

**Operational success:** A merchant can translate additional Elementor widget families (starting with Accordion/Toggle when admitted, and Image only if Elementor-owned) on top of the proven A.2 Heading / Text Editor / Button surface, without redesigning identity, Store, cache isolation, or any AIML subsystem.

**This plan is the frozen implementation contract for A.3.** Create the implementation branch and begin **A30**. Do not open another planning milestone.

---

## 1. Purpose

Expand Elementor coverage **incrementally** on the closed A.2 foundation through evidence-gated widget-family admission.

A.3 is an **admission milestone**, not an architecture rewrite. Every newly supported widget/control must earn admission. Deny-by-default and source fallback remain mandatory.

---

## 2. Preconditions (verified at freeze)

| Precondition | Status |
|---|---|
| A.R1 complete / CONDITIONAL GO | **Pass** |
| ADR-0016 **Accepted** | **Pass** |
| A.2 complete, merged, validated, tagged `a2-elementor-foundation-complete` | **Pass** |
| A.2 production surface exactly `heading/title`, `text-editor/editor`, `button/text` | **Pass** |
| Cross-language leakage = 0 (A.2) | **Pass** |
| Rendered false positives = 0 (A.2) | **Pass** |
| Baseline `main` @ `adb39f718…` | **Pass** |
| No A.3 production implementation in `src/` | **Pass** |

If any precondition regresses before coding starts: **STOP**.

---

## 3. Goals

1. Admit only widget families that clear the formal admission gate.
2. Freeze additive nested identity for repeater rows without changing A.2 keys.
3. Evolve the control registry into a dispatch surface for structured extractors/renderers.
4. Keep document-owned scope; do not open shared-definition ownership.
5. Preserve A.2 controls, cache/language isolation, overlay bridge, and platform ownership.
6. Update the permanent deny-list only with evidence-backed graduation records.
7. Close with an explicit final supported-surface table for later Elementor milestones.

---

## 4. Frozen contracts carried from A.2 / ADR-0016

Do not reopen:

- Hybrid D composition and ownership precedence
- A.2 grammar: `e:d:<owner_post_id>:<element_id>:<control_key>`
- Document-owned scope token `d` for A.3 candidates
- Registry as sole allowlist authority
- `ElementorCompatibility` boundary (Elementor `4.2.x` family)
- Local-failure policy (unit fail → source; document continues)
- Store overlays; no second Store; no schema bump
- Frontend path: `elementor/frontend/builder_content_data`
- A.2 cache strategy (Elementor element-cache disabled while overlays on; document cache bust; language-aware unique_id)
- No HTML scrape; no fuzzy rematch; no Candidate B; no `_elementor_data` mutation
- Gutenberg `b:` unchanged
- Store / TM / Glossary / Review / Jobs / renderer platform ownership unchanged
- AIML render-cache remains off
- Responsive variants remain **deferred** (no `r:<variant>` segment in A.3)

---

## 5. Frozen A.3 nested identity contract

ADR-0016 reserved nested identifiers. A.3 **freezes** this additive Store `segment_key` grammar for document-owned nested units:

### Frozen A.3 nested grammar

```text
e:d:<owner_post_id>:<element_id>:<control_key>:<nested_item_id>
```

| Token | Frozen meaning |
|---|---|
| `e` / `d` / `owner_post_id` / `element_id` / `control_key` | Identical to A.2 |
| `nested_item_id` | Elementor repeater item `_id` only |

### Mandatory nested rules

1. `nested_item_id` **must** be the Elementor repeater `_id`.
2. `_id` must satisfy the same safe-token discipline as A.2 element/control tokens (`/^[A-Za-z0-9_-]+$/`).
3. Missing `_id` → unit unsupported → source fallback (do not extract).
4. Empty `_id` → unsupported → source fallback.
5. Duplicate `_id` within the same repeater → **candidate fails admission** until collision semantics are proven safe.
6. **Array index is never persistent identity.**
7. `source_hash` remains freshness only (never identity; never part of the key).
8. Reorder must not alter identity when `_id` values are preserved.
9. Nested-of-nested repeaters remain deferred unless separately proven under a later milestone.
10. A.2 non-nested keys remain forever parsable as five-segment `e:d:…` keys.

### Repeater copy / duplicate safety (Accordion / Toggle)

Admission evidence **must** cover:

- reorder; duplicate repeater row; delete row; insert row
- duplicate widget; duplicate page; copy/paste document; source edit

**Frozen safety rule:** If Elementor duplicates a repeater row while preserving the same `_id`, admission **fails** until collision handling is understood.

- Do **not** manufacture replacement IDs in Elementor persistence.
- Do **not** mutate `_elementor_data`.

---

## 6. Graduation semantics (frozen terminology)

| State | Meaning |
|---|---|
| **Unsupported** | Denied; source fallback |
| **Research** | Being evaluated; not production-enabled |
| **Adapter** | Production support exists through a structured widget-specific adapter/strategy |
| **Directly Supported** | Production support is part of the stable supported surface, **whether or not** an internal adapter/strategy class exists |

**Directly Supported does not mean “no adapter class.”** It means the control is admitted to the production allowlist and covered by acceptance.

Lifecycle remains:

```text
Unsupported → Research → Adapter → Directly Supported
```

---

## 7. Admission model and evidence format

No widget is admitted because it “looks similar” to another.

### Formal admission gate (all required)

1. Deterministic value-level identity
2. Ownership resolved (document-owned for A.3)
3. Extraction safety (read-only)
4. Render-overlay safety (same A.2 bridge)
5. Cache/language isolation
6. Local-failure behavior
7. Sanitization defined
8. Source fallback defined
9. Performance acceptable vs A.2 baseline
10. Browser evidence
11. No regression to A.2 controls

### Canonical admission record (mandatory)

Every candidate family requires one complete record:

| Field | Required |
|---|---|
| Candidate / widget family | yes |
| Prior deny-list state | yes |
| Owning WP | yes |
| Identity model | yes |
| Ownership classification | yes |
| Controls admitted | yes (or “none”) |
| Evidence paths | yes |
| Unit-test result | yes |
| Integration-test result | yes |
| Browser result | yes |
| Cache/language result | yes |
| Performance observation | yes |
| Limitations | yes |
| Final disposition | Directly Supported \| Adapter \| Remains Unsupported |

**No candidate may be admitted without a complete record.**

The **admission matrix** (populated during A30–A38; closed at A38) is the source of truth for A.3 closure.

### Admission matrix template

| Candidate | Prior state | Owning WP | Identity | Ownership | Controls | Evidence | Unit | Integration | Browser | Cache/lang | Perf | Limitations | Disposition |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| *(fill during A30+)* | | | | | | | | | | | | | |

Evidence seeds (A.R1):

- [`research/ar1-elementor-identity/evidence/er1-stability.json`](../../research/ar1-elementor-identity/evidence/er1-stability.json) — repeater `_id` retention
- [`research/ar1-elementor-identity/evidence/er3-taxonomy.json`](../../research/ar1-elementor-identity/evidence/er3-taxonomy.json) — adapter classifications
- [`research/ar1-elementor-identity/evidence/er3-deny-list.json`](../../research/ar1-elementor-identity/evidence/er3-deny-list.json)
- [`research/ar1-elementor-identity/evidence/er0-widget-frequency.json`](../../research/ar1-elementor-identity/evidence/er0-widget-frequency.json)
- Fixtures with accordion `_id` + `tab_title` / `tab_content`

---

## 8. Candidate waves

### Wave 1 — primary A.3 surface

| Family | Expected controls | Notes |
|---|---|---|
| Accordion | `tabs` items → `tab_title`, `tab_content` + `_id` | Nested grammar; Adapter → Directly Supported via admission |
| Toggle | title/content fields + repeater `_id` | Same nested model as Accordion |
| Image | Elementor-owned alt and/or caption only | A34 may conclude admit / subset / remain unsupported |

### Wave 2 — bounded evaluation only (A35)

Evaluate at most **three** additional candidate families from current Elementor inventory/evidence. Suggested pool (not pre-admitted):

- Icon List
- Testimonials
- Tabs / nested tabs
- Counter labels
- Progress labels
- Call-to-action text
- Social labels
- Pricing table text
- Carousel captions
- Elementor form labels (only if Elementor-owned and deterministic)

**Frozen A35 bounds:**

- Candidate list must come from inventory/evidence
- Maximum **3** candidate families in A35
- Repeaters / dynamic ownership / shared templates may be deferred without failing A.3
- **Zero additional A35 admissions is an acceptable PASS**

### Explicitly excluded from A.3

`html`, `shortcode`, dynamic tags (`__dynamic__`), loop-grid query cells, WooCommerce Elementor widgets, Fluent Forms, theme/custom chrome, ambiguous globals, Theme Builder definitions, saved-template ownership, arbitrary third-party widgets, Elementor editor UI translation, AIML render-cache enablement.

---

## 9. Image ownership gate (A34)

A34 must conclude exactly one of:

| Outcome | Meaning |
|---|---|
| **A** | Admit specific Elementor-owned image text controls |
| **B** | Keep Image unsupported — visible text owned by Media Library or dynamic resolution |
| **C** | Admit only a documented subset; leave other image metadata denied |

No generic “image support.”

Every admitted value’s admission record must identify the **actual owning persistence source**. Media Library attachment alt/title/caption and dynamically resolved metadata remain outside A.3.

---

## 10. Registry dispatch contract

[`ElementorControlRegistry`](../../src/Elementor/ElementorControlRegistry.php) remains the **sole production admission surface**.

### Frozen entry metadata

Each registry entry must declare:

- widget type
- control key
- identity / nesting strategy
- extractor strategy
- renderer strategy
- sanitization strategy
- ownership classification
- support state
- compatibility constraints

### Dispatch rule

Extraction and overlay application must **dispatch** on registry strategies.

Structured widget behavior lives behind focused adapters/strategies (e.g. AccordionAdapter, ToggleAdapter, ImageAdapter — names illustrative).

**Do not** spread widget-family branching through generic extraction/render classes.

Adapters must:

- remain Elementor-specific
- use Hybrid D / frozen grammars
- emit units into existing Store/Workspace flow
- not own Store, TM, Glossary, Review, Jobs, or global rendering
- not mutate Elementor data
- fail locally with source fallback

Do not create the general external Adapter SDK (Program E).

---

## 11. Deny-list governance

[DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md) remains authoritative.

Every removal requires:

- prior denial reason recorded
- new admission evidence
- complete admission record
- browser + cache/language PASS
- source fallback still defined

Do not silently remove items. Document every deny-list change in the validation log and admission matrix.

---

## 12. Rendering

Reuse the proven A.2 overlay bridge and `elementor/frontend/builder_content_data`.

Hard rules:

- no second render path
- no HTML post-processing / DOM scraping / final-output replacement
- no Elementor persistence mutation
- request/language isolation
- local failure; unsupported → source

---

## 13. Cache / language admission gate

Preserve the A.2 cache strategy. Do **not** redesign it in A.3 unless the A.2 foundation itself is proven defective.

Every candidate that changes rendered visitor output must independently pass:

- cold EN; cold SV; warm EN; warm SV
- EN → SV → EN; SV → EN → SV
- mixed A.2 + A.3 page
- unsupported source fallback
- anonymous (and authenticated where relevant)

**Frozen requirements:**

```text
cross-language leakage = 0
rendered false positives = 0
```

A candidate that fails remains **Unsupported**.

No AIML render-cache activation.

---

## 14. Candidate-local stop semantics

A candidate admission failure does **not** fail A.3 unless it reveals a defect in:

- the already-closed A.2 foundation, or
- the frozen nested identity contract

### Candidate-local stops (continue A.3)

Ambiguous ownership; missing stable `_id`; unsafe sanitization; unsupported Elementor API; candidate-specific performance; cache leakage isolated to that candidate; Elementor `_id` collision on duplicate row.

**Result:** candidate remains deny-listed / Unsupported; other candidates may continue.

### Whole-milestone stops

Nested identity requires ADR change; shared-definition ownership required for the milestone; Store schema change required; A.2 render/cache architecture must be redesigned; cross-language leakage in the foundation path that cannot be corrected within frozen architecture.

---

## 15. Workspace / Review / TM / Glossary / Jobs

All new units flow through existing platform contracts.

Confirm:

- Workspace lists/edits new units with understandable labels
- Review unchanged
- TM write-back remains approval-gated
- Glossary/suggestions unchanged
- Jobs process units only through existing Store/Workspace contracts
- Stale / source-hash conflict behavior works

No subsystem redesign.

---

## 16. Sanitization / security

Each admitted control declares sanitization (`plain` / `html` / etc.).

Forbidden:

- executable HTML injection via translations
- shortcode execution through translated values
- translating denied HTML/shortcode controls
- Elementor editor/admin UI translation
- secret logging / body logging in diagnostics
- arbitrary JSON mutation of Elementor data

---

## 17. Compatibility

Continue A.2 `ElementorCompatibility` boundary.

- Validate candidates on supported Elementor `4.2.x` family
- Record Pro vs core dependencies
- Fail safe on unknown/unsupported versions
- Keep checks centralized
- Do not claim universal Elementor widget compatibility

---

## 18. Performance

Measure incremental cost vs A.2 baseline. Record extraction time, unit counts, Store batch lookups, cold/warm render, repeater-heavy fixtures, mixed pages, source-only pages.

Do not invent numeric thresholds. Stop admitting a widget if overhead is operationally unreasonable.

---

## 19. Work packages (ordering frozen: A30–A38)

| WP | Objective | Exact surface | Dependencies | Likely files | Tests | Validation | Rollback | Stop conditions | Commit boundary |
|---|---|---|---|---|---|---|---|---|---|
| **A30** | Baseline + inventory + empty admission matrix | Docs + inventory only | A.2 closed | `A3_…_VALIDATION_LOG.md`; matrix stub | Link/docs checks | Preconditions still Pass | Revert docs | Precondition fail | `docs(elementor): open A.3 validation log and admission matrix` |
| **A31** | Nested identity extension | Grammar + parse/build + tests | A30 | `ElementorIdentity*` + tests | Unit identity matrix | A.2 keys unchanged | Revert identity | Index-based identity proposed | `feat(elementor): add nested Hybrid-D identity segment` |
| **A32** | Accordion admission | `tab_title` / `tab_content` + `_id` | A31 | Registry + Accordion adapter + extract/apply dispatch | Unit/integration/browser/cache | Complete admission record | Disable registry entries | Missing/colliding `_id` | `feat(elementor): admit Accordion nested controls` |
| **A33** | Toggle admission | Toggle title/content + `_id` | A31; may parallelize after A32 pattern | Registry + Toggle adapter | Same as A32 | Complete admission record | Disable entries | Same as A32 | `feat(elementor): admit Toggle nested controls` |
| **A34** | Image ownership decision | Elementor-owned alt/caption subset or deny | A30 | Research notes + optional Image adapter | Ownership proofs + optional browser | Outcome A/B/C recorded | Leave unsupported | Ambiguous ownership | `feat(elementor): resolve Image ownership admission` **or** `docs(elementor): deny Image for A.3` |
| **A35** | Bounded extra low-risk wave | ≤3 inventory candidates | A32–A34 pattern | Registry/adapters as needed | Full admission gate | Zero admissions OK | Disable entries | Scope creep | `feat(elementor): admit bounded A35 candidates` **or** `docs(elementor): close A35 with zero admissions` |
| **A36** | Workspace/diagnostics/compatibility | Labels, counters, Pro/core notes | Admitted families | Workspace meta; diagnostics | Workspace smoke; diagnostics privacy | No subsystem redesign | Revert UI/meta | Platform redesign needed | `feat(elementor): consolidate A.3 diagnostics and Workspace labels` |
| **A37** | Cache/language/performance harden | All admitted families | A32–A35 | Validation log; minimal cache code only if foundation defect | Alternating EN/SV matrix | Leakage=0; FP=0 | Disable new flags | Foundation redesign | `test(elementor): harden A.3 cache and performance evidence` |
| **A38** | Full validation + deny-list + closure | Final supported-surface table | All prior | Validation log; deny-list; roadmaps | Tier 0 + targeted browser | Matrix complete; surface table frozen | Revert release docs | Incomplete matrix | `docs(elementor): close A.3 Widget Coverage` |

Do not add work packages. Do not reorder A30–A38.

---

## 20. Final supported-surface closure contract (A38)

A.3 closes only when the validation log contains an explicit **Final supported surface** table listing:

1. A.2 controls retained (unchanged)
2. A.3 newly admitted controls
3. Adapter-backed controls (if any; terminology per §6)
4. Candidates evaluated but denied
5. Known limitations

That table is the baseline for future Elementor coverage milestones.

---

## 21. Acceptance criteria

1. ADR-0016 unchanged.
2. A.2 controls unchanged and regression-free.
3. Nested grammar frozen as `e:d:<owner>:<element>:<control>:<nested_item_id>`.
4. A.2 five-segment keys remain valid.
5. Array-index identity never used.
6. Missing/empty `_id` → source fallback.
7. Duplicate `_id` in same repeater fails candidate admission.
8. Accordion identity stable across reorder when `_id` preserved.
9. Toggle identity stable under the same rules.
10. Image ownership explicitly classified (A/B/C).
11. Deny-list changes evidence-backed with admission records.
12. Registry remains sole allowlist.
13. Extract/apply dispatch on registry strategies.
14. Widget-family logic confined to adapters/strategies.
15. Adapter/local-failure policy enforced.
16. No `_elementor_data` mutation.
17. No HTML scraping.
18. No fuzzy rematch.
19. No Candidate B.
20. No Store schema bump.
21. Store ownership unchanged.
22. Review ownership unchanged.
23. TM approval-gated write-back unchanged.
24. Glossary/suggestions unchanged.
25. Jobs use existing Store/Workspace contracts only.
26. Gutenberg `b:` unchanged.
27. Document-owned scope only in A.3.
28. Theme Builder / templates / globals remain unsupported.
29. Duplicate-page isolation preserved.
30. Repeater reorder stability proven for admitted families.
31. Duplicate-row `_id` collision policy enforced.
32. Stale/source-hash behavior works for nested units.
33. Workspace labels understandable for nested units.
34. Compatibility diagnostics centralized.
35. Unknown/unsupported Elementor version fails safe.
36. Cross-language leakage = 0 for every admitted candidate.
37. Rendered false positives = 0 for every admitted candidate.
38. Mixed A.2+A.3 page correct.
39. Unsupported widgets remain source.
40. A35 ≤3 candidates; zero admissions allowed.
41. Candidate-local stop does not fail whole A.3 unless foundation defect.
42. Performance evidence recorded vs A.2.
43. Unit suite green.
44. Integration suite green.
45. PluginGuard green.
46. PHPCS green.
47. Targeted browser PASS for admitted families.
48. Admission matrix complete at A38.
49. Final supported-surface table published.
50. Responsive variants still deferred.

---

## 22. Stop conditions

### Candidate-local

See §14. Result: remains Unsupported / deny-listed.

### Whole milestone

- Nested identity requires ADR change
- Shared-definition ownership required for A.3 as a whole
- Store schema change required
- A.2 render/cache architecture must be redesigned
- Foundation path cross-language leakage cannot be corrected within frozen architecture

---

## 23. Out of scope

Theme Builder; saved/global templates; loop-grid content; WooCommerce Elementor widgets; Fluent Forms; arbitrary third-party widgets; dynamic tags; HTML; shortcodes; general plugin integration SDK; Elementor editor translation; AIML render-cache activation; visitor-facing WordPress chrome outside Elementor; WooCommerce site-wide coverage; responsive translation units; nested-of-nested repeaters.

---

## 24. Risks

| Risk | Mitigation |
|---|---|
| Elementor `_id` collision on duplicate row | Explicit admission fail; no ID rewriting |
| Image ownership misclassification | A34 A/B/C gate + persistence source in record |
| A35 scope creep | Hard cap of 3 families; zero OK |
| Registry dispatch half-migrated | A31–A32 require dispatch before Accordion ship |
| Cache regression vs A.2 | Per-candidate EN/SV matrix; no strategy redesign unless foundation defect |

---

## 25. Fast-track freeze rule

This plan introduces **no new architectural dependency** beyond the additive nested identity segment already reserved by ADR-0016 / A.2 § reserved extensions.

Therefore:

- Status = **Architecture Frozen**
- Implementation is **authorized**
- **No further A.3 planning/refinement cycle** is required

Create `feature/a3-elementor-widget-coverage` from updated `main` and begin **A30**.

---

## 26. Implementation start checklist

1. Merge this planning branch to `main` (when authorized) or branch implementation from current `main` after planning merge.
2. Create `feature/a3-elementor-widget-coverage`.
3. Open `A3_ELEMENTOR_WIDGET_COVERAGE_VALIDATION_LOG.md` at A30.
4. Do not broaden beyond Wave 1 + bounded A35.
5. Do not begin Theme Builder, Woo Elementor, or Program E SDK work under A.3.
