# MSEO.2 Implementation Baseline

| Item | Value |
|---|---|
| Frozen main SHA | `feb2658da878ef955a5d44cb36995f98393c0bf2` |
| Starting main (pre-freeze) | `88ac74cc3b1320af5359b190b32cacaf79f92bff` |
| Plan | [MSEO2_PUBLIC_LOCALIZED_URL_ROUTING_SEO_GRAPH_IMPLEMENTATION_PLAN.md](MSEO2_PUBLIC_LOCALIZED_URL_ROUTING_SEO_GRAPH_IMPLEMENTATION_PLAN.md) |
| ADR-0023 | Accepted |
| Version | 1.4.0 (do not bump) |
| TARGET | 8 (no migration) |
| STATE | B |
| Requirements | M2R1–M2R54 |
| Acceptance | M2AC1–M2AC55 |
| Work packages | MSEO2.0–MSEO2.6 |
| First-activation boundary | End of MSEO2.5 (admin toggle only then) |
| Branch | `feature/mseo2-public-localized-url-routing` |

## Explicit exclusions

- Hierarchy / terms / Woo category permalink localization (MSEO.3/4)
- Rewrite rules / `post_name` / term slug mutation
- Provider FORMAT_SLUG generation
- Activation auto-publish of editorial candidates
- Tag / release / deploy
- MSEO.3

## STOP conditions

TARGET 9, schema migration, ADR-0023 contradiction, public routing for deferred shapes → **MSEO.2 ARCHITECTURE REOPEN REQUIRED**.
