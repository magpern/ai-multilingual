# TI.2 — Bounded Translation Context — Implementation Plan

**Status:** **Complete** on `main`
**Milestone:** TI.2 — Bounded Translation Context (TIQ program)
**Kind:** Milestone implementation plan (authoritative on `main`)
**Parent:** [TIQ_PARENT_IMPLEMENTATION_PLAN.md](TIQ_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** TQ.0 **Complete**; TI.1 **Complete** on `main` @ `5bb7cd2679d122369f7f1148885c635ec9b87458`
**Official pack:** `tests/quality/baselines/baseline-v1.1.0/` · C1.0 · H1.0
**Schema:** Migrator `TARGET` = **6** (unchanged)
**ADR:** Focused **ADR-0010 amendment** — optional typed `TranslationContext` on `TranslationBatch`; context is not Store identity / `source_hash`
**Planning branch:** `docs/ti2-bounded-translation-context-plan` (merged)
**Independent review (planning):** **PASS** (2026-08-10)
**Freeze merge:** `bc79366a8d8ec10a8370a4521904dd9c7ba9fb69`
**Implementation branch:** `feature/ti2-bounded-translation-context` @ `6f35a005b0299f0a7a69044e591d6a3475b79edd`
**Independent review (implementation):** **PASS** (2026-08-10)
**Merge commit:** `80dfdcf18a93f168370aa1bb6a03d7c6dd8376fa`
**Validation log:** [TI2_BOUNDED_TRANSLATION_CONTEXT_VALIDATION_LOG.md](TI2_BOUNDED_TRANSLATION_CONTEXT_VALIDATION_LOG.md)

**Operational success:** The shared translation pipeline receives allowlisted, typed, size-capped field/object context and safer glossary/instruction packaging—without page dumps, a second translator, TM-in-generation, or Store/hash redesign.

**Hard boundary:** TI.2 is **bounded translation context**. It is not TM reuse (TI.3), glossary intelligence redesign (TI.3), QA block/warn (TI.4), site-style Settings product, Jobs redesign, provider expansion, auto-publication, or context-aware Store staleness.

---

## 1. Executive summary

TI.2 extends the one shared brain:

`TranslationService` → `TranslationBatch` (+ optional `TranslationContext`) → `AIProviderInterface`

so providers receive useful, bounded context and glossary instructions are clearly separated from source text (addressing the transport root of `gut_01`).

**Canonical ownership:** build context once in `TranslationService` after assemble, before `translate_batch`. Sync and Jobs inherit the same path. Providers render the typed DTO; `TranslationService` does not format OpenAI messages.

---

## 2. Repository findings (evidence)

| Finding | Evidence |
|---|---|
| Provider gets locales + source (+ suggest extras) + glossary lines only | [`OpenAIProvider::build_user_prompt`](../../src/Translation/AI/Providers/OpenAIProvider.php) |
| Assembler `meta` / field labels / segment_key not in prompt | [`TranslationService`](../../src/Workspace/TranslationService.php) |
| Glossary label `"Glossary terminology (use consistently):"` in user message | Root of **gut_01** Class B critical |
| Jobs call only `translate_segment()` | [`BackgroundTranslationItemProcessor`](../../src/Jobs/BackgroundTranslationItemProcessor.php) |
| No production FieldSemantic; C1.0 metadata-only | TQ.0; [`PersistPathBatchBuilder`](../../tests/quality/src/PersistPathBatchBuilder.php) |
| `source_hash` = SHA1(normalize(source_text, format)) only | [`Store::source_hash`](../../src/Translation/Store.php) |
| No site tone in Settings | [`Settings.php`](../../src/Settings.php) |

---

## 3. Current provider request lifecycle

```text
Workspace | Jobs
  → TranslationService::translate_segment / suggest_segment
  → SegmentAssembler DTO + GlossaryService::build_fragment
  → TranslationBatch + ProviderSegment[]
  → AIProviderInterface::translate_batch
  → OpenAIProvider (system = profile; user = locales/source/glossary)
  → TI.1 persist_provider_result (translate path)
```

---

## 4. Current context inventory

| Available at translate time | In prompt today? |
|---|---|
| Locales | Yes |
| Source text | Yes |
| Glossary fragment | Yes (poorly framed) |
| Suggest constraints / existing target | Suggest only |
| field_key, segment_key, meta, post type | No |
| Sibling segments / taxonomies / attributes | Resolvable — unused |
| Site tone config | Does not exist |

---

## 5. TQ.0 evidence relevant to context

| Signal | TI.2 implication |
|---|---|
| gut_01 Class B critical — glossary scaffold echoed | Packaging/separation in TI.2 |
| Class A 0 critical; Woo/SEO Class B ~flat 4.x | Do not claim broad wins without candidate evidence |
| field_semantics in prompt: false on baseline | Injecting semantics = new subject; TQ.0 compare required |
| C1.0 already labels product/SEO/UI roles | Seed closed runtime enum; C1.0 bodies immutable |

---

## 6. TI.2 ownership model

| Concern | Owner |
|---|---|
| Context contract + builder | `src/Translation/AI/`; invoked from `TranslationService` |
| Prompt rendering | Provider implementations |
| Field-semantic mapping | Deterministic mapper |
| Glossary matching/priority | Unchanged (TI.3) |
| Structural persist gate | Unchanged (TI.1) |
| Measurement | TQ.0 harness; C1.0/H1.0 immutable |

---

## 7. Canonical bounded-context contract

```text
TranslationBatch {
  …existing…
  context: TranslationContext|null
}

TranslationContext {
  schema_version: "1"
  field_semantic: FieldSemantic   // closed enum
  object_type: string             // bounded
  object_title: string            // capped
  items: ContextItem[]            // allowlisted types
  provenance: { item_types[], truncated: bool, char_count: int }
}

ContextItem { type, label?, value }
```

**Single build site:** `TranslationService` after `assemble_one`, before `translate_batch`.
**Fallback:** `context = null` — degrades safely; packaging fix still applies when glossary present.
**Forbidden:** WooContext / SeoContext / JobsContext / second pipeline.

---

## 8. Candidate matrix TC1–TC14

| ID | Candidate | Disposition |
|---|---|---|
| TC1 | Field semantics | **Supported** |
| TC2 | Source object type | **Supported** |
| TC3 | Source object title | **Supported** |
| TC4 | Parent/neighbor fields | **Partially Supported** (deterministic pairs only) |
| TC5 | Woo category context | **Partially Supported** (names ≤3) |
| TC6 | Woo attributes | **Partially Supported** (names ≤5) |
| TC7 | Site tone/style | **Deferred** |
| TC8 | Locale nuance | **Partially Supported** (optional language names) |
| TC9 | SEO field semantics | **Supported** |
| TC10 | Bounded surrounding text | **Deferred** |
| TC11 | Glossary fragment packaging | **Supported** |
| TC12 | Context size/token budget | **Supported** |
| TC13 | Privacy/redaction | **Supported** |
| TC14 | Provenance/debug | **Supported** |

Do not implement Deferred candidates.

---

## 9. Field-semantics model

**Closed enum** (unknown → `generic`):

`product_title`, `product_short_description`, `product_long_description`, `seo_title`, `seo_description`, `seo_social_title`, `seo_social_description`, `term_name`, `term_description`, `ui_label`, `heading`, `body`, `attribute_label`, `marketing`, `generic`

**Deterministic mapping** from `field_key` / `segment_key` / integration id / post_type (see approved plan §9). No free-string taxonomy in prompts.

---

## 10–12. Object / sibling / Woo / SEO

- **Object:** type + capped title; no IDs/emails/status.
- **Sibling (TC4):** product short/long → title; SEO → object title; term description → term name. Max 1–2 items.
- **Woo:** category names ≤3; attribute names ≤5. Never stock/price/SKU values/order/customer/IDs.
- **SEO:** semantic + purpose (`search_snippet` / `social_snippet`) + object title. No Rank Math ownership/emission changes.

---

## 13–15. Site style / locale / gut_01

- **TC7 Deferred** — no Settings/schema product.
- **TC8 Partial** — locales + optional language display names from `Languages`.
- **gut_01:** TI.2 owns safer packaging/source isolation only. TI.3 = glossary intelligence. H1.1/TI.4 = detection/policy. **No phrase-regex hack.**

---

## 16–17. Prompt boundary and budgets

Bump prompt/context schema version; record on TQ.0 generation fixtures.

**Provider-rendered sections (order):** locales → field role/purpose → object items → glossary (do-not-copy) → **source text (never truncated for context)**.

| Limit | Value |
|---|---|
| Total optional context chars (excl. source + glossary body) | 1200 |
| object_title | 200 |
| Per ContextItem value | 200 |
| Max ContextItems | 8 |
| Categories | ≤3 |
| Attribute names | ≤5 |
| Glossary | existing 40 terms / 4000 chars |

**Drop priority:** attributes → categories → sibling → keep object title longer → always keep field_semantic → glossary uses existing truncation. **Never drop/truncate source.**

---

## 18–21. Privacy, provenance, provider, Jobs

**Allowlist:** extract-path siblings; term names; Woo category/attribute names; Rank Math unit labels; assembler meta labels.
**Deny:** arbitrary postmeta, user/order/customer data, credentials, private notes.

**Provenance:** schema_version, field_semantic, item types, truncated, char_count — no full context bodies in audits.

**Provider-agnostic:** context on batch; OpenAI renders; Null/Scripted safe when absent.

**Jobs:** no second builder.

---

## 22. Context / staleness (frozen)

**TI.2 does not change `source_hash` / `is_stale`.** Automatic freshness remains source-text-only. Context-aware invalidation is a future ADR if required. **STOP** if Store hash redesign is attempted.

---

## 23–24. TQ.0 evaluation and success

- C1.0 / H1.0 / `baseline-v1.1.0` **immutable**
- Candidate: context ON + packaging fix on C1.0
- Compare: zero new Class A criticals
- gut_01: must not echo glossary scaffold; Class B re-check
- Optional additive **C1.1** only; never rewrite C1.0
- Success: no structural regression; targeted leakage/semantic evidence; budgets; sync+Jobs parity; provenance

---

## 25. Work packages TI2.0–TI2.8

### TI2.0 — Baseline / admission lock

| | |
|---|---|
| Objective | Lock plan/ADR on main; TARGET 6; no product change |
| Production files | None |
| Docs | Validation log start |
| Dependencies | Plan Architecture Frozen on main |
| STOP | Any production change; TI.3 start |
| Completion | Log records baseline SHAs |

### TI2.1 — Bounded context contract

| | |
|---|---|
| Objective | `TranslationContext`, `FieldSemantic`, `ContextItem`, budget constants |
| Production | `src/Translation/AI/*` |
| Tests | Unit DTO/enum |
| Dependencies | TI2.0 |
| STOP | Free-string taxonomy |
| Completion | Types green |

### TI2.2 — Context extraction / building

| | |
|---|---|
| Objective | Allowlisted builder + mappers (post/Woo/SEO/term) |
| Production | Context builder class(es) |
| Tests | Mapping, bounds, denylist |
| Dependencies | TI2.1 |
| STOP | Arbitrary meta |
| Completion | Builder unit green |

### TI2.3 — TranslationService / provider DTO integration

| | |
|---|---|
| Objective | Wire builder; extend `TranslationBatch` |
| Production | `TranslationService`, `TranslationBatch` |
| Tests | Sync path context present/absent |
| Dependencies | TI2.2 |
| STOP | Second brain |
| Completion | Batch carries context |

### TI2.4 — Prompt / context separation

| | |
|---|---|
| Objective | Glossary packaging + source isolation; version bump |
| Production | `OpenAIProvider`, `PromptProfileRegistry` |
| Tests | Prompt section unit; gut_01 packaging fixtures |
| Dependencies | TI2.3 |
| STOP | Unrelated full prompt rewrite; phrase-regex gut_01 |
| Completion | Version bumped; packaging tests green |

### TI2.5 — Jobs / context parity

| | |
|---|---|
| Objective | Prove Jobs inherits same builder path |
| Production | None expected beyond existing call path |
| Tests | Jobs integration |
| Dependencies | TI2.3 |
| STOP | Jobs redesign |
| Completion | Parity tests green |

### TI2.6 — TQ.0 candidate evaluation

| | |
|---|---|
| Objective | Harness uses real context path; candidate vs baseline |
| Production | Quality harness only |
| Tests | quality validate/verify/compare |
| Dependencies | TI2.4 |
| STOP | Mutate baseline/H1.0/C1.0 |
| Completion | 0 new Class A critical; gut_01 scaffold gone |

### TI2.7 — Acceptance

| | |
|---|---|
| Objective | Fake-provider + optional live notes |
| Docs | `acceptance/ti2/` |
| Dependencies | TI2.6 |
| STOP | Live AI in CI |
| Completion | Acceptance recorded |

### TI2.8 — Documentation closure

| | |
|---|---|
| Objective | Mark TI.2 Complete after merge; next = TI.3 planning gate |
| Docs only | |
| Dependencies | TI2.0–TI2.7 |
| STOP | Start TI.3 implementation in same milestone |
| Completion | Validation log Complete |

**Rollback:** revert commits; context null-safe; no migration.

---

## 26. Acceptance criteria (numbered)

1. TC1 Supported — closed FieldSemantic mapped deterministically.
2. TC2 Supported — object_type populated when resolvable.
3. TC3 Supported — object_title capped when resolvable.
4. TC4 Partial — only admitted sibling pairs; no page walk.
5. TC5 Partial — ≤3 Woo category names; no IDs/stock.
6. TC6 Partial — ≤5 attribute names; no values/prices/SKUs.
7. TC7 Deferred — no site tone Settings/schema.
8. TC8 Partial — locales retained; optional language names only.
9. TC9 Supported — SEO semantics + purpose; no Rank Math ownership change.
10. TC10 Deferred — no arbitrary surrounding text.
11. TC11 Supported — glossary packaging separated from source.
12. TC12 Supported — budgets and drop priority enforced.
13. TC13 Supported — allowlist-only context sources.
14. TC14 Supported — lightweight provenance without full bodies.
15. One context contract on `TranslationBatch`.
16. Context is optional (`null` safe).
17. Context is provider-agnostic.
18. No WooContext / SeoContext / JobsContext types.
19. FieldSemantic is a closed vocabulary.
20. Unknown mapping degrades to `generic`.
21. No free-string semantics enter provider prompts.
22. Context built once in `TranslationService` after assemble.
23. Sync and Jobs inherit the same builder path.
24. No second Jobs context builder.
25. Total optional context ≤ 1200 chars.
26. object_title ≤ 200 chars.
27. ContextItem value ≤ 200 chars.
28. Max ContextItems ≤ 8.
29. Categories ≤ 3; attribute names ≤ 5.
30. Drop priority matches frozen order.
31. Source text is never truncated to fit context.
32. Glossary keeps existing independent bounds.
33. Deny arbitrary `get_post_meta` dumps.
34. Deny customer/order/user/credential/private-note context.
35. Deny stock/price/SKU values/internal IDs.
36. No full-page HTML dumps.
37. SEO context is purpose-only; Rank Math emission unchanged.
38. Glossary instructions marked do-not-copy / non-output.
39. Source has explicit translate-only boundary.
40. No gut_01 phrase-specific regex.
41. No glossary matching/priority redesign (TI.3).
42. Prompt/context schema version bumped and recorded.
43. `source_hash` unchanged (source-text based).
44. `is_stale` semantics unchanged.
45. Context changes do not auto-stale translations in TI.2.
46. Staleness limitation documented.
47. TARGET remains 6.
48. No schema migration; no new identity family.
49. No Integration API v1 change required for TI.2.
50. Store remains AI-context agnostic.
51. TI.1 persist structural gate remains intact.
52. C1.0 immutable.
53. H1.0 immutable.
54. `baseline-v1.1.0` immutable / verify green.
55. TQ.0 candidate compare mandatory for semantic claims.
56. Zero new Class A critical regressions.
57. gut_01 candidate must not reproduce glossary scaffold.
58. Class B gut_01 re-evaluated with evidence.
59. Optional C1.1 is additive only.
60. No TM-in-generation / vector TM.
61. No TI.4 QA publication policy.
62. No Jobs framework redesign.
63. No provider expansion milestone work.
64. No auto-publication.
65. Normal CI remains network-free.
66. Null/scripted providers safe when context absent.
67. TranslationService contains no OpenAI message formatting.
68. PHPCS / unit / integration / quality / build green.
69. PluginGuard green.
70. ADR-0010 amendment Accepted for TI.2 context contract.
71. No TI.3–TI.7 implementation in this milestone.

**Acceptance criteria count:** **71**.

---

## 27. Validation strategy

- **Unit:** DTO, mapper, builder, budgets, packaging helpers
- **Integration:** product/SEO/term; absent context; sync+Jobs; TI.1 structural reject still works
- **TQ.0:** validate, verify-baseline, candidate compare, Quality unit
- **Live:** optional acceptance only — never normal CI

---

## 28. ADR assessment

**ADR-0010 amendment required** (see [0010-provider-agnostic-interface.md](../adr/0010-provider-agnostic-interface.md) amendment). Documents optional `TranslationContext`, provider-safe absence, non-identity / non-`source_hash`, schema versioning. No Store/TARGET ADR.

---

## 29. STOP conditions

Stop if TI.2 requires: new identity; Store/`source_hash` redesign; TARGET bump; second TranslationService; OpenAI logic in core service; meta/page dumps; TM/vector TM; glossary intelligence redesign; Jobs redesign; auto-publication; live AI in CI; mutating C1.0/H1.0/baseline; gut_01 phrase regex; silent staleness change.

---

## 30. Risks / limitations

- Token/cost increase from context
- Context noise if allowlist too broad
- Title changes do not stale short descriptions (documented)
- Thin Class B baseline — avoid overclaiming quality

---

## 31. Expected files (implementation later)

`TranslationBatch`, `TranslationContext`, `FieldSemantic`, `ContextItem`, context builder/mapper, `TranslationService`, `OpenAIProvider`, `PromptProfileRegistry`, fakes, quality `PersistPathBatchBuilder`, tests, ADR-0010 amendment, validation log.

---

## 32. Rollback

Revert feature commits; omit context field; no DB migration.

---

## 33. Repository lifecycle

1. Definitive planning (approved) — done
2. Materialize on docs branch — this document
3. Independent review
4. Merge / Architecture Frozen on `main`
5. Create `feature/ti2-bounded-translation-context`
6. Implement TI2.0–TI2.8
7. Independent review / merge (separate agent)
8. Closure

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md` |
| Revision | 1.1 — 2026-08-10 — Complete on `main` |
| Implementation | **Complete** — merge `80dfdcf18a93f168370aa1bb6a03d7c6dd8376fa` |
