# TQ.0 Translation Quality Baseline Harness

**Status:** TQ.0 Complete on `main` — official [`baselines/baseline-v1.1.0/`](baselines/baseline-v1.1.0/) frozen  
**Authoritative plan:** [`docs/plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md`](../../docs/plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md)  
**TIQ parent:** [`docs/plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md`](../../docs/plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md)  
**Official baseline:** [`baselines/baseline-v1.1.0/`](baselines/baseline-v1.1.0/)

## Purpose

Measurement-only quality ruler for comparing released **v1.1.0** translation behavior against later candidates. This harness is **not** a second translator.

## Hard rules

- One shared production path: `TranslationService` / `AIProviderInterface`
- Persist-path generation parity required for baseline capture
- `field_semantics` is corpus metadata only (never injected into v1.1.0 prompts)
- Normal CI is network-free (replay/score/validate only)
- Live OpenAI is explicit/manual under `acceptance/quality/`
- Class A deterministic failures cannot be overridden by Class C
- TQ0.7 official `baseline-v1.1.0` evidence pack is mandatory for milestone closure
- No TI.1 persist-path wiring, TM-in-generation, context redesign, or auto-publish

## Layout

| Path | Role |
|---|---|
| `corpus/C1.0/` | Versioned EN→SV corpus |
| `schemas/` | JSON schemas |
| `src/` | Scorers, loaders, comparer, parity adapter |
| `bin/` | CLI entrypoints |
| `baselines/` | Official frozen evidence packs |
| `candidates/` | Candidate evidence packs |
| `reviews/` | Human review artifacts |

## Commands

See Composer scripts `quality:validate`, `quality:score`, `quality:compare` and `tests/quality/bin/*`.

## Versions

| Axis | Initial |
|---|---|
| Corpus | `C1.0` |
| Scorer | `H1.0` |
| Methodology | `M1.0` |
| Glossary fixture | `G1.0` |
| Official baseline label | `baseline-v1.1.0` |
