# F13 Product Owner Decision Sheet

**Approved for engineering / staging GA validation:** 2026-08-05  
**Environment:** `dev.biopentra.eu` (pre-cutover production environment)

| Decision | Value | Approved by | Date |
|---|---|---|---|
| Approved post types | `post`, `page` | PO / operator authorization | 2026-08-05 |
| Approved language(s) for GA validation | **`sv`** (language_id 2) — same as F12 observation | PO | 2026-08-05 |
| Target stage for GA | **6** | Plan | 2026-08-05 |
| `general_rollout_enabled` | **`true`** only during controlled GA validation / promotion; default **`false`** | Plan | 2026-08-05 |
| Deny controls | Non-approved language and/or non-approved post type must remain source | Plan | 2026-08-05 |
| SEV-2 threshold | **0** open SEV-2 at promotion / DoD | PO (inherited F12) | 2026-08-05 |
| Cache activation | **`false`** — deferred; no measured GO | F13.3 | 2026-08-05 |
| Capability-role mapping | `administrator` → all `aiml_*` via `grant_default_roles()` | F12 | 2026-08-05 |
| Named operator | **bp_manager** (user ID **1**) | F12 | 2026-08-05 |
| Feature coverage | Frozen: `core/paragraph`, `core/heading`, `core/button` | F13 plan | 2026-08-05 |

## Active GA validation shape (staging)

| Field | Value |
|---|---|
| `schema_version` | 2 |
| `rollout_stage` | 6 |
| `rollout_render_enabled` | true |
| `general_rollout_enabled` | true |
| `allowed_post_types` | `["post","page"]` |
| `allowed_language_codes` | `["sv"]` |
| `allowed_post_ids` | ignored when GA on (may be empty or residual) |
| `render_cache_enabled` | false |

## Cache activation decision (explicit)

**Deferred.** Keep `render_cache_enabled=false` through F13 unless a later measured tech+PO GO is recorded. F12 WP8 implemented cache default-off; F13 does not activate it by default.

## Sign-off

| Role | Name | Date |
|---|---|---|
| Product / operator | F13 implementation authorization | 2026-08-05 |
| Technical owner | F13.2 GA cohort model on branch | 2026-08-05 |

**Canonical plan:** [STRATEGY_F_F13_GENERAL_ROLLOUT.md](STRATEGY_F_F13_GENERAL_ROLLOUT.md)  
**Runbook:** [F13_GENERAL_AVAILABILITY_RUNBOOK.md](F13_GENERAL_AVAILABILITY_RUNBOOK.md)
