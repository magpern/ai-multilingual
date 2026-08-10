# TQ.0 — Translation Quality Baseline — Implementation Validation Log

**Status:** **TQ.0 Complete** — independently reviewed, merged to `main`, docs closed
**Implementation branch:** `feature/tq0-translation-quality-baseline` @ `08e7b8acb787f51af861ac746f77b38df713227c`
**Implementation baseline (branch start):** `974f1d15ba7d4d5c952509bd0947cfc695ab5c8f`
**Merge commit:** `a602c4465df42b3ed9454e0ce2de7a565a3fe4cf`
**Independent review:** **PASS** (2026-08-10)
**Branch CI:** `31366251033` (all green)
**Frozen plan blob (pre-closure):** `d5fcfb2d8738a02445d51e51f0ca6fc21f270243`
**TIQ parent blob (pre-closure):** `41ec8c093ffcd63e2a87f1396b603a4b20f82134`
**Behavior reference:** tag `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a`
**TARGET:** 6
**Official pack:** `tests/quality/baselines/baseline-v1.1.0/`

## Behavior-equivalence audit

| Check | Result |
|---|---|
| `git diff --name-only v1.1.0..HEAD -- src/ bin/ assets/ ai-multilingual.php` | **empty** |
| `composer.json` vs v1.1.0 | **autoload-dev Quality + quality:* scripts only** |
| Verdict | Translator subject **behavior-equivalent** to `v1.1.0` |

## Architecture lock (confirmed at review)

- Measurement-only; one brain (`TranslationService` / `AIProviderInterface`)
- Harness is not a second translator; PersistPathBatchBuilder parity tests present
- `field_semantics` metadata only; not in prompts
- Class A offline; Class B human complete; Class C optional (not run)
- CI network-free quality job; live OpenAI only under `acceptance/quality/`
- Official pack established; TI.1–TI.7 not started
- TARGET remains 6; no Store/schema/identity/Integration API change

## Package status

| Package | Status |
|---|---|
| TQ0.0–TQ0.8 | **PASS** (all) |

## Official baseline-v1.1.0 capture

| Field | Value |
|---|---|
| Provider / model | openai / `gpt-5-mini` |
| Prompt | `translate` / `1` |
| Cases / segments / batches / HTTP | 60 / 60 / 60 / 60 |
| Tokens in / out | 4265 / 26154 |
| Class A critical failures | **0** / 60 |
| Human primary / dual | 60 / 13 (~21.7%); originals preserved |
| Class C | not run |
| Known Class B critical | `gut_01` glossary-instruction leakage (**translator-caused**; retained as baseline evidence) |

## §21 Acceptance criteria

**75/75 PASS** (independent review 2026-08-10).

## Known limitations / debt (not fixed in TQ.0)

- C1.0 is an engineering baseline, not statistical proof.
- `gut_01` glossary-instruction leakage is a real v1.1.0 quality defect exposed by TQ.0; do not correct the frozen generation; candidate H1.1 / TI.1–TI.4 ownership TBD.
- Human B1.0 evidence is structured review of live fixtures suitable for an engineering baseline; it is not a claim of independent professional linguistic certification.

## Exact next planning decision

Choose whether **TI.1** Persist-path Structural Safety or **TI.2** Bounded Translation Context should be planned first. Both depend on completed TQ.0; TI.3 depends on both. Do not start either in this closure.
