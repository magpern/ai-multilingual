# F13 Rollback Checklist

## Emergency order

1. [ ] Capture config export and metrics snapshot
2. [ ] `wp aiml rollout emergency-stop --user=<id> --yes`
3. [ ] Verify source frontend on previously allowed pages (multiple posts/languages)
4. [ ] Confirm `general_rollout_enabled` / stage restored as intended (or left off after emergency stop)
5. [ ] Disable render cache if it was enabled (`render_cache_enabled=false`)
6. [ ] **Do not** delete UUIDs, Store rows, or TM rows
7. [ ] Preserve evidence in incident log
8. [ ] Restore prior snapshot if planned rollback (increments `policy_version`)
9. [ ] Confirm expected safe state (source rendering; allowlist behavior if GA disabled)

## Rehearsal requirement

Before declaring F13 Definition of Done, execute at least one stage-6 → prior-stage (or snapshot restore) rehearsal through documented CLI/shared services and record PASS in [F13_GENERAL_AVAILABILITY_VALIDATION_LOG.md](F13_GENERAL_AVAILABILITY_VALIDATION_LOG.md).

## Notes

- Rollback must use `RolloutPromotionService` / `RolloutEmergencyService` / repository restore — not ad-hoc option edits.
- Capabilities unchanged from F12 (`aiml_emergency_rollback`, etc.).
