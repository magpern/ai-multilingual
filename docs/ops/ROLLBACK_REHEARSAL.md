# Rollback rehearsal notes (P1)

Primary kill switches and ZIP rollback for Platform v1.0.x. Detailed checklists:

- [F12_ROLLBACK_CHECKLIST.md](../plans/F12_ROLLBACK_CHECKLIST.md)
- [F13_ROLLBACK_CHECKLIST.md](../plans/F13_ROLLBACK_CHECKLIST.md)
- [BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md](BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md)

## Kill-switch order (visitor safety)

1. Disable **frontend block rendering** (`block_frontend_rendering_enabled`) — primary
2. Disable extraction / injection flags if needed (F8 order reverse)
3. Pause / cancel background jobs; confirm AS group quiet
4. Adjust rollout / GA (`general_rollout_enabled`, allowlists) per F12/F13
5. ZIP rollback to prior Release only if file-level defect; do not partially drop schema

## P1 rehearsal record

See validation log S8 section. Rehearsal confirms operators know the switches and
that deploy-verify still reports readable flags after review.
