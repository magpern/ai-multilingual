# F13 General Availability Runbook

Operational runbook for Strategy F **general availability** on Biopentra (dev/production).

**Feature coverage frozen:** `core/paragraph`, `core/heading`, `core/button` only (F12-proven set). Adapter expansion is **F14**.

## Preconditions

- F13 code deployed from `feature/f13-general-availability` (or merged release)
- F13.0 entry gate PASS — [F13_ENTRY_GATE.md](F13_ENTRY_GATE.md)
- ADR-0013 Accepted — [F13_ADR_DISPOSITION.md](F13_ADR_DISPOSITION.md)
- Administrator has `aiml_*` rollout capabilities
- Strategy F master render flag configured per F8 runbooks

## Configuration model (schema v2)

| Field | GA meaning |
|---|---|
| `general_rollout_enabled` | When `true`, cohort ignores `allowed_post_ids`; matches `allowed_post_types` ∩ `allowed_language_codes` |
| `rollout_stage` | Use **6** for general production (stages 0–5 remain F12 limited/shadow path) |
| `rollout_render_enabled` | Kill switch (must remain respected) |
| `render_cache_enabled` | Default **false** unless explicit GO recorded |

## Daily observation checklist

Review:

- rendered false positives (must remain 0)
- render failures and source fallback rate
- policy denials by `reason_code` (watch `policy_error`, `invalid_configuration`)
- metrics telemetry incomplete flag
- workspace errors / 409 / TM write-back
- open incidents (SEV-1 = stop)
- confirm `SUPPORTED_BLOCKS` unchanged (no accidental adapter expansion)

Record evidence in [F13_GENERAL_AVAILABILITY_VALIDATION_LOG.md](F13_GENERAL_AVAILABILITY_VALIDATION_LOG.md).

## Promotion checklist

See [F13_PROMOTION_CHECKLIST.md](F13_PROMOTION_CHECKLIST.md).

Typical promote to stage 6:

1. Metrics snapshot exported  
2. Open incidents reviewed  
3. Config snapshot saved (`wp aiml rollout config export`)  
4. Apply GA fields (`general_rollout_enabled=true`, approved languages/types)  
5. Operator has `aiml_promote_rollout`  
6. Promote to stage 6 with `--yes`  
7. Post-promotion smoke: multiple non-allowlisted posts × approved languages; deny for non-approved language/type  
8. Audit event verified  

## Emergency rollback order

See [F13_ROLLBACK_CHECKLIST.md](F13_ROLLBACK_CHECKLIST.md).

1. `wp aiml rollout emergency-stop --user=<id> --yes`  
2. Verify source frontend  
3. Optionally restore prior snapshot (increments `policy_version`)  
4. Preserve evidence — **never** delete UUIDs, Store, or TM rows  

## Cache activation decision

**Decision (F13.3):** `render_cache_enabled` remains **`false`** — no measured GO for general-availability traffic volume yet.

Activation requires a separate tech + PO GO after GA performance baselines are captured. Not implied by F13 implementation complete.

## Related

- Canonical plan: [STRATEGY_F_F13_GENERAL_ROLLOUT.md](STRATEGY_F_F13_GENERAL_ROLLOUT.md)
- PO values: [F13_PO_DECISION_SHEET.md](F13_PO_DECISION_SHEET.md)
- F12 limited runbook (prior): [F12_PRODUCTION_ROLLOUT_RUNBOOK.md](F12_PRODUCTION_ROLLOUT_RUNBOOK.md)
