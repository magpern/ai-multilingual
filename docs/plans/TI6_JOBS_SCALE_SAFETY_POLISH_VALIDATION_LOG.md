# TI.6 — Jobs scale / safety polish — Implementation Validation Log

**Status:** In progress on `feature/ti6-jobs-scale-safety-polish`
**Plan:** [TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md](TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md)
**Implementation baseline (branch tip at TI6.0):** recorded below
**Frozen plan on main:** merge `c6b4564032bbd3d6e402c1564906077b27eb1fcc`; closure `3e796cf1d85010f964542d9e53eed33ac2e085cd`
**TARGET:** 6 (unchanged)
**Exactly-once provider claim:** **Not claimed** (Outcome B)
**TI.7:** not started

---

## TI6.0 — Baseline / evidence lock

| Item | Value |
|---|---|
| Main baseline SHA | `3e796cf1d85010f964542d9e53eed33ac2e085cd` |
| Frozen plan path | `docs/plans/TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md` |
| Schema TARGET | **6** |
| Crash guarantee | Outcome B — persistence-safe, provider may repeat |
| Schema / attempt ledger | None |
| Publication / TI.7 | Out of scope |

### JO dispositions (locked from plan)

JO1 Supported; JO2 Supported; JO3 Partial; JO4 Partial; JO5 Partial (Outcome B); JO6 Supported; JO7 Partial; JO8 Supported; JO9 Supported; JO10 Partial; JO11 Partial; JO12 Partial; JO13 Partial; JO14 Supported; JO15 Supported; JO16 Supported; JO17 Unsupported; JO18 Supported; JO19 Partial; JO20 Partial; JO21 Partial; JO22 Supported; JO23 Deferred; JO24 Deferred.

Non-TM coalescing Deferred. Site billing Deferred. Create auto-wake Deferred.

### Contracts locked

1. **Concurrency:** `BackgroundTranslationConcurrencyPolicy::admit_running`; count only `status=running`; cap 20; reject `concurrency_limit_exceeded` HTTP 409.
2. **Usage:** `provider_requests`, `input_tokens`, `output_tokens`, `usage_known`; TM DIRECT_REUSE = known zero; no status inference; BudgetPolicy must not coerce known-zero 0→1.
3. **Retry-After:** header → error data → ItemResult → delay max(backoff, hint) capped 900s → delayed AS wake.
4. **Crash:** Outcome B — no exactly-once.

### Baseline gates (TI6.0)

Recorded in implementation commits after suite runs.

---

## Work package progress

| WP | Status | Notes |
|---|---|---|
| TI6.0 | In progress | This file |
| TI6.1 | Pending | Usage + budgets |
| TI6.2 | Pending | Retry-After |
| TI6.3 | Pending | TM Jobs path + metrics |
| TI6.4 | Pending | Concurrency + retry/resume + Outcome B |
| TI6.5 | Pending | Assessment surfacing |
| TI6.6 | Pending | Scale |
| TI6.7 | Pending | Failure injection |
| TI6.8 | Pending | Closure |

---

## Acceptance criteria tracker

Frozen ACs 1–74 — evaluated at TI6.8. Target **74/74 PASS**.
