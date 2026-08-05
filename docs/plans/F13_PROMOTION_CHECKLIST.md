# F13 Promotion Checklist

Before promoting to **stage 6 (general availability)** on production:

1. [ ] F13.0 entry gate PASS ([F13_ENTRY_GATE.md](F13_ENTRY_GATE.md))
2. [ ] ADR-0013 disposition Accepted ([F13_ADR_DISPOSITION.md](F13_ADR_DISPOSITION.md))
3. [ ] PO-approved post types / languages documented in [F13_PO_DECISION_SHEET.md](F13_PO_DECISION_SHEET.md)
4. [ ] `general_rollout_enabled` planned value documented (default off until promote)
5. [ ] Metrics snapshot exported
6. [ ] Open incidents reviewed (SEV-1 = 0; SEV-2 under threshold)
7. [ ] Config snapshot saved (`wp aiml rollout config export`)
8. [ ] Operator has `aiml_promote_rollout`
9. [ ] `--yes` confirmation on CLI promote / config apply
10. [ ] Post-promotion smoke: allow for non-allowlisted posts in approved types/languages; deny for others
11. [ ] Two-level kill switches verified (master render + `rollout_render_enabled`)
12. [ ] Audit event verified
13. [ ] Feature coverage confirmed frozen (`paragraph` / `heading` / `button` only)
14. [ ] Observation / validation log updated

**Not automatic** — stage 6 promotion is always an explicit operator action.
