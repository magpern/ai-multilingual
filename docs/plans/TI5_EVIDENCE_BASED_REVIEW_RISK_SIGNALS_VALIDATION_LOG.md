# TI.5 — Evidence-based Review / Risk Signals — Implementation Validation Log

**Status:** **Complete** on `main`
**Implementation branch:** `feature/ti5-evidence-based-review-risk-signals` @ `d48a5496f6e16658aa822f59b3b4a7bcb4064382`
**Implementation baseline (branch start):** `f0e4c7fb70a280621f92b0f30c7733179e89cddc`
**Independent review (implementation):** **PASS** (2026-08-10)
**Merge commit:** `279ea0f22752141465d6cd3f42823f21d52e2f6b`
**Fresh main CI (merge):** `31425430150` SUCCESS (phpcs/unit/integration/quality/build)
**Frozen plan blob:** `442674bf23bf727b824cbc63fa3d0c1e779f3a2d`
**ADR-0019 blob:** `cec05cb2640f04a8d8e7fba0a17320130a292d94` (**Accepted**)
**TARGET:** 6
**Assessment version:** `R1.0`
**Official TQ.0 pack:** `tests/quality/baselines/baseline-v1.1.0/` (immutable)
**H1.0 / H1.1 / C1.0–C1.3:** immutable
**TI.6–TI.7:** not started
**Next:** TI.6 **planning only**

## Admissions lock

| Admission | Status |
|---|---|
| RA1–RA22 dispositions per frozen plan | **LOCKED** |
| Taxonomy `blocked` / `needs_review` / `review_recommended` / `structurally_clean` | **LOCKED** |
| Hard > soft; approval ≠ erase hard; N/A ≠ PASS | **LOCKED** |
| RA14 Deferred (no aggregate score) | **LOCKED** |
| RA15 Unsupported (no LLM confidence) | **LOCKED** |
| RA16 Unsupported (no persisted assessment) | **LOCKED** |
| RA19 Jobs Deferred | **LOCKED** |
| No publication authority / no `publish_decision` | **LOCKED** |
| Assessment version `R1.0` | **LOCKED** |

## Work packages

| WP | Status |
|---|---|
| TI5.0 Baseline / admissions | **PASS** |
| TI5.1 Domain model | **PASS** |
| TI5.2 Evidence aggregation | **PASS** |
| TI5.3 Risk policy | **PASS** |
| TI5.4 Workspace/ViewModel | **PASS** |
| TI5.5 Provenance parity | **PASS** (RA8/9/10 Partial as frozen) |
| TI5.6 Evaluation suite | **PASS** |
| TI5.7 Gates / regression | **PASS** |
| TI5.8 Docs closure | **PASS** (closed on `main`) |

## Architecture

| Lock | Result |
|---|---|
| One assessment core `src/Translation/Assessment/` | **PASS** |
| TI.4 remains detector owner (`DeterministicQA` / `RawFinding`) | **PASS** |
| TI.5 owns assessment (`AssessmentAssembler` + `RiskAssessmentPolicy`) | **PASS** |
| One DeterministicQA pass per assessment | **PASS** |
| No second QA / Jobs assessment engine | **PASS** |
| No publication decision / TI.7 import | **PASS** |
| No numerical sole score / LLM confidence | **PASS** |
| No persisted assessment / TARGET bump / schema | **PASS** |
| Recompute current-state only | **PASS** |
| `approved ≠ published`; ADR-0015 review consume-only | **PASS** |
| N/A ≠ PASS; unavailable ≠ clean leakage | **PASS** |
| Workspace thin consumer (`meta.assessment`) | **PASS** |
| CLI Partial (`wp aiml assessment get`) | **PASS** |
| Jobs Deferred | **PASS** |
| Translator / prompts / TI.1 persist / TM generation unchanged | **PASS** |

## RA1–RA22 final dispositions

| ID | Disposition | Implementation |
|---|---|---|
| RA1 | Supported | `TranslationAssessment` + `to_array()` |
| RA2 | Supported | Hard blockers from TI.1 BLOCK checks + applicable leakage |
| RA3 | Supported | Error lane (numbers/glossary/unicode/entity) |
| RA4 | Supported | Warning/advisory aggregation |
| RA5 | Supported | `complete` / `partial` / `unavailable` |
| RA6 | Supported | ADR-0015 `review_status` facet (consume-only) |
| RA7 | Supported | ProvenanceClass closed vocabulary |
| RA8 | Partially Supported | `tm_direct_reuse` when in-request outcome present; no hit-rate/confidence authority |
| RA9 | Partially Supported | `ui_label` source==target → INFO non-escalating |
| RA10 | Partially Supported | Preferred-term facet only |
| RA11 | Supported | Structural facet |
| RA12 | Supported | Leakage facet with N/A honesty |
| RA13 | Supported | Closed overall_category taxonomy |
| RA14 | Deferred | No aggregate score |
| RA15 | Unsupported | No LLM confidence |
| RA16 | Unsupported | No persistence / TARGET |
| RA17 | Supported | Workspace `meta.assessment` |
| RA18 | Partially Supported | `wp aiml assessment get` |
| RA19 | Deferred | Jobs untouched |
| RA20 | Supported | `assessment_version=R1.0` |
| RA21 | Supported | Methodology ref independent of H1.x |
| RA22 | Supported | Read-only contract for future TI.7; no TI.7 code |

## Independent review gates

| Gate | Result |
|---|---|
| `git diff --check` | PASS |
| PHPCS | PASS (549 files) |
| Unit | 732 tests, 2079 assertions (2 skipped) |
| Integration | 616 tests, 13957 assertions (2 skipped) |
| PluginGuard | 17 tests, 10230 assertions |
| quality validate | PASS cases=60 |
| quality validate C1.3 | PASS cases=16 |
| baseline-v1.1.0 verify | PASS critical=0 dual=13 |
| H1.1 score replay | PASS 60/60; critical_failures=0; not_applicable=60 |
| Build / ZIP audit | PASS |
| Feature CI | `31424699157` SUCCESS |
| Fresh main CI | `31425430150` SUCCESS |
| TARGET | 6 |

## Assessment evaluation suite

| Source | Coverage |
|---|---|
| `tests/assessment/fixtures/cases.json` | 15 additive cases |
| `AssessmentFixtureSuiteTest` | Fixture-driven + false-authority |
| `RiskAssessmentPolicyTest` | Precedence matrix + false-authority |
| `AssessmentAssemblerTest` | One QA pass / segment DTO |

## False-authority results

| Claim | Result |
|---|---|
| Warning ≠ hard blocker | PASS |
| N/A ≠ PASS | PASS |
| Unavailable ≠ clean leakage | PASS |
| Approval cannot erase hard | PASS (`approved_with_hard_findings`) |
| Many warnings cannot cancel hard | PASS |
| TM reuse ≠ publishable | PASS (no `publish_decision`) |
| `structurally_clean` ≠ semantic perfect / auto-publish | PASS |
| No aggregate score / LLM confidence / publish_decision | PASS |

## Acceptance criteria (65)

Independent re-score: **65/65 PASS**.

## Limitations / debt

- Workspace save-path always passes `markers_applicable=false` (honest `unavailable` leakage completeness); live request markers are not re-exported into Workspace assessment.
- Assisted-TM provenance remains Partial (only in-request `tm_outcome_code`).
- RA14 aggregate score remains Deferred.
- RA19 Jobs surfacing remains Deferred (TI.6).
- No frontend badge redesign beyond additive `meta.assessment` payload.

## Closure

TI.5 is **Complete** on `main`. Evidence-based risk/readiness assessment is available as a read-only recomputed contract (`R1.0`). No aggregate score, no LLM confidence, no persisted assessment, no publication decision. Jobs integration Deferred to TI.6. TI.7 may later consume the read-only assessment contract. TARGET remains 6. TI.6/TI.7 implementation not started.
