# OTL.2 Unified Detail + Edit/Review — Implementation Validation Log

**Status:** Implementation baseline established (feature branch)
**Plan:** [OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md](OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Implementation branch:** `feature/otl2-unified-detail-edit-review`

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `d33bc0ace0040abbc39b330ff91db856a872ee41` |
| Frozen plan freeze merge | `6e5fb47427676edc156d58335055a488a7f1a899` |
| Planning closure baseline | `d33bc0ace0040abbc39b330ff91db856a872ee41` |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | **None** — reuse existing `translation_hash` column |
| New ADR | **None** |
| UD1–UD56 | Frozen dispositions (31 Supported / 12 Deferred / 5 Partial / 8 Unsupported) |
| Acceptance criteria | **88** contiguous |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.3–OTL.6 | Not started |
| Site/SaaS neutrality | Hard invariant |

## Locked contracts

- Dirty-evidence honesty (last-persisted while dirty)
- Dirty review gate (submit/approve/reject unavailable while dirty)
- Target concurrency: required `expected_translation_hash` → 409 `aiml_translation_hash_mismatch`
- Source concurrency preserved: `source_hash` → 409 `aiml_source_hash_mismatch`
- Cross-object inspection vs post-backed mutation
- Term/taxonomy mutation Deferred (coverage debt)
- Raw target round-trip integrity
- Extend OTL.1 Operations inspector (no second detail app)
- No publication mutation / retranslate / Jobs / bulk / CAT / durable drafts / keystroke QA

## Work packages

| WP | Status |
|---|---|
| OTL2.0 Baseline / factual lock | In progress |
| OTL2.1 Unified detail shell | Pending |
| OTL2.2 Target editor + save + concurrency + round-trip | Pending |
| OTL2.3 QA + assessment + dirty honesty | Pending |
| OTL2.4 Review actions + dirty gate | Pending |
| OTL2.5 Navigation / tab relationship | Pending |
| OTL2.6 Conflict / security / a11y / responsive | Pending |
| OTL2.7 Playwright + manual acceptance | Pending |
| OTL2.8 Docs / closure preparation | Pending |

## Exact next step

Implement OTL2.1–OTL2.8 on this branch; do not start OTL.3 or TSC; do not bump version or tag.
