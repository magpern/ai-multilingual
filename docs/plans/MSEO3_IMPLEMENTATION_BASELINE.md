# MSEO.3 Implementation Baseline

| Item | Value |
|---|---|
| Starting main (pre-freeze) | `c4556506c8f72fad39c38ba3f1033c29f51c2c59` |
| Freeze / materialization SHA | `3b1cff2e429b6cf544b6ec5d75e4936d77218612` |
| Plan | [MSEO3_HIERARCHICAL_PAGES_TERMS_TAXONOMY_LOCALIZED_URLS_IMPLEMENTATION_PLAN.md](MSEO3_HIERARCHICAL_PAGES_TERMS_TAXONOMY_LOCALIZED_URLS_IMPLEMENTATION_PLAN.md) |
| ADR-0023 | Accepted |
| Version | 1.4.0 (do not bump) |
| TARGET | 8 (no migration) |
| STATE | B |
| Requirements | M3R1–M3R56 |
| Acceptance | M3AC1–M3AC50 |
| Work packages | MSEO3.0–MSEO3.6 |
| First term public | End MSEO3.2 after `term_archive` admission |
| First hierarchy public | End MSEO3.4 after `page_hierarchical` admission |
| Branch | `feature/mseo3-hierarchy-terms-taxonomy-localized-urls` |

## Explicit exclusions

- Translated rewrite bases
- `%product_cat%` product permalinks (MSEO.4)
- Woo endpoint localization
- Atomic subtree generation / TARGET 9
- Provider slug generation
- Tag / release / deploy
- MSEO.4

## STOP conditions

TARGET 9, schema migration necessity, ADR-0023 contradiction, rewrite-rule requirement → **MSEO.3 ARCHITECTURE REOPEN REQUIRED**.
