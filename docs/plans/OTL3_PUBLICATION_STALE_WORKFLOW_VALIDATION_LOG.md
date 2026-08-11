# OTL.3 Publication + Stale Workflow — Implementation Validation Log

**Status:** **OTL.3 Complete** on `main`
**Merge commit:** `77fc39da5d9b30d204e5a0c04e318a463ad39484` (`merge: complete OTL.3 Publication + Stale Workflow`)
**Independent review (implementation):** **PASS**
**Feature CI:** run `31521001615` — phpcs / unit / integration / quality / build **SUCCESS**
**Fresh main CI:** run `31521213814` — phpcs / unit / integration / quality / build **SUCCESS**
**Closure commit:** `129b448d25c88fa998c8c6bddb12067bc2091a10`
**Post-closure CI:** run `31521451325` — phpcs / unit / integration / quality / build **SUCCESS**
**Plan:** [OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md](OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md)
**Evidence:** [OTL3_IMPLEMENTATION_EVIDENCE.md](OTL3_IMPLEMENTATION_EVIDENCE.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**PR:** https://github.com/magpern/ai-multilingual/pull/17

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `6a64252a602bdea923a8c1c7b86e73441cdca666` |
| Frozen plan freeze merge | `053570275e019ec88137208fd8d1ba32542961d8` |
| Reviewed planning HEAD | `5b778f1c57391182f25d051c9b5553d9bfed8704` |
| Final reviewed feature HEAD | `773a998f9fc076476d0dd4f6a49e7608ac32d1f2` |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None |
| New ADR | None |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.4–OTL.6 | Not started |
| Tag | No new tag; existing `v1.2.0` unchanged |

## Delivered

- Operations detail publish / unpublish via existing TI.7 REST → PublicationService
- Settings form controls for gate + auto_publication_mode with immediate vs prospective confirmations
- Stale workflow presentation; published+stale remains published
- Sync retranslate with mandatory pre-persist `expected_translation_hash` guard (409 `aiml_translation_hash_mismatch`)
- Jobs null-hash semantics retained
- Retranslate confirmation discloses possible auto-republish under non-manual modes
- Overlay-eligibility gate messaging (not visibility guarantees)
- Current publication facts vs in-session operation result honesty
- Local Playwright suite `acceptance/otl3-browser/`

## Acceptance

**96/96 PASS** (frozen plan ACs; independent implementation review PASS after confirmation-copy fix)

## Review fixes

1. Strengthened retranslate confirmation to explicit “MAY be automatically published again” wording (`773a998f9`)

## Known debt / limitations

- Durable publication audit timeline remains Deferred (OT15 / PS10)
- Non-post / term publication mutation remains Unsupported / Deferred
- Bulk publish/retranslate Deferred to OTL.5
- Jobs detail/retry Deferred to OTL.4
- Playwright local/documented — not CI-gated
- Path-B detail QA/assessment duplication remains OTL.0 debt

## Exact next step

Begin the definitive **OTL.4 Jobs Integration planning** process from the closed OTL.3 main baseline. Do not implement OTL.4 until its plan has been externally reviewed, materialized, independently reviewed, and frozen on main.
