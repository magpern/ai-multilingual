# Background Translation Jobs Validation Log — J8 closure

Operational acceptance record for **Background Translation Jobs**
(amended ADR-0011 resumable job pipeline).

## Environment

| Item | Value |
|---|---|
| Repo host | `/opt/biopentra/dev/ai-multilingual` (Docker-only test tooling; no host PHP/Composer/Node) |
| Branch | `feature/background-translation-jobs` |
| Planning merge | `main` @ `3f7341a31` |
| Pre-merge HEAD (implementation) | `0c26edcc5` then `e10eabe30` (pause observe fix) |
| ADR-0011 amendment | **Accepted** Gate A (2026-08-06) |
| Plugin | AI Multilingual (`AIML_VERSION`) |
| PHP (unit/PHPCS) | `8.3` (`php:8.3-cli`) |
| PHP (integration) | `8.3` (`aiml-test-runner`) |
| WordPress (integration) | pinned via `tests/bin/install-wp.sh` |
| WooCommerce (integration) | `10.9.4` (Action Scheduler bundled) |
| DB (integration) | `mariadb:11.4` on `aiml-test` internal Docker network |
| Node (frontend) | `node:20` |
| Live site | `https://dev.biopentra.eu` |

## Entry gate (J0)

| Gate | Required state | Result |
|---|---|---|
| Amended ADR-0011 Accepted | Gate A | **PASS** |
| Frozen plan | `BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md` | **PASS** |
| Glossary + Review complete | Tags on `main` | **PASS** |
| Schema before J1 | Migrator TARGET was 5 | **PASS** |

## Work package status

| WP | Result | Notes |
|---|---|---|
| J0 | **PASS** | ADR Accepted; implementation gate open |
| J1 | **PASS** | TARGET=6; `aiml_jobs` / `aiml_job_items`; repositories |
| J2 | **PASS** | Lifecycle, leases, idempotency, batches |
| J3 | **PASS** | Worker + ItemProcessor + Scheduler |
| J4 | **PASS** | Retry, budgets, AS health |
| J5 | **PASS** | REST, CLI, capabilities, ViewModels |
| J6 | **PASS** | Workspace Jobs UI |
| J7 | **PASS** | Audit, diagnostics, retention, runbook |
| J8 | **PASS** | Full Tier 0 + live smoke (this log) |

## Tier 0 validation (final pre-merge)

| Gate | Command / method | Result |
|---|---|---|
| PHPUnit unit | `php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** — 448 tests, 1048 assertions (2 skipped) |
| PHPUnit integration | `aiml-test-runner` + `mariadb:11.4` | **PASS** — 503 tests, 10365 assertions (2 skipped) |
| Jobs filter | `--filter Jobs` | **PASS** — 70 tests, 428 assertions |
| PHPCS | `vendor/bin/phpcs` | **PASS** — 0 errors |
| TypeScript | `npx tsc --noEmit` in `assets/translator-workspace` | **PASS** |
| Jest | `npm test -- --watchAll=false` | **PASS** — 7 suites, 58 tests |
| Webpack | `npm run build` | **PASS** — compiled successfully |
| PluginGuard | Included in full integration suite | **PASS** |
| `git diff --check` | whitespace | **PASS** |
| Markdown links | Jobs plan, ADR-0011, ROADMAP, POST_V1, runbook, validation log | **PASS** — 58 relative links, 0 missing |

## Live interactive / REST smoke (`dev.biopentra.eu`)

Runner: `acceptance/jobs/smoke-dev.php` via WP-CLI `wp eval-file`.

| Check | Result |
|---|---|
| Schema target 6 + tables | **PASS** |
| Capabilities grant | **PASS** |
| AS health | **PASS** |
| REST health / diagnostics / list | **PASS** |
| Create translate_missing + idempotency | **PASS** |
| Run sync + progress | **PASS** (provider unavailable → failed as expected without configured provider on job) |
| Create translate_selected | **PASS** |
| Pause → paused → resume → cancel | **PASS** (after pause observe-at-boundary fix) |
| Retry-failed endpoint | **PASS** (409 when no failed items) |
| Bulk create + batch status | **PASS** |
| Capability denial (subscriber) | **PASS** (403) |
| Store / TM / Glossary / Review tables intact | **PASS** |
| Audit privacy + created event | **PASS** |
| Cleanup retention run | **PASS** |
| Smoke summary | **35/35 PASS** |

### Browser Workspace Jobs UI

| Check | Result |
|---|---|
| Translator Workspace loads | **PASS** |
| Jobs tab present with Translate + Review | **PASS** |
| AS health banner | **PASS** — “Action Scheduler is available.” |
| Filters (status / language / batch) + Refresh + Create job | **PASS** |
| Batch grouping visible | **PASS** |
| Review queue tab still loads | **PASS** |
| Public `/sv/` HTTP 200, no fatal | **PASS** |
| Rendered false positives | **PASS** — 0 |

## Pre-merge fix included

`POST /jobs/{id}/pause` and `wp aiml jobs pause` now observe `requested_action` at the operator boundary (same pattern as cancel). Lease claim requires `requested_action=none`, so queued pause requests previously never reached `paused` and resume returned 409.

## Architecture invariants (spot checks)

| Invariant | Result |
|---|---|
| No second translation pipeline | **PASS** — ItemProcessor → TranslationService only |
| Job storage orchestration only | **PASS** — JobCheckpoint forbid bodies/prompts |
| No auto-approval | **PASS** |
| No worker TM write-back | **PASS** |
| Review/Glossary ownership preserved | **PASS** |
| AS unavailable rejects create | **PASS** |
| Frontend independence | **PASS** |
| Schema additive v5→v6 | **PASS** |

## Residual limitations / debt

- `ai_usage` billing table deferred (job budget counters sufficient for MVP)
- Provider health object on `/jobs/health` remains AS-primary; provider validated at create/execute
- Duplicate-key WordPress notices during intentional conflict tests are expected noise
- Full provider-success path on live depends on configured provider credentials for the job

## Closure

- Merged to `main` @ `b308138c4e2b6e835d3aeec8d508696d90fbe597`
- Annotated tag `background-translation-jobs-complete` points at the merge commit
- Platform v1 product track (Glossary + Review + Background Jobs) complete for controlled production deployment
