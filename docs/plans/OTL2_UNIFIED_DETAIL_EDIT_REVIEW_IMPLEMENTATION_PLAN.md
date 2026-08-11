# OTL.2 — Unified Detail + Edit/Review — Implementation Plan

**Status:** **Architecture Frozen** (planning) — independent review **PASS**; merge to `main` authorized
**Milestone:** OTL.2 — Unified Detail + Edit/Review (Operator Translation Lifecycle program)
**Kind:** Milestone implementation plan (authoritative after freeze merge on `main`)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** OTL parent **Architecture Frozen**; OTL.0 Foundations **Complete**; OTL.1 Operations List + Attention **Complete**; TIQ **Complete**; AI Multilingual **v1.2.0**; `Migrator::TARGET` **7**
**Schema:** Migrator `TARGET` = **7** (unchanged — no migration)
**ADR:** **No new ADR.** ADR-0015 / ADR-0019 / ADR-0020 unchanged.
**Planning branch:** `docs/otl2-unified-detail-edit-review-planning-freeze`
**Freeze recommendation:** **STATE A — FREEZE**
**Independent review (planning):** **PASS**
**Implementation branch:** **not created** in this planning freeze
**Next (after freeze on main):** Combined OTL.2 implementation + independent implementation review + merge + milestone closure from the frozen main baseline. Do **not** start OTL.3–OTL.6 or TSC under OTL.
**Related:** [OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md](OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md); [OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md](OTL1_OPERATIONS_LIST_ATTENTION_IMPLEMENTATION_PLAN.md); [ADR-0015](../adr/0015-review-workflow-and-tm-approval-policy.md); [ADR-0019](../adr/0019-evidence-based-risk-assessment.md); [ADR-0020](../adr/0020-controlled-auto-publication-and-frontend-gate.md)

**Operational success:** From Operations, an operator opens one translation and, in one coherent surface, understands source and last-persisted evidence, edits the target safely, saves without silent lost updates, sees structured TI.4 QA and TI.5 assessment for the persisted text, and submits/approves/rejects through ADR-0015 — without confusing unsaved draft with evaluated/reviewable content, and without publishing or retranslating (OTL.3).

**Hard boundary:** OTL.2 does **not** ship publish/unpublish mutation UX or stale→retranslate→republish (OTL.3), Jobs recovery (OTL.4), new bulk engines (OTL.5), final broad polish (OTL.6), TSC, Integration API expansion, schema change, new ADR, Biopentra-specific behavior, CAT platform, durable editor drafts, or live per-keystroke QA.

---

## 1. Executive summary

OTL.2 extends the OTL.1 reusable read-only inspector (Decision A) into the **unified translation detail workspace**: source context, target editor, dirty/save feedback, structured QA/assessment, review controls, and publication readiness **display**.

```text
Operations (OTL.1)
        ↓ open translation_id
OTL.0 detail GET
        ↓
Unified detail (extend OperationsInspector)
        ↓ edit/save (Workspace save_segment) + review POSTs
Store / ReviewWorkflowService / TI.4 / TI.5 / TI.7 explain (owners unchanged)
```

OTL.2 answers:

> I found a translation that needs attention — can I understand it, edit it safely, review it, and see what remains before publication?

It does **not** answer publication mutation or stale retranslation (OTL.3).

---

## 2. Parent / OTL.0 / OTL.1 verification

| Contract | Status |
|---|---|
| OTL parent Architecture Freeze | Complete on main (`9a31176f…`) |
| OTL.0 Foundations | Complete (`13e68f9d…`) — detail GET with QA/assessment/explain |
| OTL.1 Operations + Decision A inspector | Complete (`466eb6a470…`) — OL21 review mutations deferred here |
| Baseline HEAD at planning start | `10ffa4a9ccf430dec786dba3dda6327a63b7503e` |
| `Migrator::TARGET` | **7** |
| Plugin version | **1.2.0** |
| Store `translation_hash` column | Exists (`Schema.php`) — no schema work |
| Save source concurrency | `source_hash` → 409 `aiml_source_hash_mismatch` |
| Save target concurrency | **Missing today** — OTL.2 adds `expected_translation_hash` |

---

## 3. Official objective

Operators can open one translation from Operations and, in one surface:

1. inspect source (read-only);
2. edit target (post-backed) with dirty state and unsaved protection;
3. save via the existing Workspace save path with **source and target** optimistic concurrency;
4. see structured TI.4 QA and TI.5 assessment for the **last persisted** text (honest when dirty);
5. submit / approve / reject when clean, via ADR-0015 services;
6. see publication status + TI.7 explain (read-only) and understand approved ≠ published;
7. return to Operations without losing list filters.

---

## 4. Unified-detail architecture

**Decision:** Extend [`OperationsInspector.tsx`](../../assets/translator-workspace/src/components/OperationsInspector.tsx) in place (OTL.1 Decision A). **Do not** build a second detail application.

**IA:** Stacked sections (WP admin convention). At wide widths, source|target use a two-column CSS grid inside the texts section — not a separate tabbed detail IA.

**Section order:**

1. Header (identity, close / return to Operations)
2. Lifecycle axes (`status`, `review_status`, `publish_status`, `is_stale`, attention)
3. Source (read-only) + Target editor
4. Save / dirty / concurrency / invalidation feedback
5. TI.4 QA (structured; last-saved honesty when dirty)
6. TI.5 assessment (structured; last-saved honesty when dirty)
7. Review controls (unavailable when dirty)
8. TI.7 publication explain (read-only; last-saved when dirty) — **no publish button**
9. Provenance (bounded) + navigation (source / frontend / Open in Translate)

Component may be renamed toward `TranslationDetailWorkspace` while remaining the same React tree evolution of the inspector.

Data load: parent continues to `GET /aiml/v1/workspace/operations/{translation_id}` (OTL.0). After successful save/review: **re-GET** detail.

---

## 5. OTL.1 inspector reuse

| Rule | Disposition |
|---|---|
| Extend Decision A inspector | **Required** |
| Second parallel detail UI | **Forbidden** |
| Raw JSON evidence dumps | Replace with structured panels in OTL.2 |
| Mutation controls in OTL.1 | Remain absent until this milestone’s editor/review slots |

---

## 6. Source presentation

| Item | Contract |
|---|---|
| Full source | Detail `source_text` |
| Format | Show `text_format`; display as plain/`pre` or read-only control |
| HTML / blocks | Raw stored text displayed **escaped**; no Gutenberg visual editor |
| Metadata | `source_type`, `source_id`, `source_subtype`, `field_key`, `segment_key` |
| Links | Existing `links.edit_link`, frontend URLs |
| Source mutation | **Unsupported** |

---

## 7. Target editor

- Slim **single-segment** editor for the opened `translation_id`.
- Reuse Translate patterns: draft helpers ([`segment-rows.ts`](../../assets/translator-workspace/src/utils/segment-rows.ts)), `saveSegment` ([`workspace-api.ts`](../../assets/translator-workspace/src/api/workspace-api.ts)), textarea UX from `SegmentRowView`.
- **Do not** mount the whole Translate multi-segment tab inside the inspector.
- Translate tab remains the multi-segment object editor; detail offers “Open in Translate”.
- **One save implementation:** shared `saveSegment` / `WorkspaceService::save_segment` (including new target-hash field) used by both surfaces.
- Editor control: `TextareaControl` / textarea — **not** a rich-text editor.
- Deferred CAT: segment splitting, comments, inline diffs, concordance, keyboard CAT shortcuts.

**Mutation eligibility:** only when `source_type === 'post'` **and** capability/admission allow **and** post-scoped Workspace routes apply. See §12.

---

## 8. Save semantics (Store — preserve)

Path: `POST /aiml/v1/workspace/{post_id}/segments/{segment_key}?language=` → `WorkspaceService::save_segment` → `QAEngine` → `Store::save_translation`.

On **material** `translated_text` change (`translation_hash` differs after write):

| Axis | Effect |
|---|---|
| provenance `status` | default `manually_edited` |
| `review_status` | `not_submitted` + `Store::review_clear_fields()` |
| `publish_status` | `unpublished` + `Store::publish_clear_fields()` |
| `is_stale` | forced `0` on save |
| QA / assessment / explain | not persisted; refreshed by detail re-GET |

**No-op save** (same `translation_hash`): preserves review and publish (ADR-0015 / Store tests).

UI after material save must surface review reset and publish invalidation. approved ≠ published remains explicit.

No duplicate Store or OTL-specific save service.

---

## 9. Dirty-state evidence honesty (Amendment A — frozen)

While `dirty === true` (local draft ≠ last loaded/persisted target):

1. Displayed TI.4 QA, TI.5 assessment, review state, TI.7 publication explain, provenance, and publication state describe the **last persisted** translation — **not** the unsaved draft.
2. Evidence panels **visibly** communicate “based on last saved translation” (or equivalent).
3. UI must not imply current evidence evaluated the unsaved draft.
4. Submit / approve / reject are **unavailable** until save success or explicit discard/revert.
5. After successful save: re-GET detail; refresh QA, assessment, review, publication explain, `allowed_actions`.
6. Failed save: draft remains dirty; review mutations remain unavailable.
7. Unsaved-navigation protection remains active (`beforeunload` + in-app confirms).

**Hard product invariant:** An operator must never look at unsaved target text and accidentally submit/approve/reject the older persisted translation while believing the visible draft was reviewed.

**Not a new review policy** — presentation safety gate only. Server remains review authority.

**No** live per-keystroke QA.

### Dirty-state flows (acceptance anchors)

| Flow | Behavior |
|---|---|
| CLEAN | Persisted text visible; evidence authoritative for that text |
| DIRTY | Evidence labelled last-saved; review mutation unavailable |
| SAVE SUCCESS | Store mutation; review/publish invalidation as existing contracts; re-fetch; evidence current |
| SAVE FAILURE | Local draft retained; dirty true; review unavailable |
| DISCARD | Restore authoritative persisted target; dirty false; evidence matches visible target |

---

## 10. Source concurrency (existing)

Client sends `source_hash` from last load.

`WorkspaceService::save_segment` compares to current assembled source hash.

Mismatch → **409** `aiml_source_hash_mismatch` with refreshed segment payload.

**Does not** protect target-to-target lost updates.

---

## 11. Target concurrency (Amendment B — frozen)

### Audit (repository fact at freeze)

| Guard | Today |
|---|---|
| Source | Yes — `source_hash` |
| Review | Yes — `expected_review_status` / `submitted_translation_hash` |
| Target | **No** — save accepts `translated_text` + `source_hash` only |

Store already persists `translation_hash CHAR(40)` ([`Schema.php`](../../src/Database/Schema.php)) and computes it via `Store::translation_hash()` on every save. Segment/detail ViewModels do **not** yet expose it as an optimistic token; save does **not** yet compare it.

Silent lost-update scenario today:

> A loads T1 → B saves T2 → A saves T3 with unchanged source → **T2 overwritten**.

### OTL.2 contract (no schema)

1. **Expose** current `translation_hash` on Workspace segment ViewModel and operations detail ViewModel (additive read fields).
2. Save request carries **`expected_translation_hash`** (value from last authoritative load). For OTL.2 / shared Workspace manual save used by Translate + detail: the field is **required** (clients always send it). **Do not** copy the empty-`source_hash` skip loophole — omitting `expected_translation_hash` must **fail closed** (422/400 invalid request), not silently last-write-wins. Empty-string expected hash is valid only when the client knowingly asserts “no prior persisted target” (new/missing row).
3. Shared `WorkspaceService::save_segment` compares `expected_translation_hash` to the current persisted row’s `translation_hash` (empty string if no prior translation row/text).
4. Mismatch → **409** with dedicated code **`aiml_translation_hash_mismatch`** (must **not** be collapsed into `aiml_source_hash_mismatch`) and refreshed server segment/detail payload; **persisted newer target unchanged**. Controller/exception mapping must discriminate source vs target conflict kinds for the client.
5. Client **preserves** local unsaved draft; shows conflict; operator may refresh/compare before retry — **no automatic overwrite**.
6. Translate tab `saveSegment` updated in lockstep (one save implementation).
7. Forbidden: new DB column, lock table, durable editing session, general locking subsystem, OTL-specific Store, presenting last-write-wins as concurrency safety.

### Required scenario

```text
A loads T1 with translation_hash H1
B loads T1/H1
B saves T2 → hash H2
A attempts save T3 with expected_translation_hash H1
→ server H1 ≠ H2 → 409 aiml_translation_hash_mismatch
→ T2 remains; A's draft T3 recoverable in UI
```

Source and target concurrency remain **explicitly distinct**.

---

## 12. Cross-object inspection vs post-backed mutation (Amendment C — frozen)

| Coverage level | Disposition |
|---|---|
| Unified **inspection** | **Supported** for all object types already admitted by OTL.0/OTL.1 detail |
| Unified **edit/review mutation** | **Supported** for admitted **post-backed** types via existing Workspace POSTs |
| Taxonomy / term (and other non-post) **mutation** | **Deferred** in OTL.2 — known OTL coverage debt at milestone closure |

UI honesty rules:

- Non-post detail remains readable when authorized.
- Target editor must not appear functional for unsupported mutation types.
- Submit/approve/reject must not appear unless post-scoped authoritative services apply.
- No dead controls; no `post_id` mutation calls for non-post.
- Do **not** treat generic `allowed_actions.edit === true` as sufficient when `source_type !== post` (today `AllowedActionsResolver::capability_flags` defaults `can_edit_source` true without a post — OTL.2 must hard-gate UI and may narrow mutation admission for non-`SOURCE_POST` as presentation honesty **without** inventing term REST).

No TSC expansion. No site-specific adapters.

---

## 13. Raw target round-trip integrity (Amendment D — frozen)

| Concern | Contract |
|---|---|
| Display | Escape raw HTML/text for rendering; stored markup must **never execute** in admin UI |
| Initialize editor | Authoritative stored `translated_text` |
| Client transforms | **Forbidden:** sanitize-into-different-content, whitespace normalize, HTML beautify/minify/reformat, entity decode/re-encode, placeholder alteration, line-ending rewrite beyond existing server semantics unless operator edits |
| Server | Existing save/QA path remains authoritative |
| Rich text | **Not introduced** |

Representative fixtures (unit/Playwright as appropriate): plain text; Unicode; placeholders; HTML tags; HTML entities; multiline.

The editor must not become an accidental translation transformer.

---

## 14. Manual validation asymmetry (intentional)

| Path | Validator |
|---|---|
| Manual workspace save | `QAEngine` + `WorkspaceQAPolicy` (block-on-error policy) |
| AI persist | `ResponseValidator` / PersistSafetyPolicy |

OTL.2 **preserves** this asymmetry. Do not route manual edits through `ResponseValidator`.

---

## 15. QA presentation (TI.4)

- Owner: TI.4 detectors via detail `qa` / post-save refresh — **no JS detectors**, no quality %.
- Structured panel (reuse/adapt `QAPanel`): errors / warnings / info + messages/evidence.
- Refresh: on detail load; after successful persist; after review mutation refresh — **not** per keystroke.
- While dirty: show last-persisted QA + honesty banner (§9).

Path-B duplicate QA/assessment detector work on detail remains **known debt** unless a zero-ownership-change local reuse is trivially available; do not redesign TI.4/TI.5.

---

## 16. TI.5 assessment presentation

- R1.0 unchanged; read-only evidence.
- Categories: `blocked` | `needs_review` | `review_recommended` | `structurally_clean`.
- Show reasons/facets / evidence completeness from payload.
- **No score. No confidence %. No publication decision.**
- Label distinctly from ADR-0015 “Pending review” (machine ID `needs_review` remains TI.5-only vocabulary).
- While dirty: last-persisted assessment + honesty banner.

---

## 17. Review workflow

ADR-0015 + `ReviewWorkflowService` **unchanged**.

States: `not_submitted` | `pending` | `approved` | `rejected`.

| Action | REST | Capability |
|---|---|---|
| Submit | `POST …/{post_id}/segments/{key}/submit-review` | `aiml_translate` + `edit_post` |
| Approve | `POST …/approve` | `aiml_review_translations` + `edit_post` |
| Reject | `POST …/reject` (reason required) | review + `edit_post` |

**approved ≠ published** — UI copy required. Approve does not publish. Approve/reject do not rewrite translation text.

`allowed_actions` = UI admission hint only. Mutations always revalidate server-side.

**Dirty presentation gate (§9)** additionally disables review mutations while dirty.

On 409: show conflict, refresh authoritative state, do not invent client transitions.

---

## 18. Edit → approval / publication invalidation

**Preserve existing Store / ADR behavior — not a new choice.**

- Material edit → `review_status=not_submitted` + clear review metadata (ADR-0015).
- Material edit → `publish_status=unpublished` + clear publish metadata (ADR-0020).
- No-op save preserves both.
- After invalidation, resubmit requires explicit submit.
- Stale flag forced `0` on save; **retranslate workflow remains OTL.3**.

---

## 19. Publication boundary (OTL.3)

OTL.2 **may show:** `publish_status`, TI.7 explain, blocked reasons, published timestamps, post-edit invalidation visibility.

OTL.2 **must not:** publish / unpublish controls or mutation.

CTA copy only (e.g. publication workflow comes later) — no fake mutate button.

**Admission honesty:** OTL.0 detail may still return `allowed_actions` descriptors for `publish` / `unpublish` / `retranslate_stale`. OTL.2 UI **must not render mutation controls** for those actions even when admitted — display status/explain/CTA only. Rendering those mutate controls is OTL.3 scope.

---

## 20. Stale / retranslate boundary (OTL.3)

OTL.2 **may show:** `is_stale`, source/hash context for editing.

OTL.2 **must not:** retranslate orchestration, source_hash redesign, auto-unpublish, or render a functional retranslate mutation control from `allowed_actions`.

---

## 21. Jobs boundary (OTL.4)

`jobs: null` stub remains. No Jobs recovery UI in OTL.2.

---

## 22. Bulk boundary (OTL.5)

No new bulk engine. Existing Translate batch save / Review batch approve-reject may remain as today.

---

## 23. Operations → detail navigation

- Extend Operations URL sync ([`operations-url.ts`](../../assets/translator-workspace/src/utils/operations-url.ts)) with `translation_id` (keep language, attention, filters, page).
- Close/back restores list with same query.
- No new top-level admin menu.
- Fix URL hygiene when leaving Operations (`clearOperationsViewFromUrl` currently unused).

---

## 24. Review tab relationship

Keep Review queue tab. Incremental convergence: optional deep-link into Operations unified detail when `translation_id` available (**Partial** if queue DTO lacks id — then keep Open in Translate). Do **not** remove Review in OTL.2.

---

## 25. Translate tab relationship

Keep Translate multi-segment editor. Shared save path including `expected_translation_hash`. Detail “Open in Translate” for full object context. No big-bang Translate rewrite.

---

## 26. REST / backend changes

| Need | Approach |
|---|---|
| Load detail | Existing `GET …/operations/{translation_id}` |
| Save | Existing segment POST + **required** `expected_translation_hash` + existing `source_hash`; target mismatch → **409** `aiml_translation_hash_mismatch` (distinct mapping) |
| Expose hash | Additive `translation_hash` on segment + detail ViewModels |
| Review | Existing submit/approve/reject |
| Refresh | Re-GET detail after success |
| Non-post admission honesty | Optional `AllowedActionsResolver` deny for mutation actions when not `SOURCE_POST` |

**No** new OTL mutation facade. **No** Integration API change. **No** schema.

---

## 27. Conflict UX

| Conflict | Code | Client |
|---|---|---|
| Source | `aiml_source_hash_mismatch` | Preserve draft; show conflict; refresh/compare |
| Target | `aiml_translation_hash_mismatch` | Preserve draft; show conflict; refresh/compare; **no auto-overwrite** |
| Review | existing review 409 family | Refresh; revalidate |

No general locking system.

---

## 28. Unsaved changes

- `beforeunload` when dirty.
- Confirm on Close, tab switch, Open-in-*, language/translation switch.
- No durable draft persistence beyond memory.

---

## 29. Permissions

Unchanged caps:

- Operations view: `aiml_translate` OR `aiml_review_translations`
- Detail content (post): also `edit_post`
- Edit / submit: `aiml_translate` + `edit_post`
- Approve / reject: `aiml_review_translations` + `edit_post`

No new reviewer role architecture.

---

## 30. Accessibility

Keyboard editing; labelled controls; visible focus; error association; `aria-live` for save/review/conflict; accessible review dialogs and unsaved confirms; no color-only QA/risk; logical focus after save/review.

---

## 31. Responsive behavior

Wide: source|target side-by-side. Narrow admin: stack source → target → evidence → actions. Controls remain reachable. Not a mobile-first redesign.

---

## 32. Playwright

Local suite `acceptance/otl2-browser/` (mirror OTL.1; **not CI-gated** unless infra policy changes).

Minimum:

1. open from Operations  
2. source/target visible  
3. edit → dirty  
4. evidence honesty banner while dirty  
5. review controls unavailable while dirty  
6. save → refresh  
7. QA/assessment structured  
8. submit / approve / reject  
9. approved ≠ published visible  
10. discard restores clean  
11. target 409 preserves draft  
12. non-post: no functional mutate controls  
13. round-trip fixtures  
14. unsaved navigation protection  
15. responsive smoke  
16. keyboard basics  

No live AI.

---

## 33. Performance

One detail GET per open; one refresh GET after save/review. Avoid duplicate client fetches from layering. Bounded server QA/assessment/explain on detail acceptable. Path-B debt may remain.

---

## 34. Privacy / security

Authorized object access; escape all rendered text; no prompt/provider body/credential leakage; HTML not executed in admin; mutation capability checks; REST nonce via `apiFetch`.

---

## 35. Site / SaaS neutrality

Hard gate. No Biopentra / biopentra.eu / peptides / site IDs / site taxonomy / Swedish-only assumptions / site workflow rules in production UI, tests, or contracts. Neutral fixtures.

---

## 36. UD1–UD56 capability matrix (frozen)

| ID | Capability | Disposition |
|---|---|---|
| UD1 | Unified detail surface | **Supported** |
| UD2 | Reuse/extend OTL.1 inspector | **Supported** |
| UD3 | Cross-object inspection (OTL.0/1 types) | **Supported** |
| UD4 | Source display (read-only) | **Supported** |
| UD5 | Post-backed target editing | **Supported** |
| UD6 | Taxonomy/term target editing | **Deferred** |
| UD7 | Save via shared Workspace path | **Supported** |
| UD8 | Dirty state | **Supported** |
| UD9 | Dirty evidence honesty (last-persisted) | **Supported** |
| UD10 | Unsaved navigation warning | **Supported** |
| UD11 | TI.4 QA structured display | **Supported** |
| UD12 | TI.5 assessment structured display | **Supported** |
| UD13 | Review state display | **Supported** |
| UD14 | Submit (post-backed, not dirty) | **Supported** |
| UD15 | Approve (post-backed, not dirty) | **Supported** |
| UD16 | Reject (post-backed, not dirty) | **Supported** |
| UD17 | Taxonomy/term review mutation | **Deferred** |
| UD18 | Dirty blocks review mutations (UI gate) | **Supported** |
| UD19 | Publication status display | **Supported** |
| UD20 | Publication explain display | **Supported** |
| UD21 | Publish mutation | **Deferred** (OTL.3) |
| UD22 | Unpublish mutation | **Deferred** (OTL.3) |
| UD23 | Stale display | **Supported** |
| UD24 | Retranslate orchestration | **Deferred** (OTL.3) |
| UD25 | Jobs linkage | **Deferred** (OTL.4) |
| UD26 | Provenance display | **Partial** |
| UD27 | TM evidence | **Partial** |
| UD28 | Operations↔detail URL navigation | **Supported** |
| UD29 | Review queue integration | **Partial** |
| UD30 | Translate tab integration (shared save) | **Partial** |
| UD31 | Source concurrency (`source_hash`) | **Supported** |
| UD32 | Target concurrency (`expected_translation_hash`) | **Supported** |
| UD33 | Review concurrency tokens | **Supported** |
| UD34 | Concurrent edit locking subsystem | **Unsupported** |
| UD35 | Raw target round-trip integrity | **Supported** |
| UD36 | HTML escaped display (no execute) | **Supported** |
| UD37 | Gutenberg visual editor | **Deferred** |
| UD38 | Woo via generic post adapters | **Partial** |
| UD39 | Comments / assignment / notifications | **Deferred** |
| UD40 | History timeline | **Deferred** |
| UD41 | Bulk review from detail | **Deferred** |
| UD42 | Accessibility | **Supported** |
| UD43 | Responsive admin layout | **Supported** |
| UD44 | Playwright local acceptance | **Supported** |
| UD45 | Playwright CI gate | **Deferred** |
| UD46 | Public Integration API | **Unsupported** |
| UD47 | TSC | **Unsupported** |
| UD48 | Schema / TARGET change | **Unsupported** |
| UD49 | New ADR | **Unsupported** |
| UD50 | ResponseValidator on manual save | **Unsupported** |
| UD51 | Live per-keystroke QA | **Unsupported** |
| UD52 | Durable editor drafts | **Unsupported** |
| UD53 | CAT features | **Deferred** |
| UD54 | approved ≠ published messaging | **Supported** |
| UD55 | Site/SaaS neutrality | **Supported** |
| UD56 | Manual QAEngine asymmetry preserved | **Supported** |

---

## 37. Work packages OTL2.0–OTL2.8

### OTL2.0 — Baseline / factual lock

| Field | Content |
|---|---|
| **Objective** | Confirm freeze baseline, owners, contracts on main after planning merge |
| **Dependencies** | This plan Architecture Frozen on main |
| **Code areas** | Docs only at freeze; impl starts later |
| **Tests** | None in planning |
| **Acceptance** | TARGET 7; version 1.2.0; owners named |
| **STOP** | Baseline drift; desire to change ADR-0015/schema |
| **Completion gate** | Plan frozen on main |

### OTL2.1 — Unified detail shell

| Field | Content |
|---|---|
| **Objective** | Evolve inspector into sectioned shell; URL `translation_id`; non-post read-only honesty |
| **Dependencies** | OTL2.0 |
| **Code areas** | `OperationsInspector.tsx`, `OperationsPanel.tsx`, `operations-url.ts`, `style.css`, types |
| **Tests** | Unit URL helpers; PluginGuard no publish controls |
| **Acceptance** | One surface; filters preserved; no premature mutations |
| **STOP** | Second detail app |
| **Completion gate** | Shell usable from Operations |

### OTL2.2 — Target editor + save + concurrency + round-trip

| Field | Content |
|---|---|
| **Objective** | Draft/dirty/save; `expected_translation_hash` + `source_hash`; round-trip integrity; invalidation notices; Translate save lockstep |
| **Dependencies** | OTL2.1 |
| **Code areas** | Editor widget; `workspace-api.ts`; `WorkspaceService::save_segment`; ViewModels; `WorkspaceController` |
| **Tests** | Unit dirty/round-trip; integration target 409; source 409; no-op preserve |
| **Acceptance** | Editor/save/concurrency portions of Flows A/B/D/E (dirty draft + hash guards + round-trip); full Flow A evidence/review gates land in OTL2.3–2.4 |
| **STOP** | Schema; last-write-wins; empty-skip loophole for expected hash; client transformers |
| **Completion gate** | Shared save path green |

### OTL2.3 — QA + assessment + dirty honesty

| Field | Content |
|---|---|
| **Objective** | Structured TI.4/TI.5 panels; last-saved banners when dirty; refresh after persist |
| **Dependencies** | OTL2.1–2.2 |
| **Code areas** | `QAPanel` adapt; assessment presenter; detail refresh |
| **Tests** | Unit mappers; no % scores; dirty banner helpers |
| **Acceptance** | Dirty evidence honesty; structured evidence |
| **STOP** | Keystroke QA; TI.5 policy change |
| **Completion gate** | Evidence panels PASS |

### OTL2.4 — Review actions + dirty gate

| Field | Content |
|---|---|
| **Objective** | Submit/approve/reject when clean; disabled when dirty; approved≠published |
| **Dependencies** | OTL2.1–2.3 |
| **Code areas** | Review controls; `ReviewDecisionDialog` reuse; review API helpers |
| **Tests** | Integration permission/state matrix; dirty gate unit; PluginGuard no new review policy class |
| **Acceptance** | Flow C; review 409 refresh |
| **STOP** | New review states; approve⇒publish |
| **Completion gate** | Review actions PASS |

### OTL2.5 — Navigation / tab relationship

| Field | Content |
|---|---|
| **Objective** | Ops URL round-trip; Translate open; Review coexistence; URL hygiene |
| **Dependencies** | OTL2.1+ |
| **Code areas** | `App.tsx`, `ReviewQueuePanel` (optional deep-link), operations URL |
| **Tests** | Unit URL; browser nav smoke |
| **Acceptance** | Filters preserved; tabs remain |
| **STOP** | Deleting Review/Translate tabs |
| **Completion gate** | Nav PASS |

### OTL2.6 — Conflict / security / a11y / responsive / non-post

| Field | Content |
|---|---|
| **Objective** | 409 draft preservation; a11y; layout; AllowedActions honesty for non-post |
| **Dependencies** | OTL2.2–2.4 |
| **Code areas** | CSS; focus; confirms; resolver honesty |
| **Tests** | a11y Playwright; permission/non-post integration |
| **Acceptance** | Flow F; conflict UX |
| **STOP** | Locking subsystem; rich-text introduction |
| **Completion gate** | Hardening PASS |

### OTL2.7 — Playwright + manual acceptance

| Field | Content |
|---|---|
| **Objective** | `acceptance/otl2-browser/` + focused manual script |
| **Dependencies** | OTL2.1–2.6 |
| **Tests** | Listed browser flows; no live AI; not CI-gated |
| **Acceptance** | Flows A–F covered |
| **STOP** | Broad browser CI without infra decision |
| **Completion gate** | Local suite documented green |

### OTL2.8 — Docs / milestone closure

| Field | Content |
|---|---|
| **Objective** | Validation log; term-mutation debt; Path-B debt; roadmap → OTL.3 planning next |
| **Dependencies** | All prior green |
| **Acceptance** | Closure docs; version unchanged |
| **STOP** | Version bump / tag / release in this milestone unless separately authorized |
| **Completion gate** | OTL.2 Complete on main |

---

## 38. Acceptance criteria (88 contiguous)

### Parent / boundaries (1–12)

1. OTL parent OT3/OT4/OT7 honored for this milestone’s **admitted UI scope** (unified detail, editor Partial, review controls, QA/assessment surfacing). Parent OT3 “Jobs context” remains Deferred to OTL.4 (`jobs: null` stub); OTL.2 does not claim Jobs linkage as delivered.  
2. No OTL.3 publish/unpublish mutation controls (even if `allowed_actions` admits them).  
3. No OTL.3 retranslate orchestration / mutate controls.  
4. No OTL.4 Jobs recovery.  
5. No OTL.5 new bulk engine.  
6. No TSC under OTL.2.  
7. `Migrator::TARGET` remains **7**.  
8. No schema migration.  
9. No Integration API expansion.  
10. No new ADR.  
11. No composite persisted operator state / durable drafts.  
12. Owners preserved: Store, TI.4, TI.5, ReviewWorkflowService, TI.7 PublicationService.

### Inspector / architecture (13–22)

13. Extends OTL.1 Decision A inspector.  
14. No second detail application.  
15. One post-scoped save implementation shared with Translate.  
16. Detail load uses existing operations detail GET.  
17. Mutations use existing Workspace POSTs (plus additive expected target hash).  
18. `allowed_actions` remain admission-only.  
19. Cross-object inspection Supported for OTL.0/1 detail types.  
20. Non-post mutation controls honestly unavailable (no dead controls; no post_id calls).  
21. No CAT platform.  
22. Term/taxonomy mutation Deferred (no new term REST).

### Dirty evidence / review gate (23–32)

23. While dirty, evidence panels show last-persisted QA/assessment/explain/review/provenance/publish axes.  
24. Visible “last saved” honesty labelling while dirty.  
25. UI does not imply unsaved draft was QA/assessed/reviewed.  
26. Submit unavailable while dirty.  
27. Approve unavailable while dirty.  
28. Reject unavailable while dirty.  
29. Successful save re-fetches detail and refreshes evidence + `allowed_actions`.  
30. Failed save keeps draft dirty and review unavailable.  
31. Discard restores persisted target and clears dirty.  
32. Dirty review gate is presentation-only (server remains review authority).

### Editor / save / concurrency / round-trip (33–48)

33. Source is read-only.  
34. Post-backed target editable when permitted.  
35. Dirty state when draft ≠ persisted.  
36. Save success path via `WorkspaceService::save_segment`.  
37. Save error / QA-block path surfaces feedback.  
38. Manual path uses QAEngine, not ResponseValidator.  
39. Material edit clears review to `not_submitted` per Store/ADR-0015.  
40. Material edit sets publish `unpublished` per Store/ADR-0020.  
41. No-op save preserves review and publish.  
42. Source concurrency: `source_hash` → 409 `aiml_source_hash_mismatch` (distinct from target).  
43. Target concurrency: required `expected_translation_hash` → 409 `aiml_translation_hash_mismatch` (not collapsed into source code); omit fails closed.  
44. Target conflict leaves persisted newer text unchanged.  
45. Target conflict preserves operator local draft.  
46. Publish-invalidation visible after material edit.  
47. Stale state visible.  
48. Raw round-trip: no client sanitize/normalize/beautify/entity rewrite; HTML escaped on display; fixtures cover plain/Unicode/placeholders/HTML/entities/multiline.

### QA / TI.5 (49–56)

49. Structured QA severities (error/warning/info).  
50. QA messages/evidence shown.  
51. No quality percentage.  
52. QA refreshed after persist (not per keystroke).  
53. TI.5 categories shown honestly.  
54. TI.5 reasons/facets shown.  
55. No assessment score / confidence %.  
56. TI.5 `needs_review` copy distinct from ADR-0015 pending review.

### Review (57–67)

57. Review state catalog unchanged.  
58. Submit works when clean + eligible.  
59. Approve works when clean + eligible.  
60. Reject requires reason.  
61. Controls capability-gated.  
62. Server revalidates every review mutation.  
63. Review 409 conflicts refresh coherently.  
64. approved ≠ published messaging visible.  
65. Approve does not publish.  
66. Approve/reject do not rewrite translation text.  
67. After material edit of approved row, explicit resubmit required.

### Navigation / tabs / UX (68–76)

68. Open detail from Operations.  
69. Operations filters/page/language/attention preserved on return.  
70. URL carries `translation_id` (with Operations view state).  
71. Review tab remains.  
72. Translate tab remains.  
73. Open in Translate works for post-backed context.  
74. Unsaved warn on in-app navigate.  
75. `beforeunload` when dirty.  
76. Wide side-by-side / narrow stacked layouts.

### A11y / security / neutrality / tests / closure (77–88)

77. Keyboard operable detail/editor/review.  
78. Labels, visible focus, error association.  
79. Status announcements (`aria-live`) for save/review/conflict.  
80. No color-only status/QA encoding.  
81. Object-level authorization enforced for post detail/mutate.  
82. Errors do not leak private content inappropriately.  
83. Site/SaaS neutrality gate held.  
84. PluginGuard: no duplicate OTL review/publication policy engine; no schema TARGET drift.  
85. Unit coverage for dirty helpers, action gates, conflict mapping, round-trip fixtures.  
86. Integration coverage for save/review/permissions/target-hash/non-post honesty.  
87. Local Playwright suite documented (not CI-gated).  
88. Docs/closure without version bump or tag in the planning freeze; implementation next step is the combined OTL.2 implementation task.

---

## 39. Test strategy

### Unit

Dirty/honesty helpers; action availability (dirty + non-post); round-trip fixtures; URL state; conflict message mapping; assessment/QA presenters.

### Integration

Detail GET; save with `expected_translation_hash`; target 409; source 409; no-op preserve; material edit invalidation; submit/approve/reject; permissions; non-post admission honesty.

### PluginGuard

No second mutation/review/publication policy owner; TARGET 7; neutrality; no TSC hooks; no Integration API operations leak.

### Browser

`acceptance/otl2-browser/` — Amendments A–D + Flows A–F; local/non-CI.

---

## 40. Manual acceptance (focused)

Open from Operations → edit → dirty honesty → review blocked → save → QA/assessment refresh → submit → approve → confirm not published → reject path → discard path → target conflict UX → non-post read-only → return with filters → permission differences → stale visible → unsaved warning.

No full product validation cycle.

---

## 41. Schema / TARGET / ADR verdict

| Item | Verdict |
|---|---|
| TARGET | **7** unchanged |
| Schema | **None** — reuse existing `translation_hash` column |
| New ADR | **None** — ADR-0015/0019/0020 unchanged |
| Integration API | Unchanged |

STOP and escalate to STATE B only if implementation proves target concurrency needs schema/public redesign (planning freeze assumes narrow path is sufficient).

---

## 42. STOP conditions (implementation)

- Building a second detail app instead of extending the inspector  
- Live per-keystroke authoritative QA  
- Client-side review policy / trusting stale `allowed_actions` for mutation  
- Silent last-write-wins for target saves  
- New DB columns / lock tables / durable drafts  
- New term mutation REST / TSC expansion  
- Publish/unpublish or retranslate UX  
- Jobs recovery / new bulk engine  
- ResponseValidator forced onto manual save  
- Client content transformers violating round-trip  
- Schema bump or new ADR without explicit architecture decision  
- Site-specific / Biopentra behavior  
- Version bump/tag during unauthorized steps  

---

## 43. Verified operator flows (implementation anchors)

**FLOW A — clean edit/save:** load T1 → draft T2 → evidence last-saved while dirty → review unavailable → save → refresh → review/publish reset per Store → evidence for T2.

**FLOW B — no-op save:** load T1 → save unchanged → review/publish preserved.

**FLOW C — approved edit:** approved T1 → draft T2 cannot approve → save clears approval + publication → resubmit required.

**FLOW D — concurrent target:** A T1/H1, B saves T2/H2, A saves T3 with H1 → 409; T2 kept; draft T3 recoverable.

**FLOW E — source change:** `source_hash` mismatch → 409; no blind save.

**FLOW F — non-post:** authorized detail readable; edit/review honestly unavailable; no post_id mutation.

---

## 44. Version / release

Planning freeze: **no** version bump, **no** tag, **no** release. Plugin remains **1.2.0**. Future release numbering remains roadmap-level only.

---

## 45. Exact next step after Architecture Frozen on main

Run the **combined OTL.2 implementation + independent implementation review + merge + milestone closure** task from the frozen main baseline.

Do **not** create the implementation feature branch during this planning freeze.

Do **not** start OTL.3–OTL.6 or TSC under OTL.

---

## 46. Planning freeze checklist

- [x] External amendments A–D incorporated  
- [x] UD1–UD56 frozen  
- [x] 88 contiguous ACs  
- [x] OTL2.0–OTL2.8 ladder  
- [x] Target concurrency without schema  
- [x] Dirty evidence + review gate  
- [x] Cross-object vs post-backed mutation  
- [x] Raw round-trip integrity  
- [x] Independent planning review PASS  
- [ ] Merge `--no-ff` to main  
- [ ] Merge CI green  
- [ ] Planning closure docs  
- [ ] Post-closure CI green  
