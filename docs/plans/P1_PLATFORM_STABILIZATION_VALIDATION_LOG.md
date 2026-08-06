# P1 Platform Stabilization — Validation Log

**Milestone:** P1 — Platform Stabilization  
**Plan:** [P1_PLATFORM_STABILIZATION_IMPLEMENTATION_PLAN.md](P1_PLATFORM_STABILIZATION_IMPLEMENTATION_PLAN.md)  
**Branch:** `feature/p1-platform-stabilization`  
**Baseline:** Platform v1.0.0 / plan freeze `1205ad081`  
**Environment:** `https://dev.biopentra.eu` (dev / production-like for this VPS)

---

## Contract checklist (S0)

| Contract | Status |
|---|---|
| Overlay-not-duplication | Unchanged — no P1 `src/` contract edits |
| UUID / Store / TM / Glossary / Review / Jobs ownership | Unchanged |
| Migrator TARGET = 6 | Verify only — no TARGET bump |
| F10/F11 REST ViewModels | Unchanged |
| No new providers / Elementor / nested / Woo | Confirmed OOS |
| No new diagnostics subsystem | Confirmed — existing REST/CLI only |
| PluginGuard | Required green if any production code lands |

---

## Inventory (S0)

### REST surfaces (shipped)

| Family | Routes (representative) |
|---|---|
| Workspace | `/aiml/v1/workspace/*` including review-queue, review-diagnostics |
| Providers | `/aiml/v1/providers/active`, `test-connection`, `models` |
| Glossary | `/aiml/v1/glossary`, `/glossary/diagnostics`, CRUD |
| Jobs | `/aiml/v1/jobs`, `/jobs/health`, `/jobs/diagnostics`, lifecycle actions |

### CLI (shipped)

- `wp aiml block status`
- `wp aiml jobs *` (list/show/run/pause/resume/cancel/retry-failed/cleanup)

### Acceptance harnesses (pre-P1)

- `acceptance/rc/v1-openai-rc.php` — canonical OpenAI RC baseline
- `acceptance/jobs/smoke-dev.php`
- `acceptance/review-workflow/smoke-dev.php`
- F9–F14 browser/staging harnesses

### Ops docs (pre-P1)

- `docs/ops/BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md`
- F12/F13 runbooks under `docs/plans/`

---

## S0 — Health probe evidence

**Harness:** `acceptance/p1/health-probe.php`  
**Command:**

```bash
cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/health-probe.php
```

**Result:**

| Field | Value |
|---|---|
| Date | 2026-08-06 |
| Exit code | 0 |
| Summary | **21/21 PASS** |

Probe covered: schema 6, all `aiml_*` tables, Jobs/Glossary/Providers/Workspace routes + HTTP, diagnostics secret redaction, encrypted key length only.

---

## S1 — Deploy verify

**Harness:** `acceptance/p1/deploy-verify.php`  
**Result:** _(pending)_

---

## S2 — Ops/docs alignment

**Result:** _(pending)_

---

## S3 — Diagnostics

**Result:** _(pending)_

---

## S4 — Schema verify

**Result:** _(pending)_

---

## S5 — Acceptance index + RC baseline

**Result:** _(pending)_

---

## S6 — Release checklist

**Result:** _(pending)_

---

## S7 — Backup / restore rehearsal

**Result:** _(pending)_

---

## S8 — Rollback rehearsal

**Result:** _(pending)_

---

## S9 — Operational readiness sign-off

| Sign-off item | Status | Evidence |
|---|---|---|
| Deployment verified | pending | S1 |
| Upgrade verified | pending | S4 |
| Rollback verified | pending | S8 |
| Diagnostics verified | pending | S3 |
| Provider validation complete | pending | OpenAI RC baseline |
| Release checklist complete | pending | S6 |

**Overall:** IN PROGRESS
