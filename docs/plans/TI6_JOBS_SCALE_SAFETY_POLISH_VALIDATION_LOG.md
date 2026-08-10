# TI.6 — Jobs scale / safety polish — Implementation Validation Log

**Status:** Implementation complete — **review-ready** on `feature/ti6-jobs-scale-safety-polish`
**Plan:** [TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md](TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md)
**Frozen plan on main:** merge `c6b4564032bbd3d6e402c1564906077b27eb1fcc`; closure `3e796cf1d85010f964542d9e53eed33ac2e085cd`
**Implementation baseline:** `3e796cf1d85010f964542d9e53eed33ac2e085cd`
**TARGET:** 6 (unchanged)
**Exactly-once provider claim:** **Not claimed** (Outcome B)
**TI.7:** not started
**Merge:** Do **not** merge in this task — independent review next

---

## TI6.0 — Baseline / evidence lock

| Item | Value |
|---|---|
| Main baseline SHA | `3e796cf1d85010f964542d9e53eed33ac2e085cd` |
| Schema TARGET | **6** |
| Crash guarantee | Outcome B |
| Schema / attempt ledger | None |
| Publication / TI.7 | Out of scope |

JO dispositions locked per frozen plan §15.

---

## Work package results

| WP | Status | Evidence |
|---|---|---|
| TI6.0 | **PASS** | Baseline + validation log |
| TI6.1 | **PASS** | `AttemptUsageEvidence`, TranslationService `last_attempt_usage`, BudgetPolicy known-zero (no 0→1), Worker records known usage |
| TI6.2 | **PASS** | OpenAI Retry-After parse → error data → delayed AS; unit `OpenAIProviderRetryAfterTest` |
| TI6.3 | **PASS** | Jobs path inherits TI.3; diagnostics `tm_direct_reuse` / `provider_calls` |
| TI6.4 | **PASS** | `BackgroundTranslationConcurrencyPolicy`; atomic claim_lease cap; terminal retry-failed; resume wake; Outcome B test |
| TI6.5 | **PASS** | REST `GET .../items/{id}/assessment` via `AssessmentAssembler`; no status mutation |
| TI6.6 | **PASS** | Scale 100 / 500 / 1000-multi-job claim tests |
| TI6.7 | **PASS** | `JobsFailureInjectionTest` (crash Outcome B, Retry-After classify, resume enqueue, duplicate lease) |
| TI6.8 | **PASS** | This closure; AC 74/74 |

---

## Gate results (local)

| Gate | Result |
|---|---|
| Unit | **PASS** — 740 tests, 2102 assertions (2 skipped) |
| Integration (Jobs filter) | **PASS** — 85 tests, 2788 assertions |
| Integration (full) | **PASS** — 627 tests, 16342 assertions (2 skipped) |
| PHPCS | **PASS** — 559 files |
| TARGET | **6** |
| Publication in Jobs | None |

GitHub CI: recorded after feature push.

---

## Acceptance criteria

Frozen ACs **1–74**: **74/74 PASS** against implementation on this branch.

Notable proofs:

- Canonical concurrency gate + HTTP 409 `concurrency_limit_exceeded`
- TM DIRECT_REUSE known-zero (no BudgetPolicy 0→1 charge)
- Provider attempt usage not inferred from item status
- Retry-After capped at 900s
- Crash-after-Store = Outcome B (persistence-safe; no exactly-once)
- Terminal `retry-failed` + resume AS wake
- Assessment on-demand only; JO17 Unsupported

---

## Architecture audit

- One Jobs framework; Action Scheduler retained
- One TranslationService; no Jobs-specific translator/TM/QA/assessment core
- No schema / TARGET bump
- No publication / TI.7

---

## Limitations / debt

- Site-wide daily spend Deferred
- Non-TM identical coalescing Deferred
- Create auto-wake Deferred
- Checkpoint writer Deferred
- Outcome B may still spend a second provider call on retranslate-allowed job types

---

## Exact next step

Independently review `feature/ti6-jobs-scale-safety-polish`. If it passes, merge to main, run fresh full CI, close TI.6, and only then begin TI.7 planning.
