# TSC.3 Implementation Baseline

**Status:** Implementation baseline recorded — production coding authorized from this branch  
**Branch:** `feature/tsc3-woocommerce-extended-translation-surfaces`  
**Authoritative plan:** [TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md](TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md)  
**Planning validation:** [TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_PLANNING_VALIDATION_LOG.md](TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_PLANNING_VALIDATION_LOG.md)

## Baseline

| Field | Value |
|---|---|
| Starting `main` SHA | `924d383850aecb65e4589f2cf3d49b3398d74f6f` |
| Frozen plan SHA | `924d383850aecb65e4589f2cf3d49b3398d74f6f` (plan materialized in freeze commit) |
| Plugin version | **1.3.0** (unchanged) |
| `Migrator::TARGET` | **7** (unchanged) |
| Schema | **STATE A** — no migration |
| ADR | **None** |
| WC matrix | WC1–WC40 |
| AC matrix | AC1–AC38 |
| WP ladder | TSC3.0–TSC3.7 |
| TSC.0–TSC.2 | COMPLETE |
| TSC.3 implementation at baseline | NOT STARTED |
| TSC.4–TSC.6 | NOT STARTED |

## Frozen architecture reminders

- Canonical identity: `p:woocommerce:attribute:{attribute_id}:label`
- Shop page = technical Store host only
- Bounded shop-host rehost on `woocommerce_shop_page_id` change
- Taxonomy-backed product P5/P7 = compatibility read-only
- Product-local P5/P7 remains authoritative
- `pa_*` term values remain TSC.1
- IntegrationSegmentAuthority = facts/mechanics only; TI.7 owns publication policy
- Attribute-definition mutation requires `manage_product_terms`
- Email subject/heading stale = **PARTIAL**
- Variation machine identity never translated

## Explicit STOP boundaries

STOP rather than improvise if implementation requires:

- schema migration / TARGET bump / new `source_type` / second Store
- catalog-wide migration / permanent dual-write
- generic options translation framework
- translating variation machine values
- weakening TI.7 authority / turning IntegrationSegmentAuthority into a policy engine
- broad public registration APIs / site-specific production behavior
- material TSC.4+ implementation
- version bump / new tag / release / deploy

## Exact next steps on this branch

Implement TSC3.0–TSC3.7 per the authoritative plan, then validation, independent review, PR, merge, and closure.
