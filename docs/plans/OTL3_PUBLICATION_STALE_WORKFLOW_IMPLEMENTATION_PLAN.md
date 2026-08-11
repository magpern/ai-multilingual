# OTL.3 — Publication + Stale Workflow — Implementation Plan

**Status:** **Complete** on `main` (merge `77fc39da5d9b30d204e5a0c04e318a463ad39484`; independent implementation review PASS)
**Milestone:** OTL.3 — Publication + Stale Workflow (Operator Translation Lifecycle program)
**Kind:** Milestone implementation plan (authoritative on `main`)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** OTL parent **Architecture Frozen**; OTL.0–OTL.2 **Complete**; TIQ **Complete**; AI Multilingual **v1.2.0**; `Migrator::TARGET` **7**
**Schema:** Migrator `TARGET` = **7** (unchanged — no migration)
**ADR:** **No new ADR.** ADR-0015 / ADR-0019 / ADR-0020 unchanged.
**Planning branch:** `docs/otl3-publication-stale-workflow-planning-freeze` (merged)
**Freeze merge:** `main` @ `053570275e019ec88137208fd8d1ba32542961d8` (`merge: freeze OTL.3 Publication + Stale Workflow implementation plan`)
**Freeze merge CI:** run `31517960903` — **SUCCESS**
**Reviewed planning HEAD:** `5b778f1c57391182f25d051c9b5553d9bfed8704`
**Implementation branch:** `feature/otl3-publication-stale-workflow` (merged)
**Final reviewed feature HEAD:** `773a998f9fc076476d0dd4f6a49e7608ac32d1f2`
**Independent review (implementation):** **PASS**
**Merge CI:** run `31521213814` — **SUCCESS**
**Closure commit:** `129b448d25c88fa998c8c6bddb12067bc2091a10`
**Post-closure CI:** run `31521451325` — **SUCCESS**
**Validation:** [OTL3_PUBLICATION_STALE_WORKFLOW_VALIDATION_LOG.md](OTL3_PUBLICATION_STALE_WORKFLOW_VALIDATION_LOG.md)
**Freeze recommendation:** **STATE A — FREEZE**
**Independent review (planning):** **PASS**
**Next:** **OTL.4** Jobs Integration — [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**; production implementation next). Do **not** start OTL.5–OTL.6 or TSC under OTL until planned/frozen.
**Related:** [OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md](OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md); [ADR-0020](../adr/0020-controlled-auto-publication-and-frontend-gate.md); [ADR-0015](../adr/0015-review-workflow-and-tm-approval-policy.md); [ADR-0019](../adr/0019-evidence-based-risk-assessment.md)

**Planning freeze contracts recorded:** TI.7 sole publication authority; no OTLPublicationPolicy; Settings gate/mode with immediate-vs-prospective effect honesty; stale published remains published (no auto-unpublish); sync retranslate with mandatory pre-persist `expected_translation_hash` guard (Jobs null-hash unchanged); controlled_auto retranslate confirmation disclosure; gate overlay-eligibility wording (not visibility guarantees); current publication facts vs non-durable operation result; TARGET 7 / no schema / no new ADR.

**Operational success:** From Operations detail, an operator can understand TI.7 publication eligibility, publish/unpublish when permitted, see honest gate/mode effects, handle stale translations (edit or AI retranslate without silent lost updates), and verify publication state + gate eligibility via frontend link — without duplicating policy, Jobs UX, bulk engines, or TSC.

**Hard boundary:** OTL.3 does **not** ship Jobs recovery (OTL.4), new bulk publish/retranslate (OTL.5), final broad polish (OTL.6), TSC, Integration API expansion, schema change, new ADR, Biopentra-specific behavior, durable audit timeline, retroactive publication reconciliation, or force-publish of hard blockers.

---

## 1. Executive summary

OTL.3 makes TI.7 operable on the existing OTL.2 Operations detail surface and admits Settings form controls for existing publication keys.

```text
Operations → OTL.2 detail
  → publication explain (TI.7)
  → publish / unpublish (PublicationService)
  → source changes → stale (may remain published)
  → edit OR sync retranslate (expected_translation_hash + pre-persist guard)
  → review → publish again
  → publication-state + gate-eligibility verify (link; not “verified visible”)
```

---

## 2. Parent / OTL.0–OTL.2 verification

| Contract | Status |
|---|---|
| OTL parent Architecture Freeze | Complete (`9a31176f…`); OT8/OT9/OT10 Supported |
| OTL.0–OTL.2 | Complete on `main` |
| Baseline HEAD | `3186db54e78db663e963752d9a4c1bc8ed7dc599` |
| `Migrator::TARGET` | **7** |
| Plugin version | **1.2.0** |
| Publish/unpublish REST/CLI | Exist; UI unwired |
| OTL.2 detail publication display | Explain/readiness only; mutation deferred here |
| `retranslate_stale` admission | Present with `deferred_milestone` — lift in OTL.3 |

---

## 3. Official objective

Operators can:

1. understand whether a translation can be published (TI.7 explain);
2. publish/unpublish via PublicationService;
3. see why publication is blocked;
4. understand stale state without auto-unpublish myths;
5. retranslate (sync) or edit when stale, with target lost-update protection;
6. see honest controlled_auto / gate effects;
7. verify publication state + gate eligibility via frontend link.

---

## 4. Hard architecture boundary

**Consumes:** Store, TI.5 AssessmentAssembler, TI.7 PublicationPolicy/PublicationService, ADR-0015 ReviewWorkflow, TranslationService, existing source_hash/staleness, existing Jobs/translate entry points (initiate only).

**Must NOT create:** OTLPublicationPolicy; alternate eligibility; composite readiness score; client-side publish policy; new review/stale states; second translation/Jobs engine; new SEO/Woo ownership; auto-unpublish; schema for UI convenience; retroactive publication sweep; durable audit timeline platform.

---

## 5. Current TI.7 architecture (locked facts)

### Owners

- [`PublicationPolicy::evaluate`](../../src/Translation/Publication/PublicationPolicy.php) — P1.0 pure eligibility
- [`PublicationService`](../../src/Translation/Publication/PublicationService.php) — explain / publish (double re-get) / unpublish / `maybe_auto_publish` / `current_mode` / `is_source_public`
- Store columns: `publish_status`, `published_at`, `published_by`
- Audit channel: `do_action('aiml_publication_audit', …)` — **not** a DB table
- Settings: `segment_publication_gate_enabled` default **false**; `auto_publication_mode` default **manual**

### Manual hard blockers

`blocked` assessment · `rejected` review · non-public source · `is_stale` (new publish). Soft categories may still be manually published. Already published → `publication_already_active` (even if stale).

### Auto-only extras

Mode `manual` → skip automation; soft categories; evidence ≠ complete; `approved_only` needs approved; `controlled_auto` needs structurally_clean + provenance ∉ {missing,unknown}.

### Auto-publish trigger

**Sole production call:** `TranslationService::persist_validated_text` → `maybe_auto_publish`.  
**Does not run after:** manual save, review approve/reject, publish REST.  
Manual material edit clears publish via Store. Jobs translation success ≠ publication success.

### Gate

`Store::is_publicly_overlay_eligible`: gate OFF ignores `publish_status`; gate ON requires `published`. Helper does not check stale (path-specific).

### Stale

`sync_source` sets `is_stale` without touching `publish_status`. Cleared on successful translation save. **Published can be stale. No auto-unpublish** (ADR-0020).

---

## 6. Retranslate target-concurrency audit (Amendment 1)

### Reality today

| Layer | `expected_translation_hash`? | Pre-persist guard? |
|---|---|---|
| `WorkspaceService::save_segment` | Required | Yes → 409 `aiml_translation_hash_mismatch` |
| REST translate | **No** | **No** |
| `BatchOperationCoordinator::translate_batch` | **No** | **No** |
| `TranslationService::translate_segment` | **No** | **No** — assemble → provider → `persist_validated_text` overwrite |
| Jobs processor | N/A | Calls without hash |

OTL.2 save concurrency does **not** protect AI retranslation.

### Frozen narrow design (STATE A)

1. Optional `?string $expected_translation_hash = null` on `TranslationService::translate_segment` (thread through interactive sync REST/batch only).
2. **null** (Jobs / legacy Translate without hashes): retain existing semantics.
3. **non-null** (OTL.3 interactive sync retranslate **must** send):
   - Optional early check after load (before provider) to save spend.
   - **Mandatory** check **immediately before** persist of generated/TM text: re-read Store `translation_hash`; mismatch → **409** `aiml_translation_hash_mismatch`; do **not** persist; newer target authoritative; discard generated text.
4. No schema, lock table, durable session, second service, or second conflict code.
5. `allowed_actions` is not a concurrency token.

### FLOW C

A loads H1 → retranslate with H1 → provider in flight → B saves T2/H2 → T3 returns → pre-persist mismatch → conflict → T2 kept → UI reports change during retranslation.

---

## 7. Publish / unpublish / explain UX

**Surface:** Extend [`OperationsInspector`](../../assets/translator-workspace/src/components/OperationsInspector.tsx) — no second app.

**Authority:** Existing REST → PublicationService. Never trust stale `allowed_actions`. Pass `expected_publish_status` on publish. Refresh detail after mutation. No force / no “publish anyway.”

**Unpublish:** Manual only; caps translate + edit_post; review/stale/source do not block; idempotent noop; no delete/review/source mutation; no mass-unpublish.

**Explain:** TI.7 decision already on detail Path-B; human labels for reason codes; machine codes remain TI.7.

**Dirty:** Block publish, unpublish, and retranslate while local target draft dirty (OTL.2 honesty).

**Non-post:** Publish/unpublish/retranslate Unsupported (`mutation_unsupported_source_type`); inspection may remain.

---

## 8. Settings boundary (Amendment 5)

**Chosen:** Settings owns edit; OTL detail shows read-only effective gate/mode + link to Settings.

### Gate OFF → ON confirmation

- Immediately changes **enforcement** of existing `publish_status` via canonical overlay gate.
- Does **not** delete data or automatically unpublish.
- Changes which rows are **overlay-eligible** under the gate helper.

### Auto mode change confirmation

- Affects **future** `maybe_auto_publish()` on paths that already invoke it.
- Does **not** scan inventory, retroactively publish, start Action Scheduler reconciliation, or mass-publish.
- Return to `manual` stops future auto attempts; does **not** mass-unpublish already published rows.

Safe defaults preserved: gate **false**, mode **manual**. No silent enable. No per-item modes.

---

## 9. Retranslate confirmation (Amendment 2)

Before confirm, disclose **all**:

1. Current target will be replaced.
2. Prior review approval is cleared.
3. Current publication state is invalidated by the replacement (Store clear on material persist).
4. If effective mode can evaluate auto (`approved_only` / `controlled_auto`), warn that the new translation **MAY be automatically published again** if TI.7 allows after persist.

Do **not** say only “will unpublish” when auto may republish.

After retranslate: **never predict** client-side. Refresh authoritative detail: target, `review_status`, `publish_status`, in-session `publication_result` if present, TI.7 explain, assessment, stale. No OTL auto-publish logic.

---

## 10. Stale workflow

```text
source changes → stale visible (may remain published)
→ operator inspects → edit OR sync retranslate
→ on successful replace: is_stale=0; review cleared; publish cleared
→ maybe_auto_publish may then publish/skip/fail (mode-dependent)
→ refresh shows authoritative state → review if needed → manual publish if needed
```

No auto-unpublish on source change. Distinct stale UI (not only attention chip). Attention keeps cheap `stale` / `unpublished`; **no** `publication_eligible` bucket.

---

## 11. Chosen retranslate architecture

**A — sync single-segment** via `POST /aiml/v1/workspace/{post_id}/translate` (`mode: sync`) → TranslationService, with `expected_translation_hash` required for OTL interactive retranslate.

Lift `deferred_milestone` from `retranslate_stale` when post + stale + caps. List remains cheap (no TI.7/provider). Open in Translate remains secondary escape hatch.

**OTL.4 boundary:** No Jobs detail/retry/queue/budget UX. Jobs without hash unchanged.

---

## 12. Flows A–G (frozen)

| Flow | Behavior |
|---|---|
| A manual mode | Retranslate → persist → publish cleared → auto skip → unpublished T2 → review → manual publish |
| B controlled_auto | Confirmation warns possible auto-republish → persist → maybe_auto_publish → refresh actual result |
| C concurrent edit | Pre-persist hash mismatch → do **not** persist generated text; T2 kept; 409 `aiml_translation_hash_mismatch` |
| D provider fail | Old target + publication unchanged |
| E publish fail after persist | Translation success; publication failure separate; no translation retry |
| F gate OFF | Overlay-eligibility semantics; not guaranteed display |
| G gate ON | unpublished overlay-ineligible via gate; published necessary not universal render proof |

---

## 13. Current publication facts vs operation result (Amendment 4)

**CURRENT PUBLICATION FACTS (durable Store):** `publish_status`, `published_at`, `published_by` (0 ⇒ system).

**LAST OPERATION RESULT (non-durable):** in-session publish/unpublish/auto `publication_result` when available; **not** claimed across reload; **not** labeled “latest audit event.”

Full audit timeline = Deferred (OT15). No new persistence for timeline.

---

## 14. Gate eligibility messaging (Amendment 3)

**Gate OFF:** `publish_status` not enforced as segment overlay gate; unpublished may still be overlay-eligible via legacy behavior; does **not** guarantee every render path displays it.

**Gate ON:** `publish_status=published` **required** by canonical gate; unpublished ⇒ overlay-ineligible through that gate; published is **necessary** but not sufficient for complete frontend visibility (source/route/stale/integration conditions).

**Frontend verify:** publication state + effective gate + link. Terms: **overlay eligible**, **publication gate active**, **publication state**. Not “verified visible” without genuine render test. No crawler. No TSC.

---

## 15. allowed_actions

Activate publish / unpublish / retranslate admissions. List publish stays `detail_only` (cheap). Detail publish uses TI.7 decision handoff already present. Retranslate: stale/type/caps only on list/detail — no provider. Mutations revalidate server-side. Hash is request evidence, not admission token.

---

## 16. PS capability matrix

| ID | Capability | Disposition |
|---|---|---|
| PS1 | Publication state display | Supported |
| PS2 | Publication explain (TI.7) | Supported |
| PS3 | Manual publish | Supported |
| PS4 | Manual unpublish | Supported |
| PS5 | Publication mode display | Supported |
| PS6 | Gate display (saved/effective) | Supported |
| PS7 | Settings edit gate/mode | Supported |
| PS8 | controlled_auto visibility (actor/timestamp/in-session result) | Supported |
| PS9 | Current publication facts + last available operation result | Partial |
| PS10 | Full publication audit timeline | Deferred (OT15) |
| PS11 | Stale display + warning | Supported |
| PS12 | Stale blocks new publish | Supported |
| PS13 | Stale published remains published | Supported |
| PS14 | Manual edit stale path | Supported (OTL.2) |
| PS15 | AI retranslate (sync) | Supported |
| PS16 | Retranslate confirmation (incl. auto-republish warning) | Supported |
| PS17 | Retranslate dirty guard | Supported |
| PS18 | Source concurrency | Supported (reuse) |
| PS19 | Target concurrency on interactive sync retranslate (pre-persist hash) | Supported |
| PS19b | Jobs without interactive hash | Supported (existing semantics) |
| PS20 | Review reset after retranslate | Supported (Store) |
| PS21 | Publication invalidation after material replace | Supported (Store) |
| PS22 | controlled_auto after retranslate (existing persist path) | Supported (display) |
| PS23 | Publication failure ≠ translation failure UI | Supported |
| PS24 | Frontend verify link | Supported |
| PS25 | Gate eligibility messaging (not visibility guarantee) | Supported |
| PS26 | Non-public source block | Supported |
| PS27 | Non-post publish mutation | Unsupported |
| PS28 | Term/taxonomy publish/edit | Deferred |
| PS29 | Operations attention integration | Partial |
| PS30 | allowed_actions publish/unpublish/retranslate | Supported |
| PS31 | Bulk publish/retranslate | Unsupported (OTL.5) |
| PS32 | Jobs detail/retry/queue UX | Deferred (OTL.4) |
| PS33 | Auto-unpublish on stale | Unsupported |
| PS34 | Scheduled publication | Unsupported |
| PS35 | Publication comments/assignment | Unsupported |
| PS36 | Accessibility + responsive smoke | Supported |
| PS37 | Playwright local acceptance | Supported |
| PS38 | Integration API changes | Unsupported |
| PS39 | Schema / TARGET change | Unsupported (7) |
| PS40 | New ADR | Unsupported |
| PS41 | TSC | Unsupported |
| PS42 | Neutrality | Supported |
| PS43 | Client-side publish policy | Unsupported |
| PS44 | OTLPublicationPolicy / composite score | Unsupported |
| PS45 | Force publish hard blockers | Unsupported |
| PS46 | Separate publish capability | Deferred |
| PS47 | Post-approve auto-publish hook | Unsupported |
| PS48 | Retroactive publication reconciliation sweep | Unsupported |
| PS49 | Durable publication_result across reload | Unsupported |

---

## 17. Milestone boundaries

| Milestone | Owns |
|---|---|
| **OTL.3** | TI.7 publish/unpublish UX; explain; Settings gate/mode; stale lifecycle; single-item sync retranslate + lost-update guard; frontend publication-state/eligibility linking; operation feedback |
| **OTL.4** | Jobs detail, recovery, retry, queue diagnostics |
| **OTL.5** | Bulk publish/unpublish/retranslate |
| **OTL.6** | Final program-wide UX/acceptance polish |
| **TSC** | Separate — not under OTL.3 |

---

## 18. Schema / TARGET / ADR

- TARGET stays **7**
- No schema
- No new ADR
- Do not amend ADR-0020 casually

---

## 19. Work packages OTL3.0–OTL3.8

### OTL3.0 — Baseline / factual lock
**Objective:** Lock TI.7/OTL.2 facts, PS matrix, concurrency audit.  
**Deps:** none. **STOP:** reality drift. **Gate:** matrix frozen.

### OTL3.1 — Publication controls + explain
**Objective:** Wire publish/unpublish API + Inspector; reason UX; refresh; noop/skip/fail.  
**Owners:** PublicationService, Workspace REST, OperationsInspector, workspace-api.  
**STOP:** JS eligibility rules. **Gate:** manual publish/unpublish on post detail.

### OTL3.2 — Gate/mode Settings
**Objective:** Form controls; immediate gate vs prospective automation confirmations; detail read-only echo.  
**STOP:** silent enable; per-item modes; reconciliation sweep. **Gate:** defaults preserved.

### OTL3.3 — Stale workflow presentation
**Objective:** Distinct stale; published+stale honesty; edit/retranslate CTAs.  
**STOP:** auto-unpublish; eligibility bucket.

### OTL3.4 — Retranslate + lost-update protection
**Objective:** Sync retranslate; confirmation with auto disclosure; thread `expected_translation_hash`; mandatory pre-persist guard; lift deferred_milestone; Jobs null-hash unchanged.  
**STOP:** new Jobs engine/UI; second conflict code.

### OTL3.5 — Dirty / concurrency / failure UX
**Objective:** Dirty blocks; expected_publish_status; retranslate 409; publication failure separation; provider-failure preservation.  
**Gate:** cannot mutate while dirty; Flow C/D/E covered.

### OTL3.6 — Frontend eligibility verify + publication facts
**Objective:** Overlay-eligibility wording; frontend link; Store facts; in-session operation result only.  
**STOP:** crawler/TSC; durable audit claim.

### OTL3.7 — Accessibility / Playwright / security
**Objective:** a11y; `acceptance/otl3-browser/` local; PluginGuard (no policy/schema/Jobs/Integration/TSC/neutrality).  
**Gate:** Flows A–G smoke documented.

### OTL3.8 — Regression / docs / closure
**Objective:** Full AC; validation log; roadmap pointer; no version bump/tag.  
**STOP:** release.

Each WP completion requires: tests green for scope; STOP conditions clear; no boundary bleed into OTL.4–6.

---

## 20. Acceptance criteria (96 contiguous)

### Parent / authority (AC1–AC10)
1. Parent OT8/OT9/OT10 respected.  
2. PublicationPolicy sole eligibility.  
3. PublicationService sole mutate owner.  
4. No OTLPublicationPolicy.  
5. No JS publish policy.  
6. No composite readiness score.  
7. No new review/stale states.  
8. No second translation/Jobs engine.  
9. No SEO/Woo ownership change.  
10. No auto-unpublish on source change.

### Explain / display (AC11–AC20)
11. Show `publish_status`.  
12. Show TI.7 eligible + reason_codes.  
13. Human labels for reason codes.  
14. Approved ≠ published.  
15. Review axis ≠ publish axis.  
16. Show effective mode.  
17. Show gate saved/effective.  
18. Show `published_at` / `published_by`.  
19. `published_by=0` ⇒ system actor.  
20. Soft vs hard blocker copy honest for manual path.

### Publish (AC21–AC32)
21. Eligible unpublished can publish.  
22. Hard blocked cannot.  
23. Rejected blocks.  
24. Non-public source blocks + link.  
25. Stale unpublished blocks new publish.  
26. Already published → noop UX.  
27. Caps required.  
28. `expected_publish_status` honored.  
29. Server recheck before mutate.  
30. Detail refresh after success.  
31. No force bypass.  
32. Non-post publish denied.

### Unpublish (AC33–AC38)
33. Published can unpublish.  
34. Idempotent unpublished noop.  
35. No review mutation.  
36. No source mutation.  
37. No delete.  
38. Unpublish allowed when stale published.

### Settings (AC39–AC46)
39. Gate editable in Settings.  
40. Mode editable in Settings.  
41. Defaults gate false / mode manual.  
42. No silent enable.  
43. Gate OFF→ON confirm: immediate enforcement; no delete/auto-unpublish claim.  
44. Mode change confirm: prospective only; no retroactive scan.  
45. No Action Scheduler reconciliation on Settings change.  
46. No per-item publication mode.

### Stale (AC47–AC54)
47. Stale visible distinctly.  
48. Published+stale remains published before successful replacement.  
49. Stale warning not only attention chip.  
50. Manual edit path available.  
51. Retranslate path available when stale post.  
52. After successful replace: review reset.  
53. After material replace: publish cleared before any auto evaluation.  
54. `is_stale` clears on successful replace.

### Retranslate (AC55–AC70)
55. Uses sync workspace translate path.  
56. No new AI provider path.  
57. Confirmation before replace.  
58. Confirmation discloses replace + review clear + publication invalidation.  
59. Confirmation warns possible auto-republish when mode can evaluate auto.  
60. Dirty blocks retranslate.  
61. Non-stale not admitted as `retranslate_stale`.  
62. Caps required.  
63. Interactive request carries `expected_translation_hash`.  
64. Pre-persist (not only pre-provider) compares expected vs current hash.  
65. Concurrent edit during provider → 409 `aiml_translation_hash_mismatch`; newer target kept.  
66. Jobs/`translate_segment` without hash retain existing semantics.  
67. Provider failure before persist leaves old translation + publication unchanged.  
68. Surfaces in-session `publication_result` if auto runs; UI does not predict.  
69. Does not invent post-approve publish.  
70. Jobs UX absent; Open in Translate remains.

### Dirty / concurrency / failure (AC71–AC78)
71. Dirty blocks publish.  
72. Dirty blocks unpublish.  
73. Evidence = persisted target.  
74. Conflict refresh path for target hash mismatch.  
75. Publication failure ≠ translation failure.  
76. No translation retry solely because publication failed.  
77. No lock table.  
78. No new schema.

### Frontend / gate honesty (AC79–AC86)
79. Frontend link when available.  
80. Gate OFF copy: unpublished may still be overlay-eligible; not guaranteed display.  
81. Gate ON copy: published required by gate; unpublished overlay-ineligible via gate.  
82. Published not presented as universal render proof.  
83. Frontend verify ≠ “verified visible” without genuine render test.  
84. Uses overlay-eligible / publication-gate / publication-state terms.  
85. No TSC.  
86. No live storefront crawler.

### Facts / audit labeling (AC87–AC90)
87. Current publication facts labeled as Store metadata.  
88. Last operation result labeled non-durable / in-session when shown.  
89. No “latest audit event” claim without durable source.  
90. No durable audit timeline platform.

### Boundaries / quality (AC91–AC96)
91. No bulk publish/unpublish/retranslate.  
92. No Jobs detail/retry.  
93. Attention: no eligibility bucket.  
94. a11y labelled + aria-live; responsive usable; Playwright local suite.  
95. PluginGuard: no policy/schema/Integration/TSC/neutrality violations; permissions; no prompt/key leakage.  
96. TARGET remains 7; version remains 1.2.0 during OTL.3; docs closure without premature release/tag.

---

## 21. Test strategy

### Unit
Publication UI state mapping; explain labels; stale helpers; retranslate admission; gate/mode presenters; dirty publication gate; current-facts vs operation-result presenter.

### Integration
Publish/unpublish/idempotency/blocked/source/stale/published+stale; retranslate invalidation; pre-persist hash conflict; Jobs without hash unchanged; provider-fail preserves state; controlled_auto result attachment; publication failure separation; permissions; concurrency; non-post deny; Settings effect contracts (no sweep).

### PluginGuard
No OTL publication policy; no client eligibility; no Jobs engine; no schema; no Integration API; no TSC; neutrality.

### Browser
`acceptance/otl3-browser/` local/non-CI (match OTL.1/2). Scripted/fake provider. Cover Flows A–G messaging and concurrency smoke.

---

## 22. Manual acceptance

Approved unpublished → publish → gate-eligibility messaging/frontend link → unpublish → blocked reason → stale published → stale unpublished → manual edit → retranslate (manual mode) → re-review → republish → dirty protection → non-public source → permission differences → Settings gate/mode confirmation copy.

---

## 23. Performance / security / neutrality

Detail bounded to one translation; no list TI.7 N+1; no eligibility counts. Existing caps; bounded payloads; safe URLs. No Biopentra/peptides/site-specific fixtures or rules.

---

## 24. Versioning

No version bump, tag, or release during OTL.3. Future v1.3.0 remains roadmap-level only.

---

## 25. FREEZE RECOMMENDATION

**STATE A — FREEZE**

Repository already has TI.7 REST/CLI/policy/service, OTL.2 detail explain surface, settings keys, sync translate path, and TARGET 7 columns. OTL.3 is UI/orchestration + Settings form + admission activation + **narrow optional interactive pre-persist hash guard**. No schema/ADR redesign. Jobs compatibility preserved via null hash.

---

## 26. STOP conditions

STOP and escalate if implementation would require: schema/TARGET change; new ADR; auto-unpublish; second publication policy; Jobs UX platform; bulk engines; durable audit table merely for UI; retroactive reconciliation sweep; force-publish; Integration API change; TSC under OTL; site-specific contracts.

---

## 27. Exact next step after Architecture Frozen on main

Run the combined **OTL.3 implementation + independent implementation review + merge + milestone closure** task from the frozen main baseline.

Do **not** start OTL.4–OTL.6 or TSC under OTL until their own freezes.

---

## 28. Planning freeze checklist

- [x] External amendments 1–5 incorporated
- [x] Retranslate pre-persist `expected_translation_hash` contract frozen
- [x] PS1–PS49 frozen
- [x] 96 contiguous ACs
- [x] OTL3.0–OTL3.8 ladder
- [x] Gate eligibility wording (not visibility guarantees)
- [x] Current publication facts vs non-durable operation result
- [x] Settings immediate-gate vs prospective-automation messaging
- [x] Independent planning review PASS
- [x] Merge `--no-ff` to main (`053570275e019ec88137208fd8d1ba32542961d8`)
- [x] Merge CI green (`31517960903`)
- [x] Planning closure docs
- [x] Post-closure CI green (`31518187220`)
