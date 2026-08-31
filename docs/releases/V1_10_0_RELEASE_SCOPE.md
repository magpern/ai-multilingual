# Universal Multilingual v1.10.0 — Release Scope Audit

**Status:** IN PREPARATION (feature branch)  
**Date:** 2026-08-31  
**Feature branch:** `feature/add-deepseek-provider`  
**Baseline main:** 1.9.0 identity rebrand  
**Schema:** Migrator `TARGET = 8` (**unchanged** — no migration)  
**Decision:** **RELEASE VERSION DECISION: 1.10.0** (minor — operator-facing AI provider capability)

## A. Included train

| Item | Contribution |
|---|---|
| DeepSeek provider | `DeepSeekProvider` + factory registration; OpenAI-compatible Chat Completions at `api.deepseek.com` |
| Per-provider settings | Nested `ai_providers[{openai,deepseek}]` with model, encrypted key, temperature, max_tokens |
| OpenAI generation controls | Configurable temperature / max_tokens (replaces hardcoded `0.2`) |
| Legacy migration | Shared `ai_model` / `ai_api_key_encrypted` copy into `ai_providers.openai` on sanitize |

## B. Must NOT claim as shipped

| Item | Disposition |
|---|---|
| Thinking / reasoning_effort UI | Fixed `thinking: disabled` for translation; no operator toggle in 1.10.0 |
| Anthropic / Gemini / OpenRouter | Not this release |
| Schema TARGET 9 | Not this release |
| Public Extension/Integration API expansion | Unchanged |
| Tag / GitHub Release / deploy | **Separate authorization** |

## C. Schema / API / upgrade

| Item | Status |
|---|---|
| `Migrator::TARGET` | **8** |
| New migration in v1.10.0 | **None** |
| Public Extension API | Unchanged |
| Public Integration API | Unchanged |
| AI settings shape | Additive nested `ai_providers`; legacy keys retained for migration |
| Activation / uninstall | Unchanged |

## D. Package

| Item | Value |
|---|---|
| Artifact name | `universal-multilingual-1.10.0.zip` |
| Build | `bin/build-zip.sh` |
| Audit | `bin/audit-zip.sh` |

## E. Tag / release boundary

```
feature implementation (this branch)
≠
release preparation merge
≠
tag / GitHub Release / deployment
```
