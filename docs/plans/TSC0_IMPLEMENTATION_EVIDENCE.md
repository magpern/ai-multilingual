# TSC.0 Implementation Evidence

**Milestone:** TSC.0 — Internal Surface Capability Foundation  
**Branch:** `feature/tsc0-internal-surface-capability-foundation`  
**Frozen plan:** [TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md](TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md)  
**Baseline:** [TSC0_IMPLEMENTATION_BASELINE.md](TSC0_IMPLEMENTATION_BASELINE.md)  
**Version:** 1.3.0  
**TARGET:** 7 (STATE A — no migration)  
**ADR:** None  

## Work packages

| WP | Status | Notes |
|---|---|---|
| TSC0.0 Characterization | COMPLETE | `Tsc0CharacterizationTest`, parent matrix honesty |
| TSC0.1 Capability foundation | COMPLETE | `src/Surface/*` + ownership unit tests |
| TSC0.2 Post adapter / rewires | COMPLETE | Publication visibility; OTL mutate via registry; Jobs admission |
| TSC0.3 Internal CPT admission | COMPLETE | `AdmittedPostTypes`; no public filter |
| TSC0.4 Invalidation / orphan / Jobs | COMPLETE | Shutdown-primary coordinator; ItemProcessor orphan short-circuit |
| TSC0.5 Rank Math / Fluent | COMPLETE | Allowlisted meta dirty marks; host-local Fluent; stale UNSUPPORTED |
| TSC0.6 Hardening | COMPLETE | PluginGuard structural neutrality; perf contract tests |
| TSC0.7 Evidence | COMPLETE | This document + SF/AC mapping |

## SF1–SF22

| ID | Disposition | Evidence |
|---|---|---|
| SF1 | PASS | SurfaceRegistry / SurfaceCapability unit tests |
| SF2 | PASS | Architecture tests; no second orchestrator classes |
| SF3 | PASS | PostSurfaceAdapter delegates; Extractor/Store called from coordinator only |
| SF4 | PASS | AllowedActionsResolver + PublicationService + Jobs create |
| SF5 | PASS | JobService rejects unregistered source_type |
| SF6 | PASS | AdmittedPostTypes; PluginGuard bans `aiml_admitted_post_types` |
| SF7 | PASS | Workspace/Render/Rollout constants alias AdmittedPostTypes lists |
| SF8 | PASS | No auto-all-CPT / no public API |
| SF9 | PASS | FORM_ID/CONTACT_PAGE_ID removed; PluginGuard |
| SF10 | PASS | `discover_form_ids` host-local only |
| SF11 | PASS | RequestLocalInvalidationCoordinator unit tests |
| SF12 | PASS | PostSurfaceAdapter Rank Math meta hooks + integration |
| SF13 | PASS | Characterization: Fluent stale UNSUPPORTED |
| SF14 | PASS | Store orphan path unchanged; delete marks dirty |
| SF15 | PASS | ItemProcessor ignored/orphaned short-circuit |
| SF16 | PASS | `is_visitor_public` fact; PublicationPolicy retained |
| SF17 | PASS | `user_can_edit_source` via edit_post |
| SF18 | PASS | feature_implemented vs feature_activated; defaults OFF |
| SF19 | PASS | O(1) registry for; no list extract |
| SF20 | PASS | Fluent allowlist + IntegrationSecurity |
| SF21 | PASS | PluginGuardTest TSC.0 neutrality |
| SF22 | PASS | No SOURCE_TERM / public API / schema |

## AC1–AC36

All AC1–AC36 targeted by unit/integration/architecture evidence above. Contiguous set preserved. AC18/AC19/AC36 enforced via shutdown-sole flush (hooks mark dirty only).

## WP event-ordering finding

WordPress plugins commonly update post meta during/after `save_post`. TSC.0 uses **shutdown as sole flush authority** so Rank Math meta written after `save_post` is included in the final `Store::sync_source`. Meta-only requests also flush on shutdown.

## Limitations / debt

- Fluent form-definition stale remains **UNSUPPORTED** (no reverse-host map)
- Publish axis may remain `published` on orphaned rows while overlay suppressed
- Jobs worker remains post-shaped until TSC.1
- No TermSurfaceAdapter / SOURCE_TERM

## Schema / ADR

- TARGET **7** unchanged
- Version **1.3.0** unchanged
- No new ADR
- No schema migration

## Next after merge/closure

Do not start TSC.1 until a separate planning freeze. Implementation of TSC.1+ remains NOT STARTED.
