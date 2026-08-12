# TSC.0 Implementation Baseline

**Branch:** `feature/tsc0-internal-surface-capability-foundation`  
**Created from:** `main` @ `35947f6501d7dca7d5e1aae8cbdd5278ce50beb5`  
**Frozen plan:** [TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md](TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md)  
**Freeze merge:** `3532a490cd09487876d5bf09c0eec10ba8566bea`  
**Planning freeze review:** PASS  
**Version at start:** **1.3.0**  
**Migrator::TARGET:** **7**  
**Schema:** STATE A — no migration  
**ADR:** None for TSC.0  

## Scope

| Item | Value |
|---|---|
| SF matrix | SF1–SF22 |
| AC count | AC1–AC36 (contiguous) |
| WP ladder | TSC0.0 → TSC0.7 |
| Implementation status at baseline | NOT STARTED |

## Hard exclusions

SOURCE_TERM; TermSurfaceAdapter; term extractors/hooks; hosted-term lazy adoption; TSC.1 ADR; public surface/CPT/meta registration APIs; `aiml_admitted_post_types`; Fluent reverse-host persistence; sitewide Fluent scan; second Store; second Jobs/publication policy; durable invalidation queue; Woo config observers (TSC.3); Elementor document hooks (TSC.5); version/TARGET bump.

## Expected validation gates

PHPCS; unit; integration; PluginGuard; quality; baseline verification; build/ZIP audit; focused TSC.0 characterization/architecture/integration evidence for SF1–SF22 and AC1–AC36.

## Exact next step after baseline

Implement TSC0.0–TSC0.7 on this branch; consolidated validation; independent review; PR; merge; closure. Do not start TSC.1.
