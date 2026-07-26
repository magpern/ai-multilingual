# ADR-0011 — Resumable staged job pipeline with bounded checkpoints

## Status
Accepted (Milestone 0). Implemented in Milestone 3.

## Context
"Translate this object" is not one operation. It extracts segments, normalizes
and hashes them, consults memory, batches the remainder, calls a provider,
validates the response, saves, updates memory and invalidates caches. Treated as
an opaque unit, any failure means redoing all of it — including the paid part.

## Decision
The job is a ten-stage pipeline, with `stage` and a `checkpoint` recorded on the
job row. Stage 5, the provider call, records the index of the last batch
successfully received, so a resume never re-sends a batch that has already been
paid for. Saving is an upsert keyed by segment identity, so replaying it is a
no-op.

`aiml_jobs` is the source of truth; Action Scheduler is the trigger and
scheduler only. Action Scheduler's arguments are not queryable for the progress
UI and it purges completed actions after thirty days, which would destroy the
history the cost report needs.

Concurrency is guarded by a nullable unique `lock_key` holding
`{source_type}:{source_id}:{language_id}`, cleared when the job finishes.
MariaDB permits repeated NULLs under a unique index, so finished jobs never
collide while at most one active job can hold a given identity.

The checkpoint is resume state, not an archive. It stores stage markers, batch
indexes and segment IDs — identifiers and counters, never content. Raw provider
payloads are never persisted unless debug mode is explicitly enabled, and then
to the rotated logger rather than the database. The column is `TEXT` with a 16 KB
soft cap; exceeding it degrades to coarser resume granularity and logs that it
did, rather than truncating silently. Checkpoints compact to NULL on success.

## Consequences
- Failures are diagnosable and retries are safe and cheap.
- Job rows stay small.
- On the target host, `DISABLE_WP_CRON` with a five-minute system cron means
  worst-case dispatch latency is five minutes unless Action Scheduler's loopback
  runner works through the CDN — which must be measured, not assumed.
