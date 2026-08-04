# F12 Limited Rollout Validation Log

**Status:** F12 implementation and staging acceptance complete. Production observation pending.
**Branch:** `feature/f12-limited-rollout`
**HEAD (validation):** `638ab61e0` (post cache integration + PHPCS + staging)
**Environment:** dev.biopentra.eu

---

## Tier 0 quality gates (full repository)

| Gate | Command | Result | Count / duration |
|---|---|---|---|
| PHPUnit unit | `docker run … php:8.3-cli vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** | 354 tests, 749 assertions, ~0.1s |
| PHPUnit integration | `aiml-test-runner vendor/bin/phpunit -c phpunit-integration.xml.dist` | **PASS** | 325 tests, 6910 assertions, ~110s |
| PHPCS | `docker run … php:8.3-cli vendor/bin/phpcs` | **PASS** | 0 errors (2 warnings, ignored on exit) |
| TypeScript / Jest | `npm test` in `assets/translator-workspace/` | **PASS** | 22 tests, 4 suites |
| webpack build | `npm run build` in `assets/translator-workspace/` | **PASS** | compiled successfully |
| `git diff --check` | `git diff --check` | **PASS** | no conflict markers |
| Doc relative links | `acceptance/f12-staging/validate-doc-links.py` (manual) | **6 pre-existing broken links** outside F12 scope (see note) |
| F9 35-test suite | — | **Not run** (per F12 policy) |

**Doc link note:** Pre-existing broken links in `STRATEGY_F_PRODUCTION_IMPLEMENTATION.md` and `docs/spike-s5/IMPLEMENTATION_LOG.md` — not introduced by F12; repair deferred.

---

## WP7 — Performance evidence (dev baseline, approval thresholds pending)

Captured 2026-08-04 on dev.biopentra.eu via `acceptance/f12-staging/wp7-performance.php`. **No PO SLOs invented.**

| Surface | Cold median (ms) | Warm median (ms) | p95 (ms) | Sample size | Memory delta | Approval threshold | Evidence |
|---|---|---|---|---|---|---|---|
| Policy evaluation | 0.46 | 0.001 | 0.001 | 50 | ~0 B | **Pending PO** | dev WP-CLI |
| Outside-cohort deny path | 0.014 | 0.002 | 0.002 | 50 | ~0 B | **Pending PO** | dev WP-CLI |
| Shadow / source path | — | — | — | — | — | **Pending PO** | WP10 staging PASS |
| Inside-cohort translated render | — | — | — | — | — | **Pending PO** | WP10 staging PASS |
| Workspace load vs F11 | — | — | — | — | — | **Pending PO** | deferred |
| Metrics flush | 0.039 | 0.0002 | 0.039 | 10 | ~0 B | **Pending PO** | dev WP-CLI |
| Metrics rollup | — | — | — | — | — | **Pending PO** | not exercised |
| Cache-disabled path | — | — | — | — | — | **Pending PO** | default off |
| Cache hit/miss | — | — | — | — | — | **Pending PO** | not activated |

Reference: [F11_PERFORMANCE_BASELINE.md](F11_PERFORMANCE_BASELINE.md)

---

## Cache activation decision

| Item | State |
|---|---|
| Cache integrated in render path (WP8) | **Yes** — wired via `RolloutRenderCacheBridge`; default **off** |
| Kill-switch precedence | Tested (unit + integration + WP10 kill switches) |
| Invalidation hooks | `RenderCacheInvalidationService` + `RolloutCacheInvalidationHooks` |
| Measured GO for activation | **Pending** — no PO approval |
| `render_cache_enabled` on dev | **false** (restored after WP10) |

---

## WP10 — Staging validation (dev.biopentra.eu)

**Evidence file:** [F12_WP10_STAGING_EVIDENCE.json](F12_WP10_STAGING_EVIDENCE.json)
**Script:** `acceptance/f12-staging/wp10-staging.sh`
**Date:** 2026-08-04T21:19:56Z
**Overall:** **PASS**

### Staging-only operational values (not PO approval)

| Field | Value |
|---|---|
| Test post slug | `f12-staging-rollout-test` |
| Test post IDs (run) | 6338 (primary), 6339 (deny control) |
| Target language | `sv` (language_id 2) |
| Allowlist | Single post ID only |
| Observation interval | Short functional validation only |

### Results summary

| Check | Result |
|---|---|
| Stage 0 — source only | PASS |
| Stage 1 — shadow (metrics, source to visitor) | PASS |
| Stage 2 — allowlisted translated render | PASS |
| Stage 2 — non-allowlisted source fallback | PASS |
| Kill switch — global frontend render off | PASS |
| Kill switch — rollout disabled | PASS |
| Kill switch — emergency stop | PASS |
| Config restore increments policy_version | PASS |
| Rendered false positives | **0** |
| Rollback rehearsal (export → emergency → restore) | PASS |
| Provider outage (frontend unaffected) | **Assumed PASS** — NullAI default; not re-tested in WP10 run |

Post-validation cleanup: test posts deleted; rollout config restored to safe defaults (`rollout_render_enabled=false`, stage 0, empty allowlist, cache off).

---

## Rollback rehearsal

Executed via WP10 script using production operator services:

1. Config export (`wp aiml rollout config export`)
2. Emergency stop (`RolloutEmergencyService`)
3. Source frontend verified (curl)
4. Snapshot restore via `RolloutConfigurationRepository::restore()`
5. Safe defaults restored

---

## F12 closure gate

**Not PASS for merge/tag** — production observation window and PO sign-off pending.

### Exact status (per plan)

**F12 implementation and staging acceptance complete.**
**Production observation pending.**

---

## Unresolved PO values

- Production Stage 1–3 post IDs
- Target languages for production cohort
- Observation-window duration
- SEV-2 threshold
- AI daily warning / hard limits
- Cache activation GO
- Final capability-role mapping
- Named operator

See [F12_PO_DECISION_SHEET.md](F12_PO_DECISION_SHEET.md).
