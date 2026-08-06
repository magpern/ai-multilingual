# Acceptance harnesses

Dev / staging / production-safe verification assets for AI Multilingual.
Prefer `wp eval-file` via WP-CLI. Never commit secrets or capture cleartext keys.

## P1 — Platform Stabilization

| Harness | Purpose |
|---|---|
| [`p1/health-probe.php`](p1/health-probe.php) | S0 inventory probe — routes, tables, jobs/glossary/providers/workspace |
| [`p1/deploy-verify.php`](p1/deploy-verify.php) | S1 production deployment verification |
| [`p1/diagnostics-smoke.php`](p1/diagnostics-smoke.php) | S3 operational diagnostics Q&A smoke |
| [`p1/schema-verify.php`](p1/schema-verify.php) | S4 schema TARGET 6 + optional upgrade simulation (`AIML_P1_SIMULATE_UPGRADE=1`) |

```bash
cd /opt/biopentra/apps/wordpress && docker compose run --rm -T wpcli \
  wp eval-file wp-content/plugins/ai-multilingual/acceptance/p1/deploy-verify.php
```

## Canonical provider validation baseline (Platform v1.x) — frozen

| Artifact | Role |
|---|---|
| [`rc/v1-openai-rc.php`](rc/v1-openai-rc.php) | Canonical OpenAI end-to-end RC harness |
| [`docs/plans/V1_RC_OPENAI_VALIDATION.md`](../docs/plans/V1_RC_OPENAI_VALIDATION.md) | Evidence / PASS record |

**Freeze:** This pair is the **canonical provider validation baseline** for Platform **v1.x**. Future release validation **must reference this baseline** whenever AI behaviour changes. Do **not** redesign the RC harness in routine work.

**Future providers:** Must satisfy the same provider-independent behavioural outcomes (connection/readiness, translate/suggest path, jobs path where applicable, failure isolation, no secrets in diagnostics). Transport may differ; acceptance expectations remain equivalent. Implementations belong to Program B.

## Other smoke / staging harnesses

| Path | Notes |
|---|---|
| `jobs/smoke-dev.php` | Background Jobs smoke |
| `review-workflow/smoke-dev.php` | Review Workflow smoke |
| `f9-browser/` … `f14-staging/` | Strategy F browser/staging matrices |

## Test tiers

| Tier | Default |
|---|---|
| **Tier 0** | PHPCS + PHPUnit unit + integration (+ PluginGuard) — every merge |
| **Tier 1–2** | Targeted Playwright |
| **Tier 3** | Full F9 matrix — milestone/release only |

Provider-dependent harnesses (OpenAI RC) require encrypted credentials and may incur paid API usage. Provider-independent harnesses (`acceptance/p1/*` except RC) must not call paid APIs.
