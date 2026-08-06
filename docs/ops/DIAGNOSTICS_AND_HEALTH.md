# Diagnostics and health — operator Q&A

Answer these questions using **existing** REST/CLI only. Do **not** invent a
second diagnostics product. Never paste API keys, Authorization headers,
prompts, translation bodies, or full ciphertext into tickets.

P1 engineering smoke: `acceptance/p1/diagnostics-smoke.php`.

Action Scheduler note: when `DISABLE_WP_CRON` is true, ensure host cron
(`scripts/wp-cron.sh` or equivalent) runs so the `aiml-jobs` AS group progresses.

---

## Is AI functioning?

| Check | How |
|---|---|
| Enabled + provider | Settings: `ai_enabled`, `ai_provider`, `ai_model` (admin UI or sanitized options) |
| Key vaulted | `ai_api_key_encrypted` non-empty; never print value — record length only |
| Connection | `GET /wp-json/aiml/v1/providers/active` then admin **test-connection** (`POST .../providers/test-connection`) with `manage_options` |
| Behavioural E2E | Canonical RC: `acceptance/rc/v1-openai-rc.php` (when AI behaviour changes) |

## Are Background Jobs functioning?

| Check | How |
|---|---|
| Health | `GET /wp-json/aiml/v1/jobs/health` |
| Diagnostics | `GET /wp-json/aiml/v1/jobs/diagnostics` (bounded; no secrets) |
| CLI | `wp aiml jobs list` |
| AS | `wp action-scheduler list --group=aiml-jobs` |
| Runbook | [BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md](BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md) |

## Is Review functioning?

| Check | How |
|---|---|
| Diagnostics | `GET /wp-json/aiml/v1/workspace/review-diagnostics?post_id=&language=` (`aiml_review_translations`) |
| Queue | `GET /wp-json/aiml/v1/workspace/review-queue?...` |
| Caps | Reviewer role has `aiml_review_translations` |

## Is Translation Memory functioning?

| Check | How |
|---|---|
| Table present | `{prefix}aiml_tm` exists (schema verify / health probe) |
| Suggest path | Workspace suggest on a segment (`POST .../segments/{key}/suggest`) — uses sole `TranslationSuggestionService` |
| Write-back policy | Approval-gated when Review Workflow enabled (ADR-0015); never expect TM write on pure `machine_translated` |

## Is Glossary functioning?

| Check | How |
|---|---|
| Diagnostics | `GET /wp-json/aiml/v1/glossary/diagnostics` |
| List | `GET /wp-json/aiml/v1/glossary` (`aiml_manage_glossary`) |
| Admin | Multilingual → Glossary |

## Is Rollout functioning?

| Check | How |
|---|---|
| Config | Option `aiml_rollout_config` / GA flag `general_rollout_enabled` readable |
| Block flags | Settings block registration / injection / extraction / frontend rendering |
| Status CLI | `wp aiml block status` |
| Kill switch | Disable frontend rendering / use F12–F13 rollback checklists |

## Are providers healthy?

| Check | How |
|---|---|
| Active | `GET /wp-json/aiml/v1/providers/active` |
| Test connection | Provider admin test-connection (no key in response) |
| Models | `GET /wp-json/aiml/v1/providers/models` when supported |

---

## Secret redaction rules

- Log **lengths** and **boolean readiness**, never cleartext or full `aiml1:` blobs.
- Diagnostics payloads must not contain `sk-…`, `Bearer …`, prompts, or segment bodies.
- Validation logs may record `key_len=N` and `prefix=aiml1…` only.
