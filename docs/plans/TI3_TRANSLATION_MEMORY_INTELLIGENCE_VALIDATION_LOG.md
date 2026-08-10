# TI.3 — Translation Memory Intelligence — Implementation Validation Log

**Status:** **Complete** on `main`
**Implementation branch:** `feature/ti3-translation-memory-intelligence` @ `eb8d4bc691b3d7969eb73c0abef4eecb07f915e2`
**Implementation baseline (branch start):** `8910e6b63051d6adc5031bbd0c22b9c451f36d46`
**Independent review (implementation):** **PASS** (2026-08-10)
**Merge commit:** `95839113ba47bed80f781db238ce038c8d9b973d`
**Official TQ.0 pack:** `tests/quality/baselines/baseline-v1.1.0/` (immutable)
**Additive corpus:** `tests/quality/corpus/C1.2/` (12 TM decision cases)
**H1.0 / C1.0 / C1.1:** immutable
**TARGET:** 6
**TI.4–TI.7:** implementation not started
**Next:** TI.4 planning only (do not implement until TI.4 plan is frozen on `main`)

## Architecture lock

| Lock | Status |
|---|---|
| One brain: TranslationService → TM → optional AI → TI.1 → Store | **PASS** |
| Reuse `aiml_tm` + `TranslationMemoryService` / `TMRepository` | **PASS** |
| No second Store / TM / vector / embeddings | **PASS** |
| No `source_hash` / Store identity redesign | **PASS** |
| No TARGET / schema migration | **PASS** |
| Fuzzy remains suggestions-only | **PASS** |
| Raw machine rows not direct-reuse authority | **PASS** |
| TM metrics separate from AI quality | **PASS** |
| Sync/Jobs share TranslationService | **PASS** |
| No TI.4–TI.7 work | **PASS** |

## TM21 `translations.tm_id` evidence

| Question | Finding |
|---|---|
| Schema | Column exists on `aiml_translations` (nullable BIGINT) |
| Readers/writers | `Store` never reads/writes `tm_id` |
| Historical plans | F11 planned “set tm_id on TM hit”; never implemented |
| Tests | No contract asserting Store `tm_id` semantics |
| **Outcome** | **B. NARROWED** — leave dormant; diagnostics carry `aiml_tm.tm_id` + outcome codes |

## Structural-fail disposition (TI3.3 evidence)

| Option | Evidence |
|---|---|
| A terminal fail | Rejects recoverable work when TM corpus has a structural defect |
| B AI fallthrough once | Test `test_structural_fail_fallthrough_calls_provider_once`: invalid TM never persists; provider called exactly once; disposition recorded |
| C pre-selection | Would require duplicating TI.1 checks before selection; more complex, same safety if B is bounded |

**Selected:** **B — AI fallthrough once** (`TranslationService::STRUCTURAL_FAIL_DISPOSITION = ai_fallthrough_once`).

Hard invariants held: invalid TM never persists; TI.1 never bypassed; no retry loop; sync/Jobs parity; transport retries separate.

**Independent review fix (ordinary defects):** On structural reject, TM9 class-1 examples are collected from the blocked exact candidate before the single AI fallthrough (`examples_for_blocked_candidate`). Domain allowlist admits public taxonomy subtypes (`category`, `post_tag`, `product_cat`, `product_tag`) per plan §9.7.

## Work package status

| WP | Status |
|---|---|
| TI3.0 | **PASS** |
| TI3.1 | **PASS** |
| TI3.2 | **PASS** |
| TI3.3 | **PASS** (disposition B evidenced) |
| TI3.4 | **PASS** |
| TI3.5 | **PASS** |
| TI3.6 | **PASS** |
| TI3.7 | **PASS** (C1.2 additive; baseline immutable) |
| TI3.8 | **PASS** (merged Complete on `main`) |

## Acceptance criteria (80)

All 80 frozen ACs in the plan are covered by implementation + unit/integration/C1.2 evidence. Summary: **80 PASS** (feature-branch). Independent review may re-score.

## Metrics separation

TM effectiveness counters live on `TMGenerationLookup::metrics()` (lookup_attempts, exact_eligible, no_match, ambiguous, ineligible, direct_reuse, assisted_examples, glossary_blocked, domain_denied, rejected_structural). Not blended into TQ.0 quality scores.

## Limitations / debt

- Domain allowlist uses requesting `post_type`/taxonomy subtype evidence only (TM rows lack source domain metadata).
- Glossary modes `forced`/`never_translate` not invented; version+term-hit skip only.
- Ambiguity beyond ADR-0009 empty-context gate is rare due to UNIQUE `tm_identity`.
- C1.2 is policy metadata corpus; semantic Class B live review not claimed for broad quality uplift from hit rate alone.
- Structurally invalid TM text may still appear as a TM9 example (plan §10.1 class 1); TI.1 still blocks persist of invalid AI/TM output.
