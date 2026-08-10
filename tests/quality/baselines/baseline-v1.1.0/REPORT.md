# Official baseline-v1.1.0 — TQ.0 Translation Quality Baseline

## Provenance

- Subject: `v1.1.0` (behavior reference `d9c2336182fa2e0ae0582ead78cc0a346670c92a`)
- Subject SHA (harness branch at freeze): `bddf83bf6b70ea57c45d4da57455d36475003956`
- Equivalence: translator paths empty vs tag; composer.json harness-only
- Provider/model: `openai` / `gpt-5-mini`
- Prompt: `translate` / `1`
- Generation label: `gen-v1.1.0-20260810T072229Z`
- Corpus/scorer/methodology/glossary: C1.0 / H1.0 / M1.0 / G1.0
- Accounting: cases=60 segments=60 batches=60 http=60
- Tokens: in=4265 out=26154
- TM observed: false; field_semantics in prompt: false; Store writes: false

## Regressions / critical findings (first)

### Class A deterministic
- Critical failures: **0** / 60 (pass_count=60)

### Class B human critical flags
- `gut_01`: invented_claims, unusable_for_publish — Glossary instruction text leaked into translation output.

## Human B1.0 summary

- Primary reviews: 60
- Dual-review sample: 13 (21.7%) — originals preserved; consensus additive
- Dual IDs: woo_title_02, woo_short_03, woo_long_02, sci_02, mkt_02, ui_02, tax_02, seo_t_02, seo_d_02, gut_01, html_02, ph_02, prot_02

## Dimension means (primary, non-critical-flag cases)

- semantic_fidelity: 4.12
- omission: 4.14
- hallucination: 4.10
- terminology_accuracy: 4.24
- terminology_consistency: 4.14
- fluency_grammar: 4.14
- naturalness: 4.14
- tone_register: 4.14
- technical_meaning: 4.24
- formatting_structural: 4.41
- publish_readiness: 4.39

## Notes

- Aggregate convenience score is non-authoritative; dimensions remain independently visible.
- Class C LLM judge was not required and was not run for official freeze.
- Official pack is immutable; further captures require new generation labels / versions.
