# A.4 — Nested Gutenberg — Validation Log

**Milestone:** A.4 Nested Gutenberg
**Implementation branch:** `feature/a4-nested-gutenberg`
**Plan:** [A4_NESTED_GUTENBERG_IMPLEMENTATION_PLAN.md](A4_NESTED_GUTENBERG_IMPLEMENTATION_PLAN.md) (**Architecture Frozen** → implementation complete on branch)
**ADR:** [0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) (**Accepted** — no new ADR)
**Evidence:** [A4_NESTED_GUTENBERG_IDENTITY_RESEARCH_LOG.md](A4_NESTED_GUTENBERG_IDENTITY_RESEARCH_LOG.md) (**CONDITIONAL GO**; F5 **PASS**)
**Baseline main (pre-plan merge):** `39f41ebdd335725b9e74f534670d101f601728f8`
**Plan merge commit:** `7ea2aed7ec566618762328d573584ed9cfccb87a`
**Research tag:** `ar2-nested-gutenberg-identity-research-complete`
**Closure status:** Complete on branch — ready for independent merge review

---

## A4.0 — Baseline / F5 contract verification

**Status:** PASS

| Gate | Result |
|---|---|
| F5 / CONDITIONAL GO evidence | PASS |
| `b:<uuid>:<field>` unchanged | PASS |
| Walker / Registry / Adapter ownership | PASS |
| Seven production leaves unchanged | PASS |
| Unit (pre-code) | 491 OK (2 skipped) |
| PluginGuard | 17 OK |
| PHPCS | PASS (pre-existing warnings only) |

---

## A4.1 — Eligibility / structural transparency

**Status:** PASS

- Added `STRUCTURAL_TRANSPARENT_BLOCKS` / `CHILD_TRAVERSAL_HOST_BLOCKS` + helpers
- Kept leaf-local empty-`innerBlocks` guards (not globally removed)
- Nested supported leaves remain independently eligible

---

## A4.2 — List / list-item admission

**Status:** PASS

- `core/list` produces zero units
- Nested leaf `list-item` uses `b:<uuid>:content`
- Parent list-item with `innerBlocks` deferred (source fallback)
- Reorder preserves keys; duplicate keys prevented

---

## A4.3 — Structural container child traversal

**Status:** PASS

- group / columns / column / list transparent
- Locked by `NestedGutenbergAdmissionTest`

---

## A4.4 — Host container child traversal

**Status:** PASS

- quote / details / cover / media-text children extract
- No parent citation/summary/pullquote admission

---

## A4.5 — Diagnostics + regression hardening

**Status:** PASS

Counters: `structural_container_seen`, `nested_supported_leaf`, `nested_unsupported_leaf`, `duplicate_unit_prevented`, `nested_source_fallback`

No source text; no path telemetry.

---

## A4.6 — Performance / render safety

**Status:** PASS

Evidence: [a4-performance.json](a4-evidence/a4-performance.json)

Observation-only timings (no invented budgets). No pathological recursion observed in unit fixtures.

---

## A4.7 — Full Tier 0 + targeted acceptance

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | 508 tests, 1247 assertions — OK (2 skipped) |
| Integration | 507 tests, 10998 assertions — OK (2 skipped) |
| PluginGuard | 17 OK |
| PHPCS | PASS |
| `git diff --check` | PASS |
| Browser/HTTP matrix | **18/18 PASS** — [a4-http-acceptance.json](a4-evidence/a4-http-acceptance.json) |
| Fixture | page `6419` `/a4-nested-gutenberg-fixture/` (+ `/sv/`) |
| Units extracted | 12 |
| Duplicate logical units | 0 |
| Rendered FP | **0** |
| Language leakage (main content) | **0** |
| Elementor A.2/A.3 regression | PASS (no cross-leak) |

---

## A4.8 — Closure / final supported surface

**Status:** PASS

### Structural-transparent

- `core/group`
- `core/columns`
- `core/column`
- `core/list`

### Nested child traversal validated

- nested `core/list-item` leaves
- quote / details / cover / media-text children
- existing supported leaves inside structural containers

### Deferred

- quote citation; pullquote fields; details summary
- gallery / Media Library metadata
- parent list-item with non-empty `innerBlocks`
- Navigation / Query / post-template / reusable-synced

### Limitations

- Rank Math meta may still echo EN strings outside block overlay
- Scenario 15 validated as coexistence of separate fixtures (not one mixed document)
- Parent list-item host text remains source by design

### Technical debt

- Optional later admission for citation/summary/pullquote/image caption
- Shared-definition families require a future ADR if pursued

---

## Merge readiness

Branch `feature/a4-nested-gutenberg` is complete for independent review.
Recommended tag after merge: `a4-nested-gutenberg-complete`
Do not begin the next Program A milestone until merged and closed.
