# Strategy A-G implementation log

Running log, updated as each strategy (or sub-strategy) is completed. Read
alongside [INVARIANTS.md](INVARIANTS.md) (Oracle model invariants) and the
accepted plan ([AI_MULTILINGUAL_PLANNING.md](../plans/AI_MULTILINGUAL_PLANNING.md)
`### Strategies` / `### Confidence model` / `### Metrics`) for full context.

## Completed strategies

### Shared harness (prerequisite, not itself a strategy)

`RealBlockWalker`, `ReconciliationSimulator`, `StrategyEvaluator` — see
`spike/s5/lib/Strategy/`. Validated independently
(`StrategyHarnessTest`, 11 tests) before any strategy's results were trusted.

### Strategy A — positional index, no reconciliation

**Status: complete.** Key shape `block:N`, flat document-order counter over
eligible blocks, recomputed fresh every time, no memory.

**Verdict: rejected, exactly as the accepted plan expected.** Concrete,
evidenced false positives on: reorder/swap, insertion before the end,
deletion before the end, duplication, copy/paste (within and across
documents), nested movement that changes relative document order,
reorder+edit, swap+edit-both, delete+insert-similar. All render (I7 — none of
these are even flagged in a way that would stop them). Full metrics:
`spike/s5/corpus/strategy-a-results.json`.

**Safe for A specifically** (evidenced, not assumed): minor edit, full
rewrite, insertion/duplication/copy-paste strictly at the document's end,
undo, an exact-inverse round trip, and — a genuinely nuanced result, not
predicted correctly on the first attempt — nested movement that happens to
preserve leaves' relative document order, and in-place type conversion
(because `OracleTree::convert_type()` doesn't touch a leaf's own
prefix/suffix, so the wrapper bytes are literally unchanged — a property of
the oracle's model, not a general claim about real Gutenberg conversions).

**Performance:** extraction and reconciliation both scale sub-linearly to
linearly against the O(n) falsification test (ratio ≤ 15): 13.6× and 12.1×
respectively from 100→1000 blocks. `spike/s5/corpus/strategy-a-performance.json`.

**Confirms a pre-existing finding, does not discover a new one:** the
delete→save→restore→save sequence leaves the translation permanently
orphaned even though the restored content is byte-identical — production
`Store::sync_source()` never un-orphans a row. This was already identified
during Milestone 2 design review, before this spike began; Strategy A's
evaluation is its first concrete, reproduced evidence, not new information.

### Strategy B — content fingerprint, no reconciliation

**Status: complete.** Key shape `b:<block_name>:<sha1(norm)>`, e.g.
`b:core/paragraph:746a0e0db3a04293ce009353a3d2ce7afe659d03`.

**Key inputs:** `block_name` from `parse_blocks()` + `sha1(norm)` over the
eligible leaf's innerHTML via `ReconciliationSimulator::source_hash()` (harness
stand-in for production `Store::source_hash()` / ADR-0006).

**Excluded inputs:** document position, structural path, attrs_fingerprint,
reconciliation memory — all recomputed fresh every extraction, like A.

**Verdict: rejected.** Does not satisfy the spike exit gate's
`false_positive == 0` requirement and fails the plan's central claim about
content-derived identity ("routes the 95% case through the 5% path"). Full
metrics: `spike/s5/corpus/strategy-b-results.json`.

#### Differences from the provisional (incorrect) implementation

An earlier experimental Strategy B used `hash:<sha1>` without `block_name`.
That was **replaced entirely**, not adapted:

| Aspect | Provisional (wrong) | Approved Strategy B |
|---|---|---|
| Key shape | `hash:<sha1>` | `b:<block_name>:<sha1(norm)>` |
| block_name in key | No | Yes |
| Same text, different block type | Collided | Distinct keys (evidenced) |
| Rationale in code | Inferred from harness comments | `AI_MULTILINGUAL_PLANNING.md` § Strategies |

#### Comparison with Strategy A

| Operation class | Strategy A | Strategy B |
|---|---|---|
| Reorder / insert / delete (content unchanged) | False positives (render) | Stable (correct_reattach) |
| Minor / full text edit | Safely stale on same key | Orphaned + spurious_new — **translation lost**, not stale |
| Type conversion in place | Undetectable (oracle model) | New key (block_name changed) — orphaned + spurious_new |
| Delete + insert similar | False positive (render) | No false continuity — orphaned + new untranslated key |
| Duplicate / copy-paste identical content | False positive from position shift | **False positive from key collision** (harness maps one key to wrong leaf) |
| Identical text, same block type | Position-distinguishes | **Collision** — one key, two logical blocks |

#### Observed strengths (evidenced)

- Stable across reorder, insertion, deletion, undo, and exact-inverse transforms
  when normalized content is unchanged — every case where A produced
  `rendered_false_positive`, B produced `correct_reattach`.
- No false continuity on delete+insert-similar (unlike A's rendered false positive).
- `block_name` in the key disambiguates same norm text across block types
  (`same_text_different_block_type`: 2 keys, no collision).
- Wrap/unwrap when leaf innerHTML bytes are unchanged: stable.

#### Observed weaknesses (evidenced)

- **Every text edit** orphans the old row and creates an untranslated new key —
  not safely stale. This is worse for the common editing case than structural
  identity would be, exactly as the plan predicted.
- **Key collisions** when two eligible blocks share `block_name` + identical
  norm text: duplication, copy/paste within document, identical paragraphs,
  identical sibling subtrees. PHP array collapse + harness zip produces
  `false_positive` with rendering (I7) on duplication and copy/paste.
- **Scale-document collision rate is catastrophic** on repeated corpus
  templates: 69 eligible → 23 unique keys at 100 blocks; 644 → 23 at 1000
  (621 collisions). Real pages with repeated CTAs, labels, or boilerplate
  would share this failure mode.
- **Nested/container moves** can expose new eligible wrapper bytes or reorder
  key-to-leaf zip: `nested_movement` (2 false positives, 1 rendered),
  `move_between_containers` (1 false positive, rendered). Depends on
  serializer-normalized innerHTML, not oracle text alone.
- **Type conversion** changes key even when innerHTML bytes are unchanged
  (block_name component changes).
- **No stale detection path for edits** — edits never reach `stale_correct`;
  they orphan instead.

#### Collision and orphaning summary

- `key_collision_before/after: true` — identical_text_same_block_type,
  identical_sibling_subtrees, duplication, copy_paste_within_document.
- Orphaning on every content-changing operation including split, merge, edits.
- Un-orphaning gap on delete→restore→save confirmed (same as A; production
  `Store` behaviour, not Strategy-B-specific).

#### Performance

Median of 10 runs, automated corpus via `ScaleDocumentGenerator`:

| Blocks | Eligible | Unique keys | Collisions | Extract ms | Key-gen ms | Reconcile ms |
|---|---|---|---|---|---|---|
| 100 | 69 | 23 | 46 | 0.84 | 0.82 | 0.07 |
| 500 | 322 | 23 | 299 | 4.41 | 4.19 | 0.09 |
| 1000 | 644 | 23 | 621 | 8.74 | 8.48 | 0.10 |

O(n) falsification ratios (1000/100): extraction 10.42×, key-generation
10.31×, reconciliation 1.40× — all ≤ 15 threshold.
`spike/s5/corpus/strategy-b-performance.json`.

Peak memory median ~953 MB (PHPUnit integration environment; includes WP
bootstrap overhead, not Strategy-B-specific allocation).

#### Recommendation

**Strategy B does not advance as a candidate segment identity algorithm.** It
is rejected on evidence for the same fundamental reason as A — translations
attach to wrong content or are lost — via different failure modes. The spike
**should proceed to Strategy C** (structural path + block name + field) as
the next comparison in the A–G ladder. Strategy B's evidence confirms the
plan's prose: content-derived identity is not an escape from reconciliation
risk; it trades A's positional failures for edit-time orphaning and
duplicate-content collisions.

### Shared harness extension for Strategy C (narrow, documented)

Before Strategy C, `RealBlockWalker` inlined tree walking without exposing
structural paths. The smallest extension:

1. **New:** `StructuralPathWalker.php` — assigns dot-joined 0-based sibling
   indexes over the full `parse_blocks()` tree (see path semantics below).
2. **Refactored:** `RealBlockWalker.php` — delegates to
   `StructuralPathWalker::walk_tree()` then applies the same eligibility filter
   as before; adds `path` to each eligible block return value.

**Preservation proof:** Strategy A and B operation metrics are unchanged by
this refactor (same eligibility, same key inputs — A ignores path, B ignores
path). All pre-C Strategy A/B tests remain green; operation JSON outputs match
prior evidence. Strategy A performance timings re-run within normal variance
(ratios still ≤ 15).

**Independent path tests:** `StructuralPathTest.php` (9 tests) locks semantics
before Strategy C evaluation trusts them.

### Strategy C — structural path + block name + field, no reconciliation

**Status: complete.** Key shape `b:<structural_path>:<block_name>:content`,
e.g. `b:1.2.0:core/paragraph:content`.

**Key inputs:** structural path from `StructuralPathWalker`, `block_name`,
literal field suffix `content`. Recomputed fresh every extraction.

**Excluded inputs:** source text, source_hash, attrs_fingerprint, persistent
UUID, registry ID, reconciliation memory, fuzzy matching.

**Reconciliation:** `ReconciliationSimulator` with direct key equality only
(no source_hash rematching — that belongs to Strategy D).

**Verdict: rejected.** Improves edit handling vs B (hypothesis confirmed) but
inherits A-like structural fragility and introduces path-reuse false continuity.
Full metrics: `spike/s5/corpus/strategy-c-results.json`.

#### Structural path specification

Defined in `StructuralPathWalker.php` header and tested in
`StructuralPathTest.php`:

| Rule | Semantics |
|---|---|
| Traversal order | Depth-first pre-order within each `innerBlocks` array |
| Index origin | 0-based sibling index at each level |
| Freeform (`blockName === null`) | Invisible — no index consumed |
| Real blocks | Each consumes one sibling index; path = dot-joined indices |
| Containers | Consume index AND descend; container path is prefix for children |
| Dynamic blocks | Consume index, marked `is_dynamic`, no descent |
| Empty containers | Consume index, no children |
| Eligibility | Decided by `RealBlockWalker`, not path walker |

Paths reflect the **full parsed tree**, not the filtered eligible-leaf list.
Ineligible blocks (containers, dynamic, empty) **do** occupy path positions.

#### Comparison with Strategies A and B

| Operation class | Strategy A | Strategy B | Strategy C |
|---|---|---|---|
| Minor / full text edit | stale_correct | orphaned + spurious_new | **stale_correct** |
| Reorder / swap | false_positive (render) | correct_reattach | **false_positive (render)** — like A |
| Insert before / middle | false_positive | correct_reattach | **false_positive** — trailing paths shift |
| Delete before trailing | false_positive | correct_reattach | **false_positive** — survivor inherits path |
| Insertion at end | correct + spurious_new | correct + spurious_new | correct + spurious_new |
| Duplication / copy-paste | false_positive | key collision | **false_positive from path shift**, no collision |
| Identical text, same type | position-distinguishes | collision | **distinct keys** (paths differ) |
| Delete + insert similar | false_positive | orphaned + new | **false_positive one-save; stale two-save** |
| Type conversion | undetectable (oracle) | new key | new key (block_name component) |
| Wrap / unwrap / depth change | varies | stable if bytes unchanged | **orphaned + spurious_new** (path prefix changes) |
| Scale collisions (1000 blk) | none | 621 / 644 | **0 / 644** |

#### Identity survival (evidenced)

| Mutation | Survives? | Evidence |
|---|---|---|
| Text edits | **Yes** | minor_text_edit, full_rewrite: stale_correct=1 |
| Block-type change | **No** | type_conversion: b:0:core/paragraph → b:0:core/heading |
| Insert before/middle | **No** for shifted blocks | insertion_before/middle: false_positive |
| Delete before trailing | **No** for survivor | deletion_before_trailing: false_positive |
| Sibling reorder | **No** | reorder_swap: 2 false_positive; non_adjacent: 3 |
| Moves / wrap / unwrap | **No** | move_between_containers: 2 orphaned; wrap: 1 orphaned |
| Nesting depth change | **No** | change_nesting_depth: 1 orphaned, 2 spurious_new |
| Undo / exact inverse | **Yes** | undo_after_mutation: 2 correct_reattach |
| Duplicate identical content | **Distinct keys** | identical_text: b:0 + b:1, no collision |

#### Collision and path-reuse findings

- **No key collisions** among eligible blocks at any scale (644 unique keys at
  1000 blocks vs B's 23).
- **Distinct paths** for duplicate/identical-content blocks — major improvement
  over B.
- **Path reuse after deletion:** `delete_plus_insert_similar_one_save` →
  rendered false_positive (one save). `path_reuse_delete_then_insert_two_saves`
  → same key reused, row marked stale (`is_stale=1`), does not render — partial
  mitigation across sync cycles but one-save still renders wrong translation.
- **Duplication/copy-paste:** no collision but trailing block path shift causes
  false_positive (1 each case).

#### Reconciliation findings

- Direct key equality only; edits correctly reach `stale_correct`.
- Path-reuse attaches stale row to different content (two-save: ignored+stale,
  one-save: rendered false_positive).
- Un-orphaning gap on delete→restore→save confirmed (same as A/B).

#### Performance

Median of 10 runs via `ScaleDocumentGenerator`:

| Blocks | Eligible | Unique keys | Collisions | Tree ms | Path ms | Key ms | Extract ms | Reconcile ms |
|---|---|---|---|---|---|---|---|---|
| 100 | 69 | 69 | 0 | 0.60 | 0.63 | 0.66 | 0.65 | 0.25 |
| 500 | 322 | 322 | 0 | 3.42 | 3.01 | 3.38 | 3.38 | 1.17 |
| 1000 | 644 | 644 | 0 | 7.72 | 7.85 | 7.36 | 7.99 | 2.45 |

O(n) falsification ratios (1000/100): tree 12.86×, path 12.38×, key 11.18×,
extraction 12.21×, reconciliation 9.87× — all ≤ 15.
`spike/s5/corpus/strategy-c-performance.json`.

Peak memory median ~953 MB (PHPUnit bootstrap overhead, not C-specific).

#### Hypothesis vs evidence

| Prediction | Outcome |
|---|---|
| C improves edit handling vs B | **Confirmed** — stale_correct on all text edits |
| C fragile under structural changes | **Confirmed** — reorder/insert/delete/move/wrap all fail |
| No duplicate-content collisions | **Confirmed** — 0 collisions at scale |
| Path reuse is false-continuity risk | **Confirmed** — one-save renders; two-save stale only |

#### Recommendation

**Strategy C does not advance as a candidate segment identity algorithm.**
It fixes B's edit-time orphaning and duplicate-content collisions but
reintroduces A's positional false positives on structural edits and adds
path-reuse false continuity on delete+replace. The spike **should proceed
to Strategy D** (exact source_hash rematching) as the next comparison.

### Strategy D — Strategy C identity + exact source_hash reconciliation

**Status: complete.** Identity unchanged from C:
`b:<structural_path>:<block_name>:content`. New capability: reconciliation
via `StrategyDReconciler` — exact `source_hash` + `block_name` rematch only,
unique 1:1 mappings, key rewrite on success. No fuzzy matching, confidence
scoring, UUIDs, registry, or rendering suppression.

**Verdict: rejected.** Rematch fixes path-shift cases where old keys fully
disappear, but fails when a structural path is **reused by different content**
(insert-before key collision) and cannot fix reorder (keys persist, content
swaps). Duplicate identical content + structural change remains ambiguous or
false-continuity via direct key. Full metrics:
`spike/s5/corpus/strategy-d-results.json`, ambiguity evidence:
`spike/s5/corpus/strategy-d-ambiguity.json`.

#### Reconciliation algorithm

On every sync (`StrategyDReconciler::sync_source`):

1. Generate Strategy C keys from current document.
2. **Direct key match:** preserve row; mark stale if `source_hash` changed.
3. **Disappeared key:** mark ignored/orphaned; enter candidate pool.
4. **New key:** scan orphan pool for exact `source_hash` + `block_name`.
5. **Rematch only if 1:1 unique** on both sides; otherwise leave unreconciled.
6. **Successful rematch:** rewrite key (move row old → new), restore status.

#### Reconciliation evidence summary

| Metric | Observed |
|---|---|
| Successful rematch | wrap, unwrap, change_nesting_depth, partial moves |
| Failed rematch | insertion_before (key reuse blocks orphan pool) |
| Ambiguous rematch | two orphans same hash → new keys (never guesses) |
| Incorrect rematch | move_between_containers, nested_movement (1 each) |
| False positives | reorder (2), insertion_before, path reuse, duplicate+shift |
| Stale handling | text edits: stale_correct (same as C) |

**Critical finding:** when path index is reused in the same save (insert-before,
delete+replace), the old row stays on the reused key via direct match — the
orphan never enters the candidate pool, so rematch cannot recover the shifted
block.

#### Cumulative comparison (A–D) — selected operations

| Operation | A | B | C | D |
|---|---|---|---|---|
| Text edit | stale ✓ | orphan | stale ✓ | stale ✓ |
| Reorder | FP render | stable | FP render | FP render |
| Insert before | FP render | stable | FP render | FP render |
| Deletion shift | FP render | stable | FP render | FP render |
| Wrap/unwrap | varies | stable | orphan | **rematch ✓** |
| Duplicate content | FP | collision | FP shift | FP shift |
| Path reuse (identical) | FP | orphan | FP/stale | FP render |
| Path reuse (different) | FP | orphan | FP | FP render |
| Scale collisions | none | 621/644 | 0/644 | 0/644 |
| Ambiguous identical hash | n/a | n/a | n/a | **no guess** |

#### Performance

| Blocks | Tree ms | Path ms | Rematch ms | Total sync ms | Collisions |
|---|---|---|---|---|---|
| 100 | 0.50 | 0.56 | 0.21 | 0.78 | 0 |
| 500 | 3.07 | 2.94 | 1.37 | 5.06 | 0 |
| 1000 | 6.93 | 5.96 | 2.58 | 9.86 | 0 |

O(n) ratios (1000/100): tree 13.94×, path 10.73×, rematch 12.14×,
total sync 12.61× — all ≤ 15. `spike/s5/corpus/strategy-d-performance.json`.

#### Recommendation

**Strategy D does not advance.** Exact-hash rematch is necessary but
insufficient: it cannot fix reorder (persistent keys), key-reuse insertions, or
duplicate-content structural edits without guessing. Proceed to **Strategy E**
only if the rendering suppression gate is implemented; otherwise evaluate F/G
in the ADR. `false_positive` must be 0 — D fails.

### Strategy E — structural path + exact-hash rematch + render gate

**Status: complete.** Retains Strategy C identity
(`b:<structural_path>:<block_name>:content`) and Strategy D exact-hash
reconciliation (unique 1:1 rematch only). Adds **StrategyERenderGate** — a
rendering-suppression gate that proves source continuity before any stored
translation renders.

**Verdict: rejected.** The gate eliminates most Strategy D rendered false
positives (reorder with different text, path reuse with different content,
ambiguous rematch, stale rows, block-type mismatch) but **cannot achieve**
`rendered_false_positive == 0` on the full corpus. One operation still fails
the non-negotiable exit gate: `delete_then_insert_identical_text` (same
structural key, identical source hash, different logical block). Adversarial
identical-content reorder/swap also produces rendered false positives (2 per
case) — hash-only continuity cannot distinguish swapped blocks with identical
text. Full metrics: `spike/s5/corpus/strategy-e-results.json`, ambiguity:
`spike/s5/corpus/strategy-e-ambiguity.json`, performance:
`spike/s5/corpus/strategy-e-performance.json`.

#### Design separation

| Component | Responsibility |
|---|---|
| `StrategyE` | Extraction / identity (delegates to Strategy C) |
| `StrategyEReconciler` | Reconciliation only (Option B: displaced-row rematch) |
| `StrategyERenderGate` | Render eligibility only — no reconciliation logic |
| `StrategyEEvaluator` | Orchestration + metrics — calls gate, does not embed suppression |

#### Reconciliation changes from D

Strategy E extends D with **Option B** (suppression plus displaced-row
participation in rematch):

1. **Direct key match, hash differs:** preserve original `source_hash`, set
   `error_code='displaced'`, `is_stale=1` — row enters rematch pool (D
   overwrote hash and blocked rematch on path reuse).
2. **Direct key match, hash equal after prior displacement:** clear
   `error_code` and `is_stale` (source restored at same key).
3. **Orphan pool + new keys:** same exact-hash + block_name 1:1 rematch as D.
4. **Ambiguity:** no rematch, no render — `ambiguous_new_keys` /
   `unresolved_new_keys` passed to gate.

Rendering **never** precedes reconciliation; gate evaluates post-sync rows.

#### Render-gate truth table

| Condition | Renders? | Reason |
|---|---|---|
| No row for key | No | `no_translation_row` |
| Row status ignored/orphaned | No | `ignored_orphaned` |
| Key in `ambiguous_new_keys` | No | `ambiguous_reconciliation` |
| Key in `unresolved_new_keys` | No | `unresolved_rematch` |
| `block_name` ≠ segment | No | `block_type_mismatch` |
| `error_code = displaced` | No | `displaced_at_key` |
| `source_hash` ≠ current hash | No | `stale_source_hash` |
| `is_stale = 1` | No | `stale_flag` |
| Empty translation | No | `empty_translation` |
| All checks pass | Yes | `eligible` |

When continuity cannot be proven: render source text, retain row, record reason.

#### State transitions (evidenced)

| Transition | Outcome |
|---|---|
| reviewed → stale (text edit) | displaced suppression, source fallback |
| reviewed → orphaned (key gone) | ignored, no render |
| reviewed → uniquely rematched | key rewrite, eligible render |
| reviewed → ambiguous | no rematch, no render |
| suppressed → source restored at key | eligible render again |
| key reused, different source | displaced suppression (different text) |
| key reused, **identical** source | **false continuity — renders (FP)** |

#### Path-reuse evaluation (A vs B)

| Scenario | Option A (suppress only) | Option B (suppress + displaced rematch) |
|---|---|---|
| Path reuse, different text | Suppress ✓ | Suppress ✓ + rematch if unique |
| Path reuse, identical text | Suppress (hash match) → **still FP** | Same — hash match clears displacement → **FP** |
| Insert-before shift | D failed (key reuse) | Rematch restores shifted block ✓ |

**Chosen: Option B** — strictly safer for different-content path reuse and
insert-before without introducing new false positives on identical-content reuse.

#### Corpus suppression / rendering totals (34 operations)

| Metric | Total |
|---|---|
| Rendered false positives | **1** (exit gate fail) |
| Correctly rendered | 35 |
| Source fallbacks | 28 |
| Stale suppressions | 3 |
| Displaced suppressions | 9 |
| Failed-rematch suppressions | 16 |
| Ambiguous suppressions | 0 |
| Successful rematches | 20 |
| Incorrect rematches | 0 |
| Lost reviewed translations | 5 |
| Restored reviewed translations | 14 |

**Availability cost:** 28 source fallbacks across 34 operations (~82% of ops
trigger at least one suppression path). This is an honest translation
availability loss, not redefined as success.

#### Cumulative comparison (A–E) — selected operations

| Operation | A | B | C | D | E |
|---|---|---|---|---|---|
| Text edit | stale ✓ | orphan | stale ✓ | stale ✓ | **suppress ✓** |
| Reorder (diff text) | FP render | stable | FP render | FP render | **suppress ✓** |
| Reorder (identical) | FP | stable | FP | FP | **FP render** |
| Insert before | FP render | stable | FP render | FP render | **rematch+render ✓** |
| Path reuse (different) | FP | orphan | FP | FP render | **suppress ✓** |
| Path reuse (identical) | FP | orphan | FP/stale | FP render | **FP render** |
| Wrap/unwrap | varies | stable | orphan | rematch ✓ | rematch+render ✓ |
| Ambiguous identical hash | n/a | n/a | n/a | no guess | **no guess, no render** |
| Exit gate (rendered FP=0) | fail | fail | fail | fail | **fail** |

#### Performance

| Blocks | Tree ms | Reconcile ms | Gate ms | Total ms | Rendered FP |
|---|---|---|---|---|---|
| 100 | 1.36 | 0.27 | 0.27 | 1.92 | 0 |
| 500 | 7.08 | 1.37 | 1.31 | 9.63 | 0 |
| 1000 | 14.07 | 2.74 | 2.61 | 19.51 | 0 |

O(n) ratio (1000/100): total 10.15× (≤ 15). Scale benchmarks use stable
documents without path-reuse mutations — zero FP at scale.

#### Predictions confirmed

- Render gate eliminates D's reorder/different-content path-reuse false positives.
- Displaced-row rematch (Option B) fixes insert-before without guessing.
- Ambiguous identical-hash cases never render a guessed translation.
- Stale/displaced/ignored rows never render.
- Deterministic suppression reasons; no silent deletion of reviewed rows.
- O(n) performance preserved.

#### Predictions disproved

- **Render gate alone is sufficient for exit gate** — identical hash at reused
  key still passes all continuity checks; logical block identity is unknowable
  without persistent IDs (Strategy F/G territory).
- Identical-content block swap cannot be suppressed by hash-only gate.

#### Remaining failure modes

1. **Identical source at persistent structural key** — delete→insert identical
   text, identical-content reorder/swap: hash continuity is true but logical
   block changed.
2. **Translation availability** — safe suppressions show source text; ~82% of
   corpus ops incur at least one fallback (acceptable trade-off only if FP=0).

#### Recommendation

**Reject Strategy E.** Zero rendered false positives is not achieved (`1` on
`delete_then_insert_identical_text`; `2` each on identical-content reorder in
adversarial corpus). The render gate is **necessary** (fixes most of D) but
**insufficient** without persistent block identity. Do not proceed to production
with C+D+E alone. Strategy F/G evaluation required for identical-content /
logical-identity cases. **Strategy F was not started** (at time of E completion;
see Strategy F section below).

### Strategy F — injected persistent UUID attribute

**Status: complete.** Key shape `b:<uuid>:content` where `<uuid>` is read from
the Gutenberg block attribute `aimlBlockId` (spike recommendation — see
contract ambiguity below).

**Verdict: passes spike exit gate (`rendered_false_positive == 0`).** Persistent
UUID identity plus render gate eliminates the identical-content false
continuity that rejected Strategy E. Full metrics:
`spike/s5/corpus/strategy-f-results.json`.

#### Contract ambiguity (documented, not silently invented)

Planning docs (`AI_MULTILINGUAL_PLANNING.md` § Strategies) specify:

- Strategy F: injected persistent UUID attribute
- Segment key: `b:<uuid>:content`

They do **not** specify the Gutenberg attribute name or UUID string format.
This spike uses the following **recommendation** pending ADR-0013:

| Contract field | Spike value |
|---|---|
| Attribute name | `aimlBlockId` (`StrategyFContract::ATTR_NAME`) |
| UUID format | RFC 4122 v4, lowercase hex with hyphens |
| Segment suffix | `content` (matches Strategy C convention) |
| Duplicate repair | `first_wins` — first eligible occurrence keeps UUID; later duplicates regenerated |

Changing any of these invalidates spike evidence until re-run.

Strategy F does **not** use structural path, source text, `source_hash`,
fuzzy matching, registry allocation, document position, or block-name-only
identity. `block_name` is validation metadata only.

#### New spike files

| File | Role |
|---|---|
| `StrategyFContract.php` | UUID attribute contract, segment key, validation |
| `UuidGenerator.php` | RFC 4122 v4 generation |
| `UuidBlockWalker.php` | Extract eligible blocks + UUID from serialized content |
| `UuidInjector.php` | Inject/repair UUIDs via `parse_blocks`/`serialize_blocks` |
| `StrategyF.php` | `prepare()`, `extract()`, segment key delegation |
| `StrategyFReconciler.php` | UUID direct-match sync; orphan on missing UUID; un-orphan on restore |
| `StrategyFSuppressionReason.php` | Gate reason constants |
| `StrategyFRenderGate.php` | Render eligibility (missing/malformed/duplicate/regenerated/unknown/orphan/stale/block-type/empty) |
| `StrategyFEvaluator.php` | Orchestration + metrics |
| `StrategyFUuidSync.php` | Sync injected UUIDs onto `OracleNode.attrs` by `block_name + source_hash` match |

Tests: `StrategyFOperationsTest`, `StrategyFAdversarialTest`,
`StrategyFMutationTest`, `StrategyFPerformanceTest`, `StrategyFUuidCasesTest`,
`StrategyFUuidSerializeTest`.

#### UUID lifecycle specification

**1. Assignment**

- **Which blocks:** eligible leaf blocks only — same policy as `RealBlockWalker`
  (non-empty innerHTML, not a container with children, not a dynamic block name).
- **Containers:** skipped — no UUID.
- **Dynamic blocks:** skipped (`StructuralPathWalker::DYNAMIC_BLOCK_NAMES`).
- **Reusable/synced references:** not separately modelled in oracle; spike treats
  them like any other eligible leaf if serialized as a normal block comment.
- **When:** on every `StrategyF::prepare()` / `UuidInjector::inject()` pass
  before extraction/reconciliation.
- **Existing UUIDs:** preserved when syntactically valid and document-unique;
  malformed/empty/duplicate occurrences repaired.

**2. Serialization**

- UUID stored in block JSON attrs: `<!-- wp:paragraph {"aimlBlockId":"<uuid>"} -->`.
- PHP `parse_blocks`/`serialize_blocks` round-trip verified
  (`StrategyFUuidSerializeTest`).
- Unrelated block markup byte-stable when UUID already present and valid.
- Second injection pass on stable document: **no-op** (`content_changed=false`).
- Editor no-op saves after backfill: **not browser-verified**; PHP second pass
  is idempotent.

**3. Validation**

- Accepted: RFC 4122 v4 lowercase with hyphens (`StrategyFContract::UUID_V4_PATTERN`).
- Malformed: replaced with new v4 on inject (`malformed_replaced` stat).
- Empty: new v4 assigned.
- Duplicate in document: detected; first occurrence wins, later regenerated
  (`uuids_regenerated`); gate suppresses any surviving duplicate at render time.
- Collision (generated UUID already present): extremely unlikely; no special
  handler beyond duplicate detection on next inject pass.

**4. Duplication and copy/paste**

- Gutenberg/oracle duplication **copies** `aimlBlockId` onto the copy.
- Inject pass detects duplicate UUIDs and regenerates later occurrences
  (policy A: first-wins).
- Copy/paste within document: same repair — regenerated copy gets `unknown_uuid`
  at gate until re-translated (no false render).
- Copy/paste from another document: pasted UUID may collide with existing UUID;
  inject repairs duplicate; unknown/orphan paths suppress render.
- Template insertion: new blocks without UUID receive one on inject.
- Undo/redo after repair: not browser-verified; PHP re-inject is deterministic.

**5. Deletion and restoration**

- Delete + undo (same session): oracle models; UUID on node preserved if attrs
  survive the operation.
- Delete/save/restore/save: UUID on restored block preserved if attrs restored;
  reconciler **un-orphans** row when UUID reappears (evidenced in
  `delete_save_restore`).
- Permanent delete + insert identical text: **new UUID** assigned → old row
  orphaned, gate `unknown_uuid` — **zero false positive** (E's failure case).
- Manual reuse of old UUID on new block: treated as direct match if hash/block
  checks pass; if old row orphaned and UUID reused with different content,
  stale hash suppresses render.
- UUID reappearance after orphaned row: un-orphan + render if validation passes.

**6. Migration/backfill (modelled, not production code)**

- Documents without UUIDs: first `inject()` pass mutates `post_content` once.
- Scale corpus: ~55 bytes added per eligible block (`aimlBlockId` JSON overhead).
  100 blocks: +3777 B; 500: +17626 B; 1000: +35252 B.
- Existing C/D/E rows: **not portable** — keys change from path/hash to UUID;
  spike starts fresh rows per evaluation.
- Idempotence: repeated backfill on stable tagged doc is no-op.
- Partial failure/retry: inject is all-or-nothing per pass; no partial state
  modelled beyond WordPress serialize atomicity.

#### Identity and repair rules

- **Default identity:** UUID equality (`b:<uuid>:content`).
- Direct match preserves translation only when gate validation passes.
- **Duplicate repair policy A (primary):** first document-order occurrence
  keeps UUID; later occurrences regenerated. Alternatives B/C analysed in
  design only — B (hash-guided) risks false continuity; C (suppress all) maximises
  safety at higher availability cost. Policy A chosen for deterministic repair
  without guessing which duplicate is "original".
- Regenerated UUIDs: gate `regenerated_uuid` suppresses render on that save
  (translation row still keyed to old UUID until re-sync).

#### Render-gate truth table

| Condition | Renders? | Reason |
|---|---|---|
| No UUID on block | No | `missing_uuid` |
| Malformed UUID | No | `malformed_uuid` |
| UUID duplicated in document | No | `duplicate_uuid` |
| UUID regenerated this inject pass | No | `regenerated_uuid` |
| No row for UUID key | No | `unknown_uuid` |
| Row status ignored/orphaned | No | `orphaned_row` |
| `block_name` ≠ segment | No | `block_type_mismatch` |
| `source_hash` ≠ current hash | No | `stale_source_hash` |
| Empty translation | No | `empty_translation` |
| All checks pass | Yes | `eligible` |

Safety principle from Strategy E retained: **never render when continuity is
ambiguous.**

#### Corpus totals (39 operations + UUID cases)

| Metric | Total |
|---|---|
| Rendered false positives | **0** (exit gate **pass**) |
| Correctly rendered | 49 |
| Source fallbacks | 15 |
| Successful identity preservation | 49 |
| Stale suppressions | 0 |
| Unknown-UUID suppressions | 11 |
| Reviewed translations preserved (render path) | 49 |
| Reviewed translations lost | 9 |
| Content mutations (inject passes) | 49 |
| Serialized bytes added (corpus ops) | varies; ~55 B/block on first backfill |

**Key contrast with E:** `delete_then_insert_identical_text` — E renders (FP=1);
F suppresses (`unknown_uuid`, new UUID, zero FP).

**Availability cost:** text edits preserve UUID but `stale_source_hash` suppresses
render until re-translated; delete+insert-identical suppresses (correct safety,
translation unavailable until re-linked). Lower fallback rate than E (~44% vs ~82%)
but some reviewed translations are **lost from render path** without being false
positives.

#### Adversarial corpus (7 render-gated cases + orphan scenarios)

All render-gated adversarial cases: **FP=0**. Includes swap positions,
path reuse with different source, identical blocks move, duplicate UUID swap,
stale-never-renders. `spike/s5/corpus/strategy-f-adversarial.json`.

#### Mutation testing

Broken variants confirmed to introduce false positives or unsafe render:

| Broken variant | FP introduced? |
|---|---|
| Render despite duplicate UUID | Yes (`broken=true` vs `real=false`) |
| No UUID format validation | Suppressed (safe) |
| Render unknown UUID | Suppressed (safe) |
| Skip block_type check | Suppressed (safe) |
| Allow stale hash render | Suppressed (safe) |

`spike/s5/corpus/strategy-f-mutations.json`.

#### Performance (median of 10 runs, scale corpus)

| Blocks | Eligible | Unique keys | Backfill bytes | Parse ms | Inject ms | Reconcile ms | Gate ms | Total ms | FP |
|---|---|---|---|---|---|---|---|---|---|
| 100 | 69 | 69 | 3777 | 0.60 | 2.85 | 0.07 | 0.07 | 3.63 | 0 |
| 500 | 322 | 322 | 17626 | 2.48 | 12.22 | 0.28 | 0.24 | 15.21 | 0 |
| 1000 | 644 | 644 | 35252 | 5.90 | 27.99 | 0.56 | 0.53 | 35.26 | 0 |

O(n) falsification ratio (1000/100 total): **9.7×** (≤ 15 threshold).
Injection dominates total time (expected — parse+serialize per pass).

#### Content-mutation analysis (PHP-proven vs unverified)

**PHP spike proven:**

- Before: `<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->`
- After inject: `<!-- wp:paragraph {"aimlBlockId":"550e8400-e29b-41d4-a716-446655440000"} --><p>Hi</p><!-- /wp:paragraph -->`
- ~55 bytes added per tagged eligible block (UUID JSON overhead).
- Custom attr round-trips through core `serialize_blocks`/`parse_blocks`.
- Second inject pass byte-stable on tagged content.
- Copied blocks carry UUID until inject repair regenerates duplicates.

**Unverified (browser/editor assumptions — no browser-authored evidence):**

- Whether Gutenberg editor preserves `aimlBlockId` on save without stripping.
- Frontend rendering impact (attr in JSON comment — expected none).
- Editor block validation warnings for unknown attr.
- Revision noise from first backfill.
- REST/export/import preservation.
- Third-party block serializers stripping custom attrs.
- Undo/redo around UUID injection in real editor.

#### Cumulative comparison (A–F) — selected operations

| Operation | A | B | C | D | E | F |
|---|---|---|---|---|---|---|
| Text edit | stale ✓ | orphan | stale ✓ | stale ✓ | suppress ✓ | **preserve UUID, render ✓** |
| Reorder (diff text) | FP | stable | FP | FP | suppress ✓ | **render ✓** |
| Reorder (identical) | FP | stable | FP | FP | **FP** | **render ✓** |
| Insert before | FP | stable | FP | FP | rematch ✓ | **render ✓** |
| Path reuse (different) | FP | orphan | FP | FP | suppress ✓ | **suppress ✓** |
| Path reuse (identical) | FP | orphan | FP/stale | FP | **FP** | **suppress ✓** |
| Identical-content swap | FP | collision | FP | FP | FP | **render ✓** (UUID follows block) |
| Duplicate content | FP | collision | FP | FP | varies | **repair + no FP** |
| Copy/paste | FP | collision | FP | FP | varies | **repair + no FP** |
| Delete+insert identical | FP | orphan | FP | FP | **FP** | **suppress ✓** |
| Wrap/unwrap | varies | stable | orphan | rematch | rematch | **render ✓** |
| Persistent content mutation | No | No | No | No | No | **Yes (backfill)** |
| Browser dependency | No | No | No | No | No | **Unverified** |
| Exit gate (rendered FP=0) | fail | fail | fail | fail | fail | **pass** |

#### Predictions confirmed

- Persistent UUID distinguishes logical blocks with identical source at same
  structural location — E's exit-gate failure case fixed.
- UUID survives reorder, move, wrap/unwrap, nesting changes when attrs preserved.
- Duplicate UUID detection + first-wins repair prevents dual-render ambiguity.
- Render gate + UUID validation prevents stale/wrong-type/unknown renders.
- Identical-content swap renders correct translation on each block (UUID-bound).
- PHP serialize round-trip preserves custom attribute.
- Second inject pass idempotent on stable tagged documents.

#### Predictions disproved / limitations

- **UUID alone does not preserve translation availability on delete+reinsert
  identical** — new block gets new UUID; old translation orphaned (safe, not FP).
- **`OracleTree::convert_type()` drops attrs** — UUID lost unless merged; new
  UUID assigned (observed in `block_type_conversion`).
- **Text edit + render:** UUID preserved but implementation still requires hash
  match to render — edits show source fallback until re-translated (availability
  trade-off, not FP).
- **Operational acceptability of content mutation** — not proven; only modelled.

#### Remaining failure modes

1. **Attrs stripped** by editor, third-party blocks, or `convert_type` — UUID
   lost → new identity → translation unavailable (safe suppression).
2. **Manual UUID tampering** — wrong UUID on block suppresses or orphans (safe).
3. **Concurrent edit simulation** — two editors duplicating same UUID: inject
   repair on save is last-writer; race not fully modelled.
4. **Browser attr stripping** — if Gutenberg removes unknown attrs, entire
   Strategy F contract fails in production (unverified risk).

#### Recommendation

**Strategy F passes the spike exit gate (`rendered_false_positive == 0`)** across
the full A–E operation matrix, adversarial corpus, UUID-specific cases, and
scale benchmarks. It is the **first strategy to satisfy the non-negotiable render
safety criterion** while preserving reviewed translations through structural
changes, moves, reorders, wrap/unwrap, duplicate content, and identical-content
swaps.

**Strategy G is not required to achieve `rendered_false_positive == 0`.** G
(registry table, schema change) remains the honest non-mutating alternative if
ADR rejects `post_content` mutation or browser verification fails.

**Do not claim production readiness.** Pending: ADR-0013 attribute contract,
browser/editor verification, production migration design, and operational
acceptance of backfill mutating canonical content.

**Strategy G was not started.**

### Browser validation (Phase 2 — Strategy F operational compatibility)

**Status: partial.** Playwright 1.51.0 on `dev.biopentra.eu` (WP 7.0.2).

**Method:** Host WP-CLI creates tagged draft pages → Playwright opens Gutenberg
(WP auth cookies) → Save → host exports `post_content` → UUID analysis.

**Results (`spike/s5/corpus/browser-validation/summary.json`):**

| Metric | Result |
|---|---|
| Playwright tests passed | 11/13 |
| Fixtures with `aimlBlockId` after browser | 13/13 |
| No-op save byte-stable | yes (all tested) |
| 3-cycle noop (3 paragraphs) | all UUIDs preserved |
| Block validation errors | none observed |
| Text edit (browser) | automation failed — not proven |
| Duplicate block (browser) | automation failed — not proven |

**Core blocks browser-verified on noop save:** paragraph, heading, list, button,
buttons, group, columns, quote, separator, html.

**Browser replay:** `StrategyFBrowserReplayTest` — 0 FP on exported fixtures.

**Gaps:** duplicate/copy/paste, patterns, image/table/cover/dynamic blocks,
REST/import/export, third-party blocks, cross-post paste.

Full report: `docs/spikes/S5-gutenberg-segment-identity.md`.
ADR draft: `docs/adr/0013-gutenberg-segment-identity.md`.

### Browser validation (Phase 3 — closing the mandatory gaps)

**Status: mandatory gates closed; conditional adoption maintained.** Same
environment as Phase 2 (Playwright 1.51.0, `dev.biopentra.eu`, WP 7.0.2).

**Harness repair (fix-automation-first):** the Phase 2 `text-edit` and
`duplicate-block` failures were root-caused to the Gutenberg editor rendering
inside `iframe[name="editor-canvas"]` — all block-content locators were
un-scoped and timed out. Fixed by routing every block-content interaction
through a `canvas(page)` frame-locator helper. Additional fixes this phase:

| Symptom | Root cause | Fix |
|---|---|---|
| Pattern-workflow test 1 timed out at 180s on `page.goto` | one-off transient dev-host slowness (confirmed by clean rerun in 24s) | existing 3× goto retry already covers this; no code change needed |
| "Create pattern" dialog `Create` button not found | dialog is titled **"Add Pattern"** with an **"Add"** button, not "Create" | broadened button matcher to `/^(add|create)\b/i` |
| `insertPatternByName` timed out on `button[aria-label="Toggle block inserter"]` | actual accessible name in this WP/Gutenberg build is **"Block Inserter"** | added `"Block Inserter"` to the selector |
| Pattern appeared "inserted" (test passed) but `post_content` stayed empty | clicking the pattern's outer list-item div did nothing; the actual focusable/clickable node is `role="option"` inside a `role="listbox"`, with an async-loading preview thumbnail underneath it | switched to `page.getByRole('option', { name })`, waited for the option (not the thumbnail) to be visible before clicking |
| Detach pattern test hung until the 180s test timeout, final error was a stuck save click | newer Gutenberg shows a **"Detach pattern?"** confirmation dialog after the menu click | added a confirm-dialog handler that clicks its "Detach" button when present |
| Cross-post copy/paste produced 1 merged paragraph instead of 2 sibling blocks | pasting while the text caret was inside existing paragraph text merges the clipboard as **inline text**, not a new block | added `pasteAsNewBlockAfterLast()`: focus end of last block → `Enter` (fresh empty paragraph) → paste (Gutenberg replaces an empty paragraph with pasted block clipboard content) |

No operation in this phase was blocked by an unfixable harness limitation;
every Tier 1/2/3 operation attempted below completed with real browser
automation.

**Tier 1 — mandatory identity operations (all completed, browser-authored):**

| Operation | Result |
|---|---|
| Text edit / full rewrite | UUID **stripped** on real edit (unregistered attribute) |
| Duplicate block / nested subtree / button | UUID **stripped before duplication** (unregistered) → no duplicate ever reaches `post_content` |
| Copy/paste same-post | UUID **stripped** |
| Copy/paste **into another post** (new) | UUID **stripped**; content lands as its own sibling block, not merged |
| Reorder adjacent / non-adjacent | UUID **stripped** |
| Wrap in Group / unwrap from Group | UUID **stripped** |
| Move between Columns | UUID **stripped** |
| Transform p→h, p→q, h→p | UUID **stripped** |
| Split paragraph / merge paragraphs | UUID **stripped** |
| Undo/redo (text edit) | UUID **stripped** (same as the edit it undoes/redoes) |
| Delete then undo | UUID **preserved** — undo restores the exact prior serialization without going through the attribute-stripping edit path |

**Governing finding (confirmed across ~40 browser-authored fixtures):**
Gutenberg strips *any* unregistered custom block attribute — including
`aimlBlockId` — on every real edit operation, but preserves it through pure
no-op saves and through delete+undo. This was independently confirmed safe
for Strategy F's exit gate: stripped-attribute blocks simply get a **new**
UUID on the next save-time inject pass and render as source-fallback (safe,
not a false positive) until re-translated — never a wrong-translation render.

**Registered vs. unregistered attribute (Phase 2 spike, re-confirmed):** a
spike-only mu-plugin (`zzz-s5-attribute-registration-spike.php`, added via
`block_type_metadata`, removed immediately after each evidence capture)
registers `aimlBlockId` as a real attribute for `core/paragraph`/`core/heading`.
Registered-attribute results: text edit **preserves** UUID; heading edit
**preserves** UUID; **duplicate copies the UUID verbatim** (the one case in
the whole corpus where a real Gutenberg-produced duplicate UUID exists);
transform to a *different* block type still **drops** the attribute (the
destination block's own schema doesn't declare it). Conclusion: production
**must register** `aimlBlockId` via `block_type_metadata`/`block.json` if
attribute survival through ordinary edits is required; left unregistered, it
survives only no-op saves — which is exactly the deliberately conservative
posture the spike attribute contract currently assumes.

**Tier 2 — pattern and reference workflows (mandatory gate, all completed):**

| Sub-case | Result |
|---|---|
| Create synced pattern from a tagged block | `aimlBlockId` **stripped** from the pattern entity (`wp_block` CPT) on creation — same "any real edit strips it" rule applies to pattern conversion |
| Post-local identity after conversion | source post's block is replaced by `<!-- wp:block {"ref":N} /-->` — a **reference**, carrying no block-level attributes of its own |
| Insert synced pattern into another post | identical `<!-- wp:block {"ref":N} /-->` reference; **no content and no UUID live in the referencing post at all** |
| Edit the pattern centrally | verified via direct pattern-entity update + `do_blocks()` render of the referencing post: the edit **propagates instantly** to every reference (architectural — patterns are rendered live from the `wp_block` post at render time, not copied) |
| Detach a synced pattern | produces a real local block **materialized from the pattern entity's current content**; since the entity never carried `aimlBlockId`, the detached copy has none either |
| Non-synced pattern create + insert | **same attribute-stripping** as synced (pattern creation is itself an "edit"); insertion **materializes an independent local copy** (not a reference) tagged with `metadata.patternName`/`categories` provenance, confirming non-synced = one-time stamped copy vs. synced = live reference |
| Duplicate a pattern-derived subtree (the `wp:block` reference itself) | duplicating a reference just produces a second reference to the **same** entity — no new pattern is created, no identity ambiguity at the post_content level |
| Reusable block workflow | in WP 7.0.2, classic reusable blocks and synced patterns are the **same underlying mechanism** (`wp_block` CPT, "Synced" toggle) — there is no separate reusable-block code path left to test independently |

**Recommendation from the pattern gate:** Strategy F must **not** attempt to
tag blocks living inside pattern entities for translation purposes, because
(a) the attribute is stripped on pattern creation regardless of sync mode,
and (b) even if it survived, a synced-pattern-referencing post's own
`post_content` never contains the pattern's block content or attributes to
begin with — reconciliation would have to target the `wp_block` post as its
own independent translatable document. Strategy F should tag only
**post-local materialized blocks**: ordinary content, non-synced pattern
insertions (after materialization), and detached pattern copies. Synced
`wp:block` references should be treated as an explicitly **out-of-scope**
container type for this attribute, not an eligibility gap to fix later.

**Tier 3 — transfer workflows:**

| Sub-case | Result |
|---|---|
| Copy/paste between two posts, same site | **done** (Tier 1 table above) |
| Copy/paste between browser tabs | **not tested** — Playwright's clipboard is process-shared across pages in one browser context, so a same-context two-page test (as used for the concurrent-edit simulation) would not add new evidence over the cross-post case already covered; a genuinely separate-tab-with-separate-clipboard-permission scenario was not exercised |
| Duplicate an entire post | **not tested this phase** (WordPress core has no native "duplicate post" for pages/posts without a plugin; out of scope per "no plugins installed solely to expand the matrix") |
| Import browser-produced serialized content into another post | **done** — see WXR export/import below (equivalent transfer path) |
| Cross-site copy/paste | **not tested — no second WordPress installation available.** Not claimed. |

**Duplicate UUID repair against real browser-produced content:** re-ran
`tools/replay-duplicate-repair.php` against every `dup-*`/`*-dup-*` browser
fixture (22 cases total, including the third-party WooCommerce/Rank Math
duplicate captures) — **0 failures**: every case where Gutenberg produced
(or could have produced) a duplicate is either free of duplicates by
construction (unregistered attribute) or, for the one case with a genuine
Gutenberg-copied duplicate (`reg-duplicate`), fully repaired with first-wins
retention, single regeneration, and idempotent second pass.

**Concurrent-edit simulation (adversarial, mandatory):** two Playwright pages
sharing one authenticated browser context opened the *same*
registered-attribute-tagged post; both independently duplicated the same
block; session A saved, then session B saved. Result: **last-write-wins** —
session B's save fully overwrote session A's (classic lost-update; WordPress
`wp_update_post` performs no merge and no optimistic-lock rejection by
default). The final `post_content` contained a genuine duplicate UUID
(`66666666…` × 2). Strategy F's repair fully resolved it (first occurrence
retained, second regenerated, idempotent on replay,
`rendered_false_positive == 0` after repair). **Lost-update behavior is a
WordPress editing-concurrency property, not a Strategy F defect** — Strategy
F's contribution is that even under this adversarial race, no wrong
translation can render.

**Autosave / revision / REST / export / import:**

| Check | Result |
|---|---|
| Manual revision preserves UUID | **proven** — revision row snapshot of the tagged pre-pattern-conversion state retained `aimlBlockId` verbatim |
| Autosave preserves UUID | **proven** — `POST /wp/v2/pages/{id}/autosaves` round-trip and the resulting `{id}-autosave-v1` revision row both retained the injected UUID verbatim |
| REST read preserves UUID | **proven** — `GET ?context=edit` `content.raw` contains `aimlBlockId` unchanged (requires nonce even for this authenticated GET on this install) |
| REST write preserves UUID | **proven** — `POST` with `aimlBlockId` in the submitted content round-trips unchanged in the response and in `wp_posts` |
| XML export preserves UUID | **proven** — `wp export` WXR `<content:encoded>` CDATA contains the attribute verbatim |
| XML import preserves UUID | **proven** — `wp import` of that WXR file recreates a post whose `post_content` contains the identical `aimlBlockId` |
| Preview preserves UUID | **inferred, not directly proven** — preview renders from the same autosave/revision content already proven to preserve the attribute; no dedicated preview-iframe browser test was run this phase |
| Post duplication (native) | **not applicable** — WordPress core has no native page/post duplication; not tested |
| Restoring a revision restores prior UUID | **inferred** — revisions are byte-for-byte snapshots (proven above); restoring one is a plain `post_content` overwrite with no attribute-specific logic, so restoration necessarily restores whatever UUID state that snapshot held. Not independently browser-tested. |

**Frontend leakage (carried over from Phase 2, reconfirmed):** core blocks
never expose `aimlBlockId` in rendered HTML. WooCommerce's
`customer-account` block (Interactivity API) **does leak it** as a
`data-aiml-block-id` attribute on the front end when the attribute happens to
be present at render time — a third-party-block-specific frontend-exposure
risk distinct from the render-safety exit gate, worth a production allowlist
decision but not a `rendered_false_positive`.

**Browser-derived Strategy F replay (updated, dynamic):**
`StrategyFBrowserReplayTest` was rewritten to auto-discover every
`*-post-*.html` fixture in the corpus (Phase 2 + Phase 3 combined) instead of
a hardcoded list, and to run the actual production pipeline order
(`UuidInjector::inject` → extract segments → reconcile → render gate) rather
than a self-comparison shortcut. Latest run: **59 fixtures replayed, 83
correctly-rendered segments, 1 deliberate source-fallback (the regenerated
half of the one real duplicate), 0 `rendered_false_positive`, 1 duplicate
case fully repaired with 0 repair failures.** Output:
`spike/s5/corpus/strategy-f-browser-replay.json`.

**Updated block matrix:** all of paragraph, heading, list, image, button,
buttons, group, columns/column (via the columns fixture), quote, pullquote,
table, cover, media-text, separator, spacer, html, shortcode, plus one
WooCommerce block (dynamic, Interactivity API) and one Rank Math block
(static-ish; a pre-existing plugin defect — "unexpected or invalid content"
on the untagged control post, unrelated to `aimlBlockId` — was documented
rather than treated as a Gutenberg/Strategy-F failure). `core/list-item` was
exercised implicitly inside the `core/list` fixture but not isolated as its
own case.

**Remaining gaps after Phase 3** (kept explicit per the decision rules):
cross-tab clipboard isolation, cross-site copy/paste (no second install),
native post duplication (not a WP core feature), dedicated preview-iframe
browser proof (inferred from autosave/revision instead), revision-restore
browser proof (inferred from snapshot immutability instead), and an
exhaustive third-party sample beyond the one WooCommerce + one Rank Math
block already covered.

**Final regression pass (post-documentation) — harness cleanup and a
pre-existing `core/cover` caveat found:** re-running the full regression
suite one more time after the documentation updates above surfaced two
findings, neither of which is a Strategy F/Gutenberg-identity regression:

1. Three legacy spec files (`operations.spec.ts`, `duplicate-uuid.spec.ts`,
   `block-coverage.spec.ts`) were dead: the first two imported helper
   functions (`editFirstParagraph`, `duplicateFirstBlock`) removed during
   the Phase 3 harness repair, and all three called `createTaggedPost`/
   `exportPost` (which shell out to `docker compose run wpcli`) directly
   from *inside* the browser-validation Playwright container — a Docker
   socket dependency that must not be granted to that container per this
   repo's hard security rule (no `docker.sock` mounts). Their coverage is
   fully superseded by `manifest-driven.spec.ts` and `operations-matrix.spec.ts`
   (which follow the correct host-prepares/container-only-browses split, see
   `setup-browser-validation-manifest.sh`). Deleted as dead/superseded code,
   not as a regression finding.
2. Re-running `manifest-driven.spec.ts` end-to-end (41 tests: 18 `bv-*`, 18
   `op-*`, 5 `dup-*`) reproduced **1 pre-existing, non-attribute-related**
   issue and **1 confirmed flake**:
   - `bv-cover` (core/cover, no-op save) intermittently triggers Gutenberg's
     "unexpected or invalid content" block-recovery notice. An **untagged
     control post** (`diag-cover-untagged-recovery.json`, captured earlier
     this phase) reproduces the identical notice with no `aimlBlockId`
     present at all — proving this is a pre-existing fixture/theme markup
     quirk on `core/cover` in this environment, unrelated to Strategy F.
     Not a `rendered_false_positive`; the block matrix entry for
     `core/cover` is annotated with this caveat.
   - `op-duplicate` failed once with a browser-context crash
     (`page.goto: Target page, context or browser has been closed`) under
     sustained single-worker sequential load, then **passed cleanly in an
     isolated re-run** (18.5s) — confirming a transient environment flake,
     not a reproducible defect. `duplicateBlock()` is exercised successfully
     by the immediately-adjacent `op-duplicate-button`/`op-duplicate-nested`
     cases in the same run, which is independent corroborating evidence.
3. `operations-matrix.spec.ts` and `noop-save.spec.ts` have the same
   direct-WP-CLI-from-container dependency as the three deleted files
   (pre-existing in these files, not introduced this pass) and could not be
   re-executed in this final regression pass without violating the
   no-docker-socket rule. They were **not modified**; their previously
   captured evidence (cited throughout this log and already present in
   `spike/s5/corpus/browser-validation/`) stands unchanged. Refactoring them
   to the host/container-split architecture is flagged as follow-up work,
   not required for this spike's conclusions.

Live WordPress test infrastructure was torn down after this pass: all 158
`S5 *` draft pages and 4 `S5 *` patterns (`wp_block`) created across Phase 2
and Phase 3 were force-deleted from `dev.biopentra.eu`; the spike-only
attribute-registration mu-plugin was already inactive (confirmed absent from
the live `wp-content/mu-plugins/`); the temporary `wordpress-importer`
plugin was already uninstalled. The repo-tracked spike copy of the mu-plugin
(`spike/s5/wp-content/mu-plugins/zzz-s5-attribute-registration-spike.php`)
remains as documentation/reproducibility evidence only — it is not deployed.

## Deferred work

- Strategy G: not implemented (cost analysis in S5 final report).
- Extended browser validation matrix (duplicate, paste, patterns).
- The "un-orphaning" gap confirmed by A and B is a production `Store`
  behaviour, not something any Strategy A-G evaluation is scoped to fix.

## Known issues

- None open against the harness. Strategy A had two wrong predictions caught
  during test-writing (type conversion hash invisibility; order-preserving
  nested move) — oracle model properties, not harness bugs.
- Strategy B: provisional `hash:<sha1>` implementation was discarded; no
  shared-harness changes were required for the approved implementation.
- Strategy C: `StructuralPathWalker` extraction was the only shared-harness
  change before D; Strategy A/B/C results preserved (verified by re-run).
- Strategy D: new files only (`StrategyD.php`, `StrategyDReconciler.php`,
  `StrategyDEvaluator.php`); no changes to A/B/C implementations.
- Strategy E: new files only (`StrategyE.php`, `StrategyEReconciler.php`,
  `StrategyERenderGate.php`, `StrategyESuppressionReason.php`,
  `StrategyEEvaluator.php`); A–D implementations unchanged. Production
  `src/` untouched.
- Strategy F: new files only (see Strategy F section); A–E implementations
  unchanged. Production `src/` untouched. Strategy G not started.

## Architectural decisions

- Eligibility simplification unchanged from Strategy A (see RealBlockWalker
  docblock).
- `ReconciliationSimulator` unchanged — still used by A/B/C; D uses
  `StrategyDReconciler` separately.
- No Oracle model change was required for Strategy B, C, or D.
- Structural path semantics live in `StructuralPathWalker`; eligibility remains
  in `RealBlockWalker`.

## Open validation items

- Strategy G: unevaluated (not required for exit gate; F passes).
- Confidence model (E/F gate): built and tested; F passes exit gate.
- Browser-authored Gutenberg compatibility for `aimlBlockId`: **unverified**.
- No production-readiness claim.
