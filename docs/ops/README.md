# AI Multilingual — Operations index

Operator-facing runbooks and verification for Platform **v1.0.x**.

Long-term product planning: [POST_V1_PLATFORM_ROADMAP.md](../plans/POST_V1_PLATFORM_ROADMAP.md).  
Active stabilization plan: [P1_PLATFORM_STABILIZATION_IMPLEMENTATION_PLAN.md](../plans/P1_PLATFORM_STABILIZATION_IMPLEMENTATION_PLAN.md).

## Runbooks

| Document | Purpose |
|---|---|
| [BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md](BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md) | Jobs health, pause/resume/cancel, budgets, AS, retention |

Additional P1 ops docs (diagnostics, backup, rollback, maintenance) are added in later work packages and linked here when present.

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
```

Further harnesses (`schema-verify.php`, `diagnostics-smoke.php`) land in S3–S4.

OpenAI behavioural baseline (when AI changes): `acceptance/rc/v1-openai-rc.php`.

Deployment procedures: [DEPLOYMENT.md](../DEPLOYMENT.md) (Platform v1.0.0 section).  
REST catalogue: [HOOKS.md](../HOOKS.md).

**Secrets:** Never paste API keys, Authorization headers, prompts, or full ciphertext into tickets or validation logs—record lengths / redacted prefixes only.
