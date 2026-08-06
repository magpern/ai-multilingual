# Backup and restore posture

Operational confidence for plugin-owned data. This is **not** a backup product
and must not become a second source of truth.

Lifecycle: [ADR-0004](../adr/0004-lifecycle-and-retention.md) (inert deactivation;
all-or-nothing uninstall retention).

## What to back up

Include in site backups (database + optional object-cache awareness):

| Asset | Notes |
|---|---|
| `{prefix}aiml_languages` | Language configuration |
| `{prefix}aiml_translations` | Store segments + review columns |
| `{prefix}aiml_tm` | Translation Memory |
| `{prefix}aiml_glossary` | Glossary lexicon |
| `{prefix}aiml_jobs` / `{prefix}aiml_job_items` | Job orchestration (not canonical bodies) |
| `{prefix}aiml_metrics_daily` | Metrics aggregates |
| Options | `aiml_settings`, `aiml_db_version`, `aiml_cache_version`, `aiml_lang_version_*`, `aiml_glossary_version`, `aiml_rollout_config`, related audit/retention options |

Canonical WordPress / WooCommerce tables are **not** translation storage — back
them up with the rest of the site as usual.

## Uninstall retention

- Default `remove_data_on_uninstall` = **off** → uninstall is a no-op for data.
- When enabled, uninstall removes **all** plugin-owned state (all-or-nothing).
- **Forbidden:** deleting only `aiml_db_version`, or only some `aiml_*` tables,
  or settings while retaining translations.

## Restore order (guidance)

1. Restore WordPress core / site DB as required by the host backup tool.
2. Ensure plugin files match the backed-up schema era (Release ZIP).
3. Confirm `aiml_db_version` and tables; run `Migrator::maybe_migrate()` via
   activation or admin if files are newer (never invent TARGET bumps here).
4. Run `acceptance/p1/deploy-verify.php` and `schema-verify.php`.
5. Re-check encrypted provider credentials (re-enter if secrets were rotated).

## Restore rehearsal / tabletop (required for P1)

Record owner, date, and outcome in
[P1_PLATFORM_STABILIZATION_VALIDATION_LOG.md](../plans/P1_PLATFORM_STABILIZATION_VALIDATION_LOG.md).
A tabletop that walks the restore order against a known backup inventory is
acceptable when a full restore is not scheduled.
