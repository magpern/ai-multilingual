# Background Translation Jobs — Operator Runbook

Operational guide for background translation jobs on Biopentra dev/production.
Covers health checks, backlog, stuck leases, outages, budgets, lifecycle actions,
retention cleanup, migration, rollback, and sign-off.

**Related:** [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](../plans/BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md) §18, §22–23; [P2 Jobs / Stale Operator Literacy](../plans/P2_JOBS_STALE_OPERATOR_LITERACY_IMPLEMENTATION_PLAN.md).

---

## 0. Operator literacy (P2)

Ordinary Jobs create/monitor/recover is done from **Translator Workspace → Jobs** (and Operations for stale/conflict detail). CLI/REST remain diagnostics — not the normal merchant path.

| Concept | Meaning |
|---|---|
| Waiting | Job `queued` — **Run now** (administrators only) starts Action Scheduler wake |
| Completed with skips | Job finished with skipped/stale item buckets — expand Details |
| Skipped — conflict protected | Item `skipped_conflict` — no silent overwrite; review/edit or confirmed Retranslate when admitted |
| Source moved during job | Item `stale_source` — create a fresh job after source is stable |
| Store stale (published) | Remains published until edit/retranslate; visitor display still depends on overlay/route eligibility |
| Store stale (unpublished) | Currently unpublished — edit/retranslate before publishing; gate settings may still affect overlay |

**Multi-post create:** Workspace **Bulk translate** posts `posts[]` without segment keys. The service resolves **missing** segments per post (same eligibility as `translate_missing`). No new Job type.

**Caps:** Editors may create/manage/cancel; only administrators have `aiml_run_translation_jobs` (Run / Retry failed).

---

## 1. Health checks

### Action Scheduler (AS)

Jobs depend on WooCommerce Action Scheduler (`aiml_run_job`, `aiml_jobs_sweep` in group `aiml-jobs`).

| Check | Command / endpoint |
|---|---|
| REST health | `GET /wp-json/aiml/v1/jobs/health` |
| Diagnostics (aggregates) | `GET /wp-json/aiml/v1/jobs/diagnostics` |
| WP-CLI | `wp action-scheduler list --group=aiml-jobs` |

**Expected:** `action_scheduler.available = true` in health/diagnostics.

**Create-time fail-closed:** When AS is unavailable, `POST /jobs` returns **503** — frontend rendering is unaffected.

### Worker / queue snapshot

Diagnostics returns bounded aggregates only (no bodies, prompts, or secrets):

- `status_counts` — jobs by status (queued, running, retry_wait, failed, …)
- `queue_age.max_seconds` — oldest queued job age (capped at 30 days)
- `stuck_leases` — expired leases not yet reclaimed
- `cleanup_backlog` — terminal jobs eligible for retention deletion
- `counters` — fixed-key option counters (provider errors, budget stops, stale conflicts, cleanup totals)

Capability required: `aiml_view_translation_jobs`.

---

## 2. Queue backlog

**Symptoms:** Rising `status_counts.queued`, high `queue_age.max_seconds`, Workspace Jobs tab showing many queued jobs.

**Actions:**

1. Confirm AS is processing: `wp action-scheduler run --group=aiml-jobs` (or wait for WP cron / host cron).
2. Inspect site-wide running cap — only bounded concurrency is supported; excess jobs wait.
3. Wake a specific job: `wp aiml jobs run <id>` (requires `aiml_run_translation_jobs`).
4. Review provider availability in diagnostics (`provider_error_rate`, `counters.provider_errors`).

**Note:** Progress counters on job rows are reconciled from item rows — if UI progress looks wrong, inspect item statuses via `wp aiml jobs show <id>`.

---

## 3. Stuck leases

**Symptoms:** Job stuck in `running` with no progress; `stuck_leases > 0` in diagnostics.

**Cause:** Worker process died mid-item; lease TTL expired but sweep not yet run.

**Recovery:**

1. Hourly sweep (`aiml_jobs_sweep`) reclaims stale leases and resets orphaned `running` items to `queued`.
2. Manual sweep: `wp aiml jobs cleanup --yes` (requires `aiml_run_translation_jobs`).
3. Re-enqueue: `wp aiml jobs run <id>`.

**Safety:** Sweep never deletes active/leased jobs; it only clears expired lease fields and resets running items.

---

## 4. Provider outage

**Symptoms:** Items enter `retry_wait` or `failed`; `counters.provider_errors` rises; jobs may pause with `provider_unavailable`.

**Actions:**

1. Pause affected jobs if needed: `wp aiml jobs pause <id>` or Workspace **Pause**.
2. Fix provider credentials / upstream outage.
3. Resume: `wp aiml jobs resume <id>` then `wp aiml jobs run <id>`.

**Frontend:** Public page rendering does **not** depend on job/AS success — only the translation overlay pipeline serves visitors.

---

## 5. Budget breach

**Symptoms:** Job pauses with `last_error_code = budget_exceeded`; `counters.budget_stops` increments; audit event `translation_job_budget_exceeded`.

**Policy:** Budget is enforced **before the next item** — successful persists are never discarded.

**Actions:**

1. Inspect job budget fields via `wp aiml jobs show <id>`.
2. Raise limits on a **new** job if needed (existing job budgets are frozen at create).
3. Resume after adjustment or cancel: `wp aiml jobs cancel <id>`.

---

## 6. Pause, cancel, retry

| Action | REST | CLI | Capability |
|---|---|---|---|
| Pause (safe boundary) | `POST /jobs/{id}/pause` | `wp aiml jobs pause <id>` | `aiml_cancel_translation_jobs` |
| Cancel | `POST /jobs/{id}/cancel` | `wp aiml jobs cancel <id>` | `aiml_cancel_translation_jobs` |
| Resume | `POST /jobs/{id}/resume` | `wp aiml jobs resume <id>` | `aiml_cancel_translation_jobs` |
| Retry failed items | `POST /jobs/{id}/retry-failed` | `wp aiml jobs retry-failed <id>` | `aiml_run_translation_jobs` |

Pause/cancel are observed at **safe item boundaries** — the current in-flight provider call is not forcibly aborted.

---

## 7. Retention / cleanup

**Defaults (plan §18):**

| Class | Retention |
|---|---|
| Completed (+ completed_with_errors) | 30 days after `finished_at` |
| Failed / cancelled | 90 days after `finished_at` |
| Orphan items (missing parent job) | Deleted on sweep |

**Never deleted:** Active jobs, jobs with valid leases, jobs with `active_lock_key` set.

**Sweep:** `aiml_jobs_sweep` (hourly recurring) + `wp aiml jobs cleanup --yes`.

**Metrics:** Recorded in diagnostics counters (`cleanup_jobs_deleted`, `cleanup_items_deleted`, `cleanup_orphans_deleted`).

**Scope:** Cleanup touches **only** `aiml_jobs` / `aiml_job_items` — never Store, TM, or Glossary.

---

## 8. Audit events

Hook: `aiml_translation_job_audit`

Stable event names: `translation_job_created`, `translation_job_started`, `translation_job_paused`, `translation_job_resumed`, `translation_job_cancelled`, `translation_job_completed`, `translation_job_failed`, `translation_job_item_failed`, `translation_job_budget_exceeded`, `translation_job_stale_source`.

Payloads contain **safe metadata only** — never translation body, source body, prompt, API key, or stack traces.

Retention: follow existing site audit policy (≥90 days recommended).

---

## 9. Schema migration v5 → v6

1. Deploy plugin with Migrator `TARGET=6`.
2. Activate or visit admin to run migrations (additive DDL for `aiml_jobs`, `aiml_job_items`).
3. Verify: `wp db query "SHOW TABLES LIKE '%aiml_job%'"`.
4. Roll-forward only in production — no down migration on live data.

Uninstall drops job tables with other plugin tables and unschedules AS hooks.

---

## 10. Rollback

| Scenario | Action |
|---|---|
| Disable scheduling only | Deactivate plugin or remove AS recurring `aiml_jobs_sweep`; tables remain |
| Stop new jobs | AS unavailable or revoke `aiml_manage_translation_jobs` |
| Full rollback post-cutover | Leave tables unused; do not delete Store/TM data |
| Emergency | Cancel in-flight jobs via CLI/REST; frontend unaffected |

Production rollback is **additive-forward** — do not drop v6 tables if historical job audit is needed.

---

## 11. Operator sign-off checklist

Before enabling jobs broadly for editors:

- [ ] `GET /aiml/v1/jobs/health` → AS available
- [ ] `GET /aiml/v1/jobs/diagnostics` → counters baseline captured
- [ ] Test create → run → complete on one post in Workspace
- [ ] Verify pause/cancel at boundary on a long job
- [ ] Confirm audit hook receives events without bodies (spot-check log sink)
- [ ] Confirm `aiml_jobs_sweep` scheduled in AS
- [ ] Document provider budget defaults for operators
- [ ] Frontend spot-check: pages render when AS is stopped

---

## 12. Quick reference

```bash
# Diagnostics
curl -s -H "X-WP-Nonce: …" https://dev.biopentra.eu/wp-json/aiml/v1/jobs/diagnostics | jq .

# Manual sweep + retention
wp aiml jobs cleanup --yes

# Inspect job
wp aiml jobs show 42

# Sync run (debug only)
wp aiml jobs run 42 --sync
```
