# F11 Frozen Public APIs

Architecture Freeze Review record for Strategy F milestone F11 (Translation Memory & AI Assistance). Future milestones must treat these surfaces as frozen unless an ADR or plan revision is approved.

**Branch:** `feature/f11-translation-memory-ai`  
**Canonical plan:** [STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md](STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md)

---

## 1. Service boundaries

| Component | Responsibility | Must not |
|---|---|---|
| `TranslationSuggestionService` | Sole orchestrator for TM + AI suggestions; merge, rank, diagnostics | Persist Store rows; call OpenAI APIs directly |
| `SuggestionProvider` | Normalized suggestion sources (`tm`, `ai`, …) | Leak vendor or TM row shapes into REST |
| `TranslationMemoryService` / `TMRepository` | Exact/fuzzy lookup; human-approved write-back | Write segment translations |
| `QAEngine` + `QACheck` | Source-independent modular QA | Encode AI-origin-specific rules |
| `ProviderRegistry` + `AIProviderInterface` | Resolve active AI provider + capabilities | Leak vendor types into Workspace/REST/React |
| `WorkspaceService` | Thin facade: load/save/batch/suggest/qa | Call TM/AI providers directly |
| `WorkspaceController` | REST adapters → WorkspaceService only | Contain business logic |

---

## 2. REST contracts (additive `aiml/v1/workspace`)

Namespace unchanged from F10. Additive fields only.

| Method | Route | Notes |
|---|---|---|
| GET | `/{post_id}/segments` | Response segments include optional `meta.suggestions`, `meta.qa` |
| POST | `/{post_id}/segments/{segment_key}/suggest` | AI suggest; **does not** persist Store |
| POST | `/{post_id}/suggestions/accept` | Batch accept exact TM → save path |
| POST | `/{post_id}/qa` | Batch QA report (read-only) |
| POST | `/{post_id}/translate` | Persist via Store; no TM write-back on machine persist |
| POST | `/{post_id}/segments` / batch | QA gate on save when `qa_block_on_error` |

Admin provider routes (settings): `/aiml/v1/providers/active`, test-connection, models — credentials never returned to JS in cleartext.

---

## 3. Suggestion ranking (§2.6)

Deterministic order: tier ascending, then confidence descending, then target text. Tiers 1–5 TM provenance; tier 6 AI. Duplicate target text keeps higher tier.

---

## 4. Prompt profile IDs (frozen)

`translate`, `improve`, `rewrite`, `shorten`, `formal`, `casual` — `PromptProfileRegistry::VERSION = '1'`.

---

## 5. QA issue codes (frozen)

| Code | Check |
|---|---|
| `placeholder_mismatch` | PlaceholderCheck |
| `variable_mismatch` | VariableCheck |
| `number_mismatch` | NumberCheck |
| `html_tag_mismatch` | HTMLCheck |
| `unsupported_markup` | UnsupportedMarkupCheck |
| `length_ratio` | LengthRatioCheck |
| `punctuation_delta` | PunctuationCheck |
| `whitespace_anomaly` | WhitespaceCheck |
| `empty_translation` | EmptyTranslationCheck |

`meta.qa` shape: `{ issues: [{ code, severity, message, … }], error_count, warning_count, info_count }`.

---

## 6. Translation Memory contracts

| Rule | Detail |
|---|---|
| Table | `aiml_tm` (ADR-0009) |
| Provenance `origin` | `human` \| `ai` \| `import` \| `legacy` |
| Write-back | human / ai_accepted / import only — **not** machine persist (ADR-F11-004) |
| Exact confidence | 100 (context) / 95 (global reuse with ambiguity gate) |
| Fuzzy | Normalized confidence in [60, 94]; threshold default 85 |

---

## 7. Provider capabilities

`ProviderCapabilities` flags: `translate`, `improve`, `rewrite`, `shorten`, `formal`, `casual`, `batch`. Workspace adapts via flags — no vendor-name branching in UI/controllers.

---

## 8. Review outcome

| Check | Result |
|---|---|
| Public interfaces match plan | **PASS** |
| REST additive / backwards compatible | **PASS** |
| Provider abstraction intact | **PASS** (`WorkspaceController` / `WorkspaceService` have no OpenAI references) |
| TM write-back policy | **PASS** (integration + unit) |
| This frozen API doc committed | **PASS** |
