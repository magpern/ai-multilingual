# ADR-0010 — Provider-agnostic AI interface, OpenAI first

## Status

**Accepted** (Milestone 0). Implemented in Milestone 3.

| Field | Value |
|---|---|
| **Original acceptance** | Milestone 0 |
| **TI.2 amendment date** | 2026-08-10 |
| **TI.2 amendment status** | **Accepted** for TI.2 Bounded Translation Context |
| **TI.3 amendment date** | 2026-08-10 |
| **TI.3 amendment status** | **Accepted** and **implemented** for TI.3 Translation Memory Intelligence — Complete on `main` |
| **Related plans** | [TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md](../plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md); [TI3_TRANSLATION_MEMORY_INTELLIGENCE_IMPLEMENTATION_PLAN.md](../plans/TI3_TRANSLATION_MEMORY_INTELLIGENCE_IMPLEMENTATION_PLAN.md) |
| **Supersedes** | None — original decision retained; amended Decision adds optional context and optional TM examples |

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
OpenRouter, DeepSeek, or a local model is a new class plus a settings entry.
As of v1.10.0 the product ships OpenAI and DeepSeek with per-provider
encrypted keys and generation settings (model, temperature, max_tokens).

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

## Amended Decision — TI.3 Translation Memory Intelligence (2026-08-10)

TI.3 extends the same optional `TranslationContext` / `ContextItem` contract
without a second examples pipeline or vendor-shaped batch fields:

1. **Optional TM examples.** Allowlisted `ContextItem` may include type
   `tm_example` carrying bounded prior translation pairs for instruction-only
   use. Examples are optional; when absent, providers behave as under TI.2.

2. **Relevance-gated.** A TM row may become a `tm_example` only under the
   deterministic relevance classes frozen in the TI.3 plan. Same language-pair
   + human-approved alone is insufficient. Vector/fuzzy retrieval is out of
   contract for this amendment.

3. **Bounded.** TM examples share the existing TI.2 optional context budget
   (and the TI.3 per-example caps). Source text must not be truncated to
   preserve examples. Drop priority prefers dropping TM examples before
   `field_semantic`.

4. **Provider-agnostic.** Providers render `tm_example` items as instruction /
   example context, not as source content, and must ignore unknown item types
   safely.

5. **Not Store identity.** TM examples are not part of segment identity,
   PluginIdentity, Integration API, or `Store::source_hash` / `is_stale`.

6. **Not a parallel batch field.** Do not add a separate examples list on
   `TranslationBatch`; examples travel as `ContextItem`s inside optional
   `TranslationContext`.

## Consequences

- The initial product exposes one provider without the architecture assuming
  one.
- Provider-specific behaviour (structured output support, token accounting
  quirks) has to be normalized inside each implementation.
- **TI.2:** Adding bounded field/object context does not require a second
  translator, Store redesign, or TARGET bump. Future providers inherit the same
  optional context field.
- **TI.3:** Relevance-gated TM examples reuse the TI.2 context carrier. Direct
  TM reuse short-circuits the provider on the shared `TranslationService` path
  and is outside this ADR’s batch contract (it never reaches `translate_batch`).
  No TARGET bump; no second examples pipeline.
