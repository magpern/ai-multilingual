# OTL.4 Jobs Integration — Implementation Validation Log

**Status:** **OTL.4 Complete** on `main`
**Merge commit:** `6e77687f6ebbb000372f68d699fba33c71489704` (`merge: complete OTL.4 Jobs Integration`)
**Independent review (implementation):** **PASS**
**Feature CI:** run `31529991869` — phpcs / unit / integration / quality / build **SUCCESS**
**Fresh main CI:** run `31530162912` — phpcs / unit / integration / quality / build **SUCCESS**
**Plan:** [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md)
**Evidence:** [OTL4_IMPLEMENTATION_EVIDENCE.md](OTL4_IMPLEMENTATION_EVIDENCE.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**PR:** https://github.com/magpern/ai-multilingual/pull/18

## Baseline

| Field | Value |
|---|---|
| Main baseline SHA | `248b9abd713926d38054076c76a7d37c046da629` |
| Frozen plan freeze merge | `aaacaf3d6bacb2547ff41c53b46a9000a15d7ebd` |
| Reviewed planning HEAD | `2accd2e0d07daa63eae39e6152cd450186480ccd` |
| Final reviewed feature HEAD | `c1709f245a40ff5b19d5758e68f2d39923da2a52` |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema migration | None |
| New ADR | None |
| Integration API | Unchanged |
| TSC | Not started |
| OTL.5–OTL.6 | Not started |
| Tag | No new tag; existing `v1.2.0` unchanged |

## Delivered

- Semantic Jobs linkage by `source_type + source_id + language_id + segment_key`
- Bounded detail lookup (`LOOKUP_JOB_SCAN_LIMIT = 32`) with exhausted-window honesty
- Retention honesty; `association=null` = bounded-lookup miss (not “no retained record”)
- Detail Jobs ViewModel; list remains `jobs: null` / zero Jobs enrichment
- Jobs tab coexistence + `view=jobs&job_id=` deep-link
- TI.6 `JobsOperationAdmission` (resume / retry-failed / pause / cancel / run)
- Jobs REST `operations[]`; Jobs UI prefers server admission
- OTL `allowed_actions` mapping (`open_job`, `open_jobs`, `resume_job`, `retry_failed_job`)
- Job-scoped resume / retry-failed UX with multi-item disclosure
- Execution revalidation via existing Jobs REST → JobService
- Dirty editor preservation across Jobs subtree refresh
- `JobsFailurePresenter`; no raw association `last_error_message`
- Provider usage Partial; unknown ≠ zero; no monetary cost
- Outcome B Partial (exactly-once help without occurrence claim; no `attempt_count` inference)
- Jobs attention remains Deferred; Store `translation_failed` unchanged
- Local Playwright `acceptance/otl4-browser/` (not CI-gated)

## Acceptance

**79/79 PASS** (frozen plan ACs; independent implementation review PASS after ordinary review fixes)

## Review fixes

1. Operations detail REST test updated for bounded empty Jobs payload (`c3fd60b50`)
2. `retry_failed_items` reuses `JobsOperationAdmission`; association omits raw error messages (`1f9cb7713`)
3. PHPCS array alignment (`c1709f245`)

## Known debt / limitations

- Jobs-backed attention Deferred (JI27)
- Outcome B Partial — no attempt ledger / no occurrence claim
- Job-level usage Partial; per-item tokens / monetary cost Unsupported
- TM known-zero Partial without fabricating persistence
- Reverse Jobs→Operations link Partial
- Non-post Jobs mutation parity Unsupported
- Durable Jobs history / audit timeline Unsupported / Deferred
- Bulk failed retry Deferred to OTL.5
- Playwright local smoke — not CI-gated; deeper fixture-backed flows remain debt
- Path-B detail QA/assessment duplication remains OTL.0 debt

## Exact next step

Begin the definitive **OTL.5 Bounded Bulk Operations** planning process from the closed OTL.4 main baseline. Do not implement OTL.5 until its plan has been externally reviewed, materialized, independently reviewed, and frozen on main.
