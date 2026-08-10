# TI.2 Acceptance Notes

**Branch:** `feature/ti2-bounded-translation-context`
**Method:** Unit/integration (network-free) + optional live OpenAI TQ.0 candidate (not CI)

## Fake / scripted

| Case | Result |
|---|---|
| Context absent → OpenAI packaging still isolates source | PASS (`BoundedTranslationContextTest`) |
| Glossary do-not-copy section; source last | PASS |
| Sync + Jobs share TranslationContextBuilder | PASS (`BoundedTranslationContextParityTest`) |
| TI.1 persist-path structural suite | PASS (no regression) |
| FieldSemantic unknown → generic | PASS |
| Budgets / drop priority | PASS |

## Live OpenAI (manual)

| Pack | Path |
|---|---|
| TI.2 candidate | `tests/quality/baselines/_staging-ti2/` |
| Compared to | `tests/quality/baselines/baseline-v1.1.0/` |

| Gate | Result |
|---|---|
| zero new Class A critical | **PASS** |
| gut_01 scaffold absent | **PASS** — `Hur vi paketerar forskningsmaterial` |
| C1.0 / H1.0 / baseline-v1.1.0 | Immutable |

## Class B (gut_01)

Human B1.0 previously flagged glossary-instruction leak. Candidate output no longer contains the scaffold or `research => forskning` instruction echo. Broad quality uplift is **not** claimed from gut_01 alone; Class B consensus re-score remains optional for independent review.

## Not in CI

Live OpenAI generation and re-rolls (`acceptance/ti2/regenerate-case.php`) are acceptance-only.
