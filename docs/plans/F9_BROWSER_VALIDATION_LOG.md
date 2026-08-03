# F9 Browser Validation Log — Strategy F engineering acceptance

Operational closure record for Strategy F milestone F9 (browser acceptance) on `dev.biopentra.eu`.

## Environment

| Item | Value |
|---|---|
| Host | `https://dev.biopentra.eu` |
| Branch | `feature/f9-browser-acceptance` |
| Closure basis commit | `91785cd` (harness stabilization) |
| F10 plan on branch | `76d5b5b` (documentation only) |
| WordPress | `7.0.2` |
| PHP | `8.3.32` |
| Playwright | `1.51.0` |
| ADR-0013 | **Proposed** (not promoted by F9) |

## Product acceptance

Strategy F **supported production scope** is accepted by engineering review.

| Area | Result | Evidence |
|---|---|---|
| F1–F8 merged to `main` | PASS | Prior milestone tags and [F8_CLI_VALIDATION_LOG.md](F8_CLI_VALIDATION_LOG.md) |
| PHPUnit unit | PASS | Green on F9 branch @ `91785cd`; not rerun for closure (no code changed) |
| PHPUnit integration | PASS | Green on F9 branch @ `91785cd`; not rerun for closure |
| PHPCS | PASS | Green on F9 branch @ `91785cd`; not rerun for closure |
| F8 operational validation | PASS | [F8_CLI_VALIDATION_LOG.md](F8_CLI_VALIDATION_LOG.md) @ `55ee542` |
| Migration validation | PASS | MG-1..MG-3 exercised during F9 harness runs; idempotence confirmed in product paths |
| Frontend overlay validation | PASS | FR-1 variants (paragraph, heading, button) pass; Chromium button case stabilized @ `91785cd` |
| Kill switch validation | PASS | F8 HTTP smoke + F9 admin/frontend flag paths |
| Language routing validation | PASS | LS-1/LS-2 prefix routing validated in completed product assertions |
| Store scoping validation | PASS | TR-5 cross-post isolation validated in completed product assertions |
| `rendered_false_positive` | **0** | Completed product assertions across matrix runs; spike/F5/F8 baseline unchanged |
| Known production defects (supported scope) | **None** | All defects found during F9 (D1–D14) resolved in merged harness/product commits |

**No known production defect remains within the supported F9 scope** (post/page, `core/paragraph`, `core/heading`, `core/button`, Strategy F flags on dev).

## Browser acceptance history

### Latest complete Tier 3 matrix (@ `7b39063`)

| Field | Value |
|---|---|
| Executed | `2026-08-03T06:10:21Z` |
| Orchestrator wall clock | ~165 min |
| Tests executed | 35 |
| Passed | 32 |
| Failed | 3 |
| Classification | COMPLETE TEST FAILURE (harness — not product) |

**Failing tests (all Chromium desktop):**

| Test | Symptom | Failure phase |
|---|---|---|
| `frontend-language` FR-1: f9-fr-btn renders Swedish overlay | `page is closed` at `bootstrapProductionPost` | Before product assertions |
| `frontend-language` TR-1/TR-2: store segment_key and stale after edit | `page is closed` at bootstrap | Before product assertions |
| `uuid-matrix` edit: text change preserves UUID | `page is closed` at bootstrap | Before product assertions |

Para/heading FR-1 variants, Firefox/WebKit/mobile projects, and 29 other matrix cells **passed** on this run.

Concise local failure summary preserved under `docs/plans/f9-failure-evidence/7b39063-32of35/` (not committed — large traces remain local/ignored).

### Harness stabilization (@ `91785cd`)

Commit `91785cd` addressed Playwright page lifecycle and orchestration timing (`helpers/lifecycle.ts`, `resolveF9Page()`, WP-CLI pool in global-setup, phase timing in `run-f9-acceptance.sh`).

**Tier 2 targeted stabilization (post-fix):**

| Target | Protocol | Result |
|---|---|---|
| FR-1 button overlay | 3 consecutive `--grep` runs + combined cycle | PASS |
| TR-1/TR-2 stale/segment_key | 3 consecutive `--grep` runs + combined cycle | PASS |
| UUID edit preserves UUID | 3 consecutive `--grep` runs + combined cycle | PASS |

Two additional combined Tier 2 cycles across the three failing paths: **PASS**.

**Formal 35/35 Tier 3 PASS was not achieved** after `91785cd`. No final all-green Tier 3 matrix was executed or recorded.

## Engineering closure decision

**F9 ENGINEERING ACCEPTANCE: PASS**

Formal 35/35 Tier 3 Playwright PASS was not achieved. The remaining gap is accepted as **test-infrastructure debt** because:

1. The outstanding Tier 3 failures were isolated to Playwright lifecycle/bootstrap paths (`page is closed` before product assertions).
2. The affected product paths passed repeated Tier 2 stabilization @ `91785cd`.
3. No known production defect remains within the supported F9 scope.
4. PHPUnit, integration, PHPCS, F8 operational validation, and completed browser product assertions provide sufficient engineering confidence to proceed to F10 planning.

ADR-0013 remains **Proposed**. F9 does not promote ADR.

## Test-infrastructure debt

| ID | Description |
|---|---|
| TID-1 | No final 35/35 Tier 3 PASS recorded |
| TID-2 | Long serialized Playwright lifecycle instability on Chromium desktop under full matrix load |
| TID-3 | Excessive fixture/bootstrap repetition (444 WP-CLI compose spins on Tier 3 @ `7b39063`) |
| TID-4 | Full suite runtime (~165 min orchestrator) disproportionate to ordinary development value |
| TID-5 | Reusable suite-level fixture corpus deferred (WIP stashed locally — not part of closure) |
| TID-6 | Full Playwright matrix remains release/milestone gate only; requires explicit operator approval |

## Ongoing test policy

| Tier | Scope | When |
|---|---|---|
| **Tier 0 (default)** | PHPUnit, integration, PHPCS, WP-CLI | Every F10 work package and ordinary merge |
| **Tier 1/2** | Targeted Playwright `--grep` / single spec | Editor, overlay, or cross-browser behavior under change only |
| **Tier 3** | Full 35-test matrix via `run-f9-acceptance.sh` | Milestone/release gate only; **explicit operator approval required** |

**F10 policy:** No automatic full Playwright run during F10 work packages. See [TEST_STRATEGY.md](../TEST_STRATEGY.md) and [STRATEGY_F_F9_BROWSER_ACCEPTANCE.md](STRATEGY_F_F9_BROWSER_ACCEPTANCE.md) §25.

## Quality gates (closure commit)

| Gate | Result | Notes |
|---|---|---|
| Closure documentation | PASS | This log + plan updates |
| PHPUnit / PHPCS rerun | Skipped | Documentation-only closure; last green @ `91785cd` |
| Full Playwright Tier 3 | Not run | Waived per engineering closure decision |

## Operator sign-off

- [x] Product acceptance scope reviewed
- [x] Tier 3 failure classification reviewed (harness, pre-assertion)
- [x] Tier 2 post-fix stabilization reviewed @ `91785cd`
- [x] Test-infrastructure debt recorded (TID-1..TID-6)
- [x] ADR-0013 promotion explicitly deferred
- [ ] ADR-0013 human promotion (future gate — not part of F9)

## Final result

**F9 ENGINEERING ACCEPTANCE: PASS** @ closure commit on `feature/f9-browser-acceptance`

**Next milestone:** [STRATEGY_F_F10_TRANSLATOR_WORKSPACE.md](STRATEGY_F_F10_TRANSLATOR_WORKSPACE.md) — Translator Workspace MVP (F10). Limited rollout renumbered to F11.
