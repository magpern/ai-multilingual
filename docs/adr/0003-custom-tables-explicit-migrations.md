# ADR-0003 — Custom tables with versioned explicit-SQL migrations

## Status
Accepted (Milestone 0).

## Context
Translation data is queried by language, status and staleness, and grows with
content times languages times segments. Post meta cannot be indexed for those
queries.

WordPress's usual schema tool is `dbDelta()`, but its parser is a
reimplementation of a subset of SQL: it silently drops composite prefix indexes
and misparses several forms this schema needs.

## Decision
Custom tables, created and altered by explicit `CREATE`/`ALTER` statements in an
ordered step map in `src/Database/Migrator.php`. The applied version lives in
its own option, `aiml_db_version`, separate from the settings array. Every step
is idempotent.

Migrations run from the activation hook **and** from an `admin_init` drift
check, because bind-mount deployments update files in place and never fire an
activation hook.

`$wpdb` is confined to `src/Database/*` and the store classes, always with
`prepare()` for DML and table names from `$wpdb->prefix`.

## Consequences
- Indexes are exactly what was written, including prefix indexes.
- Each schema change is an explicit, reviewable step.
- No SQL `ENUM`: statuses are `VARCHAR` plus a PHP constant, so adding a value
  never needs an `ALTER`.
