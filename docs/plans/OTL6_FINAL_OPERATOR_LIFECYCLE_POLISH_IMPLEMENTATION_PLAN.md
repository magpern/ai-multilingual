# OTL.6 — Final Operator Lifecycle Polish — Implementation Plan

**Status:** **Architecture Frozen** candidate on planning branch (awaiting freeze merge to `main`; production implementation **not started**)
**Milestone:** OTL.6 — Final Operator Lifecycle Polish (Operator Translation Lifecycle program)
**Kind:** Milestone implementation plan (authoritative after freeze merge)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** OTL parent **Architecture Frozen**; OTL.0–OTL.5 **Complete**; TIQ **Complete**; AI Multilingual **v1.2.0**; `Migrator::TARGET` **7**
**Schema:** Migrator `TARGET` = **7** (unchanged — **no migration**, **no new index**)
**ADR:** **No new ADR.** ADR-0015 / ADR-0019 / ADR-0020 / ADR-0011 / TI.6–TI.7 ownership unchanged.
**Planning baseline main HEAD:** `78c56d3c4bba154fe73f54269ae8f0243658849d`
**Planning branch:** `docs/otl6-final-operator-lifecycle-polish-planning-freeze`
**External freeze review:** **PASS** (STATE A — FREEZE; A1–A4 locked)
**Independent planning review:** **PASS**
**Reviewed planning HEAD:** `66a0f405242798f594377e3bf52f3d06348f3179`
**Validation:** [OTL6_FINAL_OPERATOR_LIFECYCLE_POLISH_PLANNING_VALIDATION_LOG.md](OTL6_FINAL_OPERATOR_LIFECYCLE_POLISH_PLANNING_VALIDATION_LOG.md)
**Implementation branch:** **Do not create** until this plan is frozen on `main` and the combined implementation task begins.
**Next after freeze/closure:** Run the combined **OTL.6 Final Operator Lifecycle Polish implementation** + independent implementation review + merge + milestone/program closure from the frozen main baseline. Do **not** start TSC under OTL.
**Related:** [OTL5_BOUNDED_BULK_OPERATIONS_IMPLEMENTATION_PLAN.md](OTL5_BOUNDED_BULK_OPERATIONS_IMPLEMENTATION_PLAN.md); [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md); [OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md](OTL2_UNIFIED_DETAIL_EDIT_REVIEW_IMPLEMENTATION_PLAN.md); [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)

**External-review amendments locked:**

| ID | Topic | Lock |
|---|---|---|
| A1 | Centralized dirty-leave admission | Frozen |
| A2 | Operations context ownership (session snapshot; URL only while on Ops) | Frozen |
| A3 | Jobs→Operations deep-link | **Partial / Deferred (OP15 / JI50)** |
| A4 | Playwright consolidation + archive preservation | Frozen |

**Operational success:** An operator can complete find → inspect/risk → edit/review → publish → verify → Jobs/bulk follow-up without silent dirty discard, without contradictory confirm UX, without losing Operations list context on temporary tab hops (via session snapshot), with honest status language, usable laptop layout/keyboard, and an authoritative consolidated local Playwright suite — without new TIQ policy, schema, ADR, or Deferred feature revival.

**Hard boundary:** OTL.6 does **not** ship Jobs→Ops Store enrichment, bulk retry-failed, durable draft store, second policy engine, frontend eligibility authority, list TI.7 explain N+1, Integration API expansion, schema/TARGET change, new ADR, version/tag bump, deletion of historical `otl{1–5}-browser` archives, live Playwright CI gate, mobile-first redesign, or TSC.

---

## 1. Official objective

Make the already-built Operator Translation Lifecycle coherent as one journey:

**find → inspect/risk → edit/review → publish → verify → Jobs/bulk follow-up**

OTL.6 is a **polish and integration milestone**. It does not create translation, QA, publication, Jobs, or bulk policy. OTL remains presentation/orchestration; TIQ services remain mutation authorities.

Parent mapping: OTL.6 = UX polish + acceptance (parent §32). Dependency: OTL.3 + OTL.5 → OTL.6.

---

## 2. Current-state audit (repository reality)

### Workspace IA
Tabs in `assets/translator-workspace/src/App.tsx`: Operations → Translate → Review queue → Jobs. Forward Open-in-\* exist; Review→Ops missing; Jobs→Ops missing and **not Supported** (A3).

### Confirmations
| Pattern | Used by |
|---|---|
| `window.confirm` (6 sites) | `OperationsPanel.tsx`: bulk; dirty leave/refresh/row-switch; unpublish; retranslate |
| WP `Modal` | `ReviewDecisionDialog`, `JobActionConfirmDialog`, `CreateJobDialog` |
| No confirm | Single **Publish**; Discard; Submit; tab switch; Open-in-\* |

### Dirty protection
Present: close / row switch / refresh / `beforeunload` / OTL.5 A6.  
Gap: tab switch + Open-in-\* silent discard (A1).

### Context
Leave clears Ops URL (language leaks); remount loses filters (A2). Jobs URL write preserves other search params — Ops keys must not linger off-Ops.

### Playwright
`acceptance/otl{1–5}-browser/` local/non-CI; otl3 `testMatch` broken; otl4 package/import drift; no consolidated suite (A4).

### Honesty already sufficient (do not re-plan as features)
- approved ≠ published; overlay-gate ≠ visibility guarantee  
- `enqueued` ≠ translated; A3/A6 bulk rules; Jobs association honesty  
- `allowed_actions` presentation-only; server revalidation  
- List has no TI.4/TI.5/TI.7/Jobs N+1 enrichment  

---

## 3. Operator-friction findings

| ID | Gap | Severity | Amendment |
|---|---|---|---|
| F1 | Dirty leave incomplete on tab / Open-in-\* | High | A1 |
| F2 | Ops context lost + language URL leak / pollution risk | High | A2 |
| F3 | Confirm UX split (native vs Modal) | High | — |
| F4 | Single publish no confirm | Medium | — |
| F5 | Bulk/list raw status codes | Medium | — |
| F6a | No Review→Ops | Medium | — |
| F6b | No Jobs→Ops | Medium | A3 → Partial |
| F7 | Focus restore / focus-visible gaps | Medium | — |
| F8 | Table column priority at laptop widths | Medium | — |
| F9 | Playwright fragmentation + archive breakage | Medium | A4 |

---

## 4. Locked amendments A1–A4

### A1 — Centralized dirty-leave admission

**Freeze mechanism:**

1. Shared dirty-leave **admission API** used by all leave/replace decisions (in-panel and App-level). Because ConfirmDialog is a WP Modal, admission is **async** (`Promise<boolean>` / confirm-then-continue) — not a sync `window.confirm` boolean.
2. App-owned `requestViewChange(next)` / Open-in handlers consult a panel-registered dirty predicate (e.g. `isOperationsDirty(): boolean`). If dirty, App (or a shared helper) opens the shared ConfirmDialog and proceeds only on confirm; if clean, proceeds immediately. Registration is cleared on Operations unmount.
3. In-panel close / row-switch / refresh call the **same** shared admission helper (same ConfirmDialog), not a second policy.
4. **Do not** duplicate independent dirty policy across App / Panel / Inspector.
5. **`beforeunload` remains a separate browser-level guard** in OperationsPanel (sync browser dialog; not the Modal path).
6. **OTL.5 A6 `dirtyBlocksBulk` remains orthogonal and unchanged**.
7. **No durable draft store**; no keep-mounted-hidden panel to preserve dirty text across tabs.

### A2 — Operations context ownership

**Rejected:** leaving Ops query params in the live URL while on other tabs.

**Frozen restore model:**

1. URL is **canonical only while** `viewMode === 'operations'`.
2. On leave: **stash** nav snapshot (language, attention, page, axis filters, `translation_id`) in a **module-level session object** (e.g. `operations-session.ts`) — not localStorage, not DB, not draft text.
3. Then **clear Ops URL keys including `language`**.
4. On remount: hydrate from `peekOperationsSession() ?? readOperationsUrlState()`; rewrite Ops URL.
5. Cold deep-links still use URL when `view=operations`.
6. **Selection and bulk results remain intentionally non-persistent**.

### A3 — Jobs→Operations (OP15 Partial)

| Fact | Evidence |
|---|---|
| OTL.4 semantic identity | `(source_type, source_id, language_id, segment_key)` |
| Ops detail navigation | `translation_id` only |
| Jobs REST/ViewModels/UI | Never emit `translation_id` |
| Store | `Store::get` / `load_object` can resolve tuple → `translation_id` |
| Wired on Jobs wire? | **No** |

**Disposition:** Do **not** widen OTL.6 with Jobs Store enrichment. **OP15 = Partial / Deferred** (continue JI50). No heuristic client identity; no `active_lock_key`; no unbounded lookup; no new policy.

**Contrast:** Review→Ops (OP13/OP14) **Supported** — `Store::query_review_queue` uses `SELECT *` + hydrate (includes `translation_id`); additive ViewModel field is serializer-only. Operations URL `language` is a **code**; Review UI already resolves `language_id → code` via existing `languageCodeForId(languages, …)` — OTL.6 uses that client mapping with `translation_id` (no required new `language_code` REST field).

**Bulk result → Jobs** (OP16) **Supported**.

### A4 — Playwright consolidation + archives

| Package | Disposition |
|---|---|
| `acceptance/otl-browser/` (new) | **Authoritative** current lifecycle suite |
| `otl1-browser`…`otl5-browser` | **Frozen historical archives** — README pointer; **do not delete** |
| In-place fixes | OTL.3 `testMatch`; OTL.4 package name + helper import/login — archives remain runnable |
| Historical asserts | Do **not** rewrite milestone-era expectations to today’s product |
| Live execution | **Local / non-CI** |

---

## 5. Scope — Supported

1. Thin shared WP-`Modal` `ConfirmDialog` for Operations consequential actions; replace Operations `window.confirm`; keep specialized Review/Jobs dialogs.
2. Centralized dirty-leave admission (A1).
3. Session-only Operations nav snapshot (A2).
4. Confirm single publish (parent higher-risk tier).
5. Humanize list publish labels; bulk results show outcome + message/reason_codes; honesty contracts retained.
6. Review queue additive `translation_id` + Review→Ops deep-link.
7. Bulk result `job_id` → Jobs tab deep-link.
8. A11y focus restore + focus-visible; laptop column priority.
9. Authoritative `acceptance/otl-browser/`; archive otl1–5; fix broken configs in place (A4).
10. PluginGuard TS neutrality + architecture forbids.

---

## 6. Explicit non-goals / Deferred / Unsupported

| Item | Disposition |
|---|---|
| Jobs→Operations deep-link / Jobs `translation_id` enrichment | **Partial / Deferred (A3 / OP15 / JI50)** |
| Bulk retry-failed | Deferred (OTL.5 A5) |
| Sync Operations multi-retranslate | Unsupported |
| Operations review bulk | Unsupported |
| Jobs create-from-Operations | Deferred (OTL.4) |
| Jobs-backed attention | Deferred |
| Path-B QA duplication | Deferred (OTL.0) |
| OT15 / OT22–25 / OT27 | Deferred (parent) |
| Selection / bulk-result persistence; localStorage; durable draft | Unsupported |
| Leave Ops params in URL while on other tabs | Unsupported (A2) |
| Live Playwright in default PR CI | Unsupported |
| Delete historical `otl{1–5}-browser` evidence | Unsupported (A4) |
| Schema / TARGET / ADR / version / TSC / second policy | Forbidden |

---

## 7. Authority map

| Concern | Owner |
|---|---|
| Persist / translate | Store / TranslationService |
| Review | ADR-0015 Review services |
| QA / assessment | TI.4 / TI.5 |
| Jobs | TI.6 |
| Publication | TI.7 PublicationService |
| Bulk | OTL.5 `OperationsBulkCoordinator` → TI.7/TI.6 |
| OTL.6 | UI interaction, presentation, navigation, a11y — **no frontend policy** |

`allowed_actions` remains admission/presentation; mutations revalidate server-side.

---

## 8. Lifecycle flow verdicts

| Flow | Verdict | OTL.6 work |
|---|---|---|
| A Attention→edit→review→return | Cross-tab context/dirty gaps | A1 + A2 |
| B Stale→retranslate | Honest | Confirm + bulk reason presentation |
| C Review→publication | Copy OK | Review→Ops link |
| D Publication→verify | Gate wording OK | No fake verification product |
| E Translation→Jobs | Forward OK; reverse Partial | **No OP15 expansion** |
| F Bulk | Semantics OK | Confirm Modal + reasons + →Jobs |
| G Dirty | Narrow OK | Centralized leave admission |

---

## 9. Confirmation / dialog strategy

Thin shared `ConfirmDialog` (WP Modal + title/body/confirm/cancel/busy/error), modeled on `JobActionConfirmDialog` — **not** a general UI framework.

Replace Operations `window.confirm` call sites.  
Do **not** rewrite `ReviewDecisionDialog` or `JobActionConfirmDialog`.  
Single publish gains the same Modal confirm (parent §29 higher-risk tier).

Dirty-leave uses the **same** ConfirmDialog via the **centralized async admission path (A1)** — App tab/Open-in leaves and in-panel close/row/refresh share one decision surface. `beforeunload` remains the separate browser-native path.

---

## 10. Dirty-state verdict (A1 + A6)

- One in-app dirty-leave decision for all replace/unmount transitions.
- A6 bulk intersection unchanged.
- Jobs subtree refresh continues draft-preserving.
- No durable draft store.

---

## 11. REST / ViewModel implications

- **Supported additive:** Review queue `translation_id` from Store row (`ReviewQueueItemViewModel` / serializer).
- **Not Supported:** Jobs item/job-detail `translation_id` enrichment (A3 Partial).
- No new endpoints required for confirm/dirty/session snapshot.
- No Integration API / schema.

---

## 12. Accessibility / responsive

- Targeted a11y improvement — **not** a WCAG compliance claim.
- Laptop-first (OT27 mobile remains Deferred).
- Focus: Modal trap (WP Modal); restore focus to opener on detail close / confirm cancel.
- Responsive: column priority for Operations table at common WP admin widths; overflow-x as fallback.

---

## 13. Playwright decision (A4)

| Layer | Decision |
|---|---|
| Authoritative suite | `acceptance/otl-browser/` |
| Legacy otl1–otl5 | Frozen archives + README; fix P0 config bugs in place; do not delete |
| Live browser | Local / non-CI |
| Offline contracts | In authoritative suite; CI gate not required for freeze |
| F9/F10 | Separate |

---

## 14. Performance / security / neutrality

Preserve: list page ≤50; no list TI.4/5/7/Jobs N+1; bulk ≤50; bounded Jobs lookup; no AI for presentation.  
Privacy: no prompts/secrets in UI polish.  
Neutrality: no Biopentra/site strings; extend PluginGuard to TS workspace sources.

---

## 15. Schema / ADR / version / STATE

| Item | Verdict |
|---|---|
| Schema / TARGET | Remain **7** |
| ADR | No new ADR |
| Version / tag | **1.2.0** / `v1.2.0` unchanged |
| STATE | **A — FREEZE** |

---

## 16. Requirement matrix OP1–OP24

| ID | Requirement | Disposition |
|---|---|---|
| OP1 | Shared accessible ConfirmDialog for Operations consequential actions | Supported |
| OP2 | Replace all Operations `window.confirm` | Supported |
| OP3 | Confirm single publish (higher-risk tier) | Supported |
| OP4 | Single shared **async** in-app dirty-leave admission (App gate + panel dirty predicate + ConfirmDialog); all tab/Open-in/close/row/refresh paths | Supported |
| OP5 | `beforeunload` remains separate browser guard | Supported |
| OP6 | OTL.5 A6 `dirtyBlocksBulk` unchanged / orthogonal | Supported |
| OP7 | Session-only Ops nav snapshot on leave; clear URL including `language`; hydrate on remount | Supported |
| OP8 | URL canonical only while on Operations; no cross-tab Ops param pollution | Supported |
| OP9 | Selection + bulk results intentionally non-persistent | Supported |
| OP10 | Humanize list publish_status labels | Supported |
| OP11 | Bulk results show outcome + message/reason_codes | Supported |
| OP12 | Honesty: enqueued≠translated; approved≠published; gate≠visibility | Supported |
| OP13 | Review queue exposes `translation_id` (additive ViewModel) | Supported |
| OP14 | Review → Operations detail deep-link | Supported |
| OP15 | Jobs → Operations deep-link | **Partial / Deferred (A3)** |
| OP16 | Bulk result job_id → Jobs deep-link | Supported |
| OP17 | Detail close restores focus to opener | Supported |
| OP18 | Extend focus-visible / keyboard-operable confirms | Supported |
| OP19 | Operations table laptop column priority | Supported |
| OP20 | Authoritative `acceptance/otl-browser/` consolidation | Supported |
| OP21 | Preserve otl1–otl5 as archives; fix broken configs in place; no delete | Supported |
| OP22 | Live Playwright remains non-CI | Supported |
| OP23 | PluginGuard TS neutrality + no second policy | Supported |
| OP24 | No schema/TARGET/ADR/version/TSC/bulk-retry/durable-draft | Supported |

---

## 17. Acceptance criteria AC1–AC52

### Confirms
**AC1** Operations bulk publish uses accessible ConfirmDialog (not `window.confirm`).  
**AC2** Operations bulk unpublish uses ConfirmDialog.  
**AC3** Operations bulk enqueue retranslate uses ConfirmDialog.  
**AC4** Single publish uses ConfirmDialog.  
**AC5** Single unpublish and sync retranslate use ConfirmDialog.  
**AC6** No remaining Operations `window.confirm` for the above consequential paths.

### Dirty-leave (A1)
**AC7** One shared async in-app dirty-leave admission mechanism (panel dirty predicate + ConfirmDialog; App `requestViewChange` gated).  
**AC8** Leaving Operations via workspace tab change admits/denies through that mechanism.  
**AC9** Open-in-Translate admits/denies through that mechanism.  
**AC10** Open-in-Review admits/denies through that mechanism.  
**AC11** Open-in-Jobs admits/denies through that mechanism.  
**AC12** Detail close, other-row switch, and detail refresh use the same admission path (not a second policy).  
**AC13** No durable draft store; dirty draft is not preserved across tabs by hidden mount.  
**AC14** OTL.5 A6 `dirtyBlocksBulk` behavior unchanged (regression).

### Context (A2)
**AC15** On leave Operations, session snapshot stores language, attention, page, axis filters, `translation_id`.  
**AC16** On leave, Ops URL params are cleared **including `language`**.  
**AC17** While on Jobs/Translate/Review, Ops-specific params do not remain as canonical URL pollution.  
**AC18** Returning to Operations remount hydrates from session snapshot (when present) then rewrites Ops URL.  
**AC19** Selection and bulk results remain non-persistent across unmount.  
**AC20** Cold load / shared deep-link with `view=operations` hydrates from URL; in-SPA return to Operations prefers session snapshot when present, then rewrites Ops URL.

### Honesty / presentation
**AC21** List publish_status is human-labeled (not raw-only).  
**AC22** Bulk result rows present outcome plus message and/or reason_codes when provided by API.  
**AC23** UI does not introduce Eligible / Ready to publish / Publishable labels from cheap Store state.  
**AC24** `enqueued` is not presented as translated/completed translation.  
**AC25** approved ≠ published honesty retained.  
**AC26** Publication gate overlay-eligibility ≠ universal frontend visibility retained.

### Navigation
**AC27** Review queue exposes `translation_id` when present on Store row.  
**AC28** Review → Operations detail deep-link works when `translation_id` is present (language code via existing client `languageCodeForId`).  
**AC29** Operations → Jobs navigation retained.  
**AC30** Bulk result `job_id` can open Jobs tab deep-link.  
**AC31** Jobs → Operations deep-link is **not required** for OTL.6 PASS (OP15 Partial).

### A11y / responsive
**AC32** ConfirmDialog is keyboard operable (WP Modal).  
**AC33** Closing detail restores focus to a sensible opener control.  
**AC34** Primary Operations interactive controls have visible focus treatment.  
**AC35** Status/result feedback remains text-readable (not color-only).  
**AC36** aria-live / role=status patterns retained for bulk/status feedback.  
**AC37** Operations table applies laptop column priority / progressive de-emphasis.  
**AC38** Plan does not claim WCAG certification.

### Playwright (A4)
**AC39** `acceptance/otl-browser/` is the authoritative current OTL lifecycle suite.  
**AC40** `otl1-browser` through `otl5-browser` remain on disk as historical archives with README pointers.  
**AC41** Historical milestone packages are not deleted.  
**AC42** Objectively broken archive config (OTL.3 testMatch; OTL.4 name/import/login) is fixed in place without rewriting historical product expectations.  
**AC43** Live browser execution remains local/non-CI.  
**AC44** Authoritative suite covers representative lifecycle smoke including dirty-leave and context restore (local).

### Authority / program
**AC45** No frontend publication/eligibility policy engine.  
**AC46** No Operations list TI.5/TI.7/Jobs N+1 enrichment for polish.  
**AC47** Existing pagination/bulk caps (≤50) unchanged.  
**AC48** PluginGuard includes TS neutrality and no-second-policy forbids as frozen.  
**AC49** Mandatory CI gates (phpcs/unit/integration/quality/build) remain green.  
**AC50** Version remains 1.2.0; no release tag from OTL.6.  
**AC51** `Migrator::TARGET` remains 7; no new ADR.  
**AC52** TSC not started under OTL.6.

**AC count: 52** (contiguous AC1–AC52).

---

## 18. Work-package ladder OTL6.0–OTL6.8

| WP | Objective | Primary surfaces | Tests / evidence | Dependencies | Stop if |
|---|---|---|---|---|---|
| **OTL6.0** | Baseline + A1–A4 characterization | docs baseline | characterization notes | Frozen main | Drift from closed OTL.5 main |
| **OTL6.1** | ConfirmDialog + replace Operations confirms + publish confirm | `ConfirmDialog.tsx`, `OperationsPanel.tsx` | unit + local browser | OTL6.0 | New policy |
| **OTL6.2** | Centralized dirty-leave admission (A1) | `App.tsx`, `OperationsPanel`, `OperationsInspector`, `detail-dirty.ts` | unit + browser dirty-leave | OTL6.1 | Durable draft store |
| **OTL6.3** | Ops session snapshot + URL clear including language (A2) | `operations-session.ts`, `operations-url.ts`, `App.tsx` | unit stash/peek/clear | OTL6.2 | localStorage / URL pollution strategy |
| **OTL6.4** | Status/terminology humanization | `OperationsPanel`, label utils | unit labels + browser smoke | OTL6.3 | List explain N+1 |
| **OTL6.5** | Review `translation_id` + Review→Ops + bulk→Jobs; document OP15 Partial | Review ViewModel, ReviewQueuePanel, bulk results | integration + browser | OTL6.4 | Jobs Store enrichment / schema |
| **OTL6.6** | A11y focus + responsive column priority | CSS, inspector open/close | browser a11y/responsive | OTL6.5 | Mobile-first redesign |
| **OTL6.7** | Create `acceptance/otl-browser/`; archive READMEs; fix otl3/otl4 configs | `acceptance/*` | local Playwright | OTL6.6 | Deleting legacy suites; forcing live CI |
| **OTL6.8** | PluginGuard + evidence + closure prep | tests, docs | PluginGuard + CI | OTL6.7 | Version/tag bump |

---

## 19. Test / regression / rollback

- Unit: dirty-leave guard registration; session stash/peek/clear; confirm helpers; labels.  
- Integration: Review queue `translation_id`; PluginGuard; A6 regression; **no** Jobs→Ops requirement.  
- Local Playwright (authoritative): lifecycle smoke including dirty-leave + context restore.  
- Archive packages: config fixes only; historical asserts preserved.  
- Rollback: UI/session-only; no migration to undo.

---

## 20. Limitations remaining after OTL.6

- Jobs→Ops reverse deep-link remains Partial (A3 / JI50)  
- Bulk retry-failed Deferred  
- Jobs-backed attention Deferred  
- Path-B QA duplication Deferred  
- Live Playwright local-only  
- Mobile-first Deferred  
- Selection/bulk-result not cross-tab persistent  
- No durable publish verification product  

---

## 21. STOP conditions

STOP / STATE B if implementation would require:

- schema / TARGET change  
- new ADR  
- second policy engine  
- frontend publication/eligibility authority  
- durable draft persistence  
- bulk retry revival  
- Integration API expansion/v2  
- TSC  
- site-specific / Biopentra behavior  
- list TI.7 explain N+1  
- unplanned Jobs Store enrichment  
- deletion of historical acceptance archives  
- version / tag change  

---

## 22. Exact next step after Architecture Frozen + planning closure

Run the combined **OTL.6 Final Operator Lifecycle Polish implementation** + independent implementation review + review-fix loop + merge + fresh main CI + OTL.6/program closure from the frozen main baseline.

Do **not** create `feature/otl6-*` in the planning freeze task.  
Do **not** start TSC.  
Do **not** bump version, change TARGET, create ADR, or tag/release under OTL.6 planning.
