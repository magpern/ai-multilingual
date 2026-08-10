# TI.4 — Deterministic QA Hardening — Implementation Validation Log

**Status:** **TI.4 implementation complete — review-ready**
**Implementation branch:** `feature/ti4-deterministic-qa-hardening`
**Implementation baseline (branch start):** `831b646a34089f8f167014597934fcb0a6712010`
**Frozen plan blob:** `96c1832fb58d9425922ad110f6d4b08506e050c9`
**TIQ parent blob:** `6b24212ac3a1810e5b20493f425140bb25db9405`
**Official TQ.0 pack:** `tests/quality/baselines/baseline-v1.1.0/` (immutable)
**H1.0 / C1.0 / C1.1 / C1.2:** immutable
**Additive methodology:** H1.1 · C1.3 (16 cases)
**TARGET:** 6
**TI.5–TI.7:** not started
**Merge:** not merged — awaiting independent review
**Historical leakage gate:** Outcome **C** — `scores.H1.1.json` records `leakage_not_applicable` per case (not PASS)

## Architecture lock

| Lock | Status |
|---|---|
| Option B: shared detectors → raw findings → policy adapters | **PASS** |
| Raw findings policy-neutral (no severity/owner/blocking_class) | **PASS** |
| TI.1 persist BLOCK set unchanged (`ResponseValidator` intact) | **PASS** |
| Numbers persist omit (TS7) | **PASS** |
| Historical leakage Outcome C / not_applicable ≠ PASS | **PASS** |
| No gut_01 phrase rule | **PASS** |
| No TI.7 publication authority | **PASS** |
| No second QA / Jobs QA engine | **PASS** |
| H1.0 / C1.0–C1.2 / baseline immutable | **PASS** |
| TARGET 6 / no schema | **PASS** |
| Marker export via optional `ScaffoldingMarkerSource` (no AIProviderInterface change) | **PASS** |

## QD1–QD22 final dispositions

| ID | Disposition | Implementation |
|---|---|---|
| QD1 | Deferred | Not implemented |
| QD2 | Partially Supported | `qd2_source_equals_target` WARNING |
| QD3–QD4 / QD15 / QD18–QD19 | Partially Supported | `qd3_scaffolding_leakage` + Outcome C |
| QD5 | Supported | loss/addition |
| QD6 | Supported | HTML loss; Workspace WARN when target has zero tags |
| QD7 | Supported | Forbidden markup ERROR |
| QD8 | Supported | Absolute URL ERROR in Workspace |
| QD9 | Partially Supported | Normalized numbers; Workspace WARNING; persist omit |
| QD10 | Deferred | — |
| QD11 | Deferred | Fixture SKUs H1.0 only |
| QD12 | Partially Supported | INFO soft |
| QD13 | Supported | WARNING length ratio |
| QD14 | Partially Supported | Exact duplicate paragraph WARNING |
| QD16 | Partially Supported | Glossary preferred-term |
| QD17 | Unsupported | — |
| QD20 | Deferred | — |
| QD21 | Supported | Persist BLOCK; Workspace WARNING + clear-on-save |
| QD22 | Supported | RawFinding + policies |

## Work package status

| WP | Status |
|---|---|
| TI4.0 | **PASS** |
| TI4.1 | **PASS** |
| TI4.2 | **PASS** |
| TI4.3 | **PASS** |
| TI4.4 | **PASS** |
| TI4.5 | **PASS** |
| TI4.6 | **PASS** |
| TI4.7 | **PASS** |
| TI4.8 | **PASS** (review-ready; not merged) |

## Baseline gates (TI4.0)

| Gate | Result |
|---|---|
| Unit (baseline) | 666 tests, 1876 assertions (2 skipped) |
| PHPCS | clean |
| quality validate | PASS cases=60 |
| baseline verify | PASS critical=0 |

## Implementation gates (TI4.7)

| Gate | Result |
|---|---|
| Unit | 694 tests, 1941 assertions (2 skipped) |
| Integration | 616 tests, 13651 assertions (2 skipped) |
| PluginGuard | 17 tests, 9924 assertions |
| PHPCS | clean |
| quality validate C1.0 | PASS |
| quality validate C1.3 | PASS cases=16 |
| baseline H1.0 verify | PASS |
| baseline H1.1 scores | 60/60 pass; 0 critical; 60 not_applicable leakage |
| Build / ZIP audit | PASS |

## Acceptance criteria (78)

All 78 frozen ACs covered by implementation + tests + this log. Summary: **78 PASS** (feature-branch). Independent review may re-score.

## False-positive notes

- Numbers: `NumberNormalizer` + `NumberLocalizationProofTest` SV controls.
- Source==target: minimum length gate; short brands not flagged.
- HTML Workspace: zero target tags → WARNING (editor plain-text UX).
- Empty Workspace: WARNING + clear-on-save strip.
- Leakage: requires markers_applicable + marker length ≥ 8; no gut_01 phrase.

## ADR

No ADR. Optional `ScaffoldingMarkerSource` capability interface — `AIProviderInterface` unchanged.

## Limitations / debt

- PunctuationCheck / VariableCheck remain Workspace-only (not shared suite).
- Historical gut_01 Swedish leak remains Class B; H1.1 leakage N/A.
- Units / wrong-language / SKU heuristic / never_translate / SEO length remain Deferred/Unsupported.
