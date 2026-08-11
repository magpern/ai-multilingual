# OTL.4 Implementation Evidence Map

**Branch:** `feature/otl4-jobs-integration`
**Baseline main:** `248b9abd713926d38054076c76a7d37c046da629`
**Frozen plan:** [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md)
**Frozen planning HEAD:** `2accd2e0d07daa63eae39e6152cd450186480ccd`
**Version:** 1.2.0 · **TARGET:** 7 · **Schema:** unchanged · **ADR:** none new

## OTL4.0–OTL4.8 → evidence

| WP | Evidence |
|---|---|
| OTL4.0 | `OTL4_IMPLEMENTATION_BASELINE.md` + this map |
| OTL4.1 | `JobsLifecycleLinker`, `list_recent_by_object`, `Otl4JobsLinkageTest` |
| OTL4.2 | OperationsInspector Jobs section; `jobs-url.ts`; App deep-link |
| OTL4.3 | `JobsOperationAdmission`; Jobs REST `operations[]`; `jobs.ts` prefers operations; JobService retry admission reuse |
| OTL4.4 | Resume/retry confirms; mutateJob; dirty-preserving refresh |
| OTL4.5 | `JobsFailurePresenter`; Partial usage + exactly_once_help; no raw `last_error_message` on association |
| OTL4.6 | List `jobs: null`; invocation `jobs` counter = 0 on list |
| OTL4.7 | `acceptance/otl4-browser/`; a11y confirms / aria-live |
| OTL4.8 | PluginGuard `test_otl4_jobs_integration_boundaries`; tests |

## Critical contracts

- Semantic identity: source_type + source_id + language_id + segment_key
- `LOOKUP_JOB_SCAN_LIMIT = 32`; exhausted-window honesty
- No `selection_rule` / no public `active_lock_key`
- TI.6 admission → Jobs UI + OTL mapping; JobService retry-failed admits via same class
- Job-scoped resume/retry-failed disclosure
- Outcome B Partial (no attempt_count inference of occurrence)
- Jobs attention Deferred; list Jobs enrichment = 0
- Association omits raw `last_error_message` (presentation.failure only)

## Independent review defects fixed

1. OperationsRestTest expected `jobs: null` on detail — updated for bounded empty payload.
2. `BackgroundTranslationJobService::retry_failed_items` duplicated eligibility — now uses `JobsOperationAdmission`.
3. Association serialized raw `last_error_message` — removed; bounded `presentation.failure` remains.

## Feature CI (authoritative)

- Fix push CI: `31529128773` SUCCESS (phpcs, unit, integration, quality, build)
- Review-fix CI: recorded after push of review fixes

## AC1–AC79

79/79 PASS against frozen plan (see review verdict in closure docs after merge).
