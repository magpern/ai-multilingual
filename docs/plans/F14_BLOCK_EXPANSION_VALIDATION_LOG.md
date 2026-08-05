# F14 Block Expansion Validation Log

**Status:** **PASS** — F14 complete; merged to `main`.  
**Branch:** `feature/f14-block-expansion` → `main`  
**Environment:** `dev.biopentra.eu`  
**Date:** 2026-08-05  
**Canonical plan:** [STRATEGY_F_F14_BLOCK_EXPANSION.md](STRATEGY_F_F14_BLOCK_EXPANSION.md)  
**Implementation summary:** [F14_IMPLEMENTATION_SUMMARY.md](F14_IMPLEMENTATION_SUMMARY.md)

---

## Admission ledger

| Adapter | Render-safety | PHPUnit | Browser | Docs | Log | SUPPORTED_BLOCKS | Overall |
|---|---|---|---|---|---|---|---|
| `core/list-item` | PASS | PASS | PASS | PASS | PASS | PASS | **PASS** — [F14_ADMISSION_list_item_EVIDENCE.json](F14_ADMISSION_list_item_EVIDENCE.json) |
| `core/preformatted` | PASS | PASS | PASS | PASS | PASS | PASS | **PASS** — [F14_ADMISSION_preformatted_EVIDENCE.json](F14_ADMISSION_preformatted_EVIDENCE.json) |
| `core/verse` | PASS | PASS | PASS | PASS | PASS | PASS | **PASS** — [F14_ADMISSION_verse_EVIDENCE.json](F14_ADMISSION_verse_EVIDENCE.json) |
| `core/code` | PASS | PASS | PASS | PASS | PASS | PASS | **PASS** — [F14_ADMISSION_code_EVIDENCE.json](F14_ADMISSION_code_EVIDENCE.json) |

---

## Milestone gates

| Gate | State |
|---|---|
| F14.0 scaffold | **PASS** |
| F14.1 `core/list-item` | **PASS** |
| F14.2 `core/preformatted` | **PASS** |
| F14.3 `core/verse` | **PASS** |
| F14.4 `core/code` | **PASS** |
| All adapters admitted | **PASS** |
| Final Tier 0 | **PASS** |
| Cumulative F14 adapter smoke | **PASS** (FP=0 × 4) |
| Merge/tag | **Complete** — merge on `main`; tag `strategy-f-f14-block-expansion-complete` |

---

## Final Tier 0

| Gate | Result |
|---|---|
| PHPUnit unit | **PASS** — 373 tests, 836 assertions |
| PHPUnit integration | **PASS** — 329 tests, 7120 assertions |
| PHPCS (`src/Block`, Block/Rollout tests) | **PASS** — 0 errors |
| TypeScript / frontend build | **N/A** — F14 PHP-only |
| F9 35-test Playwright suite | **Not run** (per F14 policy) |

Evidence: [F14_TIER0_EVIDENCE.json](F14_TIER0_EVIDENCE.json)

---

## Cumulative browser smoke (F14.5)

Script: `acceptance/f14-staging/f14-adapter-smoke.sh` for each admitted key.

| Adapter | Result | FP |
|---|---|---|
| `list-item` | PASS | 0 |
| `preformatted` | PASS | 0 |
| `verse` | PASS | 0 |
| `code` | PASS | 0 |

Config restored to F12 observation defaults after each run (`stage=2`, post `6321`, `sv`, GA off, cache off).

---

## Final supported allowlist

```
core/paragraph
core/heading
core/button
core/list-item
core/preformatted
core/verse
core/code
```

Frozen architecture unchanged: UUID, Store, TM, AI, Workspace, RolloutPolicyService, CohortProvider, render/cache/metrics/diagnostics/REST/security.
