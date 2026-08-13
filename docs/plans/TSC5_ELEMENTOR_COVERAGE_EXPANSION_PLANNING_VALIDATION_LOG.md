# TSC.5 Elementor Coverage Expansion — Planning Freeze Validation Log

**Status:** **TSC.5 Architecture Frozen** (planning) — production implementation **NOT STARTED**
**Authoritative plan:** [TSC5_ELEMENTOR_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md](TSC5_ELEMENTOR_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md)
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md)
**ADR:** **None new** — [0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md) remains authoritative

## Freeze record

| Field | Value |
|---|---|
| Planning baseline main HEAD | `592d97b72e63efb96dd3d1a6a33717a52ef8f39d` |
| Baseline drift | None; `main` == `origin/main` at materialization; version **1.3.0**; TARGET **7** |
| Materialization path | Docs-only direct to `main` (no planning PR / no docs branch / no full CI matrix) |
| Plan source | Externally reviewed amended plan · **TSC.5 PLAN REVIEW: FREEZE** |
| External review history | Initial proposal → external review **AMEND** → four refinements (A1–A4) → revalidation **PASS** → **FREEZE** |
| Plugin version | **1.3.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema / migration | None (**STATE A**) |
| New ADR | **None** — ADR-0016 sufficient |
| Production implementation | **NOT STARTED** |
| TSC.6 | **NOT STARTED** |
| Tag | No new tag; existing `v1.3.0` unchanged |

## External amendments incorporated

1. **A1 — Widget scope:** **No optional expansion.** Committed Supported set frozen to existing A.2/A.3 eight families only. TSC5.6 optional widget wave removed. No discretionary product scope at implementation time.
2. **A2 — Invalidation event contract:** **Frozen.** `elementor/document/after_save` = authoritative mark-dirty after final Elementor persistence; `save_post` @ 20 = redundant/fallback early mark; coordinator coalesces; shutdown @ 20 = sole sync authority; **no** `before_save`; **no** `updated_post_meta` for `_elementor_data`; direct meta-only writes = **Unsupported**.
3. **A3 — Structural guard:** **Surface-neutral** `StructuralAttributeGuard` in `src/Translation/Safety/`; `BlockStructuralAttributeGuard` delegates with zero TSC.4 regression; Elementor HTML controls consume after sanitize.
4. **A4 — Render/cache:** **Frozen context matrix** (editor/preview/admin/REST/cron/source = canonical; visitor frontend = overlay when eligible); cache/language invariant satisfied by existing `ElementorCacheInvalidation` for committed scope; integration tests required.

## STATE A reasoning

- Elementor segments remain under `source_type=post` with existing `e:d:` grammar — no new `source_type`.
- TSC.5 changes are invalidation seam, render-context gates, structural guard refactor, and integration proof — no schema change.
- A.2/A.3 production module already exists under `src/Elementor/` — TSC.5 hardens, does not greenfield.
- Deferred surfaces (templates, Theme Builder, forms, globals, third-party widgets) remain out of scope.
- No canonical `_elementor_data` translated writes.

## TARGET / schema verdict

**STATE A · TARGET 7 · no migration · no new source_type · no second Store.**

## ADR verdict

**No new ADR.** ADR-0016 (Hybrid D, owner scope, deny-list, overlay model) remains the sole governing record for Elementor identity. TSC.5 adds invalidation seam, render-context hardening, and surface-neutral structural guard — same class of change as A.2/A.3 without new ADR.

## Matrices frozen

| Matrix | Count / range |
|---|---|
| EL | EL1–EL31 |
| AC | AC1–AC30 |
| WP | TSC5.0–TSC5.6 |

## Frozen architecture decisions (non-exhaustive)

| Decision | Freeze |
|---|---|
| Segment grammar | `e:d:<owner_post_id>:<element_id>:<control_key>[:<nested_item_id>]` (ADR-0016) |
| Store field_key | `_elementor` |
| Source ownership | `source_type=post` — no SOURCE_ELEMENTOR |
| Committed widgets | heading, text-editor, button, accordion, toggle, image (custom caption), icon-list, call-to-action |
| Optional widget expansion | **None** — no TSC5.x widget wave |
| Invalidation authoritative hook | `elementor/document/after_save` @ 20 |
| Invalidation fallback | `save_post` @ 20 (existing) |
| Meta hook for `_elementor_data` | **Not used** |
| before_save | **Not used** |
| Direct meta-only writes | **Unsupported** |
| Shutdown sync | Sole authority @ 20 |
| Render seam | `elementor/frontend/builder_content_data` @ 20 |
| Editor/preview | Canonical source only — no translation preview |
| Structural guard | `Translation/Safety/StructuralAttributeGuard` |
| Cache invariant | Existing ElementorCacheInvalidation — Supported for committed scope |
| Activation | Both Elementor flags default OFF |
| Public API | TSC.6 — out of scope |

## Lightweight validation (materialization)

| Check | Result |
|---|---|
| EL1–EL31 contiguous | PASS |
| AC1–AC30 contiguous | PASS |
| TSC5.0–TSC5.6 contiguous | PASS |
| Eight-widget scope consistent | PASS |
| A2 event matrix consistent | PASS |
| A4 render-context matrix consistent | PASS |
| Cache verdict consistent | PASS |
| STATE A / TARGET 7 / no migration | PASS |
| ADR-0016 sufficient / no new ADR | PASS |
| No implementation claims | PASS |
| No TSC.6 work | PASS |
| Docs-only diff | PASS (see git diff --name-only) |
| Full unit/integration/PHPCS/build/ZIP | **Not run** (docs-only workflow) |

## Explicit TSC.6 exclusion

- No public Elementor widget registration API
- No wildcard settings-path configuration
- No admin UI for user-defined widget fields

## Exact next step

Implement TSC.5 from the frozen `main` baseline using branch `feature/tsc5-elementor-coverage-expansion`, followed by independent implementation review, review-fix loop, merge, fresh main CI, and milestone closure.

**TSC.5 PLANNING FREEZE: COMPLETE**

**TSC.5 Architecture Frozen**
