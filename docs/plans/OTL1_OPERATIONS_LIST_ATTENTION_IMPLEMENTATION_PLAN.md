# OTL.1 — Operations List + Attention — Implementation Plan

**Status:** Architecture Frozen (planning) — on `main` — implementation not started
**Milestone:** OTL.1 — Operations List + Attention (Operator Translation Lifecycle program)
**Kind:** Milestone implementation plan (authoritative on `main`)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** OTL parent **Architecture Frozen**; OTL.0 Foundations **Complete**; TIQ **Complete**; AI Multilingual **v1.2.0**; `Migrator::TARGET` **7**
**Schema:** Migrator `TARGET` = **7** (unchanged — no migration expected)
**ADR:** **No new ADR.** STOP if OTL.1 requires durable composite operator/risk state, new role architecture, Integration API change, schema/index migration, or TIQ ownership change.
**Planning branch:** `docs/otl1-operations-list-attention-planning-freeze` (merged)
**Freeze merge:** `main` @ `30332a315e2b0a99a036a5aa521771b21ba2cd9a` (`merge: freeze OTL.1 Operations list + attention plan`)
**Baseline main (pre-freeze):** `30674ed7c80ca969b987e0c4ccdfb9b6bfe518db`
**Independent review (planning):** **PASS**
**Implementation branch:** create **`feature/otl1-operations-list-attention` only after** this plan is Architecture Frozen on `main` — **not** as part of this planning freeze
**Next after freeze:** Implement OTL1.0–OTL1.8 strictly per this plan on `feature/otl1-operations-list-attention`. Do **not** begin OTL.2+ or TSC.
**Related:** [OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md](OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md); [ADR-0015](../adr/0015-review-workflow-and-tm-approval-policy.md); [ADR-0019](../adr/0019-evidence-based-risk-assessment.md); [ADR-0020](../adr/0020-controlled-auto-publication-and-frontend-gate.md)

**Operational success:** Operators can open Workspace → Operations, select a language, see operational attention counts and a paginated cross-object list, understand why a row needs operational attention from cheap Store axes, inspect authoritative TI.4/TI.5/TI.7 facts read-only, and navigate into Translate or Review — without inventing a second policy engine.

**Hard boundary:** OTL.1 does **not** ship unified edit/review mutations (OTL.2), publication/stale mutation UX (OTL.3), Jobs recovery (OTL.4), new bulk (OTL.5), TSC, Integration API, schema change, or Biopentra-specific behavior.

---

## 1. Executive summary

OTL.1 turns the OTL.0 backend foundation into the operator's primary **cross-object operational list and operational-attention surface**.

```text
Store axes (cheap) + OTL.0 list/detail REST
        ↓
Operations Workspace tab
        ↓
find → understand operational state → navigate
```

OTL.1 answers:

> What translations need my **operational** attention, why (from persisted lifecycle axes), and where do I go next?

It does **not** answer “every translation that may need human quality/risk attention,” because default list intentionally omits TI.5/TI.7 computation.

---

## 2. Parent / OTL.0 verification

| Contract | Status |
|---|---|
| OTL parent Architecture Freeze | Complete on main |
| OTL.0 Foundations | Complete (`13e68f9d5…`) |
| `OperatorTranslationAssembler` / list+detail VMs | Shipped |
| `AllowedActionsResolver` | Shipped (admission only) |
| `Store::query_operations` / `get_by_translation_id` | Shipped |
| `GET …/workspace/operations` (+ detail) | Shipped |
| TARGET 7 indexes for cheap axes | Assessed sufficient for admitted axis filters; count path still measured — **STOP** if insufficient |
| Operations UI | **Not started** — this milestone |

---

## 3. Current Workspace / admin architecture

| Fact | Detail |
|---|---|
| Shell | [TranslatorWorkspace.php](../../src/Admin/TranslatorWorkspace.php) — `page=aiml-translator` |
| SPA | [App.tsx](../../assets/translator-workspace/src/App.tsx) — tabs `editor` \| `queue` \| `jobs` |
| Closest list pattern | Review queue panel + server pagination (`per_page=20`) |
| Settings | Separate PHP submenu — **not** a Workspace tab |
| REST client | [workspace-api.ts](../../assets/translator-workspace/src/api/workspace-api.ts) |
| Playwright | F10 smoke local; **not** CI-gated |

OTL.1 adds a fourth tab (`operations`) in the same shell.

---

## 4. Official objective

Operators can find work without knowing every axis — language-scoped Operations list, operational attention filters/counts, lifecycle-axis presentation, navigation, and reusable read-only inspection over OTL.0.

---

## 5. Architecture invariant

```text
Store / TI.4 / TI.5 / TI.7 / Jobs
        ↓
OTL.0 read model + allowed_actions
        ↓
OTL.1 Operations UI (consumer)
```

OTL must not become a second QA, assessment, publication, Jobs, TM, or translation engine.

---

## 6. Operational-attention honesty rule

**OTL.1 attention =** operational attention based on **cheap persisted translation lifecycle axes**.

**OTL.1 attention ≠** every translation that may require human attention.

Default list does **not** compute:

- TI.5 `blocked` / `needs_review` / `review_recommended` / `structurally_clean`
- TI.7 publication eligibility
- rich Jobs failure/retry state

UI/help text must state this honestly. Detail/inspector remains the place for TI.4/TI.5/TI.7 evidence. No opaque score. No heuristic substitute for TI.5.

---

## 7. Attention taxonomy (collision-free)

Primary attention filter: **one preset at a time**. `all` = no-filter default (not a reason ID).

| Bucket ID | Human label | Semantics | SQL | Index |
|---|---|---|---|---|
| `stale` | Stale | Source drifted | `is_stale=1` | `stale_sweep` / `lang_status` |
| `review_pending` | Pending review | ADR-0015 pending | `review_status=pending` | `lang_review_queue` |
| `review_rejected` | Rejected | Review rejected | `review_status=rejected` | `lang_review_queue` |
| `unpublished` | Unpublished | Not published | `publish_status=unpublished` | `lang_publish_status` |
| `translation_failed` | Translation failed | Provenance failed | `status=failed` | `lang_status` |

**Hard naming rule:** Machine ID `needs_review` is **reserved exclusively for TI.5 AssessmentCategory**. OTL.1 must never use `needs_review` for ADR-0015 pending review.

**Merchant copy:** OTL.1 supersedes any parent-plan merchant shorthand “Needs review” for ADR-0015 pending with human label **Pending review**. The inspector may still surface TI.5 category `needs_review` as assessment vocabulary (distinct axis).

**Parent OT2 note:** Parent program concepts that mentioned TI.5/TI.7/Jobs as *potential* attention ideas remain parent-level concepts, not OTL.1 list/count policy. OTL.1 milestone-narrows to cheap Store axes with honesty + Deferred buckets.

**`attention_reasons`:** multi-label array on list rows; presentation aliases of Store axes only. A row may include multiple reasons. Counts are **independent and may overlap**. No precedence system that creates a composite status.

**`unpublished` product note:** On new languages, `unpublished` may dominate inventories (broader than “unpublished but TI.7-eligible”). Honesty copy should set that expectation.

**Deferred buckets:** TI.5 risk categories; TI.7 eligibility; rich Jobs; “ready/clean” composite.

---

## 8. Attention counts

### Endpoint

`GET /aiml/v1/workspace/operations/attention-counts?language=` (**required**)

Response keys: `{ stale, review_pending, review_rejected, unpublished, translation_failed, total }`

### Frozen semantics (not frozen SQL shape)

- language-scoped
- bounded vocabulary above
- independent overlapping counts (**bucket counts must not be assumed additive**)
- **`total`** = count of language-scoped rows visible under the **same auth/visibility as the unfiltered Operations list** (`attention=all` / no attention preset). It is **not** the sum of bucket counts and **not** “rows with ≥1 attention reason”
- **identical authorization to Operations list**
- no full inventory load
- no AssessmentAssembler / PublicationService::explain / AI/network
- bounded DB work; TARGET 7 / existing indexes
- Implementation may use separate COUNTs, one bounded aggregate, or Store helper — measured, semantics-identical, index-safe
- **STOP** if TARGET 7 cannot deliver acceptable performance

---

## 9. Authorization model

| Surface | Visibility |
|---|---|
| Operations list | `aiml_translate` **OR** `aiml_review_translations`; language-scoped; no per-row `edit_post` filter (OTL.0 intentional Workspace operator visibility) |
| Attention counts | **Identical** to list — never a broader universe |
| Detail / inspector | Same list caps **plus** object-level `edit_post` for post-backed sources (OTL.0) |
| Mutations | Existing authoritative controllers/services only; not introduced by Operations table |

**Audit verdict:** OTL.0 list auth is intentional (aligned with review-queue capability model), not an unfixed leak. Counts must not invent a new permissions architecture or widen visibility.

**List → inspector denial UX:** When a row is list-visible but detail returns **403** (missing object-level access), Operations must not pretend the inspector opened. Freeze UX: disable or hide **View detail** when `allowed_actions` / links indicate detail is unavailable **or** show a clear non-leaky denial message after a failed detail fetch — never dump protected content. Open in Translate / Open in Review remain subject to their own existing capability gates.

---

## 10. List row contract

Reuse OTL.0 list ViewModel fields. Additive:

- `attention_reasons: string[]` ⊆ `{stale, review_pending, review_rejected, unpublished, translation_failed}`

No assessment/QA/publication-eligibility payloads on default list.

Desktop column priority: source → previews → language → status → review → publish → stale → attention chips → updated → navigation actions.

---

## 11. Filters / URL state

Server-side only:

- required `language`
- `attention` preset → maps to Store filter args
- optional explicit `status`, `review_status`, `publish_status`, `is_stale`, `source_type`, `source_id`
- **Unsupported:** FULLTEXT / cross-axis text search

**Composition rule:** When both `attention` and an explicit axis filter are present, filters are combined with **AND**. Contradictory pairs (e.g. `attention=stale` and `is_stale=0`) return an empty page (HTTP 200, empty items) — not a silent override of either filter. Unknown / reserved `attention` values (including accidental `needs_review`) return **400/422** with a clear error; they must not be silently ignored or remapped to TI.5 vocabulary.

URL sync on admin page: `page=aiml-translator&view=operations&language=&attention=&page_num=…` (plus explicit filters when set). Admin `page_num` maps to REST `page`. Saved views Deferred.

---

## 12. Pagination / sorting

Retain OTL.0: default **20**, max **50**, order `updated_at DESC, translation_id DESC`. No alternate sorts in OTL.1.

---

## 13. Actions and mutation authority

OTL.1 flow: **find → understand → navigate**.

| Affordance | Disposition |
|---|---|
| Open in Translate | Supported |
| Open in Review | Supported |
| View detail (read-only inspector) | Supported |
| Open source / frontend | Supported |
| Inline submit/approve/reject | **Deferred OTL.2** — navigate to Review; no Operations mutation REST |
| Publish / unpublish / retranslate | Deferred OTL.3 (display/admission only) |
| Jobs retry / open job | Deferred OTL.4 |

`allowed_actions` remains UI admission only. Mutations revalidate via ReviewWorkflowService / PublicationService / Jobs. Never `structurally_clean ⇒ eligible`, `approved ⇒ published`, or client-side policy.

---

## 14. Read-only inspector — Decision A

Ship a **reusable read-only inspection component** consuming OTL.0 detail REST:

- what / why / next
- axes + TI.4 QA + TI.5 assessment + TI.7 publication explain
- navigation CTAs

Must **not** implement: edit, review mutations, publication mutations, retranslation, Jobs recovery.

OTL.0 detail may still return mutation-shaped `allowed_actions` descriptors. OTL.1 inspector **may receive** them for admission/navigation cues but **must not render** review/publish/Jobs mutation controls.

**OTL.2 extends this component** into the unified detail/editor — do not build a disposable second detail app.

---

## 15. Milestone boundaries

| Milestone | Owns |
|---|---|
| OTL.1 | Operations tab; list; operational attention; navigation; reusable inspector; a11y/responsive; local Playwright |
| OTL.2 | Unified detail; edit; review actions/workflow |
| OTL.3 | Publication mutation UX; stale/retranslation workflow |
| OTL.4 | Jobs linkage/recovery |
| OTL.5 | Bounded new bulk |
| OTL.6 | Final UX polish/acceptance |

---

## 16. REST additions

| Method | Path | Notes |
|---|---|---|
| GET | `/aiml/v1/workspace/operations` | Existing; add `attention` preset param |
| GET | `/aiml/v1/workspace/operations/attention-counts` | New; language required |
| GET | `/aiml/v1/workspace/operations/{translation_id}` | Existing; inspector consumer |

Admin Workspace API only. No Integration API / v2.

---

## 17. UI architecture

- Extend `WorkspaceViewMode` with `operations`
- New panels mirroring Review queue patterns (`OperationsPanel`, filter bar, row, pagination, counts)
- CSS `.aiml-operations-*` beside review-queue styles
- Bootstrap: Operations access is the mandatory OR of `aiml_translate` / `aiml_review_translations`; an explicit `canAccessOperations` bootstrap flag is optional naming only
- Honesty copy near attention filters/counts (including that `unpublished` can dominate new-language inventories)

---

## 18. Accessibility

Semantic table/region; labeled filters; keyboard tabs/actions; no color-only status; visible focus; pagination `aria`/`aria-live`; inspector keyboard operable; loading/error text.

---

## 19. Responsive admin

Target common laptop/desktop WP admin widths (soft flex-wrap like existing Workspace). Column priority/truncation documented. Not a mobile-first redesign (Deferred). Must not be broken at narrower admin widths.

---

## 20. Playwright

`acceptance/otl1-browser/` smoke: tab loads; language; attention presets; pagination; navigate Translate/Review; inspector opens; axes distinct; honesty copy present. **Local / documented — not CI-gating** (matches F10 infrastructure).

---

## 21. Performance

Evidence for hundreds / thousands / ~10k where practical:

- bounded SQL
- no full inventory load
- zero AssessmentAssembler / PublicationService::explain on default list and counts
- pagination enforced
- measured count strategy under TARGET 7 indexes

No invented UX latency SLO.

---

## 22. Security / neutrality

Existing capabilities only. Counts ≡ list visibility. No prompts/keys/secrets in payloads. No Biopentra / site-specific product behavior. PluginGuard/neutrality for new strings. No Integration API.

---

## 23. Schema / ADR verdict

| Question | Verdict |
|---|---|
| TARGET | Remains **7** |
| Migration | **None** expected |
| New ADR | **None** for ordinary OTL.1 UI/REST |
| New index needed | **STOP** for architecture review |

---

## 24. OL capability matrix

| ID | Capability | Disposition |
|---|---|---|
| OL1 | Operations Workspace tab | Supported |
| OL2 | Cross-object list | Supported |
| OL3 | Language scope | Supported (required) |
| OL4 | Translation status filter | Supported |
| OL5 | Review-state filter | Supported |
| OL6 | Publication-state filter | Supported |
| OL7 | Stale filter | Supported |
| OL8 | Source-type / optional source-id filter | Supported |
| OL9 | Text / FULLTEXT search | Unsupported |
| OL10 | Operational attention buckets | Supported |
| OL11 | Attention counts (auth ≡ list) | Supported |
| OL12 | Alternate sorting | Unsupported |
| OL13 | Pagination | Supported |
| OL14 | Source preview | Supported |
| OL15 | Target preview | Supported |
| OL16 | Source / frontend navigation | Supported |
| OL17 | Open in Translate / Open in Review | Supported |
| OL18 | Assessment on list | Deferred |
| OL19 | QA on list | Deferred |
| OL20 | Publication eligibility on list | Unsupported |
| OL21 | Review mutations from Operations | Deferred OTL.2; navigation Supported |
| OL22 | Publication mutations | Deferred OTL.3 |
| OL23 | Retranslate workflow | Deferred OTL.3 |
| OL24 | Jobs linkage / retry | Deferred OTL.4 |
| OL25 | New bulk | Deferred OTL.5 |
| OL26 | Saved views | Deferred |
| OL27 | Assignment | Deferred |
| OL28 | Comments | Deferred |
| OL29 | Notifications | Deferred |
| OL30 | Mobile-first redesign | Deferred |
| OL31 | Desktop responsive admin | Supported |
| OL32 | Accessibility | Supported |
| OL33 | Playwright smoke (local) | Supported |
| OL34 | Metrics dashboard | Unsupported |
| OL35 | Audit timeline | Deferred |
| OL36 | TSC | Unsupported |
| OL37 | Integration API | Unsupported |
| OL38 | Reusable read-only inspector | Supported |
| OL39 | Operational-attention honesty copy | Supported |

Do not widen Deferred/Unsupported without amending this freeze.

---

## 25. Work packages

### OTL1.0 — Baseline

| | |
|---|---|
| **Objective** | Validation log; confirm OTL.0 contracts / TARGET 7 |
| **STOP** | Version/TARGET change |

### OTL1.1 — Attention application contract

| | |
|---|---|
| **Objective** | Preset mapping; `attention_reasons`; Store count helper (semantics frozen) |
| **Constraints** | No `needs_review` ID for review axis; no TI.5/TI.7 in SQL |

### OTL1.2 — REST contracts

| | |
|---|---|
| **Objective** | `attention` list param; attention-counts endpoint; auth parity tests |
| **Constraints** | Counts ≡ list visibility; no Integration API |

### OTL1.3 — Operations admin shell

| | |
|---|---|
| **Objective** | Fourth Workspace tab + bootstrap |
| **Constraints** | Same shell; no Settings merge |

### OTL1.4 — Filters / list / counts UI

| | |
|---|---|
| **Objective** | Filter bar, table, counts, URL state, honesty copy |
| **Constraints** | Server-side filters only |

### OTL1.5 — Navigation + reusable inspector

| | |
|---|---|
| **Objective** | Translate/Review/source/frontend navigation; Decision A inspector |
| **Constraints** | No review/publish/Jobs mutations |

### OTL1.6 — A11y / responsive / Playwright

| | |
|---|---|
| **Objective** | Accessibility + soft responsive + local smoke |
| **Constraints** | Playwright not CI-gating unless separate CI decision |

### OTL1.7 — Perf / security / neutrality

| | |
|---|---|
| **Objective** | Scale evidence; auth parity; PluginGuard/neutrality |
| **STOP** | Schema/index required |

### OTL1.8 — Closure

| | |
|---|---|
| **Objective** | Docs closure after implementation review/merge (implementation task) |

---

## 26. Acceptance criteria (82)

### Parent / boundary

1. OTL.1 remains subordinate to the OTL parent Architecture Freeze.
2. OTL.0 foundation contracts remain the list/detail backend.
3. OTL.1 does not implement OTL.2 unified edit/review mutations.
4. OTL.1 does not implement OTL.3 publication/stale mutation UX.
5. OTL.1 does not implement OTL.4 Jobs recovery.
6. OTL.1 does not implement OTL.5 new bulk.
7. OTL.1 does not implement TSC or Integration API exposure.
8. Production code remains site-neutral (no Biopentra-specific behavior).

### Attention honesty / naming

9. Attention is documented and labeled as operational (cheap Store axes), not exhaustive risk.
10. Machine ID `needs_review` is never used for ADR-0015 pending review in OTL.1 contracts.
11. TI.5 `needs_review` remains assessment-only vocabulary.
12. Supported attention IDs are exactly: `stale`, `review_pending`, `review_rejected`, `unpublished`, `translation_failed`.
13. `all` is a no-filter selection, not an attention reason.
14. List rows may expose multiple `attention_reasons`.
15. Primary attention filter selects one preset at a time.
16. Counts are independent and may overlap across buckets.
17. No precedence system creates a composite operator status.
18. No attention bucket claims TI.5 risk category membership.
19. No attention bucket claims TI.7 publication eligibility.
20. No rich Jobs attention bucket is introduced.
21. UI/help text states that structural risk and publication eligibility require detail/inspector evidence.

### Read model / list

22. Operations list uses OTL.0 cheap list representation plus `attention_reasons`.
23. Default list does not invoke AssessmentAssembler.
24. Default list does not invoke PublicationService::explain.
25. Default list does not invoke full QA evaluation attributable to OTL list.
26. Previews remain bounded (OTL.0 200-char contract).
27. Axes `status`, `review_status`, `publish_status`, `is_stale` remain independently visible.
28. Approved ≠ published remains explicit in UI/docs/tests.

### Counts

29. Attention-counts endpoint requires language.
30. Count keys match the frozen attention vocabulary + `total`.
31. `total` means language-scoped inventory under the same auth/visibility as the unfiltered list — not the sum of buckets and not “≥1 attention reason”.
32. Counts use the same capability boundary as the Operations list.
33. Counts never include a broader language/capability universe than the list.
34. Counts do not run AssessmentAssembler or publication explain.
35. Count SQL/implementation shape is free within frozen semantics; plans are measured.
36. Unacceptable count performance under TARGET 7 is a STOP, not a silent migration.

### Authorization

37. List access requires `aiml_translate` OR `aiml_review_translations`.
38. List remains language-scoped Workspace visibility per OTL.0 (no new role system).
39. Detail/inspector enforces object-level access for post-backed sources.
40. When list-visible rows lack detail object access, UI disables/hides View detail or shows a clear denial — never opens protected content.
41. Unauthorized operators do not receive protected full source/target content.
42. No new SaaS role/permission architecture is introduced.

### REST / filters / pagination

43. Additive `attention` list query parameter maps presets to Store filters.
44. Explicit axis filters remain supported.
45. When `attention` and explicit axis filters are both present, they combine with AND; contradictions yield empty results (not silent override).
46. Unknown or reserved `attention` values (including `needs_review`) return 400/422 — not silent ignore/remap.
47. FULLTEXT / arbitrary text search is not admitted.
48. Pagination default ≤20 and maximum ≤50.
49. Ordering remains `updated_at DESC, translation_id DESC` (or documented OTL.0 equivalent).
50. Queries do not load all translations into PHP.
51. Responses remain under admin `aiml/v1` Workspace API only.
52. Admin URL `page_num` maps to REST `page`.

### UI / navigation / inspector

53. An Operations tab exists in the existing Workspace SPA shell.
54. Filters and pagination are server-driven (no client filter over incomplete pages).
55. URL/query state supports reproducible Operations views for language/attention/page.
56. Open in Translate navigates to the existing editor tab with context.
57. Open in Review navigates to the existing review queue with context.
58. Open source / frontend uses generated links (no hard-coded hosts).
59. A reusable read-only inspector consumes OTL.0 detail REST.
60. Inspector shows axes and TI.4/TI.5/TI.7 evidence without mutation controls for review/publish/Jobs.
61. Inspector may receive `allowed_actions` descriptors but must not render review/publish/Jobs mutation controls in OTL.1.
62. Inspector is designed for OTL.2 extension (Decision A), not a disposable app.
63. Operations table does not call review mutation endpoints.
64. Publish/unpublish/retranslate mutation UX is not shipped in OTL.1.

### Performance / a11y / browser

65. Scale evidence covers hundreds and thousands of rows (≈10k where practical).
66. Default list assessment/explain invocations remain zero.
67. Count path assessment/explain invocations remain zero.
68. Accessibility requirements (labels, keyboard, no color-only status, focus, pagination a11y) are met and testable.
69. Soft responsive laptop/desktop admin behavior is defined and not broken at narrower widths.
70. Local Playwright smoke covers tab load, attention filter, pagination, navigation, inspector, and honesty copy.
71. Playwright is not required to be CI-gating for OTL.1 given current infrastructure.

### Schema / regression / docs

72. Runtime `Migrator::TARGET` remains 7.
73. No schema/index migration ships in OTL.1 unless architecture STOP is raised instead.
74. No new ADR is required for ordinary OTL.1 UI/REST work.
75. TI.4 / TI.5 / TI.7 / ADR-0015 ownership remains unchanged.
76. Jobs lifecycle remains TI.6-owned; OTL.1 does not duplicate it.
77. PluginGuard/neutrality coverage extends to OTL.1 product surfaces as appropriate.
78. No prompts, API keys, or auth headers appear in OTL payloads.
79. Version remains 1.2.0; no release tag created by OTL.1.
80. Implementation validation records evidence for these ACs.
81. Parent/roadmap pointers update only as needed for freeze/closure.
82. After freeze, exact next step is combined OTL.1 implementation + independent implementation review + merge + closure — not started by the planning freeze.

**Verified AC count: 82.**

---

## 27. STOP conditions

STOP implementation (architecture review) if OTL.1 discovers need for:

- second QA/assessment/publication/Jobs/TM/translation engine
- opaque attention/risk score or LLM confidence
- client-side publication eligibility or review policy
- full inventory load / unbounded per-row assess/explain
- schema/TARGET change without approval
- Integration API v2
- TSC implementation
- site-specific/Biopentra product behavior
- OTL.2 review-mutation surface inside Operations
- OTL.3/4/5 scope absorption
- release/version bump as part of OTL.1

---

## 28. Test strategy

- **Unit:** attention preset mapping; `attention_reasons`; serializers; honesty ID collision guards; invalid `attention` rejection including `needs_review`
- **Integration:** list `attention` param; counts auth parity with list; zero assess/explain; pagination; permissions
- **PluginGuard / neutrality:** no `needs_review` as OTL attention ID in product contracts; no site branding
- **JS unit:** filter URL state helpers; panel mapping
- **Playwright (local):** Operations smoke suite
- **Perf:** hundreds/thousands/(≈10k) count+list evidence

---

## 29. External review amendments incorporated

1. Removed TI.5 `needs_review` collision → `review_pending`
2. Review mutations deferred to OTL.2; navigation Supported
3. Reusable read-only inspector (Decision A)
4. Counts share list authorization; intentional OTL.0 list model documented
5. Operational-attention honesty rule
6. Count semantics frozen; SQL shape free within bounds
7. Post-review ordinary fixes: `total` semantics; attention×axis AND composition; invalid `attention` 400/422; list→inspector 403 UX; parent “Needs review” copy supersession; inspector `allowed_actions` render rule; Playwright honesty AC alignment

---

## 30. Exact next action after this plan freezes on main

Run the combined **OTL.1 implementation + independent implementation review + merge + milestone closure** task from the frozen main baseline on `feature/otl1-operations-list-attention`.

Do **not** create that feature branch during the planning freeze (this closure records the freeze only).
Do **not** start OTL.2–OTL.6 or TSC.
