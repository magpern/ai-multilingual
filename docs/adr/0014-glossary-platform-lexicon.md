# ADR-0014 — Glossary as a platform-owned lexicon

## Status

**Accepted** (2026-08-05) — Glossary MVP platform lexicon architecture.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-05  
**Decision:** ADR-0014 Accepted  
**Scope:** Glossary MVP platform lexicon architecture exactly as defined in this ADR and the frozen [GLOSSARY_MVP_IMPLEMENTATION_PLAN.md](../plans/GLOSSARY_MVP_IMPLEMENTATION_PLAN.md).

**Residual risks accepted:**

- Unicode whole-word matching has documented MVP limits
- Glossary ranking changes F11 tier numbering as explicitly documented
- New persistent table and schema migration v4
- Glossary version is a stamp and does not automatically invalidate TM or rendered content
- Warning-only glossary QA may permit terminology deviations

**Review point:** Glossary MVP closure review  

**Revalidation trigger:** schema redesign; new glossary ownership model; multiple glossary providers; automatic TM/glossary synchronization; breaking public API change; provider-owned glossary persistence.

**Implementation gate:** **Closed (implementation complete)** — G1–G7 delivered on `feature/glossary-mvp`; see [GLOSSARY_MVP_VALIDATION_LOG.md](../plans/GLOSSARY_MVP_VALIDATION_LOG.md). Gate A (Accepted) satisfied. Gate B (provisional approval) is **not applicable**.

Canonical plan: [GLOSSARY_MVP_IMPLEMENTATION_PLAN.md](../plans/GLOSSARY_MVP_IMPLEMENTATION_PLAN.md).  
Product context: [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md).

## Context

F11 reserved glossary seams without shipping a lexicon:

- `TranslationBatch::$glossary_fragment` (always `''` today)
- `aiml_tm.glossary_version` / `aiml_translations.glossary_version` (default `0`)
- `SuggestionProvider` pluggability and a documented future glossary ranking tier
- QA extensibility via `QACheck`

Post-v1 product priority is **Glossary MVP**: a curated terminology asset for Biopentra translators and merchants.

Three ownership domains must stay distinct (ADR-0009 already separates TM from segment storage):

| Domain | Role |
|---|---|
| **Glossary** | Curated linguistic asset (approved terms) |
| **Translation Memory** | Observed reuse asset (prior translations) |
| **Store** | Rendered segment persistence |

A naïve design that emits an embedded glossary target term as a full-segment `NormalizedSuggestion` would be unsafe: the approved term is not a complete translation of a longer segment.

F11 frozen ranking ([F11_FROZEN_API.md](../plans/F11_FROZEN_API.md) §4) currently uses tiers 1–5 for TM provenance and tier 6 for AI. Inserting exact-segment glossary between imported TM and fuzzy TM requires an **explicit public-contract amendment**, not a silent renumber.

## Decision

1. **Platform ownership.** Glossary persistence, matching, versioning, fragment generation, and admin CRUD belong to the platform (`GlossaryRepository` / `GlossaryService`). AI providers consume a platform-built fragment only. No OpenAI-specific glossary tables, endpoints, or lookup logic.

2. **New persistent storage.** Introduce table `aiml_glossary` and option `aiml_glossary_version` under Migrator target schema version **4**. Options-only storage is rejected for multi-term CRUD, uniqueness, language pairs, and versioning.

3. **No Store reuse.** Glossary terms are not Store segments.

4. **No automatic TM coupling.** Glossary mutations never rewrite TM or Store rows. TM never auto-creates glossary terms. TM `glossary_version` is a **stamp** of the glossary version at write-back / check time; version skew may drive later QA/revalidation under a future plan, but **does not** auto-invalidate accepted TM or change rendering in MVP.

5. **Suggestion semantics.**
   - **Exact-segment match** (normalized segment source ≡ normalized glossary source term): may emit `NormalizedSuggestion` with `provider_id=glossary`, `target_text` = approved target term, confidence 95.
   - **Embedded-term match** (term occurs inside a longer segment): must **not** emit isolated target terms as segment suggestions. Expose as terminology constraints (AI fragment, QA) via an **internal** `GlossaryTermMatch` application DTO — not a REST/ViewModel/public contract. Public metadata, if any, uses dedicated ViewModels.

6. **Ranking amendment (F11 §2.6).** Ordinary ranking sort keys unchanged (`rank_tier` ASC → `confidence` DESC → `target_text` ASC → `provider_id` ASC). Tier numbers after Glossary:

| Tier | Meaning |
|---|---|
| 1 | Exact TM |
| 2 | Reviewed human TM |
| 3 | Human TM |
| 4 | Imported TM |
| **5** | **Exact-segment Glossary** (new) |
| **6** | Fuzzy TM (**was 5**) |
| **7** | AI (**was 6**) |

Only exact-segment glossary suggestions participate. This renumber of fuzzy/AI is an **approved F11 API amendment** recorded by this ADR.

7. **Orchestration.** `TranslationSuggestionService` remains the sole suggestion orchestrator. `GlossarySuggestionProvider` implements existing `SuggestionProvider`. No second suggestion pipeline. No `GlossaryProviderInterface` in MVP (single source; speculative abstraction rejected).

8. **Permissions.** Dedicated capability `aiml_manage_glossary` (granted to Administrator). Not `manage_options` as the permanent public contract.

9. **QA.** Additive warning code `glossary_term_missing` only; does not block save in MVP.

## Consequences

### Positive

- Terminology consistency without conflating lexicon and memory.
- Safe suggestion UX (no partial targets disguised as full translations).
- Provider-neutral AI enrichment via existing batch contract.
- Clear F11 compatibility story with an explicit ranking amendment.

### Negative / costs

- New table + migrator step + uninstall surface.
- Fuzzy TM and AI tier constants must change (callers/tests updated).
- Matching/normalization must be carefully tested (Unicode, whole-word).

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| Options-only term list | Insufficient for CRUD, uniqueness, language pairs, versioning |
| Store rows as glossary | Blurs segment overlay with lexicon |
| TM rows as glossary | Violates ADR-0009 lifecycle separation; observed ≠ curated |
| Partial `NormalizedSuggestion` for embedded terms | Unsafe; frozen DTO is segment-level |
| `GlossaryProviderInterface` with one implementation | Speculative; deferred as future extension |
| Auto-invalidate TM on glossary bump | Contradicts ADR-0009 selective invalidation; too aggressive for MVP |

## Provisional approval log

**Not applicable** — ADR-0014 is fully **Accepted** (gate A). Gate B provisional approval was not used.

## References

- [GLOSSARY_MVP_IMPLEMENTATION_PLAN.md](../plans/GLOSSARY_MVP_IMPLEMENTATION_PLAN.md)
- [POST_V1_PRODUCT_ROADMAP.md](../plans/POST_V1_PRODUCT_ROADMAP.md)
- [F11_FROZEN_API.md](../plans/F11_FROZEN_API.md)
- [ADR-0009](0009-translation-memory-table.md), [ADR-0010](0010-provider-agnostic-interface.md)
