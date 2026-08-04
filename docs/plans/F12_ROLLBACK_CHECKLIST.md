# F12 Rollback Checklist

## Emergency order

1. [ ] Capture config export and metrics snapshot
2. [ ] `wp aiml rollout emergency-stop --user=<id> --yes`
3. [ ] Verify source frontend on cohort + deny pages
4. [ ] Disable render cache if it was enabled (`render_cache_enabled=false`)
5. [ ] **Do not** delete UUIDs, Store rows, or TM rows
6. [ ] Preserve evidence in incident log
7. [ ] Restore prior snapshot if planned rollback (increments `policy_version`)
8. [ ] Confirm expected safe state

## Rehearsal evidence

Staging rehearsal **PASS** on 2026-08-04 — see [F12_LIMITED_ROLLOUT_VALIDATION_LOG.md](F12_LIMITED_ROLLOUT_VALIDATION_LOG.md) and [F12_WP10_STAGING_EVIDENCE.json](F12_WP10_STAGING_EVIDENCE.json).

## Production observation rollback

Requires named operator and PO-approved cohort values — see [F12_PO_DECISION_SHEET.md](F12_PO_DECISION_SHEET.md).
