# TSC.4 Gutenberg Coverage Expansion — Planning Freeze Validation Log

**Status:** **TSC.4 Architecture Frozen** (planning) — production implementation **NOT STARTED**
**Authoritative plan:** [TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md](TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md)
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md)
**ADR:** **None** — [0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) remains authoritative

## Freeze record

| Field | Value |
|---|---|
| Planning baseline main HEAD | `65daa01545136968cfebd84466f52fbc9ad79035` |
| Baseline drift | None; `main` == `origin/main` at materialization; version **1.3.0**; TARGET **7** |
| Materialization path | Docs-only direct to `main` (no planning PR / no docs branch / no full CI matrix) |
| Plan source | Externally reviewed amended plan · **TSC.4 PLAN REVIEW: FREEZE** |
| External review history | Initial proposal → external review round 1 → four amendments (A1–A4) → revalidation **PASS** → **FREEZE** |
| Plugin version | **1.3.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema / migration | None (**STATE A**) |
| New ADR | **None** |
| Production implementation | **NOT STARTED** |
| TSC.5–TSC.6 | **NOT STARTED** |
| Tag | No new tag; existing `v1.3.0` unchanged |

## External amendments incorporated

1. **A1 — Stale granularity:** **Confirmed by evidence.** `Store::sync_source()` provides segment_key-scoped mutation; unchanged sibling rows receive no update; no new stale/invalidation mechanism.
2. **A2 — Canonical-content invariant:** **Wording corrected.** Distinguish existing ADR-0013 UUID save-time mutation from translation rendering; TSC.4 introduces no new canonical post_content/block-attribute mutation; render path never persists translated content or triggers a save.
3. **A3 — Structural attribute preservation:** **Real gap found; closed with narrow guard.** Existing whole-fragment replacement can permit translated HTML to replace structural attributes; TSC4.1 adds fail-closed structural-attribute-equality guard for existing 14 adapters; protects href, class, id, target, rel, data-*; not a generic HTML engine.
4. **A4 — Block/field pair authority:** **Invariant holds by construction; tests required.** `BlockRenderer` + adapter `get_supported_fields()` remains authoritative; do not duplicate block/field matrix in `BlockTranslationLookup`; malformed-pair characterization tests required.

## STATE A reasoning

- Block segments remain under `source_type=post` with existing `b:<uuid>:<field>` grammar — no new `source_type`.
- TSC4.1 changes are render-path validation/lookup widening only — no schema change.
- Structural-attribute guard is a fail-closed validation addition — no new Store table or identity model.
- ADR-0013 `aimlBlockId` save pipeline unchanged — not a TSC.4 schema concern.
- Deferred surfaces (navigation, query, reusable, FSE, table, search, custom blocks) remain out of scope.

## TARGET / schema verdict

**STATE A · TARGET 7 · no migration · no new source_type · no second Store.**

## ADR verdict

**No new ADR.** ADR-0013 (`b:<uuid>:<field>`, `aimlBlockId`) remains the sole governing record for Gutenberg identity. TSC.4 extends render-layer field allowlist and adds validation guard — same class of change as A.0 without new ADR.

## Matrices frozen

| Matrix | Count / range |
|---|---|
| GB | GB1–GB25 |
| AC | AC1–AC22 |
| WP | TSC4.0–TSC4.4 |

## Frozen architecture decisions (non-exhaustive)

| Decision | Freeze |
|---|---|
| Segment grammar | `b:<uuid>:<field>` (ADR-0013) |
| Block attribute | `aimlBlockId` (ADR-0013) |
| Render seam | `the_content` @ priority 1 |
| Activation | Four block flags default **OFF** |
| Lookup fix | Accept all `Contract::SUPPORTED_FIELDS` (grammar layer) |
| Pair authority | `BlockRenderer` + adapter `get_supported_fields()` |
| Structural guard | Fail-closed attribute equality for tag-bypass adapters |
| Stale granularity | Per-segment_key via existing `Store::sync_source` |
| Canonical write | Render path never writes; UUID save pipeline unchanged |
| Navigation / Query / reusable | **Deferred** |
| FSE / table / search / custom blocks | **Deferred** |
| html / shortcode / embed | **Unsupported** |
| core/code | Explicit carry-forward (pre-TSC) |
| Elementor | TSC.5 — out of scope |
| Public block API | TSC.6 — out of scope |
| OTL / Jobs / TI.7 | Existing authorities unchanged |

## Explicit TSC.5 / TSC.6 exclusion

- **TSC.5 (Elementor):** No `e:` work, no Elementor save/invalidation seam, no Elementor feature-flag changes.
- **TSC.6 (public extension):** No public block registration API, no `register_translatable_block()`, no third-party block schema traversal.

## Consistency checks (materialization)

| Check | Result |
|---|---|
| GB numbering contiguous 1–25 | PASS |
| AC numbering contiguous 1–22 | PASS |
| WP ladder TSC4.0–TSC4.4 | PASS |
| STATE A consistent across plan + log | PASS |
| TARGET 7 consistent | PASS |
| A1–A4 dispositions recorded | PASS |
| No claim TSC.4 implemented | PASS |
| No claim TSC.5+ started | PASS |
| No version/tag/release/deployment change | PASS |
| Referenced parent/TSC0–3/ADR-0013 paths exist | PASS |
| Key symbols verified (`Migrator::TARGET`, `Contract::SUPPORTED_FIELDS`, `BlockTranslationLookup`) | PASS |
| Docs-only diff | PASS (validated at commit time) |

## Lightweight reference validation

| Reference | Verified |
|---|---|
| `docs/adr/0013-gutenberg-segment-identity.md` | Exists |
| `src/Database/Migrator.php` `TARGET = 7` | Confirmed |
| `src/Translation/BlockTranslationLookup.php` content-only filter | Confirmed (lines 73–77) |
| `src/Translation/Store.php` `sync_source` per-row branching | Confirmed (lines 1585–1666) |
| `src/Translation/Renderer.php` `the_content` @ 1 | Confirmed |
| `src/Block/Contract.php` `SUPPORTED_FIELDS` (six fields) | Confirmed |
| `ai-multilingual.php` version 1.3.0 | Confirmed |

## Implementation status

**NOT STARTED.** No production code, tests, config, workflows, or dependency changes in this freeze.

## Exact next step

Implement TSC.4 from the frozen `main` baseline using branch `feature/tsc4-gutenberg-coverage-expansion`, followed by independent implementation review, review-fix loop, merge, fresh main CI, and milestone closure.

Do **not** begin implementation in the planning-freeze task.
