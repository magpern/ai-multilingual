# TSC.1 First-Class Taxonomy Terms — Planning Freeze Validation Log

**Status:** Planning freeze in progress on branch `docs/tsc1-first-class-taxonomy-terms-planning-freeze`
**Authoritative plan:** [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md)
**ADR:** [0021-first-class-taxonomy-term-identity-and-lazy-adoption.md](../adr/0021-first-class-taxonomy-term-identity-and-lazy-adoption.md)
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md)

## Freeze record (filled through closure)

| Field | Value |
|---|---|
| Planning baseline main HEAD | `56eff6aa172e1dd8b4f9267a11bc53afa0508f1d` |
| Baseline drift | None vs recorded baseline; `main` == `origin/main` at branch creation |
| Planning branch | `docs/tsc1-first-class-taxonomy-terms-planning-freeze` |
| Materialization commit | _(pending)_ |
| Final reviewed planning HEAD | _(pending)_ |
| External freeze review | **FREEZE** · STATE A · TARGET 7 (eight amendments) |
| Independent planning review | _(pending)_ |
| Review fixes | _(pending)_ |
| Planning PR | _(pending)_ |
| Planning CI | _(pending)_ |
| Freeze merge | _(pending)_ |
| Fresh main CI | _(pending)_ |
| Closure commit | _(pending)_ |
| Post-closure CI | _(pending)_ |
| Plugin version | **1.3.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema / migration | None (STATE A) |
| New ADR | **0021** |
| Production implementation | **NOT STARTED** |
| Tag | No new tag; existing `v1.3.0` unchanged |

## External amendments incorporated

1. Hosted retirement ignored-not-orphaned  
2. Dedicated Store `adopt_row_to_identity` (not `save_translation`)  
3. Semantic Store-column validity matrix  
4. Content-write adoption triggers; axis-only no adopt  
5. Single read-only `TermTranslationResolver`  
6. Exact visitor overlay seam table  
7. `pa_*` values vs attribute taxonomy labels  
8. Store `with_term_compat_authority` serializing axis vs adopt  

## STATE A reasoning

- Unique key `(source_type, source_id, segment_hash, language_id)` admits `source_type=term` without migration (`source_id` BIGINT fits `term_id`; `source_subtype` VARCHAR(32) fits taxonomy slugs; subtype not in uniqueness).
- No durable alias table; supersession via native existence + resolver.
- No TARGET bump; locking is application-level on existing InnoDB table.

## Independent planning review

**Verdict:** _(pending — run against materialized docs + current source)_

### Checklist

| ID | Check | Result |
|---|---|---|
| A | Identity/schema fits Store | _(pending)_ |
| B | Adoption columns/writes/locks sound | _(pending)_ |
| C | Axis/adopt race serialization sound | _(pending)_ |
| D | Resolver read-only / native precedence | _(pending)_ |
| E | OTL implications sound | _(pending)_ |
| F | Jobs implications sound | _(pending)_ |
| G | Frontend seams sound | _(pending)_ |
| H | Rank Math disposition sound | _(pending)_ |
| I | Deletion/orphan sound | _(pending)_ |
| J | Scope excludes TSC.2+ / labels / public API | _(pending)_ |

## Planning closure (after merge)

**TSC.1 Architecture Frozen** on `main` — filled in closure commit.
