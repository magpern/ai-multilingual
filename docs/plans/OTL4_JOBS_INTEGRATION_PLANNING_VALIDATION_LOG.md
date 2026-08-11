# OTL.4 Jobs Integration — Planning Freeze Validation Log

**Status:** **OTL.4 Architecture Frozen** on `main`
**Authoritative plan:** [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)

## Freeze record

| Field | Value |
|---|---|
| Planning baseline main HEAD | `61ebfc4d4ca47dae9424a71a518000b923edef03` |
| Planning branch | `docs/otl4-jobs-integration-planning-freeze` |
| Materialization HEAD | `f3c44ca0ba0a372286298df3e515d8fcd451f897` |
| Final reviewed planning HEAD | `2accd2e0d07daa63eae39e6152cd450186480ccd` |
| Independent planning review | **PASS** |
| Review fixes | Correct ADR-0011 related link (`0011-resumable-job-pipeline.md`) |
| Freeze merge | `aaacaf3d6bacb2547ff41c53b46a9000a15d7ebd` (`merge: freeze OTL.4 Jobs Integration implementation plan`) |
| Freeze merge CI | run `31524892001` — phpcs / unit / integration / quality / build **SUCCESS** |
| Plugin version | **1.2.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema / new index | None |
| New ADR | None |
| Production implementation | **Not started** |
| OTL.5–OTL.6 / TSC | Not started |
| Tag | No new tag; existing `v1.2.0` unchanged |

## Locked contracts

- Semantic Jobs linkage: `source_type + source_id + language_id + segment_key`
- `active_lock_key` = TI.6 exclusivity only (not public identity)
- Bounded detail-only lookup; `LOOKUP_JOB_SCAN_LIMIT = 32`
- `association=null` = bounded-lookup miss (not “no retained record”)
- No serialized `selection_rule`
- TI.6 `JobsOperationAdmission` owns resume/retry-failed UI admission; OTL maps only
- Resume / retry-failed are **job-scoped** with multi-item disclosure
- Outcome B presentation **Partial** (never from `attempt_count` alone)
- Jobs-backed attention **Deferred**; list zero Jobs enrichment
- Jobs tab remains first-class
- JI1–JI55; OTL4.0–OTL4.8; AC1–AC79

## Exact next step

Run the combined OTL.4 implementation + independent implementation review + merge + milestone closure task from the frozen main baseline.

Do **not** implement OTL.4 until that combined implementation task begins.
Do **not** create the implementation branch in the planning freeze task.
Do **not** start OTL.5–OTL.6 or TSC.
