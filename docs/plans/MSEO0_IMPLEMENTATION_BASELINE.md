# MSEO.0 Implementation Baseline

**Branch:** `feature/mseo0-localized-url-foundation`  
**Starting main SHA:** `074a02b2834703415d49e59e5d3dfa454c3004dd`  
**Planning/freeze SHA:** `074a02b2834703415d49e59e5d3dfa454c3004dd`  
**ADR:** [0023-localized-url-overlay-architecture.md](../adr/0023-localized-url-overlay-architecture.md) (**Accepted**)  
**Implementation plan:** [MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md](MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md)  
**Plan review:** **MSEO.0 IMPLEMENTATION PLAN REVIEW: PASS** (R1–R7 applied)  
**Version at start:** **1.4.0**  
**STATE:** B  
**Migrator::TARGET:** **7** → **8**  
**M0AC:** M0AC1–M0AC20  
**WP ladder:** WP0–WP10  

## R1–R7 refinements (frozen)

| Ref | Summary |
|---|---|
| R1 | Exact TARGET 8 nullability/defaults; `activated_at` NULL |
| R2 | Repositories own path→hash; no independent semantic path/hash pairs |
| R3 | BINARY(32) SHA-256; `UNHEX(%s)` SQL boundary; NUL-byte tests |
| R4 | PathCanonicalizer path-only; no query; no `sanitize_title()` |
| R5 | Frontier: one row per parent; `checkpoint_json`; UNIQUE `parent_frontier` |
| R6 | TARGET literals audited individually; historical fixtures retained |
| R7 | EffectiveUrlService Settings-only; no optional test dependencies |

## Inert scope

TARGET 8 schema; PathHash; PathCanonicalizer; route/history/frontier repositories; EffectiveUrlService passthrough; `localized_urls_state=off` defaults.

## Hard exclusions

No localized URL admin UI; no Router/home_url wiring; no redirects; no rewrite rules; no post_name/term slug mutation; no provider slug calls; no route activation; no MSEO.1+; no version bump; no tag/release/deploy.

## STOP conditions

MSEO.1 NOT STARTED. Public URL behavior unchanged from v1.4.0.

## Next step

Implement WP1–WP10 on this branch; validate; independent review; PR; merge; closure.
