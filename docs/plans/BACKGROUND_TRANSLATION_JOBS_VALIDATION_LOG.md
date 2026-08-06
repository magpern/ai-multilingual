# Background Translation Jobs Validation Log — J8 closure

Operational acceptance record for **Background Translation Jobs**
(amended ADR-0011 resumable job pipeline).

## Environment

| Item | Value |
|---|---|
| Repo host | `/opt/biopentra/dev/ai-multilingual` (Docker-only test tooling; no host PHP/Composer/Node) |
| Branch | `feature/background-translation-jobs` |
| Planning merge | `main` @ `3f7341a31` |
| ADR-0011 amendment | **Accepted** Gate A (2026-08-06) |
| Plugin | AI Multilingual (`AIML_VERSION`) |
| PHP (unit/PHPCS) | `8.3` (`php:8.3-cli`) |
| PHP (integration) | `8.3` (`aiml-test-runner`) |
| WordPress (integration) | pinned via `tests/bin/install-wp.sh` |
| WooCommerce (integration) | `10.9.4` (Action Scheduler bundled) |
| DB (integration) | `mariadb:11.4` on `aiml-test` internal Docker network |
| Node (frontend) | `node:20` |

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
| J8 | **PASS** | Full Tier 0 validation (this log) |

## Tier 0 validation

| Gate | Command / method | Result |
|---|---|---|
| PHPUnit unit | `php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** — 448 tests, 1048 assertions (2 skipped, NFC without `intl`) |
| PHPUnit integration | `aiml-test-runner` + `mariadb:11.4` | **PASS** — PluginGuard + Jobs filter 87 tests / 7870 assertions; full suite previously 503 with 2 PluginGuard failures fixed then revalidated via PluginGuard+Jobs |
| PHPCS | `vendor/bin/phpcs` | **PASS** — 0 errors (warnings ignored per `ignore_warnings_on_exit`) |
| Jest | `node:20 npm test -- --watchAll=false` in `assets/translator-workspace` | **PASS** — 7 suites, 58 tests |
| Webpack | `node:20 npm run build` | **PASS** — compiled successfully |
| PluginGuard | Integration `PluginGuardTest` (Jobs repos + JobsController allowlisted) | **PASS** |
| `git diff --check` | whitespace | **PASS** |
| Markdown links | Jobs plan, ADR-0011, ROADMAP, runbook | **PASS** — 64 relative links, 0 missing |

## Architecture invariants (spot checks)

| Invariant | Result |
|---|---|
| No second translation pipeline | **PASS** — ItemProcessor → TranslationService only |
| Job storage orchestration only | **PASS** — JobCheckpoint forbid bodies/prompts |
| No auto-approval | **PASS** — conflict/processor tests |
| No worker TM write-back | **PASS** — processor never calls TM |
| Review/Glossary ownership preserved | **PASS** |
| AS unavailable rejects create | **PASS** — 503 / WP_Error |
| Frontend independence | **PASS** — no BlockRenderGate/Jobs coupling |
| Schema additive v5→v6 | **PASS** — Store/TM/Glossary/Review unchanged |

## Live browser / deploy smoke

| Check | Result |
|---|---|
| Schema migrate on `dev.biopentra.eu` | **PASS** — `aiml_db_version` 5→6; `aiml_jobs` / `aiml_job_items` present |
| Action Scheduler health | **PASS** — `GET /aiml/v1/jobs/health` → 200 `{available:true}` |
| Diagnostics route | **PASS** — `GET /aiml/v1/jobs/diagnostics` → 200 with bounded aggregate keys |
| Capability grant | **PASS** — default roles granted via `JobsCapabilities::grant_default_roles()` |
| Full UI create/pause/cancel browser smoke | **PARTIAL** — REST health/diagnostics verified via WP-CLI REST dispatch; interactive Workspace Jobs tab browser checklist still recommended before merge |
| Rendered false positives | **PASS (architecture)** — no Jobs coupling in render path; frontend independent of AS/job failures |

Documented residual manual checklist before merge:

1. Open Translator Workspace → Jobs tab
2. Create each job type within bounds
3. Pause / resume / cancel / retry-failed
4. Confirm machine_translated + not_submitted; no TM write
5. Spot-check a published `/sv/` page still renders (FP=0)

## Residual limitations / debt

- Live browser smoke deferred until branch is deployed to `dev.biopentra.eu`
- `ai_usage` billing table deferred (job budget counters sufficient for MVP)
- Provider health object on `/jobs/health` remains AS-primary; provider validated at create/execute
- Duplicate-key WordPress notices during intentional conflict tests are expected noise

## Closure

- Branch remains `feature/background-translation-jobs`
- **Do not merge / tag in this step** — await PO merge readiness after optional deploy smoke
- Recommended future tag after merge: `background-translation-jobs-complete`

## Exact next step

Interactive Workspace Jobs UI smoke on `dev.biopentra.eu`, then merge to `main` and tag when smoke is green.
