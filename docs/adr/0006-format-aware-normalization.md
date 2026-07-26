# ADR-0006 — Format-aware, versioned source normalization

## Status
Accepted (Milestone 0).

## Context
Source hashing exists to answer one question: did the meaning of the source
change? It must therefore normalize away differences that cannot change
meaning — and only those.

A single rule cannot do this. Collapsing runs of whitespace is harmless in a
title, destructive inside `<pre>`, and meaning-changing inside a JSON string
literal. Reordering JSON keys changes nothing; reordering a JSON array changes
everything.

## Decision
Normalization dispatches on the segment's `text_format`:

- `plain` — line endings, non-breaking spaces and whitespace runs all collapse.
- `html` — line endings and the several spellings of a non-breaking space are
  canonicalized; whitespace is never collapsed, and the non-breaking space
  becomes the codepoint rather than an ordinary space because the difference is
  visible.
- `json` — parsed and re-encoded canonically with sorted keys; invalid JSON is
  hashed byte-for-byte rather than "repaired".
- `code` — line endings only; indentation is meaning.
- `slug` — trimmed and lowercased; values arrive already canonicalized through
  `sanitize_title()`.

The algorithm is versioned. Every row records the `norm_version` that produced
its hash, old implementations stay reachable, and rows re-hash lazily on their
next write.

## Consequences
- Cosmetic edits do not create review work; real edits always do.
- A future fix to the rules cannot mark an entire translated site stale
  overnight.
- Normalization must stay free of WordPress so the rules can be unit-tested
  without a bootstrap, which is why slug canonicalization happens at the write
  site rather than here.
