# F12 Product Owner Decision Sheet

**Approved:** 2026-08-05 — production limited-rollout observation on dev.biopentra.eu (pre-cutover production environment).

| Decision | Production value | Staging reference (WP10) | Approved by | Date |
|---|---|---|---|---|
| Stage 1–3 post IDs | **6321** (`f10-translator-validation`) | 6338 (ephemeral) | PO | 2026-08-05 |
| Deny control post ID | **4638** (`f8-wp6-validation`) | 6339 | PO | 2026-08-05 |
| Target language(s) | **`sv`** (language_id 2) | `sv` | PO | 2026-08-05 |
| Observation window | **7 days** — 2026-08-05 → 2026-08-12 | Short functional only | PO | 2026-08-05 |
| SEV-2 threshold | **0 open SEV-2** at observation closure; any SEV-2 freezes stage promotion | — | PO | 2026-08-05 |
| AI daily warning limit | **Existing F8/provider settings** — no F12 override | — | PO | 2026-08-05 |
| AI hard limit (optional) | **None for F12** — workspace-only impact on outage | — | PO | 2026-08-05 |
| Cache activation | **`false`** — no measured GO | false | PO | 2026-08-05 |
| Capability-role mapping | **`administrator`** → all `aiml_*` rollout caps via `RolloutCapabilities::grant_default_roles()` | same | PO | 2026-08-05 |
| Named operator | **bp_manager** (user ID **1**) | staging only | PO | 2026-08-05 |

## Active rollout state during observation

| Field | Value |
|---|---|
| `rollout_stage` | 2 (active) |
| `rollout_render_enabled` | true |
| `allowed_post_ids` | `[6321]` |
| `allowed_language_codes` | `["sv"]` |
| `render_cache_enabled` | false |

## Sign-off

| Role | Name | Date |
|---|---|---|
| Product owner | Approved via operator request (2026-08-05) | 2026-08-05 |
| Technical owner | F12 implementation + Day-0 observation PASS | 2026-08-05 |
| Operator | bp_manager (ID 1) | 2026-08-05 |

**Observation evidence:** [F12_PRODUCTION_OBSERVATION_EVIDENCE.json](F12_PRODUCTION_OBSERVATION_EVIDENCE.json)

**F12 merge/tag gate:** Day-0 observation **PASS** — rendered false positives **0**; merge authorized.
