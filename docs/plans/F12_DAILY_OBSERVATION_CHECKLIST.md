# F12 Daily Observation Checklist

Use during the **production** limited-rollout observation window only. Staging values from WP10 are **not** PO-approved cohorts.

## Before each review period

- [ ] Export rollout config: `wp aiml rollout config export --user=<operator_id>`
- [ ] Export metrics snapshot (see [F12_METRICS_SNAPSHOT_TEMPLATE.md](F12_METRICS_SNAPSHOT_TEMPLATE.md))
- [ ] Confirm `render_cache_enabled=false` unless explicit measured GO on file
- [ ] Confirm `rollout_render_enabled` matches approved stage

## During observation

- [ ] Rendered false positives = **0** (spot-check allowlist + deny pages)
- [ ] Source fallback on deny/shadow/kill-switch paths
- [ ] Policy denials by `reason_code` stable (no spike in `policy_error` / `invalid_configuration`)
- [ ] Hot metrics updating; daily rollup present when enabled
- [ ] Workspace errors / 409 / TM write-back within normal range
- [ ] Provider failures affect workspace only; public frontend operational
- [ ] Open incidents logged ([F12_INCIDENT_LOG_TEMPLATE.md](F12_INCIDENT_LOG_TEMPLATE.md))

## Escalation

- **SEV-1:** Emergency stop immediately — [F12_ROLLBACK_CHECKLIST.md](F12_ROLLBACK_CHECKLIST.md)
- **SEV-2:** Threshold **pending PO** — record in incident log; do not invent threshold

## Sign-off

Record date, operator, and evidence path in [F12_LIMITED_ROLLOUT_VALIDATION_LOG.md](F12_LIMITED_ROLLOUT_VALIDATION_LOG.md).
