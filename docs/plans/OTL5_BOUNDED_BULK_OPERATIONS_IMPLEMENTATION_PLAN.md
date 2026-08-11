# OTL.5 — Bounded Bulk Operations — Implementation Plan

**Status:** **Architecture Frozen** (planning freeze lifecycle — production implementation **not started**)
**Milestone:** OTL.5 — Bounded Bulk Operations (Operator Translation Lifecycle program)
**Kind:** Milestone implementation plan (authoritative on `main` after freeze merge)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** OTL parent **Architecture Frozen**; OTL.0–OTL.4 **Complete**; TIQ **Complete** (incl. TI.6 Jobs, TI.7 Publication); AI Multilingual **v1.2.0**; `Migrator::TARGET` **7**
**Schema:** Migrator `TARGET` = **7** (unchanged — **no migration**, **no new index**)
**ADR:** **No new ADR.** ADR-0015 / ADR-0020 / ADR-0011 / TI.6–TI.7 ownership unchanged.
**Planning baseline main HEAD:** `7a2aa2145f95b3cc44ea26a9c004f9296cf09fb6`
**Planning branch:** `docs/otl5-bounded-bulk-operations-planning-freeze`
**External freeze review:** **PASS** (STATE A — FREEZE; A1–A6 locked)
**Independent planning review:** **PASS**
**Reviewed planning HEAD:** `5673b418875047ed5007244d94843f4462798494`
**Validation:** [OTL5_BOUNDED_BULK_OPERATIONS_PLANNING_VALIDATION_LOG.md](OTL5_BOUNDED_BULK_OPERATIONS_PLANNING_VALIDATION_LOG.md)
**Implementation branch:** **Do not create** until this plan is frozen on `main` and the combined implementation task begins.
**Next after freeze/closure:** Run the combined **OTL.5 Bounded Bulk Operations implementation** + independent implementation review + merge + milestone closure from the frozen main baseline. Do **not** start OTL.6 or TSC under OTL.
**Related:** [OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md](OTL4_JOBS_INTEGRATION_IMPLEMENTATION_PLAN.md); [OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md](OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md); [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)

**External-review amendments locked:**

| ID | Topic | Lock |
|---|---|---|
| A1 | Jobs enqueue = async acceptance only (`enqueued`) | Frozen |
| A2 | No synthetic TI.7 publish eligibility on list/selection | Frozen |
| A3 | Result-aware post-bulk selection retention | Frozen |
| A4 | Two-level results for Jobs-backed enqueue (`items[]` + `operations[]`) | Frozen |
| A5 | Bulk retry-failed | **`BULK RETRY-FAILED: DEFERRED`** |
| A6 | Dirty-editor protection = intersection-based (`D ∈ S` block) | Frozen |

**Operational success:** From Operations, an operator can select a bounded set of translations (≤50), attempt bulk publish/unpublish with per-item TI.7 authority, and enqueue selected stale retranslation through TI.6 Jobs with honest `enqueued` semantics — without a second policy engine, without synthetic publishability labels, without job-scoped retry-from-selection, and without clobbering an unrelated dirty editor draft.

**Hard boundary:** OTL.5 does **not** ship Operations bulk retry-failed, sync Operations multi-retranslate, Operations bulk review, row-based pause/cancel/resume, per-translation retry, whole-language mutate, force publish, synthetic Eligible/Ready/Publishable labels, list TI.5/TI.7/Jobs N+1 enrichment, second queue, second publication/review/Jobs policy, Integration API expansion, schema/index change, new ADR, OTL.6 polish, or TSC.

---

## 1. Official objective

Provide **useful, safe, bounded multi-translation operations** on the Operations surface so an operator can:

find → select (bounded) → attempt publish/unpublish → enqueue retranslate → see honest partial results → remediate remaining failures

without weakening per-item TI.7 / ADR-0015 / TI.6 / OTL.0–OTL.4 guarantees.

Parent mapping: **OT18** bulk publish/unpublish → Supported (narrowed); **OT19** bulk stale retranslation → Supported as Jobs enqueue; **OT20** bulk failed retry → **Deferred**.

---

## 2. Parent / OTL dependencies

| Dependency | Role |
|---|---|
| OTL.0 | OperatorTranslationAssembler; allowed_actions; Operations REST; pagination |
| OTL.1 | Operations UI; attention; filters; OperationsInspector shell |
| OTL.2 | Unified detail; dirty honesty; review; concurrency hashes |
| OTL.3 | Manual publish/unpublish; interactive sync retranslate; PublicationService delegation; controlled_auto honesty |
| OTL.4 | Jobs linkage (detail); JobsOperationAdmission; job-scoped retry/resume on detail + Jobs tab |
| TI.6 | Job create (`translate_selected`), snapshots, ItemProcessor, budgets/concurrency |
| TI.7 | PublicationService / PublicationPolicy — sole publish eligibility authority |
| Store | Persisted translation state |
| ADR-0015 | Review ≠ publication |
| ADR-0020 | Publication axis |

**Ownership:** OTL orchestrates/read-models/UI only. No duplicate policy engines.

---

## 3. Repository reality audit

- Multi-select exists on **Translate** (`segmentKey`) and **Review** (`post:lang:segment`); **Operations has no multi-select** today (`inspectorId` only).
- Sync batch caps: `BatchOperationCoordinator::BATCH_LIMIT = 50`, `ReviewBatchCoordinator::BATCH_LIMIT = 50`.
- Operations list: `Store::OPERATIONS_MAX_PER_PAGE = 50` (default 20).
- Jobs: `JobBounds::MAX_SELECTED_SEGMENTS = 50`; `translate_selected` accepts explicit keys + `segment_snapshots`.
- PublicationService is single-item; safe to wrap; no bulk methods.
- List `publish` action is always `detail_only` (no TI.7 on list).
- Jobs ItemProcessor consumes `source_hash_captured` and `translation_hash_captured` at execution; does **not** pass OTL.3 `expected_translation_hash`.
- `retry_failed_items` re-queues **all** failed items on a Job (job-scoped) — OTL.4 detail/Jobs tab remain the honest surfaces.

---

## 4. Selection architecture

| Rule | Contract |
|---|---|
| Identity | `translation_id` |
| Persistence | Client `Set<number>` only (not URL/localStorage) |
| Max selection | **50** (client + server fail-closed) |
| Select-all | **Current page** selectable rows only |
| Cross-page | Accumulation allowed up to 50 |
| Clear on | Language change, attention change, list filter change |
| Page navigation | Does **not** clear selection |
| Explicit Clear | Always available |
| Whole-language select-all mutate | **Unsupported** |

Helpers may mirror Translate/Review selection utilities with Operations identity — do not invent an unrelated second selection product.

### A3 — Result-aware selection (frozen)

After a completed bulk request:

**Remove** from selection items whose requested operation reached a terminal non-remediation outcome:

- successful mutation (`published` / `unpublished` / equivalent)
- `noop`
- `enqueued`
- equivalent terminal non-remediation outcomes

**Retain** selected items needing attention:

- `blocked`
- `conflict`
- `unauthorized`
- `failed`
- equivalent actionable failures

Rules:

- Do not clear everything on partial success.
- Do not retain completed siblings merely because another item failed.
- Do not automatically retry remaining selection.
- Result panel remains visible after refresh.
- Fully successful/noop/`enqueued` batches naturally empty selection.

---

## 5. Dirty intersection (A6)

Definitions:

- `D` = `translation_id` currently open in a dirty Operations editor
- `S` = set of selected `translation_id`s for the bulk request

| Case | Behavior |
|---|---|
| `D ∈ S` | **Block** bulk until operator Saves D, Discards D, or Removes D from selection. Clear copy that the selected translation has unsaved changes. Visible exclude-D + confirm reduced set is allowed only if non-silent and result shows D not processed. |
| `D ∉ S` | **Allow** publish/unpublish/enqueue on S. Preserve dirty inspector; do not overwrite target; do not auto re-fetch/replace D; do not clear dirty; keep OTL.2 last-persisted evidence honesty for D. |

Dirty restriction is **OTL UI/session safety only** — not server review/publication/Jobs policy.

**Post-operation refresh:**

- Refresh Operations list as needed.
- If inspector is **clean** and its translation was affected → authoritative detail refresh may occur.
- If inspector is **dirty** → never replace automatically.
- Selection cleanup follows A3; result panel remains visible.
- Bulk completion must never treat list refresh as permission to replace D’s local draft.
- Later save of D uses OTL.2 concurrency guards; no automatic draft merge.

**Indirect mutation audit (Supported actions):**

| Action | Affects only S? | `D ∉ S` safe? |
|---|---|---|
| Publish | Yes | Yes |
| Unpublish | Yes | Yes |
| Enqueue retranslate | Yes — explicit selected keys only; grouping must not expand set | Yes |
| Retry-failed | Job-wide (unsafe for intersection alone) | N/A — **Deferred** |

---

## 6. Publish attemptability vs TI.7 eligibility (A2)

**`can_attempt_publish` (invitation only):**

- Toolbar may expose “Publish selected” when the operator has broad capability/context to *attempt* publication on a non-empty bounded selection.
- This is **not** TI.7 eligibility.

**Forbidden** cheap-list labels: “Eligible”, “Ready to publish”, “Publishable”.

**Confirm copy (equivalent):** “Selected translations will be evaluated individually for publication.”

**Execution:** every item → `PublicationService::publish` / TI.7 re-evaluates authoritative state (stale, rejected, source visibility, assessment blockers, evidence, provenance, publish state, other P1.0 guards).

Invariants:

- Zero list-time `PublicationService::explain` N+1
- No JavaScript publication policy
- No synthetic eligibility intersections
- Blocked items return authoritative TI.7 reason codes/outcomes
- No force publish

---

## 7. Bulk publish architecture

- Orchestrator resolves each `translation_id` → Store row → per-item capability/`edit_post` → `PublicationService::publish` (or Workspace facade).
- Optional `expected_publish_status` when known (conflict on mismatch).
- Outcomes: `published` | `noop` | `skipped`/`blocked` | `conflict` | `unauthorized` | `failed` (+ reason codes).
- Continue-on-error; aggregate `completed` | `partial` | `failed`.
- Existing publication audit hooks fire per actual mutation; **no new audit store**.

---

## 8. Bulk unpublish architecture

- Same orchestration pattern → `PublicationService::unpublish`.
- Idempotent noop if already unpublished.
- **Must not** mutate `review_status` or review workflow fields.
- Review ⊥ publication remains absolute.

---

## 9. Jobs enqueue retranslate architecture (A1 + A4)

**Supported path:** group admitted selected translations by `(source_type, source_id, language_id)` and create TI.6 jobs with:

- `job_type = translate_selected`
- **explicit** `segment_keys` from selection only
- `segment_snapshots` with `source_hash_captured` + `translation_hash_captured`

**Unsupported:** synchronous Operations multi-retranslate; whole-object `retranslate_stale` expansion beyond selection.

### Snapshot execution model (repository truth)

| Snapshot | TI.6 consumption |
|---|---|
| `source_hash_captured` | `BackgroundTranslationItemProcessor::process` — drift → existing stale outcome |
| `translation_hash_captured` | `evaluate_conflict` — drift → existing conflict outcome |
| OTL.3 `expected_translation_hash` | **Not used** by Jobs (`translate_segment` called without interactive hash) |

Snapshots are **execution-time evidence**, not a guarantee that execution will still be admissible later. Conditions may change after enqueue; ItemProcessor + TranslationService remain authoritative.

### `enqueued` semantics (frozen)

Accepted enqueue means only: **accepted for asynchronous processing**.

It does **not** mean: translation completed; target replaced; provider succeeded; review invalidated; publication changed/succeeded.

UI must never present an enqueued item as already translated. OTL must not predict publication outcome.

### controlled_auto boundary

`controlled_auto` / `approved_only` may run only after actual successful persist via existing `TranslationService` → `maybe_auto_publish`. Confirm discloses possible later auto-publish; does not assume unpublished.

### Two-level enqueue results (A4)

```text
{
  status: "completed|partial|failed",
  items: [
    { translation_id, outcome, code, job_id?, message? }
  ],
  operations: [
    { operation_key, action, job_id?, outcome, code, affected_items?, message? }
  ],
  summary: { ... }
}
```

- `items[]` = selected translation resolution/result (`enqueued` | blocked | conflict | unauthorized | failed | …)
- `operations[]` = actual unique Jobs **create** operations
- N translations → M Jobs ⇒ N item records, **M** operation records
- Do not imply N Jobs were created

Publish/unpublish may omit `operations[]` (item-level results only).

---

## 10. Partial-success semantics

- `items.length > 50` → **422 `aiml_batch_too_large`** (fail closed; **no truncation**)
- Within limit → explicit per-item execution; continue-on-error
- Noop ≠ failed
- One failure must not rewrite successful siblings as failed
- **No distributed transaction** claim

---

## 11. Bulk retry-failed — Deferred (A5)

**Verdict: `BULK RETRY-FAILED: DEFERRED`**

Rationale:

1. OTL.4 already provides honest job-scoped retry from unified detail and the Jobs tab.
2. Translation selection → job-wide `retry_failed_items` can affect failed items **not** in the selection.
3. Dirty `D ∉ S` sharing a Job with selected `X` can mutate D behind an unsaved draft.
4. Safe overlap detection is bounded but turns Operations into an indirect multi-Job console without enough incremental value for OTL.5.

**Do not** implement Operations bulk retry-failed, per-translation retry, or item-retry API in OTL.5.

**Regression:** OTL.4 detail + Jobs tab retry remain intact.

---

## 12. REST / orchestration shape

- `POST /aiml/v1/workspace/operations/bulk`
- Actions for OTL.5 Supported set: `publish` | `unpublish` | `enqueue_retranslate`
- Admin Workspace scope; capability protected; per-item object access revalidated
- Thin `OperationsBulkCoordinator` (name flexible) — **not** a second PublicationPolicy / JobsOperationAdmission / review policy
- No Integration API expansion
- No new durable queue

---

## 13. Security / privacy

- Capability + object-level checks per item
- Bulk responses: ids, codes, short messages — **no** source/target bodies, prompts, provider secrets, raw provider failure bodies
- Safe reason/result codes
- PluginGuard neutrality (no Biopentra/site-specific production behavior)

---

## 14. Performance / boundedness

| Bound | Value |
|---|---|
| Max selection / request | 50 |
| Select-all scope | Current page |
| List enrichment | No TI.5 / TI.7 / Jobs N+1 for bulk chrome |
| Publish mutation | ≤50 PublicationService calls |
| Enqueue provider work | Via Jobs wakes — not inline admin HTTP provider loop |
| Fail closed over 50 | 422 |

---

## 15. Accessibility / responsive / Playwright

- Toolbar keyboard operable; SR announcements for selection/results
- Usable on common WP admin laptop widths
- Local `acceptance/otl5-browser/` smoke (select, mixed results, publish/unpublish, enqueue honesty, A3 retention, A6 dirty ∈/∉ S, toolbar) — **non-CI** unless later evidence changes

---

## 16. Neutrality

Hard invariant: no production Biopentra / customer-specific terminology, URLs, taxonomy rules, or branding. Fixtures generic. Extend PluginGuard if new strings land.

---

## 17. BO1–BO31 requirements matrix

| ID | Requirement | Disposition | Owner | Implementation consequence | Validation/evidence |
|---|---|---|---|---|---|
| BO1 | Operations multi-select by `translation_id` | Supported | OTL UI | Selection module | JS unit + browser |
| BO2 | Max selection 50; over-select fail-closed | Supported | OTL + REST | Client + 422 | Integration |
| BO3 | Select-all = current page only | Supported | OTL UI | No dataset-wide select | Unit + browser |
| BO4 | Cross-page accumulate ≤50 | Supported | OTL UI | Review-like | Unit |
| BO5 | Clear on language/attention/filter | Supported | OTL UI | Reset rules | Unit + browser |
| BO6 | Zero list TI.5/TI.7/Jobs enrichment for bulk chrome | Supported | OTL | Keep list cheap | PluginGuard / scale |
| BO7 | Bulk publish via PublicationService | Supported | TI.7 + orchestrator | Per-item revalidation | Integration |
| BO8 | Bulk unpublish via PublicationService | Supported | TI.7 + orchestrator | No review mutation | Integration |
| BO9 | No force publish | Supported | TI.7 | STOP if added | PluginGuard |
| BO10 | Attemptability ≠ eligibility (`can_attempt_publish` invitation only) | Supported | OTL | A2 wording | Unit + browser |
| BO11 | Zero list-time PublicationService::explain N+1 | Supported | OTL | Perf invariant | Architecture / scale |
| BO12 | Jobs enqueue retranslate from selection | Supported | TI.6 + OTL | `translate_selected` + snapshots | Integration |
| BO13 | Enqueue outcome = `enqueued` not translation success | Supported | OTL | A1 contract | Integration + UI |
| BO14 | Snapshots consumed later by TI.6 | Supported | TI.6 | Existing ItemProcessor | Integration / docs |
| BO15 | Selected-key-only workload (no set expansion) | Supported | OTL + TI.6 | Dirty/indirect safety | Integration |
| BO16 | Sync Operations multi-retranslate | Unsupported | — | Detail sync only | Architecture test |
| BO17 | Bulk retry-failed from Operations | **Deferred** | TI.6 / OTL.4 UI | Use detail/Jobs tab | Plan debt |
| BO18 | No per-translation retry | Supported | TI.6 | STOP if added | PluginGuard |
| BO19 | Two-level enqueue results (`items` + `operations`) | Supported | OTL | A4 honesty | Integration + UI |
| BO20 | Partial outcomes; noop ≠ failed | Supported | OTL coordinator | Result schema | Integration |
| BO21 | Result-aware selection retention (A3) | Supported | OTL UI | Keep actionable failures | Unit + browser |
| BO22 | Dirty intersection: `D ∈ S` blocks; `D ∉ S` allows (A6) | Supported | OTL UI | Session safety only | Unit + browser |
| BO23 | Dirty draft not clobbered by list refresh | Supported | OTL UI | Race-safe | Browser |
| BO24 | Review ≠ publication | Supported | ADR-0015/0020 | No coupling | Integration |
| BO25 | controlled_auto honesty; no predicted publish | Supported | OTL UI + TI.7 | Disclosure | Browser |
| BO26 | Caps/object access per item | Supported | REST/services | blocked codes | Integration |
| BO27 | No bodies/secrets in bulk responses | Supported | REST | Privacy | Integration + Guard |
| BO28 | Neutrality | Supported | OTL | Generic copy | PluginGuard |
| BO29 | A11y + responsive toolbar | Supported | OTL UI | Keyboard/SR/laptop | Browser |
| BO30 | No schema / TARGET / ADR / new queue | Supported | Program | STATE A | Migrator + Guard |
| BO31 | OTL.0–OTL.4 + TIQ regression | Supported | CI | Suites green | CI |

---

## 18. Work-package ladder OTL5.0–OTL5.8

### OTL5.0 — Baseline + characterization
Characterize existing batch coordinators, PublicationService outcomes, Jobs create + snapshot consumption, Operations selection absence. Confirm bulk retry remains out of scope. **STOP** if baseline drift from frozen main.

### OTL5.1 — Bounded selection + result-aware helpers
`translation_id` selection; cap 50; clear rules; page select-all; A3 selection reducer. Checkboxes without mutations yet.

### OTL5.2 — Server bulk orchestration + action-aware result model
Coordinator + `POST .../operations/bulk`; limit enforcement; publish/unpublish dispatch; item-level outcome schema. Tests: auth, 422, mixed outcomes, no review coupling, no force publish.

### OTL5.3 — Publication UX + attemptability/eligibility honesty
Toolbar publish/unpublish; A2 confirm wording; forbid Eligible/Ready/Publishable; partial result panel; list refresh; A3 cleanup.

### OTL5.4 — Jobs enqueue retranslate (A1 + A4)
`enqueue_retranslate` → Jobs `translate_selected` + snapshots; two-level results; `enqueued` semantics; selection-only payload; controlled_auto disclosure; **NO retry-failed implementation**. **STOP** if sync provider loops or set expansion beyond selection.

### OTL5.5 — Dirty intersection + bulk UX
A6 `D ∈ S` block / `D ∉ S` allow; preserve dirty draft; a11y/responsive toolbar; invitation ≠ eligibility messaging.

### OTL5.6 — Partial-result / refresh / race hardening
Result panel persistence; clean-vs-dirty detail refresh; A3 cleanup; bulk completion must not replace dirty D.

### OTL5.7 — Performance / security / PluginGuard
Architecture forbids: second policies, per-translation retry, bulk retry engine, force publish, synthetic eligibility, explain N+1, Integration API, site strings, schema/TARGET drift. Scale fixture: cannot exceed 50.

### OTL5.8 — Validation evidence / closure preparation
Evidence pack after implementation (separate lifecycle). No version bump/tag in OTL.5.

---

## 19. Acceptance criteria AC1–AC74

### Selection & bounds
**AC1** Selection identity is `translation_id` only.  
**AC2** Selection cannot exceed 50 client-side.  
**AC3** Server rejects `items.length > 50` with 422 `aiml_batch_too_large`.  
**AC4** Select-all affects current page only.  
**AC5** Cross-page selection accumulates until cap.  
**AC6** Language change clears selection.  
**AC7** Attention change clears selection.  
**AC8** Filter change clears selection.  
**AC9** Page change alone does not clear selection.  
**AC10** No “select all matching in language” control exists.  
**AC11** Explicit Clear selection control always available.

### Auth & attemptability
**AC12** Bulk REST requires authenticated admin Workspace capability path.  
**AC13** Per-item missing caps/`edit_post` → blocked/unauthorized; siblings continue.  
**AC14** “Publish selected” availability is invitation-only (`can_attempt_publish`); not TI.7 eligibility.  
**AC15** UI does not label rows Eligible/Ready to publish/Publishable from cheap Store state.  
**AC16** Publish confirm states translations will be evaluated individually.

### Publication
**AC17** Bulk publish calls PublicationService per item (no force).  
**AC18** Ineligible publish → skipped/blocked with TI.7 reason codes.  
**AC19** Already published → noop.  
**AC20** Bulk unpublish does not change `review_status`.  
**AC21** Stale/source-not-public/rejected/assessment blockers still prevent publish.  
**AC22** Optional `expected_publish_status` mismatch → per-item conflict.  
**AC23** Zero Operations list PublicationService::explain N+1 for bulk chrome.  
**AC24** No JS publication policy engine.

### Retranslate enqueue (A1/A4)
**AC25** Retranslate bulk creates TI.6 jobs (not sync provider loop).  
**AC26** Enqueue uses explicit selected keys + hash snapshots only.  
**AC27** Accepted item outcome is `enqueued` (or equivalent), not translation-complete success.  
**AC28** UI never presents enqueued items as already translated.  
**AC29** OTL does not predict publication outcome after enqueue.  
**AC30** Confirm discloses async processing + possible later controlled_auto/approved_only auto-publish after persist.  
**AC31** Non-admitted/non-stale (as planned) items → blocked/skipped at enqueue validation.  
**AC32** Response includes `operations[]` for unique job creates; N→1 mapping does not imply N creates.  
**AC33** Snapshots consumed later by TI.6; post-enqueue drift uses existing Jobs skip/fail semantics.

### Retry (A5)
**AC34** OTL.5 does not expose Operations bulk retry-failed.  
**AC35** Job-scoped retry remains available via OTL.4 detail and Jobs tab (regression).  
**AC36** No per-translation retry endpoint/engine.

### Partial results & selection (A3)
**AC37** Partial success reports per-item codes; aggregate `partial`.  
**AC38** Noop distinguished from failed.  
**AC39** Conflicts represented per item without aborting siblings.  
**AC40** After bulk, terminal success/noop/`enqueued` items are removed from selection.  
**AC41** After bulk, blocked/conflict/unauthorized/failed items remain selected.  
**AC42** Completed items are not kept selected merely because siblings failed.  
**AC43** No automatic retry of remaining selection.  
**AC44** Result panel remains visible after refresh.

### Dirty editor (A6)
**AC45** Dirty `D ∈ S` blocks bulk until save, discard, or remove-from-selection.  
**AC46** Removing D from selection allows bulk on remaining S.  
**AC47** Saving D allows bulk including D.  
**AC48** Discarding D allows bulk including D.  
**AC49** Dirty `D ∉ S` does not block unrelated publish.  
**AC50** Dirty `D ∉ S` does not block unrelated unpublish.  
**AC51** Dirty `D ∉ S` does not block unrelated enqueue retranslate.  
**AC52** Operations list refresh does not clear D’s draft.  
**AC53** Result-aware selection cleanup does not alter D’s dirty state.  
**AC54** Later save of D still uses OTL.2 concurrency guards.  
**AC55** If inspector dirty, bulk completion does not auto-replace detail.  
**AC56** If inspector clean and affected, detail refresh may occur.

### Review independence / neutrality / privacy
**AC57** Approve/reject not exposed as Operations bulk actions.  
**AC58** Bulk responses omit source/target bodies and provider secrets.  
**AC59** No Biopentra/site-specific production strings.

### Program invariants
**AC60** No schema migration; TARGET remains 7.  
**AC61** Version remains 1.2.0; no release tag from OTL.5.  
**AC62** No Integration API expansion.  
**AC63** No new durable queue.  
**AC64** No second PublicationPolicy / JobsOperationAdmission / review policy.

### UX / perf / CI
**AC65** Toolbar keyboard operable; status announced to SR.  
**AC66** Toolbar usable on common WP admin laptop widths.  
**AC67** Representative 20-row page: checkbox + toolbar without list N+1 policy calls.  
**AC68** Fixture proving mutation cannot exceed cap.  
**AC69** Existing publication audit hooks still fire for actual publish/unpublish mutations; no required new audit store.  
**AC70** OTL.0–OTL.4 regression suites green.  
**AC71** TI.6/TI.7/review integration tests green.  
**AC72** PluginGuard green including new architecture forbids.  
**AC73** PHPCS / unit / integration / quality baseline / build ZIP green per repo CI.  
**AC74** Local `acceptance/otl5-browser` covers: select; mixed result; publish/unpublish; enqueue `enqueued` honesty; A3 selection retention; A6 dirty ∈/∉ S; keyboard/toolbar (non-CI).

**AC count: 74** (contiguous AC1–AC74; no duplicates; no missing numbers).

---

## 20. Test strategy

- **Unit:** selection helpers; A3 reducer; A6 dirty intersection; enqueue outcome mapping; operations[] dedupe; attemptability vs eligibility helpers.
- **Integration:** REST auth; 422; mixed publish/unpublish; no explain N+1; enqueue + `enqueued` + two-level results; snapshot wiring; non-expansion; no bulk retry route; privacy; unpublish leaves review unchanged.
- **PluginGuard / architecture:** forbid second policies, per-translation retry, bulk retry engine, force publish, synthetic eligibility, Integration API, site strings, schema/TARGET drift.
- **Browser (local non-CI):** A3/A6 + publish/unpublish + enqueue honesty.
- **Regression:** OTL.0–OTL.4, TI.6/TI.7, review, OTL.4 retry-from-detail/Jobs tab.

---

## 21. Schema / TARGET / ADR / release verdict

| Item | Verdict |
|---|---|
| Schema / migration / index | **NO** |
| `Migrator::TARGET` | **remains 7** |
| Plugin version | **remains 1.2.0** |
| New ADR | **NO** |
| Tag / release | **NO** (existing `v1.2.0` unchanged) |
| STATE | **A — FREEZE** |

---

## 22. STOP conditions

**STOP** implementation if it would require:

- schema / TARGET change not approved by this plan
- new queue / job engine
- duplicate TI.6 admission policy or TI.7 publication policy
- force-publish path
- per-translation retry semantics
- Operations bulk retry-failed (Deferred — do not pull forward casually)
- unbounded “all matching rows” execution
- weakened translation_hash / snapshot concurrency
- hidden review→publication coupling
- client-side eligibility becoming TI.7 authority
- list-time PublicationService::explain N+1
- synthetic Eligible/Ready/Publishable labels from cheap Store state
- sync Operations multi-retranslate provider loop
- enqueue payload expanding beyond selected keys
- presenting `enqueued` as translation success
- dirty draft clobber on list/bulk refresh
- site-specific production behavior
- TSC work / OTL.6 work
- Integration API expansion
- substantial parent OTL architecture change

---

## 23. Exact implementation next step

After this plan is Architecture Frozen on `main` and planning closure is recorded:

Run the combined **OTL.5 Bounded Bulk Operations implementation** + independent implementation review + merge + milestone closure task from the frozen main baseline.

Do **not** create `feature/otl5-*` in the planning freeze task.  
Do **not** start OTL.6 or TSC.  
Do **not** bump version, change TARGET, create ADR, or tag/release under OTL.5.
