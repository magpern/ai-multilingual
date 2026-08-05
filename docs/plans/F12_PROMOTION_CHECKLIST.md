# F12 Promotion Checklist

Before promoting rollout stage on **production**:

1. [ ] PO-approved cohort post IDs documented in [F12_PO_DECISION_SHEET.md](F12_PO_DECISION_SHEET.md)
2. [ ] PO-approved target language(s) documented
3. [ ] Metrics snapshot exported ([F12_METRICS_SNAPSHOT_TEMPLATE.md](F12_METRICS_SNAPSHOT_TEMPLATE.md))
4. [ ] Open incidents reviewed ([F12_INCIDENT_LOG_TEMPLATE.md](F12_INCIDENT_LOG_TEMPLATE.md))
5. [ ] Config snapshot saved (`wp aiml rollout config export`)
6. [ ] Operator has `aiml_promote_rollout`
7. [ ] `--yes` confirmation on CLI promote
8. [ ] Post-promotion smoke: allow / deny / shadow for approved cohort
9. [ ] Audit event verified
10. [ ] Observation checklist updated ([F12_DAILY_OBSERVATION_CHECKLIST.md](F12_DAILY_OBSERVATION_CHECKLIST.md))

**Not for staging WP10 values** — production PO approval required.
