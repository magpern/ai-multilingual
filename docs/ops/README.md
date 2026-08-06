# AI Multilingual — Operations index

Operator-facing runbooks and verification for Platform **v1.0.x**.

Long-term product planning: [POST_V1_PLATFORM_ROADMAP.md](../plans/POST_V1_PLATFORM_ROADMAP.md).
Active stabilization plan: [P1_PLATFORM_STABILIZATION_IMPLEMENTATION_PLAN.md](../plans/P1_PLATFORM_STABILIZATION_IMPLEMENTATION_PLAN.md).
Validation log: [P1_PLATFORM_STABILIZATION_VALIDATION_LOG.md](../plans/P1_PLATFORM_STABILIZATION_VALIDATION_LOG.md).
Release checklist: [P1_RELEASE_VALIDATION_CHECKLIST.md](../plans/P1_RELEASE_VALIDATION_CHECKLIST.md).

## Runbooks

| Document | Purpose |
|---|---|
| [BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md](BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md) | Jobs health, pause/resume/cancel, budgets, AS, retention |
| [DIAGNOSTICS_AND_HEALTH.md](DIAGNOSTICS_AND_HEALTH.md) | Operational “is X functioning?” Q&A |
| [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md) | Plugin-owned data backup/restore posture |
| [ROLLBACK_REHEARSAL.md](ROLLBACK_REHEARSAL.md) | Kill-switch / rollback rehearsal notes |
| [V1_0_X_MAINTENANCE.md](V1_0_X_MAINTENANCE.md) | Standing v1.0.x maintenance cadence |

## Related Strategy F / GA checklists

| Document | Purpose |
|---|---|
| [F12_PRODUCTION_ROLLOUT_RUNBOOK.md](../plans/F12_PRODUCTION_ROLLOUT_RUNBOOK.md) | Limited rollout ops |
| [F12_ROLLBACK_CHECKLIST.md](../plans/F12_ROLLBACK_CHECKLIST.md) | Rollout rollback |
| [F13_GENERAL_AVAILABILITY_RUNBOOK.md](../plans/F13_GENERAL_AVAILABILITY_RUNBOOK.md) | GA ops |
| [F13_ROLLBACK_CHECKLIST.md](../plans/F13_ROLLBACK_CHECKLIST.md) | GA rollback |

## Engineering verification (P1)

```bash
cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/health-probe.php

cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/deploy-verify.php

cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/diagnostics-smoke.php

cd /opt/biopentra/apps/wordpress && docker compose run --rm -T -e AIML_P1_SIMULATE_UPGRADE=1 wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/schema-verify.php
```

Harness index: [acceptance/README.md](../../acceptance/README.md).

OpenAI behavioural baseline (when AI changes): `acceptance/rc/v1-openai-rc.php` +
[V1_RC_OPENAI_VALIDATION.md](../plans/V1_RC_OPENAI_VALIDATION.md).

Deployment procedures: [DEPLOYMENT.md](../DEPLOYMENT.md) (Platform v1.0.0 section).
REST catalogue: [HOOKS.md](../HOOKS.md).

**Secrets:** Never paste API keys, Authorization headers, prompts, or full ciphertext into tickets or validation logs—record lengths / redacted prefixes only.
