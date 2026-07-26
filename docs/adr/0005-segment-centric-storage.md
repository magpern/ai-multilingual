# ADR-0005 — Segment-centric storage

## Status
Accepted (Milestone 0).

## Context
Storing one translated blob per field makes any source edit invalidate the whole
field, and gives an AI provider no way to be told "only this paragraph changed".
It also makes reusing an identical string across the catalogue impossible.

## Decision
The unit of storage is a segment: one addressable piece of one field of one
object in one language. Identity is
`(source_type, source_id, segment_hash, language_id)`, where `segment_hash` is
`sha1(field_key ␟ segment_key)`.

Hashing rather than indexing the raw `(field_key, segment_key)` pair keeps the
unique key around 131 bytes instead of roughly a kilobyte, which matters because
it is the hot index. Both columns are still stored plainly for querying and
debugging.

Segment keys are chosen for stability across edits: `post_title`,
`block:0.innerHTML:2`, `elementor:a1b2c3:heading`, `string:<sha1>`.

Milestone 1 writes only `field`-kind segments; the schema already accommodates
the rest.

## Consequences
- Every write is an upsert, so a retried job is a no-op rather than a duplicate.
- Incremental retranslation and translation memory become possible.
- Gutenberg has no native block IDs, so `block:N` is positional and will drift
  when blocks are reordered. A content-similarity rematch pass on save is
  required before Milestone 2 commits to the grammar.
