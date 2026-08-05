# Review Workflow Validation Log — R7 closure

Operational acceptance record for **Review Workflow** (ADR-0015 review workflow
and TM approval policy).

## Environment

| Item | Value |
|---|---|
| Repo host | `/opt/biopentra/dev/ai-multilingual` (Docker-only test tooling; no host PHP/Composer/Node) |
| Branch | `feature/review-workflow` (merged to `main` @ `c8b383c67`) |
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

**PASS** — executed 2026-08-05 against live `https://dev.biopentra.eu` with
`feature/review-workflow` @ `069d620a85a92b8f54a3d775001ea0c68af018fd`
bind-mounted (schema migrated 4→5). Runner:
`acceptance/review-workflow/smoke-dev.php` via WP-CLI `wp eval-file`
(REST + Store + frontend HTTP checks). Evidence:
`acceptance/review-workflow/smoke-latest.txt` (**68/68 PASS**).

### Deployment

| Item | Value |
|---|---|
| Branch | `feature/review-workflow` |
| Commit | `069d620a85a92b8f54a3d775001ea0c68af018fd` |
| Method | Bind-mount `/opt/biopentra/dev/ai-multilingual` → WP plugins (already active) |
| Plugin version | `0.1.0` (`AIML_VERSION`) |
| WordPress | `7.0.2` |
| PHP (runtime) | `8.4.23` (container) / edge `8.3.32` |
| Schema before | `4` |
| Schema after | `5` |
| Deploy timestamp (UTC) | `2026-08-05T19:28:41Z` |
| Pre-migrate Store/TM/Glossary | Store 148 rows; TM 0; glossary_version 2; content MD5 samples unchanged |
| Post-migrate | All review columns + `lang_review_queue`; 148/148 `not_submitted`; Store MD5 samples unchanged; TM unchanged; glossary_version 2 |

### Test content

| Item | Value |
|---|---|
| Validation page | ID `6356`, slug `review-workflow-validation` (left `private` after smoke) |
| QA fixture page | ID `6357` (trashed after smoke; prior `6355` also trashed) |
| Source language | `en` (default) |
| Target language | `sv` (`language_id=2`) |
| Translator | `aiml-rw-translator` (editor, `aiml_translate`, no review cap) uid `10` |
| Reviewer | `aiml-rw-reviewer` (custom role, `aiml_review_translations`, no `aiml_translate`) uid `11` |
| Segment keys | `post_title` + two Strategy F paragraphs |

### Checklist results

- [x] Translator opens Workspace segments (HTTP 200)
- [x] Translator edits/saves; `review_status=not_submitted`
- [x] No-op save preserves pending review
- [x] Material edit resets to `not_submitted`
- [x] Submit → pending + submitted_by/at/hash
- [x] Translator cannot approve/reject (403)
- [x] Reviewer queue lists pending; language/post/status filters; no review/queue tables
- [x] Reviewer cannot edit translation text (403); text unchanged
- [x] Reject without reason → 422; valid reject → rejected + metadata; text unchanged; no TM write
- [x] Correct + resubmit → not_submitted then pending
- [x] Stale approve/reject → HTTP 409; no transition
- [x] Approve → approved + reviewed_by/at; text unchanged; TM write-back (identity upsert; target_text hit)
- [x] Duplicate approve → 200; TM row count / use_count not inflated
- [x] Reject after historic TM preserves TM rows
- [x] Batch approve partial success (one approved, one hash conflict remains pending)
- [x] Approve with warnings allowed (HTTP 200)
- [x] QA error (`Hello {name}` vs missing placeholder) blocks approve (`aiml_qa_blocked` 422); reject still allowed
- [x] Diagnostics endpoint 200; conflicts and qa_blocked counters > 0
- [x] Audit events `review_submitted` / `review_approved` (+ invalidate); payloads exclude translation bodies / full rejection reasons
- [x] Frontend EN/SV pages HTTP 200; no cross-post leak marker; **rendered false positives = 0**
- [x] Unauthorized subscriber approve → 403

Operator sign-off: automated WP-CLI/REST smoke on live bind-mounted feature branch, 2026-08-05.

## Rendered false-positive count

| Check | Result |
|---|---|
| Review Workflow code touches BlockRenderGate / FrontendRenderer | **None** |
| Live frontend smoke | EN/SV HTTP 200 for validation page; no Review-driven render divergence observed |
| FP render regression attributed to Review Workflow | **0** |

## Compatibility

| Check | Result |
|---|---|
| F11 DTO field names | **Unchanged** |
| F10/F11 REST routes | **Unchanged**, additive only |
| `aiml_review_invalidated_by_edit` Store hook (R2) | **Unchanged signature** |
| Pre-deploy Tier 0 (this closure) | **PASS** — unit 402/911 (2 skipped); integration 433/8818 (2 skipped); PluginGuard 17/6325; PHPCS 0 errors; TS/Jest 50; webpack OK; `git diff --check` OK; markdown links OK |

## Documentation

| Doc | Result |
|---|---|
| `REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md` | **PASS** |
| ADR-0015 | **Accepted** |
| `POST_V1_PRODUCT_ROADMAP.md` §11.2 | Updated at merge |
| `docs/ROADMAP.md` | Updated at merge |
| `docs/HOOKS.md` | **PASS** |

## Known limitations / technical debt

- `tm_write_back_failure` remains hard to force under a valid approval path.
- Diagnostics counters reset has no admin UI trigger yet.
- As accepted in ADR-0015: approval does not gate frontend rendering; rejected text may still render when otherwise eligible; no version history.

## Merge readiness

**Review Workflow validation: PASS**  
**Merge readiness: YES** (merged)

Merged to `main` @ `c8b383c67e988dffc5c57e6289570ce724fa9b99`. Tag `review-workflow-complete`. R0–R7 complete; ADR-0015 Accepted; schema v5 live on dev; Tier 0 green; targeted Review smoke **68/68 PASS**; rendered FP = 0.

## Recommended tag

`review-workflow-complete` — create **on the merge commit** after merge to `main`.

## Exact next step

Draft and review the canonical Background Translation Jobs architecture plan on `feature/background-translation-jobs-plan`. Do not implement production code until the plan is frozen and ADR-0011 compliance has been revalidated.
