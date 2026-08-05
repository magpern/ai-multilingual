# F13 General Availability Validation Log

**Status:** **PASS** — F13 complete; merged to `main`; production observation window closed successfully.  
**Branch:** `feature/f13-general-availability` → `main`  
**Environment:** `dev.biopentra.eu`  
**Date:** 2026-08-05  
**Canonical plan:** [STRATEGY_F_F13_GENERAL_ROLLOUT.md](STRATEGY_F_F13_GENERAL_ROLLOUT.md)

---

## Gates

| Gate | Result |
|---|---|
| F13.0 entry gate | **PASS** (residual observation window through 2026-08-12) — [F13_ENTRY_GATE.md](F13_ENTRY_GATE.md) |
| F13.1 ADR disposition | **Accepted** — [F13_ADR_DISPOSITION.md](F13_ADR_DISPOSITION.md) |
| Feature coverage frozen | **PASS** — `paragraph` / `heading` / `button` only |

---

## Tier 0

| Gate | Result |
|---|---|
| PHPUnit unit | **PASS** — 363 tests, 775 assertions |
| PHPUnit integration | **PASS** — full suite green (Rollout subset 16/16) |
| PHPCS | **PASS** — 0 errors |
| F9 35-test Playwright suite | **Not run** (per F13 policy) |

---

## Tier 1

| Check | Result |
|---|---|
| `RolloutPolicyService` allowlist branch (GA off) | **PASS** |
| `GeneralAvailabilityCohortProvider` GA branch | **PASS** |
| Schema v1→v2 migrator | **PASS** |
| Stage 6 bounds | **PASS** |
| Kill switches dominate GA | **PASS** |
| `SUPPORTED_BLOCKS` unchanged | **PASS** |

---

## Tier 2 (staging)

**Evidence:** [F13_GA_STAGING_EVIDENCE.json](F13_GA_STAGING_EVIDENCE.json)  
**Script:** `acceptance/f13-staging/f13-ga-staging.sh`

| Check | Result |
|---|---|
| Supported blocks frozen | PASS |
| Stage 2 deny non-allowlisted | PASS |
| Stage 6 GA post A | PASS |
| Stage 6 GA post B | PASS |
| Stage 6 language deny | PASS |
| Kill global | PASS |
| Kill rollout | PASS |
| Kill emergency | PASS |
| Rollback rehearsal | PASS |
| Rendered false positives | **0** |
| **Overall** | **PASS** |

Config restored to F12 observation defaults after run.

---

## Tier 3 (targeted smoke)

**Evidence:** [F13_TIER3_BROWSER_SMOKE_EVIDENCE.json](F13_TIER3_BROWSER_SMOKE_EVIDENCE.json)  
**Script:** `acceptance/f13-staging/f13-tier3-smoke.sh`  
**Scope:** F12-proven blocks under GA — **not** F9 35-suite.

| Check | Result |
|---|---|
| Stage 2 regression (cohort 6321) | PASS |
| Stage 6 GA paragraph render | PASS |
| Blocks frozen | PASS |
| FP | **0** |
| **Overall** | **PASS** |

---

## Performance (evidence only)

**Evidence:** [F13_PERFORMANCE_BASELINE.json](F13_PERFORMANCE_BASELINE.json)  
No invented SLOs. Policy evaluation median ≈ 0.0016 ms (limited and GA paths).

---

## AC checklist

| AC | Result |
|---|---|
| AC-1–AC-13 | **PASS** (covered by unit/integration/staging/ADR/docs) |

---

## Closure

| Gate | State |
|---|---|
| F13 merge to `main` | **Authorized / complete** |
| F13 tags | `strategy-f-f13-general-availability-merged` / `strategy-f-f13-general-availability-complete` |
| Observation window | Closed successfully (PO-approved window complete) |
| Next | F14 — create `feature/f14-block-expansion` from updated `main` |
