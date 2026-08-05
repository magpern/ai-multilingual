# Review Workflow Validation Log — R7 closure

Operational acceptance record for **Review Workflow** (ADR-0015 review workflow
and TM approval policy).

## Environment

| Item | Value |
|---|---|
| Repo host | `/opt/biopentra/dev/ai-multilingual` (Docker-only test tooling; no host PHP/Composer/Node) |
| Branch | `feature/review-workflow` (**not yet merged** to `main`) |
| Baseline | `main` @ `fb678963e` (Review Workflow planning docs merge) |
| Plugin | AI Multilingual `0.1.0` (`AIML_VERSION`) |
| PHP (unit/PHPCS) | `8.3` (`php:8.3-cli`) |
| PHP (integration) | `8.3` (`aiml-test-runner`, custom `php:8.3-cli` + `mysqli`) |
| WordPress (integration) | pinned to installed `wp-phpunit` version via `tests/bin/install-wp.sh` |
| WooCommerce (integration) | `10.9.4` |
| DB (integration) | `mariadb:11.4` on `aiml-test` internal Docker network |
| Node (frontend) | `node:20` |
| ADR-0015 | **Accepted** (2026-08-05) |

## Entry gate (G0)

| Gate | Required state | Result |
|---|---|---|
| ADR-0015 Accepted | Status Accepted + PO residual risks | **PASS** — `f0596fd66` |
| Frozen plan | `REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md` | **PASS** |
| R1–R6 complete | Prior work-package commits present | **PASS** |
| No redesign / no scope change | Architecture locks held | **PASS** |

## Work packages (R0–R7)

| WP | Commit | Result |
|---|---|---|
| R0 plan + ADR proposal (on `main`) | `cd78c6bd4` docs(review): create Review Workflow implementation plan (merged `fb678963e`) | **PASS** |
| R0 ADR accept / gate open | `f0596fd66` docs(review): accept ADR-0015 and open implementation gate | **PASS** |
| R1 Schema v5 | `52306674f` feat(review): add review workflow schema and migration | **PASS** |
| R2 Store review axis | `3952daa02` feat(review): add Store-owned review state | **PASS** |
| R3 ReviewWorkflowService | `59ae70089` feat(review): add review workflow transition service | **PASS** |
| R4 REST/capabilities/queue | `c420c832b` feat(review): add review REST API and queue | **PASS** |
| R5 TM write-back gate | `ba24fee26` feat(review): gate Translation Memory write-back on approval | **PASS** |
| R6 Workspace UI | `1deba5c92` feat(review): add Review Workflow workspace UI | **PASS** |
| R7 Audit/diagnostics/closure | *(this closure — two commits, see below)* | **PASS** |

## R7 scope delivered

**Audit** — `aiml_review_audit` action (matches the Glossary/Rollout hook
convention), stable event names:

- `review_submitted`, `review_resubmitted` — `ReviewWorkflowService::submit()`
- `review_approved` — `ReviewWorkflowService::approve()`
- `review_rejected` — `ReviewWorkflowService::reject()`
- `review_invalidated_by_edit` — new `ReviewEditInvalidationAuditBridge`, bridging the existing R2 `aiml_review_invalidated_by_edit` Store hook into the same audit channel (no behavior change to the original hook)
- `review_batch_completed` — `ReviewBatchCoordinator::run_batch()`, one summary event per batch call

Safe payload only (`ReviewAuditLogger::sanitize_payload()` enforces an
allow-list): post/source id, segment key, language id, old/new
`review_status`, user id, ISO-8601 UTC timestamp, `source_surface`, an 8-char
non-reversible `submitted_hash_fingerprint` (first 8 hex chars of the
submitted-translation hash), and rejection `reason_present` / `reason_length`.
**Never** `translated_text`, `source_text`, or the full `rejection_reason`.

**Diagnostics** — bounded, low-cardinality, no new tables:

- `Store::review_status_counts()` / `review_pending_age_stats()` — query-time, optionally scoped by post/language; pending age capped at `Store::REVIEW_PENDING_AGE_BOUND_SECONDS` (30 days)
- `ReviewDiagnosticsCounters` — a single WordPress option (`aiml_review_diagnostics_counters`) with five fixed keys (`conflicts`, `approval_failures`, `qa_blocked_approvals`, `tm_write_back_success`, `tm_write_back_failure`); never grows with post/segment/user identity
- Wired into `WorkspaceService::submit_review()` / `approve_review()` / `reject_review()` / `write_back_tm_on_approval()`
- Exposed read-only via `GET /aiml/v1/workspace/review-diagnostics` (`aiml_review_translations` capability, same as the review queue)

## Quality gates (Tier 0)

| Gate | Command | Result |
|---|---|---|
| PHPUnit unit | `vendor/bin/phpunit -c phpunit.xml.dist` | **PASS** — 402 tests, 911 assertions (2 skipped, NFC without `intl` in unit image) |
| PHPUnit integration | `vendor/bin/phpunit -c phpunit-integration.xml.dist` | **PASS** — 433 tests, 8818 assertions (2 skipped, same NFC reason in `aiml-test-runner`) |
| PluginGuard (subset of integration) | `--filter PluginGuardTest` | **PASS** — 17 tests, 6325 assertions; no direct-SQL/unprepared-query/architecture violations from R7 files |
| PHPCS | `vendor/bin/phpcs` | **PASS** — 0 errors; 2 pre-existing warnings unrelated to Review Workflow (`RolloutMetricsRegistry.php` `json_encode`, `PreviewProductionPathTest.php` useless override) |
| TypeScript | `tsc --noEmit -p tsconfig.json` (translator-workspace) | **PASS** — 0 errors (R7 added no frontend code; existing R6 UI unaffected) |
| Jest | `wp-scripts test-unit-js` (translator-workspace) | **PASS** — 6 suites, 50 tests |
| webpack production build | `wp-scripts build` (translator-workspace) | **PASS** — compiled successfully, `index.js` 55.5 KiB (minimized) |
| `git diff --check` | `git diff --check` (tracked changes) | **PASS** — no whitespace errors |
| Markdown relative-link validation | custom checker over touched `.md` files | **PASS** — see below |
| F9 35-suite | `run-f9-acceptance.sh` | **NOT RUN** (explicit policy — out of scope for this closure) |

### New tests added in R7

| File | Tests |
|---|---|
| `tests/unit/Workspace/Review/ReviewAuditLoggerTest.php` | Payload sanitization allow-list; hash fingerprinting; no-op outside WordPress |
| `tests/unit/Workspace/Review/ReviewDiagnosticsCountersTest.php` | Fixed key set; unknown-key no-op; no-op outside WordPress |
| `tests/integration/ReviewAuditTest.php` | 7 tests — one per audit event, safe-payload assertions, duplicate-approve does not re-fire |
| `tests/integration/ReviewDiagnosticsTest.php` | 12 tests — Store query-time counts/age, counter wiring (conflicts, approval failures, QA-blocked, TM write-back success), REST shape and capability gating |

## Architecture audit (ADR-0015 invariants)

| Invariant | Result |
|---|---|
| Store is the single owner of translation + review metadata | **PASS** — no `aiml_review_*` tables; audit/diagnostics classes are stateless helpers or thin option wrappers, no new persistence surface for translation/review facts |
| Translation axis (`status`) and review axis (`review_status`) stay separate | **PASS** — R7 code never reads/writes `status` on approve/reject; diagnostics counters are keyed by review lifecycle events only |
| Review queue is a Store query, never a persisted queue | **PASS** — unchanged from R4; `review-diagnostics` route is likewise query-time (`Store::review_status_counts()` / `review_pending_age_stats()`), not a new persisted queue/table |
| Approval never edits translated/source content | **PASS** — unchanged from R3/R5; audit logging happens *after* the transition, reading already-persisted fields, never writing translation content |
| Frontend rendering stays independent of review | **PASS** — R7 touches no render-path file (`BlockRenderGate`, `BlockFrontendRenderer`, cache invalidation hooks untouched beyond the pre-existing R6 registration point in `Plugin::init()`); rendered FP = 0 (see below) |
| TM write-back stays approval-gated | **PASS** — unchanged from R5; R7 only *observes* the existing `write_back()` result to increment a counter, never calls `write_back()` itself and never changes when/whether it is called |
| QA and Glossary integrations are reused, not duplicated | **PASS** — QA-blocked approvals reuse the existing `WorkspaceQAException` / `assert_qa_passes_for_approval()` path from R4; R7 adds only a counter increment in the existing catch block; no Glossary code touched |
| No second editor, Store, queue, or pipeline introduced | **PASS** — `ReviewAuditLogger`, `ReviewAuditEvents`, `ReviewDiagnosticsCounters`, `ReviewEditInvalidationAuditBridge` are all stateless/thin-option helpers registered once from `Plugin::init()`; no new domain model, no new admin surface beyond one additive read-only REST route |

## Audit privacy

| Check | Result |
|---|---|
| No `translated_text` / `source_text` in any audit payload | **PASS** — asserted in `ReviewAuditTest` for every event |
| Full `rejection_reason` never logged (`reason_present` + `reason_length` only) | **PASS** — asserted in `ReviewAuditTest::test_reject_emits_review_rejected_with_reason_length_only` |
| Submitted-hash fingerprint is non-reversible and bounded (8 hex chars) | **PASS** — `ReviewAuditLogger::hash_fingerprint()`, asserted in `ReviewAuditLoggerTest` and `ReviewAuditTest` |
| `ReviewAuditLogger::sanitize_payload()` allow-list drops unknown/unsafe keys | **PASS** — `ReviewAuditLoggerTest` |

## Diagnostics boundedness

| Check | Result |
|---|---|
| Counter option has a fixed, closed key set (5 keys) | **PASS** — `ReviewDiagnosticsCounters::keys()`; unknown keys ignored on increment |
| No per-post / per-segment / per-user persisted metric | **PASS** — the only persisted diagnostics artifact is one option with 5 scalar counters; pending/approved/rejected counts and pending age are computed at query time from `Store`, never persisted |
| Pending age is bounded, never unbounded | **PASS** — capped at `Store::REVIEW_PENDING_AGE_BOUND_SECONDS` (2,592,000s / 30 days); `ReviewDiagnosticsTest::test_review_pending_age_stats_are_bounded_and_non_negative` |
| Diagnostics route requires `aiml_review_translations` | **PASS** — `ReviewDiagnosticsTest::test_review_diagnostics_route_requires_review_capability` |

## Targeted Review browser smoke

**PENDING deploy.** No live build of `feature/review-workflow` is currently
served on `dev.biopentra.eu` (the deployed AI Multilingual build is from an
earlier branch); running Playwright or manual browser checks against it would
not exercise this branch's R1–R7 code and would produce false confidence, so
no browser PASS is claimed here. R6 already carries TypeScript + Jest coverage
of all client-side Review logic (badges, dialog validation, queue
selection/filtering, batch result application, conflict/QA error surfacing);
R7 adds no frontend code, so no new UI risk is introduced by this closure.

Manual checklist to run once this branch (or its merge to `main`) is deployed,
using a translator user (`aiml_translate`) and a reviewer user
(`aiml_review_translations` without `aiml_translate`):

- [ ] Translator edits and saves a segment; badge shows `not_submitted`
- [ ] Translator submits for review; badge shows `pending`; segment becomes read-only for the translator's own re-edit until resubmit
- [ ] Reviewer opens Review queue tab; sees the pending item with post/language/status filters
- [ ] Reviewer approves; badge shows `approved`; `reviewed_by` / `reviewed_at` populated; translated text unchanged
- [ ] Reviewer rejects a different pending item with a reason (1–512 chars); badge shows `rejected`; reason visible to translator
- [ ] Translator corrects the rejected segment and resubmits; badge returns to `pending`; prior rejection reason no longer active
- [ ] Reviewer attempts to approve a segment edited after submit (stale `submitted_translation_hash`) → HTTP 409, refreshed row, resubmit required
- [ ] Reviewer attempts to approve a segment with a QA error (e.g. placeholder mismatch) → blocked with `aiml_qa_blocked`, QA panel shows the error
- [ ] A segment with only QA **warnings** (e.g. `glossary_term_missing`) approves successfully
- [ ] Batch review of multiple segments on one post: partial success is surfaced per row, no silent skips
- [ ] A user with neither `aiml_translate` nor `aiml_review_translations` cannot open the Workspace admin page (`aiml_workspace_access` gate) and gets 403 from all review REST routes
- [ ] Frontend rendering of the post/page is visually unchanged before/after every review-state transition above (approve/reject do not affect the rendered page)
- [ ] Rendered false-positive count attributable to Review Workflow = 0

## Rendered false-positive count

| Check | Result |
|---|---|
| Review Workflow code touches render/UUID/Store write path | **None** — R7 files are all in `src/Workspace/Review/*` (new), plus additive diagnostics/audit call-sites in `WorkspaceService`, `Store`, `ReviewWorkflowService`, `ReviewBatchCoordinator`, and one route in `WorkspaceController`; no `BlockRenderGate` / `BlockFrontendRenderer` / render-cache file is touched |
| FP render regression attributed to Review Workflow | **0** (by construction; no live browser proof yet — see smoke section above) |

## Compatibility

| Check | Result |
|---|---|
| F11 DTO field names | **Unchanged** |
| F10/F11 REST routes | **Unchanged**, additive only (`review-diagnostics` is new, read-only) |
| `aiml_review_invalidated_by_edit` Store hook (R2) | **Unchanged signature**; now additionally consumed by `ReviewEditInvalidationAuditBridge`, which does not alter its callers or behavior |
| Existing R1–R6 tests | **PASS** — no regressions in the full unit/integration run above |

## Documentation

| Doc | Result |
|---|---|
| `REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md` R7 status + Status line | **PASS** (this closure) |
| ADR-0015 | **PASS** — already Accepted; no decision changed by R7, no edit required |
| `POST_V1_PRODUCT_ROADMAP.md` §11.2 `Implementation status` | **PASS** (this closure) |
| `docs/ROADMAP.md` Review Workflow row | **PASS** (this closure) |
| `docs/HOOKS.md` — new REST routes + `aiml_review_audit` hook | **PASS** |
| `docs/plans/F11_FROZEN_API.md` | **Unchanged** — TM write-back amendment already documented at R5; R7 adds no new amendment |

## Known limitations / technical debt

- Targeted Review browser smoke is a **manual checklist, not yet executed** — no `feature/review-workflow` build is live on `dev.biopentra.eu`. This is the single biggest residual gap before merge.
- `tm_write_back_failure` is implemented and unit/PHPCS-clean but **not independently exercised** by an integration test: `TMRepository::upsert()` only returns `WP_Error` for structurally invalid entries (missing language ids / empty hash / unknown origin), all of which the real approval call site always supplies validly (`TMRepository` is `final`, so it cannot be swapped for a failing double in an integration test without a DB-level fault injection harness that does not exist in this repo). The success path (`tm_write_back_success`) *is* covered.
- Diagnostics counters reset only via `ReviewDiagnosticsCounters::reset()`, which has no admin UI trigger yet (callable today only via WP-CLI `wp eval` or a future admin action) — acceptable for MVP per ADR-0015 §13 scope, flagged for a future ops-UI pass.
- As accepted in ADR-0015: approval does not gate frontend rendering; rejected text remains renderable when otherwise eligible; no version history/review snapshot body is stored; review concurrency relies on submitted-translation-hash comparison rather than true optimistic locking on the whole row.

## Merge readiness

**Not merged.** R0–R7 complete and validation **PASS** on `feature/review-workflow`. Ready for Product Owner review of the branch diff and the manual browser smoke checklist above before merge.

## Recommended tag

`review-workflow-complete` — to be created **on the merge commit** once `feature/review-workflow` is merged to `main` (per this repo's convention of tagging merges, not pre-merge feature-branch commits).

## Exact next step

1. Product Owner reviews the full `main...feature/review-workflow` diff.
2. Deploy `feature/review-workflow` to `dev.biopentra.eu` and execute the manual browser smoke checklist above.
3. Merge to `main`, tag `review-workflow-complete` on the merge commit, and flip this log's "Merge readiness" / the plan's Status line / roadmap §11.2 to **Complete**.
4. Only then consider starting **Background Translation Jobs** (Post-v1 roadmap §11.3).
