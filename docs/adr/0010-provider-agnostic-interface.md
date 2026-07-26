# ADR-0010 — Provider-agnostic AI interface, OpenAI first

## Status
Accepted (Milestone 0). Implemented in Milestone 3.

## Context
Only OpenAI will ship initially. The risk is letting its request shape become
the abstraction — `messages`, `response_format`, its particular error codes —
so that adding a second provider means rewriting the pipeline rather than adding
a class.

## Decision
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

## Consequences
- The initial product exposes one provider without the architecture assuming
  one.
- Provider-specific behaviour (structured output support, token accounting
  quirks) has to be normalized inside each implementation.
