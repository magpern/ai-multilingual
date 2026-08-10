# TI.4 — Deterministic QA Hardening — Implementation Validation Log

**Status:** **TI.4 implementation in progress**
**Implementation branch:** `feature/ti4-deterministic-qa-hardening`
**Implementation baseline (branch start):** `831b646a34089f8f167014597934fcb0a6712010`
**Frozen plan blob:** `96c1832fb58d9425922ad110f6d4b08506e050c9`
**TIQ parent blob:** `6b24212ac3a1810e5b20493f425140bb25db9405`
**Official TQ.0 pack:** `tests/quality/baselines/baseline-v1.1.0/` (immutable)
**H1.0 / C1.0 / C1.1 / C1.2:** immutable
**Additive methodology:** H1.1 · C1.3 (planned)
**TARGET:** 6
**TI.5–TI.7:** not started
**Merge:** not merged — awaiting independent review

## Architecture lock

| Lock | Status |
|---|---|
| Option B: shared detectors → raw findings → policy adapters | **LOCKED** |
| Raw findings policy-neutral (no severity/owner/blocking_class) | **LOCKED** |
| TI.1 persist BLOCK set unchanged | **LOCKED** |
| Numbers persist omit (TS7) | **LOCKED** |
| Historical leakage Outcome C / not_applicable ≠ PASS | **LOCKED** |
| No gut_01 phrase rule | **LOCKED** |
| No TI.7 publication authority | **LOCKED** |
| No second QA / Jobs QA engine | **LOCKED** |
| H1.0 / C1.0–C1.2 / baseline immutable | **LOCKED** |
| TARGET 6 / no schema | **LOCKED** |

## QD1–QD22 dispositions (frozen)

| ID | Disposition |
|---|---|
| QD1 | Deferred |
| QD2 | Partially Supported |
| QD3 | Partially Supported (+ Outcome C historical) |
| QD4 | Partially Supported (+ Outcome C historical) |
| QD5 | Supported |
| QD6 | Supported |
| QD7 | Supported |
| QD8 | Supported |
| QD9 | Partially Supported (persist omit) |
| QD10 | Deferred |
| QD11 | Deferred (fixture SKUs measurement-only) |
| QD12 | Partially Supported (soft) |
| QD13 | Supported |
| QD14 | Partially Supported |
| QD15 | Partially Supported (markers only) |
| QD16 | Partially Supported |
| QD17 | Unsupported |
| QD18 | Partially Supported (+ Outcome C historical) |
| QD19 | Partially Supported (+ Outcome C historical) |
| QD20 | Deferred |
| QD21 | Supported |
| QD22 | Supported |

## Work package status

| WP | Status |
|---|---|
| TI4.0 | **PASS** |
| TI4.1 | pending |
| TI4.2 | pending |
| TI4.3 | pending |
| TI4.4 | pending |
| TI4.5 | pending |
| TI4.6 | pending |
| TI4.7 | pending |
| TI4.8 | pending |

## Baseline gates (TI4.0)

Recorded after branch creation from main @ `831b646a34089f8f167014597934fcb0a6712010`.

| Gate | Result |
|---|---|
| Unit | 666 tests, 1876 assertions (2 skipped) |
| PHPCS | 517 files, 0 errors |
| quality validate | PASS cases=60 |
| baseline verify | PASS critical=0 dual=13 |
| git diff --check | clean |

Integration / PluginGuard / build recorded in TI4.7 after feature work.
