# OracleNode / OracleTree invariants

Every invariant the Oracle model relies on, reviewed and classified as either
**[GUTENBERG]** — reflects real WordPress `parse_blocks()`/`serialize_blocks()`
behaviour, confirmed against core, not invented — or **[MODEL]** — a decision
this spike's Oracle makes for its own tracking purposes, with no claim that it
matches (or needs to match) real Gutenberg or WordPress behaviour. No
invariant here has been changed as part of this hardening pass; this is a
review, not a redesign.

## OracleNode

1. **A node is a leaf (`text !== null`) or a container (`text === null`),
   never both.** [MODEL] Real Gutenberg has no such binary distinction at the
   data-shape level — a block's `innerContent`/`innerBlocks` can in principle
   mix text-around-children with any number of children in any arrangement.
   This model is deliberately narrower: every shape actually found in the
   corpus (and every shape `CorpusImporter` produces from real
   `parse_blocks()` output) fits one of these two categories, so the
   narrower model is sufficient without being a claim about Gutenberg's
   general capability.

2. **A leaf's content is `prefix . text . suffix`.** [MODEL — placement is
   arbitrary] Where the "prefix"/"text"/"suffix" boundary sits is chosen by
   whoever constructs the node. `Builders::paragraph()` splits meaningfully
   (`<p>` / body / `</p>`); `CorpusImporter::convert()` puts the ENTIRE
   `innerHTML` into `text` with empty prefix/suffix. Both are valid; the
   model does not care where the split is, only that concatenation
   reproduces the byte content.

3. **A container's `separators` has exactly `count(children) + 1` entries,
   enforced by `container()`'s constructor (throws otherwise).** [MODEL, but
   byte-transparent with Gutenberg] Real `innerContent` for a block with N
   children has exactly N null markers, and 0 or 1 string chunk in each of
   the N+1 gaps around them — core omits a gap's entry entirely when there is
   no content there (`!empty($html)` in `add_inner_block()`/
   `add_block_from_stack()`). This model always allocates all N+1 slots,
   using `''` for an absent gap. The two are observably identical:
   `to_parsed_array()` skips emitting a `''` slot, exactly reproducing core's
   omission — so this is a modeling convenience (fixed-size array is easier
   to reason about and validate) that produces byte-identical serialized
   output to core's variable-shape representation.

4. **`to_parsed_array()` skips a separator only when it is exactly `''`, using
   strict `!==` comparison — NOT PHP's `empty()`.** [MODEL, deliberate
   divergence from core] Core's own `get_comment_delimited_block_content()`
   decides void-vs-normal via `empty($block_content)`, which treats the
   string `"0"` as empty too — the confirmed parser bug this spike's Phase 0
   evidence documents. This model's separator check deliberately does NOT
   replicate that bug for inter-child gaps: a `"0"` separator between two
   real children is one genuine byte and must survive, and it does. The
   degenerate case — a zero-child container whose sole separator is `"0"` —
   still loses that content, but only because it is handed to REAL
   `serialize_block()`, whose own outer `empty()` check applies regardless of
   anything this model does; `RandomTreeGenerator` and `CorpusImporter`
   deliberately avoid ever producing that one degenerate shape by accident
   (see `RandomTreeGenerator`'s docblock), and `PropertyBasedOracleTest`
   exercises it as a DELIBERATE, targeted case instead of a stumbled-into one.

5. **`id` never appears in `attrs`, `innerContent`, or anywhere
   `to_parsed_array()` emits.** [MODEL — this is the entire point] Gutenberg
   has no persistent block identity at all; this is the gap the oracle exists
   to fill for testing purposes, and the mechanism (an id living only in the
   PHP object, read by nothing that touches serialization) is what keeps that
   fill from leaking into what a real extractor would ever see.

6. **A node's kind (leaf vs. container) is fixed at construction and never
   changes.** [MODEL] `convert_type()` only operates on leaves (changes
   `block_name`/`attrs`, never converts a leaf to a container or vice versa).
   This matches every real Gutenberg block-type conversion this spike's
   corpus or Phase 1a plan considered (paragraph↔heading, etc. — both
   leaves); the oracle does not attempt to model a hypothetical
   container-to-leaf conversion, which is not a real editor operation.

## OracleTree

7. **`roots` is an ordered list representing top-level document order.**
   [GUTENBERG] Directly mirrors what `parse_blocks()` returns at the top
   level.

8. **Every `id` in a tree is globally unique across all levels.** [MODEL —
   required for identity-tracking to mean anything] Enforced by construction
   (a monotonic `IdGenerator`) and checked directly by
   `PropertyBasedOracleTest`/`PropertyBasedOperationsTest` (mapping
   stability) across thousands of random trees and 16,000 random operations.

9. **`id` is invariant under `reorder()`, `move()`, `edit_text()`, and
   `convert_type()`.** [MODEL — the central premise] Gutenberg itself has no
   concept of identity to preserve or not; this is the assumption the whole
   oracle exists to test the CONSEQUENCES of, not something derived from
   Gutenberg behaviour.

10. **`duplicate()`, `copy_paste()`, and `copy_from()` always mint a FRESH id
    for the copy; the source's id is never reused.** [MODEL] A deliberate
    stance (duplicated content is new content), matching the confidence-model
    reasoning from the accepted M2 planning discussion, not a Gutenberg fact.

11. **`delete()` removes an id from the tree entirely; `restore()` can bring
    back the SAME id, but only if the caller still holds the exact
    `OracleNode` `delete()` returned.** [MODEL, deliberately simplified] The
    oracle has no "ignored/orphaned" status or provenance encoding the way
    the eventual production `Store` table does — it is a pure
    identity-and-content tracker, not a stand-in for that table's semantics.

12. **`remove_node()`'s separator-collapse rule: deleting the child at index
    `i` removes `separators[i]` (the gap immediately BEFORE it), keeping
    `separators[i+1]` onward.** [MODEL, explicitly arbitrary] There is no
    principled way to decide which of a deleted child's two neighbouring gaps
    "should" survive — a real editor re-serializes the whole document fresh
    on save with its own current whitespace conventions, so neither gap is
    more "correct" to keep. This rule is documented precisely so it is never
    mistaken for a discovered Gutenberg fact.

13. **`insert_node()`'s separator-growth rule: a newly inserted child's slot
    defaults to `''`.** [MODEL] The only honest default — inventing
    non-empty content for a slot that never existed would be exactly the
    "infer missing content" this whole amendment must not do.

14. **`reorder()` never touches `separators` — they stay strictly positional
    (tied to slot index, not to whichever child now occupies it).** [MODEL,
    with a stated real-world caveat] This means a moved block's surrounding
    whitespace does not "follow" it. This is an approximation of unknown real
    behaviour, not a claim about it: a genuine editor re-save would rebuild
    the whole document's whitespace fresh via editor JS, which could differ
    from both "old positional" and "moved with the child" — the point this
    model makes is only that SOME defensible, simple rule is needed, and
    positional is it.

15. **`checkpoint()` pushes a full deep clone of `roots`; `undo()`/`redo()`
    swap in clones from history/redo_stack; `checkpoint()` clears
    `redo_stack`.** [GUTENBERG in spirit, MODEL in mechanism] Clearing the
    redo branch on a new action after an undo is standard undo/redo semantics
    that any real editor (including Gutenberg's own) also follows. The
    specific mechanism — whole-tree deep clones rather than a diff-based
    history — is a simplicity-over-efficiency modeling choice with no
    Gutenberg counterpart to compare against.

16. **`snapshot_paths()` produces dot-joined, depth-first, document-order
    path strings.** [MODEL, derived from a GUTENBERG fact] The underlying
    document-order concept is real (`innerBlocks` array order); the specific
    string encoding is this oracle's own convenience representation.

17. **`verify_round_trip_shape()` is a TEST-ONLY diagnostic, not a tree
    invariant.** Two known, documented limitations of this specific helper
    (not of the model it tests): (a) it cannot distinguish a zero-child
    container from a leaf when comparing against a real reparse, because
    both present identically once `innerBlocks` is empty — a cosmetic
    blind spot in the comparison helper, not a defect in what it is
    comparing; (b) it recurses via `array_map()`, which (see Stress Testing
    section of the review package) hits PHP's native call-stack guard around
    13,000-14,000 levels of nesting — many orders of magnitude beyond any
    realistic document, and specific to this helper's implementation
    (`to_content()`'s own `foreach`-based recursion in `to_parsed_array()` is
    measurably more stack-efficient and survives past 20,000 levels in the
    same environment).

18. **`find()`/`locate()` are pure, side-effect-free lookups, always
    consistent with the current state of `roots`.** [MODEL] Trivially true by
    construction; listed for completeness.
