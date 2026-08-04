# F11 Translation Memory & AI Assistance Validation Log — dev.biopentra.eu

Operational acceptance record for Strategy F milestone F11 (TM + AI suggestions + QA + batch productivity).

## Environment

| Item | Value |
|---|---|
| Host | `https://dev.biopentra.eu` (`169.58.7.116`) |
| Branch | `feature/f11-translation-memory-ai` |
| Commit | tag `strategy-f-f11-tm-ai-complete` |
| Plugin | AI Multilingual `0.1.0` (`AIML_VERSION`) |
| Plugin mount | `/opt/biopentra/dev/ai-multilingual` → `wp-content/plugins/ai-multilingual` |
| WordPress | `7.0.2` |
| PHP | `8.3.32` (WordPress container) |
| WP-CLI user | `bp_manager` (ID `1`, `--user=1`) |
| Languages | `en` (default), `sv` (published) |
| Validation page | Post ID `6321`, slug `f10-translator-validation` (workspace smoke reused) |
| Strategy F flags | attr, uuid, extraction, frontend rendering = **on** |

## Entry gate (§14)

| Gate | Required state | Result |
|---|---|---|
| F10 PASS | `F10_TRANSLATOR_VALIDATION_LOG.md` + tag `strategy-f-f10-translator-complete` | **PASS** |
| PHPUnit / PHPCS | Green on implementation branch | **PASS** (closure gates below) |
| F10 limitations reviewed | §15 acknowledged | **PASS** |
| Provider credentials | Staging config documented (ADR-0010); NullAI when unconfigured | **PASS** — NullAI default; OpenAI via encrypted vault when configured |

## Quality gates (Tier 0 / G3)

| Gate | Command | Result |
|---|---|---|
| PHPUnit unit | `vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** — 326 tests, 693 assertions |
| PHPUnit integration | `vendor/bin/phpunit -c phpunit-integration.xml.dist` | **PASS** — 306 tests, 5682 assertions |
| PHPCS | `vendor/bin/phpcs` | **PASS** — 0 errors (warnings ignored on exit) |
| TypeScript | `npx tsc --noEmit` in `assets/translator-workspace/` | **PASS** |
| Jest | `npm test` in `assets/translator-workspace/` | **PASS** — 22 tests |
| webpack build | `npm run build` in `assets/translator-workspace/` | **PASS** |
| git diff --check | `git diff --check` | **PASS** |

## Browser validation (milestone closure)

**Suite:** `acceptance/f10-browser` — F10 workspace smoke (F11 extends the same workspace UI; no separate F11 Playwright suite in the plan).  
**Full F9 35-test matrix:** **NOT RE-RUN** (closed under F9 engineering acceptance; harness cost/risk unchanged).

| Test | Result |
|---|---|
| Workspace load + post context + status summary | **PASS** |
| Manual edit and save | **PASS** |
| Preview opens public `/sv/` route | **PASS** |
| Bulk translate reports not configured | **PASS** |

**Count:** 4/4 passed in ~40s (Chromium, headless) after auth cookie refresh. Earlier attempts hit intermittent `net::ERR_NETWORK_CHANGED` (Cloudflare/host); final closure run is green.

## REST / workspace smoke (dev.biopentra.eu)

| Step | HTTP | Evidence |
|---|---|---|
| GET `/aiml/v1/workspace/6321/segments?language=sv` | 200 | 2 segments; `meta.suggestions` + `meta.qa` present |

## TM smoke

| Check | Evidence | Result |
|---|---|---|
| Exact match | `TranslationMemoryServiceIntegrationTest::test_exact_match_returns_confidence_100`; `WorkspaceTmSuggestionsTest` | **PASS** |
| Fuzzy match | `test_fuzzy_returns_ranked_candidates`; unit fuzzy confidence scaling | **PASS** |

## Provider smoke

| Check | Evidence | Result |
|---|---|---|
| NullAI when unconfigured | `WorkspaceProviderTest`, `ProviderCapabilitiesTest` | **PASS** |
| OpenAI via registry + vault | `ProviderFrameworkTest` (HTTP-injected; no live key required) | **PASS** |
| Suggest does not persist | `WorkspaceSuggestionsRestTest::test_suggest_endpoint_does_not_persist_translation` | **PASS** |
| Translate persists | `test_translate_still_attempts_persist_path` | **PASS** |

## QA smoke

| Check | Evidence | Result |
|---|---|---|
| Placeholder mismatch blocks save | `WorkspaceQARestTest::test_save_blocked_on_placeholder_error` | **PASS** |
| Same checks regardless of origin | `QAEngineTest::test_same_checks_regardless_of_origin_context` | **PASS** |
| `meta.qa` on GET | `WorkspaceQARestTest::test_get_segments_includes_meta_qa` | **PASS** |

## TM write-back

| Check | Evidence | Result |
|---|---|---|
| Machine persist excluded (policy) | `TranslationMemoryServiceTest::test_machine_persist_is_not_write_back_eligible`; integration write-back test | **PASS** |
| Human / ai_accepted eligible (policy) | `test_human_write_back_persists_and_machine_is_skipped`; `test_ai_accepted_write_back_uses_ai_origin` | **PASS** |
| Accept TM exact → Store save | `WorkspaceBatchProductivityTest::test_accept_tm_exact_saves_match` | **PASS** |
| Workspace save invokes `write_back` / `record_usage` | `WorkspaceTmWriteBackTest`; `save_segment` sync path | **PASS** |

## SuggestionService / ranking / capabilities

| Check | Evidence | Result |
|---|---|---|
| WorkspaceService delegates suggestions | Composition + `WorkspaceService` calls only `$this->suggestions->*` | **PASS** |
| Deterministic ranking §2.6 | `TranslationSuggestionServiceTest` tier/confidence/text order | **PASS** |
| Capabilities adapt without vendor branching | `ProviderCapabilitiesTest`, `ProviderFrameworkTest`; no OpenAI refs in controller | **PASS** |

## Batch partial success

| Check | Evidence | Result |
|---|---|---|
| F10 partial contract unchanged | Existing batch tests + WP10 productivity routes return per-item results | **PASS** |

## Architecture Freeze Review (G10)

| Check | Result |
|---|---|
| Public interfaces | **PASS** — see [F11_FROZEN_API.md](F11_FROZEN_API.md) |
| REST contracts additive | **PASS** |
| Provider abstraction | **PASS** |
| TM contracts | **PASS** (policy + save-path write-back F11.1) |
| Frozen API doc committed | **PASS** |

## Acceptance criteria (§11)

| ID | Criterion | Evidence | Result |
|---|---|---|---|
| AC-1 | Exact TM match across posts | TM + workspace suggestion tests | **PASS** |
| AC-2 | Fuzzy TM with normalized confidence | TM service unit + integration | **PASS** |
| AC-3 | AI translate persists; suggest does not | `WorkspaceSuggestionsRestTest` | **PASS** |
| AC-4 | Six prompt profiles callable | `PromptProfileRegistryTest` | **PASS** |
| AC-5 | Placeholder mismatch blocking for all origins | QA unit + REST | **PASS** |
| AC-6 | Stale `source_hash` → 409 | F10 `WorkspaceConflictTest` (unchanged) | **PASS** |
| AC-7 | Batch partial success unchanged | Batch + productivity tests | **PASS** |
| AC-8 | First production provider via interface; NullAI fallback | Provider framework + workspace tests | **PASS** |
| AC-9 | ViewModels only | REST shape tests; no raw Store/TM rows | **PASS** |
| AC-10 | Machine persist does not write TM; accepted save does | Policy + `WorkspaceTmWriteBackTest` | **PASS** |
| AC-11 | SuggestionService + providers mediate suggestions | Architecture + unit tests | **PASS** |
| AC-12 | Deterministic ranking | `TranslationSuggestionServiceTest` | **PASS** |
| AC-13 | Capability discovery adapts UI | Provider capabilities tests + settings | **PASS** |
| AC-14 | Modular QAEngine; no AI-specific QA | `QAEngineTest` | **PASS** |
| AC-15 | This validation log PASS | This file | **PASS** |

## Milestone closure gates (§20)

| Gate | Result |
|---|---|
| G1 §11 ACs | **PASS** |
| G2 Validation log PASS | **PASS** |
| G3 PHPUnit + PHPCS | **PASS** |
| G4 SuggestionService owns suggestions | **PASS** |
| G5 QA source-independent | **PASS** |
| G6 TM write-back policy | **PASS** (policy + save-path wiring F11.1) |
| G7 Provider swappable + capabilities | **PASS** |
| G8 Deterministic ranking | **PASS** |
| G9 Tag `strategy-f-f11-tm-ai-complete` | Applied at closure |
| G10 Architecture Freeze Review | **PASS** |
| G11 Definition of Done | **PASS** |

## Operator sign-off

| Field | Value |
|---|---|
| Validator | Cursor agent (autonomous F11 closure) |
| Date | 2026-08-03 |
| Final result | **PASS** — D1 write-back wiring completed in F11.1 |

## Tag

`strategy-f-f11-tm-ai-complete` on branch `feature/f11-translation-memory-ai` (merge to `main` remains operator action).

**Next milestone:** F12 — Limited rollout (operational only: flags, cohort, telemetry, monitoring, performance, caching). **No** new translator features in F12. See [F11_MERGE_READINESS_REPORT.md](F11_MERGE_READINESS_REPORT.md).
