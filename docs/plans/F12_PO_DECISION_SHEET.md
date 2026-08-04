# F12 Product Owner Decision Sheet

Complete before starting the **production** limited-rollout observation window. Leave blank where not yet decided.

| Decision | Production value | Staging-only reference (WP10) | Approved by | Date |
|---|---|---|---|---|
| Stage 1–3 post IDs | _Pending PO_ | 6338 (deleted after WP10) | | |
| Target language(s) | _Pending PO_ | `sv` | | |
| Observation window duration | _Pending PO_ (plan proposes 14 days) | Short functional validation only | | |
| SEV-2 threshold | _Pending PO_ | — | | |
| AI daily warning limit | _Pending PO_ | — | | |
| AI hard limit (optional) | _Pending PO_ | — | | |
| Cache activation (`render_cache_enabled`) | _Pending PO_ — default **false** | false (measured GO not approved) | | |
| Capability-role mapping | _Pending PO_ | administrator granted via `RolloutCapabilities::grant_default_roles()` | | |
| Named operator (user ID + login) | _Pending PO_ | bp_manager (ID 1) for staging only | | |

## Sign-off

| Role | Name | Signature / date |
|---|---|---|
| Product owner | | |
| Technical owner | | |
| Operator | | |

**F12 merge/tag gate:** Observation window must pass with rendered false positives = 0 and PO sign-off above.
