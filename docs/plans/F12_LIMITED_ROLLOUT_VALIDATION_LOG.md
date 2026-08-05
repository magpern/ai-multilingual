# F12 Limited Rollout Validation Log

**Status:** **PASS** — F12 complete; production observation Day-0 validated; merge/tag authorized.
**Branch:** `feature/f12-limited-rollout` → merged to `main`
**Environment:** dev.biopentra.eu
**Observation window:** 2026-08-05 → 2026-08-12 (7 days, PO-approved)

---

## PO-approved production cohort

| Field | Value |
|---|---|
| Cohort post ID | 6321 (`f10-translator-validation`) |
| Deny control | 4638 (`f8-wp6-validation`) |
| Target language | `sv` |
| Active stage | 2 (allowlisted render) |
| Cache | off |
| Operator | bp_manager (ID 1) |

See [F12_PO_DECISION_SHEET.md](F12_PO_DECISION_SHEET.md).

---

## Tier 0 quality gates

| Gate | Result |
|---|---|
| PHPUnit unit | **PASS** — 354 tests, 749 assertions |
| PHPUnit integration | **PASS** — 325 tests, 6910 assertions |
| PHPCS | **PASS** — 0 errors |
| TypeScript / Jest / webpack | **PASS** |
| F9 35-test suite | **Not run** (per F12 policy) |

---

## Production observation (Day-0)

**Evidence:** [F12_PRODUCTION_OBSERVATION_EVIDENCE.json](F12_PRODUCTION_OBSERVATION_EVIDENCE.json)
**Script:** `acceptance/f12-staging/f12-production-observation.sh`
**Date:** 2026-08-05
**Overall:** **PASS**

| Check | Result |
|---|---|
| Stage 1 shadow | PASS |
| Stage 2 allow (6321) | PASS |
| Stage 2 deny (4638) | PASS |
| Kill switch — global render | PASS |
| Kill switch — emergency stop | PASS |
| Rendered false positives | **0** |
| Rollback rehearsal (WP10) | PASS |
| Config export/restore | PASS |

---

## F12 closure

**F12 implementation, staging acceptance, and production observation Day-0 complete.**

| Gate | State |
|---|---|
| F12 merge to `main` | **Authorized** |
| F12 tag | `strategy-f-f12-limited-rollout-merged` |
| F13-ready | **Engineering complete** on `feature/f13-general-availability` — see [F13_GENERAL_AVAILABILITY_VALIDATION_LOG.md](F13_GENERAL_AVAILABILITY_VALIDATION_LOG.md); observation window residual through 2026-08-12 before merge |
| ADR-0013 | **Accepted** (2026-08-05) |

---

## Staging reference (WP10)

[ F12_WP10_STAGING_EVIDENCE.json](F12_WP10_STAGING_EVIDENCE.json) — ephemeral staging posts; not production cohort.
