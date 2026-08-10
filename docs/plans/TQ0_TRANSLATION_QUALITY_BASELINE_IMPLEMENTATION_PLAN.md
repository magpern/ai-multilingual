# TQ.0 — Translation Quality Baseline — Implementation Plan

**Status:** Architecture Frozen (planning) — implementation not started
**Milestone:** TQ.0 — Translation Quality Baseline (TIQ program)
**Kind:** Milestone implementation plan (authoritative after merge to `main`)
**Parent (authoritative program architecture):** [TIQ_PARENT_IMPLEMENTATION_PLAN.md](TIQ_PARENT_IMPLEMENTATION_PLAN.md)
**Planning branch:** `docs/tq0-translation-quality-baseline-plan`
**Implementation branch (after this plan is Architecture Frozen on `main`):** `feature/tq0-translation-quality-baseline` — **not created in this freeze**
**Repository baseline (plan authoring):** `main` @ `5ad61f2dda8b490a280b5caeb493b87adce37ca1`
**Behavior reference (released translator):** tag `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a`
**Schema:** Migrator `TARGET` = **6** (unchanged)
**ADR assessment:** **No new ADR.** Methodology is plan-locked under the TIQ parent. TQ.0 is repository-internal measurement infrastructure, not a new public/runtime contract.
**Related:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md); [CI_RELEASE_BASELINE.md](../CI_RELEASE_BASELINE.md); [TEST_STRATEGY.md](../TEST_STRATEGY.md); [adr/0010-provider-agnostic-interface.md](../adr/0010-provider-agnostic-interface.md); [acceptance/rc/v1-openai-rc.php](../../acceptance/rc/v1-openai-rc.php) (provider RC — remains separate; not replaced by TQ.0)

**Operational success:** The repository can answer, with versioned evidence: *Is a proposed translation-intelligence change actually better than the released v1.1.0 translator, and in what ways?* — without redesigning the translator.

**Hard boundary:** TQ.0 is the **measurement ruler**. It is **not** an intelligence-improvement milestone. It must not redesign `TranslationService`, `AIProviderInterface`, OpenAI provider behavior, prompts, context construction, TM, glossary, Jobs, publication, Store, PluginIdentity, Integration API v1, Router, LanguageContext, SB11, SEO/Woo/Rank Math ownership, schema, or `TARGET`.

---

## 1. Purpose

Establish a repeatable, versioned, auditable **Translation Quality Baseline** so TI.1–TI.7 (and later candidates) can be compared against released **v1.1.0** translation behavior using the same corpus and evaluation methodology.

TQ.0 must:

1. Freeze a product-representative EN→SV corpus (C1.0)
2. Provide deterministic (Class A) scoring that runs in normal CI without network
3. Provide a human (Class B) evaluation rubric and workflow
4. Bound optional advisory model-assisted (Class C) evaluation
5. Define baseline/candidate identity, provenance, and immutable evidence packs
6. Compare baseline vs candidate with dimension-level and case-level regression visibility
7. **Establish the official labeled `baseline-v1.1.0` evidence pack** (TQ0.7 — mandatory for milestone closure)

TQ.0 must **not**:

- Improve prompts, context, TM-in-generation, glossary enforcement, providers, or auto-publication
- Create a second translator / provider abstraction / Store / TM / glossary
- Make normal CI depend on live OpenAI
- Collapse quality into a single opaque aggregate score
- Treat LLM confidence as publication authority

If reliable measurement appears to require redesigning the translator itself: **STOP** and open architectural review against the TIQ parent.

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| `main == origin/main` @ `5ad61f2dda8b490a280b5caeb493b87adce37ca1` | **Pass** |
| Working tree clean | **Pass** |
| [TIQ_PARENT_IMPLEMENTATION_PLAN.md](TIQ_PARENT_IMPLEMENTATION_PLAN.md) Architecture Frozen on `main` | **Pass** |
| Tag `v1.1.0` @ `d9c2336182fa2e0ae0582ead78cc0a346670c92a` | **Pass** |
| Translation-affecting paths `v1.1.0..5ad61f2dd` empty (docs-only after release) at authoring | **Pass** (re-audit before official baseline capture) |
| Migrator `TARGET` = **6** | **Pass** |
| No `tests/quality/` harness yet | **Pass** |
| No TQ.0 / TI.* implementation branches | **Pass** |
| No existing `TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md` before this freeze | **Pass** |

If any precondition regresses before implementation starts: **STOP**.

---

## 3. Repository findings (measurement constraints)

### 3.1 Actual translation path (persist)

```text
WP_Post
  → Extractor / SegmentAssembler
  → TranslationService::translate_segment()
  → TranslationBatch (locales, translate profile, glossary_fragment, ProviderSegment[])
  → AIProviderInterface::translate_batch()  (OpenAIProvider | NullAIProvider)
  → Store::save_translation()  status=machine_translated
```

Key facts for TQ.0:

- **Shared path:** Workspace and Jobs both call `TranslationService::translate_segment()`.
- **Persist path does not run** `ResponseValidator` or Workspace `QAEngine` (suggest path does validate).
- **TM** is suggestions + approval write-back only — **not** injected into generation prompts or Jobs.
- **Glossary** preferred terms may reach the model via `GlossaryService::build_fragment()` (bounded).
- **Prompt user content (v1.1.0):** source/target locales, source text, optional existing target, optional constraints (suggest), optional glossary fragment — **not** field_semantics / object/site context.
- Provider RC harness [`acceptance/rc/v1-openai-rc.php`](../../acceptance/rc/v1-openai-rc.php) remains the frozen **provider** baseline; TQ.0 is the **quality** baseline. Do not redesign the RC harness in TQ.0.

### 3.2 Existing reusable scoring libraries (read-only reuse)

| Component | Path | TQ.0 use |
|---|---|---|
| `SegmentConstraintAnalyzer` | `src/Translation/AI/SegmentConstraintAnalyzer.php` | Class A adapters |
| `ResponseValidator` | `src/Translation/AI/ResponseValidator.php` | Class A adapters (scoring only — do not wire into persist in TQ.0) |
| `QAEngine` + checks | `src/Workspace/QA/` | Class A adapters |
| `NullAIProvider` / `EchoAIProvider` / OpenAI `$http` fake | src + tests | CI fakes |

TI.1 owns wiring structural validation into the persist path. TQ.0 only **measures**.

### 3.3 Host / CI conventions

No host system PHP. Use Docker (`composer:2.8`, `php:8.3-cli`, integration runner per [CI_RELEASE_BASELINE.md](../CI_RELEASE_BASELINE.md)). Normal CI: phpcs, unit, integration, build. TQ.0 must keep that baseline green and network-free for quality jobs.

---

## 4. Architecture invariants (TQ.0)

Inherited from TIQ parent and made milestone-binding:

1. Quality claims for later TI.* require TQ.0 comparison (same corpus + methodology versions, or documented intentional bump with re-baseline).
2. TQ.0 does not redesign intelligence; TI.1 may add persist structural safety later without semantic-quality claims.
3. **One shared** `TranslationService` / `AIProviderInterface` path — no benchmark translator.
4. TQ.0 is the ruler, not a translator redesign.
5. Preserve Integration API v1, Store, PluginIdentity, TARGET **6**, Router, LanguageContext, SB11, A.SEO / Woo / Rank Math ownership.
6. No opaque sole quality score; dimensions remain independently visible.
7. No LLM self-confidence percentage as publication authority.
8. Additional providers Deferred.
9. Semantic/vector TM Deferred.
10. Normal CI does not depend on live OpenAI.

### 4.1 Materialization clarifications (binding)

**Corpus size.** C1.0 starts with approximately **60** evaluated cases. This is an **initial engineering quality baseline** selected for breadth, maintainability, human-review feasibility, and regression usefulness. It is **not** claimed to constitute statistical proof of translation quality. Expansion occurs only through explicit corpus versions (C1.1+).

**Provider request count.** Do **not** assume 60 cases ⇒ 60 OpenAI HTTP calls. Generation must respect actual `TranslationService` / `AIProviderInterface` / `OpenAIProvider` batching semantics. Distinguish and record:

- evaluated **cases**
- **segments** within batches
- provider **batches**
- underlying **HTTP requests**

The harness reproduces the real translation path; it must not impose benchmark-specific request semantics.

**Human disagreement provenance.** For the ~20% dual-review calibration sample, **preserve both original reviewer assessments**. Consensus/resolution is **additive**. Never overwrite individual source reviews with only the consensus result.

**Versioned immutability.** Historical official baseline evidence must never silently change. Legitimate evolution uses explicit versions (`C1.0`→`C1.1`, `H1.0`→`H1.1`/`H2.0`, methodology versions, generation labels). CI detects **unauthorized mutation** of frozen evidence without blocking intentional versioned evolution.

**Generation parity.** Measuring the real **persist** translation behavior is an architectural acceptance requirement. The harness must not become a second translator. Implementation must verify harness generation **inputs** match the production `TranslationService::translate_segment` batch contract (parity tests).

**Field semantics.** `field_semantics` is corpus metadata for categorization, human evaluation, comparison, and reporting. It **MUST NOT** be injected into v1.1.0 generation prompts (it is not part of the real v1.1.0 path). Injecting it would invalidate the baseline.

**TQ0.7 is mandatory.** TQ.0 cannot close until the official v1.1.0 baseline has been generated/captured, provenance-recorded, deterministically scored, human-reviewed, frozen as versioned evidence, and rendered into a usable baseline report. Optional Class C LLM judge is **not** required for TQ.0 closure.

---

## 5. TQ.0 package architecture

```text
Corpus (JSON, versioned)
    → Live generator (explicit/manual; persist-path batch parity; no Store write)
    → Generation fixtures
    → Class A deterministic scorer (CI)
    → Class B human reviews
    → Class C optional LLM judge (advisory)
    → Result manifest + provenance
    → Comparer (baseline vs candidate)
    → JSON + Markdown reports
```

### 5.1 Intended filesystem layout (implementation — not created in this freeze)

| Path | Role |
|---|---|
| `tests/quality/` | Permanent harness root on `main` |
| `tests/quality/corpus/C1.0/` | Manifest, cases, glossary fixture |
| `tests/quality/schemas/` | JSON schemas |
| `tests/quality/src/` | Loaders, scorers, comparer (dev harness code) |
| `tests/quality/bin/` | CLI entrypoints |
| `tests/quality/baselines/baseline-v1.1.0/` | Official frozen evidence pack |
| `tests/quality/candidates/{sha}/` | Candidate evidence packs |
| `tests/quality/reviews/` | Human review sheets / completed reviews |
| `tests/unit/Quality/` | Unit tests for scorers/schema/comparer/parity |
| `acceptance/quality/` | Live generate + optional LLM judge (manual) |

Prefer PHPUnit bootstrap / `autoload-dev` for harness PHP — avoid production runtime autoload changes. Production translation code remains unchanged for TQ.0 behavior.

### 5.2 Generation subject (persist-path)

Live generation builds `TranslationBatch` fields matching `TranslationService::translate_segment`:

- source/target **locales** from language records (as production does)
- prompt profile `translate` / profile version as production
- glossary fragment from corpus glossary fixture via the same fragment builder semantics
- `ProviderSegment` with case `source_text` + `text_format`
- **empty constraints array** (persist path)
- **no** `ResponseValidator` gate (matching persist)
- **no** Store persistence (measurement only)

OpenAI Chat Completions transport remains inside `OpenAIProvider` (per-segment HTTP today). Harness records actual batch/request counts — does not invent alternate batching.

---

## 6. Baseline / candidate identity model

Every result pack records at least:

| Field | Purpose |
|---|---|
| `corpus_version` | e.g. `C1.0` |
| `source_locale` / `target_locale` | e.g. `en_US` / `sv_SE` |
| `subject_kind` | `baseline` \| `candidate` |
| `subject_ref` | e.g. `v1.1.0` or `candidate@abc1234` |
| `subject_sha` | full git SHA |
| `equivalence_evidence` | required when labeling `main@SHA` as behavior-equivalent to `v1.1.0` |
| `provider_id` / `model` | when generation used a provider |
| `prompt_profile` / `prompt_version` | e.g. `translate` / `1` |
| `generation_mode` | `live` \| `replay` |
| `generation_label` | immutable generation id for the fixture set |
| `scorer_version` | e.g. `H1.0` |
| `methodology_version` | e.g. `M1.0` |
| `glossary_fixture_version` | e.g. `G1.0` |
| `cases_evaluated` / `segments` / `batches` / `http_requests` | request accounting |
| `timestamp` | ISO-8601 |
| `token_usage` | optional on live |

First official baseline label: **`baseline-v1.1.0`**. Architecture remains reusable for future release subjects.

**Equivalence rule:** Never silently treat current `main` as identical to `v1.1.0`. Demonstrate via empty translation-affecting diff **or** generate against translator checkout at `v1.1.0` while harness code comes from `main`.

---

## 7. Corpus architecture

### 7.1 Format

**JSON** — matches existing spike corpus practice, easy schema validation, reviewable diffs, PHPUnit-friendly. Not PHP arrays (harder to review) or YAML (absent from first-party corpus trees).

### 7.2 Layout

- `tests/quality/corpus/C1.0/manifest.json` — version, language pair, case index, immutability policy
- `tests/quality/corpus/C1.0/cases/{case_id}.json` — one case per file; **stable IDs**
- `tests/quality/corpus/C1.0/glossary.json` — sanitized preferred terms for reproducible fragments (`G1.0`)

### 7.3 Case fields (minimum)

`id`, `category`, `case_class` (`free` \| `terminology` \| `structural` \| `protected`), `text_format` (`plain` \| `html`), `source_text`, `field_semantics` (**metadata only**), `expected_invariants` (placeholders, URLs, numbers, SKU-like tokens, glossary term ids), optional `reference_sv`, `notes`, `difficulty`.

### 7.4 Versioning / immutability

- Once `C1.0` is used for official `baseline-v1.1.0`, historical case **bodies** must not change.
- Additive cases ⇒ `C1.1+` (minor/patch per policy documented in manifest).
- Methodology-breaking corpus changes ⇒ major bump + required re-baseline.
- CI fails unauthorized mutation of frozen case files / official baseline generations.

### 7.5 Multilingual readiness

Locales and pair are data fields. C1.0 populates **EN→SV** only.

### 7.6 Initial C1.0 composition (~60 cases)

| Category | Count | Notes |
|---|---:|---|
| Woo product titles | 6 | short/long, brand-like, technical |
| Woo short descriptions | 6 | marketing + terminology |
| Woo long descriptions | 6 | HTML-rich paragraphs |
| Scientific / technical terminology | 6 | sanitized fiction (no real customer PII) |
| Marketing copy | 4 | tone/register |
| Navigation / UI-like | 4 | short chrome-like strings as plain segments |
| Taxonomy name/description | 4 | cat/tag style |
| SEO title | 4 | literal meta (not Rank Math `%token%` templates) |
| SEO description | 4 | literal meta |
| Gutenberg / plain body | 4 | headings/paragraphs |
| HTML-rich / lists | 4 | tags, entities |
| Placeholder / token-sensitive | 4 | `{order_number}`, `%s`, bracket tokens |
| Protected / should-not-change | 4 | SKU-like, URLs, pure numbers/units |

Deliberately difficult cases are distributed across categories. Exclude surfaces the pipeline does not translate as Store units (Rank Math template tokens, Deferred attribute values, Elementor HTML-denied controls, etc.).

Corpus content: repository-owned, sanitized, no customer PII, no secrets, no live-site dependency.

---

## 8. Evaluation classes

### 8.1 Class A — deterministic (CI-primary)

Reuse production analyzer/QA algorithms via adapters **for scoring only**.

Severities: `critical` \| `error` \| `warning`.

| Check | Typical severity |
|---|---|
| Empty translation | critical |
| Unexpected source==target (per case flag) | warning/error |
| Placeholder add/loss | critical |
| HTML tag inventory / broken markup | critical/error |
| Number / unit preservation | error |
| URL / SKU-like / protected token preservation | critical |
| Entity / Unicode damage heuristics | error |
| Whitespace anomalies | warning |
| Gross length ratio | warning |
| Glossary preferred-term presence (mechanical, when required) | error |
| Forbidden tag invention (`script`/`iframe`) | critical |

**Hard rule:** Class C (or any LLM) **must not** override Class A failures.

### 8.2 Class B — semantic / human

**Scale:** integer **1–5** per dimension with written anchors (1 = unusable / wrong meaning; 3 = usable with issues; 5 = publish-ready for that dimension).

**Human-scored dimensions (aligned to parent list):** semantic fidelity; omission; hallucination/unsupported addition; terminology accuracy; terminology consistency; fluency/grammar; naturalness; tone/register; technical meaning; formatting/structural fidelity (beyond deterministic); publish readiness (holistic).

**Critical-error flags (binary):** wrong language; meaning inversion; invented claims; corrupted protected tokens; unusable for publish.

**Workflow:**

- Review sheets under `tests/quality/reviews/` (Markdown and/or JSON): case id, source, translation, optional reference, dimension scores, flags, notes.
- **One primary reviewer** for all C1.0 cases.
- **Dual review on ~12 stratified cases (~20%)** for calibration.
- **Preserve both original reviews**; consensus/resolution is additive — never overwrite individuals with consensus-only.

### 8.3 Class C — optional model-assisted (advisory)

- Runner: `acceptance/quality/llm-judge.php` (manual)
- Structured JSON output + evaluator model/prompt/version in manifest
- New `judge_version` writes new files; does not mutate prior judge results
- **Not required** for TQ.0 closure
- Must not clear Class A failures
- Must not become publish authority / confidence-% gate
- Not ground truth

---

## 9. Required quality dimensions → measurement mapping

| # | Dimension | Primary mechanisms |
|---|---|---|
| 1 | Semantic completeness / meaning preservation | B (+ C advisory) |
| 2 | Terminology correctness | B + A (glossary mechanical) |
| 3 | Glossary compliance | A + B |
| 4 | Hallucination / unsupported additions | B (+ C) |
| 5 | Omission | B (+ C) |
| 6 | HTML / markup preservation | A + B |
| 7 | Placeholder / token preservation | A |
| 8 | Numbers / units preservation | A |
| 9 | URLs / identifiers / SKU-like protected values | A |
| 10 | Language correctness | B (+ light A heuristics) |
| 11 | Field-appropriate style | B |
| 12 | Field-appropriate length / conciseness | A length-ratio warning + B |

**Aggregate convenience score (optional):** mean of non-critical human dimensions excluding cases with any critical A/B flag; labeled `aggregate_convenience`. **Never** sole evidence; weighting must be explicit if used.

---

## 10. Comparison / regression model

Comparer requires matching `corpus_version` and scoring/methodology versions (or documented intentional bump).

Reports must answer:

- which cases improved / regressed / unchanged
- which dimensions improved / regressed
- whether any **new critical deterministic** failures were introduced
- distribution by corpus category (Woo, SEO, HTML, terminology, protected, …)
- visibility of regressions first in human Markdown report

**Advancement semantics established by TQ.0:**

| Gate | Rule |
|---|---|
| Hard | Zero new Class A **critical** regressions vs baseline |
| Hard | Corpus + methodology versions compatible |
| Claim | Any “quality improved” statement requires dimension tables + human affirmation for claimed semantic dimensions |
| Deferred | Arbitrary numeric semantic cutoffs (e.g. “≥87%”) until post-baseline evidence justifies them |

---

## 11. TM metric separation

For v1.1.0 generation, record `tm_observed: false` (TM not in generation path). Optional future `tm_metrics` side channel must **never** blend into AI quality aggregates. TI.3 owns TM/glossary intelligence work.

---

## 12. Live-provider / replay strategy

| Mode | Location | Network |
|---|---|---|
| Validate / score / compare / report | `tests/quality` + unit CI | **none** |
| Live generate baseline/candidate | `acceptance/quality/generate.php` | OpenAI via existing settings/vault — credentials never committed |
| Live LLM judge | `acceptance/quality/llm-judge.php` | optional |

Promotion path: live staging outputs → review → copy into `baselines/` or `candidates/{sha}/` as immutable fixtures (translations + safe metadata only; no API keys; no sensitive full prompt dumps).

---

## 13. Provenance, versioning, re-scoring

**Manifest** ties corpus, subject, provider/model, generation mode/label, scorer version, methodology version, glossary fixture version, request accounting, timestamps.

**Non-destructive re-scoring:** new `H` version writes `scores.H2.0.json` alongside `scores.H1.0.json` — never overwrite.

**Generation fixtures** labeled official are immutable; new captures get new generation labels / directories.

---

## 14. Result artifact model

```text
tests/quality/baselines/baseline-v1.1.0/
  manifest.json
  generations.jsonl
  scores.H1.0.json
  human.B1.0.json          # includes dual-review originals + additive consensus
  judge.C1.0.json          # optional
  REPORT.md
tests/quality/candidates/{sha}/
  ... same shape ...
```

Machine-readable: JSON / JSONL. Human: `REPORT.md` with regressions first. No WordPress admin quality dashboard required for TQ.0.

---

## 15. Developer / CLI workflow

Docker-friendly CLIs under `tests/quality/bin/`:

| Command | Purpose |
|---|---|
| `quality-validate` | Schema + corpus integrity |
| `quality-generate` | Live only (acceptance wrapper) |
| `quality-score` | Deterministic (+ load human/judge files) |
| `quality-compare` | Baseline vs candidate |
| `quality-report` | Markdown report |

Composer dev scripts: `quality:validate`, `quality:score`, `quality:compare` — **not** live generate in default CI.

---

## 16. CI integration

**Add to Tier 0 (bounded, network-free):**

- corpus schema validation
- scorer unit tests
- deterministic replay score of frozen `baseline-v1.1.0` generations (after TQ0.7)
- comparer self-tests
- unauthorized frozen-evidence mutation detection

**Never in normal CI:** live OpenAI, LLM judge, full human review.

Do not weaken existing phpcs / unit / integration / build jobs or the release ZIP audit path.

---

## 17. Official v1.1.0 baseline-establishment procedure (TQ0.7)

1. Prove translator subject corresponds to `v1.1.0` behavior (diff evidence or tag checkout for translator code).
2. Record equivalence evidence in the manifest.
3. Live-generate C1.0 with persist-path parity (profile `translate`, corpus glossary fixture, production-like model settings).
4. Capture provider/model, profile versions, token usage, timestamps, and request accounting (cases/segments/batches/HTTP).
5. Run deterministic scorer `H1.0`; freeze `scores.H1.0.json`.
6. Complete human reviews `B1.0` (full primary + ~20% dual with preserved originals).
7. Optionally run Class C judge (not required for closure).
8. Freeze `tests/quality/baselines/baseline-v1.1.0/` including `REPORT.md`.
9. Document the pack as the official TIQ comparison baseline.
10. Future candidates use `quality-compare` against this pack.

**Official ≠ “tests passed”.** Official = labeled immutable evidence pack usable for candidate comparison.

---

## 18. Security / privacy

- Synthetic/sanitized corpus only; no customer PII; no credentials/secrets
- No live-site dependency for corpus content
- Result artifacts: translations + safe metadata only
- Strip Authorization and secrets from any logged transport metadata
- Acceptance runners read vault/env only; never commit keys

---

## 19. Performance / cost bounds

| Item | Bound |
|---|---|
| C1.0 cases | ~60 evaluated cases |
| CI deterministic score | target under ~30s in unit job |
| Live generate | respects real batching; expect on the order of tens of provider HTTP calls once per capture — record actuals |
| Human review | ~60 primary sheets; ~12 dual |
| Artifact size | JSON/JSONL — low megabytes |

---

## 20. Work packages TQ0.0–TQ0.8

Implementation order follows dependencies. **None are implemented in this planning freeze.**

### TQ0.0 — Baseline and architecture lock

| | |
|---|---|
| **Objective** | Skeleton `tests/quality/` README + pointer docs confirming measurement-only scope and parity rules |
| **Permitted scope** | Docs + empty harness README tree; no generation yet |
| **Expected files** | `tests/quality/README.md`; optional TEST_STRATEGY pointer |
| **Dependencies** | This plan Architecture Frozen on `main` |
| **Tests/validation** | Docs link check; no runtime behavior change |
| **Acceptance** | README states ruler-only scope, persist-path parity, CI offline rule |
| **STOP** | Any production translator change |
| **Completion evidence** | Merged skeleton commit on implementation branch |

### TQ0.1 — Corpus contract + C1.0

| | |
|---|---|
| **Objective** | JSON schemas, C1.0 manifest/cases/glossary (~60), case_class tagging |
| **Permitted scope** | `tests/quality/corpus`, `schemas` |
| **Dependencies** | TQ0.0 |
| **Tests** | Schema validation unit tests; corpus integrity CLI |
| **Acceptance** | Coverage table satisfied; no PII/secrets; stable IDs; EN→SV; multilingual-ready fields |
| **STOP** | Live-site scraping into corpus; injecting field_semantics into generation design |
| **Completion evidence** | C1.0 tree + passing validate |

### TQ0.2 — Deterministic quality engine

| | |
|---|---|
| **Objective** | Class A scorer with severities; adapters over existing analyzers/QA logic |
| **Permitted scope** | `tests/quality/src`, `tests/unit/Quality` |
| **Dependencies** | TQ0.1 |
| **Tests** | Unit tests per check; fixture pairs |
| **Acceptance** | Critical/error/warning semantics; LLM cannot override A |
| **STOP** | Wiring validator into production persist path (TI.1) |
| **Completion evidence** | Unit suite green |

### TQ0.3 — Result / provenance / comparison model

| | |
|---|---|
| **Objective** | Manifest schema, generations fixtures model, comparer, immutability/re-score rules |
| **Permitted scope** | schemas, loaders, comparer, unit tests |
| **Dependencies** | TQ0.2 |
| **Tests** | Comparer fixtures; mutation-detection tests |
| **Acceptance** | Identity fields complete; non-destructive re-score; version evolution supported |
| **STOP** | Overwriting historical scores/generations |
| **Completion evidence** | Compare self-test green |

### TQ0.4 — Human evaluation rubric / workflow

| | |
|---|---|
| **Objective** | Rubric anchors, review sheet templates, dual-review provenance protocol |
| **Permitted scope** | `tests/quality/reviews`, docs in quality tree |
| **Dependencies** | TQ0.1 |
| **Tests** | Template schema validation |
| **Acceptance** | 1–5 anchors; critical flags; dual-review originals preserved; additive consensus |
| **STOP** | Consensus-only overwrite of individual reviews |
| **Completion evidence** | Rubric + templates merged |

### TQ0.5 — Optional model-assisted evaluator boundary

| | |
|---|---|
| **Objective** | Advisory LLM-judge acceptance stub + versioning rules |
| **Permitted scope** | `acceptance/quality/llm-judge.php` (+ docs) |
| **Dependencies** | TQ0.3 |
| **Tests** | Contract tests with fake responses; no CI live calls |
| **Acceptance** | Advisory-only; no override of A; not required for TQ.0 exit |
| **STOP** | Making Class C mandatory or CI-live |
| **Completion evidence** | Stub + docs |

### TQ0.6 — CLI / reporting / CI integration

| | |
|---|---|
| **Objective** | CLIs + Markdown/JSON reports + Tier 0 quality steps (network-free) |
| **Permitted scope** | `tests/quality/bin`, composer scripts, `.github/workflows/ci.yml`, TEST_STRATEGY pointer |
| **Dependencies** | TQ0.2, TQ0.3 |
| **Tests** | CLI smoke in unit/docker; CI job green offline |
| **Acceptance** | validate/score/compare/report work; no live OpenAI in CI |
| **STOP** | Paid provider in normal CI |
| **Completion evidence** | CI green with quality steps |

### TQ0.7 — Establish official v1.1.0 quality baseline (**mandatory**)

| | |
|---|---|
| **Objective** | Capture/freeze `baseline-v1.1.0` evidence pack |
| **Permitted scope** | acceptance generate; `tests/quality/baselines/baseline-v1.1.0/`; human reviews |
| **Dependencies** | TQ0.4, TQ0.6 (and TQ0.1–0.3) |
| **Tests/validation** | Equivalence audit; deterministic scores; human B1.0 complete; REPORT.md |
| **Acceptance** | All §17 steps; pack usable for candidate compare; Class C optional |
| **STOP** | Closing TQ.0 without this pack; unlabeled “tests passed” substitute |
| **Completion evidence** | Frozen baseline directory + report on `main` |

### TQ0.8 — Acceptance + documentation closure

| | |
|---|---|
| **Objective** | Validation log vs §21 criteria; update TIQ/TQ.0 status to Complete; confirm CI green |
| **Permitted scope** | docs only (+ log) |
| **Dependencies** | TQ0.7 |
| **Acceptance** | Milestone exit gate (§22) satisfied; implementation not leaking into TI.1 |
| **STOP** | Starting TI.1 before TQ.0 Complete |
| **Completion evidence** | Closure docs commit on `main` |

---

## 21. Architecture acceptance criteria

Numbered criteria for TQ.0 closure. Do not pad.

### Corpus

1. C1.0 corpus exists as repository-owned JSON with schema validation.
2. Corpus version id is explicit (`C1.0`) and recorded in manifests.
3. Every case has a stable unique `id`.
4. Every case has `category`, `case_class`, `text_format`, and `source_text`.
5. `case_class` ∈ {`free`, `terminology`, `structural`, `protected`}.
6. EN→SV is the first populated language pair; locale fields are multilingual-ready.
7. Approximate C1.0 size is ~60 evaluated cases per §7.6 composition.
8. Corpus is documented as an engineering baseline, not statistical proof of quality.
9. Corpus expansion requires an explicit new corpus version.
10. Historical C1.0 case bodies used by official baseline are immutable.
11. Corpus contains no customer PII and no credentials/secrets.
12. Corpus has no live-site runtime dependency.
13. `field_semantics` is metadata only and is not injected into v1.1.0 generation prompts.
14. Protected/non-translatable expectations are expressible via `expected_invariants` / `protected` class.

### Identity and provenance

15. Baseline identity fields (§6) are present on official packs.
16. Candidate identity fields (§6) are present on candidate packs.
17. Behavior equivalence to `v1.1.0` is demonstrated, not assumed, when labeling main-equivalent subjects.
18. Provider id and model are recorded for live generations.
19. Prompt profile and prompt version are recorded.
20. Generation mode (`live`/`replay`) is recorded.
21. Scorer version and methodology version are recorded.
22. Glossary fixture version is recorded when fragments are used.
23. Request accounting distinguishes cases, segments, batches, and HTTP requests.
24. Timestamps are recorded for live captures.
25. Token usage is captured when available on live runs.

### Generation parity

26. Harness generation targets persist-path batch semantics of `TranslationService::translate_segment`.
27. Harness does not apply suggest-path `ResponseValidator` as a generation gate for the v1.1.0 baseline.
28. Automated parity tests verify harness batch field construction against the production persist-path contract.
29. Harness does not persist translations to the Store during measurement.
30. Harness is not a second translator or second provider abstraction.

### Class A

31. Deterministic scorer implements the Class A check set in §8.1.
32. Severities distinguish critical / error / warning.
33. Critical deterministic failures are first-class in reports.
34. Class A runs fully offline in normal CI.
35. Class A failures cannot be overridden by Class C.

### Class B

36. Human rubric uses 1–5 scales with written anchors.
37. Critical-error flags are independent of 1–5 scores.
38. Review sheets capture case id, source, translation, scores, flags, notes.
39. Full C1.0 primary human review is defined for official baseline.
40. Dual-review covers ~20% stratified cases for calibration.
41. Both original dual-review assessments are preserved.
42. Consensus/resolution is additive and does not overwrite originals.

### Class C

43. Model-assisted evaluation is optional and advisory.
44. Evaluator provider/model/prompt/version are recorded when used.
45. Class C is not required for TQ.0 closure.
46. Class C must not become publish authority or confidence-% gate.

### Dimensions and aggregate

47. All twelve parent-required dimensions are independently reportable.
48. Dimension mapping in §9 is implemented in reporting.
49. Optional aggregate is explicitly labeled convenience-only.
50. Aggregate cannot hide critical failures.

### Comparison and gates

51. Comparer produces improved / regressed / unchanged case outcomes.
52. Comparer produces dimension-level deltas.
53. Comparer surfaces new critical deterministic regressions.
54. Category rollups are available (at least Woo / SEO / HTML / terminology / protected).
55. Human Markdown report prioritizes regressions.
56. Hard gate: zero new Class A critical regressions vs baseline for candidate advancement claims under TQ.0 semantics.
57. Semantic quality-improvement claims require TQ.0 dimension evidence + human affirmation for claimed semantic dimensions.
58. No arbitrary sole percentage threshold is invented without evidence.

### TM separation

59. TM reuse metrics are not blended into AI quality aggregates.
60. v1.1.0 baseline records that TM was not part of generation (`tm_observed: false` or equivalent).

### Live vs CI

61. Normal CI requires no provider network and no API keys.
62. Live OpenAI generation is explicit/manual under `acceptance/quality/`.
63. Existing provider RC harness remains untouched as the provider baseline.
64. Existing phpcs/unit/integration/build CI remains green.

### Versioning and immutability

65 65. Scorer re-scoring is non-destructive (new version files).
66. Official generation fixtures are immutable once labeled.
67. CI detects unauthorized mutation of frozen official evidence.
68. Explicit version evolution (C/H/M/generation labels) remains possible.

### Security, cost, closure

69. Results contain no API keys or secrets.
70. Runtime/cost bounds in §19 are respected as design targets.
71. Official `baseline-v1.1.0` evidence pack exists and is usable for candidate comparison.
72. TQ0.7 steps in §17 are complete before TQ.0 closure.
73. No production redesign of TranslationService / providers / prompts / TM / glossary / Jobs / Store / identity / Integration API / Router / LanguageContext / SEO-Woo-RankMath ownership for TQ.0.
74. `Migrator::TARGET` remains **6**.
75. TQ.0 implementation plan lifecycle followed: plan freeze on main before coding; work packages TQ0.0–TQ0.8.

**Acceptance criteria count:** **75** substantive criteria (within the intended 55–70+ band; not padded with trivia).

---

## 22. TQ.0 milestone exit gate

TQ.0 is **Complete** only when all of the following are true:

1. Canonical C1.0 corpus exists and validates
2. Deterministic scorer exists and is CI-safe (offline)
3. Human rubric and review workflow exist
4. Result / provenance model exists
5. Comparison and reporting work
6. Normal CI requires no provider network
7. Official v1.1.0 generation evidence has been captured
8. Deterministic v1.1.0 scores are frozen
9. Required human v1.1.0 review is complete (primary + dual sample with provenance rules)
10. Baseline report is frozen and usable for candidate comparison
11. Architecture invariants and §21 criteria are verified
12. Repository CI remains green

Only then may later TI milestones use TQ.0 as evidence for quality-improvement claims.

Optional Class C judge may be absent at closure.

---

## 23. STOP conditions

**STOP** (do not improvise) if implementation would require:

- a second `TranslationService`
- a second provider abstraction / benchmark-specific translator
- Store redesign
- PluginIdentity redesign or new identity family
- Integration API v1 change
- `TARGET` / schema change
- semantic / vector TM
- glossary redesign (as product behavior change)
- Jobs redesign
- publication / auto-publish redesign
- normal-CI live OpenAI dependency
- hidden aggregate-only quality scoring
- LLM confidence as publication authority
- customer PII or secrets in corpus/results
- silent historical baseline / generation / score mutation
- injecting non-v1.1.0 prompt context (e.g. `field_semantics`) into the official v1.1.0 baseline generation
- closing TQ.0 without TQ0.7 official evidence pack

---

## 24. ADR assessment

**No new ADR** for TQ.0. Methodology is plan-locked here under the TIQ parent. Revisit only if TQ.0 becomes a cross-cutting public/runtime contract (unexpected). ADR-0010 extensions remain deferred to TI.2/TI.3 if needed for context/TM-in-batch — out of TQ.0 scope.

---

## 25. Risks and limitations

| Risk | Mitigation |
|---|---|
| Human subjectivity | Anchors + dual-review sample + preserved originals |
| Model drift over time | Pin model id in manifest; document |
| Harness/production batch drift | Mandatory parity tests; update when `translate_segment` changes |
| Small corpus | Explicit non-statistical framing; versioned expansion |
| Suggest vs persist validator asymmetry | Baseline measures **persist** path by design |

---

## 26. Files expected during implementation (not this freeze)

**Create later:** `tests/quality/**`, `tests/unit/Quality/**`, `acceptance/quality/**`, TQ.0 validation log at closure.
**Modify later (minimal):** `composer.json` scripts; `.github/workflows/ci.yml`; possibly `docs/TEST_STRATEGY.md`.
**Do not modify for TQ.0 behavior:** production translation/provider/TM/glossary/Jobs/Store paths.

This planning freeze creates **docs only**.

---

## 27. Validation strategy (implementation)

- Unit: scorers, schema, comparer, batch parity
- CI: validate + replay score official baseline (offline)
- Manual: live generate + human review (TQ0.7)
- Closure: validation log against §21 + §22; parent TQ.0 exit checklist

---

## 28. Repository lifecycle

1. Planning (Cursor) — done
2. Plan approval — done
3. **Materialize on docs branch** — this document
4. Independent review
5. Merge / Architecture Frozen on `main`
6. Create `feature/tq0-translation-quality-baseline`
7. Implement TQ0.0–TQ0.8
8. Independent review / merge
9. Validation + docs closure

**This task stops after step 3 (push for review).** Implementation must wait for step 5.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md` |
| Parent | `docs/plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md` |
| Revision | 1.0 — 2026-08-10 — Architecture Frozen (planning) on `docs/tq0-translation-quality-baseline-plan` |
| Implementation | **Not started** |
