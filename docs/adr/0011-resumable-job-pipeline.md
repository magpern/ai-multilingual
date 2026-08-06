# ADR-0011 — Resumable staged job pipeline with bounded checkpoints

## Status

**Accepted baseline** (Milestone 0 design). **Amendment Proposed** (2026-08-06) — pending explicit disposition before J1 implementation.

| Field | Value |
|---|---|
| **Original acceptance** | Milestone 0 (Accepted design baseline) |
| **Amendment date** | 2026-08-06 |
| **Implementation status** | **Not implemented** |
| **Supersedes status claim** | ~~Implemented in Milestone 3~~ — false; Jobs code, schema, and Action Scheduler wiring do not exist on `main` after Glossary MVP + Review Workflow |
| **Decision maker (amendment)** | _Pending — Gate A Accept or Gate B provisional_ |
| **Approval date (amendment)** | _Pending_ |
| **Scope of amendment** | Review Workflow / TM interaction; lifecycle and table model; schema target 6; batch grouping; lock/lease implementability; canonical plan pointer — exactly as in **Amended Decision** below and [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md) |
| **Residual risks accepted** | _Recorded at Gate A/B disposition_ |
| **Mandatory review date** | Background Translation Jobs milestone closure (or Gate B expiration) |
| **Revalidation trigger** | New queue engine; automatic approval from jobs; worker TM write-back; dedicated lock table replacing job-table SoT; parent job aggregate state machine; approval-gated rendering; site-wide unrestricted fan-out |

**Implementation gate:** **Closed** until Gate A or Gate B is satisfied.

- **Gate A:** This amended ADR is explicitly **Accepted** after review of the amendment scope.
- **Gate B:** A complete Product Owner provisional approval records decision maker, approval date, exact amendment scope, accepted residual risks, mandatory review date, and expiration/revalidation trigger.

A generic “ADR-0011 was already Accepted” is **insufficient** for these material amendments.

Canonical plan: [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md).  
Product context: [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md) §11.3.  
Related: [ADR-0015](0015-review-workflow-and-tm-approval-policy.md) (Review + approval-gated TM), [ADR-0014](0014-glossary-platform-lexicon.md) (Glossary).

---

## Context

"Translate this object" is not one operation. It extracts segments, normalizes
and hashes them, consults memory, batches the remainder, calls a provider,
validates the response, saves, and invalidates caches. Treated as an opaque
unit, any failure means redoing all of it — including the paid part.

Post-v1 platform state (after Glossary MVP and Review Workflow):

- Store owns translation content and the review axis (`review_status`).
- TM write-back occurs **only on approval** (ADR-0015); machine-origin rows do not enter TM at save time.
- Glossary is resolved through `GlossaryService` / `glossary_fragment`.
- Sync Workspace translate already uses `TranslationService` → providers → Store.
- Migrator `TARGET` is **5**; no `aiml_jobs` table exists yet.
- Action Scheduler is **not** yet a plugin dependency (F12 deliberately avoided it; Jobs introduces it intentionally).

---

## Decision (original — retained where not superseded)

The job is a staged pipeline, with `stage` and a `checkpoint` recorded on the
job row. The provider-call stage records the index of the last batch
successfully received, so a resume never re-sends a batch that has already been
paid for. Saving is an upsert keyed by segment identity, so replaying it is a
no-op.

`aiml_jobs` is the source of truth for **orchestration**; Action Scheduler is the
trigger and scheduler only. Action Scheduler's arguments are not queryable for
the progress UI and it purges completed actions after thirty days, which would
destroy operational history if used as SoT.

Concurrency is guarded so that at most one **active** job holds a given
`{source_type}:{source_id}:{language_id}` identity. MariaDB does not provide a
portable partial unique index over “active” statuses; see **Amended Decision**
for the implementable `active_lock_key` design.

The checkpoint is resume state, not an archive. It stores stage markers, batch
indexes and segment IDs — identifiers and counters, never content. Raw provider
payloads are never persisted unless debug mode is explicitly enabled, and then
to the rotated logger rather than the database. The column is `TEXT` with a 16 KB
soft cap; exceeding it degrades to coarser resume granularity and logs that it
did, rather than truncating silently. Checkpoints compact to NULL on success.

---

## Amended Decision (2026-08-06)

Material amendments relative to the original text. **Superseded wording is struck through in spirit below; do not implement the superseded behaviors.**

1. **Implementation status.** Jobs are **not implemented**. Delivery is the post-v1 **Background Translation Jobs** initiative. Schema step is Migrator **TARGET = 6**.

2. **Table ownership.**
   - `aiml_jobs` — job aggregate orchestration state (SoT for jobs).
   - `aiml_job_items` — per-segment item state, hashes, bounded errors, result codes.
   - Store remains SoT for translation content. Job rows must not hold canonical translated bodies.

3. **~~Update memory as a job stage~~ — SUPERSEDED.**  
   Machine job output must **not** write Translation Memory.  
   - Persist via existing `TranslationService` / Store paths as `machine_translated` with `review_status=not_submitted`.  
   - TM **consult/read** during orchestration remains allowed.  
   - TM **write-back** remains owned by Review Workflow approval (ADR-0015).  
   - Jobs must never auto-approve or auto-submit for review.

4. **Execution boundary.**  
   Workers and Action Scheduler callbacks must not call AI providers or Store repositories directly. One canonical application boundary — `BackgroundTranslationItemProcessor` — coordinates existing platform services (`TranslationService`, `GlossaryService`, QA, Store persistence semantics). Controllers/CLI/AS callbacks are transport/trigger only.

5. **Three-way state model.**  
   Separate **job aggregate status**, **item status**, and **requested operator action** (`none` | `pause` | `cancel`). Pause/cancel are observed at safe item boundaries; in-flight provider calls are not forcibly interrupted; terminal states are immutable. Catalogs and transitions are frozen in the canonical Jobs plan.

6. **Lock / lease (MariaDB-implementable).**  
   Retain job-table SoT. Use a nullable **`active_lock_key`** column that equals the stable `lock_key` only while the job is active, with a UNIQUE index (many NULLs allowed when finished). Lease owner token, expiry, heartbeat, and stale recovery are defined in the Jobs plan. Do **not** rely on partial unique indexes, read-then-insert without atomic protection, or application-only uniqueness.

7. **Batch semantics.**  
   Bulk work uses a lightweight **`batch_id`** grouping identifier over independent post/language jobs. No persisted parent aggregate or hidden parent/child state machine. Progress and cancel-all derive from grouped child jobs.

8. **Item materialization.**  
   Eligible items are materialized **at job creation** (bounded workload). Execution revalidates hashes and eligibility; it must not silently fan out to newly discovered segments.

9. **Glossary / prompt context.**  
   Record intended glossary version and provider/profile identifiers at create; resolve current glossary through `GlossaryService` at execution; record actual glossary version used. Glossary version drift is allowed and recorded in MVP. Do not store full glossary fragments or prompts in job rows.

10. **Action Scheduler.**  
    Required runtime dependency for Jobs. If unavailable, job creation is rejected with a clear health error; public frontend remains unaffected. No second queue fallback.

11. **Canonical plan.**  
    Schema DDL, indexes, retention, retry taxonomy, budgets, permissions, REST/CLI/UI, acceptance criteria, and work packages **J0–J8** live in [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md). That plan must not contradict this amended ADR; conflicts require a further ADR amendment.

---

## Consequences

### Positive (retained + amendment)

- Failures are diagnosable; retries are safe and cheap.
- Job rows stay small (identifiers, counters, bounded errors).
- Review Workflow and approval-gated TM remain authoritative.
- Single translation pipeline; Jobs only orchestrate.
- MariaDB-compatible uniqueness without a second lock product.

### Negative / residual risks

- On hosts with `DISABLE_WP_CRON` and a five-minute system cron, worst-case dispatch latency is five minutes unless Action Scheduler's loopback runner works through the CDN — must be measured, not assumed.
- Action Scheduler becomes a hard dependency for job creation.
- Glossary drift between create and execute may change terminology mid-batch (recorded, allowed in MVP).
- Operator pause/cancel does not abort an in-flight provider call (cost may still accrue for the current item).

### Out of scope (unchanged by this ADR)

Automatic approval, automatic publishing, review assignments, glossary import jobs, WooCommerce crawling, site-wide unrestricted fan-out, render-cache redesign, new AI providers, Elementor, nested block identity, translation version history, import/export.

---

## Provisional approval log

**Not applicable until Gate B is used.** Gate A (explicit Accept of this amendment) is preferred.

When Gate B is used, record here:

| Field | Value |
|---|---|
| Decision maker | |
| Approval date | |
| Exact amendment scope | |
| Accepted residual risks | |
| Mandatory review date | |
| Expiration / revalidation trigger | |

---

## References

- [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md)
- [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md) §11.3
- [0015-review-workflow-and-tm-approval-policy.md](0015-review-workflow-and-tm-approval-policy.md)
- [0014-glossary-platform-lexicon.md](0014-glossary-platform-lexicon.md)
- [0010-provider-agnostic-interface.md](0010-provider-agnostic-interface.md)
