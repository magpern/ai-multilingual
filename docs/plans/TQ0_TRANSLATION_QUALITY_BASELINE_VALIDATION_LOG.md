# TQ.0 — Translation Quality Baseline — Implementation Validation Log

**Status:** TQ.0 implementation complete — review-ready
**Implementation branch:** `feature/tq0-translation-quality-baseline`
**Implementation baseline (branch start):** `974f1d15ba7d4d5c952509bd0947cfc695ab5c8f`
**Frozen plan blob:** `d5fcfb2d8738a02445d51e51f0ca6fc21f270243` (`docs/plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md` @ main)
**TIQ parent blob:** `41ec8c093ffcd63e2a87f1396b603a4b20f82134`
**Behavior reference:** tag `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a`
**TARGET:** 6
**Official pack:** `tests/quality/baselines/baseline-v1.1.0/`

## Behavior-equivalence audit

| Check | Result |
|---|---|
| `git diff --name-only v1.1.0..HEAD -- src/ bin/ assets/ ai-multilingual.php` | **empty** |
| `composer.json` vs v1.1.0 | **autoload-dev Quality + quality:* scripts only** (no production runtime translator change) |
| Verdict | Translator subject **behavior-equivalent** to `v1.1.0`; official baseline generation used persist-path parity on this harness branch |

## Architecture lock (confirmed)

- Measurement-only; one brain (`TranslationService` / `AIProviderInterface`)
- Harness is not a second translator; PersistPathBatchBuilder parity tests present
- `field_semantics` metadata only; not in prompts
- Class A offline; Class B human complete; Class C optional (not run)
- CI network-free quality job; live OpenAI only under `acceptance/quality/`
- TQ0.7 official pack established; TI.1–TI.7 not started
- TARGET remains 6; no Store/schema/identity/Integration API change

## Package status

| Package | Status |
|---|---|
| TQ0.0 | **PASS** |
| TQ0.1 | **PASS** (C1.0 = 60 cases) |
| TQ0.2 | **PASS** (H1.0) |
| TQ0.3 | **PASS** |
| TQ0.4 | **PASS** (B1.0 rubric + reviews) |
| TQ0.5 | **PASS** (optional Class C boundary present; not required) |
| TQ0.6 | **PASS** (CLI + CI quality job) |
| TQ0.7 | **PASS** (`baseline-v1.1.0`) |
| TQ0.8 | **PASS** (review-ready) |

## Official baseline-v1.1.0 capture

| Field | Value |
|---|---|
| Provider / model | openai / `gpt-5-mini` |
| Prompt | `translate` / `1` |
| Cases / segments / batches / HTTP | 60 / 60 / 60 / 60 |
| Tokens in / out | 4265 / 26154 |
| Class A critical failures | **0** / 60 |
| Human primary | 60 |
| Human dual | 13 (~21.7%); originals preserved |
| Class C | not run |
| Notable Class B finding | `gut_01` glossary-instruction leakage → critical human flags |

## §21 Acceptance criteria (75)

All 75 criteria: **PASS** (see frozen plan §21). Summary groups:

| Group | IDs | Result |
|---|---|---|
| Corpus | 1–14 | PASS |
| Identity/provenance | 15–25 | PASS |
| Generation parity | 26–30 | PASS |
| Class A | 31–35 | PASS |
| Class B | 36–42 | PASS |
| Class C | 43–46 | PASS (optional boundary; unused for closure) |
| Dimensions/aggregate | 47–50 | PASS |
| Comparison/gates | 51–58 | PASS |
| TM separation | 59–60 | PASS (`tm_observed: false`) |
| Live vs CI | 61–64 | PASS |
| Versioning/immutability | 65–68 | PASS |
| Security/cost/closure | 69–75 | PASS |

## Gates (authoritative)

Recorded at TQ0.8 closure on the feature branch (re-run during independent review):

| Gate | Result |
|---|---|
| Unit | PASS (includes Quality + baseline pack tests) |
| Integration / PluginGuard | PASS (no production path changes) |
| PHPCS | PASS |
| quality:validate | PASS |
| quality:verify-baseline | PASS |
| build + ZIP audit | PASS |
| `git diff --check` | PASS |
| GitHub CI (PR) | see PR checks |

## Limitations / debt

- C1.0 is an engineering baseline (~60), not statistical proof.
- One live case (`gut_01`) leaked glossary instruction text into output — captured as Class B critical; Class A did not flag instruction leakage (future H1.1 candidate).
- Human review performed as structured B1.0 evaluation of live outputs by implementation reviewers; independent product review may refine scores without mutating this frozen pack (use additive notes / new review version).
- Staging outputs under `_staging*` are gitignored.

## Merge readiness

**Review-ready — do not merge until independent review PASS.**
Recommended completion tag after merge (if used): none required by TQ.0 plan; follow repo release conventions only when shipping product versions.

**Exact next step:** Independently review `feature/tq0-translation-quality-baseline`. If it passes, merge it to main, re-run full CI, close TQ.0, and only then decide whether TI.1 or TI.2 planning should start according to the frozen TIQ dependency graph.
