# TI.6 — Jobs scale / safety polish — Implementation Validation Log

**Status:** **Complete** on `main`
**Plan:** [TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md](TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md)
**Frozen plan on main:** merge `c6b4564032bbd3d6e402c1564906077b27eb1fcc`; planning closure `3e796cf1d85010f964542d9e53eed33ac2e085cd`
**Implementation baseline:** `3e796cf1d85010f964542d9e53eed33ac2e085cd`
**Reviewed feature HEAD:** `6e30ade9677695ffbc815bdeba0b99af63dfa20a`
**Merge commit:** `7286156ed977200907f9416d6af9022517291e76`
**TARGET:** 6 (unchanged)
**Exactly-once provider claim:** **Not claimed** (Outcome B)
**TI.7:** not started (planning next)

---

## Closure summary

TQ.0–TI.5 Complete. **TI.6 Complete.**

Shipped:

- Truthful provider usage budgets (`AttemptUsageEvidence`)
- Retry-After end-to-end with delayed AS wake (wake stops after retryable backoff)
- Bounded concurrency (`MAX_CONCURRENT_RUNNING=20`) with named-lock serialization on claim
- Terminal retry-failed + resume wake; admit-before-mutate
- TM-aware operational accounting; on-demand TI.5 assessment (JO17 Unsupported)
- Crash-after-Store Outcome B (persistence-safe; provider may repeat)

Independent review fixed before merge:

- removed `max(1, …)` failed-attempt coercion
- budget hard-stop forbids provider when already exhausted (TM/skip still proceed)
- concurrency admit before resume/retry-failed mutation
- MySQL `GET_LOCK` around running-cap transitions
- Retry-After not bypassed by same-wake reclaim

---

## Work package results

| WP | Status |
|---|---|
| TI6.0–TI6.8 | **PASS** |
| AC 1–74 | **74/74 PASS** (post-review) |

## Gates (authoritative)

| Gate | Result |
|---|---|
| Unit | **PASS** — 740 tests, 2104 assertions (2 skipped) |
| Integration | **PASS** — 631 tests, 17431 assertions (2 skipped) |
| PluginGuard | **PASS** — 17 tests, 10298 assertions |
| PHPCS | **PASS** — 559 files |
| Quality / baseline | **PASS** |
| Feature CI | **PASS** — run `31431939887` (after SSL rerun) @ `6e30ade96` |
| Fresh main CI | **PASS** — run `31432155143` @ `7286156ed` |

## Exact next step

Begin the definitive TI.7 planning process from the closed TI.6 main baseline. Do not implement TI.7 until its plan has been independently reviewed and frozen on main.
