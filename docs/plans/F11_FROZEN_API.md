# F11 Frozen Public APIs

Architecture Freeze Review record for Strategy F milestone F11 (Translation Memory & AI Assistance). Future milestones must treat these surfaces as frozen unless an ADR or plan revision is approved.

**Branch:** `feature/f11-translation-memory-ai`  
**Tag:** `strategy-f-f11-tm-ai-complete`  
**Canonical plan:** [STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md](STRATEGY_F_F11_TRANSLATION_MEMORY_AND_AI_ASSISTANCE.md)  
**Merge readiness:** [F11_MERGE_READINESS_REPORT.md](F11_MERGE_READINESS_REPORT.md)

---

## 1. Service boundaries (frozen)

| Component | Path | Responsibility | Must not |
|---|---|---|---|
| `AIProviderInterface` | `src/Translation/AI/AIProviderInterface.php` | Vendor-neutral translate/suggest/capabilities | Leak HTTP/vendor shapes upward |
| `ProviderRegistry` | `src/Translation/AI/ProviderRegistry.php` | Resolve active provider | Branch on vendor in callers |
| `ProviderCapabilities` | `src/Translation/AI/ProviderCapabilities.php` | Capability flags for UI adaptation | Encode vendor names |
| `PromptProfileRegistry` | `src/Translation/AI/PromptProfileRegistry.php` | Six frozen profile IDs | Rename existing IDs |
| `ResponseValidator` | `src/Translation/AI/ResponseValidator.php` | Structural AI response validation | Replace `QAEngine` |
| `TranslationMemoryService` | `src/Translation/Memory/TranslationMemoryService.php` | Exact/fuzzy lookup; write-back **policy** | Write Store segment rows |
| `TMRepository` | `src/Translation/Memory/TMRepository.php` | `aiml_tm` persistence | Bypass origin validation |
| `SuggestionProvider` | `src/Workspace/Suggestion/SuggestionProvider.php` | Pluggable suggestion source | Return raw TM/Store rows |
| `NormalizedSuggestion` | `src/Workspace/Suggestion/NormalizedSuggestion.php` | Canonical suggestion DTO | Change core field names |
| `TranslationSuggestionService` | `src/Workspace/TranslationSuggestionService.php` | **Sole** suggestion orchestrator | Persist translations; call OpenAI |
| `QAEngine` / `QACheck` | `src/Workspace/QA/` | Source-independent modular QA | AI-origin-specific rules |
| `WorkspaceService` | `src/Workspace/WorkspaceService.php` | Thin facade for REST | Call TM/AI providers directly |
| `WorkspaceController` | `src/Rest/WorkspaceController.php` | REST → WorkspaceService only | Business logic / vendor types |

**Composition root:** `src/Plugin.php` wires providers into `TranslationSuggestionService`. OpenAI construction is confined to `ProviderFactory` / `Providers/OpenAIProvider`.

---

## 2. Suggestion DTO (`NormalizedSuggestion`) — frozen fields

| Field | Type | Rule |
|---|---|---|
| `provider_id` | string | Stable id (`tm`, `ai`, …) |
| `target_text` | string | Suggested translation |
| `confidence` | float | 0–100 |
| `rank_tier` | int | Deterministic §2.6 tier |
| `metadata` | object | Additive display hints only |

REST exposes suggestions via `meta.suggestions[]` using `to_array()` of this DTO. Core fields must not rename; metadata keys may be added.

---

## 3. REST contracts (additive `aiml/v1`)

### Workspace (F10 preserved + F11 additive)

| Method | Route | F11 change |
|---|---|---|
| GET | `/workspace/posts` | Unchanged |
| GET | `/workspace/{post_id}/segments` | **Additive** `meta.suggestions`, `meta.qa` |
| GET | `/workspace/{post_id}/status` | Unchanged |
| GET | `/workspace/{post_id}/preview-url` | Unchanged |
| POST | `/workspace/{post_id}/segments/{segment_key}` | QA gate; response may include `meta.qa` |
| POST | `/workspace/{post_id}/segments/batch` | QA gate; partial-success unchanged |
| POST | `/workspace/{post_id}/translate` | Unchanged contract; NullAI → `aiml_ai_not_configured` |
| POST | `/workspace/{post_id}/segments/{segment_key}/suggest` | **New** — suggest only; no Store persist |
| POST | `/workspace/{post_id}/suggestions/accept` | **New** — exact TM accept → save_batch |
| POST | `/workspace/{post_id}/qa` | **New** — read-only batch QA |

Header `X-AIML-Workspace-Api-Version: 1` unchanged. ViewModels only — no raw Store/TM rows.

### Providers (admin)

| Method | Route | Notes |
|---|---|---|
| GET | `/providers/active` | Capabilities; never returns cleartext keys |
| POST | `/providers/test-connection` | Active provider only |
| GET | `/providers/models` | Capability-gated |

---

## 4. Ranking policy (§2.6) — frozen

Order: `rank_tier` ascending → `confidence` descending → `target_text` ascending.  
Tiers 1–5: TM provenance; tier 6: AI. Duplicate target text keeps higher tier.

---

## 5. Prompt profile IDs — frozen

`translate` · `improve` · `rewrite` · `shorten` · `formal` · `casual`  
`PromptProfileRegistry::VERSION = '1'`. New profiles may only be **added**.

---

## 6. QA issue codes — frozen

| Code | Default severity (plan) | Implementation note |
|---|---|---|
| `placeholder_mismatch` | error | Matches plan — **blocks save** |
| `variable_mismatch` | error | Matches plan |
| `number_mismatch` | warning | Matches plan |
| `html_tag_mismatch` | error | **Deviation:** plain-text target (zero tags) → **warning** so F10-style saves work |
| `broken_formatting` | error | Emitted by `HTMLCheck`; additive code |
| `unsupported_markup` | warning | Matches plan |
| `length_ratio` | warning | Matches plan |
| `punctuation_delta` | warning | Matches plan |
| `whitespace_anomaly` | warning | Matches plan |
| `empty_translation` | error (plan) | **Deviation:** implemented as **warning**; blank clears filtered out of block path |

New codes may only be **added** via new `QACheck` classes.

---

## 7. Translation Memory contracts — frozen

| Rule | Detail |
|---|---|
| Table | `aiml_tm` (ADR-0009); Migrator `TARGET=2` |
| Provenance `origin` | `human` \| `ai` \| `import` \| `legacy` |
| Write-back eligibility | `human`, `ai_accepted`, `import` only — **never** `machine` |
| Accept TM + save | No new TM row — `record_usage` only (policy) |
| Exact confidence | 100 (context) / 95 (global + ambiguity gate) |
| Fuzzy | Confidence ∈ [60, 94]; default threshold 85 |

**Implementation note (pre-Review-Workflow, superseded below):** `WorkspaceService::save_segment()` invoked `TranslationMemoryService::write_back()` after a successful Store persist for eligible origins (`human`, `ai_accepted`, `import`). Accepting an existing TM hit uses `record_usage()` only (`tm_accepted`). Machine persist goes through `TranslationService` → Store and never calls this path.

**Additive amendment (ADR-0015 §7, Review Workflow R5):** when Review Workflow is enabled, *new*-content write-back moves from save-time to approval-time. `WorkspaceService::save_segment()` no longer calls `write_back()`; it still calls `record_usage()` immediately for an accepted exact TM hit (`tm_accepted` — not new content, unaffected by this amendment). `WorkspaceService::approve_review()` calls the new `write_back_tm_on_approval()` exactly once on a real `pending` → `approved` transition (never on an idempotent duplicate approve), reusing the same `TranslationMemoryService::write_back()` and its existing eligibility rules (format exclusions unchanged). Pending and rejected translations never write TM; rejecting never deletes historical TM. Machine-origin content (`status = machine_translated`) remains excluded unless a human has since edited it. See [ADR-0015](../adr/0015-review-workflow-and-tm-approval-policy.md) and [REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md §10](REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md#10-translation-memory-interaction) for the full policy.

---

## 8. Provider capabilities — frozen flags

`translate` · `improve` · `rewrite` · `shorten` · `formal` · `casual` · `batch`

Workspace/settings adapt via flags — no vendor-name branching in `WorkspaceService`, REST workspace controllers, ViewModels, or React.

**Allowed vendor touchpoints:** settings `provider_id` option list, `ProviderFactory`, `Providers/OpenAIProvider` only.

---

## 9. Architecture Freeze Review outcome

| Check | Result |
|---|---|
| Public interfaces match plan | **PASS** (with severity deviations documented §6) |
| REST additive / F10 compatible | **PASS** |
| No OpenAI leakage outside provider boundary | **PASS** |
| `TranslationSuggestionService` sole suggestion orchestrator | **PASS** |
| `WorkspaceService` does not call SuggestionProviders / OpenAI | **PASS** |
| TM write-back **policy** machine-excluded | **PASS** |
| TM write-back **wired on save** | **PASS** at F11 close; **amended** by ADR-0015 (Review Workflow R5) — new-content write-back now wired on approval via `WorkspaceService::write_back_tm_on_approval()`; `record_tm_usage_after_save()` still records usage for accepted exact TM hits at save-time |
| This frozen API doc committed | **PASS** |
