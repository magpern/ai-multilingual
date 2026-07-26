# ADR-0009 — Translation memory in its own table

## Status
Accepted (Milestone 0). Implemented in Milestone 3.

## Context
A catalogue repeats itself: "Add to cart", "Free shipping", "Out of stock" and
hundreds of product-copy fragments recur across objects. Sending each occurrence
to a provider separately costs money and produces inconsistent wording for
identical source text.

## Decision
Reusable translations live in `aiml_tm`, keyed by
`(normalized source hash, source language, target language, context)` — not in
the segment table. The two have different lifecycles, different query shapes
(lookup by content rather than by object) and different retention rules; sharing
a table would blur all three.

Reuse policy:

- Exact match on hash, language pair and **exact context** is always reusable.
- An empty-context match is reusable only if the source passes an ambiguity
  gate: at least 25 characters and containing a space. Short strings are where
  reuse goes wrong — "Free" as a price and "Free" as an adjective translate
  differently, and "Order" is a noun or a verb depending on where it sits.
- Human-approved entries always win over machine ones. Machine entries are
  reusable within the same context only, and only when the setting allows it.
- A glossary change does not invalidate the memory wholesale. An entry whose
  recorded glossary version is behind is checked against the rules that changed;
  it is skipped only if its source actually contains an affected term.
- `forced` and `never_translate` glossary rules are enforced after any reuse, so
  a reused translation cannot launder a rule violation.

Only short `plain` and `html` segments are written to memory. Whole block trees
are effectively unique and would bloat the table without ever hitting. Slugs are
never stored.

## Consequences
- Provider cost falls and terminology stays consistent.
- Reuse is visible in the editor, so a wrong reuse is correctable rather than
  invisible.
- The ambiguity gate is a heuristic; the threshold is filterable.
