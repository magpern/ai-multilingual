# TSC.1 First-Class Taxonomy Terms — Planning Freeze Validation Log

**Status:** Planning freeze on branch `docs/tsc1-first-class-taxonomy-terms-planning-freeze` — independent review **PASS**
**Authoritative plan:** [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md)
**ADR:** [0021-first-class-taxonomy-term-identity-and-lazy-adoption.md](../adr/0021-first-class-taxonomy-term-identity-and-lazy-adoption.md)
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md)

## Freeze record

| Field | Value |
|---|---|
| Planning baseline main HEAD | `56eff6aa172e1dd8b4f9267a11bc53afa0508f1d` |
| Baseline drift | None; `main` == `origin/main` at branch creation |
| Planning branch | `docs/tsc1-first-class-taxonomy-terms-planning-freeze` |
| Materialization commit | `d1c467c3081fd781ebe8d533d9587d00434d57c1` |
| Final reviewed planning HEAD | `0a0d0b57c396ae74788fb03c7c821adf71d60bd7` |
| External freeze review | **FREEZE** · STATE A · TARGET 7 (eight amendments) |
| Independent planning review | **PASS** |
| Review fixes | Gap-lock note for absent native; WP VARCHAR(32) citation; FE guard honesty; parent §6 retirement wording aligned to ADR-0021 |
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

- Unique key `(source_type, source_id, segment_hash, language_id)` admits `source_type=term` without migration.
- `source_id` BIGINT fits `term_id`; `source_subtype` VARCHAR(32) matches WP `wp_term_taxonomy.taxonomy`.
- No durable alias table; supersession via native existence + resolver.
- No TARGET bump; locking is application-level on existing InnoDB table.

## Independent planning review

**Verdict:** `TSC.1 PLANNING FREEZE REVIEW: PASS`

Falsified against source at materialization tip (Store, Schema, Surface coordinator, Woo/Rank Math hosts, OTL, Jobs). No blocking defects.

### Checklist

| ID | Check | Result |
|---|---|---|
| A | Identity/schema fits Store | **PASS** — unique key / BIGINT / VARCHAR widths |
| B | Adoption columns/writes/locks sound | **PASS** — `save_translation` unsafe confirmed; dedicated adopt required; no locks today |
| C | Axis/adopt race serialization sound | **PASS** — shared native-then-hosted order; gap-lock note added |
| D | Resolver read-only / native precedence | **PASS** |
| E | OTL implications sound | **PASS** — post-shaped gaps match TSC1.4 |
| F | Jobs implications sound | **PASS** — non-post auth skip hole confirmed; plan targets it |
| G | Frontend seams sound | **PASS** — existing Woo hooks; hard guards additive |
| H | Rank Math disposition sound | **PASS** — hosted keys/`p:rankmath:term:` match |
| I | Deletion/orphan sound | **PASS** — true orphan vs adopt retirement distinguished |
| J | Scope excludes TSC.2+ / labels / public API | **PASS** |

### Defects found

None blocking. Non-blocking documentation tighten-ups applied (see Review fixes).

### Fixes applied

1. Authority lock: absent-native unique-key `FOR UPDATE` + ban premature hosted `translation_id` lock.  
2. Cite WP `taxonomy` VARCHAR(32) for `source_subtype`.  
3. Note current FE bridge primarily `is_admin()`; TSC.1 hard guards additive.  
4. Parent §6 item 3: retire hosted as `ignored` with cleared `error_code` (not `orphaned`).

## Matrices frozen

| Matrix | Count |
|---|---|
| TT | TT1–TT40 |
| AC | AC1–AC58 |
| WP | TSC1.0–TSC1.8 |

## Planning closure

Filled after merge to `main`.
