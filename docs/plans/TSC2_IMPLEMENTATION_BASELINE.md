# TSC.2 Implementation Baseline

**Status:** Implementation in progress on `feature/tsc2-registered-meta-surfaces`
**Authoritative plan:** [TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md](TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md)
**Planning validation:** [TSC2_REGISTERED_META_SURFACES_PLANNING_VALIDATION_LOG.md](TSC2_REGISTERED_META_SURFACES_PLANNING_VALIDATION_LOG.md)

## Starting point

| Field | Value |
|---|---|
| Starting main HEAD | `e8f2341b634b99655c2f42a31b24eae570f7dd91` |
| Freeze merge | `51be1f0aa771261c3d7e44d2ea891da7bb9ffcd1` |
| Version | **1.3.0** |
| `Migrator::TARGET` | **7** |
| Schema | **STATE A** — no migration |
| ADR | **None** |
| TSC.0 / TSC.1 | COMPLETE |
| TSC.2 planning | Architecture Frozen · PASS |
| Production implementation before this branch | NOT STARTED |

## Matrices (frozen)

| Matrix | IDs |
|---|---|
| RM | RM1–RM34 |
| AC | AC1–AC32 |
| WP | TSC2.0–TSC2.7 |

## Authority owners (must not steal)

| Concern | Owner |
|---|---|
| Source admission / existence / edit / publicness | SurfaceCapability + Admitted\* |
| Jobs policy | TI.6 |
| Publication | TI.7 |
| OTL mutate | OTL |
| Field catalog (keys, identity, extract/provider/overlay facts) | RegisteredMetaRegistry |
| Rank Math `p:` / literal / social / overlay / sitemap | RankMathIntegration |
| Segment persistence / orphan / retain | Store |

## Deferred / Unsupported (do not implement)

- Bounded structured meta paths (Deferred)
- Gutenberg (TSC.4), Elementor (TSC.5), public API (TSC.6)
- Woo economic meta, ACF wildcards, options/theme_mods/usermeta
- Generic `filter:{hook}` overlay engine
- `SOURCE_META`, schema/TARGET bump, durable registration table

## STOP conditions

- CASE B requires durable registration schema → STOP
- Need SOURCE_META / public register API / translated meta writes → STOP
- Rank Math dual `m:`+`p:` emission → STOP and fix

## Work package intent

Implement TSC2.0–TSC2.7 exactly per frozen plan; no opportunistic scope expansion.
