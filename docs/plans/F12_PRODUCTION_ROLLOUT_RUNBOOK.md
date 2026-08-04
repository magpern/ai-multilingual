# F12 Production Rollout Runbook

Operational runbook for Strategy F limited rollout on Biopentra dev/production.

## Preconditions

- F12 code deployed from `feature/f12-limited-rollout`
- Administrator has `aiml_*` rollout capabilities
- Strategy F master flags configured per F8 runbooks

## Daily observation checklist

Review:

- rendered false positives (must remain 0)
- render failures and source fallback rate
- policy denials by `reason_code`
- reason-code distribution stability (`policy_error`, `invalid_configuration`)
- metrics telemetry incomplete flag
- workspace errors / 409 / TM write-back
- provider failures and cost guardrails
- open incidents (SEV-1 = stop)

Record evidence in [F12_LIMITED_ROLLOUT_VALIDATION_LOG.md](F12_LIMITED_ROLLOUT_VALIDATION_LOG.md).

## Promotion checklist

1. Metrics snapshot exported  
2. Open incidents reviewed  
3. Config snapshot saved (`wp aiml rollout config export`)  
4. Operator has `aiml_promote_rollout`  
5. `--yes` confirmation  
6. Post-promotion smoke (cohort allow/deny/shadow)  
7. Audit event verified  

## Emergency rollback order

1. `wp aiml rollout emergency-stop --user=<id> --yes`  
2. Verify source frontend  
3. Preserve evidence — **never** delete UUIDs, Store, or TM rows  

## Rollback rehearsal

Required before F13: restore prior snapshot via promotion service; verify `policy_version` increment and source rendering.

## F13 entry gate

See [STRATEGY_F_F12_LIMITED_ROLLOUT.md](STRATEGY_F_F12_LIMITED_ROLLOUT.md) §20 — observation window and operator sign-off required.

**F12 status:** implementation complete; **observation pending** — not F13-ready.
