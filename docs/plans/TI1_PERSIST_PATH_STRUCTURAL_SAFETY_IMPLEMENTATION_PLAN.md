# TI.1 — Persist-path Structural Safety — Implementation Plan

**Status:** **Architecture Frozen** on `main` — **implementation not started**
**Milestone:** TI.1 — Persist-path Structural Safety (TIQ program)
**Kind:** Milestone implementation plan (authoritative on `main`)
**Parent:** [TIQ_PARENT_IMPLEMENTATION_PLAN.md](TIQ_PARENT_IMPLEMENTATION_PLAN.md)
**TQ.0:** **Complete** — [TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md](TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md); official pack `tests/quality/baselines/baseline-v1.1.0/`
**Planning branch:** `docs/ti1-persist-path-structural-safety-plan` (merged)
**Independent review:** **PASS** (2026-08-10)
**Freeze merge:** recorded on `main` after independent review (see git history for `merge: freeze TI.1 Persist-path Structural Safety implementation plan`)
**Repository baseline (plan authoring):** `main` @ `aa7812b7f0a10cb21441593c1ec0af4867116571`
**Behavior reference:** tag `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a`
**Schema:** Migrator `TARGET` = **6** (unchanged)
**ADR assessment:** **No new ADR.** Narrow extension of existing F11 `ResponseValidator` / error contracts into the shared persist path.
**Implementation branch:** `feature/ti1-persist-path-structural-safety` — **not created yet**; create from frozen `main` for TI1.0–TI1.8 only

**Operational success:** A machine-generated translation cannot be persisted through the normal `TranslationService` / Jobs path when it violates high-confidence deterministic structural invariants the platform already knows how to detect.

**Hard boundary:** TI.1 is **persist-path structural safety**. It is not semantic quality improvement, prompt/context redesign (TI.2), TM/glossary-in-generation (TI.3), full QA block/warn product policy (TI.4), auto-publication, Store redesign, or a second translation brain.

---

## 1. Purpose

Design and later implement the smallest safe architecture change that ensures:

> After a provider returns a normalized translation result, the shared persist path rejects structurally invalid machine output **before** `Store::save_translation()`.

Product value:

- Prevent malformed / structurally unsafe machine translations from being saved as usable targets
- Apply the same deterministic safety policy to synchronous translation and background Jobs
- Fail predictably and visibly with stable codes
- Preserve one translation brain (`TranslationService` / `AIProviderInterface`)
- Keep TI.2/TI.3 free to improve quality without bypassing safety

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| `main == origin/main` @ `aa7812b7f0a10cb21441593c1ec0af4867116571` | **Pass** |
| TIQ parent Architecture Frozen on `main` | **Pass** |
| TQ.0 Complete; official `baseline-v1.1.0` present | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| No TI.1–TI.7 implementation started | **Pass** |
| No `feature/ti1-persist-path-structural-safety` | **Pass** |

If any precondition regresses before implementation starts: **STOP**.

---

## 3. Repository findings (evidence)

| Finding | Evidence |
|---|---|
| Persist skips structural validation | [`TranslationService::translate_segment`](../../src/Workspace/TranslationService.php) → `persist_provider_result`: non-empty gate only, then `STATUS_MACHINE_TRANSLATED` |
| Suggest validates | Same file `suggest_segment`: `ResponseValidator::validate` |
| Analyzer derives constraints when list empty | [`ResponseValidator`](../../src/Translation/AI/ResponseValidator.php); [`SegmentConstraintAnalyzer`](../../src/Translation/AI/SegmentConstraintAnalyzer.php) |
| Jobs share sync path | [`BackgroundTranslationItemProcessor`](../../src/Jobs/BackgroundTranslationItemProcessor.php) calls `translate_segment()`; conflict gate is Jobs-only pre-check |
| Store does not AI-validate | [`Store::save_translation`](../../src/Translation/Store.php) upsert |
| Malformed-but-nonempty persists today | Empty-only gate |
| TQ.0 Class A on official baseline | 60/60 pass, 0 critical; `gut_01` only `length_ratio` warning |
| `gut_01` translator prompt-echo | OpenAI user prompt glossary label + fragment; Class B critical; H1.0 blind |
| Number analyzer is literal substring | `extract_numbers()` uses `/\d+(?:[.,]\d+)?/` — high SV localization FP risk for persist BLOCK |
| Provider shape errors already exist | `aiml_ai_invalid_response` in OpenAI provider normalization |

---

## 4. Current persist-path lifecycle

```text
Workspace sync OR Jobs ItemProcessor
  → (Jobs only: conflict gate)
  → TranslationService::translate_segment()
  → TranslationBatch (OPERATION_TRANSLATE, constraints=[])
  → AIProviderInterface::translate_batch()
  → persist_provider_result()
       empty text → WP_Error (today: aiml_ai_not_configured) — no Store
       nonempty  → Store::save_translation(status=machine_translated)
```

**TI.1 change point:** inside `persist_provider_result`, after provider success / segment resolution, **before** Store write.

---

## 5. Current validation lifecycle

| Path | Structural validation |
|---|---|
| Persist machine translate | **None** beyond non-empty |
| Suggest | `ResponseValidator` (blocking → 422) |
| Manual Workspace save | `QAEngine` — not auto-translate |
| TQ.0 Class A | Offline measurement only |

---

## 6. Ownership model (frozen)

**Option A — Production validator is canonical; TQ.0 measures.**

| Concern | Owner |
|---|---|
| Persist + suggest structural content checks | `ResponseValidator` + `SegmentConstraintAnalyzer` under `src/Translation/AI/` |
| Manual save UX QA | `QAEngine` (unchanged ownership; not TI.1 persist gate) |
| Benchmark scoring | TQ.0 `DeterministicScorer` **H1.0** (immutable) |

Do **not** promote `tests/quality/src/*` into production runtime.

---

## 7. Canonical validation seam (frozen)

**Placement:** `TranslationService::persist_provider_result()`

Order:

1. **Response-contract (TS2):** resolve segment by `segment_key` from `ProviderResult`. Missing / duplicate / unusable / unmappable → `WP_Error( 'aiml_ai_invalid_response', … )`; **no Store write**.
2. **Content structural:** run `ResponseValidator` on the present string hypothesis (including `''`); fail → content codes; **no Store write**.
3. **Persist:** `Store::save_translation(...)` only if content validation passes.

**Not Store. Not provider HTTP layer. Not Jobs-only.**

Why:

- Store must stay AI-structure-agnostic
- Validation after `AIProviderInterface` keeps provider-agnosticism
- Sync + Jobs both call `translate_segment()`

---

## 8. Candidate matrix TS1–TS14 (frozen dispositions)

| ID | Candidate | Disposition | Rationale |
|---|---|---|---|
| TS1 | Empty provider output (segment present, empty string) | **Supported** | Unify via `ResponseValidator` `non_empty`; stop misusing `aiml_ai_not_configured` for empty-after-provider |
| TS2 | Missing/misaligned provider segment response | **Supported** | Blocks persist as **provider response-contract** failure using **`aiml_ai_invalid_response`** (existing family). Not content-structural codes. See §10 |
| TS3 | Placeholder preservation (loss) | **Supported** | Existing `ResponseValidator` + analyzer |
| TS4 | HTML markup inventory blocking | **Supported** | Tag inventory when `text_format=html` |
| TS5 | Forbidden dangerous tag invention | **Supported** | `script` / `iframe` / `object` / `embed` invented in target — high confidence |
| TS6 | URL / protected identifier | **Partially Supported** | **Absolute `http(s)` URL loss:** Supported BLOCK. **SKU-like heuristic:** Deferred (high FP) |
| TS7 | Number / unit preservation | **Partially Supported** | **Suggest-path parity alone is not sufficient for a persist BLOCK.** Admit `numbers` to persist constraints only after TI1.1 localization proof PASSes; else **narrow** (omit from persist). See §9.1 |
| TS8 | Unicode / entity corruption | **Deferred** | Soft heuristics; TI.4 / H1.0 observe |
| TS9 | Shared sync/Jobs validation seam | **Supported** | Seam in `persist_provider_result` |
| TS10 | Retryability / failure classification | **Supported** | Content-structural codes → Jobs **terminal**; TS2 keeps existing `aiml_ai_invalid_response` disposition |
| TS11 | Existing translation preservation | **Supported** | Rejected machine output never overwrites prior row. Valid-output sync overwrite of approved content remains pre-existing Deferred gap |
| TS12 | Diagnostics / audit | **Supported** | Bounded codes/messages; no bodies/secrets |
| TS13 | TQ.0 safety regression | **Supported** | H1.0 immutable; baseline verify; zero new Class A critical regressions |
| TS14 | Glossary-instruction leakage (`gut_01`) | **Deferred** | Not TI.1 structural; H1.1 detect / TI.4 policy / TI.2–TI.3 root cause |

Do not pre-expand Deferred candidates during implementation.

---

## 9. Blocking vs warning policy (frozen)

**Rule:** Fail closed only for high-confidence deterministic structural failures with acceptably low false-positive risk.

| Check | Detectability | FP risk | Runtime action | Retryable? | TQ.0 H1.0 |
|---|---|---|---|---|---|
| Empty target (segment present) | High | Low | **BLOCK** (`empty_target`) | No (terminal) | `empty_translation` |
| Missing/misaligned `ProviderResult` segment | High | Low | **BLOCK** (`aiml_ai_invalid_response`) | Existing invalid-response mapping | (contract) |
| Placeholder loss | High | Low–med | **BLOCK** | No | `placeholder_loss` |
| Placeholder addition | Med | Med–high | **OBSERVE** | — | `placeholder_addition` |
| HTML tag loss | High | Low–med | **BLOCK** | No | `html_tag_loss` |
| Broken HTML soft heuristic | Low | High | **OBSERVE** | — | `broken_html` |
| Forbidden dangerous tags | High | Low | **BLOCK** | No | `forbidden_markup` |
| Number loss | High | Med–high (SV locale) | **CONDITIONAL BLOCK** — only if TI1.1 proof PASSes; else omit from persist | No when blocked | `number_corruption` |
| Absolute URL loss | High | Low | **BLOCK** | No | `url_corruption` |
| SKU-like loss | Med | High | **DEFERRED** | — | `sku_corruption` |
| Entity / Unicode | Med | Med | **OBSERVE / DEFERRED** | — | entity/unicode |
| Whitespace / length ratio | Soft | High | **OBSERVE** | — | warnings |
| Glossary preferred missing | Mech | Med | **OBSERVE** | — | `glossary_compliance` |
| Glossary instruction echo | Soft | High if naive | **DEFERRED** (`gut_01`) | — | not in H1.0 |
| source == target | Soft | High | **OBSERVE** | — | warning |

Do **not** turn all TQ.0 Class A findings into runtime blockers.

### 9.1 TS7 — number preservation (materialization clarification)

**Suggest-path parity alone is not sufficient justification for a persist blocker.**

Before TI.1 treats number mismatch as BLOCK on persist, TI1.1 **must** prove (unit fixtures) that existing analyzer/validator behavior does **not** reject legitimate Swedish localization with acceptably low false-positive risk, covering at minimum:

- decimal separators: `1.5` ↔ `1,5`
- thousands separators: `1,000` / `1 000` / `1.000`
- percentages
- currencies
- quantities with units
- punctuation adjacent to numbers
- ranges where applicable

**Outcomes:**

| Proof result | Persist policy |
|---|---|
| **PASS** (safe subset identified) | Admit only that safe subset to persist constraints; document admit decision |
| **FAIL** (literal match rejects correct SV) | **Narrow:** omit `numbers` from persist constraints; keep suggest path unchanged; do **not** invent a localization/unit-conversion engine in TI.1 |

TI.1 must never block a correct Swedish translation merely to mirror suggest-path behavior. Units-as-prose conversion remains Deferred.

---

## 10. Failure and retry semantics (frozen)

Keep families distinct:

| Category | Example codes | Persist? | Jobs |
|---|---|---|---|
| Provider transport | `aiml_ai_http_error`, 429/5xx | No | Retryable (existing) |
| Provider response-shape / normalization | **`aiml_ai_invalid_response`** (missing segment key, unusable slot, unmappable result) | **No** | Existing disposition for this code — **do not relabel as content-structural** |
| Content structural validation | `empty_target`, `placeholder_mismatch`, `html_structure_mismatch`, optional `number_mismatch` (if admitted), `forbidden_markup`, `url_mismatch` | **No** | **Terminal** — add these codes to `BackgroundTranslationRetryPolicy::terminal_codes()` |
| Persistence | Store `WP_Error` | No | Existing |

**TS2 vs content (required algorithm):**

1. Extract segment by `segment_key` from `ProviderResult`.
2. If missing / misaligned / unusable → `aiml_ai_invalid_response`; stop; no Store.
3. If a string hypothesis is present (including empty string) → `ResponseValidator` content checks → content codes; stop on fail; no Store.
4. Else persist.

**Do not** wrap response-contract failures under a catch-all `aiml_structural_validation_failed` that collapses shape vs content.

**Empty-after-provider (segment present):** use `empty_target`, not `aiml_ai_not_configured`.

**Diagnostics:** stable code + human-readable message; bounded `data` (token / missing_tags / url); **no** raw prompt / full source / full target body logging; no secrets.

**No infinite retries** for deterministic content-structural failures.

---

## 11. Sync / Jobs consistency (frozen)

```text
Workspace REST translate ──┐
                           ├──► translate_segment ► persist_provider_result
Jobs ItemProcessor ────────┘         ▲
   (conflict gate first)             │
                                     ├── TS2 → aiml_ai_invalid_response
                                     └── ResponseValidator → content codes
```

- Same content validator and codes on sync + Jobs
- Jobs conflict policy unchanged
- Content-structural fail → item FAILED, terminal
- Response-contract fail → existing `aiml_ai_invalid_response` mapping

---

## 12. Existing translation preservation (frozen)

A structurally invalid **or** response-contract-invalid new machine translation must **never** destroy or overwrite the prior target state (because Store is not called).

| Prior state | On TI.1 reject | On TI.1 pass |
|---|---|---|
| No row / missing | Unchanged | Write `machine_translated` |
| Prior `machine_translated` | Unchanged | Upsert machine (existing) |
| Prior human / approved / reviewed | Unchanged on reject | **Sync:** existing valid-output overwrite behavior unchanged (**Deferred** outside TI.1). **Jobs:** still conflict-gated |

Do not redesign review/status lifecycle in TI.1.

---

## 13. Batch / provider boundary (frozen)

- Validate **after** normalized `AIProviderInterface::translate_batch` output
- No OpenAI-specific validation in `TranslationService`
- Preserve current single-segment-per-`translate_segment` behavior
- Multi-segment provider results: map by `segment_key`; missing key = TS2 / `aiml_ai_invalid_response`
- Do not redesign provider batching
- `BatchOperationCoordinator` continues per-key aggregation

---

## 14. TQ.0 / H1.0 relationship (frozen)

| Rule | Requirement |
|---|---|
| Official `baseline-v1.1.0` | Immutable |
| Scorer H1.0 | Immutable — never rewrite scores/fingerprints to fit TI.1 |
| Baseline verification | Remains green in CI |
| Candidate compare | Zero new Class A **critical** regressions vs baseline |
| Parity | Persist-path parity tests remain / extend; fake invalid provider must not reach Store |
| Runtime vs H1.0 | Runtime may admit a high-confidence subset; if a new rule lacks H1.0 coverage, document optional future **H1.1** — do not silently mutate H1.0 |
| H1.1 | **Not** implemented in TI.1 planning or by default in TI.1 implementation |

---

## 15. `gut_01` disposition (frozen)

**Deferred from TI.1.**

| Layer | Owner |
|---|---|
| Detection (Class A) | Future **H1.1** |
| Block/warn product policy | **TI.4** |
| Prompt/glossary root cause | **TI.2** and/or **TI.3** |

No brittle special-case regex for instruction leakage in TI.1. **STOP** if implementation attempts one.

---

## 16. Diagnostics / privacy / performance

- Sync: `WP_Error` to existing REST/Workspace surfaces
- Jobs: `last_error_code` / truncated message + audit allowlist
- No new admin UI
- No API keys, Authorization headers, full prompts, or full translation bodies in diagnostics
- Local CPU only; no network; no extra AI; no DB in validator; O(segment length)

---

## 17. Work packages TI1.0–TI1.8

### TI1.0 — Baseline and admission lock

| | |
|---|---|
| **Objective** | Lock TQ.0 Complete, TARGET 6, seam/ownership decisions; no product change |
| **Permitted scope** | Docs / validation log start only |
| **Production files** | None |
| **Tests** | None required |
| **Docs** | Validation log stub |
| **Dependencies** | This plan Architecture Frozen on `main` |
| **Validation** | TARGET 6; no TI.* branches |
| **Rollback** | N/A |
| **STOP** | Any production change; TI.2 start |
| **Completion evidence** | Log records baseline SHAs |

### TI1.1 — Canonical structural policy

| | |
|---|---|
| **Objective** | Codify BLOCK matrix; extend `ResponseValidator` / analyzer for Supported content checks (forbidden tags, URL inventory); **run TS7 SV localization proof suite** |
| **Permitted scope** | `ResponseValidator`, `SegmentConstraintAnalyzer`, unit tests, validation-log TS7 decision |
| **Production files** | `src/Translation/AI/ResponseValidator.php`, `SegmentConstraintAnalyzer.php` |
| **Tests** | Unit: forbidden markup; URL loss; **TS7 fixtures** (decimals, thousands, percent, currency, units, adjacent punctuation, ranges) |
| **Docs** | Validation log: TS7 **admit** or **narrow** |
| **Dependencies** | TI1.0 |
| **Validation** | Unit green; TS7 decision recorded |
| **Rollback** | Revert validator extensions |
| **STOP** | Localization engine / unit conversion scope; `gut_01` special-case; H1.0 mutation |
| **Completion evidence** | Explicit admit/narrow + proof results |

### TI1.2 — Persist-path integration

| | |
|---|---|
| **Objective** | Wire content validator in `persist_provider_result`; map TS2 to `aiml_ai_invalid_response`; fix empty-code |
| **Permitted scope** | `TranslationService` persist path only |
| **Production files** | `src/Workspace/TranslationService.php` |
| **Tests** | Integration: fake invalid content → no Store; missing segment → `aiml_ai_invalid_response` + no Store; valid → persists |
| **Docs** | None required beyond log |
| **Dependencies** | TI1.1 |
| **Validation** | Integration green |
| **Rollback** | Revert wire |
| **STOP** | Store AI validation; prompt changes |
| **Completion evidence** | Persist seam live for sync path |

### TI1.3 — Jobs retry semantics

| | |
|---|---|
| **Objective** | Content-structural codes terminal; no infinite retry; preserve `aiml_ai_invalid_response` disposition |
| **Permitted scope** | `BackgroundTranslationRetryPolicy` (+ processor mapping if needed) |
| **Production files** | `src/Jobs/BackgroundTranslationRetryPolicy.php` |
| **Tests** | Jobs unit/integration: content fail → FAILED terminal; no retry loop |
| **Dependencies** | TI1.2 |
| **Validation** | Jobs tests green |
| **Rollback** | Revert policy list |
| **STOP** | Jobs framework redesign |
| **Completion evidence** | Terminal codes documented + tested |

### TI1.4 — Existing-translation preservation

| | |
|---|---|
| **Objective** | Prove prior row retained on reject; document Deferred sync valid-overwrite |
| **Permitted scope** | Tests + docs clarification |
| **Production files** | None expected (behavior follows no-write) |
| **Tests** | Prior machine/human row unchanged after reject |
| **Dependencies** | TI1.2 |
| **STOP** | Review lifecycle redesign |
| **Completion evidence** | Tests green |

### TI1.5 — Diagnostics / operator surfacing

| | |
|---|---|
| **Objective** | Stable messages/codes on REST + Jobs; audit allowlist intact |
| **Permitted scope** | Message wording; ensure codes surface; no new UI |
| **Production files** | Minimal if any (prefer existing surfaces) |
| **Tests** | Error code/message assertions |
| **Dependencies** | TI1.3 |
| **STOP** | New diagnostics subsystem; body logging |
| **Completion evidence** | Codes visible without secrets |

### TI1.6 — TQ.0 regression / parity

| | |
|---|---|
| **Objective** | Baseline verify; zero new Class A critical regressions; parity extended |
| **Permitted scope** | Quality/parity/unit tests; no H1.0 pack mutation |
| **Production files** | None |
| **Tests** | `quality:verify-baseline`; Quality suite; Store-not-called on invalid fake |
| **Dependencies** | TI1.2 |
| **STOP** | Mutating H1.0 / fingerprints |
| **Completion evidence** | Quality CI green |

### TI1.7 — Acceptance validation

| | |
|---|---|
| **Objective** | Fake-provider reject + valid persist acceptance notes; optional live happy-path only |
| **Permitted scope** | `acceptance/` notes if needed; **no** live AI in normal CI |
| **Dependencies** | TI1.6 |
| **STOP** | Relying on random OpenAI malformed output |
| **Completion evidence** | Acceptance recorded |

### TI1.8 — Documentation closure

| | |
|---|---|
| **Objective** | Mark TI.1 Complete after merge; next planning = TI.2 (or TI.1 vs TI.2 already decided) |
| **Permitted scope** | Docs only |
| **Dependencies** | TI1.0–TI1.7 |
| **STOP** | Starting TI.2 implementation in same milestone |
| **Completion evidence** | Validation log Complete; priorities updated |

**Global rollback:** Revert TI.1 wire commits → prior empty-only persist. No schema/TARGET/data migration.

---

## 18. Acceptance criteria (numbered)

### Candidates and policy

1. TS1 Supported — empty string with present segment blocks persist via `empty_target`.
2. TS2 Supported — missing/misaligned/unusable segment blocks persist via `aiml_ai_invalid_response`.
3. TS2 does not use content-structural codes for response-shape defects.
4. TS3 Supported — placeholder loss blocks persist.
5. TS4 Supported — HTML tag inventory loss blocks persist for HTML format.
6. TS5 Supported — invented dangerous tags block persist.
7. TS6 Partially Supported — absolute URL loss blocks; SKU-like Deferred.
8. TS7 Partially Supported — number persist BLOCK only after localization proof PASSes; else narrowed.
9. Suggest-path parity alone does not admit a persist BLOCK.
10. TS8 Deferred.
11. TS9 Supported — single seam for sync + Jobs.
12. TS10 Supported — content-structural failures terminal for Jobs.
13. TS11 Supported — reject never overwrites prior Store row.
14. TS12 Supported — bounded diagnostics without secrets/bodies.
15. TS13 Supported — TQ.0 baseline/H1.0 immutable; verify green.
16. TS14 Deferred — `gut_01` not TI.1.

### Seam and ownership

17. Canonical seam is `TranslationService::persist_provider_result`.
18. Validation runs after provider success and before Store write.
19. Store does not own AI structural validation.
20. Canonical content validator is production `ResponseValidator` + `SegmentConstraintAnalyzer`.
21. `tests/quality/` code is not promoted into production runtime.
22. Validation is provider-agnostic after `AIProviderInterface` normalization.

### Sync / Jobs

23. Synchronous translate and Jobs share the same content validation seam.
24. Jobs conflict policy remains unchanged.
25. Content-structural Jobs failures are terminal (non-retryable).
26. TS2/`aiml_ai_invalid_response` retains existing Jobs disposition mapping (not forcibly relabeled).
27. No infinite retry loops on deterministic content-structural failures.

### Blocking vs observe

28. Fail closed only for high-confidence low-FP structural failures.
29. Placeholder addition remains OBSERVE (non-blocking).
30. Broken-HTML soft heuristics remain OBSERVE.
31. Unicode/entity heuristics remain OBSERVE/Deferred.
32. Whitespace and length-ratio remain OBSERVE.
33. Glossary compliance remains OBSERVE (not persist BLOCK).
34. source == target remains OBSERVE.
35. Glossary-instruction leakage remains Deferred (not BLOCK).
36. SKU-like heuristic remains Deferred.

### TS7 localization

37. TI1.1 includes explicit SV localization proof fixtures (decimals, thousands, percent, currency, units, adjacent punctuation, ranges).
38. If proof PASSes, only the safe subset is admitted to persist number constraints.
39. If proof FAILs, persist omits `numbers` (narrow) rather than expanding TI.1 into localization/unit conversion.
40. Validation log records explicit TS7 **admit** or **narrow** decision.
41. Correct Swedish translations are not blocked solely to mirror suggest-path behavior.

### Failures and privacy

42. Provider transport failures remain distinct from content-structural failures.
43. Response-shape failures use `aiml_ai_invalid_response` family.
44. Content-structural failures use stable `ResponseValidator` (or admitted extension) codes.
45. Content-structural failures return HTTP 422 (or existing equivalent) with bounded `data`.
46. No catch-all that collapses shape vs content into one opaque code.
47. Empty-after-provider (segment present) uses `empty_target`, not `aiml_ai_not_configured`.
48. No raw prompt / full source / full target body logging in Jobs audits.
49. No API keys or Authorization headers in diagnostics.

### Preservation and batch

50. No prior row: reject leaves Store unchanged.
51. Prior machine row: reject leaves prior unchanged.
52. Prior human/approved row: reject leaves prior unchanged.
53. Jobs conflict-gated rows remain conflict-gated for valid machine attempts.
54. Valid-output sync overwrite of approved content remains Deferred (out of TI.1 redesign).
55. Single-segment-per-call semantics preserved; no provider batching redesign.
56. Missing segment_key in provider result → TS2 / `aiml_ai_invalid_response`.

### TQ.0 / architecture bounds

57. Official `baseline-v1.1.0` pack remains immutable.
58. H1.0 scorer/results remain immutable.
59. `quality:verify-baseline` (or equivalent) remains green.
60. TI.1 introduces zero new Class A critical regressions vs baseline.
61. Persist-path parity tests remain or are extended to cover reject-before-Store.
62. No H1.1 implementation required for TI.1 closure.
63. No brittle `gut_01` special-case rule.
64. No prompt/context redesign.
65. No TM / glossary product redesign.
66. No Jobs framework redesign.
67. No auto-publication.
68. No TI.2–TI.7 implementation in this milestone.
69. `Migrator::TARGET` remains **6**.
70. No schema migration; no new identity family; no Integration API v1 change.
71. Normal CI remains network-free for quality; no live OpenAI in default gates.
72. PHPCS, unit, integration, quality, build remain green.
73. Validator performance remains local CPU, O(segment length), no extra AI/network/DB.
74. No new ADR unless implementation unexpectedly creates a cross-cutting public contract (STOP and escalate).
75. Implementation branch created only after this plan is Architecture Frozen on `main`.

**Acceptance criteria count:** **75**.

---

## 19. Validation strategy

### Unit

- ResponseValidator extensions (forbidden tags, URL)
- TS7 localization fixtures → admit/narrow gate
- Content failure codes
- TS2 → `aiml_ai_invalid_response`
- Retry policy terminal list for content codes

### Integration

- Fake provider: empty / placeholder loss / HTML loss / invented `<script>` / dropped URL → no Store
- Missing `segment_key` → `aiml_ai_invalid_response` + no Store
- Valid translation persists
- Jobs path same outcomes + terminal item status

### TQ.0

- `quality:validate`
- `quality:verify-baseline`
- Quality unit suite
- Compare / zero new critical regressions

### Live

- Not in normal CI
- Optional acceptance: valid live persist + fake invalid reject only

---

## 20. ADR assessment

**No new ADR required**, provided TI.1 remains a narrow runtime-safety extension around existing `ResponseValidator` / `WP_Error` / Jobs retry contracts.

**STOP and escalate (do not silently broaden)** if implementation would require:

- a new cross-plugin public failure API beyond existing codes
- new persistent translation lifecycle states
- a schema-level failure model
- TARGET / identity / Integration API change

Do not create an ADR in the planning freeze task.

---

## 21. STOP conditions

STOP rather than improvise if TI.1 would require:

- Store redesign or Store-owned AI validation
- new identity family / TARGET / schema bump
- second `TranslationService` or second provider pipeline
- prompt / context redesign
- TM-in-generation or glossary redesign
- auto-publication
- new review-state architecture
- Jobs framework redesign
- live AI in normal CI
- mutating TQ.0 H1.0 / baseline fingerprints
- brittle one-off rule solely for `gut_01`
- unconditional number BLOCK justified only by suggest-path parity
- inventing a localization / unit-conversion engine to force TS7 admit
- collapsing response-contract failures into content-structural codes

---

## 22. Risks and limitations

- TS7 literal number match may false-positive on correct SV forms — mitigated by mandatory proof + narrow fallback
- Sync may still overwrite approved content with **valid** machine output — Deferred, pre-existing
- TI.1 may increase Jobs item failure rate where bad structures previously persisted — intended
- `gut_01` remains baseline quality debt until H1.1 / TI.2–TI.4
- Suggest may remain stricter than persist on numbers if TS7 is narrowed — acceptable and explicit

---

## 23. Expected files (implementation later — not this freeze)

| Area | Paths |
|---|---|
| Production | `src/Translation/AI/ResponseValidator.php`, `SegmentConstraintAnalyzer.php`, `src/Workspace/TranslationService.php`, `src/Jobs/BackgroundTranslationRetryPolicy.php` |
| Tests | unit AI validator; integration translate/Jobs reject; Quality parity |
| Docs | this plan; TI.1 validation log at implementation |
| Unchanged | Store AI semantics; OpenAI prompts; TQ.0 H1.0 pack bodies |

This planning freeze creates **docs only**.

---

## 24. Rollback strategy (implementation)

- Revert TI.1 wire commit(s) → prior empty-only persist
- No migration; no TARGET change; no data backfill
- Jobs items failed under TI.1 remain historically accurate

---

## 25. Repository lifecycle

1. Definitive planning (approved) — done
2. Materialize on docs branch — done
3. Independent review — **PASS**
4. Merge / Architecture Frozen on `main` — done
5. Create `feature/ti1-persist-path-structural-safety` — **not started**
6. Implement TI1.0–TI1.8
7. Independent review / merge
8. Validation + docs closure

**Planning freeze is closed.** Implementation begins at step 5 from this frozen plan.

---

## 26. What TI.1 enables next (not designed here)

- TI.2 / TI.3 may improve prompts, context, and glossary-assisted generation without bypassing structural safety
- TI.4 may harden broader deterministic QA block/warn policy atop a trusted persist gate
- TI.3 still depends on both TI.1 and TI.2 per TIQ parent

Do not start TI.2–TI.7 in this freeze.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/TI1_PERSIST_PATH_STRUCTURAL_SAFETY_IMPLEMENTATION_PLAN.md` |
| Parent | `docs/plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md` |
| Revision | 1.1 — 2026-08-10 — Architecture Frozen on `main` after independent review PASS; implementation not started |
| Implementation | **Not started** |
