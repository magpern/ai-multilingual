# ADR-0007 — Hash semantics and deferred revision history

## Status
Accepted (Milestone 0).

## Context
Two questions need answering about a stored translation: has the source moved
underneath it, and has the translation itself been changed? They are different
questions and need different data.

## Decision
`source_hash` is a hash of the normalized source. It detects meaningful source
drift and nothing else.

`translation_hash` is a hash of the stored translation exactly as stored. It is
an **integrity and revision marker**, not an edit detector: the plugin owns the
write path, so an editor saving through the UI updates text and hash together
and they always agree afterwards. What it catches is change from outside —
direct SQL, a partial restore, replication damage. What records that a human
edited a segment is the `status` column plus `translated_by`.

Provenance (`status`) and freshness (`is_stale`) are separate columns, because a
translation can be machine-produced and current, or reviewed and stale. A single
enum would lose one of the two facts. Freshness is materialized so that "show me
everything needing review" is one indexed query.

Comparison against remembered historical states — `last_machine_hash`,
`reviewed_hash` — is **deferred to a Milestone 3 migration**. Milestone 1 ships
no AI and no review workflow, so those columns could be neither exercised nor
tested in the milestone that created them.

## Consequences
- Until that migration, protecting manual work rests on `status` alone, which is
  sufficient because there is no automatic writer to protect against yet.
- The Milestone 3 "safe to overwrite" test becomes
  `translation_hash === last_machine_hash`.
