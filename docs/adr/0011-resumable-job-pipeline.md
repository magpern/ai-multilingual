# ADR-0011 — Resumable staged job pipeline with bounded checkpoints

## Status

**Accepted** (2026-08-06) — Materially amended ADR-0011 Accepted by Product Owner. Gate A satisfied. J1+ implementation authorized.

| Field | Value |
|---|---|
| **Original acceptance** | Milestone 0 (Accepted design baseline) |
| **Amendment date** | 2026-08-06 |
| **Amendment acceptance date** | 2026-08-06 |
| **Implementation status** | **Authorized — not yet implemented** (J1+ on `feature/background-translation-jobs`) |
| **Supersedes status claim** | ~~Implemented in Milestone 3~~ — false at amendment time; implementation proceeds under this Accepted amendment |
| **Decision maker** | Product Owner |
| **Decision** | Amended ADR-0011 **Accepted** |
| **Scope** | Action Scheduler trigger-only architecture; orchestration state in `aiml_jobs` and `aiml_job_items`; `BackgroundTranslationItemProcessor` as sole per-item application boundary; job/item/requested-action state separation; nullable unique `active_lock_key` lease model; schema target 6; Review Workflow owns approval; workers never write Translation Memory; Glossary resolved through GlossaryService; bounded retries, concurrency, budgets, and retention — exactly as in **Amended Decision** below and [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md) |
| **Residual risks accepted** | Action Scheduler becomes a runtime dependency for Jobs; nullable unique active-lock behavior depends on supported MariaDB semantics; queued work may become stale before execution; provider usage estimates may differ from actual usage; cancellation is observed only at safe item boundaries; Glossary drift is allowed and recorded; completed operational records are retained temporarily and later cleaned |
| **Mandatory review point** | Background Translation Jobs milestone closure |
| **Revalidation triggers** | Replacement of Action Scheduler; alternate queue engine; separate lock table; automatic approval; worker TM write-back; job-owned translation bodies; provider-specific worker pipeline; dynamic site-wide fan-out; material schema redesign; breaking public API change |

**Implementation gate:** **Open** — Gate A (Accepted) satisfied. Gate B (provisional approval) is **not applicable**.

Canonical plan: [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md) — architecture frozen; **ADR-0011 amendment Accepted — J1 implementation authorized**.  
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

**Not applicable** — Amended ADR-0011 is fully **Accepted** (Gate A). Gate B provisional approval is not used.

---

## References

- [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md)
- [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md) §11.3
- [0015-review-workflow-and-tm-approval-policy.md](0015-review-workflow-and-tm-approval-policy.md)
- [0014-glossary-platform-lexicon.md](0014-glossary-platform-lexicon.md)
- [0010-provider-agnostic-interface.md](0010-provider-agnostic-interface.md)
