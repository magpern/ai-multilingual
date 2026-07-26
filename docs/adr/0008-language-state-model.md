# ADR-0008 — Single three-state language model, no fallback chains

## Status
Accepted (Milestone 0).

## Context
An earlier draft carried two booleans, `is_active` and `is_published`. Four
combinations exist; only three are meaningful, and "inactive but published" has
no defensible behaviour.

Separately, a `fallback_id` column was proposed so a missing translation could
fall through to another language.

## Decision
One `status` column with three states:

- `disabled` — not a route at all; translations retained.
- `preview` — routable only for a user holding `aiml_translate`; hidden from
  switchers and, later, from hreflang and sitemaps.
- `published` — public.

Transitions: `preview ⇄ published`, `preview ⇄ disabled`, and
`published → disabled`. A disabled language returns through `preview` before it
can be published, so nothing goes live unlooked-at. New languages start in
`preview`. The default language is always published.

**`fallback_id` is removed.** The default language is the implicit fallback when
a segment is missing.

Resolution and request state are also split into two classes —
`LanguageResolver` is a pure function, `LanguageContext` is mutable request
state with a switch stack. They have different lifecycles and merging them to
save a class would put mutable state inside something meant to have none.

## Consequences
- Every state has one meaning and the illegal ones cannot be represented.
- Arbitrary language-to-language fallback chains are deferred until there is a
  complete policy for mixed-language pages, canonical URLs, hreflang,
  indexability, archives and transactional emails.
