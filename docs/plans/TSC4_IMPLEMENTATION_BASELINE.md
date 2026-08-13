# TSC.4 Implementation Baseline

**Status:** Implementation baseline recorded — TSC.4 **COMPLETE** on `main`  
**Branch:** `feature/tsc4-gutenberg-coverage-expansion`  
**Authoritative plan:** [TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md](TSC4_GUTENBERG_COVERAGE_EXPANSION_IMPLEMENTATION_PLAN.md)  
**Planning validation:** [TSC4_GUTENBERG_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md](TSC4_GUTENBERG_COVERAGE_EXPANSION_PLANNING_VALIDATION_LOG.md)

## Baseline

| Field | Value |
|---|---|
| Starting `main` SHA | `8a9e0310f6340f70cb49e5c53cae886148f87cb9` |
| Frozen plan SHA | `8a9e0310f6340f70cb49e5c53cae886148f87cb9` |
| Planning baseline main HEAD (pre-freeze) | `65daa01545136968cfebd84466f52fbc9ad79035` |
| Plugin version | **1.3.0** (unchanged) |
| `Migrator::TARGET` | **7** (unchanged) |
| Schema | **STATE A** — no migration |
| ADR | **None** — ADR-0013 governs Gutenberg identity |
| GB matrix | GB1–GB25 |
| AC matrix | AC1–AC22 |
| WP ladder | TSC4.0–TSC4.4 |
| TSC.0–TSC.3 | COMPLETE |
| TSC.4 implementation at baseline | NOT STARTED |
| TSC.5–TSC.6 | NOT STARTED |

## Frozen architecture reminders

- Segment identity: `b:<uuid>:<field>`; attribute `aimlBlockId` (ADR-0013)
- Render seam: `the_content` @ priority 1 — no `render_block` / `pre_render_block`
- Block ownership: `SOURCE_POST`; four block flags default **OFF**
- Pair authority: `BlockRenderer` + adapter `get_supported_fields()`; lookup grammar-only
- TSC4.1: widen lookup to `Contract::SUPPORTED_FIELDS`; structural-attribute guard after apply
- No canonical `post_content` writes on render path; UUID save pipeline unchanged

## Explicit STOP boundaries

STOP rather than improvise if implementation requires:

- schema migration / TARGET bump / new block `source_type`
- generic HTML translation engine / URL translation / arbitrary DOM translation
- public block registration API (TSC.6) / Elementor (TSC.5)
- navigation/query/reusable/FSE/table/search/custom block admission
- `core/html`, `core/shortcode`, `core/embed` adapters
- activation default changes / version bump / tag / release / deploy
- material TSC.5/TSC.6 implementation

## Exact next steps on this branch

Implement TSC4.0–TSC4.4 per the authoritative plan, then validation, independent review, PR, merge, and closure.
