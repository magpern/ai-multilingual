# OTL.2 Unified Detail + Edit/Review — Implementation Validation Log

**Status:** **OTL.2 Complete** on `main`
**Merge commit:** `060649d9a8cf20c3698f9ed145d29c8d20d67143` (`merge: complete OTL.2 Unified Detail + Edit/Review`)
**Independent review (implementation):** **PASS** (after `fix(otl): restore allVisibleSelected import in App`)
**Feature CI:** run `31502074356` — phpcs / unit / integration / quality / build **SUCCESS**
**Fresh main CI:** run `31502287019` — phpcs / unit / integration / quality / build **SUCCESS**
**Post-closure CI:** run `31502540261` — phpcs / unit / integration / quality / build **SUCCESS**
**Plan:** [OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md](OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**PR:** https://github.com/magpern/ai-multilingual/pull/16

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `d33bc0ace0040abbc39b330ff91db856a872ee41` |
| Reviewed feature HEAD | `77ea3feac8722313aec11b5b1a12270b2a04f1ed` |
| Frozen plan freeze merge | `6e5fb47427676edc156d58335055a488a7f1a899` |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None — reuse existing `translation_hash` |
| New ADR | None |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.3–OTL.6 | Not started (OTL.3 next = planning only) |
| Public/SaaS neutrality | Hard invariant held |
| Tag | No new tag; existing `v1.2.0` unchanged |

## Delivered

- Unified detail workspace by extending OTL.1 `OperationsInspector` (no second detail app)
- Post-backed target editor + shared Workspace save path
- Required `expected_translation_hash` optimistic concurrency → 409 `aiml_translation_hash_mismatch`
- Source concurrency preserved (`aiml_source_hash_mismatch`)
- Dirty evidence honesty + dirty review gate
- Structured TI.4 QA + TI.5 assessment presentation
- Submit / approve / reject via existing ADR-0015 routes
- Approved ≠ published messaging; publication mutation still OTL.3
- Stale/retranslate still OTL.3
- Non-post inspection supported; non-post mutation deferred (coverage debt)
- Raw target round-trip integrity; HTML escaped display
- Operations URL `translation_id` sync; Translate/Review tabs retained
- Local Playwright suite `acceptance/otl2-browser/` (not CI-gated)

## Acceptance

**88/88 PASS** (frozen plan ACs; independent implementation review PASS after App import fix)

## Known debt / limitations

- Term/taxonomy mutation REST Deferred (non-post inspection only)
- Path-B detail QA/assessment duplication remains OTL.0 debt
- Playwright local/documented like OTL.1 — not CI-gating
- Review queue deep-link Partial (queue DTO lacks `translation_id`)
- Provenance/TM evidence Partial (bounded display)

## Exact next step

Begin / continue **OTL.3** from the frozen plan [OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md](OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**; implementation next). Do not start OTL.4–OTL.6 or TSC under OTL.
