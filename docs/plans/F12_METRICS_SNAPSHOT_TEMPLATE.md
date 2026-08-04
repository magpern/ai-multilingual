# F12 Metrics Snapshot Template

```json
{
  "captured_at": "",
  "operator_id": 0,
  "policy_version": 0,
  "rollout_stage": 0,
  "rollout_render_enabled": false,
  "render_cache_enabled": false,
  "hot_metrics": {},
  "metrics_registry_version": 1,
  "telemetry_incomplete": false,
  "reason_code_distribution": {},
  "notes": ""
}
```

Capture via:

```bash
wp --user=<id> aiml rollout status
```

Store sanitized snapshots only — no secrets, cookies, or PII.
