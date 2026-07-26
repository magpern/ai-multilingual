# Strategy A-G implementation log

Running log, updated as each strategy (or sub-strategy) is completed. Read
alongside `spike/s5/oracle/INVARIANTS.md` (Oracle model invariants) and the
accepted plan (`### Strategies` / `### Confidence model` / `### Metrics`
sections) for full context.

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

## Deferred work

- Strategies B through G: not yet implemented. Per instruction, stopping
  after Strategy A for review before proceeding.
- The "un-orphaning" gap confirmed above is a production `Store` behaviour,
  not something any Strategy A-G evaluation is scoped to fix — noted as an
  open item for whichever milestone eventually addresses it.

## Known issues

- None open against the harness or Strategy A itself. Two WRONG PREDICTIONS
  were caught and corrected during test-writing (type conversion's hash
  invisibility; the specific nested-movement case that doesn't reorder) —
  both are properties of the Oracle model correctly reflected by the
  harness, not bugs, and are recorded as findings above rather than "issues".

## Architectural decisions

- Eligibility (which blocks get a segment/key at all) is deliberately
  simplified for this evaluation: leaves only (no container-owned text),
  excluding a fixed list of dynamic block names, excluding empty content via
  a bare `trim()` check (mirroring production `Extractor::extract()`'s own
  simplistic check, not a stricter HTML-aware one). Stated explicitly as a
  simplification in `RealBlockWalker`'s docblock, not a claim about the
  eventual production `BlockRegistry`'s real policy.
- `ReconciliationSimulator` reproduces `Store::sync_source()`'s algorithm
  faithfully (including the un-orphaning gap above) rather than an idealized
  version of it, so a strategy is evaluated against what it would actually be
  wired into.
- No Oracle model change was needed for Strategy A. If a later strategy
  requires one, per instruction: stop, do not redesign, report why the
  existing model is insufficient.

## Open validation items

- Strategies B-G: unevaluated.
- The confidence-model gate artifact (bucket-based `m`/`n` matching, tie
  breaks) is Strategy D/E's concern — not yet built or tested.
- No browser-authored Gutenberg compatibility claim. No editor-save
  verification. No production-readiness claim. All three remain outstanding
  validation gates, exactly as before this phase.
