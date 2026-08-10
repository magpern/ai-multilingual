# ADR-0010 — Provider-agnostic AI interface, OpenAI first

## Status

**Accepted** (Milestone 0). Implemented in Milestone 3.

| Field | Value |
|---|---|
| **Original acceptance** | Milestone 0 |
| **TI.2 amendment date** | 2026-08-10 |
| **TI.2 amendment status** | **Accepted** for TI.2 Bounded Translation Context (planning freeze) |
| **Related plan** | [TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md](../plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md) |
| **Supersedes** | None — original decision retained; amended Decision adds optional context |

## Context (original)

Only OpenAI will ship initially. The risk is letting its request shape become
the abstraction — `messages`, `response_format`, its particular error codes —
so that adding a second provider means rewriting the pipeline rather than adding
a class.

## Decision (original — retained)

`AIProviderInterface` is defined in terms of the domain, not any vendor:
connection test, model listing, and translating a batch of segments. A
`TranslationBatch` carries source and target locale, prompt profile and version,
glossary fragment and the segments. A `ProviderResult` carries translated
segments, token counts, the model actually used, and an error classified as
retryable, permanent or a validation failure.

Nothing OpenAI-shaped appears in the interface. Adding Anthropic, Gemini,
OpenRouter or a local model is a new class plus a settings entry.

Settings, jobs, segments and usage rows all record `provider`, `model`,
`prompt_profile` and `prompt_version`, so a history mixing providers stays
interpretable and a cost report can attribute spend correctly.

The API key is encrypted at rest with a salt-derived key, overridable by a
constant that is never persisted, never returned to JavaScript, and redacted at
the logger boundary rather than at each call site.

## Amended Decision — TI.2 Bounded Translation Context (2026-08-10)

TI.2 extends the domain batch contract without making it vendor-shaped:

1. **Optional typed context.** `TranslationBatch` may carry an optional
   `TranslationContext` value object (schema-versioned). When absent (`null`),
   providers behave as before (plus any packaging improvements that apply to
   glossary/source framing alone).

2. **Provider-agnostic.** `TranslationContext` is a domain DTO. Providers map it
   into their own request shapes. `TranslationService` must not format
   OpenAI `messages`. Providers that do not understand context must ignore it
   safely.

3. **Not Store identity.** Context is not part of segment identity, PluginIdentity,
   or Integration API contracts.

4. **Not `source_hash`.** Context must not be folded into `Store::source_hash` or
   `is_stale` in TI.2. Automatic freshness remains source-text-based. Context-aware
   invalidation requires a separate future ADR if productized.

5. **Schema versioning.** `TranslationContext.schema_version` (and prompt profile
   version bumps used with it) must be recorded on quality generation fixtures so
   TQ.0 candidates remain reproducible.

6. **Bounds and allowlisting.** Context is size-capped and built only from
   allowlisted public/content sources. Arbitrary postmeta, customer/order data,
   credentials, and full-page dumps are out of contract.

## Consequences

- The initial product exposes one provider without the architecture assuming
  one.
- Provider-specific behaviour (structured output support, token accounting
  quirks) has to be normalized inside each implementation.
- **TI.2:** Adding bounded field/object context does not require a second
  translator, Store redesign, or TARGET bump. Future providers inherit the same
  optional context field.
