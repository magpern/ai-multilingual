# TI.1 — Persist-path Structural Safety — Implementation Validation Log

**Status:** **In progress** on `feature/ti1-persist-path-structural-safety`
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

## Architecture lock (TI1.0)

| Lock | Status |
|---|---|
| Canonical seam `TranslationService::persist_provider_result()` | Locked |
| Production ownership `ResponseValidator` + `SegmentConstraintAnalyzer` | Locked |
| TQ.0 H1.0 measurement-only | Locked |
| No prompt/context/TM/glossary/Jobs redesign | Locked |
| No Store AI validation ownership | Locked |
| No schema / TARGET / identity / Integration API change | Locked |
| Sync + Jobs share one gate | Locked |
| `gut_01` Deferred | Locked |

## TS1–TS14 dispositions (frozen)

| ID | Disposition |
|---|---|
| TS1 | Supported |
| TS2 | Supported (`aiml_ai_invalid_response` response-contract) |
| TS3 | Supported |
| TS4 | Supported |
| TS5 | Supported |
| TS6 | Partial (absolute URL Supported; SKU Deferred) |
| TS7 | Partially Supported — **evidence-gated** (see TI1.1) |
| TS8 | Deferred |
| TS9 | Supported |
| TS10 | Supported |
| TS11 | Supported |
| TS12 | Supported |
| TS13 | Supported |
| TS14 | Deferred (`gut_01`) |

## TI1.0 baseline gates

| Gate | Result |
|---|---|
| `quality:verify-baseline` | PASS (cases=60 critical=0 fingerprints=ok) |
| Working tree at branch start | clean @ `125a0ee7801dccbb93b396b477894f0bc18d3cba` |
| TARGET | 6 |

## Package status

| Package | Status |
|---|---|
| TI1.0 | **PASS** (this commit) |
| TI1.1 | pending |
| TI1.2 | pending |
| TI1.3 | pending |
| TI1.4 | pending |
| TI1.5 | pending |
| TI1.6 | pending |
| TI1.7 | pending |
| TI1.8 | pending |

## TS7 decision (filled in TI1.1)

_Pending proof suite._
