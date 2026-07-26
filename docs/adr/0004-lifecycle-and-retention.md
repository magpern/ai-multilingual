# ADR-0004 — Inert deactivation, all-or-nothing uninstall retention

## Status
Accepted (Milestone 0). Retention default confirmed by the product owner.

## Context
Translation work is expensive to recreate. It should never be destroyed by
someone toggling a plugin, and a partially removed install is worse than either
extreme — a reinstall that finds tables but no schema version, or translations
but no language configuration, cannot reason about what it is looking at.

## Decision
**Deactivation removes nothing.** No hook is registered, so there is nothing to
go wrong. Tables, rows, options, the schema version and the capability all
survive; the plugin simply stops registering hooks.

**Uninstall with `remove_data_on_uninstall` off (the default) is a no-op.**
Everything needed for a later reinstall is retained.

**Uninstall with the setting on removes everything the plugin created**: per-language
cache counters, the `aiml_translate` capability from every role holding it, all
`aiml_*` options, and all `aiml_*` tables. Canonical WordPress and WooCommerce
content is never touched under either branch.

## Consequences
- Reinstalling resumes exactly where it left off.
- Deleting translation data is a deliberate, explicit act.
- A structural test asserts nothing destructive appears before the retention
  guard in `uninstall.php`.
