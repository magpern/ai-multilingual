# OTL.4 Implementation Evidence Map

**Branch:** `feature/otl4-jobs-integration`
**Baseline main:** `248b9abd713926d38054076c76a7d37c046da629`
**Frozen plan:** [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md)
**Version:** 1.2.0 · **TARGET:** 7 · **Schema:** unchanged · **ADR:** none new

## OTL4.0–OTL4.8 → evidence

| WP | Evidence |
|---|---|
| OTL4.0 | `OTL4_IMPLEMENTATION_BASELINE.md` + this map |
| OTL4.1 | `JobsLifecycleLinker`, `list_recent_by_object`, `Otl4JobsLinkageTest` |
| OTL4.2 | OperationsInspector Jobs section; `jobs-url.ts`; App deep-link |
| OTL4.3 | `JobsOperationAdmission`; Jobs REST `operations[]`; `jobs.ts` prefers operations |
| OTL4.4 | Resume/retry confirms; mutateJob; dirty-preserving refresh |
| OTL4.5 | `JobsFailurePresenter`; Partial usage + exactly_once_help |
| OTL4.6 | List `jobs: null`; invocation `jobs` counter = 0 on list |
| OTL4.7 | `acceptance/otl4-browser/`; a11y confirms / aria-live |
| OTL4.8 | PluginGuard `test_otl4_jobs_integration_boundaries`; tests |

## Critical contracts

- Semantic identity: source_type + source_id + language_id + segment_key
- `LOOKUP_JOB_SCAN_LIMIT = 32`; exhausted-window honesty
- No `selection_rule` / no public `active_lock_key`
- TI.6 admission → Jobs UI + OTL mapping
- Job-scoped resume/retry-failed disclosure
- Outcome B Partial (no attempt_count inference of occurrence)
- Jobs attention Deferred; list Jobs enrichment = 0
