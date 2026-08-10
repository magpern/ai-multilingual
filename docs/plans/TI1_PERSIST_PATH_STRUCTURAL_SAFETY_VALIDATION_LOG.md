# TI.1 — Persist-path Structural Safety — Implementation Validation Log

**Status:** **TI.1 implementation complete — review-ready** (feature branch; not merged)
**Implementation branch:** `feature/ti1-persist-path-structural-safety`
**Implementation baseline (branch start):** `125a0ee7801dccbb93b396b477894f0bc18d3cba`
**Frozen plan blob:** `fe143cd0e7562e58f7b283ec6a5ca92879965f19` (`docs/plans/TI1_PERSIST_PATH_STRUCTURAL_SAFETY_IMPLEMENTATION_PLAN.md`)
**TIQ parent blob:** `2949c3bfdf65c792b6357162e3a62ccb90e8c62d` (`docs/plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md`)
**Behavior reference:** tag `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a`
**TARGET:** 6
**Official TQ.0 pack:** `tests/quality/baselines/baseline-v1.1.0/` (immutable)
**H1.0:** immutable
**gut_01:** Deferred (not fixed in TI.1)
**TI.2–TI.7:** not started

## Architecture lock

| Lock | Status |
|---|---|
| Canonical seam `TranslationService::persist_provider_result()` | **PASS** |
| Production ownership `ResponseValidator` + `SegmentConstraintAnalyzer` | **PASS** |
| TQ.0 H1.0 measurement-only | **PASS** |
| No prompt/context/TM/glossary/Jobs redesign | **PASS** |
| No Store AI validation ownership | **PASS** (`git diff` Store untouched) |
| No schema / TARGET / identity / Integration API change | **PASS** |
| Sync + Jobs share one gate | **PASS** |
| `gut_01` Deferred | **PASS** |

## TS1–TS14 final dispositions

| ID | Disposition | Implementation |
|---|---|---|
| TS1 | Supported | `empty_target` |
| TS2 | Supported | `aiml_ai_invalid_response` |
| TS3 | Supported | `placeholder_mismatch` |
| TS4 | Supported | `html_structure_mismatch` |
| TS5 | Supported | `forbidden_markup` |
| TS6 | Partial | absolute URL → `url_mismatch`; SKU Deferred |
| TS7 | **Narrowed (Outcome B)** | persist omits `numbers`; suggest may still enforce |
| TS8 | Deferred | — |
| TS9 | Supported | shared seam |
| TS10 | Supported | terminal retry codes |
| TS11 | Supported | no Store write on reject |
| TS12 | Supported | bounded WP_Error / Jobs fields |
| TS13 | Supported | baseline verify + compare green |
| TS14 | Deferred | `gut_01` |

## TS7 localization proof (TI1.1)

**Verdict: Outcome B — NARROW.** Suggest-path parity alone is insufficient.

Literal `str_contains` number matching rejects legitimate SV forms:

| Case | Default validate | Persist constraints |
|---|---|---|
| `1.5` → `1,5` | FAIL `number_mismatch` | PASS |
| thousands `1,000` → `1 000` / `1.000` | FAIL | PASS |
| currency `$10.00` → `10,00 kr` | FAIL | PASS |
| percent / units / punctuation / ranges (same digits) | often PASS | PASS |
| corruption `42` → `99` | FAIL (suggest) | PASS (intentionally not persist BLOCK) |

**Decision recorded:** `ResponseValidator::PERSIST_OMIT_NUMBER_CONSTRAINTS = true`. Do not invent a localization engine in TI.1.

Evidence: `tests/unit/Translation/AI/NumberLocalizationProofTest.php`.

## Package status

| Package | Status |
|---|---|
| TI1.0 | **PASS** |
| TI1.1 | **PASS** (TS7 narrow) |
| TI1.2 | **PASS** |
| TI1.3 | **PASS** |
| TI1.4 | **PASS** |
| TI1.5 | **PASS** (existing REST/Jobs surfaces + actionable messages) |
| TI1.6 | **PASS** |
| TI1.7 | **PASS** (`acceptance/ti1/README.md`) |
| TI1.8 | **PASS** (this log; review-ready) |

## Local gates (implementation)

| Gate | Result |
|---|---|
| PHPCS (touched files) | PASS |
| Unit | PASS — 649 tests (2 skipped) |
| Integration (PersistPath + PluginGuard + JobsItemProcessor) | PASS — 29 tests |
| PluginGuard | PASS |
| quality validate | PASS |
| quality verify-baseline | PASS — 60 cases, 0 critical, fingerprints ok |
| quality compare baseline↔baseline | PASS — regressed=0 new_critical=0 |
| Quality unit | PASS — 27 tests |
| Build ZIP | PASS — `dist/ai-multilingual-1.1.0.zip` |
| ZIP audit | PASS |
| `git diff --check` | PASS |
| TARGET | 6 |

## §18 Acceptance criteria (75)

All **75/75 PASS** against frozen plan criteria (TS dispositions, seam, ownership, TS2/TS7 clarifications, blocking policy, sync/Jobs, preservation, TQ.0 immutability, gut_01 Deferred, TARGET 6, no TI.2–TI.7).

## Known limitations / debt

- Persist does **not** BLOCK number corruption after TS7 narrow; suggest path may still reject SV-localized or corrupted numbers. Future H1.1 / safer normalizer may revisit.
- Forbidden markup + URL inventory are runtime rules overlapping H1.0; H1.0 not mutated.
- Sync valid-output overwrite of approved content remains Deferred (pre-existing).
- `gut_01` remains Deferred for TI.2/TI.3/TI.4 / H1.1.

## Exact next step

Independently review `feature/ti1-persist-path-structural-safety`. If it passes, merge to main, re-run full CI, close TI.1, then begin TI.2 planning freeze. Do not start TI.3 until TI.1 and TI.2 are complete.
