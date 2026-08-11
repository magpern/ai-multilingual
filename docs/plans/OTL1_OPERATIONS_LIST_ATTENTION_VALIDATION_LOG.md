# OTL.1 Operations List + Attention — Implementation Validation Log

**Status:** **OTL.1 Complete** on `main`
**Merge commit:** `466eb6a470b2ea48b949bc05e0717afbc6600fc3` (`merge: complete OTL.1 Operations List + Attention`)
**Independent review (implementation):** **PASS**
**Feature CI:** run `31489809640` — phpcs / unit / integration / quality / build **SUCCESS**
**Plan:** [OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md](OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**PR:** https://github.com/magpern/ai-multilingual/pull/15

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `54ea13fdc06347ccb81acd51cdd939f124251be3` |
| Reviewed feature HEAD | `0cd6ae39da9ec18a7aa732f6001aa6d87da971dc` |
| OTL.1 freeze merge | `30332a315e2b0a99a036a5aa521771b21ba2cd9a` |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.2–OTL.6 | Not started (OTL.2 next = planning only) |
| Public/SaaS neutrality | Hard invariant |

## Delivered

- Operations Workspace tab in existing Translator Workspace shell
- Collision-free operational attention IDs: `stale`, `review_pending`, `review_rejected`, `unpublished`, `translation_failed`
- Multi-label `attention_reasons` on list/detail (cheap Store axes only)
- Attention list filter + language-wide attention-counts (auth ≡ list)
- Honesty copy (not exhaustive TI.5/TI.7/Jobs risk)
- Filters, pagination (20/50), URL state (`view=operations`)
- Reusable read-only inspector (Decision A); no review/publish/Jobs mutations
- Navigation: Open in Translate / Open in Review / source / frontend
- Local Playwright suite `acceptance/otl1-browser/` (not CI-gated)

## Acceptance

**82/82 PASS** (frozen plan ACs; independent implementation review PASS after link-key / detail-reasons / reserved-URL notice fixes)

## Known debt / limitations

- Inspector presents TI.4/TI.5/TI.7 as structured JSON (OTL.2 will polish editable unified detail)
- Playwright is local/documented like F10 — not CI-gating
- ~10k scale measured only where practical (hundreds/thousands covered)
- Path-B detail QA/assessment duplication remains OTL.0 debt

## Exact next step

Begin the definitive **OTL.2 Unified Detail + Edit/Review planning** process from the closed OTL.1 main baseline. Do not implement OTL.2 until its plan has been externally reviewed, materialized, independently reviewed, and frozen on main.
