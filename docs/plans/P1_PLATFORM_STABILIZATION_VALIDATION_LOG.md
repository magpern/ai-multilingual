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
**Command:**

```bash
cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/deploy-verify.php
```

**Result:**

| Field | Value |
|---|---|
| Date | 2026-08-06 |
| Exit code | 0 |
| Summary | **16/16 PASS** (AIML 1.0.0, schema 6, rollout readable, OpenAI vaulted key, jobs/workspace/providers healthy) |

---

## S2 — Ops/docs alignment

**Result:** **PASS** (2026-08-06)

- `docs/HOOKS.md` — Jobs REST family documented; allowlist includes `JobsController`
- `docs/DEPLOYMENT.md` — Platform v1.0.0 deploy/upgrade/rollback section
- `docs/ops/README.md` — updated verification commands
- Live probes from S0/S1 already exercised the documented Jobs/Workspace/Glossary/Provider routes

---

## S3 — Diagnostics

**Docs:** `docs/ops/DIAGNOSTICS_AND_HEALTH.md`  
**Harness:** `acceptance/p1/diagnostics-smoke.php`  
**Result:** **12/12 PASS** (2026-08-06) — AI/providers, jobs, review, TM table, glossary, rollout; no secrets in payloads.

## S4 — Schema verify

**Harness:** `acceptance/p1/schema-verify.php`  
**Command (with upgrade simulation):**

```bash
cd /opt/biopentra/apps/wordpress && docker compose run --rm -T -e AIML_P1_SIMULATE_UPGRADE=1 wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/schema-verify.php
```

**Result:** **14/14 PASS** (2026-08-06) — TARGET 6, all tables, review_status column, glossary version option, maybe_migrate noop, simulated 5→6 upgrade.

---

## S5 — Acceptance index + RC baseline

**Result:** **PASS** (2026-08-06)

- `acceptance/README.md` indexes P1 harnesses + freezes OpenAI RC as v1.x provider baseline
- Future-provider equivalent-outcomes philosophy documented
- Evidence: [V1_RC_OPENAI_VALIDATION.md](V1_RC_OPENAI_VALIDATION.md) prior **56/56 PASS** remains canonical baseline (no RC redesign; no paid re-run required for non-AI-change P1)

## S6 — Release checklist

**Result:** **PASS** (2026-08-06)

- [P1_RELEASE_VALIDATION_CHECKLIST.md](P1_RELEASE_VALIDATION_CHECKLIST.md) published with Tier 0, zip rules, P1 harnesses, and RC gate when AI changes
- Dry-run recorded in checklist (no new production tag)

## S7 — Backup / restore rehearsal

**Result:** **PASS** (2026-08-06) — tabletop

| Field | Value |
|---|---|
| Date | 2026-08-06 |
| Owner | engineering (P1 implementation) |
| Type | Tabletop against [BACKUP_AND_RESTORE.md](../ops/BACKUP_AND_RESTORE.md) |
| Outcome | Inventory of `aiml_*` tables/options confirmed via schema-verify; restore order and forbidden partial deletes reviewed; full host restore not scheduled |

## S8 — Rollback rehearsal

**Result:** **PASS** (2026-08-06) — tabletop + flag readability

| Field | Value |
|---|---|
| Date | 2026-08-06 |
| Owner | engineering (P1 implementation) |
| Type | Tabletop walkthrough of [ROLLBACK_REHEARSAL.md](../ops/ROLLBACK_REHEARSAL.md) + F12/F13 checklists |
| Evidence | `deploy-verify.php` confirmed block render flag and rollout config readable; primary kill switch = frontend rendering / rollout flags; Jobs pause/cancel per existing runbook |
| Note | Production flags were **not** flipped on the shared dev site during rehearsal (visitor safety); procedure validated as operable |

## S9 — Operational readiness sign-off

| Sign-off item | Status | Evidence |
|---|---|---|
| Deployment verified | **PASS** | S1 `deploy-verify.php` 16/16 |
| Upgrade verified | **PASS** | S4 `schema-verify.php` 14/14 with simulate |
| Rollback verified | **PASS** | S8 tabletop + flag readability |
| Diagnostics verified | **PASS** | S3 Q&A + `diagnostics-smoke.php` 12/12 |
| Provider validation complete | **PASS** | Canonical OpenAI RC baseline frozen; prior 56/56 PASS cited (AI behaviour unchanged in P1) |
| Release checklist complete | **PASS** | S6 checklist dry-run |

Supporting: [V1_0_X_MAINTENANCE.md](../ops/V1_0_X_MAINTENANCE.md)

**Overall:** **PASS** — P1 Platform Stabilization complete on `feature/p1-platform-stabilization`
