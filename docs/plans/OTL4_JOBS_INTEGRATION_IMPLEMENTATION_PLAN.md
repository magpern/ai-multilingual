# OTL.4 — Jobs Integration — Implementation Plan

**Status:** **Architecture Frozen** (planning freeze; production implementation **not** started)
**Milestone:** OTL.4 — Jobs Integration (Operator Translation Lifecycle program)
**Kind:** Milestone implementation plan (authoritative on `main` after freeze merge)
**Parent:** [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md)
**Prerequisites:** OTL parent **Architecture Frozen**; OTL.0–OTL.3 **Complete**; TIQ **Complete** (incl. TI.6 Jobs); AI Multilingual **v1.2.0**; `Migrator::TARGET` **7**
**Schema:** Migrator `TARGET` = **7** (unchanged — **no migration**, **no new index**)
**ADR:** **No new ADR.** ADR-0011 / TI.6 Jobs ownership unchanged. ADR-0015 / ADR-0019 / ADR-0020 unchanged.
**Planning baseline main HEAD:** `61ebfc4d4ca47dae9424a71a518000b923edef03`
**Planning branch:** `docs/otl4-jobs-integration-planning-freeze`
**Freeze recommendation:** **STATE A — FREEZE**
**Independent review (planning):** **PASS** (adversarial review on planning branch; ordinary fix: correct ADR-0011 related link)
**Implementation branch:** **Do not create until this plan is frozen on `main`.**
**Related:** [OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md](OTL3_PUBLICATION_STALE_WORKFLOW_IMPLEMENTATION_PLAN.md); [TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md](TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md); [ADR-0011](../adr/0011-resumable-job-pipeline.md); [TI6_JOBS_SCALE_SAFETY_POLISH_VALIDATION_LOG.md](TI6_JOBS_SCALE_SAFETY_POLISH_VALIDATION_LOG.md)

**External-review amendments locked:** A1 semantic linkage ≠ `active_lock_key`; A2 no serialized `selection_rule`; A3 TI.6 owns operation admission (`JobsOperationAdmission`); A4 Outcome B **Partial** (not from `attempt_count`); A5 bounded-lookup-honest `association=null`.

**Operational success:** From Operations detail, an operator can see associated TI.6 Jobs work for a translation (when found within bounded lookup), understand failure/retry_wait state, open the Jobs tab, and — when TI.6 admission allows — delegate **job-scoped** resume / retry-failed with multi-item disclosure, then refresh authoritative detail without leaving the translation lifecycle and without a second Jobs engine.

**Hard boundary:** OTL.4 does **not** ship OTL.5 bulk failed retry, OTL.6 polish, TSC, Integration API v2, schema/index change, new ADR, attempt ledger, Jobs-backed attention, list Jobs enrichment, item-scoped retry, client-side retryability authority, Biopentra-specific behavior, durable full Jobs history, or a second Jobs console inside Operations.

**Exact next step after freeze:** Run the combined OTL.4 **implementation** + independent implementation review + merge + milestone closure task from the frozen main baseline. Do **not** implement until freeze is on `main`.

---

## 1. Official objective

Make existing TI.6 Jobs state and legitimate Jobs operations coherent inside the Operator Translation Lifecycle so an operator can:

find translation → inspect translation → understand associated background work → understand failure/retry state → take legitimate **delegated** Jobs actions → return to the translation lifecycle

without creating a second Jobs engine, queue, retry policy, concurrency policy, budget policy, duplicated assessment/publication logic, client-side Jobs policy, or generic observability platform.

Parent mapping: **OT11** Jobs linkage, **OT12** Failure detail — Supported → OTL.4.

---

## 2. OTL / TI.6 ownership

| Owner | Owns |
|---|---|
| **TI.6 Jobs** | job/item state; queueing; retry taxonomy; AS scheduling; concurrency; leases; budgets; progress; terminalization; resume / retry-failed **execution**; **operation admission** (`JobsOperationAdmission`); diagnostics |
| **OTL** | lifecycle read aggregation; presentation; navigation; mapping Jobs admission → `allowed_actions`; delegation to existing Jobs REST |
| **Store / TI.4 / TI.5 / TI.7 / Review** | unchanged |

OTL must **not** decide: whether an item is retryable; concurrency admission; backoff; budget availability; whether a job is terminal; publication eligibility; or recreate TI.6 transition/retry rules.

---

## 3. Jobs architecture audit (repository truth)

TI.6 ships a complete ADR-0011 pipeline under `src/Jobs/`:

- Tables: `aiml_jobs`, `aiml_job_items` (schema step 6; TARGET still **7**).
- Job identity: `job_id`; object scope `(source_type, source_id, language_id)`.
- Item identity: `(job_id, segment_key)` UNIQUE; retries **reuse** the same item row (`attempt_count++`).
- Active exclusivity: `active_lock_key` (at most one active job per object+lang).
- Worker → ItemProcessor → `TranslationService::translate_segment`.
- AS owns wakes (`aiml_run_job`, delayed Retry-After/backoff, hourly sweep).
- REST/CLI: create, list, detail, pause, resume, cancel, retry-failed, run, diagnostics, health.
- Admin UI: first-class Workspace **Jobs** tab.
- Assessment: `GET /jobs/{id}/items/{item_id}/assessment` — read-only TI.5; not Jobs success authority.

**OTL today:** `OperatorTranslationAssembler` sets `jobs => null`; no Jobs `allowed_actions`; no Operations↔Jobs deep links; attention `translation_failed` = Store `status=failed` only.

---

## 4. Semantic linkage identity

**Canonical semantic identity:**

```text
source_type + source_id + language_id + segment_key
```

Store also has `translation_id`. Jobs tables have **no** `translation_id` column.

### `active_lock_key` boundary

`active_lock_key` / `find_active_by_lock_key` is TI.6 **concurrency/exclusivity infrastructure only**.

It **may** be used internally as a lookup optimization when the active job also contains the segment item.

It **MUST NOT** become:

- semantic OTL identity;
- public ViewModel / API identity;
- serialized linkage key.

---

## 5. Multiplicity / retention / indexes

### Multiplicity

| Question | Reality |
|---|---|
| One translation → multiple historical job items? | **Yes** across jobs (same `segment_key` in different jobs) |
| Within one job? | **No** — UNIQUE `(job_id, segment_key)` |
| Retries? | **Reuse** same item row |
| Multiple jobs same object+lang? | One **active**; many **historical** until retention |
| Completed items queryable? | While parent job retained |

### Retention (TI.6)

`BackgroundTranslationRetentionCleanup`:

- completed / completed_with_errors: **30 days**
- failed / cancelled: **90 days**
- Deletes job **and** items; does not touch Store

OTL must **not** present linkage as durable/full Jobs history.

### Indexes / query (no schema change)

| Index | Use |
|---|---|
| `aiml_jobs.KEY object_lang (source_type, source_id, language_id)` | Bounded recent jobs for object+lang |
| `aiml_job_items.UNIQUE job_segment (job_id, segment_key)` | Item within known job |
| `aiml_jobs.UNIQUE active_lock_key` | Optional active-job optimization only |

**STOP** if basic linkage requires a new column/index or TARGET bump.

---

## 6. Bounded detail-only linkage

### Scope

- **Detail only** Jobs enrichment.
- Operations **list**: `jobs: null`; **zero** Jobs queries; **no** N+1.

### Constant

```text
JobsLifecycleLinker::LOOKUP_JOB_SCAN_LIMIT = 32
```

(or repository-equivalent name). Detail-only; must be validated in OTL4.1 tests. Raising under the same architecture (still bounded, detail-only, no schema) does not require STATE B; public `lookup.job_scan_limit` keeps clients honest.

### Internal algorithm (not serialized)

1. Load up to `LOOKUP_JOB_SCAN_LIMIT` most recent jobs for `(source_type, source_id, language_id)` via `object_lang` (`ORDER BY job_id DESC`).
2. Among those, select the **newest** job that has an item with this `segment_key`.
3. Optional optimization: if active lock job contains the segment item, prefer it when it matches — never treat lock absence as “no association.”
4. No match within window → `association = null` + lookup metadata.
5. Match → job + item summaries + `failed_items_in_job`.

### Required tests

- match within bounded window;
- no match;
- matching retained item **outside** bounded window;
- exhausted lookup semantics (`lookup.exhausted`).

### Bounded miss semantics

`association === null` means:

> no matching Jobs association found within the bounded OTL lookup

**NOT:**

> no retained Jobs record exists

Public UI/API copy must preserve this honesty. When the scan window ends without a match, set `lookup.exhausted = true`.

---

## 7. Public Jobs ViewModel

### List

```text
jobs: null
```

### Detail (when operator may view Jobs; else `jobs: null` fail-closed)

```text
jobs: {
  association: null | {
    job: { job_id, status, job_type, source_type, source_id, language_id,
           progress counters, last_error_*, budget_* },
    item: { item_id, segment_key, status, attempt_count, result_code, last_error_* },
    failed_items_in_job: number,
    mutation_scope: "job"
  },
  lookup: {
    bounded: true,
    job_scan_limit: number,
    matched: bool,
    exhausted: bool
  },
  retention: { applies: true },
  presentation: {
    failure: {...}|null,
    usage: {...}|null,
    exactly_once_help: {...}|null
  },
  navigation: { jobs_tab: true, job_id?: number, item_id?: number }
}
```

### Forbidden in public contract

- `selection_rule` (or any serialized retrieval-algorithm name)
- `active_lock_key` as linkage identity
- claims that `association=null` means no retained Jobs row exists anywhere
- unstable repository implementation choices as stable client contracts

### Null / empty

| Shape | Meaning |
|---|---|
| Detail `jobs === null` | Jobs section omitted (e.g. no view capability) |
| `association === null` | Bounded-lookup miss for this domain identity |
| `lookup.exhausted === true` | Scan reached limit without segment match |
| `lookup.matched === true` | Association present within bound |

---

## 8. Jobs tab coexistence and deep links

- Existing Jobs tab remains **first-class**.
- OTL **summarizes + deep-links**; does **not** embed a second Jobs console.
- Additive URL sync: `view=jobs&job_id={id}` (optional `item_id`) focuses/expands that job.
- From Operations detail: “Open in Jobs”.
- Reverse Jobs→Operations link: **Partial** (JI50) — only if cheap; defer if N+1/non-post ambiguity.

---

## 9. TI.6 operation admission extraction

### Repository audit

| Surface | Today |
|---|---|
| Resume | `JobTransitionPolicy::validate_resume` (paused only) via `JobService::resume` |
| Retry-failed | Eligibility **inline** in `JobService::retry_failed_items` (terminal `failed`/`completed_with_errors` **or** non-terminal with failed items; empty failed = no-op success) |
| Concurrency | `BackgroundTranslationConcurrencyPolicy::admit_running` at **execution** (REST/CLI) |
| Jobs UI | Client-side duplicate in `assets/translator-workspace/src/utils/jobs.ts` (`canResumeJob`, `canRetryFailedJob`, …) |

### Frozen architecture

Introduce reusable TI.6 **`JobsOperationAdmission`** (name flexible) that, for a loaded job (+ caps), returns for each operation (`resume`, `retry-failed`, and preferably `pause`/`cancel`/`run` for Jobs UI parity):

- `operation_id`
- `allowed: bool`
- `reason_code: string|null`
- `mutation_scope: "job"`

Rules from existing `JobTransitionPolicy` + extracted retry-failed eligibility. **Concurrency remains execution-time only** (do not bake free-slot prediction into UI admission).

**Consumers:**

1. OTL `AllowedActionsResolver` **maps** Jobs admission → `resume_job` / `retry_failed_job` (no independent paused⇒resume / failed⇒retry rules in OTL).
2. Jobs UI **migrates** off client predicates onto the same server contract (e.g. job detail embeds `operations[]`) — **in scope** (JI51).

**Mutation endpoints MUST still revalidate** (JobService + concurrency admit).

**STOP** if extraction requires substantial TI.6 redesign rather than a narrow refactor — do **not** silently duplicate rules in OTL.

---

## 10. Allowed actions and job-scoped mutations

| Action id | Scope | Admission |
|---|---|---|
| `open_job` / `open_jobs` | navigation | OTL (caps + association/navigation) |
| `resume_job` | **job** | TI.6 `JobsOperationAdmission` → map |
| `retry_failed_job` | **job** | TI.6 `JobsOperationAdmission` → map |

### Resume disclosure

Affects the **entire** paused job.

### Retry-failed disclosure

Resets **all eligible failed items** in that job; does **not** retry only the currently viewed translation. Show `failed_items_in_job` when available.

### Unsupported

- Item-scoped retry API
- Client-side retryability as authority (JI33)

### Automatic retry / `retry_wait`

Show status honestly. Do **not** offer generic manual “Retry” when status is `retry_wait`. Wake timing: **Partial** — no false clock if AS delay is not authoritative in the payload.

### Concurrency / budget

- Concurrency rejection = execution failure message; no client capacity prediction.
- Budget display from job counters: **Partial** (JI20).

---

## 11. Honesty surfaces

### Provider usage

- Job-level Partial; unknown ≠ zero; no monetary cost; no per-item token fields without schema (**Unsupported**).

### TM known-zero

**Partial** — only when authoritative evidence exists without new persistence; otherwise omit.

### Outcome B — Partial (JI25)

- Never infer crash-after-Store Outcome B **occurred** from `attempt_count > 1` alone (JI55).
- Site diagnostic `crash_recovery_provider_repeat_risk` is **not** per-association proof.
- Optional contextual help: TI.6 does not claim exactly-once; provider call **MAY** repeat on some retry/recovery paths.
- Never claim it **DID** occur without authoritative evidence.
- No attempt ledger; no schema.

### Failure presentation

Server-side adapter from stored `last_error_*` / `result_code` into bounded operator labels (provider/transport, rate-limit/retry_wait, terminal, TI.1 safety, stale_source, skipped_conflict, concurrency, budget, unknown fail-closed).

**Never expose:** prompts, credentials, raw provider bodies, secrets.

### Attention

Jobs-backed attention **Deferred** (JI27). OTL.1 `translation_failed` remains Store-based (JI28). No list Jobs enrichment; no attention N+1; no new index for attention.

---

## 12. REST / ViewModel / mutations / races / cross-object

- Additive admin `aiml/v1` Operations **detail** only; list stays `jobs: null`.
- No Integration API v2.
- Browser calls existing Jobs REST for resume / retry-failed (default; no OTL mutation duplicate).
- After mutate: do not predict state in JS; refresh Jobs subtree; **preserve dirty editor draft** (OTL.2); server errors as-is.
- Non-post Jobs mutation parity **Unsupported** (JI37); do not invent TSC coverage. Honest empty/unsupported association.

---

## 13. Permissions / privacy / neutrality / a11y / Playwright

- Existing Jobs + translate/review capabilities; object edit scope on Jobs mutations unchanged.
- Bounded error evidence; no secrets/prompts/raw bodies; SaaS-neutral; no Biopentra production assumptions.
- A11y: text status (not color-only); keyboard; multi-item confirmations; aria-live; responsive Operations inspector reuse; no modal traps.
- **Playwright:** local `acceptance/otl4-browser/` — **not CI-gated** (JI44 Unsupported).

---

## 14. Schema / TARGET / ADR verdict

| Decision | Verdict |
|---|---|
| Version during milestone | remains **1.2.0** |
| TARGET | remains **7** |
| Schema / new index | **Unsupported** — STOP if required |
| New ADR | **Unsupported** — STOP if truly new authority discovered |
| Attempt ledger | **Unsupported** |
| Integration API v2 | **Unsupported** |

---

## 15. JI requirement matrix (JI1–JI55)

| ID | Requirement | Disposition |
|---|---|---|
| JI1 | Detail association by domain identity via existing indexes | Supported |
| JI2 | Internal deterministic linker (not public algorithm field) | Supported |
| JI3 | `association=null` = bounded-lookup miss (not “no retained row”) | Supported |
| JI4 | List `jobs` null / zero list Jobs queries | Supported |
| JI5 | Separate translation / Jobs / review / publication axes | Supported |
| JI6 | Associated job status + progress | Supported |
| JI7 | Associated item status + attempt_count + errors | Supported |
| JI8 | Bounded failure presentation adapter | Supported |
| JI9 | `retry_wait` / automatic retry honesty (no false wake clock) | Partial |
| JI10 | Deep-link to Jobs tab (`job_id`) | Supported |
| JI11 | Jobs tab remains first-class | Supported |
| JI12 | `open_job` / `open_jobs` allowed_actions | Supported |
| JI13 | `resume_job` via TI.6 admission + job-scoped confirm | Supported |
| JI14 | `retry_failed_job` via TI.6 admission + multi-item disclosure | Supported |
| JI15 | Item-scoped retry | Unsupported |
| JI16 | Delegate mutations to existing Jobs REST/service | Supported |
| JI17 | Server revalidation at mutation time | Supported |
| JI18 | AS ownership unchanged | Supported |
| JI19 | Concurrency policy unchanged (execution admit only) | Supported |
| JI20 | Budget display from job counters | Partial |
| JI21 | Per-item provider token fields | Unsupported |
| JI22 | Monetary cost | Unsupported |
| JI23 | Unknown usage ≠ zero | Supported |
| JI24 | TM known-zero when evidence available | Partial |
| JI25 | Outcome B / exactly-once help without false occurrence claims | Partial |
| JI26 | Exactly-once guarantee / attempt ledger | Unsupported |
| JI27 | Jobs-backed attention buckets | Deferred |
| JI28 | Store `translation_failed` attention unchanged | Supported |
| JI29 | Durable Jobs history beyond retention | Unsupported |
| JI30 | Full Jobs audit timeline | Deferred (OT15) |
| JI31 | Bulk failed retry | Unsupported (OTL.5 / OT20) |
| JI32 | Second Jobs engine/queue/policy | Unsupported |
| JI33 | Client-side retryability as authority | Unsupported |
| JI34 | Schema/TARGET change | Unsupported |
| JI35 | New ADR | Unsupported |
| JI36 | Integration API v2 | Unsupported |
| JI37 | Non-post Jobs mutation parity | Unsupported |
| JI38 | CLI required for OTL.4 | Unsupported (CLI already sufficient) |
| JI39 | Permissions via existing Jobs caps | Supported |
| JI40 | Privacy (no prompts/secrets/raw bodies) | Supported |
| JI41 | SaaS neutrality | Supported |
| JI42 | Accessibility + responsive smoke | Supported |
| JI43 | Local Playwright acceptance | Supported |
| JI44 | CI-gated browser infra | Unsupported |
| JI45 | Dirty editor preservation across Jobs refresh | Supported |
| JI46 | OTL.0–3 / TI.6 / TIQ regression | Supported |
| JI47 | Publication policy duplication | Unsupported |
| JI48 | Generic monitoring dashboard | Unsupported |
| JI49 | Jobs create-from-Operations | Deferred |
| JI50 | Reverse link Jobs→Operations | Partial |
| JI51 | TI.6 `JobsOperationAdmission` reused by Jobs UI + OTL | Supported |
| JI52 | `active_lock_key` not semantic/public linkage identity | Supported |
| JI53 | No serialized `selection_rule` (or equivalent) in API | Supported |
| JI54 | Bounded lookup cap validated (default 32) + `lookup.*` honesty | Supported |
| JI55 | `attempt_count` must not assert Outcome B occurred | Supported |

---

## 16. Work packages (OTL4.0–OTL4.8)

### OTL4.0 — Baseline & contracts

**Objective:** Lock freeze docs on implementation branch baseline.
**Scope:** Baseline SHA, JI/AC, ownership, lookup cap, admission ownership.
**Tests:** N/A (docs).
**Exclusions:** production code.
**Stop:** any schema/ADR creep.

### OTL4.1 — Linkage read path + bounded lookup

**Objective:** Fill detail `jobs` via domain-identity linker.
**Likely files:** Jobs repositories; `JobsLifecycleLinker`; `OperatorTranslationAssembler`; ViewModels; unit/integration tests.
**Tests:** match; miss; exhausted out-of-window fixture; list zero Jobs queries; no lock key / no `selection_rule` in payload.
**Deps:** OTL4.0.
**Exclusions:** UI mutations; schema.
**Stop:** need for new index.

### OTL4.2 — Detail presentation + navigation

**Objective:** Operations inspector Jobs section + deep-link.
**Likely files:** Operations inspector/panel; URL sync; copy for bounded null.
**Tests:** navigation; Jobs tab still first-class.
**Deps:** OTL4.1.
**Exclusions:** second Jobs console.

### OTL4.3 — TI.6 operation admission + OTL mapping

**Objective:** Extract `JobsOperationAdmission`; migrate Jobs UI; map OTL actions.
**Likely files:** new Jobs admission service; `JobService`/`JobTransitionPolicy` call sites; Jobs REST ViewModel `operations[]`; `jobs.ts` migration; `AllowedActionsResolver`.
**Tests:** admission parity with prior transition/retry rules; OTL mapping; no OTL duplicate rules.
**Deps:** OTL4.1.
**Exclusions:** concurrency prediction; item retry.
**Stop:** substantial TI.6 redesign required.

### OTL4.4 — Retry/resume UX + refresh/races

**Objective:** Job-scoped confirms; refresh; dirty draft; error surfacing.
**Tests:** paused→resume; failed→retry-failed; race; completed denied.
**Deps:** OTL4.2–4.3.

### OTL4.5 — Failure / usage / exactly-once help

**Objective:** Failure adapter; Partial usage/budget; Partial exactly-once help without occurrence claims; privacy PluginGuard.
**Deps:** OTL4.2.
**Exclusions:** attempt ledger; monetary cost.

### OTL4.6 — Attention & list performance lock

**Objective:** Keep Store attention; prove list assembler never queries Jobs.
**Deps:** OTL4.1.
**Exclusions:** Jobs-backed attention (Deferred).

### OTL4.7 — A11y + local Playwright

**Objective:** `acceptance/otl4-browser/`; keyboard/confirm/aria-live.
**Exclusions:** CI browser infra.

### OTL4.8 — Validation evidence & PluginGuard

**Objective:** Full validation suite + evidence map; no version/tag.
**Deps:** OTL4.1–4.7.

---

## 17. Acceptance criteria (AC1–AC79)

**Architecture:**
AC1 TI.6 sole Jobs authority.
AC2 no second engine/queue.
AC3 no OTL-owned retry/concurrency/budget policy.
AC4 no publication-policy duplication.
AC5 TARGET 7.
AC6 no new ADR.
AC7 no Integration API v2.
AC8 separate UI axes.
AC9 TI.6 `JobsOperationAdmission` authoritative for resume/retry-failed UI admission.
AC10 Jobs UI consumes that admission (not sole client-side policy).
AC11 OTL only maps admission into `allowed_actions`.

**Linkage:**
AC12 association keyed by domain identity.
AC13 `active_lock_key` not public linkage identity.
AC14 no `selection_rule` (or algorithm name) in serialized contract.
AC15 `lookup.bounded` + `job_scan_limit` when Jobs object returned.
AC16 `association=null` means bounded-lookup miss.
AC17 exhausted-window fixture does not claim “no retained record.”
AC18 list `jobs` null.
AC19 list performs zero Jobs queries.
AC20 `attempt_count` visible without Outcome-B occurrence claim.
AC21 no fabricated durable history.

**Presentation:**
AC22 job status.
AC23 item status.
AC24 failure adapter.
AC25 `retry_wait` not offered as manual retry.
AC26 navigation to Jobs.
AC27 Jobs tab first-class.
AC28 operator copy for null association is bounded-lookup honest.

**Actions:**
AC29 `open_job` / `open_jobs`.
AC30 `resume_job` allowed iff Jobs admission allows.
AC31 `retry_failed_job` allowed iff Jobs admission allows.
AC32 confirm discloses job scope + failed count.
AC33 no item-scoped retry.
AC34 mutations use existing Jobs endpoints.
AC35 execution revalidates.
AC36 browser does not authoritatively infer retryability.

**Races / refresh:**
AC37 refresh after mutate.
AC38 dirty draft preserved.
AC39 stale action → Jobs error, Store intact.
AC40 progress visible after refresh.

**Honesty:**
AC41 unknown usage ≠ zero.
AC42 no monetary cost.
AC43 exactly-once/Outcome-B help does not claim event occurred.
AC44 `attempt_count>1` alone never asserts Outcome B.
AC45 no exactly-once guarantee.
AC46 no prompts/secrets/raw bodies.

**Attention / perf:**
AC47 Store `translation_failed` unchanged.
AC48 no Jobs attention.
AC49 detail lookup ≤ scan limit.

**Security / a11y:**
AC50 Jobs caps.
AC51 object scope.
AC52 SaaS-neutral.
AC53 text status + keyboard + aria-live.
AC54 multi-item confirm a11y.

**Cross-object:**
AC55 no invented non-post Jobs mutation.
AC56 honest empty/unsupported association.

**Regression / packaging:**
AC57–AC67 OTL.0–3 / publish / retranslate hash / dirty / attention seams.
AC68 TI.6 regression.
AC69 TIQ unchanged.
AC70 PHPCS.
AC71 unit.
AC72 integration.
AC73 PluginGuard.
AC74 quality.
AC75 baseline verify.
AC76 build/ZIP.
AC77 TS build.
AC78 local Playwright.
AC79 no version/tag/release.

**Verified AC count:** **79**.

---

## 18. STOP conditions

Stop and reopen architecture if any of:

- second Jobs engine/queue/retry/concurrency/budget policy **or OTL reimplementation of Jobs admission rules**
- schema/TARGET change or new index required for basic linkage
- durable history / attempt ledger required for acceptance
- treating `association=null` as “no retained Jobs row” in product copy/API
- serializing retrieval algorithm as stable client contract
- claiming Outcome B occurred from `attempt_count` / weak heuristics
- generic monitoring dashboard; Integration API v2; OTL.5 bulk retry pulled forward; OTL.6; TSC
- site-specific/Biopentra production behavior; exactly-once redesign; publication-policy duplication
- Operations-list Jobs N+1; client-side retryability as authority
- item-scoped retry without separate freeze
- inability to disclose job-scoped mutation impact honestly
- substantial TI.6 redesign required for admission extraction

---

## 19. Known limitations / debt

- Bounded lookup (default scan 32) may miss older retained matches → honest `exhausted`
- Retention 30/90d still deletes history
- No per-item usage persistence
- Automatic retry wake time not precisely exposed
- Outcome B occurrence not detectable per association without ledger (unsupported)
- Jobs-backed attention Deferred
- Non-post Jobs parity Unsupported
- Bulk failed retry → OTL.5
- Jobs create-from-Operations Deferred
- Playwright not CI-gated

---

## 20. STATE A — FREEZE

OTL.4 is implementable as a **bounded additive integration**:

- TARGET stays **7**; no new ADR
- TI.6 remains sole Jobs authority
- Detail linkage uses existing `object_lang` + `job_segment` (lock key optimization only)
- Narrow `JobsOperationAdmission` extract; OTL maps only
- Outcome B honesty **Partial** without ledger
- Bounded lookup honesty; list stays cheap; Jobs tab first-class
- Parent OT11/OT12 satisfied

**Not STATE B** unless implementation proves admission extraction needs substantial TI.6 redesign (then STOP rather than duplicate rules in OTL).

---

## 21. Planning freeze checklist

- [x] JI1–JI55 frozen
- [x] OTL4.0–OTL4.8 frozen
- [x] AC1–AC79 frozen
- [x] External amendments A1–A5 preserved
- [x] TARGET 7 / no schema / no new ADR
- [x] Independent planning review PASS
- [ ] Freeze merge to `main`
- [ ] Planning closure docs
- [ ] Post-closure CI green

---

## 22. Exact implementation next step (after freeze on main)

Run the combined OTL.4 implementation + independent implementation review + merge + milestone closure task from the frozen main baseline.

Do **not** create `feature/otl4-jobs-integration` until this plan is frozen on `main`.
Do **not** start OTL.5–OTL.6 or TSC under this plan.
